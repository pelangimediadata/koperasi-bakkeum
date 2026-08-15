<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
include __DIR__ . "/api/koneksi.php"; // Menggunakan koneksi pusat

// Pastikan tabel dan default admin tetap ada jika belum ada
try {
    $koneksi->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        password TEXT NOT NULL,
        nama_lengkap TEXT DEFAULT '',
        role TEXT DEFAULT 'admin'
    )");
} catch (PDOException $e) {
    die("Error setup tabel: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $koneksi->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        if ($password === $user['password'] || password_verify($password, $user['password'])) {
            $_SESSION['login_admin'] = true;
            $_SESSION['id_user']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['nama']        = $user['nama_lengkap'] ?? 'Administrator';
            $_SESSION['role']        = $user['role'] ?? 'admin';

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        // Debugging tambahan untuk melihat isi seluruh database saat ini
        $all_users = $koneksi->query("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $daftar = count($all_users) > 0 ? implode(', ', $all_users) : 'Tabel benar-benar kosong!';
        $error = "Username '$username' tidak ditemukan di DB. User tersedia: [$daftar]";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Koperasi Bakkeum</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        body { 
            min-height: 100vh; 
            min-height: 100dvh; /* Mendukung tinggi layar penuh pada browser mobile modern */
            display: flex; 
            justify-content: center; 
            align-items: center; 
            overflow-x: hidden; 
            overflow-y: auto; 
            position: relative; 
            background: linear-gradient(-45deg, #004d40, #00796b, #009688, #26a69a, #004d40); 
            background-size: 400% 400%; 
            animation: gradientBG 15s ease infinite; 
            padding: 20px; /* Mencegah card menempel langsung di tepi layar HP */
        }

        @keyframes gradientBG { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }

        .circles { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: 1; pointer-events: none; }
        .circles li { position: absolute; display: block; list-style: none; width: 20px; height: 20px; background: rgba(255, 255, 255, 0.15); animation: animate 25s linear infinite; bottom: -150px; border-radius: 50%; backdrop-filter: blur(5px); }
        .circles li:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .circles li:nth-child(2) { left: 10%; width: 30px; height: 30px; animation-delay: 2s; animation-duration: 12s; }
        .circles li:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .circles li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .circles li:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .circles li:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .circles li:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .circles li:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .circles li:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .circles li:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }
        
        @keyframes animate { 
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 20%; } 
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; } 
        }

        .login-card { 
            position: relative; 
            z-index: 10; 
            width: 100%; 
            max-width: 400px; 
            padding: 35px 25px; 
            background: rgba(255, 255, 255, 0.92); 
            backdrop-filter: blur(10px); 
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); 
            border: 1px solid rgba(255, 255, 255, 0.4); 
            text-align: center; 
            margin: auto; /* Memastikan card berada di tengah secara vertikal dan horizontal pada mobile */
        }

        .app-logo { font-size: 42px; margin-bottom: 5px; display: inline-block; }
        .app-title { font-size: 20px; font-weight: 700; color: #004d40; margin-bottom: 5px; }
        .app-subtitle { font-size: 12px; color: #666; margin-bottom: 20px; }
        
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
        
        .input-box { position: relative; }
        .input-box input { 
            width: 100%; 
            padding: 12px 15px 12px 40px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            font-size: 16px; /* Ukuran minimum 16px mencegah fitur auto-zoom otomatis pada browser Safari/Chrome iOS */
            outline: none; 
            background: #f9f9f9; 
        }
        .input-box input:focus { border-color: #00796b; background: #fff; box-shadow: 0 0 8px rgba(0, 121, 107, 0.25); }
        .input-box .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 16px; color: #888; }
        
        .alert-danger { background-color: #ffebee; color: #c62828; padding: 10px 15px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; border: 1px solid #ffcdd2; text-align: left; }
        
        .btn-login { 
            width: 100%; 
            padding: 12px; 
            background: linear-gradient(135deg, #00796b, #004d40); 
            border: none; 
            border-radius: 8px; 
            color: white; 
            font-size: 15px; 
            font-weight: bold; 
            cursor: pointer; 
            box-shadow: 0 4px 10px rgba(0, 77, 64, 0.3); 
            transition: all 0.3s ease; 
            margin-top: 5px;
        }
        .btn-login:hover { background: linear-gradient(135deg, #004d40, #00251a); }
        
        .footer-text { margin-top: 20px; font-size: 11px; color: #888; }

        /* Penyesuaian Ekstra untuk Layar Ponsel Berukuran Kecil */
        @media (max-width: 480px) {
            body {
                padding: 12px;
                align-items: center;
            }
            .login-card {
                padding: 25px 18px;
                border-radius: 12px;
            }
            .app-logo { font-size: 36px; }
            .app-title { font-size: 18px; }
            .app-subtitle { font-size: 11px; margin-bottom: 16px; }
        }
    </style>
</head>
<body>
    <ul class="circles">
        <li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li>
    </ul>
    <div class="login-card">
        <div class="app-logo">🏛️</div>
        <h1 class="app-title">KOPERASI BAKKEUM</h1>
        <p class="app-subtitle">Sistem Informasi Pengelolaan Koperasi</p>

        <?php if (!empty($error)) { ?>
            <div class="alert-danger">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-box">
                    <span class="icon">👤</span>
                    <input type="text" name="username" id="username" placeholder="Masukkan username" required autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-box">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                </div>
            </div>
            <button type="submit" class="btn-login">Masuk ke Sistem</button>
        </form>

        <div class="footer-text">&copy; <?php echo date('Y'); ?> Koperasi Bakkeum. All rights reserved.</div>
    </div>
</body>
</html>