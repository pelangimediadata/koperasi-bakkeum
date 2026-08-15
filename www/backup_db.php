<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

// Konfigurasi database dari koneksi PDO yang ada
$host = "localhost";
$username = "root"; // Sesuaikan jika ada username database
$password = "";     // Sesuaikan jika ada password database
$database = "koperasi"; // Sesuaikan dengan nama database Anda

// Nama file backup hasil unduhan
$filename = "backup_koperasi_" . date('Y-m-d_H-i-s') . ".sql";

// Header untuk mendownload file SQL
header('Content-Type: application/octet-stream');   
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"" . $filename . "\"");

// Mengambil seluruh nama tabel
$tables = [];
$stmt = $koneksi->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$output = "";
foreach ($tables as $table) {
    // Struktur tabel
    $stmt = $koneksi->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $output .= "\n\n" . $row[1] . ";\n\n";

    // Data tabel
    $stmt = $koneksi->query("SELECT * FROM `$table`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fields = array_keys($row);
        $values = array_values($row);
        
        // Escape string untuk keamanan query SQL
        $escaped_values = array_map(function($val) use ($koneksi) {
            if ($val === null) return "NULL";
            return $koneksi->quote($val);
        }, $values);

        $output .= "INSERT INTO `$table` (`" . implode("`, `", $fields) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
    }
}

echo $output;
exit();
?>