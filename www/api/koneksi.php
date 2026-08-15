<?php
// Mengatur zona waktu agar perhitungan tanggal hari ini akurat
date_default_timezone_set('Asia/Jakarta');

// Path file database SQLite mengarah ke database.db
$database_path = __DIR__ . '/database.db';

try {
    // Membuat koneksi SQLite menggunakan PDO
    $koneksi = new PDO("sqlite:" . $database_path);
    // Mengaktifkan error mode exception untuk mempermudah debugging
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi ke database SQLite gagal: " . $e->getMessage());
}
?>