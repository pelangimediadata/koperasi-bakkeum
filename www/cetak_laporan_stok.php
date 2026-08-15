<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$tanggal_cetak = $_GET['tanggal'] ?? date('Y-m-d');
$nama_petugas = $_SESSION['nama_admin'] ?? $_SESSION['username'] ?? 'Petugas Koperasi';

// Query untuk menghitung sisa stok historis berdasarkan tanggal laporan:
// Stok saat ini ditambah jumlah penjualan yang terjadi SETELAH tanggal laporan,
// dikurangi jumlah penambahan/kulakan yang terjadi SETELAH tanggal laporan.
$sql_stok = "
    SELECT 
        b.id_barang,
        b.nama_barang,
        b.harga_beli,
        b.harga_jual,
        (
            b.stok 
            + COALESCE((
                SELECT SUM(p.jumlah) 
                FROM penjualan p 
                WHERE p.id_barang = b.id_barang 
                  AND p.tanggal_transaksi > :tgl_lap1
            ), 0)
            - COALESCE((
                SELECT SUM(
                    CAST(
                        SUBSTR(bt.keterangan, INSTR(bt.keterangan, '[IDBRG:') + 7, INSTR(SUBSTR(bt.keterangan, INSTR(bt.keterangan, '[IDBRG:') + 7), ']') - 1) 
                    AS INTEGER)
                ) 
                FROM belanja_toko bt 
                WHERE bt.tanggal > :tgl_lap2
                  AND bt.keterangan LIKE '%[IDBRG:' || b.id_barang || ']%'
            ), 0)
        ) AS stok_historis
    FROM barang b
    ORDER BY b.nama_barang ASC
";

$stmt_stok = $koneksi->prepare($sql_stok);
$stmt_stok->execute([
    'tgl_lap1' => $tanggal_cetak,
    'tgl_lap2' => $tanggal_cetak
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Stok Barang - Koperasi Bakkeum</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #000;
            background: #fff;
            padding: 20px;
            font-size: 13px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h2, .header h3 { margin: 0; }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .signature-section {
            float: right;
            text-align: center;
            width: 250px;
            margin-top: 20px;
        }
        .signature-space {
            height: 60px;
        }
        .filter-tanggal {
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .filter-tanggal { display: none; }
        }
    </style>
</head>
<body>

    <!-- Form Filter Pilihan Tanggal Laporan -->
    <div class="filter-tanggal no-print">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 10px; width: 100%;">
            <label for="tanggal"><b>Pilih Tanggal Laporan:</b></label>
            <input type="date" id="tanggal" name="tanggal" value="<?php echo htmlspecialchars($tanggal_cetak); ?>" style="padding: 5px; font-size: 13px;">
            <button type="submit" style="padding: 5px 12px; font-size: 13px; cursor: pointer;">Terapkan</button>
            <button type="button" onclick="window.print()" style="margin-left: auto; padding: 5px 12px; font-size: 13px; cursor: pointer; background: #28a745; color: #fff; border: none; border-radius: 3px;">🖨️ Cetak / Print</button>
        </form>
    </div>

    <div class="header">
        <h3>KOPERASI BAKKEUM</h3>
        <h2>LAPORAN STOK BARANG TOKO</h2>
    </div>

    <div class="meta-info">
        <div><b>Tanggal Laporan:</b> <?php echo date('d-m-Y', strtotime($tanggal_cetak)); ?></div>
        <div><b>Dicetak Oleh:</b> <?php echo htmlspecialchars($nama_petugas); ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th>Nama Barang</th>
                <th style="text-align: right;">Harga Modal</th>
                <th style="text-align: right;">Harga Jual</th>
                <th style="text-align: center;">Sisa Stok</th>
                <th style="text-align: right;">Total Nilai Aset (Modal)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $grand_total_aset = 0;
            $rows_stok = $stmt_stok ? $stmt_stok->fetchAll(PDO::FETCH_ASSOC) : [];
            if (count($rows_stok) > 0) {
                foreach ($rows_stok as $row) {
                    $stok_tampil = max(0, (int)$row['stok_historis']);
                    $nilai_aset = $row['harga_beli'] * $stok_tampil;
                    $grand_total_aset += $nilai_aset;
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><b><?php echo htmlspecialchars($row['nama_barang']); ?></b></td>
                        <td class="text-right">Rp <?php echo number_format($row['harga_beli'], 0, ',', '.'); ?></td>
                        <td class="text-right">Rp <?php echo number_format($row['harga_jual'], 0, ',', '.'); ?></td>
                        <td class="text-center"><b><?php echo $stok_tampil; ?></b></td>
                        <td class="text-right">Rp <?php echo number_format($nilai_aset, 0, ',', '.'); ?></td>
                    </tr>
                    <?php
                }
            } else {
                echo "<tr><td colspan='6' class='text-center'>Tidak ada data stok barang.</td></tr>";
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right; font-weight: bold;">TOTAL NILAI ASET STOK BARANG:</td>
                <td class="text-right" style="font-weight: bold;">Rp <?php echo number_format($grand_total_aset, 0, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <p>Petugas / Pengelola Toko</p>
        <div class="signature-space"></div>
        <p><b>( _________________________ )</b></p>
    </div>

    <div class="no-print" style="clear: both; text-align: center; margin-top: 40px;">
        <button onclick="window.print()" style="padding: 8px 16px; font-size: 14px; cursor: pointer;">Cetak Ulang</button>
        <a href="toko.php" style="display: inline-block; margin-left: 10px; padding: 8px 16px; font-size: 14px; text-decoration: none; background: #6c757d; color: #fff; border-radius: 4px;">Kembali ke Toko</a>
    </div>

</body>
</html>