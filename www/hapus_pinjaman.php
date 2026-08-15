<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_admin'])) { 
    header("Location: login.php"); 
    exit(); 
}
include __DIR__ . "/api/koneksi.php";

// Validasi hak akses (Petugas dilarang menghapus)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas') {
    echo "<script>alert('Akses ditolak! Petugas tidak diizinkan menghapus data.'); window.location='pinjaman.php';</script>";
    exit();
}

$no_pinjaman = $_GET['no_pinjaman'] ?? $_GET['id'] ?? '';
if (!empty($no_pinjaman)) {
    // Hapus data berdasarkan no_pinjaman menggunakan PDO Prepared Statement
    $stmt = $koneksi->prepare("DELETE FROM pinjaman WHERE no_pinjaman = ?");
    $stmt->execute([$no_pinjaman]);
}

header("Location: pinjaman.php");
exit();
?>