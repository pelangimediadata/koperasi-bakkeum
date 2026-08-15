<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$is_admin = (isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin') || (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
if (!$is_admin) {
    header("Location: toko.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_barang_del = $_GET['id'];
    
    // Ambil data barang menggunakan PDO prepared statement[cite: 21]
    $stmt_get = $koneksi->prepare("SELECT * FROM barang WHERE id_barang = ?");
    $stmt_get->execute([$id_barang_del]);
    $d_brg_del = $stmt_get->fetch();

    if ($d_brg_del) {
        $nama_brg_del = $d_brg_del['nama_barang'];
        $keyword = '%' . $nama_brg_del . '%';

        // Hapus dari tabel belanja_toko dan kas menggunakan operator LIKE[cite: 21]
        $stmt_belanja = $koneksi->prepare("DELETE FROM belanja_toko WHERE keterangan LIKE ?");
        $stmt_belanja->execute([$keyword]);

        $stmt_kas = $koneksi->prepare("DELETE FROM kas WHERE keterangan LIKE ?");
        $stmt_kas->execute([$keyword]);

        $stmt_del = $koneksi->prepare("DELETE FROM barang WHERE id_barang = ?");
        $stmt_del->execute([$id_barang_del]);
    }
}

header("Location: toko.php");
exit();
?>