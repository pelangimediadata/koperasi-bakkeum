<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

// Proses Hapus Data Pinjaman langsung di dalam file
if (isset($_GET['hapus_no'])) {
    $no_pinj_hapus = $_GET['hapus_no'];
    $stmt = $koneksi->prepare("DELETE FROM pinjaman WHERE no_pinjaman = ?");
    $stmt->execute([$no_pinj_hapus]);
    
    // Opsional: Hapus juga data pembayaran terkait jika ada relasi
    $stmt_bayar = $koneksi->prepare("DELETE FROM pembayaran WHERE no_pinjaman = ?");
    $stmt_bayar->execute([$no_pinj_hapus]);

    header("Location: pinjaman.php");
    exit();
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
$role_user = $_SESSION['role'] ?? 'admin'; // Ambil role user yang sedang login
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Data Pinjaman - KOPERASI BAKKEUM</title>
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
            gap: 15px;
            flex-wrap: wrap;
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            text-align: left;
            font-size: 14px;
            white-space: nowrap;
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

        .badge {
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .badge-warning { background-color: #fff3e0; color: #ed6c02; border: 1px solid #ffe0b2; }
        .badge-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .text-danger { color: #d32f2f; font-weight: bold; }
        .text-muted { color: #888888; }

        /* MODAL KONFIRMASI */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; padding: 15px; }
        .modal-content { background-color: #fff; padding: 25px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
        .modal-header { font-size: 18px; font-weight: bold; color: #d32f2f; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-body { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.5; }
        .modal-footer { display: flex; justify-content: center; gap: 10px; }

        /* RESPONSIF KHUSUS MOBILE / LAYAR KECIL */
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
            .card-box {
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
                <p>Sistem Informasi Manajemen Pinjaman Anggota</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>

        <div class="card-box">
            <div class="page-header">
                <div class="page-title">
                    📄 Daftar Pinjaman
                </div>
                <div>
                    <a href="tambah_pinjaman.php" class="btn btn-primary">+ Tambah Pinjaman Baru</a>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: center;">No Pinjaman</th>
                            <th>Nama Anggota</th>
                            <th style="text-align: center;">Jangka Waktu</th>
                            <th style="text-align: right;">Jumlah Pinjaman</th>
                            <th style="text-align: right;">Sisa Pokok</th>
                            <th style="text-align: right;">Sisa Bunga</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: center; width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT pinjaman.*, anggota.nama,
                                COALESCE(SUM(CASE WHEN pembayaran.jenis_bayar IN ('Bayar Angsuran', 'Pelunasan') OR pembayaran.jenis_bayar IS NULL THEN pembayaran.jumlah_bayar ELSE 0 END), 0) AS total_pokok_dibayar,
                                COALESCE(SUM(
                                    CASE 
                                        WHEN pembayaran.bayar_bunga > 0 THEN pembayaran.bayar_bunga 
                                        WHEN pembayaran.jenis_bayar = 'Bunga Saja' THEN pembayaran.jumlah_bayar 
                                        ELSE 0 
                                    END
                                ), 0) AS total_bunga_dibayar,
                                COALESCE(SUM(
                                    CASE 
                                        WHEN pembayaran.jenis_bayar = 'Bayar Bunga dari sisa Pokok' THEN pembayaran.bayar_bunga 
                                        ELSE 0 
                                    END
                                ), 0) AS total_bunga_sisa_pokok_dibayar
                                FROM pinjaman
                                JOIN anggota ON pinjaman.id_anggota = anggota.id
                                LEFT JOIN pembayaran ON pinjaman.no_pinjaman = pembayaran.no_pinjaman
                                GROUP BY pinjaman.no_pinjaman
                                ORDER BY pinjaman.no_pinjaman DESC";

                        $query = $koneksi->query($sql);
                        $rows = $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];

                        if (count($rows) > 0) {
                            foreach ($rows as $row) {
                                $jumlah_pinjaman = (float) $row['jumlah_pinjaman'];
                                $bunga_persen = (float) ($row['bunga'] ?? 0);
                                $tenor = (int) ($row['tenor'] ?? $row['lama_angsuran'] ?? 1);

                                $sisa_pokok = $jumlah_pinjaman - (float) $row['total_pokok_dibayar'];
                                if ($sisa_pokok < 0) $sisa_pokok = 0;

                                $total_bunga_keseluruhan = ($jumlah_pinjaman * $bunga_persen / 100) * $tenor;
                                $total_bunga_dibayar = (float) $row['total_bunga_dibayar'];
                                $sisa_bunga = $total_bunga_keseluruhan - $total_bunga_dibayar;

                                if ($sisa_bunga <= 0 && $sisa_pokok > 0) {
                                    $sisa_bunga = ($sisa_pokok * $bunga_persen) / 100;
                                }

                                if ($sisa_bunga < 0) $sisa_bunga = 0;

                                $is_lunas = ($sisa_pokok <= 0 && $sisa_bunga <= 0) || strtolower($row['status']) === 'lunas';
                                $no_pinjaman = $row['no_pinjaman'];
                                $nama_anggota = $row['nama'];
                                ?>
                                <tr>
                                    <td style="text-align: center; font-weight: bold; color: #00796b;">#<?php echo htmlspecialchars($no_pinjaman); ?></td>
                                    <td style="font-weight: 600; color: #004d40;"><?php echo htmlspecialchars($nama_anggota); ?></td>
                                    <td style="text-align: center;"><?php echo $tenor; ?> Bulan</td>
                                    <td style="text-align: right;">Rp <?php echo number_format($jumlah_pinjaman, 0, ',', '.'); ?></td>
                                    
                                    <td style="text-align: right;" class="<?php echo ($sisa_pokok > 0) ? 'text-danger' : 'text-muted'; ?>">
                                        Rp <?php echo number_format($sisa_pokok, 0, ',', '.'); ?>
                                    </td>

                                    <td style="text-align: right;" class="<?php echo ($sisa_bunga > 0) ? 'text-danger' : 'text-muted'; ?>">
                                        Rp <?php echo number_format($sisa_bunga, 0, ',', '.'); ?>
                                    </td>

                                    <td style="text-align: center;">
                                        <?php if ($is_lunas): ?>
                                            <span class="badge badge-success">Lunas</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Berjalan</span>
                                        <?php endif; ?>
                                    </td>

                                    <td style="text-align: center; white-space: nowrap;">
                                        <a href="edit_pinjaman.php?no_pinjaman=<?php echo urlencode($no_pinjaman); ?>" class="btn btn-warning btn-sm" style="margin-right: 4px;">✏️ Ubah</a>
                                        
                                        <!-- Tombol Hapus hanya tampil jika role adalah admin -->
                                        <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteModal('<?php echo urlencode($no_pinjaman); ?>', '<?php echo addslashes($nama_anggota); ?>')">🗑️ Hapus</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align:center; padding: 20px; color: #888;'>Belum ada data pinjaman.</td></tr>";
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
        <div class="modal-header">⚠️ Konfirmasi Hapus Pinjaman</div>
        <div class="modal-body" id="deleteModalText">
            Apakah Anda yakin ingin menghapus data pinjaman ini secara permanen?
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

    function openDeleteModal(noPinjaman, nama) {
        document.getElementById('deleteModalText').innerHTML = `Apakah Anda yakin ingin menghapus data pinjaman untuk anggota <strong>${nama}</strong> (No: #${noPinjaman}) secara permanen?`;
        document.getElementById('confirmDeleteBtn').href = `pinjaman.php?hapus_no=${noPinjaman}`;
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