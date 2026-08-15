<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_admin']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
$database_path = __DIR__ . '/database.db';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);

    if (empty($username) || empty($password)) {
        $error = 'Username dan Password wajib diisi!';
    } else {
        // Cek username apakah sudah digunakan menggunakan PDO SQLite Prepared Statement[cite: 45]
        $stmt_cek = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
        $stmt_cek->execute([$username]);
        
        if ($stmt_cek->fetch(PDO::FETCH_ASSOC)) {
            $error = 'Username sudah digunakan![cite: 45]';
        } else {
            // Hash password (MD5 / Plain sesuai konfigurasi sistem Anda)[cite: 45]
            $hashed_password = md5($password); 

            // Query standar tanpa memanggil kolom nama[cite: 45]
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            
            try {
                $stmt_ins = $koneksi->prepare($sql);
                if ($stmt_ins->execute([$username, $hashed_password, $role])) {
                    echo "<script>alert('User berhasil ditambahkan![cite: 45]'); window.location='users.php';</script>";
                    exit();
                }
            } catch (PDOException $e) {
                $error = 'Gagal menambahkan user: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah User Baru - Koperasi Bakkeum</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #1d3538; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 40px auto; }
        .card-box { background: #ffffff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .page-title { font-size: 20px; font-weight: bold; color: #044b3b; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; }
        .form-control:focus { border-color: #044b3b; }
        .btn-primary { background-color: #044b3b; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn-secondary { background-color: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <div class="card-box">
        <div class="page-title">➕ Tambah User Baru</div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required placeholder="Masukkan username">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
            </div>

            <div class="form-group">
                <label>Role / Hak Akses</label>
                <select name="role" class="form-control" required>
                    <option value="admin">Admin</option>
                    <option value="petugas">Petugas</option>
                </select>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">💾 Simpan User</button>
                <a href="users.php" class="btn-secondary">Kembali</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>