<?php
// router.php untuk PHP Built-in Server
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Jika file atau folder fisik benar-benar ada di dalam folder www, tampilkan langsung
if ($uri !== '/' && file_exists(__DIR__ . '/www' . $uri)) {
    return false;
}

// Jika mengakses root (/), arahkan ke login.php di dalam www
if ($uri === '/' || $uri === '') {
    require_once __DIR__ . '/www/login.php';
} else {
    // Untuk halaman lain seperti dashboard.php, anggota.php, dll.
    $target = __DIR__ . '/www' . $uri;
    if (file_exists($target)) {
        require_once $target;
    } else {
        http_response_code(404);
        echo "404 Not Found: " . htmlspecialchars($uri);
    }
}