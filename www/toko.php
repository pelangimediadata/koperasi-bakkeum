<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$pesan = '';
$tipe_pesan = '';

// Cek apakah level user adalah 'admin' (bukan petugas/lainnya)
$is_admin = (isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin') || (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');

// -------------------------------------------------------------
// 1. PROSES TAMBAH / UPDATE STOK BARANG (MODAL & JUAL)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_barang'])) {
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $harga_beli  = (float) ($_POST['harga_beli'] ?? 0);
    $harga_jual  = (float) ($_POST['harga_jual'] ?? 0);
    $stok        = (int) ($_POST['stok'] ?? 0);
    $tanggal_skr = date('Y-m-d');

    if (empty($nama_barang) || $harga_beli <= 0 || $harga_jual <= 0 || $stok < 0) {
        $pesan = "Isi nama barang, harga modal, harga jual, dan stok dengan benar!";
        $tipe_pesan = "danger";
    } else {
        $total_modal_belanja = $harga_beli * $stok;

        $stmt_cek = $koneksi->prepare("SELECT * FROM barang WHERE nama_barang = ?");
        $stmt_cek->execute([$nama_barang]);
        $d_b = $stmt_cek->fetch(PDO::FETCH_ASSOC);

        if ($d_b) {
            $id_b = $d_b['id_barang'];
            
            $stmt_upd = $koneksi->prepare("UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ? WHERE id_barang = ?");
            if ($stmt_upd->execute([$stok, $harga_beli, $harga_jual, $id_b])) {
                
                $ket_belanja = "[IDBRG:$id_b] Belanja Barang: " . $nama_barang;
                $stmt_belanja = $koneksi->prepare("INSERT INTO belanja_toko (tanggal, keterangan, total_belanja) VALUES (?, ?, ?)");
                $stmt_belanja->execute([$tanggal_skr, $ket_belanja, $total_modal_belanja]);
                
                $ket_kas = "[IDBRG:$id_b] Pengeluaran Belanja Barang Toko: " . $nama_barang;
                $stmt_kas = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, 'Keluar', ?, ?)");
                $stmt_kas->execute([$tanggal_skr, $total_modal_belanja, $ket_kas]);

                $pesan = "Stok & harga barang <b>$nama_barang</b> berhasil diperbarui!";
                $tipe_pesan = "success";
            }
        } else {
            $stmt_ins = $koneksi->prepare("INSERT INTO barang (nama_barang, harga_beli, harga_jual, stok) VALUES (?, ?, ?, ?)");
            if ($stmt_ins->execute([$nama_barang, $harga_beli, $harga_jual, $stok])) {
                $id_b_baru = $koneksi->lastInsertId();

                $ket_belanja = "[IDBRG:$id_b_baru] Belanja Barang: " . $nama_barang;
                $stmt_belanja = $koneksi->prepare("INSERT INTO belanja_toko (tanggal, keterangan, total_belanja) VALUES (?, ?, ?)");
                $stmt_belanja->execute([$tanggal_skr, $ket_belanja, $total_modal_belanja]);

                $ket_kas = "[IDBRG:$id_b_baru] Pengeluaran Belanja Barang Toko: " . $nama_barang;
                $stmt_kas = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, 'Keluar', ?, ?)");
                $stmt_kas->execute([$tanggal_skr, $total_modal_belanja, $ket_kas]);

                $pesan = "Barang baru <b>$nama_barang</b> berhasil disimpan!";
                $tipe_pesan = "success";
            }
        }
    }
}

