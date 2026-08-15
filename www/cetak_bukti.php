<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

$id = $_GET['id'] ?? '';

// Ambil data transaksi + data pinjaman + data anggota berdasarkan id_bayar menggunakan PDO[cite: 23]
$stmt = $koneksi->prepare("SELECT pembayaran.*, pinjaman.sisa_pinjaman, anggota.nama, anggota.nik 
        FROM pembayaran 
        JOIN pinjaman ON pembayaran.no_pinjaman = pinjaman.no_pinjaman 
        JOIN anggota ON pinjaman.id_anggota = anggota.id 
        WHERE pembayaran.id_bayar = ?");
$stmt->execute([$id]);
$d = $stmt->fetch();

if (!$d) {
    echo "<script>alert('Data pembayaran tidak ditemukan!'); window.close();</script>";
    exit();
}

$nominal_bayar = ($d['jumlah_bayar'] > 0) ? $d['jumlah_bayar'] : ($d['bayar_bunga'] ?? 0);

// Format Tanggal dan Jam/Waktu
$raw_tanggal = $d['created_at'] ?? $d['tanggal'] ?? date('Y-m-d H:i:s');

if ($raw_tanggal && strpos($raw_tanggal, ' ') === false) {
    $raw_tanggal .= ' ' . date('H:i:s');
}

$waktu_transaksi = date('d-m-Y H:i:s', strtotime($raw_tanggal));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Pembayaran #TRX-<?php echo htmlspecialchars($id); ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .receipt {
            max-width: 380px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 12px; }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .row.bold { font-weight: bold; }
        .footer {
            border-top: 2px dashed #333;
            margin-top: 15px;
            padding-top: 10px;
            text-align: center;
            font-size: 11px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; border: none; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="receipt">
    <div class="header">
        <h2>APRILNET KOPERASI</h2>
        <p>BUKTI PEMBAYARAN ANGSURAN</p>
    </div>

    <div class="row">
        <span>No TRX:</span>
        <span>#TRX-<?php echo htmlspecialchars($id); ?></span>
    </div>
    <div class="row">
        <span>Waktu Transaksi:</span>
        <span><?php echo $waktu_transaksi; ?></span>
    </div>
    <div class="row">
        <span>Nama Anggota:</span>
        <span><?php echo htmlspecialchars($d['nama']); ?></span>
    </div>
    <div class="row">
        <span>No Pinjaman:</span>
        <span>#<?php echo htmlspecialchars($d['no_pinjaman']); ?></span>
    </div>

    <hr style="border: none; border-top: 1px dashed #ccc; margin: 10px 0;">

    <div class="row">
        <span>Jenis Bayar:</span>
        <span><?php echo htmlspecialchars($d['jenis_bayar']); ?></span>
    </div>
    <div class="row bold">
        <span>Jumlah Bayar:</span>
        <span>Rp <?php echo number_format($nominal_bayar, 0, ',', '.'); ?></span>
    </div>
    <div class="row">
        <span>Sisa Pinjaman:</span>
        <span>Rp <?php echo number_format($d['sisa_pinjaman'] ?? 0, 0, ',', '.'); ?></span>
    </div>

    <div class="footer">
        <p>Terima kasih atas pembayaran Anda.</p>
        <p>-- Simpan bukti ini sebagai tanda terima sah --</p>
    </div>
</div>

</body>
</html>