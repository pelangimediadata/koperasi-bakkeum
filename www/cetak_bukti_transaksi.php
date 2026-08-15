<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Ambil Data Operasional menggunakan PDO SQLite Prepared Statement
$stmt = $koneksi->prepare("SELECT * FROM operasional WHERE id_operasional = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Data transaksi tidak ditemukan.");
}
$is_masuk = ($data['jenis_transaksi'] === 'Masuk');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti <?php echo htmlspecialchars($data['jenis_transaksi']); ?> Anggaran - <?php echo htmlspecialchars($data['nomor_bukti']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #000; background: #fff; padding: 20px; }
        .kwitansi-box { width: 100%; max-width: 700px; margin: 0 auto; border: 2px solid #000; padding: 25px; border-radius: 8px; }
        .kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop h2 { font-size: 16px; margin: 0; text-transform: uppercase; }
        .kop p { font-size: 12px; margin: 3px 0 0 0; }
        .title-trx { text-align: center; font-size: 15px; font-weight: bold; text-decoration: underline; margin-bottom: 20px; text-transform: uppercase; }
        .details-table { width: 100%; font-size: 14px; margin-bottom: 25px; border-collapse: collapse; }
        .details-table td { padding: 8px 5px; vertical-align: top; }
        .nominal-box { background: #f1f1f1; padding: 10px 15px; font-weight: bold; font-size: 16px; border: 1px dashed #444; margin-bottom: 25px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; font-size: 13px; }
        .sig-box { width: 200px; }
        .btn-print { background: #17a2b8; color: white; border: none; padding: 10px 20px; font-size: 14px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; display: block; margin-left: auto; margin-right: auto; }
        @media print { .btn-print { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Cetak Bukti Ini</button>

<div class="kwitansi-box">
    <div class="kop">
        <h2>KOPERASI KELOMPOK / UNIT USAHA BAKKEUM</h2>
        <p>Bukti Resmi Arus Kas Operasional Keuangan</p>
    </div>

    <div class="title-trx">BUKTI KAS <?php echo strtoupper(htmlspecialchars($data['jenis_transaksi'])); ?></div>

    <table class="details-table">
        <tr>
            <td style="width: 160px;"><b>No. Bukti / Kwitansi</b></td>
            <td style="width: 10px;">:</td>
            <td><b><?php echo htmlspecialchars($data['nomor_bukti'] ?: '-'); ?></b></td>
        </tr>
        <tr>
            <td><b>Tanggal Transaksi</b></td>
            <td>:</td>
            <td><?php echo date('d-m-Y', strtotime($data['tanggal'])); ?></td>
        </tr>
        <tr>
            <td><b>Kategori Anggaran</b></td>
            <td>:</td>
            <td><?php echo htmlspecialchars($data['kategori']); ?></td>
        </tr>
        <tr>
            <td><b>Pihak <?php echo $is_masuk ? 'Penyetor' : 'Penerima'; ?></b></td>
            <td>:</td>
            <td><?php echo htmlspecialchars($data['penerima_penyetor'] ?: '-'); ?></td>
        </tr>
        <tr>
            <td><b>Uraian / Keterangan</b></td>
            <td>:</td>
            <td><?php echo htmlspecialchars($data['keterangan']); ?></td>
        </tr>
    </table>

    <div class="nominal-box">
        Jumlah Nominal: Rp <?php echo number_format($data['jumlah'], 0, ',', '.'); ?>
    </div>

    <div class="signatures">
        <div class="sig-box">
            <p>Diserahkan Oleh,</p>
            <br><br><br>
            <p><b>( _________________ )</b></p>
        </div>
        <div class="sig-box">
            <p>Diterima / Mengetahui,</p>
            <br><br><br>
            <p><b>( _________________ )</b></p>
        </div>
    </div>
</div>

</body>
</html>