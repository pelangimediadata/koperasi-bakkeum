<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

$message = '';
$message_type = '';

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if ($is_admin && isset($_GET['db_action'])) {
    $action = $_GET['db_action'];

    // 1. FITUR BACKUP DATABASE (SQLite cukup export file sqlite.db jika diinginkan, atau format sql custom)
    if ($action === 'backup') {
        $db_file = __DIR__ . '/api/database.db';
        if (file_exists($db_file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=Backup_Database_Koperasi_' . date('Y-m-d_H-i-s') . '.db');
            readfile($db_file);
            exit();
        } else {
            $message = "File database tidak ditemukan!";
            $message_type = "danger";
        }
    }

// 2. FITUR RESTORE DATABASE
    if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['backup_file']['tmp_name'];
            $targetPath = __DIR__ . '/api/database.db';
            
            // Validasi apakah file yang diupload adalah file SQLite yang valid sebelum menimpa
            $is_valid = false;
            try {
                $test_pdo = new PDO("sqlite:" . $fileTmpPath);
                $test_res = $test_pdo->query("SELECT name FROM sqlite_master WHERE type='table' LIMIT 1");
                if ($test_res) {
                    $is_valid = true;
                }
            } catch (Exception $e) {
                $is_valid = false;
            }

            if ($is_valid) {
                if (move_uploaded_file($fileTmpPath, $targetPath)) {
                    $message = "Database berhasil di-restore!";
                    $message_type = "success";
                } else {
                    $message = "Gagal memindahkan file restore.";
                    $message_type = "danger";
                }
            } else {
                $message = "File yang diunggah bukan database SQLite yang valid!";
                $message_type = "danger";
            }
        } else {
            $message = "Pilih file backup database (.db) yang valid!";
            $message_type = "danger";
        }
    }

    // 3. FITUR RESET DATABASE
    if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirm = $_POST['confirm_reset'] ?? '';
        if ($confirm === 'RESET') {
            $tables_query = $koneksi->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            while ($tbl = $tables_query->fetch()) {
                $tname = $tbl['name'];
                if ($tname === 'users' || $tname === 'admin') {
                    $koneksi->exec("DELETE FROM `$tname` WHERE role != 'admin'");
                } else {
                    $koneksi->exec("DELETE FROM `$tname`");
                }
            }
            $message = "Database berhasil di-reset!";
            $message_type = "success";
        } else {
            $message = "Konfirmasi reset gagal! Ketik kata 'RESET' untuk melanjutkan.";
            $message_type = "danger";
        }
    }

    // 4. FITUR RESET TRANSAKSI
    if ($action === 'reset_transaksi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirm_trx = $_POST['confirm_reset_trx'] ?? '';
        if ($confirm_trx === 'TRANSAKSI') {
            $koneksi->exec("DELETE FROM penjualan");
            $koneksi->exec("DELETE FROM belanja_toko");
            $koneksi->exec("DELETE FROM kas");
            $koneksi->exec("DELETE FROM simpanan");
            $koneksi->exec("DELETE FROM pinjaman");
            $koneksi->exec("DELETE FROM pembayaran");
            $koneksi->exec("DELETE FROM transaksi_toko");
            $koneksi->exec("DELETE FROM shu_pembayaran");

            $message = "Semua data transaksi berhasil di-reset!";
            $message_type = "success";
        } else {
            $message = "Konfirmasi reset transaksi gagal! Ketik kata 'TRANSAKSI' untuk melanjutkan.";
            $message_type = "danger";
        }
    }
}

// DATA RINGKASAN DASHBOARD DENGAN PENGAMANAN ERROR DATABASE
$tot_anggota = 0;
$tot_berjalan = 0;
$tot_lunas = 0;
$tot_macet = 0;
$tot_trx_hari = 0;
$nom_hari = 0;
$tot_trx_bulan = 0;
$nom_bulan = 0;
$tot_simpanan = 0;
$tot_pokok = 0;
$pendapatan_bunga_bulan = 0;
$pendapatan_bunga_total = 0;

