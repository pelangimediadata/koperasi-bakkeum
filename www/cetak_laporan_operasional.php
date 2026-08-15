<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_kategori = $_GET['kategori_filter'] ?? '';

$periode_target = sprintf("%04d-%02d", $filter_tahun, $filter_bulan);
$where_clause = "WHERE tanggal LIKE ?";
$params_filter = ["%$periode_target%"];

if (!empty($filter_kategori)) {
    $where_clause .= " AND kategori = ?";
    $params_filter[] = $filter_kategori;
}

$nama_bln = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Resmi - Koperasi Bakkeum</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 20px; background: #fff; }
        .kop-surat { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h2 { font-size: 18px; font-weight: bold; text-transform: uppercase; margin: 0; color: #000; }
        .kop-surat p { font-size: 12px; color: #333; margin: 5px 0 0 0; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 12px; margin-top: 10px; }
        table th { background-color: #044b3b !important; color: white !important; padding: 8px 10px; font-weight: 600; border: 1px solid #000; -webkit-print-color-adjust: exact; }
        table td { padding: 6px 8px; border: 1px solid #000; color: #333; }
        .print-signature { margin-top: 30px; float: right; text-align: center; width: 220px; page-break-inside: avoid; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">🖨️ Cetak Dokumen</button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Tutup</button>
    </div>

    <div class="kop-surat">
        <h2>KOPERASI KELOMPOK / UNIT USAHA BAKKEUM</h2>
        <p>Laporan Resmi Buku Kas Anggaran Operasional Periode <?php echo $nama_bln[(int)$filter_bulan] . ' ' . $filter_tahun; ?></p>
    </div>

    <?php
    $params_sum = $params_filter;
    
    $stmt_sum_masuk = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND jenis_transaksi = 'Masuk'");
    $stmt_sum_masuk->execute($params_sum);
    $total_pemasukan = $stmt_sum_masuk->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_sum_modal = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Modal'");
    $stmt_sum_modal->execute($params_sum);
    $total_belanja_modal = $stmt_sum_modal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_sum_pegawai = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Pegawai (Honor Pengurus)'");
    $stmt_sum_pegawai->execute($params_sum);
    $total_belanja_pegawai = $stmt_sum_pegawai->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt_sum_bj = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Barang dan Jasa'");
    $stmt_sum_bj->execute($params_sum);
    $total_belanja_bj = $stmt_sum_bj->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $total_pengeluaran = $total_belanja_modal + $total_belanja_pegawai + $total_belanja_bj;
    $saldo_netto = $total_pemasukan - $total_pengeluaran;
    ?>

<!-- Bagian Ringkasan Kartu dengan Gaya Garis Samping (Sesuai Gambar 2) -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
        <!-- Pemasukan -->
        <div style="padding: 5px 0 5px 12px; border-left: 4px solid #16a34a;">
            <div style="font-size: 11px; font-weight: bold; color: #16a34a; text-transform: uppercase;">Total Pemasukan</div>
            <div style="font-size: 16px; font-weight: bold; color: #000; margin-top: 2px;">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></div>
        </div>
        <!-- Belanja Modal -->
        <div style="padding: 5px 0 5px 12px; border-left: 4px solid #f97316;">
            <div style="font-size: 11px; font-weight: bold; color: #f97316; text-transform: uppercase;">Belanja Modal</div>
            <div style="font-size: 16px; font-weight: bold; color: #000; margin-top: 2px;">Rp <?php echo number_format($total_belanja_modal, 0, ',', '.'); ?></div>
        </div>
        <!-- Belanja Pegawai -->
        <div style="padding: 5px 0 5px 12px; border-left: 4px solid #0284c7;">
            <div style="font-size: 11px; font-weight: bold; color: #0284c7; text-transform: uppercase;">Belanja Pegawai (Honor)</div>
            <div style="font-size: 16px; font-weight: bold; color: #000; margin-top: 2px;">Rp <?php echo number_format($total_belanja_pegawai, 0, ',', '.'); ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
        <!-- Barang & Jasa -->
        <div style="padding: 5px 0 5px 12px; border-left: 4px solid #db2777;">
            <div style="font-size: 11px; font-weight: bold; color: #db2777; text-transform: uppercase;">Barang & Jasa</div>
            <div style="font-size: 16px; font-weight: bold; color: #000; margin-top: 2px;">Rp <?php echo number_format($total_belanja_bj, 0, ',', '.'); ?></div>
        </div>
    </div>

    <!-- Sisa Saldo Netto -->
    <div style="padding: 12px 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #cbd5e1; background: #f8fafc; margin-bottom: 25px;">
        <div>
            <strong style="color: #0f172a; font-size: 12px;">Sisa Saldo Netto Operasional (Pemasukan - Total Pengeluaran):</strong>
        </div>
        <div style="font-size: 16px; font-weight: bold; color: <?php echo $saldo_netto >= 0 ? '#0f766e' : '#b91c1c'; ?>;">
            Rp <?php echo number_format($saldo_netto, 0, ',', '.'); ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 35px; text-align: center;">No</th>
                <th style="width: 80px;">Tanggal</th>
                <th>Uraian / Keterangan</th>
                <th style="width: 90px;">No. Bukti</th>
                <th style="text-align: right; width: 100px;">Masuk (Rp)</th>
                <th style="text-align: right; width: 100px;">Keluar (Rp)</th>
                <th style="text-align: right; width: 110px;">Saldo (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt_buku = $koneksi->prepare("SELECT * FROM operasional $where_clause ORDER BY tanggal ASC, id_operasional ASC");
            $stmt_buku->execute($params_filter);
            $rows_buku = $stmt_buku->fetchAll(PDO::FETCH_ASSOC);

            $no_bk = 1;
            $running_saldo = 0;
            $sum_total_masuk = 0;
            $sum_total_keluar = 0;

            if (count($rows_buku) > 0) {
                foreach ($rows_buku as $rb) {
                    $is_m = ($rb['jenis_transaksi'] === 'Masuk');
                    $val_masuk = $is_m ? (float)$rb['jumlah'] : 0;
                    $val_keluar = !$is_m ? (float)$rb['jumlah'] : 0;
                    
                    if ($is_m) {
                        $running_saldo += $val_masuk;
                        $sum_total_masuk += $val_masuk;
                    } else {
                        $running_saldo -= $val_keluar;
                        $sum_total_keluar += $val_keluar;
                    }
                    ?>
                    <tr>
                        <td style="text-align: center;"><?php echo $no_bk++; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($rb['tanggal'])); ?></td>
                        <td>
                            <b><?php echo htmlspecialchars($rb['kategori']); ?></b><br>
                            <span style="font-size: 11px;"><?php echo htmlspecialchars($rb['keterangan']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($rb['nomor_bukti'] ?: '-'); ?></td>
                        <td style="text-align: right;"><?php echo $val_masuk > 0 ? number_format($val_masuk, 0, ',', '.') : '-'; ?></td>
                        <td style="text-align: right;"><?php echo $val_keluar > 0 ? number_format($val_keluar, 0, ',', '.') : '-'; ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo number_format($running_saldo, 0, ',', '.'); ?></td>
                    </tr>
                    <?php
                }
                ?>
                <tr style="font-weight: bold; background: #f2f2f2;">
                    <td colspan="4" style="text-align: right;">TOTAL BULAN INI:</td>
                    <td style="text-align: right;">Rp <?php echo number_format($sum_total_masuk, 0, ',', '.'); ?></td>
                    <td style="text-align: right;">Rp <?php echo number_format($sum_total_keluar, 0, ',', '.'); ?></td>
                    <td style="text-align: right;">Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></td>
                </tr>
                <?php
            } else {
                echo "<tr><td colspan='7' style='text-align:center; padding: 15px;'>Tidak ada transaksi buku kas pada periode ini.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="print-signature">
        <p>Pengurus Koperasi,</p>
        <br><br><br>
        <p><b>( _________________________ )</b></p>
    </div>

</body>
</html>