<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas') {
    echo "<script>alert('Akses Ditolak! Akun Petugas tidak memiliki izin.'); window.location='pinjaman.php';</script>";
    exit();
}

$no_pinjaman = $_GET['no_pinjaman'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Hapus Pinjaman - AprilNet</title>
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
        <h3 style="color: #00f2ff;">⚠️ Konfirmasi Hapus Pinjaman</h3>
        <p style="margin: 20px 0; color: #94a3b8;">Apakah Anda yakin ingin menghapus data pinjaman <b style="color: #00f2ff;">#<?php echo htmlspecialchars($no_pinjaman); ?></b> ini secara permanen?</p>
        <a href="hapus_pinjaman.php?no_pinjaman=<?php echo urlencode($no_pinjaman); ?>" class="btn btn-danger">Ya, Hapus</a>
        <a href="pinjaman.php" class="btn btn-secondary">Batal</a>
    </div>
</body>
</html>