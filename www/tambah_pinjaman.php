<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['login_admin'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once __DIR__ . "/api/koneksi.php";

$kolom_tabel = [];

if (isset($_POST['simpan_pinjaman'])) {
    $id_anggota      = trim($_POST['id_anggota'] ?? '');
    $jumlah_pinjaman = isset($_POST['jumlah_pinjaman']) ? (float)$_POST['jumlah_pinjaman'] : 0;
    $bunga_persen    = isset($_POST['bunga_persen']) ? (float)$_POST['bunga_persen'] : 0;
    $tenor           = isset($_POST['tenor']) ? (int)$_POST['tenor'] : 1;
    $tanggal         = date('Y-m-d');
    $no_pinjaman     = (int)date('ymdHis');

    if (empty($id_anggota)) {
        echo "<script>alert('Gagal: Silakan pilih anggota terlebih dahulu!');</script>";
    } else {
        try {
            $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $q_cek = $koneksi->query("PRAGMA table_info(pinjaman)");
            $kolom_tabel = $q_cek->fetchAll(PDO::FETCH_ASSOC);
            
            // Disesuaikan menggunakan kolom tanggal_pinjam dan sisa_pokok
            $sql = "INSERT INTO pinjaman (no_pinjaman, id_anggota, jumlah_pinjaman, sisa_pokok, bunga, tenor, tanggal_pinjam, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $koneksi->prepare($sql);
            $simpan = $stmt->execute([
                (int)$no_pinjaman,
                (int)$id_anggota,
                (float)$jumlah_pinjaman,
                (float)$jumlah_pinjaman, 
                (float)$bunga_persen,
                (int)$tenor,
                (string)$tanggal,
                'Berjalan'
            ]);

            if ($simpan) {
                echo "<script>alert('Sukses! Pinjaman berhasil disimpan.'); window.location.href='pinjaman.php';</script>";
                exit();
            }
        } catch (Exception $e) {
            echo "<div style='background: red; color: white; padding: 15px; margin: 10px; font-weight: bold;'>";
            echo "ERROR SQLITE: " . $e->getMessage() . "<br><br>";
            echo "Struktur Kolom Tabel Anda Saat Ini:<br>";
            echo "<pre>"; print_r($kolom_tabel); echo "</pre>";
            echo "</div>";
            exit();
        }
    }
}

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
    <title>Tambah Pinjaman Baru - Koperasi</title>
    <style>
        body { background-color: #1d3538; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; margin: 0; }
        .card-box { background: #ffffff; max-width: 650px; margin: 0 auto; padding: 30px; border-radius: 16px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .form-title { color: #044b3b; font-size: 20px; font-weight: bold; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #333; margin-bottom: 6px; font-size: 14px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; }
        .form-control:focus { border-color: #044b3b; }
        .note { font-size: 12px; color: #666; margin-top: 4px; font-style: italic; }
        .estimation-box { background-color: #e6f4f1; border: 1px solid #b2dfdb; border-radius: 8px; padding: 15px; margin-top: 20px; margin-bottom: 20px; }
        .estimation-item { display: flex; align-items: center; font-size: 14px; margin-bottom: 8px; color: #2e7d32; font-weight: 600; }
        .estimation-item:last-child { margin-bottom: 0; }
        .estimation-item.total { color: #c62828; font-size: 15px; }
        .btn-group { display: flex; gap: 10px; margin-top: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-primary { background-color: #044b3b; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
    </style>
</head>
<body>

<div class="card-box">
    <div class="form-title">📄 Form Input Pinjaman Baru</div>

    <form action="" method="POST">
        <div class="form-group">
            <label for="id_anggota">Pilih Anggota</label>
            <select name="id_anggota" id="id_anggota" class="form-control" required>
                <option value="">-- Pilih Anggota --</option>
                <?php foreach ($list_anggota as $a): ?>
                    <option value="<?php echo $a['id']; ?>">
                        <?php echo htmlspecialchars($a['nama']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="jumlah_pinjaman">Jumlah Besar Pinjaman (Rp)</label>
            <input type="number" name="jumlah_pinjaman" id="jumlah_pinjaman" class="form-control" value="50000" required>
        </div>

        <div class="form-group">
            <label for="bunga_persen">Besaran Bunga per Bulan (%)</label>
            <input type="number" step="0.01" name="bunga_persen" id="bunga_persen" class="form-control" value="2" required>
            <div class="note">*Bunga akan terus berjalan per bulan jika pokok belum lunas melebihi tenor.</div>
        </div>

        <div class="form-group">
            <label for="tenor">Jangka Waktu Pinjaman (Tenor)</label>
            <select name="tenor" id="tenor" class="form-control" required>
                <option value="1">1 Bulan</option>
                <option value="2">2 Bulan</option>
                <option value="3" selected>3 Bulan</option>
                <option value="6">6 Bulan</option>
                <option value="12">12 Bulan</option>
            </select>
        </div>

        <div class="estimation-box">
            <div class="estimation-item">📊 Estimasi Angsuran Pokok: <span id="est_pokok" style="margin-left: 6px;">Rp 0 / bulan</span></div>
            <div class="estimation-item">📊 Estimasi Bunga Awal: <span id="est_bunga" style="margin-left: 6px;">Rp 0 / bulan</span></div>
            <div class="estimation-item total">➡️ Total Tagihan Awal: <span id="est_total" style="margin-left: 6px;">Rp 0</span></div>
        </div>

        <div class="btn-group">
            <button type="submit" name="simpan_pinjaman" class="btn btn-primary">🧪 Setujui Pinjaman</button>
            <a href="pinjaman.php" class="btn btn-danger">❌ Batal</a>
        </div>
    </form>
</div>

<script>
    function hitungEstimasi() {
        const pinjaman = parseFloat(document.getElementById('jumlah_pinjaman').value) || 0;
        const bungaPersen = parseFloat(document.getElementById('bunga_persen').value) || 0;
        const tenor = parseInt(document.getElementById('tenor').value) || 1;

        const pokokPerBulan = pinjaman / tenor;
        const bungaPerBulan = (pinjaman * bungaPersen) / 100;
        const totalTagihanAwal = pinjaman + (bungaPerBulan * tenor);

        const formatRupiah = (val) => "Rp " + Math.round(val).toLocaleString('id-ID');

        document.getElementById('est_pokok').innerText = formatRupiah(pokokPerBulan) + " / bulan";
        document.getElementById('est_bunga').innerText = formatRupiah(bungaPerBulan) + " / bulan";
        document.getElementById('est_total').innerText = formatRupiah(totalTagihanAwal);
    }

    document.getElementById('jumlah_pinjaman').addEventListener('input', hitungEstimasi);
    document.getElementById('bunga_persen').addEventListener('input', hitungEstimasi);
    document.getElementById('tenor').addEventListener('change', hitungEstimasi);
    window.onload = hitungEstimasi;
</script>

</body>
</html>