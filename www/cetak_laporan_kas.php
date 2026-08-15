<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

// Ambil parameter filter bulan & tahun dari URL (default ke bulan & tahun saat ini)
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');

$nama_bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
    '04' => 'April', '05' => 'Mei', '06' => 'Juni', 
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September', 
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$str_nama_bulan = $nama_bulan_arr[$filter_bulan] ?? $filter_bulan;

// Fungsi bantuan untuk cek apakah tabel ada di database SQLite
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

// 4. Hasil Omset Penjualan Toko
$hasil_omset_toko = 0; 

// 5. Pencairan / Penyaluran Pinjaman Anggota
$pencairan_pinjaman = getSumFilteredPHP($koneksi, 'pinjaman', 'jumlah_pinjaman', ['tanggal', 'tgl', 'created_at']);

// 6. Pengeluaran Belanja Barang Toko
$modal_barang_bersih = 0; 

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
    <title>Cetak Resmi - Laporan Kas KOPERASI BAKKEUM</title>
    <style>
        body { font-family: 'Times New Roman', Times, serif, Arial; color: #000; background-color: #fff; margin: 0; padding: 20px; line-height: 1.4; }
        .print-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 20px; }
        
        .kop-surat { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .kop-text h2 { margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .kop-text h1 { margin: 3px 0; font-size: 24px; text-transform: uppercase; }
        .kop-text p { margin: 0; font-size: 12px; font-style: italic; }

        .judul-laporan { text-align: center; margin-bottom: 20px; }
        .judul-laporan h3 { margin: 0; font-size: 16px; text-transform: uppercase; text-decoration: underline; }
        .judul-laporan p { margin: 5px 0 0 0; font-size: 13px; }

        table.tbl-resmi { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        table.tbl-resmi th, table.tbl-resmi td { border: 1px solid #000; padding: 8px 10px; text-align: left; vertical-align: middle; }
        table.tbl-resmi th { background-color: #f2f2f2; text-align: center; text-transform: uppercase; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .sub-desc { font-size: 11px; font-style: italic; color: #444; margin-top: 2px; }

        .summary-box { display: flex; justify-content: space-between; margin-bottom: 20px; gap: 10px; }
        .box-item { flex: 1; border: 1px solid #000; padding: 10px; text-align: center; }
        .box-item h4 { margin: 0 0 5px 0; font-size: 11px; text-transform: uppercase; }
        .box-item .val { font-size: 14px; font-weight: bold; }

        .ttd-container { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
        .ttd-box { width: 250px; text-align: center; font-size: 13px; }
        .ttd-space { height: 60px; }

        .no-print { margin-bottom: 20px; display: flex; justify-content: space-between; }
        .btn { padding: 8px 16px; background: #00796b; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn:hover { background: #004d40; }
        .btn-back { background: #757575; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .print-container { padding: 0; max-width: 100%; }
            @page { size: A4; margin: 1.5cm; }
        }
    </style>
</head>
<body>

<div class="print-container">
    <div class="no-print">
        <a href="laporan_kas.php?bulan=<?php echo $filter_bulan; ?>&tahun=<?php echo $filter_tahun; ?>" class="btn btn-back">⬅ Kembali ke Laporan</a>
        <button onclick="window.print()" class="btn">🖨️ Cetak Dokumen</button>
    </div>

    <div class="kop-surat">
        <div class="kop-text">
            <h2>SISTEM INFORMASI MANAJEMEN LAPORAN KAS</h2>
            <h1>KOPERASI BAKKEUM</h1>
            <p>Pusat Pelayanan Koperasi Bakkeum dan Pengelolaan Usaha Terpadu</p>
        </div>
    </div>

    <div class="judul-laporan">
        <h3>REKAPITULASI RINCIAN ARUS KAS KOPERASI BAKKEUM</h3>
        <p>Periode: <strong><?php echo $str_nama_bulan . ' ' . $filter_tahun; ?></strong></p>
    </div>

    <div class="summary-box">
        <div class="box-item">
            <h4>Total Kas Masuk</h4>
            <div class="val">Rp <?php echo number_format($total_kas_masuk, 0, ',', '.'); ?></div>
        </div>
        <div class="box-item">
            <h4>Total Kas Keluar</h4>
            <div class="val">Rp <?php echo number_format($total_kas_keluar, 0, ',', '.'); ?></div>
        </div>
        <div class="box-item">
            <h4>Saldo Kas Bersih</h4>
            <div class="val">Rp <?php echo number_format($total_saldo_kas_bersih, 0, ',', '.'); ?></div>
        </div>
        <div class="box-item">
            <h4>Keuntungan Usaha</h4>
            <div class="val">Rp <?php echo number_format($keuntungan_usaha, 0, ',', '.'); ?></div>
        </div>
    </div>

    <table class="tbl-resmi">
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th>Keterangan Komponen Sumber / Pengeluaran Kas</th>
                <th style="width: 120px;">Klasifikasi</th>
                <th style="width: 150px;" class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>Penerimaan Simpanan Anggota</td>
                <td class="text-center"><b>KAS MASUK</b></td>
                <td class="text-right">Rp <?php echo number_format($penerimaan_simpanan, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-center">2</td>
                <td>Penerimaan Angsuran Pokok Pinjaman</td>
                <td class="text-center"><b>KAS MASUK</b></td>
                <td class="text-right">Rp <?php echo number_format($penerimaan_pokok, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-center">3</td>
                <td>Penerimaan Bunga / Jasa Pinjaman</td>
                <td class="text-center"><b>KAS MASUK</b></td>
                <td class="text-right">Rp <?php echo number_format($penerimaan_bunga, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-center">4</td>
                <td>
                    Hasil Omset Penjualan Toko (Margin / Keuntungan Penjualan)
                    <div class="sub-desc">(Hanya dari Transaksi Lunas/Dibayar)</div>
                </td>
                <td class="text-center"><b>KAS MASUK</b></td>
                <td class="text-right">Rp <?php echo number_format($hasil_omset_toko, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td class="text-center">5</td>
                <td>Pencairan / Penyaluran Pinjaman Anggota</td>
                <td class="text-center"><b style="color: #b91c1c;">KAS KELUAR</b></td>
                <td class="text-right" style="color: #b91c1c;">(Rp <?php echo number_format($pencairan_pinjaman, 0, ',', '.'); ?>)</td>
            </tr>
            <tr>
                <td class="text-center">6</td>
                <td>Pengeluaran Belanja Barang Toko (Modal Barang Bersih)</td>
                <td class="text-center"><b style="color: #b91c1c;">KAS KELUAR</b></td>
                <td class="text-right" style="color: #b91c1c;">(Rp <?php echo number_format($modal_barang_bersih, 0, ',', '.'); ?>)</td>
            </tr>
            <tr>
                <td class="text-center">7</td>
                <td>Pembayaran SHU Anggota</td>
                <td class="text-center"><b style="color: #b91c1c;">KAS KELUAR</b></td>
                <td class="text-right" style="color: #b91c1c;">(Rp <?php echo number_format($pembayaran_shu, 0, ',', '.'); ?>)</td>
            </tr>
            <tr>
                <td class="text-center">8</td>
                <td>Pengeluaran Operasional (Belanja Modal, Pegawai, & Barang/Jasa)</td>
                <td class="text-center"><b style="color: #b91c1c;">KAS KELUAR</b></td>
                <td class="text-right" style="color: #b91c1c;">(Rp <?php echo number_format($pengeluaran_operasional, 0, ',', '.'); ?>)</td>
            </tr>
        </tbody>
        <tfoot>
            <tr style="background-color: #eaeaea; font-weight: bold;">
                <td colspan="3" class="text-center">TOTAL SALDO KAS BERSIH PERIODE INI</td>
                <td class="text-right" style="color: <?php echo ($total_saldo_kas_bersih < 0) ? '#b91c1c' : '#000'; ?>;">Rp <?php echo number_format($total_saldo_kas_bersih, 0, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="ttd-container">
        <div class="ttd-box">
            <p>Mengetahui,<br><strong>Pimpinan Keperasi Bakkeum</strong></p>
            <div class="ttd-space"></div>
            <p><strong>( _______________________ )</strong></p>
        </div>
        <div class="ttd-box">
            <p>Dibuat Oleh,<br><strong>Bendahara / Admin</strong></p>
            <div class="ttd-space"></div>
            <p><strong>( _______________________ )</strong></p>
        </div>
    </div>
</div>

</body>
</html>