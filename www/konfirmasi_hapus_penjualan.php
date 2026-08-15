<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$is_admin = (isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin') || (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
if (!$is_admin) {
    echo "<script>alert('Akses Ditolak! Hanya Admin yang berhak menghapus riwayat transaksi.'); window.location='toko.php';</script>";
    exit();
}

$id = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Hapus Penjualan - AprilNet</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #fff; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(16px); padding: 30px; border-radius: 12px; text-align: center; border: 1px solid rgba(0, 242, 255, 0.2); width: 420px; box-shadow: 0 0 20px rgba(0,242,255,0.1); }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; transition: 0.2s; }
        .btn-danger { background-color: #ef4444; color: white; box-shadow: 0 0 10px rgba(239,68,68,0.3); }
        .btn-danger:hover { background-color: #dc2626; }
        .btn-secondary { background-color: #475569; color: white; }
        .btn-secondary:hover { background-color: #334155; }
    </style>
</head>
<body>
    <div class="box">
        <h3 style="color: #00f2ff;">⚠️ Konfirmasi Hapus Penjualan</h3>
        <p style="margin: 20px 0; color: #94a3b8;">Apakah Anda yakin ingin menghapus riwayat transaksi penjualan ID <b>#<?php echo htmlspecialchars($id); ?></b> ini? (Stok barang akan dikembalikan otomatis).</p>
        <a href="hapus_penjualan.php?id=<?php echo urlencode($id); ?>" class="btn btn-danger">Ya, Hapus</a>
        <a href="toko.php" class="btn btn-secondary">Batal</a>
    </div>
</body>
</html>