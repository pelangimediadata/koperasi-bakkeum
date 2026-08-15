<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = '';

$pk = 'id';
$stmt_pk = $koneksi->query("PRAGMA table_info(simpanan)");
if ($stmt_pk) {
    $cols_pk = $stmt_pk->fetchAll(PDO::FETCH_ASSOC);
    $col_names_pk = array_column(array_map('array_change_key_case', $cols_pk), 'name');
    if (in_array('id_simpanan', $col_names_pk)) $pk = 'id_simpanan';
}

$fk = 'id_anggota';
$stmt_fk = $koneksi->query("PRAGMA table_info(simpanan)");
if ($stmt_fk) {
    $cols_fk = $stmt_fk->fetchAll(PDO::FETCH_ASSOC);
    $col_names_fk = array_column(array_map('array_change_key_case', $cols_fk), 'name');
    if (in_array('anggota_id', $col_names_fk)) $fk = 'anggota_id';
}

$kolom_jumlah = 'jumlah';
$stmt_j = $koneksi->query("PRAGMA table_info(simpanan)");
if ($stmt_j) {
    $cols_j = $stmt_j->fetchAll(PDO::FETCH_ASSOC);
    $col_names_j = array_column(array_map('array_change_key_case', $cols_j), 'name');
    if (in_array('nominal', $col_names_j)) $kolom_jumlah = 'nominal';
    elseif (in_array('besar_simpanan', $col_names_j)) $kolom_jumlah = 'besar_simpanan';
}

// Ambil Data Lama menggunakan PDO Prepared Statement[cite: 38]
$stmt_data = $koneksi->prepare("SELECT * FROM simpanan WHERE $pk = ?");
$stmt_data->execute([$id]);
$data = $stmt_data->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data Simpanan Tidak Ditemukan!'); window.location='simpanan.php';</script>";
    exit();
}

$jumlah_lama = floatval($data[$kolom_jumlah]);
$id_anggota_lama = intval($data[$fk]);

// PROSES UPDATE
if (isset($_POST['update_simpanan'])) {
    $id_anggota_baru = intval($_POST['id_anggota']);
    $jenis_simpanan  = $_POST['jenis_simpanan'];
    $jumlah_baru     = floatval($_POST['jumlah']);

    $query_update = "UPDATE simpanan SET 
                        $fk = ?, 
                        jenis_simpanan = ?, 
                        $kolom_jumlah = ? 
                     WHERE $pk = ?";

    $stmt_upd = $koneksi->prepare($query_update);
    if ($stmt_upd->execute([$id_anggota_baru, $jenis_simpanan, $jumlah_baru, $id])) {
        
        // Update Saldo Anggota menggunakan PRAGMA SQLite
        $kolom_saldo = '';
        $stmt_s = $koneksi->query("PRAGMA table_info(anggota)");
        if ($stmt_s) {
            $cols_s = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
            $col_names_s = array_column(array_map('array_change_key_case', $cols_s), 'name');

            if (in_array('saldo', $col_names_s)) $kolom_saldo = 'saldo';
            elseif (in_array('total_simpanan', $col_names_s)) $kolom_saldo = 'total_simpanan';
            elseif (in_array('simpanan', $col_names_s)) $kolom_saldo = 'simpanan';
        }

        if (!empty($kolom_saldo)) {
            if ($id_anggota_lama === $id_anggota_baru) {
                $selisih = $jumlah_baru - $jumlah_lama;
                $stmt_upd_saldo = $koneksi->prepare("UPDATE anggota SET $kolom_saldo = $kolom_saldo + ? WHERE id = ?");
                $stmt_upd_saldo->execute([$selisih, $id_anggota_baru]);
            } else {
                // Kurangi saldo anggota lama, tambah saldo anggota baru
                $stmt_kurang = $koneksi->prepare("UPDATE anggota SET $kolom_saldo = $kolom_saldo - ? WHERE id = ?");
                $stmt_kurang->execute([$jumlah_lama, $id_anggota_lama]);

                $stmt_tambah = $koneksi->prepare("UPDATE anggota SET $kolom_saldo = $kolom_saldo + ? WHERE id = ?");
                $stmt_tambah->execute([$jumlah_baru, $id_anggota_baru]);
            }
        }

        echo "<script>alert('Data Simpanan Berhasil Diperbarui!'); window.location='simpanan.php';</script>";
        exit();
    } else {
        $err_info = $stmt_upd->errorInfo();
        $msg = "Gagal Mengubah Data: " . ($err_info[2] ?? 'Unknown error');
    }
}

$query_anggota = $koneksi->query("SELECT id, nik, nama FROM anggota ORDER BY nama ASC");
$list_anggota = $query_anggota ? $query_anggota->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Simpanan - Koperasi Bakkeum</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background-color: #0b3c36; font-family: Arial, sans-serif; padding: 20px; }
        .form-card { background: white; max-width: 500px; margin: 30px auto; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .form-card h3 { color: #1a3c40; margin-top: 0; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; margin-bottom: 5px; color: #1a3c40; font-size: 14px; }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        .form-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 14px; }
        .btn-primary { background-color: #0088cc; color: white; }
        .btn-danger { background-color: #d9534f; color: white; }
        .alert { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="form-card">
        <h3>✏️ Edit Data Simpanan - Koperasi Bakkeum</h3>

        <?php if (!empty($msg)) { echo "<div class='alert'>" . htmlspecialchars($msg) . "</div>"; } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Nama Anggota</label>
                <select name="id_anggota" required>
                    <?php 
                    foreach ($list_anggota as $row) {
                        $selected = ($row['id'] == $data[$fk]) ? 'selected' : '';
                        echo "<option value='" . $row['id'] . "' $selected>" . htmlspecialchars($row['nama']) . " (" . htmlspecialchars($row['nik']) . ")</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Simpanan</label>
                <select name="jenis_simpanan" required>
                    <option value="Simpanan Pokok" <?php if($data['jenis_simpanan'] == 'Simpanan Pokok') echo 'selected'; ?>>Simpanan Pokok</option>
                    <option value="Simpanan Wajib" <?php if($data['jenis_simpanan'] == 'Simpanan Wajib') echo 'selected'; ?>>Simpanan Wajib</option>
                    <option value="Simpanan Sukarela" <?php if($data['jenis_simpanan'] == 'Simpanan Sukarela') echo 'selected'; ?>>Simpanan Sukarela</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Simpanan (Rp)</label>
                <input type="number" name="jumlah" value="<?php echo htmlspecialchars($data[$kolom_jumlah]); ?>" required>
            </div>

            <div class="form-actions">
                <button type="submit" name="update_simpanan" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="simpanan.php" class="btn btn-danger">❌ Batal</a>
            </div>
        </form>
    </div>

</body>
</html>