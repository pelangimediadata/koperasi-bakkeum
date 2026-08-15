<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$pesan = '';
$tipe_pesan = '';
$last_inserted_id = 0; 
$is_admin = (isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin') || (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') || isset($_SESSION['login_admin']);

// Tangkap filter untuk mempertahankan state URL setelah aksi
$filter_bulan = $_GET['bulan'] ?? date('m');
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_kategori = $_GET['kategori_filter'] ?? '';

// ==========================================
// EKSEKUSI PROSES HAPUS
// ==========================================
if (isset($_GET['hapus']) && !empty($_GET['hapus'])) {
    if (!$is_admin) {
        $pesan = "Akses ditolak! Hanya Admin yang berhak menghapus data operasional.";
        $tipe_pesan = "danger";
    } else {
        $id_del = trim($_GET['hapus']);
        
        $stmt_del = $koneksi->prepare("DELETE FROM operasional WHERE id_operasional = ?");
        if ($stmt_del->execute([$id_del])) {
            header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?bulan=$filter_bulan&tahun=$filter_tahun&kategori_filter=" . urlencode($filter_kategori) . "&status=sukses_hapus");
            exit();
        } else {
            $err_info = $stmt_del->errorInfo();
            $pesan = "Gagal menghapus data: " . ($err_info[2] ?? 'Unknown error');
            $tipe_pesan = "danger";
        }
    }
}

// Tangkap notifikasi sukses hapus via URL jika ada
if (isset($_GET['status']) && $_GET['status'] === 'sukses_hapus') {
    $pesan = "Data operasional berhasil dihapus!";
    $tipe_pesan = "success";
}

// 1. PROSES TAMBAH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_operasional'])) {
    $tanggal           = trim($_POST['tanggal']);
    $jenis_transaksi   = trim($_POST['jenis_transaksi']); 
    $kategori          = trim($_POST['kategori']); 
    $nomor_bukti       = trim($_POST['nomor_bukti']);
    $jumlah            = (float) $_POST['jumlah'];
    $keterangan        = trim($_POST['keterangan']);
    $penerima_penyetor = trim($_POST['penerima_penyetor']);

    if (empty($tanggal) || empty($jenis_transaksi) || empty($kategori) || $jumlah <= 0) {
        $pesan = "Lengkapi form tanggal, jenis transaksi, kategori, dan jumlah nominal dengan benar!";
        $tipe_pesan = "danger";
    } else {
        $query_ins = "INSERT INTO operasional (tanggal, jenis_transaksi, kategori, nomor_bukti, jumlah, keterangan, penerima_penyetor) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_ins = $koneksi->prepare($query_ins);
        if ($stmt_ins->execute([$tanggal, $jenis_transaksi, $kategori, $nomor_bukti, $jumlah, $keterangan, $penerima_penyetor])) {
            $last_inserted_id = $koneksi->lastInsertId();
            
            $check_kas = false;
            try {
                $chk = $koneksi->query("SELECT 1 FROM kas LIMIT 1");
                if ($chk) $check_kas = true;
            } catch (Exception $e) {
                $check_kas = false;
            }

            if ($check_kas) {
                $ket_kas = "Operasional [$kategori]: $keterangan";
                $stmt_kas = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, ?, ?, ?)");
                $stmt_kas->execute([$tanggal, $jenis_transaksi, $jumlah, $ket_kas]);
            }
            $pesan = "Data anggaran operasional berhasil disimpan!";
            $tipe_pesan = "success";
        } else {
            $err_info = $stmt_ins->errorInfo();
            $pesan = "Gagal menyimpan data: " . ($err_info[2] ?? 'Unknown error');
            $tipe_pesan = "danger";
        }
    }
}

