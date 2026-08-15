<?php
// router.php untuk Railway
$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . '/www' . $url;

if ($url !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Sajikan file statis (CSS, JS, gambar) secara langsung
}

// Arahkan halaman utama ke file login.php atau index.php di dalam folder www
require_once __DIR__ . '/www/login.php';