// -------------------------------------------------------------
// 1.5. PROSES EDIT BARANG + SINKRONISASI KAS & BELANJA TOKO
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_barang'])) {
    $id_barang   = $_POST['id_barang'] ?? '';
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $harga_beli  = (float) ($_POST['harga_beli'] ?? 0);
    $harga_jual  = (float) ($_POST['harga_jual'] ?? 0);
    $stok        = (int) ($_POST['stok'] ?? 0);
    $tanggal_skr = date('Y-m-d');

    if (empty($id_barang) || empty($nama_barang) || $harga_beli <= 0 || $harga_jual <= 0 || $stok < 0) {
        $pesan = "Lengkapi data edit barang dengan benar!";
        $tipe_pesan = "danger";
    } else {
        $stmt_lama = $koneksi->prepare("SELECT * FROM barang WHERE id_barang = ?");
        $stmt_lama->execute([$id_barang]);
        $d_lama = $stmt_lama->fetch(PDO::FETCH_ASSOC);

        if ($d_lama) {
            $nama_lama = $d_lama['nama_barang'];
            $total_modal_baru = $harga_beli * $stok;

            // Hapus log belanja dan kas lama berdasarkan ID Barang
            $stmt_del_b = $koneksi->prepare("DELETE FROM belanja_toko WHERE keterangan LIKE ?");
            $stmt_del_b->execute(["%[IDBRG:$id_barang]%"]);

            $stmt_del_k = $koneksi->prepare("DELETE FROM kas WHERE keterangan LIKE ? AND jenis = 'Keluar'");
            $stmt_del_k->execute(["%[IDBRG:$id_barang]%"]);

            // Masukkan log belanja dan kas baru dengan menyertakan tag [IDBRG:$id_barang] agar konsisten
            $ket_belanja = "[IDBRG:$id_barang] Belanja Barang: " . $nama_barang;
            $stmt_ins_b = $koneksi->prepare("INSERT INTO belanja_toko (tanggal, keterangan, total_belanja) VALUES (?, ?, ?)");
            $stmt_ins_b->execute([$tanggal_skr, $ket_belanja, $total_modal_baru]);

            $ket_kas = "[IDBRG:$id_barang] Pengeluaran Belanja Barang Toko (Modal Barang): " . $nama_barang;
            $stmt_ins_k = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, 'Keluar', ?, ?)");
            $stmt_ins_k->execute([$tanggal_skr, $total_modal_baru, $ket_kas]);

            $stmt_update = $koneksi->prepare("UPDATE barang SET nama_barang = ?, harga_beli = ?, harga_jual = ?, stok = ? WHERE id_barang = ?");
            if ($stmt_update->execute([$nama_barang, $harga_beli, $harga_jual, $stok, $id_barang])) {
                $pesan = "Data barang <b>$nama_barang</b> berhasil diperbarui dan laporan kas telah disesuaikan!";
                $tipe_pesan = "success";
            } else {
                $pesan = "Gagal memperbarui barang.";
                $tipe_pesan = "danger";
            }
        } else {
            $pesan = "Data barang tidak ditemukan!";
            $tipe_pesan = "danger";
        }
    }
}

