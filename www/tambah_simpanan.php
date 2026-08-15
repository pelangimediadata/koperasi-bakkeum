<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$msg = '';
$msg_type = '';

if (isset($_POST['simpan_transaksi'])) {
    $id_anggota     = intval($_POST['id_anggota']);
    $jenis_simpanan = $_POST['jenis_simpanan'];
    $jumlah         = floatval(str_replace(['.', ','], ['', '.'], $_POST['jumlah']));
    $tgl_sekarang   = date('Y-m-d'); // Tanggal hari ini (YYYY-MM-DD)

    if ($id_anggota <= 0) {
        $msg = "Silakan pilih anggota terlebih dahulu!";
        $msg_type = "danger";
    } elseif ($jumlah <= 0) {
        $msg = "Jumlah simpanan harus lebih dari 0!";
        $msg_type = "danger";
    } else {
        // Simpan ke kolom 'jumlah' DAN 'jumlah_simpanan' agar terbaca oleh dashboard/laporan menggunakan PDO Prepared Statement[cite: 46]
        $query_insert = "INSERT INTO simpanan (id_anggota, jenis_simpanan, jumlah, jumlah_simpanan, tanggal_simpan) 
                        VALUES (?, ?, ?, ?, ?)";

        $stmt_ins = $koneksi->prepare($query_insert);
        if ($stmt_ins->execute([$id_anggota, $jenis_simpanan, $jumlah, $jumlah, $tgl_sekarang])) {
            
            // Update Saldo Anggota menggunakan PRAGMA SQLite
            $kolom_saldo = '';
            $stmt_cols = $koneksi->query("PRAGMA table_info(anggota)");
            if ($stmt_cols) {
                $cols = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);
                $col_names = array_column(array_map('array_change_key_case', $cols), 'name');

                if (in_array('saldo', $col_names)) $kolom_saldo = 'saldo';
                elseif (in_array('total_simpanan', $col_names)) $kolom_saldo = 'total_simpanan';
                elseif (in_array('simpanan', $col_names)) $kolom_saldo = 'simpanan';
            }

            if (!empty($kolom_saldo)) {
                $stmt_upd_saldo = $koneksi->prepare("UPDATE anggota SET $kolom_saldo = $kolom_saldo + ? WHERE id = ?");
                $stmt_upd_saldo->execute([$jumlah, $id_anggota]);
            }

            echo "<script>alert('Transaksi Simpanan Berhasil Disimpan!'); window.location='simpanan.php';</script>";
            exit();
        } else {
            $err_info = $stmt_ins->errorInfo();
            $msg = "Gagal Simpan Data: " . ($err_info[2] ?? 'Unknown error');
            $msg_type = "danger";
        }
    }
}

// Ambil Data Anggota menggunakan PDO
$query_anggota = $koneksi->query("SELECT id, nik, nama FROM anggota ORDER BY nama ASC");
$list_anggota = $query_anggota ? $query_anggota->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Simpanan Baru - Koperasi Bakkeum</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background-color: #0b3c36; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .form-card { background: #ffffff; max-width: 550px; margin: 30px auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
        .form-card h3 { margin-top: 0; margin-bottom: 20px; color: #1a3c40; font-size: 20px; }
        .form-group { margin-bottom: 18px; display: flex; flex-direction: column; }
        .form-group label { font-weight: bold; color: #1a3c40; margin-bottom: 6px; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 11px 14px; border: 1px solid #b2dfdb; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; }
        .btn { padding: 11px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
        .btn-primary { background-color: #0088cc; color: white; }
        .btn-danger { background-color: #d9534f; color: white; }
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
    </style>
</head>
<body>

    <div class="form-card">
        <h3>💰 Transaksi Simpanan Baru</h3>

        <?php if (!empty($msg)) { ?>
            <div class="alert-danger"><?php echo htmlspecialchars($msg); ?></div>
        <?php } ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>Pilih Anggota</label>
                <select name="id_anggota" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php 
                    foreach ($list_anggota as $row) {
                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['nama']) . " (" . htmlspecialchars($row['nik']) . ")</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Simpanan</label>
                <select name="jenis_simpanan" required>
                    <option value="Simpanan Pokok">Simpanan Pokok</option>
                    <option value="Simpanan Wajib">Simpanan Wajib</option>
                    <option value="Simpanan Sukarela">Simpanan Sukarela</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Simpanan (Rp)</label>
                <input type="number" name="jumlah" placeholder="Masukkan nominal" min="1000" step="1000" required>
            </div>

            <div class="form-actions">
                <button type="submit" name="simpan_transaksi" class="btn btn-primary">💾 Simpan Pembayaran</button>
                <a href="simpanan.php" class="btn btn-danger">❌ Batal</a>
            </div>
        </form>
    </div>

</body>
</html>