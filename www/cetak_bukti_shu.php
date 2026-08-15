<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

$id_anggota = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tahun_periode = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Ambil data anggota
$stmt_ang = $koneksi->prepare("SELECT * FROM anggota WHERE id = ? LIMIT 1");
$stmt_ang->execute([$id_anggota]);
$data_ang = $stmt_ang->fetch();
if (!$data_ang) {
    die("Data anggota tidak ditemukan.");
}
$nama_ang = $data_ang['nama'];

// Ambil data pembayaran SHU
$stmt_bayar = $koneksi->prepare("SELECT * FROM shu_pembayaran WHERE id_anggota = ? AND periode_tahun = ? LIMIT 1");
$stmt_bayar->execute([$id_anggota, $tahun_periode]);
$data_bayar = $stmt_bayar->fetch();

$jumlah_diterima = $data_bayar ? floatval($data_bayar['jumlah_dibayar']) : 0;
$tanggal_bayar = $data_bayar ? $data_bayar['tanggal_dibayar'] : date('Y-m-d H:i:s');
$status_bayar = $data_bayar ? $data_bayar['status'] : 'Belum Dibayar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Pembayaran SHU - <?php echo htmlspecialchars($nama_ang); ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #f1f5f9;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .receipt-container {
            max-width: 700px;
            background: #fff;
            margin: 0 auto;
            padding: 30px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .kop-surat {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .kop-surat h2 { font-size: 18pt; margin: 0; text-transform: uppercase; font-weight: bold; }
        .kop-surat p { font-size: 11pt; margin: 4px 0 0 0; }
        
        h3.title-bukti {
            text-align: center;
            font-size: 14pt;
            margin-bottom: 20px;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .info-detail {
            width: 100%;
            margin-bottom: 25px;
            font-size: 12pt;
            border-collapse: collapse;
        }
        .info-detail td {
            padding: 8px 4px;
            vertical-align: top;
        }
        .info-detail td.label {
            width: 200px;
            font-weight: bold;
        }

        .nominal-box {
            background: #f8fafc;
            border: 2px dashed #0f172a;
            padding: 15px;
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 30px;
            border-radius: 6px;
            color: #16a34a;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .sign-box {
            text-align: center;
            width: 220px;
            font-size: 12pt;
        }
        .sign-box .space {
            height: 70px;
        }

        .btn-print {
            display: block;
            width: 100%;
            background: #0288d1;
            color: white;
            border: none;
            padding: 12px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 25px;
            transition: background 0.2s;
        }
        .btn-print:hover { background: #01579b; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt-container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <div class="kop-surat">
        <h2>KOPERASI SIMPAN PINJAM</h2>
        <p>Bukti Resmi Pembayaran Sisa Hasil Usaha (SHU)</p>
    </div>

    <h3 class="title-bukti">BUKTI PEMBAYARAN SHU ANGGOTA</h3>

    <table class="info-detail">
        <tr>
            <td class="label">ID Anggota</td>
            <td>: PJ-<?php echo str_pad($id_anggota, 3, '0', STR_PAD_LEFT); ?></td>
        </tr>
        <tr>
            <td class="label">Nama Anggota</td>
            <td>: <strong><?php echo htmlspecialchars($nama_ang); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Periode SHU</td>
            <td>: Tahun <?php echo htmlspecialchars($tahun_periode); ?> (01/01/<?php echo $tahun_periode; ?> - 31/12/<?php echo $tahun_periode; ?>)</td>
        </tr>
        <tr>
            <td class="label">Tanggal Pembayaran</td>
            <td>: <?php echo date('d-m-Y H:i:s', strtotime($tanggal_bayar)); ?></td>
        </tr>
        <tr>
            <td class="label">Status Transaksi</td>
            <td>: <strong style="color: #16a34a;"><?php echo htmlspecialchars($status_bayar); ?></strong></td>
        </tr>
    </table>

    <div class="nominal-box">
        Total SHU Diterima: Rp <?php echo number_format($jumlah_diterima, 0, ',', '.'); ?>
    </div>

    <div class="signature-section">
        <div class="sign-box">
            <p>Penerima,</p>
            <div class="space"></div>
            <p><strong>( <?php echo htmlspecialchars($nama_ang); ?> )</strong></p>
        </div>
        <div class="sign-box">
            <p>Bendahara / Kasir,</p>
            <div class="space"></div>
            <p><strong>( Administrator )</strong></p>
        </div>
    </div>

    <button onclick="window.print()" class="btn-print">🖨️ Cetak Bukti Pembayaran Ini</button>
</div>

</body>
</html>