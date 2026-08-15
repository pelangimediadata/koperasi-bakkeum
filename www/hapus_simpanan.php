<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

// PROTEKSI: Jika role yang login adalah 'petugas', tolak akses
if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas') {
    echo "<script>alert('Akses Ditolak! Akun Petugas tidak memiliki izin untuk menghapus data simpanan.'); window.location='simpanan.php';</script>";
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Deteksi Primary Key dinamis untuk tabel simpanan agar aman
    $pk_simpanan = 'id';
    $cek_pk = mysqli_query($koneksi, "SHOW COLUMNS FROM simpanan LIKE 'id_simpanan'");
    if ($cek_pk && mysqli_num_rows($cek_pk) > 0) {
        $pk_simpanan = 'id_simpanan';
    }

    // Perbaikan: Hapus dari tabel 'simpanan', bukan tabel 'anggota'
    mysqli_query($koneksi, "DELETE FROM simpanan WHERE $pk_simpanan = '$id'");
}

header("Location: simpanan.php");
exit();
?>