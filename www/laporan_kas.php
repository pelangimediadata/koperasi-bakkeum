<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

$is_admin = isset($_SESSION['login_admin']);
$nama_user = $_SESSION['nama'] ?? 'Admin';

// Filter bulan & tahun yang dipilih
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

// Fungsi bantuan untuk cek tabel
function isTableExists($db, $tableName) {
    try {
        $chk = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'");
        return ($chk && $chk->fetchColumn());
    } catch (Exception $e) {
        return false;
    }
}

// Fungsi untuk mengekstrak bulan dan tahun dari berbagai format tanggal (DD-MM-YYYY, YYYY-MM-DD, dll)
function extractMonthYear($dateStr) {
    if (empty($dateStr)) return ['bulan' => '', 'tahun' => ''];
    
    // Jika format DD-MM-YYYY atau DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})/', $dateStr, $matches)) {
        return ['bulan' => str_pad($matches[2], 2, '0', STR_PAD_LEFT), 'tahun' => $matches[3]];
    }
    // Jika format YYYY-MM-DD
    if (preg_match('/^(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})/', $dateStr, $matches)) {
        return ['bulan' => str_pad($matches[2], 2, '0', STR_PAD_LEFT), 'tahun' => $matches[1]];
    }
    
    // Fallback menggunakan strtotime jika format lain
    $time = strtotime($dateStr);
    if ($time) {
        return ['bulan' => date('m', $time), 'tahun' => date('Y', $time)];
    }
    
    return ['bulan' => '', 'tahun' => ''];
}

// Fungsi untuk menjumlahkan data dengan memfilter bulan & tahun di sisi PHP secara aman
function getSumFilteredPHP($db, $tableName, $sumColumn, $dateColumnNames = [], $extraCondition = null) {
    global $filter_bulan, $filter_tahun;
    if (!isTableExists($db, $tableName)) return 0;
    
    try {
        $rows = $db->query("SELECT * FROM $tableName")->fetchAll(PDO::FETCH_ASSOC);
        $total = 0;
        
        foreach ($rows as $row) {
            // Jalankan kondisi tambahan jika ada (misal status lunas)
            if ($extraCondition && is_callable($extraCondition)) {
                if (!$extraCondition($row)) continue;
            }
            
            // Cari kolom tanggal yang cocok pada baris data
            $dateVal = '';
            foreach ($dateColumnNames as $col) {
                if (!empty($row[$col])) {
                    $dateVal = $row[$col];
                    break;
                }
            }
            
            // Jika kolom tanggal tidak ditemukan secara spesifik, cari kolom apa saja yang formatnya mirip tanggal
            if (empty($dateVal)) {
                foreach ($row as $k => $v) {
                    if (strpos(strtolower($k), 'tgl') !== false || strpos(strtolower($k), 'tanggal') !== false || strpos(strtolower($k), 'date') !== false || strpos(strtolower($k), 'time') !== false) {
                        if (!empty($v)) {
                            $dateVal = $v;
                            break;
                        }
                    }
                }
            }
            
            $my = extractMonthYear($dateVal);
            
            // Jika bulan dan tahun cocok dengan filter yang dipilih, tambahkan ke total
            if ($my['bulan'] === $filter_bulan && $my['tahun'] === $filter_tahun) {
                $total += floatval($row[$sumColumn] ?? 0);
            }
        }
        return $total;
    } catch (Exception $e) {
        return 0;
    }
}

// 1. Penerimaan Simpanan Anggota
$penerimaan_simpanan = getSumFilteredPHP($koneksi, 'simpanan', 'jumlah', ['tanggal', 'tgl', 'created_at']);

// 2. Penerimaan Angsuran Pokok Pinjaman
$penerimaan_pokok = getSumFilteredPHP($koneksi, 'pembayaran', 'jumlah_bayar', ['tanggal', 'tgl', 'tanggal_bayar', 'tgl_bayar']);

// 3. Penerimaan Bunga / Jasa Pinjaman
$penerimaan_bunga = getSumFilteredPHP($koneksi, 'pembayaran', 'bayar_bunga', ['tanggal', 'tgl', 'tanggal_bayar', 'tgl_bayar']);

