<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_admin']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include __DIR__ . "/api/koneksi.php";

$error = '';
$id = $_GET['id'] ?? 0;

// Ambil data user berdasarkan ID menggunakan PDO Prepared Statement[cite: 36]
$stmt_user = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<script>alert('User tidak ditemukan!'); window.location='users.php';</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role     = trim($_POST['role']);

    if (empty($username)) {
        $error = 'Username tidak boleh kosong!';
    } else {
        // Cek apakah username dipakai user lain menggunakan PDO Prepared Statement[cite: 36]
        $stmt_cek = $koneksi->prepare("SELECT * FROM users WHERE username = ? AND id != ?");
        $stmt_cek->execute([$username, $id]);
        
        if ($stmt_cek->rowCount() > 0) {
            $error = 'Username sudah digunakan oleh user lain!';
        } else {
            // Jika password diisi, perbarui password (MD5). Jika kosong, gunakan password lama.[cite: 36]
            if (!empty($password)) {
                $hashed_password = md5($password);
                $stmt_upd = $koneksi->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                $result = $stmt_upd->execute([$username, $hashed_password, $role, $id]);
            } else {
                $stmt_upd = $koneksi->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                $result = $stmt_upd->execute([$username, $role, $id]);
            }

            try {
                if ($result) {
                    echo "<script>alert('Data user berhasil diperbarui!'); window.location='users.php';</script>";
                    exit();
                }
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui user: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit User - Koperasi Bakkeum</title>
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
        .help-text { font-size: 12px; color: #666; margin-top: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card-box">
        <div class="page-title">✏️ Edit Data User</div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>

            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password baru jika ingin mengubah">
                <div class="help-text">*Kosongkan jika tidak ingin mengganti password.</div>
            </div>

            <div class="form-group">
                <label>Role / Hak Akses</label>
                <select name="role" class="form-control" required>
                    <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="petugas" <?php echo ($user['role'] === 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                </select>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <button type="submit" class="btn-primary">💾 Simpan Perubahan</button>
                <a href="users.php" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>