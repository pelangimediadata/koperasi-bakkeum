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
    
    // Deteksi Primary Key dinamis untuk tabel simpanan menggunakan PRAGMA SQLite
    $pk_simpanan = 'id';
    $stmt_cols = $koneksi->query("PRAGMA table_info(simpanan)");
    if ($stmt_cols) {
        while ($col = $stmt_cols->fetch(PDO::FETCH_ASSOC)) {
            if ($col['name'] === 'id_simpanan') {
                $pk_simpanan = 'id_simpanan';
                break;
            }
        }
    }

    // Eksekusi hapus menggunakan PDO prepared statement
    $stmt = $koneksi->prepare("DELETE FROM simpanan WHERE $pk_simpanan = ?");
    $stmt->execute([$id]);
}

header("Location: simpanan.php");
exit();
?>