// -------------------------------------------------------------
// 2. PROSES SIMPAN TRANSAKSI PENJUALAN (TANPA MEMPENGARUHI KAS)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_penjualan'])) {
    $jenis_pembeli = $_POST['jenis_pembeli'] ?? 'umum';
    $id_anggota    = ($jenis_pembeli === 'anggota') ? ($_POST['id_anggota'] ?? NULL) : NULL;
    $nama_umum     = ($jenis_pembeli === 'umum') ? trim($_POST['nama_umum'] ?? 'Masyarakat Umum') : NULL;
    $id_barang     = $_POST['id_barang'] ?? '';
    $jumlah        = (int)($_POST['jumlah'] ?? 0);
    $tanggal       = $_POST['tanggal'] ?? date('Y-m-d');

    if (empty($id_barang) || $jumlah <= 0) {
        $pesan = "Pilih barang dan masukkan jumlah yang valid!";
        $tipe_pesan = "danger";
    } else if ($jenis_pembeli === 'anggota' && empty($id_anggota)) {
        $pesan = "Silakan pilih Anggota Koperasi!";
        $tipe_pesan = "danger";
    } else if ($jenis_pembeli === 'umum' && empty($nama_umum)) {
        $pesan = "Silakan masukkan nama pembeli masyarakat umum!";
        $tipe_pesan = "danger";
    } else {
        $stmt_brg = $koneksi->prepare("SELECT * FROM barang WHERE id_barang = ?");
        $stmt_brg->execute([$id_barang]);
        $d_brg = $stmt_brg->fetch(PDO::FETCH_ASSOC);

        if ($d_brg) {
            $harga_satuan = $d_brg['harga_jual'];
            $stok_tersedia = $d_brg['stok'];

            if ($stok_tersedia < $jumlah) {
                $pesan = "Stok barang tidak mencukupi! (Sisa Stok: $stok_tersedia)";
                $tipe_pesan = "danger";
            } else {
                $total_harga = $harga_satuan * $jumlah;

                // Status awal saat transaksi disimpan adalah belum bayar/tagihan
                $stmt_penjualan = $koneksi->prepare("INSERT INTO penjualan (id_anggota, nama_umum, id_barang, jumlah, harga_satuan, total_harga, tanggal_transaksi, status_bayar) VALUES (?, ?, ?, ?, ?, ?, ?, 'BELUM BAYAR / TAGIHAN')");
                
                if ($stmt_penjualan->execute([$id_anggota, $nama_umum, $id_barang, $jumlah, $harga_satuan, $total_harga, $tanggal])) {
                    
                    // Kurangi stok barang
                    $stmt_stok = $koneksi->prepare("UPDATE barang SET stok = stok - ? WHERE id_barang = ?");
                    $stmt_stok->execute([$jumlah, $id_barang]);

                    $pesan = "Transaksi penjualan berhasil disimpan! Silakan cetak nota untuk melakukan pembayaran.";
                    $tipe_pesan = "success";
                } else {
                    $pesan = "Gagal menyimpan transaksi.";
                    $tipe_pesan = "danger";
                }
            }
        }
    }
}
$q_list_barang = $koneksi->query("SELECT * FROM barang ORDER BY nama_barang ASC");
$q_list_anggota = $koneksi->query("SELECT * FROM anggota ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Toko Koperasi - Koperasi Bakkeum</title>
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
        .app-container { display: flex; min-height: 100vh; width: 100%; }
        .main-content { flex-grow: 1; padding: 25px 30px; overflow-y: auto; background: transparent; }

        .dashboard-header-flex {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px); padding: 15px 25px; border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .dashboard-title-box h2 { color: #ffffff; font-size: 22px; font-weight: 700; margin: 0; }
        .dashboard-title-box p { color: #b2dfdb; font-size: 12px; margin: 3px 0 0 0; }
        .header-live-clock {
            background: rgba(0, 77, 64, 0.6); color: #e0f2f1; padding: 8px 16px;
            border-radius: 20px; font-size: 13px; font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.15); display: flex; align-items: center; gap: 8px;
        }
        .main-card {
            background: rgba(255, 255, 255, 0.96); padding: 25px; border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); margin-bottom: 25px;
        }
        .section-title { color: #044b3b; font-size: 16px; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #444; }
        .form-group input, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; width: 100%; }
        .btn { padding: 9px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
        .btn-tambah { background: #007bff; color: white; padding: 10px 16px; font-size: 14px; }
        .btn-simpan { background: #28a745; color: white; padding: 10px 16px; font-size: 14px; }
        .btn-edit { background: #ffc107; color: #333; padding: 5px 10px; font-size: 12px; }
        .btn-hapus { background: #dc3545; color: white; padding: 5px 10px; font-size: 12px; }
        .btn-batal { background: #6c757d; color: white; }

        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; margin-top: 10px; }
        table th { background-color: #044b3b; color: white; padding: 12px 15px; font-weight: 600; }
        table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; }
        tbody tr:hover { background-color: #f1f8f6; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .badge-anggota { background-color: #d1ecf1; color: #0c5460; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-umum { background-color: #e2e3e5; color: #383d41; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; margin: 8% auto; padding: 25px; border-radius: 12px; width: 100%; max-width: 450px; }
        .modal-header { font-size: 16px; font-weight: bold; color: #044b3b; margin-bottom: 15px; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px; }
		/* Pengaturan Responsif untuk Handphone / Layar Kecil */
@media screen and (max-width: 768px) {
    /* Sidebar dibuat otomatis menyesuaikan atau bisa disembunyikan/diperkecil */
    .sidebar, nav.sidebar {
        width: 70px !important;
        min-width: 70px !important;
        overflow: hidden;
    }

    /* Sembunyikan teks menu, biarkan ikonnya saja jika di HP */
    .sidebar span, .sidebar .menu-text {
        display: none !important;
    }

    /* Konten utama mengambil sisa lebar layar penuh */
    .main-content, .content, .container {
        margin-left: 70px !important;
        width: calc(100% - 70px) !important;
        padding: 10px !important;
    }

    /* Sesuaikan ukuran kartu atau kotak agar tidak terpotong */
    .card, .box {
        width: 100% !important;
        box-sizing: border-box;
    }
}
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>🛍️ Manajemen Toko Koperasi</h2>
                <p>Kelola stok barang, kulakan, serta transaksi penjualan toko koperasi.</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?php echo $tipe_pesan; ?>"><?php echo $pesan; ?></div>
        <?php endif; ?>

        <!-- 1. FORM TAMBAH BARANG -->
        <div class="main-card">
            <div class="section-title">📦 Form Tambah Barang / Stok Baru (Kulakan)</div>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nama_barang">Nama Barang:</label>
                        <input type="text" id="nama_barang" name="nama_barang" placeholder="Contoh: Kertas A4" required>
                    </div>
                    <div class="form-group">
                        <label for="harga_beli">Harga Modal / Beli (Rp):</label>
                        <input type="number" id="harga_beli" name="harga_beli" placeholder="Contoh: 45000" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="harga_jual">Harga Jual Satuan (Rp):</label>
                        <input type="number" id="harga_jual" name="harga_jual" placeholder="Contoh: 55000" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="stok">Jumlah Stok Masuk:</label>
                        <input type="number" id="stok" name="stok" placeholder="Contoh: 50" min="1" required>
                    </div>
                </div>
                <button type="submit" name="simpan_barang" class="btn btn-tambah">➕ Tambah Stok Barang</button>
            </form>
        </div>

        <!-- 2. FORM TRANSAKSI PENJUALAN -->
        <div class="main-card">
            <div class="section-title">🛒 Form Input Transaksi Penjualan Barang</div>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="tanggal">Tanggal Transaksi:</label>
                        <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="jenis_pembeli">Kategori Pembeli:</label>
                        <select id="jenis_pembeli" name="jenis_pembeli" onchange="togglePelanggan(this.value)" required>
                            <option value="umum">Masyarakat Umum (Tidak Ada SHU)</option>
                            <option value="anggota">Anggota Koperasi (Dapat SHU / JUA)</option>
                        </select>
                    </div>
                    <div class="form-group" id="box_umum">
                        <label for="nama_umum">Nama Pembeli Umum:</label>
                        <input type="text" id="nama_umum" name="nama_umum" placeholder="Masukkan Nama Pembeli" required>
                    </div>
                    <div class="form-group" id="box_anggota" style="display: none;">
                        <label for="id_anggota">Pilih Anggota Koperasi:</label>
                        <select id="id_anggota" name="id_anggota">
                            <option value="">-- Pilih Anggota --</option>
                            <?php 
                            $rows_anggota = $q_list_anggota ? $q_list_anggota->fetchAll(PDO::FETCH_ASSOC) : [];
                            foreach ($rows_anggota as $ang) {
                                $id_a = $ang['id'] ?? $ang['id_anggota'];
                                $no_ang = $ang['no_anggota'] ?? $ang['nik'] ?? $id_a;
                                echo "<option value='$id_a'>" . htmlspecialchars($ang['nama']) . " ($no_ang)</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_barang">Pilih Barang:</label>
                        <select id="id_barang" name="id_barang" required>
                            <option value="">-- Pilih Barang --</option>
                            <?php 
                            $rows_barang = $q_list_barang ? $q_list_barang->fetchAll(PDO::FETCH_ASSOC) : [];
                            foreach ($rows_barang as $brg) {
                                $id_b = $brg['id_barang'];
                                $hrg_j = $brg['harga_jual'];
                                $stk = $brg['stok'];
                                echo "<option value='$id_b'>" . htmlspecialchars($brg['nama_barang']) . " - Rp " . number_format($hrg_j, 0, ',', '.') . " (Stok: $stk)</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="jumlah">Jumlah Qty:</label>
                        <input type="number" id="jumlah" name="jumlah" min="1" value="1" required>
                    </div>
                </div>
                <button type="submit" name="simpan_penjualan" class="btn btn-simpan">💾 Simpan Transaksi Penjualan</button>
            </form>
        </div>

        <!-- 3. TABEL DAFTAR STOK BARANG -->
        <div class="main-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div class="section-title" style="margin-bottom: 0;">📋 Daftar Stok Barang & Harga Toko</div>
                <a href="cetak_laporan_stok.php" target="_blank" class="btn" style="background: #17a2b8; color: white; padding: 8px 14px; font-size: 13px;">🖨️ Cetak Laporan Stok</a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Nama Barang</th>
                            <th style="text-align: right;">Harga Modal</th>
                            <th style="text-align: right;">Harga Jual</th>
                            <th style="text-align: right;">Margin Keuntungan</th>
                            <th style="text-align: center;">Sisa Stok</th>
                            <th style="text-align: center; width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q_stok = $koneksi->query("SELECT * FROM barang ORDER BY nama_barang ASC");
                        $rows_stok = $q_stok ? $q_stok->fetchAll(PDO::FETCH_ASSOC) : [];
                        $no_s = 1;
                        if (count($rows_stok) > 0) {
                            foreach ($rows_stok as $st) {
                                $margin = $st['harga_jual'] - $st['harga_beli'];
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no_s++; ?></td>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($st['nama_barang']); ?></td>
                                    <td style="text-align: right; color: #64748b;">Rp <?php echo number_format($st['harga_beli'], 0, ',', '.'); ?></td>
                                    <td style="text-align: right; font-weight: bold; color: #0f766e;">Rp <?php echo number_format($st['harga_jual'], 0, ',', '.'); ?></td>
                                    <td style="text-align: right; color: #16a34a; font-weight: bold;">+Rp <?php echo number_format($margin, 0, ',', '.'); ?></td>
                                    <td style="text-align: center; font-weight: bold; color: <?php echo ($st['stok'] > 5) ? '#15803d' : '#b91c1c'; ?>;"><?php echo $st['stok']; ?></td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <button type="button" class="btn btn-edit" onclick="openEditModal('<?php echo $st['id_barang']; ?>', '<?php echo addslashes($st['nama_barang']); ?>', '<?php echo $st['harga_beli']; ?>', '<?php echo $st['harga_jual']; ?>', '<?php echo $st['stok']; ?>')" style="margin-right: 3px;">✏️ Edit</button>
                                        <?php if ($is_admin): ?>
                                        <a href="konfirmasi_hapus_barang.php?id=<?php echo $st['id_barang']; ?>&nama=<?php echo urlencode($st['nama_barang']); ?>" class="btn btn-hapus">🗑️ Hapus</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center; padding: 20px;'>Belum ada data barang.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. TABEL RIWAYAT PENJUALAN & LAPORAN PIUTANG -->
        <div class="main-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div class="section-title" style="margin-bottom: 0;">📜 Riwayat Transaksi Penjualan & Laporan Toko</div>
                
                <!-- Filter Status Pembayaran -->
                <div style="display: flex; gap: 6px;">
                    <?php $filter_status = $_GET['filter_status'] ?? 'semua'; ?>
                    <a href="toko.php?filter_status=semua" class="btn <?php echo ($filter_status === 'semua') ? '' : 'btn-batal'; ?>" style="font-size: 11px; padding: 6px 10px; background: <?php echo ($filter_status === 'semua') ? '#044b3b' : '#6c757d'; ?>; color: white;">Semua</a>
                    <a href="toko.php?filter_status=lunas" class="btn" style="font-size: 11px; padding: 6px 10px; background: <?php echo ($filter_status === 'lunas') ? '#28a745' : '#6c757d'; ?>; color: white;">Lunas</a>
                    <a href="toko.php?filter_status=sebagian" class="btn" style="font-size: 11px; padding: 6px 10px; background: <?php echo ($filter_status === 'sebagian') ? '#ffc107' : '#6c757d'; ?>; color: <?php echo ($filter_status === 'sebagian') ? '#333' : 'white'; ?>;">Bayar Sebagian</a>
                    <a href="toko.php?filter_status=hutang" class="btn" style="font-size: 11px; padding: 6px 10px; background: <?php echo ($filter_status === 'hutang') ? '#dc3545' : '#6c757d'; ?>; color: white;">Belum Bayar</a>
                </div>
            </div>

            <?php
            // Hitung Statistik Ringkasan Laporan Penjualan & Piutang
            $q_stat_terjual = $koneksi->query("SELECT COUNT(*) AS total_trx, SUM(jumlah) AS total_qty, SUM(total_harga) AS total_omzet FROM penjualan")->fetch(PDO::FETCH_ASSOC);
            
            // Pendapatan lunas ditambah pembayaran sebagian yang sudah masuk
            $q_omset_lunas = $koneksi->query("SELECT SUM(total_harga) AS jml FROM penjualan WHERE status_bayar = 'LUNAS'")->fetch(PDO::FETCH_ASSOC)['jml'] ?? 0;
            $q_omset_sebagian = $koneksi->query("SELECT SUM(jumlah_bayar) AS jml FROM penjualan WHERE status_bayar = 'BAYAR SEBAGIAN'")->fetch(PDO::FETCH_ASSOC)['jml'] ?? 0;
            $total_pendapatan_masuk = $q_omset_lunas + $q_omset_sebagian;
            
            // Hitung sisa piutang untuk bayar sebagian & hutang
            $q_piutang_sebagian = $koneksi->query("SELECT SUM(total_harga - jumlah_bayar) AS jml FROM penjualan WHERE status_bayar = 'BAYAR SEBAGIAN'")->fetch(PDO::FETCH_ASSOC)['jml'] ?? 0;
            $q_piutang_hutang = $koneksi->query("SELECT SUM(total_harga) AS jml FROM penjualan WHERE status_bayar = 'BELUM BAYAR / TAGIHAN'")->fetch(PDO::FETCH_ASSOC)['jml'] ?? 0;
            
            $total_piutang_toko = $q_piutang_sebagian + $q_piutang_hutang;
            ?>

            <!-- Widget Ringkasan / Laporan Cepat -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div style="background: #e0f2f1; border-left: 4px solid #00796b; padding: 12px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #004d40; font-weight: bold;">📦 Total Barang Terjual</div>
                    <div style="font-size: 18px; font-weight: bold; color: #004d40; margin-top: 4px;"><?php echo number_format($q_stat_terjual['total_qty'] ?? 0, 0, ',', '.'); ?> Pcs <span style="font-size: 12px; font-weight: normal;">(<?php echo $q_stat_terjual['total_trx'] ?? 0; ?> Transaksi)</span></div>
                </div>
                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #155724; font-weight: bold;">✅ Pendapatan / Omset Diterima</div>
                    <div style="font-size: 16px; font-weight: bold; color: #155724; margin-top: 4px;">Rp <?php echo number_format($total_pendapatan_masuk, 0, ',', '.'); ?></div>
                </div>
                <div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #721c24; font-weight: bold;">⚠️ Total Piutang Toko (Belum Lunas)</div>
                    <div style="font-size: 16px; font-weight: bold; color: #721c24; margin-top: 4px;">Rp <?php echo number_format($total_piutang_toko, 0, ',', '.'); ?></div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Tanggal</th>
                            <th>Nama Barang</th>
                            <th>Kategori Pembeli</th>
                            <th>Nama Pelanggan / Anggota</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Total Harga</th>
                            <th style="text-align: center;">Status Bayar</th>
                            <th style="text-align: center; width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query dengan filter status bayar
                        $sql_riwayat = "
                            SELECT p.*, b.nama_barang, a.nama AS nama_anggota 
                            FROM penjualan p
                            LEFT JOIN barang b ON p.id_barang = b.id_barang
                            LEFT JOIN anggota a ON p.id_anggota = a.id
                        ";
                        
                        if ($filter_status === 'lunas') {
                            $sql_riwayat .= " WHERE p.status_bayar = 'LUNAS'";
                        } elseif ($filter_status === 'sebagian') {
                            $sql_riwayat .= " WHERE p.status_bayar = 'BAYAR SEBAGIAN'";
                        } elseif ($filter_status === 'hutang') {
                            $sql_riwayat .= " WHERE p.status_bayar = 'BELUM BAYAR / TAGIHAN'";
                        }
                        
                        $sql_riwayat .= " ORDER BY p.tanggal_transaksi DESC, p.id_penjualan DESC LIMIT 25";
                        
                        $q_riwayat = $koneksi->query($sql_riwayat);
                        $rows_riwayat = $q_riwayat ? $q_riwayat->fetchAll(PDO::FETCH_ASSOC) : [];
                        $no = 1;

                        if (count($rows_riwayat) > 0) {
                            foreach ($rows_riwayat as $r) {
                                $is_anggota = !empty($r['id_anggota']) && !empty($r['nama_anggota']);
                                
                                if ($is_anggota) {
                                    $nama_tampil = $r['nama_anggota'];
                                } else {
                                    $nama_tampil = !empty($r['nama_umum']) ? $r['nama_umum'] : 'Masyarakat Umum';
                                }

                                $status_b = $r['status_bayar'] ?? 'LUNAS';
                                $badge_bg = '#28a745';
                                if ($status_b === 'BAYAR SEBAGIAN') $badge_bg = '#ffc107';
                                if ($status_b === 'BELUM BAYAR / TAGIHAN') $badge_bg = '#dc3545';
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no++; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($r['tanggal_transaksi'])); ?></td>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($r['nama_barang'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($is_anggota): ?>
                                            <span class="badge-anggota">ANGGOTA (SHU)</span>
                                        <?php else: ?>
                                            <span class="badge-umum">UMUM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($nama_tampil); ?></td>
                                    <td style="text-align: center;"><?php echo $r['jumlah']; ?></td>
                                    <td style="text-align: right; font-weight: bold;">Rp <?php echo number_format($r['total_harga'], 0, ',', '.'); ?></td>
                                    <td style="text-align: center;">
                                        <span style="background-color: <?php echo $badge_bg; ?>; color: <?php echo ($status_b === 'BAYAR SEBAGIAN') ? '#333' : '#fff'; ?>; padding: 3px 7px; border-radius: 4px; font-size: 10px; font-weight: bold;">
                                            <?php echo $status_b; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="cetak_nota_toko.php?id=<?php echo $r['id_penjualan']; ?>" target="_blank" class="btn" style="background: #17a2b8; color: white; padding: 5px 8px; font-size: 11px; margin-right: 3px;">🖨️ Nota</a>
                                        <?php if ($is_admin): ?>
                                        <a href="konfirmasi_hapus_penjualan.php?id=<?php echo $r['id_penjualan']; ?>" class="btn btn-hapus" style="padding: 5px 8px; font-size: 11px;">🗑️ Hapus</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align:center; padding: 20px;'>Belum ada data transaksi untuk filter ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL EDIT -->
        <div id="modalEdit" class="modal">
            <div class="modal-content">
                <div class="modal-header">✏️ Edit Data Barang</div>
                <form method="POST" action="">
                    <input type="hidden" id="edit_id_barang" name="id_barang">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_nama_barang">Nama Barang:</label>
                        <input type="text" id="edit_nama_barang" name="nama_barang" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_harga_beli">Harga Modal / Beli (Rp):</label>
                        <input type="number" id="edit_harga_beli" name="harga_beli" min="1" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="edit_harga_jual">Harga Jual Satuan (Rp):</label>
                        <input type="number" id="edit_harga_jual" name="harga_jual" min="1" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="edit_stok">Sisa Stok:</label>
                        <input type="number" id="edit_stok" name="stok" min="0" required>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn btn-batal" onclick="closeEditModal()">Batal</button>
                        <button type="submit" name="edit_barang" class="btn btn-simpan">Simpan Perubahan</button>
                    </div>
                </form>
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

    function togglePelanggan(val) {
        const boxUmum = document.getElementById('box_umum');
        const inputUmum = document.getElementById('nama_umum');
        const boxAnggota = document.getElementById('box_anggota');
        const selectAnggota = document.getElementById('id_anggota');
        
        if (val === 'anggota') {
            boxUmum.style.display = 'none';
            inputUmum.removeAttribute('required');
            inputUmum.value = '';

            boxAnggota.style.display = 'flex';
            selectAnggota.setAttribute('required', 'required');
        } else {
            boxAnggota.style.display = 'none';
            selectAnggota.removeAttribute('required');
            selectAnggota.value = '';

            boxUmum.style.display = 'flex';
            inputUmum.setAttribute('required', 'required');
        }
    }

    function openEditModal(id, nama, hargaBeli, hargaJual, stok) {
        document.getElementById('edit_id_barang').value = id;
        document.getElementById('edit_nama_barang').value = nama;
        document.getElementById('edit_harga_beli').value = hargaBeli;
        document.getElementById('edit_harga_jual').value = hargaJual;
        document.getElementById('edit_stok').value = stok;
        document.getElementById('modalEdit').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('modalEdit').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('modalEdit');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>