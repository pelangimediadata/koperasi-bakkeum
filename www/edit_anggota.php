<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = '';
$msg_type = '';

// Ambil Data Anggota Berdasarkan ID menggunakan PDO Prepared Statement[cite: 37]
$stmt_data = $koneksi->prepare("SELECT * FROM anggota WHERE id = ?");
$stmt_data->execute([$id]);
$data = $stmt_data->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data Anggota Tidak Ditemukan!'); window.location='anggota.php';</script>";
    exit();
}

// PROSES UPDATE DATA
if (isset($_POST['update_anggota'])) {
    $nik      = trim($_POST['nik']);
    $nama     = trim($_POST['nama']);
    $no_telp  = trim($_POST['no_telp']);
    $alamat   = trim($_POST['alamat']);

    // Deteksi Nama Kolom Telepon di Tabel via PRAGMA SQLite[cite: 37]
    $kolom_telp = 'no_hp'; 
    $stmt_cols = $koneksi->query("PRAGMA table_info(anggota)");
    if ($stmt_cols) {
        $cols = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
        $col_names = array_column(array_map('array_change_key_case', $cols), 'name');

        if (in_array('no_telp', $col_names)) {
            $kolom_telp = 'no_telp';
        } elseif (in_array('telepon', $col_names)) {
            $kolom_telp = 'telepon';
        } elseif (in_array('no_hp', $col_names)) {
            $kolom_telp = 'no_hp';
        }
    }

    $query_update = "UPDATE anggota SET 
                        nik = ?, 
                        nama = ?, 
                        $kolom_telp = ?, 
                        alamat = ? 
                     WHERE id = ?";

    $stmt_upd = $koneksi->prepare($query_update);
    if ($stmt_upd->execute([$nik, $nama, $no_telp, $alamat, $id])) {
        echo "<script>alert('Data Anggota Berhasil Diperbarui!'); window.location='anggota.php';</script>";
        exit();
    } else {
        $err_info = $stmt_upd->errorInfo();
        $msg = "Gagal Mengubah Data: " . ($err_info[2] ?? 'Unknown error');
        $msg_type = "danger";
    }
}

// Ambil nomor telp/hp secara dinamis sesuai kolom database[cite: 37]
$val_telp = isset($data['no_telp']) ? $data['no_telp'] : (isset($data['no_hp']) ? $data['no_hp'] : (isset($data['telepon']) ? $data['telepon'] : ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Anggota - Koperasi Bakkeum</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body {
            background-color: #1b3b36;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .form-card {
            background: #ffffff;
            max-width: 550px;
            margin: 30px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .form-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #004d40;
            font-size: 20px;
        }
        .form-group {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: bold;
            color: #004d40;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #b2dfdb;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .btn-primary { background-color: #0088cc; color: white; }
        .btn-danger { background-color: #d9534f; color: white; }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="form-card">
        <h3>✏️ Edit Data Anggota - Koperasi Bakkeum</h3>

        <?php if (!empty($msg)) { ?>
            <div class="alert-danger"><?php echo htmlspecialchars($msg); ?></div>
        <?php } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>NIK</label>
                <input type="text" name="nik" value="<?php echo htmlspecialchars($data['nik']); ?>" required>
            </div>

            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" value="<?php echo htmlspecialchars($data['nama']); ?>" required>
            </div>

            <div class="form-group">
                <label>No. Telp / WA</label>
                <input type="text" name="no_telp" value="<?php echo htmlspecialchars($val_telp); ?>">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat"><?php echo htmlspecialchars($data['alamat']); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="update_anggota" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="anggota.php" class="btn btn-danger">❌ Batal</a>
            </div>
        </form>
    </div>

</body>
</html>