<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

// Cek status role/admin
$is_admin = isset($_SESSION['login_admin']);

// Proses Hapus Data Pembayaran menggunakan kolom id_bayar yang sesuai dengan database
if (isset($_GET['hapus_id']) && $is_admin) {
    $id_hapus = $_GET['hapus_id'];
    $stmt = $koneksi->prepare("DELETE FROM pembayaran WHERE id_bayar = ?");
    $stmt->execute([$id_hapus]);

    header("Location: bayar.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - KOPERASI BAKKEUM</title>
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

        .card-box { 
            background: rgba(255, 255, 255, 0.96); 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #00796b;
            padding-bottom: 15px;
        }

        .page-title {
            font-size: 18px;
            font-weight: bold;
            color: #004d40;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }

        .btn-primary { background-color: #00796b; color: white; box-shadow: 0 4px 10px rgba(0, 121, 107, 0.3); }
        .btn-info { background-color: #0284c7; color: white; }
        .btn-warning { background-color: #ed6c02; color: white; }
        .btn-danger { background-color: #d32f2f; color: white; }
        .btn-secondary { background-color: #64748b; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background-color: #00796b;
            color: #ffffff;
            padding: 12px 15px;
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #333333;
            vertical-align: middle;
        }

        tbody tr:hover {
            background-color: #f0fdf4;
        }

        /* MODAL KONFIRMASI */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; padding: 25px; border-radius: 12px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
        .modal-header { font-size: 18px; font-weight: bold; color: #d32f2f; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-body { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; }
        .modal-footer { display: flex; justify-content: center; gap: 10px; }
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
                <h2>KOPERASI BAKKEUM</h2>
                <p>Sistem Informasi Manajemen Pembayaran Anggota</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="card-box">
            <div class="page-header">
                <div class="page-title">
                    📋 Histori Pembayaran Angsuran
                </div>
                <div>
                    <a href="tambah_bayar.php" class="btn btn-primary">+ Input Pembayaran Baru</a>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center;">ID Bayar</th>
                            <th style="text-align: center;">Tanggal</th>
                            <th>Nama Anggota</th>
                            <th>Jenis Bayar</th>
                            <th style="text-align: right;">Jumlah Bayar</th>
                            <th style="text-align: center; width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT pembayaran.*, anggota.nama 
                                FROM pembayaran 
                                JOIN pinjaman ON pembayaran.no_pinjaman = pinjaman.no_pinjaman 
                                JOIN anggota ON pinjaman.id_anggota = anggota.id 
                                ORDER BY pembayaran.tanggal DESC";
                        $query = $koneksi->query($sql);
                        
                        $rows = $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                $id_bayar = $row['id_bayar'] ?? 1;
                                $total_bayar = ($row['jumlah_bayar'] > 0) ? $row['jumlah_bayar'] : ($row['bayar_bunga'] ?? 0);
                                $tgl = date('d-m-Y', strtotime($row['tanggal']));
                                $nama_anggota = $row['nama'];
                                ?>
                                <tr>
                                    <td style="text-align: center; color: #00796b; font-weight: bold;">#TRX-<?php echo htmlspecialchars($id_bayar); ?></td>
                                    <td style="text-align: center;"><?php echo htmlspecialchars($tgl); ?></td>
                                    <td style="font-weight: 600; color: #004d40;"><?php echo htmlspecialchars($nama_anggota); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['jenis_bayar']); ?></td>
                                    <td style="text-align: right; font-weight: bold; color: #00796b;">
                                        Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?>
                                    </td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="cetak_bukti.php?id=<?php echo urlencode($id_bayar); ?>" target="_blank" class="btn btn-info btn-sm" style="margin-right: 4px;">🖨️ Cetak</a>
                                        <?php if ($is_admin): ?>
                                            <a href="edit_bayar.php?id=<?php echo urlencode($id_bayar); ?>" class="btn btn-warning btn-sm" style="margin-right: 4px;">✏️ Ubah</a>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteModal('<?php echo urlencode($id_bayar); ?>', '<?php echo addslashes($nama_anggota); ?>')">🗑️ Hapus</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: #888;'>Belum ada histori pembayaran.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- MODAL KONFIRMASI HAPUS -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">⚠️ Konfirmasi Hapus Pembayaran</div>
        <div class="modal-body" id="deleteModalText">
            Apakah Anda yakin ingin menghapus data pembayaran ini secara permanen?
        </div>
        <div class="modal-footer">
            <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Ya, Hapus</a>
            <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Batal</button>
        </div>
    </div>
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

    function openDeleteModal(idBayar, nama) {
        document.getElementById('deleteModalText').innerHTML = `Apakah Anda yakin ingin menghapus data pembayaran untuk anggota <strong>${nama}</strong> (ID: #TRX-${idBayar}) secara permanen?`;
        document.getElementById('confirmDeleteBtn').href = `bayar.php?hapus_id=${idBayar}`;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    window.onclick = function(e) {
        const modal = document.getElementById('deleteModal');
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>