try {
    $tot_anggota = $koneksi->query("SELECT COUNT(id) AS total FROM anggota")->fetch()['total'] ?? 0;
    $tot_berjalan = $koneksi->query("SELECT COUNT(no_pinjaman) AS total FROM pinjaman WHERE status = 'Berjalan'")->fetch()['total'] ?? 0;
    $tot_lunas = $koneksi->query("SELECT COUNT(no_pinjaman) AS total FROM pinjaman WHERE status = 'Lunas'")->fetch()['total'] ?? 0;
    $tot_macet = $koneksi->query("SELECT COUNT(no_pinjaman) AS total FROM pinjaman WHERE status = 'Macet'")->fetch()['total'] ?? 0;

    $hari_ini  = date('Y-m-d');
    $bulan_ini = date('Y-m');

    $stmt_trx_h = $koneksi->prepare("SELECT COUNT(id_bayar) AS total FROM pembayaran WHERE DATE(tanggal) = ?");
    $stmt_trx_h->execute([$hari_ini]);
    $tot_trx_hari = $stmt_trx_h->fetch()['total'] ?? 0;

    $stmt_nom_h = $koneksi->prepare("SELECT IFNULL(SUM(jumlah_bayar), 0) AS total FROM pembayaran WHERE DATE(tanggal) = ?");
    $stmt_nom_h->execute([$hari_ini]);
    $nom_hari = $stmt_nom_h->fetch()['total'] ?? 0;

    $stmt_trx_b = $koneksi->prepare("SELECT COUNT(id_bayar) AS total FROM pembayaran WHERE strftime('%Y-%m', tanggal) = ?");
    $stmt_trx_b->execute([$bulan_ini]);
    $tot_trx_bulan = $stmt_trx_b->fetch()['total'] ?? 0;

    $stmt_nom_b = $koneksi->prepare("SELECT IFNULL(SUM(jumlah_bayar), 0) AS total FROM pembayaran WHERE strftime('%Y-%m', tanggal) = ?");
    $stmt_nom_b->execute([$bulan_ini]);
    $nom_bulan = $stmt_nom_b->fetch()['total'] ?? 0;

    $tot_simpanan = $koneksi->query("SELECT IFNULL(SUM(jumlah_simpanan), 0) AS total FROM simpanan")->fetch()['total'] ?? 0;
    $tot_pokok = $koneksi->query("SELECT IFNULL(SUM(jumlah_pinjaman), 0) AS total FROM pinjaman WHERE status = 'Berjalan'")->fetch()['total'] ?? 0;

    $stmt_bg_b = $koneksi->prepare("SELECT IFNULL(SUM(CASE WHEN bayar_bunga > 0 THEN bayar_bunga WHEN jenis_bayar = 'Bayar Bunga' THEN jumlah_bayar ELSE 0 END), 0) AS total FROM pembayaran WHERE strftime('%Y-%m', tanggal) = ?");
    $stmt_bg_b->execute([$bulan_ini]);
    $pendapatan_bunga_bulan = $stmt_bg_b->fetch()['total'] ?? 0;

    $pendapatan_bunga_total = $koneksi->query("SELECT IFNULL(SUM(CASE WHEN bayar_bunga > 0 THEN bayar_bunga WHEN jenis_bayar = 'Bayar Bunga' THEN jumlah_bayar ELSE 0 END), 0) AS total FROM pembayaran")->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Jika struktur database rusak/bukan database valid, abaikan error fatal agar menu restore tetap bisa diakses
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Koperasi Bakkeum</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

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

        /* HEADER / TOP BAR DINAMIS */
        .dashboard-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .dashboard-title-box h2 {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            margin: 0;
        }

        .dashboard-title-box p {
            color: #b2dfdb;
            font-size: 12px;
            margin: 3px 0 0 0;
        }

        .header-live-clock {
            background: rgba(0, 77, 64, 0.6);
            color: #e0f2f1;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .content { 
            background: rgba(255, 255, 255, 0.96); 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .welcome-banner {
            background: linear-gradient(135deg, #00796b, #004d40);
            color: white;
            padding: 22px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 20px rgba(0, 121, 107, 0.35);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: -30px;
            bottom: -30px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-banner h2 {
            margin: 0 0 6px 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .welcome-banner p {
            margin: 0;
            font-size: 13px;
            color: #b2dfdb;
        }

        .welcome-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .logout-btn {
            color: #ffcccc;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }
        .logout-btn:hover { color: #ffffff; }

        h3 { 
            color: #004d40; 
            border-left: 4px solid #00796b; 
            padding-left: 12px; 
            margin-top: 35px; 
            margin-bottom: 18px; 
            font-size: 16px;
            letter-spacing: 0.5px;
        }
        h3:first-of-type { margin-top: 10px; }

        .grid-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 20px; 
            margin-bottom: 10px; 
        }

        .card { 
            background: #fff; 
            padding: 22px; 
            border-radius: 12px; 
            border: 1px solid #e0e0e0; 
            box-shadow: 0 3px 10px rgba(0,0,0,0.03); 
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .card h4 { 
            margin: 0 0 10px 0; 
            color: #666; 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .card .value { 
            font-size: 26px; 
            font-weight: 800; 
            color: #333; 
            margin: 0; 
        }

        .card .sub-value { 
            font-size: 12px; 
            color: #888; 
            margin-top: 6px; 
        }

        .card.income-card { 
            background: #f0fdf4; 
            border-color: #bbf7d0; 
        }

        .admin-db-menu { 
            background: #fff8e1; 
            border: 1px solid #ffe082; 
            padding: 18px; 
            border-radius: 12px; 
            margin-bottom: 25px; 
        }

        .admin-db-menu h4 { 
            margin: 0 0 12px 0; 
            color: #b71c1c; 
            font-size: 13px; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }

        .btn-db { 
            padding: 9px 16px; 
            border: none; 
            border-radius: 6px; 
            font-weight: bold; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            font-size: 13px; 
            margin-right: 8px; 
            margin-bottom: 5px;
            color: white; 
            transition: all 0.2s ease;
        }

        .btn-db:hover {
            transform: translateY(-1px);
            filter: brightness(0.95);
        }

        .btn-backup { background-color: #0288d1; }
        .btn-restore { background-color: #ed6c02; }
        .btn-reset { background-color: #d32f2f; }
        .btn-reset-trx { background-color: #c2185b; }

        .alert { 
            padding: 14px 18px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-weight: bold; 
            font-size: 14px;
        }

        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 25px; border-radius: 12px; width: 420px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .modal-header { font-size: 16px; font-weight: bold; color: #333; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .close-modal { background: #6c757d; color: white; padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; float: right; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <!-- BAGIAN ATAS DINAMIS & ATRAKTIF -->
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>🏛️ KOPERASI BAKKEUM</h2>
                <p>Sistem Informasi Manajemen Koperasi Bakkeum</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="content">
            <!-- WELCOME BANNER -->
            <div class="welcome-banner">
                <div>
                    <h2>Selamat Datang, <?php echo htmlspecialchars($nama_user); ?> 👋</h2>
                    <p>Berikut adalah ikhtisar ringkasan keuangan dan aktivitas sistem per hari ini.</p>
                </div>
                <div class="welcome-badge">
                    <span>Role: <?php echo strtoupper($_SESSION['role'] ?? 'Admin'); ?></span>                   
                </div>
            </div>

            <?php if (!empty($message)) { ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php } ?>

            <!-- FITUR KHUSUS ADMIN DATABASE & TRANSAKSI -->
            <?php if ($is_admin) { ?>
                <div class="admin-db-menu">
                    <h4>🛠️ Panel Pengelolaan Database (Khusus Admin)</h4>
					<button class="btn-db btn-backup" onclick="location.href='dashboard.php?db_action=backup'">💾 Backup Database</button>
					<button class="btn-db btn-restore" onclick="openModal('restoreModal')">🔄 Restore Database</button>
                    <button class="btn-db btn-reset" onclick="openModal('resetModal')">⚠️ Reset Database</button>
                    <button class="btn-db btn-reset-trx" onclick="openModal('resetTrxModal')">🗑️ Reset Transaksi</button>
                </div>
            <?php } ?>

            <!-- SEKSI 1: REKAP PENDAPATAN / HASIL USAHA -->
            <h3>📈 REKAP PENDAPATAN & HASIL USAHA (SHU)</h3>
            <div class="grid-container">
                <div class="card income-card">
                    <h4>Pendapatan Bunga Bulan Ini</h4>
                    <p class="value" style="color: #2e7d32;">Rp <?php echo number_format($pendapatan_bunga_bulan, 0, ',', '.'); ?></p>
                    <p class="sub-value">Keuntungan bersih jasa bulan <?php echo date('F Y'); ?></p>
                </div>
                <div class="card income-card">
                    <h4>Total Keuntungan Bunga (Akumulasi)</h4>
                    <p class="value" style="color: #2e7d32;">Rp <?php echo number_format($pendapatan_bunga_total, 0, ',', '.'); ?></p>
                    <p class="sub-value">Total pendapatan jasa pinjaman</p>
                </div>
            </div>

            <!-- SEKSI 2: STATUS PINJAMAN & ANGGOTA -->
            <h3>📊 STATUS PINJAMAN & ANGGOTA</h3>
            <div class="grid-container">
                <div class="card">
                    <h4>Total Anggota</h4>
                    <p class="value" style="color: #00796b;"><?php echo $tot_anggota; ?></p>
                </div>
                <div class="card">
                    <h4>Berjalan</h4>
                    <p class="value" style="color: #f57c00;"><?php echo $tot_berjalan; ?></p>
                </div>
                <div class="card">
                    <h4>Lunas</h4>
                    <p class="value" style="color: #2e7d32;"><?php echo $tot_lunas; ?></p>
                </div>
                <div class="card">
                    <h4>⚠️ Macet</h4>
                    <p class="value" style="color: #d32f2f;"><?php echo $tot_macet; ?></p>
                </div>
            </div>

            <!-- SEKSI 3: ANGSURAN MASUK -->
            <h3>💵 ANGSURAN MASUK (PIUTANG)</h3>
            <div class="grid-container">
                <div class="card">
                    <h4>Hari Ini</h4>
                    <p class="value" style="color: #00796b;"><?php echo $tot_trx_hari; ?> <span style="font-size:15px; font-weight:normal; color:#666;">trx</span></p>
                    <p class="sub-value">Rp <?php echo number_format($nom_hari, 0, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Bulan Ini</h4>
                    <p class="value" style="color: #00796b;"><?php echo $tot_trx_bulan; ?> <span style="font-size:15px; font-weight:normal; color:#666;">trx</span></p>
                    <p class="sub-value">Rp <?php echo number_format($nom_bulan, 0, ',', '.'); ?></p>
                </div>
            </div>

            <!-- SEKSI 4: RINGKASAN KAS KOPERASI -->
            <h3>💰 RINGKASAN KAS KOPERASI</h3>
            <div class="grid-container">
                <div class="card">
                    <h4>Total Dana Simpanan</h4>
                    <p class="value" style="color: #2e7d32;">Rp <?php echo number_format($tot_simpanan, 0, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Total Pokok Berjalan</h4>
                    <p class="value" style="color: #f57c00;">Rp <?php echo number_format($tot_pokok, 0, ',', '.'); ?></p>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- MODAL POPUP RESTORE DATABASE -->
<div id="restoreModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">🔄 Restore Database Koperasi</div>
        <form action="dashboard.php?db_action=restore" method="POST" enctype="multipart/form-data">
            <p style="font-size: 13px; color: #666;">Pilih file cadangan database (format <strong>.db</strong>):</p>
            <input type="file" name="backup_file" accept=".db" required style="margin-bottom: 15px; width: 100%;">
            <button type="submit" class="btn-db btn-restore">Mulai Restore</button>
            <button type="button" onclick="closeModal('restoreModal')" class="close-modal">Batal</button>
        </form>
    </div>
</div>>

<!-- MODAL POPUP RESET DATABASE -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="color: #d32f2f;">⚠️ Konfirmasi Reset Database</div>
        <form action="dashboard.php?db_action=reset" method="POST">
            <p style="font-size: 13px; color: #d32f2f; font-weight: bold;">PERINGATAN: Tindakan ini akan MENGHAPUS SELURUH DATA transaksi, anggota, simpanan, dan pinjaman!</p>
            <p style="font-size: 13px; color: #666;">Ketik kata <strong>RESET</strong> untuk mengonfirmasi:</p>
            <input type="text" name="confirm_reset" required autocomplete="off" placeholder="Ketik RESET disini" style="padding: 8px; width: 93%; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px;">
            <br>
            <button type="submit" class="btn-db btn-reset">Kosongkan Database</button>
            <button type="button" onclick="closeModal('resetModal')" class="close-modal">Batal</button>
        </form>
    </div>
</div>

<!-- MODAL POPUP RESET TRANSAKSI -->
<div id="resetTrxModal" class="modal">
    <div class="modal-content">
        <div class="modal-header" style="color: #c2185b;">🗑️ Konfirmasi Reset Transaksi</div>
        <form action="dashboard.php?db_action=reset_transaksi" method="POST">
            <p style="font-size: 13px; color: #c2185b; font-weight: bold;">PERINGATAN: Tindakan ini akan MENGHAPUS SELURUH DATA TRANSAKSI (simpanan, pinjaman, pembayaran, belanja modal barang, kas, toko, dan SHU) tanpa menghapus data anggota!</p>
            <p style="font-size: 13px; color: #666;">Ketik kata <strong>TRANSAKSI</strong> untuk mengonfirmasi:</p>
            <input type="text" name="confirm_reset_trx" required autocomplete="off" placeholder="Ketik TRANSAKSI disini" style="padding: 8px; width: 93%; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px;">
            <br>
            <button type="submit" class="btn-db btn-reset-trx">Reset Semua Transaksi</button>
            <button type="button" onclick="closeModal('resetTrxModal')" class="close-modal">Batal</button>
        </form>
    </div>
</div>

<script>
    // Script Jam Realtime
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('timeText').textContent = `${hours}:${minutes}:${seconds}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function openModal(id) {
        document.getElementById(id).style.display = 'block';
    }
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }
    window.onclick = function(e) {
        if (e.target.className === 'modal') {
            e.target.style.display = 'none';
        }
    }
</script>

</body>
</html>