<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['login_admin'])) { 
    header("Location: login.php"); 
    exit(); 
}

include __DIR__ . "/api/koneksi.php";

$id = $_GET['id'] ?? '';

// Cek data pembayaran secara aman di kolom id_bayar, id, atau no_bayar menggunakan PDO Prepared Statement[cite: 39]
$q_bayar = null;
foreach (['id_bayar', 'id', 'no_bayar'] as $kolom) {
    // Pastikan kolom ada di tabel pembayaran lewat PRAGMA
    $stmt_cols = $koneksi->query("PRAGMA table_info(pembayaran)");
    if ($stmt_cols) {
        $cols = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
        $col_names = array_column(array_map('array_change_key_case', $cols), 'name');
        if (in_array($kolom, $col_names)) {
            $stmt = $koneksi->prepare("SELECT * FROM pembayaran WHERE $kolom = ?");
            $stmt->execute([$id]);
            $data_b = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data_b) {
                break;
            }
        }
    }
}

if (!isset($data_b) || !$data_b) {
    header("Location: bayar.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $jenis_bayar  = $_POST['jenis_bayar'];
    $jumlah_bayar = (int) $_POST['jumlah_bayar'];
    $tanggal      = $_POST['tanggal'];

    $val_jumlah_bayar = ($jenis_bayar === 'Bunga Saja') ? 0 : $jumlah_bayar;
    $val_bayar_bunga  = ($jenis_bayar === 'Bunga Saja') ? $jumlah_bayar : 0;

    // Tentukan klausa WHERE berdasarkan kolom yang tersedia di data_b[cite: 39]
    if (isset($data_b['id_bayar'])) {
        $where_clause = "id_bayar = ?";
    } elseif (isset($data_b['id'])) {
        $where_clause = "id = ?";
    } else {
        $where_clause = "no_bayar = ?";
    }

    $stmt_update = $koneksi->prepare("UPDATE pembayaran SET jenis_bayar = ?, jumlah_bayar = ?, bayar_bunga = ?, tanggal = ? WHERE $where_clause");
    $update = $stmt_update->execute([$jenis_bayar, $val_jumlah_bayar, $val_bayar_bunga, $tanggal, $id]);

    if ($update) {
        echo "<script>alert('Data pembayaran berhasil diubah!'); window.location='bayar.php';</script>";
        exit();
    } else {
        $err_info = $stmt_update->errorInfo();
        echo "<script>alert('Gagal memperbarui data: " . ($err_info[2] ?? 'Unknown error') . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Pembayaran - Koperasi Bakkeum</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #1d3538; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .card-box { background: white; max-width: 500px; margin: 40px auto; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-control { width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .btn { padding: 10px 18px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #044b3b; color: white; }
        .btn-primary:hover { background: #03362a; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #bd2130; }
    </style>
</head>
<body>
    <div class="card-box">
        <h3 style="color: #044b3b; margin-top: 0;">✏️ Edit Pembayaran (#TRX-<?php echo htmlspecialchars($id); ?>)</h3>
        <form action="" method="POST">
            <div class="form-group">
                <label>Tanggal Pembayaran</label>
                <input type="date" name="tanggal" class="form-control" value="<?php echo htmlspecialchars($data_b['tanggal']); ?>" required>
            </div>

            <div class="form-group">
                <label>Jenis Pembayaran</label>
                <select name="jenis_bayar" class="form-control" required>
                    <option value="Bayar Angsuran" <?php echo ($data_b['jenis_bayar'] == 'Bayar Angsuran') ? 'selected' : ''; ?>>Bayar Angsuran</option>
                    <option value="Angsuran + Bunga" <?php echo ($data_b['jenis_bayar'] == 'Angsuran + Bunga') ? 'selected' : ''; ?>>Angsuran + Bunga</option>
                    <option value="Bunga Saja" <?php echo ($data_b['jenis_bayar'] == 'Bunga Saja') ? 'selected' : ''; ?>>Bunga Saja</option>
                    <option value="Pelunasan" <?php echo ($data_b['jenis_bayar'] == 'Pelunasan') ? 'selected' : ''; ?>>Pelunasan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Bayar (Rp)</label>
                <input type="number" name="jumlah_bayar" class="form-control" value="<?php echo ($data_b['jumlah_bayar'] > 0) ? $data_b['jumlah_bayar'] : $data_b['bayar_bunga']; ?>" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="bayar.php" class="btn btn-danger">❌ Batal</a>
            </div>
        </form>
    </div>
</body>
</html>