// 4. Hasil Omset Penjualan Toko (Dihitung otomatis berdasarkan margin / keuntungan penjualan item)
$hasil_omset_toko = 0;
if (isTableExists($koneksi, 'penjualan') && isTableExists($koneksi, 'barang')) {
    try {
        $q_penjualan = $koneksi->query("
            SELECT p.*, b.harga_beli 
            FROM penjualan p 
            LEFT JOIN barang b ON p.id_barang = b.id_barang
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($q_penjualan as $row) {
            $status_bayar = strtoupper($row['status_bayar'] ?? 'LUNAS');
            // Hanya hitung jika status lunas atau dibayar sebagian
            if ($status_bayar === 'LUNAS' || $status_bayar === 'BAYAR SEBAGIAN') {
                // Cari kolom tanggal transaksi
                $tgl_transaksi = $row['tanggal_transaksi'] ?? $row['tanggal'] ?? $row['tgl'] ?? '';
                $my = extractMonthYear($tgl_transaksi);

                if ($my['bulan'] === $filter_bulan && $my['tahun'] === $filter_tahun) {
                    $harga_satuan = floatval($row['harga_satuan'] ?? 0);
                    $harga_beli = floatval($row['harga_beli'] ?? 0);
                    $jumlah_item = floatval($row['jumlah'] ?? 0);
                    
                    // Margin = (Harga Jual - Harga Beli) * Jumlah
                    $margin = ($harga_satuan - $harga_beli) * $jumlah_item;
                    if ($margin > 0) {
                        $hasil_omset_toko += $margin;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $hasil_omset_toko = 0;
    }
}

// 5. Pencairan / Penyaluran Pinjaman Anggota
$pencairan_pinjaman = getSumFilteredPHP($koneksi, 'pinjaman', 'jumlah_pinjaman', ['tanggal', 'tgl', 'created_at']);

// 6. Pengeluaran Belanja Barang Toko
$modal_barang_bersih = getSumFilteredPHP($koneksi, 'belanja_toko', 'total_belanja', ['tanggal', 'tgl', 'created_at']);

// 7. Pembayaran SHU Anggota (Hanya status Lunas / Lunas Terbayar)
$pembayaran_shu = getSumFilteredPHP($koneksi, 'shu_pembayaran', 'jumlah_dibayar', ['tanggal', 'tgl', 'tanggal_bayar', 'tgl_bayar'], function($row) {
    $status = strtolower($row['status'] ?? '');
    return in_array($status, ['lunas', 'lunas terbayar']);
});

// 8. PENGELUARAN OPERASIONAL 
$pengeluaran_operasional = getSumFilteredPHP($koneksi, 'operasional', 'jumlah', ['tanggal', 'tgl', 'created_at'], function($row) {
    return strtolower($row['jenis_transaksi'] ?? '') === 'masuk' || true; 
});

// Total Kas Masuk & Keluar
$total_kas_masuk = $penerimaan_simpanan + $penerimaan_pokok + $penerimaan_bunga + $hasil_omset_toko;
$total_kas_keluar = $pencairan_pinjaman + $modal_barang_bersih + $pembayaran_shu + $pengeluaran_operasional;
$total_saldo_kas_bersih = $total_kas_masuk - $total_kas_keluar;
$keuntungan_usaha = $hasil_omset_toko + $penerimaan_bunga; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan & Arus Kas - KOPERASI BAKKEUM</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364, #004d40, #00796b);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
            color: #333;
            display: flex;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .app-container { display: flex; min-height: 100vh; width: 100%; }
        .main-content { flex-grow: 1; padding: 25px 30px; overflow-y: auto; background: transparent; }

        .dashboard-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .dashboard-title-box h2 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            margin: 0;
        }

        .dashboard-title-box p {
            color: #b2dfdb;
            font-size: 12px;
            margin: 3px 0 0 0;
        }

        .header-live-clock {
            background: rgba(0, 77, 64, 0.6);
            color: #e0f2f1;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .top-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 25px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .top-section-header h1 {
            font-size: 20px;
            color: #004d40;
            margin-bottom: 4px;
        }

        .top-section-header p {
            font-size: 13px;
            color: #666;
        }

        .filter-form-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form-group select {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            background: #fff;
            color: #333;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }

        .btn-success { background-color: #00796b; color: white; box-shadow: 0 4px 12px rgba(0, 121, 107, 0.3); }
        .btn-filter { background-color: #0284c7; color: white; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3); }

        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: #ffffff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid #ccc;
        }

        .summary-card.masuk { border-left-color: #10b981; }
        .summary-card.keluar { border-left-color: #ef4444; }
        .summary-card.saldo { border-left-color: #0284c7; }
        .summary-card.keuntungan { border-left-color: #f97316; }

        .summary-card .card-label {
            font-size: 11px;
            font-weight: 700;
            color: #666;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .summary-card .card-value {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .summary-card.masuk .card-value { color: #10b981; }
        .summary-card.keluar .card-value { color: #ef4444; }
        .summary-card.saldo .card-value { color: #0284c7; }
        .summary-card.keuntungan .card-value { color: #f97316; }

        .card-box { 
            background: rgba(255, 255, 255, 0.96); 
            padding: 25px; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background-color: #00796b;
            color: #ffffff;
            padding: 12px 15px;
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #333333;
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: #f0fdf4;
        }

        @media print {
            body { background: white; }
            .app-container { display: block; }
            .dashboard-header-flex, sidebar, .btn, form { display: none !important; }
            .card-box, .top-section-header, .summary-card { box-shadow: none; border: 1px solid #ddd; background: white; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>KOPERASI BAKKEUM</h2>
                <p>Sistem Informasi Manajemen Keuangan Koperasi Bakkeum</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="top-section-header">
            <div>
                <h1>Laporan Keuangan & Arus Kas</h1>
                <p>Rekapitulasi total kas masuk, kas keluar, dan keuntungan usaha koperasi.</p>
            </div>
            
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <form method="GET" action="" class="filter-form-group">
                    <select name="bulan">
                        <?php
                        $nama_bulan_arr = [
                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
                            '04' => 'April', '05' => 'Mei', '06' => 'Juni', 
                            '07' => 'Juli', '08' => 'Agustus', '09' => 'September', 
                            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                        ];
                        foreach ($nama_bulan_arr as $num => $b) {
                            $selected = ($filter_bulan == $num) ? 'selected' : '';
                            echo "<option value='$num' $selected>$b</option>";
                        }
                        ?>
                    </select>
                    
                    <select name="tahun">
                        <?php
                        $tahun_sekarang = date('Y');
                        for ($t = $tahun_sekarang; $t >= $tahun_sekarang - 4; $t--) {
                            $selected = ($filter_tahun == $t) ? 'selected' : '';
                            echo "<option value='$t' $selected>$t</option>";
                        }
                        ?>
                    </select>
                    
                    <button type="submit" class="btn btn-filter">🔍 Filter</button>
                </form>

                <a href="cetak_laporan_kas.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" target="_blank" class="btn btn-success">
                    🖨️ Cetak Laporan Resmi
                </a>
            </div>
        </div>

        <div class="summary-cards-grid">
            <div class="summary-card masuk">
                <div class="card-label">Total Kas Masuk</div>
                <div class="card-value">Rp <?php echo number_format($total_kas_masuk, 0, ',', '.'); ?></div>
            </div>
            <div class="summary-card keluar">
                <div class="card-label">Total Kas Keluar</div>
                <div class="card-value">Rp <?php echo number_format($total_kas_keluar, 0, ',', '.'); ?></div>
            </div>
            <div class="summary-card saldo">
                <div class="card-label">Saldo Kas Bersih</div>
                <div class="card-value">Rp <?php echo number_format($total_saldo_kas_bersih, 0, ',', '.'); ?></div>
            </div>
            <div class="summary-card keuntungan">
                <div class="card-label">Keuntungan Usaha</div>
                <div class="card-value">Rp <?php echo number_format($keuntungan_usaha, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="card-box" id="rekapKasCard">
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="color: #004d40; font-size: 18px; text-transform: uppercase; font-weight: 700;">REKAPITULASI RINCIAN ARUS KAS KOPERASI BAKKEUM</h3>
                <p style="font-size: 12px; color: #666; margin-top: 4px;">Periode: <?php echo ($nama_bulan_arr[$filter_bulan] ?? $filter_bulan) . ' ' . $filter_tahun; ?></p>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Keterangan Komponen Sumber / Pengeluaran Kas</th>
                            <th>Klasifikasi</th>
                            <th style="text-align: right; width: 180px;">Jumlah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;">1</td>
                            <td>Penerimaan Simpanan Anggota</td>
                            <td><b style="color: #155724;">KAS MASUK</b></td>
                            <td style="text-align: right; font-weight: 600;">Rp <?php echo number_format($penerimaan_simpanan, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">2</td>
                            <td>Penerimaan Angsuran Pokok Pinjaman</td>
                            <td><b style="color: #155724;">KAS MASUK</b></td>
                            <td style="text-align: right; font-weight: 600;">Rp <?php echo number_format($penerimaan_pokok, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">3</td>
                            <td>Penerimaan Bunga / Jasa Pinjaman</td>
                            <td><b style="color: #155724;">KAS MASUK</b></td>
                            <td style="text-align: right; font-weight: 600;">Rp <?php echo number_format($penerimaan_bunga, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">4</td>
                            <td>Hasil Omset Penjualan Toko (Margin / Keuntungan Penjualan) <br><i style="font-size: 11px; color: #666;">(Hanya dari Transaksi Lunas/Dibayar)</i></td>
                            <td><b style="color: #155724;">KAS MASUK</b></td>
                            <td style="text-align: right; font-weight: 600;">Rp <?php echo number_format($hasil_omset_toko, 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">5</td>
                            <td>Pencairan / Penyaluran Pinjaman Anggota</td>
                            <td><b style="color: #b91c1c;">KAS KELUAR</b></td>
                            <td style="text-align: right; color: #b91c1c; font-weight: 600;">(Rp <?php echo number_format($pencairan_pinjaman, 0, ',', '.'); ?>)</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">6</td>
                            <td>Pengeluaran Belanja Barang Toko (Modal Barang Bersih)</td>
                            <td><b style="color: #b91c1c;">KAS KELUAR</b></td>
                            <td style="text-align: right; color: #b91c1c; font-weight: 600;">(Rp <?php echo number_format($modal_barang_bersih, 0, ',', '.'); ?>)</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">7</td>
                            <td>Pembayaran SHU Anggota <br><i style="font-size: 11px; color: #666;">(Terintegrasi otomatis dari status Lunas Terbayar)</i></td>
                            <td><b style="color: #b91c1c;">KAS KELUAR</b></td>
                            <td style="text-align: right; color: #b91c1c; font-weight: 600;">(Rp <?php echo number_format($pembayaran_shu, 0, ',', '.'); ?>)</td>
                        </tr>
                        <tr>
                            <td style="text-align: center;">8</td>
                            <td>Pengeluaran Operasional (Belanja Modal, Pegawai, & Barang/Jasa)</td>
                            <td><b style="color: #b91c1c;">KAS KELUAR</b></td>
                            <td style="text-align: right; color: #b91c1c; font-weight: 600;">(Rp <?php echo number_format($pengeluaran_operasional, 0, ',', '.'); ?>)</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f1f8f6; font-weight: bold;">
                            <td colspan="3" style="text-align: right; padding: 15px; border-top: 2px solid #00796b; font-size: 14px;">TOTAL SALDO KAS BERSIH PERIODE INI:</td>
                            <td style="text-align: right; padding: 15px; color: <?php echo ($total_saldo_kas_bersih < 0) ? '#b91c1c' : '#004d40'; ?>; border-top: 2px solid #00796b; font-size: 15px;">
                                Rp <?php echo number_format($total_saldo_kas_bersih, 0, ',', '.'); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('timeText').textContent = `${hours}:${minutes}:${seconds}`;
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>

</body>
</html>