// 2. PROSES EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_operasional'])) {
    $id_op             = trim($_POST['id_operasional']);
    $tanggal           = trim($_POST['tanggal']);
    $jenis_transaksi   = trim($_POST['jenis_transaksi']);
    $kategori          = trim($_POST['kategori']);
    $nomor_bukti       = trim($_POST['nomor_bukti']);
    $jumlah            = (float) $_POST['jumlah'];
    $keterangan        = trim($_POST['keterangan']);
    $penerima_penyetor = trim($_POST['penerima_penyetor']);

    if (empty($id_op) || empty($tanggal) || empty($kategori) || $jumlah <= 0) {
        $pesan = "Lengkapi data perubahan dengan benar!";
        $tipe_pesan = "danger";
    } else {
        $query_upd = "UPDATE operasional SET tanggal = ?, jenis_transaksi = ?, kategori = ?, nomor_bukti = ?, jumlah = ?, keterangan = ?, penerima_penyetor = ? WHERE id_operasional = ?";
        $stmt_upd = $koneksi->prepare($query_upd);
        if ($stmt_upd->execute([$tanggal, $jenis_transaksi, $kategori, $nomor_bukti, $jumlah, $keterangan, $penerima_penyetor, $id_op])) {
            $pesan = "Data operasional berhasil diperbarui!";
            $tipe_pesan = "success";
        } else {
            $err_info = $stmt_upd->errorInfo();
            $pesan = "Gagal memperbarui data: " . ($err_info[2] ?? 'Unknown error');
            $tipe_pesan = "danger";
        }
    }
}

// Filter aman menggunakan pencarian string LIKE berdasarkan Tahun dan Bulan
$periode_target = sprintf("%04d-%02d", $filter_tahun, $filter_bulan);
$where_clause = "WHERE tanggal LIKE ?";
$params_filter = ["%$periode_target%"];

if (!empty($filter_kategori)) {
    $where_clause .= " AND kategori = ?";
    $params_filter[] = $filter_kategori;
}

