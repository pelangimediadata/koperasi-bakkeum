<?php
// router.php - Mengarahkan semua request otomatis ke folder www
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Jika file fisiknya ada di dalam folder www, langsung muat/tampilkan
$file = __DIR__ . '/www' . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Jika mengakses halaman utama (/), buka login.php
if ($uri === '/' || $uri === '') {
    require_once __DIR__ . '/www/login.php';
} else {
    // Untuk file lain seperti dashboard.php, anggota.php, dll.
    $target = __DIR__ . '/www' . $uri;
    if (file_exists($target)) {
        require_once $target;
    } else {
        // Fallback jika file tidak ditemukan
        require_once __DIR__ . '/www/login.php';
    }
}