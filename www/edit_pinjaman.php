<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

$no_pinjaman = isset($_GET['no_pinjaman']) ? trim($_GET['no_pinjaman']) : '';
$msg = '';

// Ambil Data Pinjaman Berdasarkan no_pinjaman
$stmt_data = $koneksi->prepare("SELECT * FROM pinjaman WHERE no_pinjaman = ?");
$stmt_data->execute([$no_pinjaman]);
$data = $stmt_data->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo "<script>alert('Data Pinjaman Tidak Ditemukan!'); window.location='pinjaman.php';</script>";
    exit();
}

// PROSES UPDATE DATA PINJAMAN
if (isset($_POST['update_pinjaman'])) {
    $id_anggota      = trim($_POST['id_anggota'] ?? '');
    $jumlah_pinjaman = isset($_POST['jumlah_pinjaman']) ? (float)$_POST['jumlah_pinjaman'] : 0;
    $bunga_persen    = isset($_POST['bunga_persen']) ? (float)$_POST['bunga_persen'] : 0;
    $tenor           = isset($_POST['tenor']) ? (int)$_POST['tenor'] : 1;
    $status          = trim($_POST['status'] ?? 'Berjalan');

    $jumlah_lama     = (float)$data['jumlah_pinjaman'];
    $sisa_pokok_lama = (float)$data['sisa_pokok'];
    $sudah_dibayar   = $jumlah_lama - $sisa_pokok_lama;
    $sisa_pokok_baru = $jumlah_pinjaman - $sudah_dibayar;
    if ($sisa_pokok_baru < 0) $sisa_pokok_baru = 0;

    $query_update = "UPDATE pinjaman SET 
                        id_anggota = ?, 
                        jumlah_pinjaman = ?, 
                        sisa_pokok = ?, 
                        bunga = ?, 
                        tenor = ?, 
                        status = ? 
                     WHERE no_pinjaman = ?";
    
    $stmt_upd = $koneksi->prepare($query_update);
    if ($stmt_upd->execute([
        (int)$id_anggota,
        (float)$jumlah_pinjaman,
        (float)$sisa_pokok_baru,
        (float)$bunga_persen,
        (int)$tenor,
        (string)$status,
        (int)$no_pinjaman
    ])) {
        echo "<script>alert('Data Pinjaman Berhasil Diperbarui!'); window.location='pinjaman.php';</script>";
        exit();
    } else {
        $err_info = $stmt_upd->errorInfo();
        $msg = "Gagal Mengubah Data: " . ($err_info[2] ?? 'Unknown error');
    }
}

// Ambil daftar anggota untuk dropdown
try {
    $q_anggota = $koneksi->query("SELECT * FROM anggota ORDER BY nama ASC");
    $list_anggota = $q_anggota ? $q_anggota->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $list_anggota = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pinjaman - AprilNet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .form-card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            width: 100%;
            max-width: 600px;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid rgba(0, 242, 255, 0.15);
        }
        .form-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #00f2ff;
            font-size: 20px;
            text-shadow: 0 0 10px rgba(0,242,255,0.3);
            border-bottom: 2px solid rgba(0, 242, 255, 0.15);
            padding-bottom: 15px;
        }
        .form-group {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-weight: 600;
            color: #94a3b8;
            font-size: 13px;
        }
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 11px;
            border: 1px solid rgba(0, 242, 255, 0.3);
            border-radius: 6px;
            font-size: 14px;
            background: rgba(15, 23, 42, 0.9);
            color: #fff;
            outline: none;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #00f2ff;
            box-shadow: 0 0 8px rgba(0, 242, 255, 0.3);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        .btn {
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.1);
        }
        .btn-primary { background-color: #00f2ff; color: #0f172a; box-shadow: 0 0 12px rgba(0,242,255,0.3); }
        .btn-danger { background-color: #dc2626; color: white; box-shadow: 0 0 10px rgba(220,38,38,0.3); }
        .alert-danger {
            background-color: rgba(220,38,38,0.2);
            color: #fca5a5;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            border: 1px solid rgba(220,38,38,0.4);
        }
    </style>
</head>
<body>

    <div class="form-card">
        <h3>✏️ Edit Data Pinjaman (#<?php echo htmlspecialchars($data['no_pinjaman']); ?>)</h3>

        <?php if (!empty($msg)) { ?>
            <div class="alert-danger"><?php echo $msg; ?></div>
        <?php } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Pilih Anggota</label>
                <select name="id_anggota" class="form-control" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php foreach ($list_anggota as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo ($a['id'] == $data['id_anggota']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Besar Pinjaman (Rp)</label>
                <input type="number" name="jumlah_pinjaman" value="<?php echo htmlspecialchars($data['jumlah_pinjaman']); ?>" required>
            </div>

            <div class="form-group">
                <label>Besaran Bunga per Bulan (%)</label>
                <input type="number" step="0.01" name="bunga_persen" value="<?php echo htmlspecialchars($data['bunga']); ?>" required>
            </div>

            <div class="form-group">
                <label>Jangka Waktu Pinjaman (Tenor)</label>
                <select name="tenor" required>
                    <option value="1" <?php echo ($data['tenor'] == 1) ? 'selected' : ''; ?>>1 Bulan</option>
                    <option value="2" <?php echo ($data['tenor'] == 2) ? 'selected' : ''; ?>>2 Bulan</option>
                    <option value="3" <?php echo ($data['tenor'] == 3) ? 'selected' : ''; ?>>3 Bulan</option>
                    <option value="6" <?php echo ($data['tenor'] == 6) ? 'selected' : ''; ?>>6 Bulan</option>
                    <option value="12" <?php echo ($data['tenor'] == 12) ? 'selected' : ''; ?>>12 Bulan</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status Pinjaman</label>
                <select name="status" required>
                    <option value="Berjalan" <?php echo (strtolower($data['status']) == 'berjalan') ? 'selected' : ''; ?>>Berjalan</option>
                    <option value="Lunas" <?php echo (strtolower($data['status']) == 'lunas') ? 'selected' : ''; ?>>Lunas</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="pinjaman.php" class="btn btn-danger">❌ Batal</a>
                <button type="submit" name="update_pinjaman" class="btn btn-primary">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>

</body>
</html>