$nama_bln = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Anggaran Operasional - Koperasi Bakkeum</title>
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
        .main-content { flex-grow: 1; padding: 25px 30px; overflow-y: auto; background: transparent; width: 100%; }
        
        .dashboard-header-flex {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); padding: 15px 25px;
            border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap; gap: 15px;
        }
        .dashboard-title-box h2 { color: #ffffff; font-size: 22px; font-weight: 700; margin: 0; }
        .dashboard-title-box p { color: #b2dfdb; font-size: 12px; margin: 3px 0 0 0; }
        .header-live-clock {
            background: rgba(0, 77, 64, 0.6); color: #e0f2f1; padding: 8px 16px; border-radius: 20px;
            font-size: 13px; font-weight: 600; border: 1px solid rgba(255, 255, 255, 0.15); display: flex; align-items: center; gap: 8px;
        }
        .main-card {
            background: rgba(255, 255, 255, 0.96); padding: 25px; border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 25px;
        }
        .section-title {
            color: #044b3b; font-size: 16px; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #444; }
        .form-group input, .form-group select, .form-group textarea {
            padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 100%;
        }
        .btn {
            padding: 9px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-1px); filter: brightness(0.95); }
        .btn-simpan { background: #28a745; color: white; padding: 10px 16px; font-size: 14px; width: 100%; }
        .btn-edit { background: #ffc107; color: #333; padding: 5px 10px; font-size: 12px; }
        .btn-hapus { background: #dc3545; color: white; padding: 5px 10px; font-size: 12px; }
        .btn-cetak { background: #17a2b8; color: white; padding: 10px 16px; font-size: 14px; }
        .btn-batal { background: #6c757d; color: white; }
        .btn-print-bukti { background: #6610f2; color: white; padding: 10px 16px; font-size: 14px; }
        
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 10px;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; min-width: 700px; }
        table th { background-color: #044b3b; color: white; padding: 10px 12px; font-weight: 600; border: 1px solid #03362a; }
        table td { padding: 10px 12px; border-bottom: 1px solid #e0e0e0; color: #333; border-right: 1px solid #e0e0e0; border-left: 1px solid #e0e0e0; }
        tbody tr:hover { background-color: #f1f8f6; }
        
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-masuk { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-keluar { background-color: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); padding: 15px; overflow-y: auto;}
        .modal-content { background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 12px; width: 100%; max-width: 480px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .modal-header { font-size: 16px; font-weight: bold; color: #044b3b; margin-bottom: 15px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        
        .kop-surat { display: none; text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h2 { font-size: 18px; font-weight: bold; text-transform: uppercase; color: #000; }
        .kop-surat p { font-size: 12px; color: #333; }
        
        .modal-bukti-overlay {
            display: <?php echo ($last_inserted_id > 0) ? 'flex' : 'none'; ?>;
            position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; padding: 15px;
        }
        .modal-bukti-box { background: white; padding: 25px; border-radius: 10px; width: 100%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; }

        /* Responsif Media Queries untuk HP / Tablet Kecil */
        @media (max-width: 768px) {
            body { display: block; }
            .app-container { flex-direction: column; }
            .main-content { padding: 15px 10px; }
            .dashboard-header-flex { padding: 12px 15px; flex-direction: column; align-items: flex-start; }
            .header-live-clock { width: 100%; justify-content: center; }
            .main-card { padding: 15px; border-radius: 12px; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-group[style*="grid-column"] { grid-column: span 1 !important; }
            .modal-content { margin: 15% auto; padding: 15px; }
            .modal-bukti-box { padding: 20px; }
        }

        @media print {
            body { background: white !important; color: black !important; display: block !important; height: auto !important; }
            .app-container { display: block !important; }
            aside, .sidebar, .dashboard-header-flex, .main-card:not(#laporanResmiCard), .btn, form, script, .modal, .print-hide, .modal-bukti-overlay { display: none !important; }
            .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; display: block !important; }
            #laporanResmiCard { background: white !important; box-shadow: none !important; border: none !important; padding: 0 !important; margin: 0 !important; width: 100% !important; display: block !important; }
            .kop-surat { display: block !important; }
            .print-signature { display: block !important; page-break-inside: avoid; }
            table { border-collapse: collapse !important; width: 100% !important; min-width: auto !important; }
            table th, table td { border: 1px solid #000 !important; color: #000 !important; padding: 6px 8px !important; }
        }
    </style>
</head>
<body>

<?php if ($last_inserted_id > 0): 
    $stmt_last = $koneksi->prepare("SELECT * FROM operasional WHERE id_operasional = ?");
    $stmt_last->execute([$last_inserted_id]);
    $d_last = $stmt_last->fetch(PDO::FETCH_ASSOC);
    if ($d_last):
?>
<div class="modal-bukti-overlay" id="modalBuktiPopup">
    <div class="modal-bukti-box">
        <h3 style="color: #044b3b; margin-bottom: 10px;">✅ Transaksi Berhasil Disimpan!</h3>
        <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Anda dapat mencetak bukti <?php echo strtolower($d_last['jenis_transaksi']); ?> anggaran ini sekarang.</p>
        <div style="background: #f8fafc; padding: 12px; border-radius: 6px; text-align: left; font-size: 13px; margin-bottom: 20px; border: 1px solid #cbd5e1;">
            <div><b>No. Bukti:</b> <?php echo htmlspecialchars($d_last['nomor_bukti'] ?: '-'); ?></div>
            <div><b>Tanggal:</b> <?php echo date('d-m-Y', strtotime($d_last['tanggal'])); ?></div>
            <div><b>Kategori:</b> <?php echo htmlspecialchars($d_last['kategori']); ?></div>
            <div><b>Jumlah:</b> Rp <?php echo number_format($d_last['jumlah'], 0, ',', '.'); ?></div>
            <div><b>Uraian:</b> <?php echo htmlspecialchars($d_last['keterangan']); ?></div>
        </div>
        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-batal" onclick="document.getElementById('modalBuktiPopup').style.display='none'">Tutup</button>
            <button type="button" class="btn btn-print-bukti" onclick="printBuktiTrans('<?php echo $last_inserted_id; ?>')">🖨️ Cetak Bukti Transaksi</button>
        </div>
    </div>
</div>
<?php endif; endif; ?>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>📊 Manajemen Anggaran Operasional</h2>
                <p>Pencatatan Pemasukan, Belanja Modal, Honor Pengurus, serta Barang & Jasa Operasional Koperasi Bakkeum.</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?php echo $tipe_pesan; ?>"><?php echo htmlspecialchars($pesan); ?></div>
        <?php endif; ?>

        <!-- Form Input -->
        <div class="main-card print-hide">
            <div class="section-title">📝 Form Input Transaksi Operasional</div>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="tanggal">Tanggal:</label>
                        <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="jenis_transaksi">Jenis Arus Dana:</label>
                        <select id="jenis_transaksi" name="jenis_transaksi" onchange="updateKategoriOptions(this.value, 'kategori')" required>
                            <option value="Masuk">Pemasukan Anggaran</option>
                            <option value="Keluar" selected>Pengeluaran Anggaran</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="kategori">Kategori Anggaran:</label>
                        <select id="kategori" name="kategori" required>
                            <option value="Belanja Modal">Belanja Modal (Aset/Inventaris)</option>
                            <option value="Belanja Pegawai (Honor Pengurus)">Belanja Pegawai (Honor Pengurus)</option>
                            <option value="Belanja Barang dan Jasa">Belanja Barang dan Jasa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nomor_bukti">Nomor Bukti / Kwitansi:</label>
                        <input type="text" id="nomor_bukti" name="nomor_bukti" placeholder="Contoh: BPK-001/VIII/2026">
                    </div>

                    <div class="form-group">
                        <label for="jumlah">Jumlah Nominal (Rp):</label>
                        <input type="number" id="jumlah" name="jumlah" placeholder="Contoh: 1500000" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="penerima_penyetor">Pihak Penerima / Penyetor:</label>
                        <input type="text" id="penerima_penyetor" name="penerima_penyetor" placeholder="Nama Personil / Toko / Instansi">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label for="keterangan">Keterangan / Uraian Keperluan:</label>
                        <textarea id="keterangan" name="keterangan" rows="2" placeholder="Jelaskan rincian keperluan anggaran operasional..."></textarea>
                    </div>
                </div>
                <button type="submit" name="simpan_operasional" class="btn btn-simpan">💾 Simpan & Cetak Bukti</button>
            </form>
        </div>

        <!-- Tabel Riwayat Panel Admin -->
        <div class="main-card print-hide">
            <div class="section-title">📋 Riwayat & Data Input Operasional</div>
            
            <form method="GET" action="" style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 15px; flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom:0; flex: 1; min-width: 130px;">
                    <label style="font-size:11px;">Bulan:</label>
                    <select name="bulan" style="width: 100%;">
                        <?php 
                        foreach($nama_bln as $num => $nm) {
                            $sel = ($num == $filter_bulan) ? 'selected' : '';
                            echo "<option value='$num' $sel>$nm</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0; flex: 1; min-width: 90px;">
                    <label style="font-size:11px;">Tahun:</label>
                    <input type="number" name="tahun" value="<?php echo htmlspecialchars($filter_tahun); ?>" style="padding: 8px; width: 100%;">
                </div>
                <div class="form-group" style="margin-bottom:0; flex: 2; min-width: 180px;">
                    <label style="font-size:11px;">Kategori:</label>
                    <select name="kategori_filter" style="width: 100%;">
                        <option value="">-- Semua Kategori --</option>
                        <option value="Pemasukan Operasional" <?php if($filter_kategori=='Pemasukan Operasional') echo 'selected'; ?>>Pemasukan Operasional</option>
                        <option value="Belanja Modal" <?php if($filter_kategori=='Belanja Modal') echo 'selected'; ?>>Belanja Modal</option>
                        <option value="Belanja Pegawai (Honor Pengurus)" <?php if($filter_kategori=='Belanja Pegawai (Honor Pengurus)') echo 'selected'; ?>>Belanja Pegawai (Honor Pengurus)</option>
                        <option value="Belanja Barang dan Jasa" <?php if($filter_kategori=='Belanja Barang dan Jasa') echo 'selected'; ?>>Belanja Barang dan Jasa</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn" style="background: #007bff; color:white; padding: 9px 14px; height: 40px;">🔍 Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">No</th>
                            <th>Tanggal</th>
                            <th>No. Bukti</th>
                            <th>Arus</th>
                            <th>Kategori</th>
                            <th>Uraian / Keterangan</th>
                            <th>Penerima/Penyetor</th>
                            <th style="text-align: right;">Jumlah Nominal</th>
                            <th style="text-align: center; width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt_op = $koneksi->prepare("SELECT * FROM operasional $where_clause ORDER BY tanggal DESC, id_operasional DESC");
                        $stmt_op->execute($params_filter);
                        $rows_op = $stmt_op->fetchAll(PDO::FETCH_ASSOC);
                        $no = 1;

                        if (count($rows_op) > 0) {
                            foreach ($rows_op as $row) {
                                $is_masuk = ($row['jenis_transaksi'] === 'Masuk');
                                
                                $id_val = htmlspecialchars($row['id_operasional'] ?? '');
                                $tgl_val = htmlspecialchars($row['tanggal'] ?? '');
                                $jenis_val = htmlspecialchars($row['jenis_transaksi'] ?? '');
                                $kat_val = $row['kategori'] ?? '';
                                $nob_val = $row['nomor_bukti'] ?? '';
                                $jml_val = htmlspecialchars($row['jumlah'] ?? '');
                                $pen_val = $row['penerima_penyetor'] ?? '';
                                $ket_val = $row['keterangan'] ?? '';
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($row['tanggal'])); ?></td>
                                    <td><b><?php echo htmlspecialchars($row['nomor_bukti'] ?: '-'); ?></b></td>
                                    <td>
                                        <?php if ($is_masuk): ?>
                                            <span class="badge-masuk">MASUK</span>
                                        <?php else: ?>
                                            <span class="badge-keluar">KELUAR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($row['kategori']); ?></td>
                                    <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['penerima_penyetor'] ?: '-'); ?></td>
                                    <td style="text-align: right; font-weight: bold; color: <?php echo $is_masuk ? '#16a34a' : '#dc2626'; ?>;">
                                        <?php echo ($is_masuk ? '+Rp ' : '-Rp ') . number_format($row['jumlah'], 0, ',', '.'); ?>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn btn-print-bukti" style="padding: 5px 8px; font-size: 11px;" onclick="printBuktiTrans('<?php echo $id_val; ?>')">🖨️ Bukti</button>
                                        
                                        <button type="button" class="btn btn-edit" onclick="openEditModal('<?php echo $id_val; ?>', '<?php echo $tgl_val; ?>', '<?php echo $jenis_val; ?>', '<?php echo addslashes($kat_val); ?>', '<?php echo addslashes($nob_val); ?>', '<?php echo $jml_val; ?>', '<?php echo addslashes($pen_val); ?>', '<?php echo addslashes($ket_val); ?>')">✏️</button>
                                        
                                        <?php if ($is_admin): ?>
                                        <button type="button" class="btn btn-hapus" onclick="openHapusModal('<?php echo $id_val; ?>', '<?php echo addslashes($kat_val); ?>', '<?php echo number_format($row['jumlah'], 0, ',', '.'); ?>')">🗑️</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align:center; padding: 20px;'>Belum ada data anggaran operasional untuk periode ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Laporan Resmi Format Buku Kas -->
        <div class="main-card" id="laporanResmiCard">
            <div class="kop-surat">
                <h2>KOPERASI KELOMPOK / UNIT USAHA BAKKEUM</h2>
                <p>Laporan Resmi Buku Kas Anggaran Operasional Periode <?php echo $nama_bln[(int)$filter_bulan] . ' ' . $filter_tahun; ?></p>
            </div>

            <!-- Bagian Filter Periode dan Tombol Cetak -->
            <div class="print-hide" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #ffffff; padding: 15px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); flex-wrap: wrap; gap: 15px;">
                <form method="GET" action="operasional.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%;">
                    <label style="font-weight: 600; font-size: 13px; color: #333; width: 100%;">Filter Periode:</label>
                    <select name="bulan" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 13px; flex: 1; min-width: 120px;">
                        <?php
                        foreach ($nama_bln as $key => $nama_bln_item) {
                            $sel = ($filter_bulan == sprintf('%02d', $key)) ? 'selected' : '';
                            echo "<option value='" . sprintf('%02d', $key) . "' $sel>$nama_bln_item</option>";
                        }
                        ?>
                    </select>
                    <select name="tahun" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 13px; flex: 1; min-width: 90px;">
                        <?php
                        for ($t = date('Y'); $t >= date('Y') - 5; $t--) {
                            $sel = ($filter_tahun == $t) ? 'selected' : '';
                            echo "<option value='$t' $sel>$t</option>";
                        }
                        ?>
                    </select>
                    <input type="hidden" name="kategori_filter" value="<?php echo htmlspecialchars($filter_kategori); ?>">
                    <button type="submit" class="btn" style="background: #007bff; color: white; padding: 8px 15px; font-size: 13px;">Tampilkan</button>
                </form>

                <div style="width: 100%;">
					<button type="button" onclick="cetakLaporanResmi()" class="btn btn-cetak" style="padding: 8px 15px; font-size: 13px; border-radius: 6px; width: 100%;">
						🖨️ Cetak Laporan Resmi
					</button>
				</div>
            </div>

            <div class="section-title print-hide" style="display: flex; justify-content: space-between; align-items: center;">
                <span>📈 Laporan Resmi Buku Kas Operasional (<?php echo $nama_bln[(int)$filter_bulan] . ' ' . $filter_tahun; ?>)</span>
            </div>

            <?php
            $params_sum = $params_filter;
            
            $stmt_sum_masuk = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND jenis_transaksi = 'Masuk'");
            $stmt_sum_masuk->execute($params_sum);
            $total_pemasukan = $stmt_sum_masuk->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt_sum_modal = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Modal'");
            $stmt_sum_modal->execute($params_sum);
            $total_belanja_modal = $stmt_sum_modal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt_sum_pegawai = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Pegawai (Honor Pengurus)'");
            $stmt_sum_pegawai->execute($params_sum);
            $total_belanja_pegawai = $stmt_sum_pegawai->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $stmt_sum_bj = $koneksi->prepare("SELECT SUM(jumlah) as total FROM operasional $where_clause AND kategori = 'Belanja Barang dan Jasa'");
            $stmt_sum_bj->execute($params_sum);
            $total_belanja_bj = $stmt_sum_bj->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

            $total_pengeluaran = $total_belanja_modal + $total_belanja_pegawai + $total_belanja_bj;
            $saldo_netto = $total_pemasukan - $total_pengeluaran;
            ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #2e7d32;">
                    <div style="font-size: 12px; font-weight: bold; color: #2e7d32;">TOTAL PEMASUKAN</div>
                    <div style="font-size: 18px; font-weight: bold; color: #1b5e20; margin-top: 5px;">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></div>
                </div>
                <div style="background: #fff8e1; padding: 15px; border-radius: 8px; border-left: 4px solid #f57f17;">
                    <div style="font-size: 12px; font-weight: bold; color: #f57f17;">BELANJA MODAL</div>
                    <div style="font-size: 18px; font-weight: bold; color: #e65100; margin-top: 5px;">Rp <?php echo number_format($total_belanja_modal, 0, ',', '.'); ?></div>
                </div>
                <div style="background: #e1f5fe; padding: 15px; border-radius: 8px; border-left: 4px solid #0288d1;">
                    <div style="font-size: 12px; font-weight: bold; color: #0288d1;">BELANJA PEGAWAI (HONOR)</div>
                    <div style="font-size: 18px; font-weight: bold; color: #01579b; margin-top: 5px;">Rp <?php echo number_format($total_belanja_pegawai, 0, ',', '.'); ?></div>
                </div>
                <div style="background: #fce4ec; padding: 15px; border-radius: 8px; border-left: 4px solid #c2185b;">
                    <div style="font-size: 12px; font-weight: bold; color: #c2185b;">BARANG & JASA</div>
                    <div style="font-size: 18px; font-weight: bold; color: #880e4f; margin-top: 5px;">Rp <?php echo number_format($total_belanja_bj, 0, ',', '.'); ?></div>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #cbd5e1; margin-bottom: 25px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="color: #334155;">Sisa Saldo Netto Operasional (Pemasukan - Total Pengeluaran):</strong>
                </div>
                <div style="font-size: 18px; font-weight: bold; color: <?php echo $saldo_netto >= 0 ? '#0f766e' : '#b91c1c'; ?>;">
                    Rp <?php echo number_format($saldo_netto, 0, ',', '.'); ?>
                </div>
            </div>

            <!-- TABEL RINCIAN BUKU KAS -->
            <div style="margin-top: 20px;">
                <div style="font-size: 14px; font-weight: bold; margin-bottom: 10px; color: #044b3b; text-transform: uppercase;">Buku Kas Transaksi Operasional</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 35px; text-align: center;">No</th>
                                <th style="width: 90px;">Tanggal</th>
                                <th>Uraian / Keterangan</th>
                                <th style="width: 100px;">No. Bukti</th>
                                <th style="text-align: right; width: 110px;">Masuk (Rp)</th>
                                <th style="text-align: right; width: 110px;">Keluar (Rp)</th>
                                <th style="text-align: right; width: 120px;">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_buku = $koneksi->prepare("SELECT * FROM operasional $where_clause ORDER BY tanggal ASC, id_operasional ASC");
                            $stmt_buku->execute($params_filter);
                            $rows_buku = $stmt_buku->fetchAll(PDO::FETCH_ASSOC);

                            $no_bk = 1;
                            $running_saldo = 0;
                            $sum_total_masuk = 0;
                            $sum_total_keluar = 0;

                            if (count($rows_buku) > 0) {
                                foreach ($rows_buku as $rb) {
                                    $is_m = ($rb['jenis_transaksi'] === 'Masuk');
                                    $val_masuk = $is_m ? (float)$rb['jumlah'] : 0;
                                    $val_keluar = !$is_m ? (float)$rb['jumlah'] : 0;
                                    
                                    if ($is_m) {
                                        $running_saldo += $val_masuk;
                                        $sum_total_masuk += $val_masuk;
                                    } else {
                                        $running_saldo -= $val_keluar;
                                        $sum_total_keluar += $val_keluar;
                                    }
                                    ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $no_bk++; ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($rb['tanggal'])); ?></td>
                                        <td>
                                            <b><?php echo htmlspecialchars($rb['kategori']); ?></b><br>
                                            <span style="font-size: 11px; color: #555;"><?php echo htmlspecialchars($rb['keterangan']); ?></span>
                                            <?php if(!empty($rb['penerima_penyetor'])): ?>
                                                <br><span style="font-size: 10px; color: #777;">Pihak: <?php echo htmlspecialchars($rb['penerima_penyetor']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($rb['nomor_bukti'] ?: '-'); ?></td>
                                        <td style="text-align: right; color: #16a34a; font-weight: 500;">
                                            <?php echo $val_masuk > 0 ? number_format($val_masuk, 0, ',', '.') : '-'; ?>
                                        </td>
                                        <td style="text-align: right; color: #dc2626; font-weight: 500;">
                                            <?php echo $val_keluar > 0 ? number_format($val_keluar, 0, ',', '.') : '-'; ?>
                                        </td>
                                        <td style="text-align: right; font-weight: bold; color: #0f766e;">
                                            <?php echo number_format($running_saldo, 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <tr style="background-color: #f8fafc; font-weight: bold;">
                                    <td colspan="4" style="text-align: right; border-top: 2px solid #000;">TOTAL BULAN INI:</td>
                                    <td style="text-align: right; color: #16a34a; border-top: 2px solid #000;">Rp <?php echo number_format($sum_total_masuk, 0, ',', '.'); ?></td>
                                    <td style="text-align: right; color: #dc2626; border-top: 2px solid #000;">Rp <?php echo number_format($sum_total_keluar, 0, ',', '.'); ?></td>
                                    <td style="text-align: right; color: #0f766e; border-top: 2px solid #000;">Rp <?php echo number_format($running_saldo, 0, ',', '.'); ?></td>
                                </tr>
                                <?php
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center; padding: 15px;'>Tidak ada transaksi buku kas pada periode ini.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tanda Tangan Pengurus (Muncul saat dicetak) -->
            <div class="print-signature" style="display: none; margin-top: 40px; float: right; text-align: center; width: 250px;">
                <p>Pengurus Koperasi,</p>
                <br><br><br>
                <p><b>( _________________________ )</b></p>
            </div>
        </div>

        <!-- Modal Edit -->
        <div id="modalEdit" class="modal">
            <div class="modal-content">
                <div class="modal-header">✏️ Edit Data Anggaran Operasional</div>
                <form method="POST" action="">
                    <input type="hidden" id="edit_id_operasional" name="id_operasional">
                    
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_tanggal">Tanggal:</label>
                        <input type="date" id="edit_tanggal" name="tanggal" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_jenis_transaksi">Jenis Arus Dana:</label>
                        <select id="edit_jenis_transaksi" name="jenis_transaksi" onchange="updateKategoriOptions(this.value, 'edit_kategori')" required>
                            <option value="Masuk">Pemasukan Anggaran</option>
                            <option value="Keluar">Pengeluaran Anggaran</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_kategori">Kategori Anggaran:</label>
                        <select id="edit_kategori" name="kategori" required>
                            <option value="Pemasukan Operasional">Pemasukan Operasional</option>
                            <option value="Belanja Modal">Belanja Modal (Aset/Inventaris)</option>
                            <option value="Belanja Pegawai (Honor Pengurus)">Belanja Pegawai (Honor Pengurus)</option>
                            <option value="Belanja Barang dan Jasa">Belanja Barang dan Jasa</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_nomor_bukti">Nomor Bukti / Kwitansi:</label>
                        <input type="text" id="edit_nomor_bukti" name="nomor_bukti">
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_jumlah">Jumlah Nominal (Rp):</label>
                        <input type="number" id="edit_jumlah" name="jumlah" min="1" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_penerima_penyetor">Pihak Penerima / Penyetor:</label>
                        <input type="text" id="edit_penerima_penyetor" name="penerima_penyetor">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_keterangan">Keterangan / Uraian:</label>
                        <textarea id="edit_keterangan" name="keterangan" rows="2"></textarea>
                    </div>

                    <div class="modal-buttons">
                        <button type="button" class="btn btn-batal" onclick="closeEditModal()">Batal</button>
                        <button type="submit" name="edit_operasional" class="btn btn-simpan" style="width: auto;">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus Data -->
        <div id="modalHapus" class="modal">
            <div class="modal-content" style="max-width: 400px; text-align: center;">
                <div class="modal-header" style="color: #dc3545; justify-content: center;">⚠️ Konfirmasi Hapus Data</div>
                <p style="font-size: 14px; color: #555; margin-bottom: 15px;">Apakah Anda yakin ingin menghapus data transaksi kategori <b id="hapus_info_kategori"></b> senilai <b id="hapus_info_jumlah"></b> ini?</p>
                <div class="modal-buttons" style="justify-content: center;">
                    <button type="button" class="btn btn-batal" onclick="closeHapusModal()">Batal</button>
                    <a href="" id="btnKonfirmasiHapus" class="btn btn-hapus">Ya, Hapus Data</a>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('timeText').textContent = `${hours}:${minutes}:${seconds}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function updateKategoriOptions(jenis, selectId) {
        const select = document.getElementById(selectId);
        select.innerHTML = '';
        if (jenis === 'Masuk') {
            let opt = document.createElement('option');
            opt.value = 'Pemasukan Operasional';
            opt.textContent = 'Pemasukan Operasional';
            select.appendChild(opt);
        } else {
            let opts = [
                {val: 'Belanja Modal', text: 'Belanja Modal (Aset/Inventaris)'},
                {val: 'Belanja Pegawai (Honor Pengurus)', text: 'Belanja Pegawai (Honor Pengurus)'},
                {val: 'Belanja Barang dan Jasa', text: 'Belanja Barang dan Jasa'}
            ];
            opts.forEach(o => {
                let opt = document.createElement('option');
                opt.value = o.val;
                opt.textContent = o.text;
                select.appendChild(opt);
            });
        }
    }

    function openEditModal(id, tanggal, jenis, kategori, no_bukti, jumlah, penerima, keterangan) {
        document.getElementById('edit_id_operasional').value = id;
        document.getElementById('edit_tanggal').value = tanggal;
        document.getElementById('edit_jenis_transaksi').value = jenis;
        updateKategoriOptions(jenis, 'edit_kategori');
        document.getElementById('edit_kategori').value = kategori;
        document.getElementById('edit_nomor_bukti').value = no_bukti;
        document.getElementById('edit_jumlah').value = jumlah;
        document.getElementById('edit_penerima_penyetor').value = penerima;
        document.getElementById('edit_keterangan').value = keterangan;
        document.getElementById('modalEdit').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('modalEdit').style.display = 'none';
    }

    function openHapusModal(id, kategori, jumlah) {
        document.getElementById('hapus_info_kategori').textContent = kategori;
        document.getElementById('hapus_info_jumlah').textContent = 'Rp ' + jumlah;
        
        const bulan = "<?php echo $filter_bulan; ?>";
        const tahun = "<?php echo $filter_tahun; ?>";
        const kategori_filter = "<?php echo urlencode($filter_kategori); ?>";
        
        const deleteUrl = `operasional.php?hapus=${id}&bulan=${bulan}&tahun=${tahun}&kategori_filter=${kategori_filter}`;
        document.getElementById('btnKonfirmasiHapus').setAttribute('href', deleteUrl);
        
        document.getElementById('modalHapus').style.display = 'block';
    }

    function closeHapusModal() {
        document.getElementById('modalHapus').style.display = 'none';
    }

    function printBuktiTrans(id) {
        const win = window.open('cetak_bukti_transaksi.php?id=' + id, '_blank', 'width=800,height=600');
        if (win) win.focus();
    }

    window.onclick = function(event) {
        const modalEdit = document.getElementById('modalEdit');
        const modalHapus = document.getElementById('modalHapus');
        if (event.target === modalEdit) {
            modalEdit.style.display = 'none';
        }
        if (event.target === modalHapus) {
            modalHapus.style.display = 'none';
        }
    }
	function cetakLaporanResmi() {
        const bulan = document.querySelector('select[name="bulan"]').value;
        const tahun = document.querySelector('select[name="tahun"]').value;
        const kategori = document.querySelector('input[name="kategori_filter"]')?.value || '';
        
        const win = window.open(`cetak_laporan_operasional.php?bulan=${bulan}&tahun=${tahun}&kategori_filter=${kategori}`, '_blank', 'width=900,height=700');
        if (win) win.focus();
    }
</script>

</body>
</html>