<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Proses hapus data anggota menggunakan PDO prepared statement yang kompatibel untuk id atau id_anggota SQLite
    $stmt = $koneksi->prepare("DELETE FROM anggota WHERE id = ? OR id_anggota = ?");
    $stmt->execute([$id, $id]);
}

header("Location: anggota.php");
exit();
?>