<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

// Proses Hapus via URL GET (Menggunakan kolom 'id' sesuai struktur tabel SQLite)
if (isset($_GET['hapus_id'])) {
    $id_hapus = intval($_GET['hapus_id']);
    $stmt = $koneksi->prepare("DELETE FROM anggota WHERE id = ?");
    $stmt->execute([$id_hapus]);
    header("Location: anggota.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
$role_user = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Anggota - KOPERASI BAKKEUM</title>
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
        .btn-warning { background-color: #ed6c02; color: white; }
        .btn-danger { background-color: #d32f2f; color: white; }
        .btn-secondary { background-color: #64748b; color: white; }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; font-size: 14px; }
        th { background-color: #00796b; color: #ffffff; padding: 12px 15px; font-weight: 600; }
        td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333333; vertical-align: middle; }
        tbody tr:hover { background-color: #f0fdf4; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; padding: 25px; border-radius: 12px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
        .modal-header { font-size: 18px; font-weight: bold; color: #d32f2f; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-body { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; }
        .modal-footer { display: flex; justify-content: center; gap: 10px; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <!-- HEADER / TOP BAR -->
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>KOPERASI BAKKEUM</h2>
                <p>Sistem Informasi Manajemen Pelanggan & Layanan Internet Desa</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="content">
            <div class="page-header">
                <div class="page-title">👥 Daftar Anggota / Pelanggan</div>
                <div><a href="tambah_anggota.php" class="btn btn-primary">+ Tambah Anggota Baru</a></div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 50px;">No</th>
                            <th>NIK</th>
                            <th>Nama Lengkap</th>
                            <th>Alamat</th>
                            <th>No. Telp / WA</th>
                            <th style="text-align: center; width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM anggota ORDER BY id DESC";
                        $query = $koneksi->query($sql);
                        $no = 1;
                        $rows = $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];

                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                $id_key = $row['id'] ?? '';
                                $nama_member = htmlspecialchars($row['nama'] ?? $row['nama_lengkap'] ?? 'Anggota Ini');
                                ?>
                                <tr>
                                    <td style="text-align: center;"><?php echo $no++; ?></td>
                                    <td style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($row['nik'] ?? '-'); ?></td>
                                    <td style="font-weight: 600; color: #00796b;"><?php echo $nama_member; ?></td>
                                    <td><?php echo htmlspecialchars($row['alamat'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_telp'] ?? $row['telepon'] ?? '-'); ?></td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="edit_anggota.php?id=<?php echo $id_key; ?>" class="btn btn-warning" style="margin-right: 4px;">✏️ Ubah</a>
                                        
                                        <!-- Tombol Hapus hanya tampil jika role adalah admin -->
                                        <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
                                            <button type="button" class="btn btn-danger" onclick="openDeleteModal('<?php echo $id_key; ?>', '<?php echo addslashes($nama_member); ?>')">🗑️ Hapus</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: #888;'>Belum ada data anggota.</td></tr>";
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
        <div class="modal-header">⚠️ Konfirmasi Hapus</div>
        <div class="modal-body" id="deleteModalText">
            Apakah Anda yakin ingin menghapus data anggota ini secara permanen?
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

    function openDeleteModal(id, nama) {
        document.getElementById('deleteModalText').innerHTML = `Apakah Anda yakin ingin menghapus data anggota <strong>${nama}</strong> secara permanen?`;
        document.getElementById('confirmDeleteBtn').href = `anggota.php?hapus_id=${id}`;
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