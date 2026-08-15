<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>alert('Akses Ditolak! Hanya Admin yang dapat menghapus data.'); window.location='bayar.php';</script>";
    exit();
}

$id = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Hapus Pembayaran - AprilNet</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(16px); padding: 30px; border-radius: 12px; text-align: center; border: 1px solid rgba(0, 242, 255, 0.2); width: 400px; box-shadow: 0 0 20px rgba(0,242,255,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; transition: 0.2s; }
        .btn-danger { background-color: #dc2626; color: white; box-shadow: 0 0 10px rgba(220,38,38,0.3); }
        .btn-danger:hover { background-color: #b91c1c; }
        .btn-secondary { background-color: #475569; color: white; }
        .btn-secondary:hover { background-color: #334155; }
    </style>
</head>
<body>
    <div class="box">
        <h3 style="color: #00f2ff;">⚠️ Konfirmasi Hapus Pembayaran</h3>
        <p style="margin: 20px 0; color: #94a3b8;">Apakah Anda yakin ingin menghapus data transaksi pembayaran <b>#TRX-<?php echo htmlspecialchars($id); ?></b> ini secara permanen?</p>
        <a href="hapus_bayar.php?id=<?php echo urlencode($id); ?>" class="btn btn-danger">Ya, Hapus</a>
        <a href="bayar.php" class="btn btn-secondary">Batal</a>
    </div>
</body>
</html>