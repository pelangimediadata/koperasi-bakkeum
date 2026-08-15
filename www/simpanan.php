<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$columns_info = [];
$stmt_cols = $koneksi->query("PRAGMA table_info(simpanan)");
if ($stmt_cols) {
    while ($col = $stmt_cols->fetch(PDO::FETCH_ASSOC)) {
        $columns_info[] = $col['name'];
    }
}

$pk_simpanan = 'id';
if (in_array('id_simpanan', $columns_info)) {
    $pk_simpanan = 'id_simpanan';
}

$kolom_fk = 'id_anggota';
if (in_array('anggota_id', $columns_info)) {
    $kolom_fk = 'anggota_id';
}

$kolom_tgl = "NULL";
if (in_array('tgl_simpan', $columns_info)) $kolom_tgl = "s.tgl_simpan";
elseif (in_array('tgl_transaksi', $columns_info)) $kolom_tgl = "s.tgl_transaksi";
elseif (in_array('tanggal', $columns_info)) $kolom_tgl = "s.tanggal";
elseif (in_array('created_at', $columns_info)) $kolom_tgl = "s.created_at";

$kolom_nominal = "0";
if (in_array('jumlah', $columns_info)) $kolom_nominal = "s.jumlah";
elseif (in_array('nominal', $columns_info)) $kolom_nominal = "s.nominal";
elseif (in_array('besar_simpanan', $columns_info)) $kolom_nominal = "s.besar_simpanan";

$query_simpanan = $koneksi->query("
    SELECT 
        s.*, 
        s.$pk_simpanan AS id_transaksi,
        $kolom_tgl AS tgl_transaksi,
        $kolom_nominal AS nominal_uang,
        a.nama AS nama_anggota
    FROM simpanan s
    LEFT JOIN anggota a ON s.$kolom_fk = a.id
    ORDER BY s.$pk_simpanan DESC
");
$nama_user = $_SESSION['nama'] ?? 'Admin';
$role_user = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Simpanan - KOPERASI BAKKEUM</title>
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

        .app-container { display: flex; min-height: 100vh; width: 100%; flex-direction: row; }
        .main-content { flex-grow: 1; padding: 25px 30px; overflow-y: auto; background: transparent; width: 100%; }

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
            gap: 15px;
            flex-wrap: wrap;
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
            white-space: nowrap;
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
            gap: 15px;
            flex-wrap: wrap;
        }

        .page-title-text { 
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
            display: inline-flex; 
            align-items: center;
            justify-content: center;
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
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        .table-responsive { 
            width: 100%; 
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch;
        }
        .custom-table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; font-size: 14px; white-space: nowrap; }
        .custom-table th { background-color: #00796b; color: #ffffff; padding: 12px 15px; font-weight: 600; }
        .custom-table td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333333; vertical-align: middle; }
        .custom-table tbody tr:hover { background-color: #f0fdf4; }

        .badge {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pokok { background-color: #e0f2f1; color: #00796b; border: 1px solid #b2dfdb; }
        .badge-sukarela { background-color: #e0f7fa; color: #00838f; border: 1px solid #b2ebf2; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; padding: 15px; }
        .modal-content { background-color: #fff; padding: 25px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
        .modal-header { font-size: 18px; font-weight: bold; color: #d32f2f; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-body { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; }
        .modal-footer { display: flex; justify-content: center; gap: 10px; }

        /* RESPONSIF UNTUK MOBILE / LAYAR KECIL */
        @media screen and (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .app-container {
                flex-direction: column;
            }
            .main-content {
                padding: 15px 12px;
            }
            .content {
                padding: 15px;
                border-radius: 12px;
            }
            .dashboard-header-flex {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }
            .header-live-clock {
                justify-content: center;
            }
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .page-header div a {
                display: block;
                text-align: center;
                width: 100%;
            }
            .modal-footer {
                flex-direction: column;
            }
            .modal-footer .btn {
                width: 100%;
            }
        }
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
                <p>Sistem Informasi Manajemen Simpanan Anggota</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="content">
            <div class="page-header">
                <div class="page-title-text">💰 Daftar Transaksi Simpanan</div>
                <div><a href="tambah_simpanan.php" class="btn btn-primary">➕ Tambah Simpanan</a></div>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 50px;">No</th>
                            <th>Tanggal</th>
                            <th>Nama Anggota</th>
                            <th>Jenis Simpanan</th>
                            <th>Jumlah (Rp)</th>
                            <th style="text-align: center; width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $rows = $query_simpanan ? $query_simpanan->fetchAll(PDO::FETCH_ASSOC) : [];
                        
                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                $tgl_raw = $row['tgl_transaksi'];
                                if (!empty($tgl_raw) && $tgl_raw != 'NULL' && is_numeric(strtotime($tgl_raw))) {
                                    $tgl_tampil = date('d-m-Y', strtotime($tgl_raw));
                                } else {
                                    $tgl_tampil = date('d-m-Y'); 
                                }

                                $nama_anggota = !empty($row['nama_anggota']) ? $row['nama_anggota'] : "Anggota ID #" . $row[$kolom_fk];
                                $jenis = isset($row['jenis_simpanan']) ? $row['jenis_simpanan'] : 'Simpanan';
                                $badge_class = (strpos(strtolower($jenis), 'sukarela') !== false) ? 'badge-sukarela' : 'badge-pokok';
                                $id_transaksi = $row['id_transaksi'];
                        ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $no++; ?></td>
                                <td><?php echo htmlspecialchars($tgl_tampil); ?></td>
                                <td><strong style="color: #00796b;"><?php echo htmlspecialchars($nama_anggota); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($jenis); ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; color: #004d40;">Rp <?php echo number_format($row['nominal_uang'], 0, ',', '.'); ?></td>
								<td style="text-align: center; white-space: nowrap;">
									<a href="edit_simpanan.php?id=<?php echo urlencode($id_transaksi); ?>" class="btn btn-warning btn-sm" style="margin-right: 4px;">✏️ Ubah</a>

									<!-- Tombol Hapus hanya tampil jika role adalah admin -->
									<?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteModal('<?php echo urlencode($id_transaksi); ?>', '<?php echo addslashes($nama_anggota); ?>')">🗑️ Hapus</button>
                                    <?php endif; ?>
								</td>
                            </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align:center; padding: 20px; color: #888;'>Belum ada data simpanan.</td></tr>";
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
            Apakah Anda yakin ingin menghapus data simpanan ini secara permanen?
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
        document.getElementById('deleteModalText').innerHTML = `Apakah Anda yakin ingin menghapus data simpanan untuk anggota <strong>${nama}</strong> secara permanen?`;
        document.getElementById('confirmDeleteBtn').href = `konfirmasi_hapus_simpanan.php?id=${id}`;
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