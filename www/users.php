<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validasi ketat role Admin pada halaman users.php
$is_admin_user = false;
if (isset($_SESSION['role']) && strtoupper(trim($_SESSION['role'])) === 'ADMIN') {
    $is_admin_user = true;
}

if (!$is_admin_user) {
    header("Location: dashboard.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

// 1. Buat tabel users jika belum ada (Sintaks SQLite)
$koneksi->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    nama_lengkap TEXT DEFAULT '',
    role TEXT DEFAULT 'ADMIN',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 2. Pastikan kolom nama_lengkap ada di tabel users (SQLite ALTER TABLE ADD COLUMN)
$q_cols_check = $koneksi->query("PRAGMA table_info(users)");
$has_nama_lengkap = false;
$has_role = false;
while ($col = $q_cols_check->fetch(PDO::FETCH_ASSOC)) {
    if (strtolower($col['name']) === 'nama_lengkap') $has_nama_lengkap = true;
    if (strtolower($col['name']) === 'role') $has_role = true;
}

if (!$has_nama_lengkap) {
    $koneksi->exec("ALTER TABLE users ADD COLUMN nama_lengkap TEXT DEFAULT ''");
}
if (!$has_role) {
    $koneksi->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'ADMIN'");
}

// Proses Tambah / Edit User
if (isset($_POST['simpan_user'])) {
    $id_user = intval($_POST['id_user'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = trim($_POST['role'] ?? 'ADMIN');
    $password = $_POST['password'] ?? '';

    if ($id_user > 0) {
        // Update user
        if (!empty($password)) {
            $pass_hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt_upd = $koneksi->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ?, password = ? WHERE id = ?");
            $stmt_upd->execute([$username, $nama_lengkap, $role, $pass_hashed, $id_user]);
        } else {
            $stmt_upd = $koneksi->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ? WHERE id = ?");
            $stmt_upd->execute([$username, $nama_lengkap, $role, $id_user]);
        }
        $_SESSION['notif_success'] = "Data user berhasil diperbarui!";
    } else {
        // Tambah user baru
        $pass_hashed = password_hash(!empty($password) ? $password : '123456', PASSWORD_DEFAULT);
        $stmt_cek = $koneksi->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_cek->execute([$username]);
        if ($stmt_cek->fetch()) {
            $_SESSION['notif_error'] = "Username sudah digunakan, pilih username lain!";
        } else {
            $stmt_ins = $koneksi->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt_ins->execute([$username, $pass_hashed, $nama_lengkap, $role]);
            $_SESSION['notif_success'] = "User baru berhasil ditambahkan!";
        }
    }
    header("Location: users.php");
    exit();
}

// Proses Hapus User
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($id_hapus > 1) { // Mencegah hapus user utama ID 1
        $stmt_del = $koneksi->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->execute([$id_hapus]);
        $_SESSION['notif_success'] = "User berhasil dihapus!";
    } else {
        $_SESSION['notif_error'] = "User utama sistem tidak dapat dihapus!";
    }
    header("Location: users.php");
    exit();
}

// Ambil data user untuk diedit jika ada parameter ?edit=id
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
    $stmt_edit = $koneksi->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt_edit->execute([$id_edit]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// Ambil seluruh daftar user
$list_users = $koneksi->query("SELECT * FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan User - Koperasi Bakkeum</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364, #004d40, #00796b);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
            color: #333;
            display: flex;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .app-container { display: flex; min-height: 100vh; width: 100%; flex-direction: row; }
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; background: transparent; width: 100%; }

        h2 { color: #ffffff; margin-bottom: 20px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); font-size: 24px; }

        .content {
            background: rgba(255, 255, 255, 0.96);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .form-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .form-card h3 { font-size: 16px; color: #1e293b; margin-bottom: 15px; border-bottom: 2px solid #cbd5e1; padding-bottom: 8px; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; background: #fff; }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            justify-content: center;
        }
        .btn-primary { background: #00796b; color: white; }
        .btn-primary:hover { background: #004d40; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-secondary:hover { background: #475569; }
        .btn-edit { background: #0288d1; color: white; padding: 6px 12px; font-size: 12px; }
        .btn-edit:hover { background: #01579b; }
        .btn-hapus { background: #dc2626; color: white; padding: 6px 12px; font-size: 12px; }
        .btn-hapus:hover { background: #b91c1c; }

        /* Responsive Table & Card Wrapper */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            min-width: 600px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; }
        tr:hover { background-color: #f8fafc; }
        .text-center { text-align: center; }
        .badge-role { background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }

        /* Media Queries untuk Mobile */
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .app-container {
                flex-direction: column;
            }
            .main-content {
                padding: 15px;
            }
            .content {
                padding: 15px;
                border-radius: 12px;
            }
            h2 {
                font-size: 20px;
                margin-bottom: 15px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            /* Ubah tampilan tabel menjadi card list agar sangat rapi di HP */
            .desktop-table {
                display: none;
            }
            .mobile-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-top: 15px;
            }
            .user-card-item {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 15px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .user-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 8px;
            }
            .user-card-body {
                font-size: 14px;
                color: #334155;
            }
            .user-card-footer {
                display: flex;
                gap: 8px;
                margin-top: 5px;
            }
            .user-card-footer .btn {
                flex: 1;
            }
        }

        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <h2>Pengaturan Pengguna & Akses Sistem - Koperasi Bakkeum</h2>

        <div class="content">
            <?php if (isset($_SESSION['notif_success'])): ?>
                <div class="alert-success"><?php echo $_SESSION['notif_success']; unset($_SESSION['notif_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['notif_error'])): ?>
                <div class="alert-error"><?php echo $_SESSION['notif_error']; unset($_SESSION['notif_error']); ?></div>
            <?php endif; ?>

            <!-- Form Tambah / Edit User -->
            <div class="form-card">
                <h3><?php echo $edit_data ? '✏️ Edit Akun Pengguna' : '➕ Tambah User Baru'; ?></h3>
                <form method="POST" action="">
                    <input type="hidden" name="id_user" value="<?php echo $edit_data['id'] ?? ''; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username:</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($edit_data['username'] ?? ''); ?>" required placeholder="Masukkan username...">
                        </div>
                        <div class="form-group">
                            <label>Nama Lengkap:</label>
                            <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($edit_data['nama_lengkap'] ?? $edit_data['nama'] ?? ''); ?>" placeholder="Nama lengkap...">
                        </div>
                        <div class="form-group">
                            <label>Password <?php echo $edit_data ? '(Kosongkan jika tdk diubah)' : ''; ?>:</label>
                            <input type="password" name="password" placeholder="Password...">
                        </div>
                        <div class="form-group">
                            <label>Role / Hak Akses:</label>
                            <select name="role">
                                <option value="ADMIN" <?php echo (isset($edit_data['role']) && strtoupper($edit_data['role']) == 'ADMIN') ? 'selected' : ''; ?>>ADMIN</option>
                                <option value="PENGURUS" <?php echo (isset($edit_data['role']) && strtoupper($edit_data['role']) == 'PENGURUS') ? 'selected' : ''; ?>>PENGURUS</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; gap: 8px;">
                            <button type="submit" name="simpan_user" class="btn btn-primary" style="flex: 1;">💾 Simpan</button>
                            <?php if ($edit_data): ?>
                                <a href="users.php" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <h3 style="color: #1e293b; margin-bottom: 10px; font-size: 18px;">Manajemen Akun Pengguna Terdaftar</h3>

            <?php 
            $rows_users = $list_users->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <!-- Tampilan Desktop (Tabel Biasa dengan Scroll Responsif) -->
            <div class="table-responsive desktop-table">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">NO</th>
                            <th>USERNAME</th>
                            <th>NAMA LENGKAP</th>
                            <th class="text-center">ROLE</th>
                            <th style="width: 150px;" class="text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (count($rows_users) > 0):
                            $no = 1;
                            foreach ($rows_users as $row):
                                $nama_tampil = $row['nama_lengkap'] ?? $row['nama'] ?? '-';
                                $role_tampil = $row['role'] ?? 'ADMIN';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['username'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($nama_tampil); ?></td>
                            <td class="text-center"><span class="badge-role"><?php echo htmlspecialchars(strtoupper($role_tampil)); ?></span></td>
                            <td class="text-center">
                                <a href="users.php?edit=<?php echo $row['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                                <a href="users.php?hapus=<?php echo $row['id']; ?>" class="btn btn-hapus" onclick="return confirm('Yakin ingin menghapus user ini?');">🗑️ Hapus</a>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 20px; color: #64748b;">Belum ada data user.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tampilan Mobile (Card Layout agar sangat rapi di HP) -->
            <div class="mobile-cards">
                <?php 
                if (count($rows_users) > 0):
                    $no = 1;
                    foreach ($rows_users as $row):
                        $nama_tampil = $row['nama_lengkap'] ?? $row['nama'] ?? '-';
                        $role_tampil = $row['role'] ?? 'ADMIN';
                ?>
                <div class="user-card-item">
                    <div class="user-card-header">
                        <strong>#<?php echo $no++; ?> - <?php echo htmlspecialchars($row['username']); ?></strong>
                        <span class="badge-role"><?php echo htmlspecialchars(strtoupper($role_tampil)); ?></span>
                    </div>
                    <div class="user-card-body">
                        <div><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($nama_tampil); ?></div>
                    </div>
                    <div class="user-card-footer">
                        <a href="users.php?edit=<?php echo $row['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                        <a href="users.php?hapus=<?php echo $row['id']; ?>" class="btn btn-hapus" onclick="return confirm('Yakin ingin menghapus user ini?');">🗑️ Hapus</a>
                    </div>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <div class="text-center" style="padding: 20px; color: #64748b; background: #f8fafc; border-radius: 8px;">Belum ada data user.</div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

</body>
</html>