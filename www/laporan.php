<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";
$is_admin = isset($_SESSION['login_admin']);

// Cek apakah mode sedang dalam pratinjau cetak (Print Preview)
$is_print_preview = isset($_GET['print_preview']) && $_GET['print_preview'] == 'true';
$print_tgl_mulai  = isset($_GET['print_tgl_mulai']) ? $_GET['print_tgl_mulai'] : date('Y-01-01');
$print_tgl_selesai= isset($_GET['print_tgl_selesai']) ? $_GET['print_tgl_selesai'] : date('Y-m-d');

// Filter Utama Laporan
$tgl_awal         = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-01-01');
$tgl_akhir        = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-12-31');

if (isset($_GET['action_export'])) {
    $export_type = $_GET['action_export'];
    $exp_tgl_mulai = isset($_GET['exp_tgl_mulai']) && !empty($_GET['exp_tgl_mulai']) ? $_GET['exp_tgl_mulai'] : '1970-01-01';
    $exp_tgl_selesai = isset($_GET['exp_tgl_selesai']) && !empty($_GET['exp_tgl_selesai']) ? $_GET['exp_tgl_selesai'] : date('Y-m-d');

    if ($export_type === 'transaksi' || $export_type === 'semua') {
        if ($export_type === 'transaksi') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=Laporan_Transaksi_dan_Riwayat_" . date('Y-m-d') . ".xls");
            header("Pragma: no-cache");
            header("Expires: 0");

            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            echo '<head><meta charset="UTF-8"><style>';
            echo 'table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11pt; margin-bottom: 20px; }';
            echo 'th { background-color: #00796b; color: #ffffff; font-weight: bold; border: 1px solid #004d40; padding: 8px 12px; text-align: center; }';
            echo 'td { border: 1px solid #d1d5db; padding: 6px 10px; vertical-align: middle; }';
            echo '.text-center { text-align: center; }';
            echo '.text-right { text-align: right; }';
            echo '.title { font-size: 14pt; font-weight: bold; margin-bottom: 5px; color: #004d40; }';
            echo '.subtitle { font-size: 11pt; font-weight: bold; margin-top: 15px; margin-bottom: 5px; color: #1e293b; }';
            echo '</style></head><body>';
            
            echo '<div class="title">REKAPITULASI PINJAMAN & PIUTANG KOPERASI BAKKEUM</div>';
            echo '<div style="margin-bottom: 15px; font-size: 10pt; color: #555;">Rentang Tanggal Riwayat: ' . date('d-m-Y', strtotime($exp_tgl_mulai)) . ' s/d ' . date('d-m-Y', strtotime($exp_tgl_selesai)) . '</div>';
            
            echo '<div class="subtitle">1. Data Pinjaman & Piutang</div>';
            echo '<table><thead><tr><th>No Pinjaman</th><th>Nama Anggota</th><th>Jangka Waktu</th><th>Jumlah Pinjaman</th><th>Bunga (%)</th><th>Sisa Pokok</th><th>Sisa Bunga</th><th>Status</th></tr></thead><tbody>';

            $q_exp_pinjaman = $koneksi->query("
                SELECT p.*, a.nama AS nama_anggota,
                    IFNULL((SELECT SUM(jumlah_bayar) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman AND (pb.jenis_bayar = 'Bayar Angsuran' OR pb.jenis_bayar = 'Pelunasan' OR pb.jenis_bayar IS NULL)), 0) AS total_pokok_terbayar,
                    IFNULL((SELECT SUM(CASE WHEN pb.bayar_bunga > 0 THEN pb.bayar_bunga WHEN pb.jenis_bayar = 'Bayar Bunga' THEN pb.jumlah_bayar ELSE 0 END) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_terbayar
                FROM pinjaman p JOIN anggota a ON p.id_anggota = a.id
            ");

            while ($r = $q_exp_pinjaman->fetch(PDO::FETCH_ASSOC)) {
                $tenor = (int) ($r['tenor'] ?? $r['lama_angsuran'] ?? 1);
                $jumlah_pinjaman = (float) $r['jumlah_pinjaman'];
                $bunga_persen = (float) ($r['bunga'] ?? 0);
                
                $tot_bunga = (($jumlah_pinjaman * $bunga_persen) / 100) * $tenor;
                $sisa_pokok = max(0, $jumlah_pinjaman - (float) $r['total_pokok_terbayar']);
                
                $sisa_bunga = $tot_bunga - (float) $r['total_bunga_terbayar'];
                if ($sisa_bunga <= 0 && $sisa_pokok > 0) {
                    $sisa_bunga = ($sisa_pokok * $bunga_persen) / 100;
                }
                if ($sisa_bunga < 0) $sisa_bunga = 0;

                echo '<tr>';
                echo '<td class="text-center">PJ-' . sprintf('%03d', $r['no_pinjaman']) . '</td>';
                echo '<td>' . htmlspecialchars($r['nama_anggota']) . '</td>';
                echo '<td class="text-center">' . $tenor . ' Bulan</td>';
                echo '<td class="text-right">' . $jumlah_pinjaman . '</td>';
                echo '<td class="text-center">' . $bunga_persen . '%</td>';
                echo '<td class="text-right">' . $sisa_pokok . '</td>';
                echo '<td class="text-right">' . $sisa_bunga . '</td>';
                echo '<td class="text-center">' . $r['status'] . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<div class="subtitle">2. Riwayat Pembayaran Angsuran & Bunga</div>';
            echo '<table><thead><tr><th>Tanggal Bayar</th><th>No Pinjaman</th><th>Nama Anggota</th><th>Jenis Bayar</th><th>Nominal Bayar</th><th>Bayar Bunga</th></tr></thead><tbody>';

            $stmt_exp_riwayat = $koneksi->prepare("
                SELECT pb.*, a.nama AS nama_anggota
                FROM pembayaran pb
                JOIN pinjaman p ON pb.no_pinjaman = p.no_pinjaman
                JOIN anggota a ON p.id_anggota = a.id
                WHERE pb.tanggal BETWEEN ? AND ?
                ORDER BY pb.tanggal DESC, pb.id_bayar DESC
            ");
            $stmt_exp_riwayat->execute([$exp_tgl_mulai, $exp_tgl_selesai]);
            $rows_exp_riwayat = $stmt_exp_riwayat->fetchAll(PDO::FETCH_ASSOC);

            $tot_nom_exp = 0; $tot_bunga_exp = 0;
            foreach ($rows_exp_riwayat as $rw) {
                $tot_nom_exp += (float)$rw['jumlah_bayar'];
                $tot_bunga_exp += (float)$rw['bayar_bunga'];
                echo '<tr>';
                echo '<td class="text-center">' . date('d-m-Y', strtotime($rw['tanggal'])) . '</td>';
                echo '<td class="text-center">PJ-' . sprintf('%03d', $rw['no_pinjaman']) . '</td>';
                echo '<td>' . htmlspecialchars($rw['nama_anggota']) . '</td>';
                echo '<td class="text-center">' . htmlspecialchars($rw['jenis_bayar'] ?? 'Bayar Angsuran') . '</td>';
                echo '<td class="text-right">' . (float)$rw['jumlah_bayar'] . '</td>';
                echo '<td class="text-right">' . (float)$rw['bayar_bunga'] . '</td>';
                echo '</tr>';
            }
            echo '<tr style="font-weight: bold; background-color: #f1f5f9;"><td colspan="4" class="text-right">TOTAL:</td><td class="text-right">' . $tot_nom_exp . '</td><td class="text-right">' . $tot_bunga_exp . '</td></tr>';
            echo '</tbody></table></body></html>';
            exit();
        }
    }
}

$query_anggota_list = $koneksi->query("SELECT id, nama FROM anggota ORDER BY nama ASC");
$anggota_filter = $_GET['id_anggota'] ?? '';
$status_filter  = $_GET['status'] ?? '';

$where_clauses = ["1=1"];
$params_laporan = [];
if (!empty($anggota_filter)) {
    $where_clauses[] = "p.id_anggota = ?";
    $params_laporan[] = $anggota_filter;
}
if (!empty($status_filter)) {
    $where_clauses[] = "p.status = ?";
    $params_laporan[] = $status_filter;
}
$where_sql = implode(" AND ", $where_clauses);

$stmt_laporan = $koneksi->prepare("
    SELECT p.*, a.nama AS nama_anggota,
        IFNULL((SELECT SUM(jumlah_bayar) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman AND (pb.jenis_bayar = 'Bayar Angsuran' OR pb.jenis_bayar = 'Pelunasan' OR pb.jenis_bayar IS NULL)), 0) AS total_pokok_terbayar,
        IFNULL((SELECT SUM(CASE WHEN pb.bayar_bunga > 0 THEN pb.bayar_bunga WHEN pb.jenis_bayar IN ('Bunga Saja') THEN pb.jumlah_bayar ELSE 0 END) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_terbayar,
        IFNULL((SELECT SUM(CASE WHEN pb.jenis_bayar = 'Bayar Bunga dari sisa Pokok' THEN pb.bayar_bunga ELSE 0 END) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_sisa_pokok
    FROM pinjaman p
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_sql
    ORDER BY p.no_pinjaman DESC
");
$stmt_laporan->execute($params_laporan);
$rows_laporan = $stmt_laporan->fetchAll(PDO::FETCH_ASSOC);

$grand_total_pinjaman = 0; $grand_total_bunga = 0; $grand_sisa_pokok = 0; $grand_sisa_bunga = 0;
$data_laporan = [];
foreach ($rows_laporan as $row) {
    $pinjaman_pokok = (float)$row['jumlah_pinjaman'];
    $bunga_persen = (float)$row['bunga'];
    $tenor = (int) ($row['tenor'] ?? $row['lama_angsuran'] ?? 1);
    
    $total_bunga_pinjaman = (($pinjaman_pokok * $bunga_persen) / 100) * $tenor;
    $sisa_pokok = max(0, $pinjaman_pokok - (float)$row['total_pokok_terbayar']);
    
    $sisa_bunga = $total_bunga_pinjaman - (float)$row['total_bunga_terbayar'];
    if ($sisa_bunga <= 0 && $sisa_pokok > 0) {
        $sisa_bunga = ($sisa_pokok * $bunga_persen) / 100;
    }
    if ($sisa_bunga < 0) $sisa_bunga = 0;

    $grand_total_pinjaman += $pinjaman_pokok;
    $grand_total_bunga    += $total_bunga_pinjaman;
    $grand_sisa_pokok     += $sisa_pokok;
    $grand_sisa_bunga     += $sisa_bunga;

    $row['tenor_calc']       = $tenor;
    $row['total_bunga_calc'] = $total_bunga_pinjaman;
    $row['sisa_pokok_calc']  = $sisa_pokok;
    $row['sisa_bunga_calc']  = $sisa_bunga;
    $data_laporan[] = $row;
}

// Riwayat Pembayaran
$limit_riwayat = $is_print_preview ? 1000 : 5;
$page_riwayat = isset($_GET['page_riw']) ? (int)$_GET['page_riw'] : 1;
if ($page_riwayat < 1) $page_riwayat = 1;
$offset_riwayat = ($page_riwayat - 1) * $limit_riwayat;

$where_riwayat = ["1=1"];
$params_riwayat = [];
if ($is_print_preview) {
    $where_riwayat[] = "pb.tanggal BETWEEN ? AND ?";
    $params_riwayat[] = $print_tgl_mulai;
    $params_riwayat[] = $print_tgl_selesai;
}
if (!empty($anggota_filter)) {
    $where_riwayat[] = "p.id_anggota = ?";
    $params_riwayat[] = $anggota_filter;
}
if (!empty($status_filter)) {
    $where_riwayat[] = "p.status = ?";
    $params_riwayat[] = $status_filter;
}
$where_riwayat_sql = implode(" AND ", $where_riwayat);

$stmt_count_riwayat = $koneksi->prepare("
    SELECT COUNT(*) FROM pembayaran pb
    JOIN pinjaman p ON pb.no_pinjaman = p.no_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_riwayat_sql
");
$stmt_count_riwayat->execute($params_riwayat);
$total_data_riwayat = $stmt_count_riwayat->fetchColumn();
$total_pages_riwayat = ceil($total_data_riwayat / $limit_riwayat);

$stmt_riwayat = $koneksi->prepare("
    SELECT pb.*, a.nama AS nama_anggota
    FROM pembayaran pb
    JOIN pinjaman p ON pb.no_pinjaman = p.no_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_riwayat_sql
    ORDER BY pb.tanggal DESC, pb.id_bayar DESC
    LIMIT $limit_riwayat OFFSET $offset_riwayat
");
$stmt_riwayat->execute($params_riwayat);
$rows_riwayat = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);

$stmt_total_all = $koneksi->prepare("
    SELECT SUM(pb.jumlah_bayar) AS total_nominal, SUM(pb.bayar_bunga) AS total_bunga
    FROM pembayaran pb
    JOIN pinjaman p ON pb.no_pinjaman = p.no_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_riwayat_sql
");
$stmt_total_all->execute($params_riwayat);
$res_total_all = $stmt_total_all->fetch(PDO::FETCH_ASSOC);
$total_nominal_bayar = (float)($res_total_all['total_nominal'] ?? 0);
$total_bayar_bunga = (float)($res_total_all['total_bunga'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_print_preview ? 'Pratinjau Cetak Laporan' : 'Laporan Utama - KOPERASI BAKKEUM'; ?></title>
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

        .page-title { 
            font-size: 18px; 
            font-weight: bold; 
            color: #004d40; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }

        /* TOMBOL AKSI */
        .action-bar { 
            margin-bottom: 20px; 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap;
        }
        
        .btn { 
            padding: 9px 16px; 
            border: none; 
            border-radius: 8px; 
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
        .btn-save { background-color: #00796b; color: white; box-shadow: 0 4px 10px rgba(0, 121, 107, 0.3); }
        .btn-print { background-color: #475569; color: white; box-shadow: 0 4px 10px rgba(71, 85, 105, 0.3); }
        .btn-primary { background-color: #0288d1; color: white; }
        .btn-secondary { background-color: #9e9e9e; color: white; }

        .filter-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .filter-box select {
            flex: 1;
            min-width: 180px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
            color: #1e293b;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
        }
        .card h4 { font-size: 13px; color: #64748b; margin-bottom: 6px; font-weight: 600; }
        .card .value { font-size: 18px; font-weight: bold; }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            white-space: nowrap;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }
        th { background-color: #00796b; color: #ffffff; font-weight: 600; }
        tr:hover { background-color: #f8fafc; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: #dc2626; }
        .text-success { color: #16a34a; }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            color: #475569;
            background: #ffffff;
        }
        .pagination .active { background: #00796b; color: #ffffff; border-color: #00796b; }
        .pagination .disabled { color: #94a3b8; background: #f8fafc; pointer-events: none; }

        .badge { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-berjalan { background: #e0f2fe; color: #0369a1; }
        .badge-lunas { background: #dcfce7; color: #15803d; }
        .badge-macet { background: #fee2e2; color: #b91c1c; }

        h3.section-title {
            color: #004d40;
            margin-top: 25px;
            margin-bottom: 12px;
            font-size: 16px;
            border-bottom: 2px solid #00796b;
            padding-bottom: 6px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            justify-content: center; 
            align-items: center; 
            padding: 15px;
        }
        .modal-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 12px; width: 100%; max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            text-align: center;
        }
        .modal-header { font-weight: bold; font-size: 16px; margin-bottom: 12px; color: #1e293b; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .modal-options { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; }
        .modal-options button, .modal-options a {
            padding: 10px 15px; border-radius: 8px; text-align: center;
            font-weight: 600; text-decoration: none; font-size: 13px; border: none; cursor: pointer;
        }
        .opt-transaksi { background: #e0f2fe; color: #0369a1; width: 100%; }
        .opt-database { background: #fef3c7; color: #b45309; width: 100%; }
        .opt-semua { background: #dcfce7; color: #15803d; width: 100%; }
        .opt-print { background: #00796b; color: white; width: 100%; }
        .close-btn { background: #e2e8f0; color: #475569; width: 100%; }

        .form-group-date {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            text-align: left;
        }
        .form-group-date label { font-size: 12px; font-weight: 600; color: #475569; }
        .form-group-date input {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            width: 100%;
        }

        /* KHUSUS MODE PRATINJAU CETAK (LANDSCAPE & OPTIMAL UNTUK TABEL LEBAR) */
        <?php if ($is_print_preview): ?>
        @page { size: A4 landscape; margin: 10mm; }
        .app-container { flex-direction: column !important; }
        .sidebar, .action-bar, .filter-box, .pagination, .dashboard-header-flex { display: none !important; }
        .main-content { width: 100% !important; padding: 0 !important; background: #fff !important; }
        .content { box-shadow: none !important; border: none !important; padding: 10px !important; width: 100% !important; max-width: none !important; margin: 0 auto; background: #fff !important; }
        
        .print-kop-surat { display: block !important; text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 12px; }
        .print-kop-surat h1 { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; color: #000; }
        .print-kop-surat p { font-size: 10pt; margin: 3px 0 0 0; color: #333; }
        
        .print-meta { display: flex !important; justify-content: space-between; font-size: 10pt; margin-bottom: 15px; font-weight: 600; color: #000; }
        
        .print-tanda-tangan { display: flex !important; justify-content: flex-end; margin-top: 30px; page-break-inside: avoid; }
        .tanda-tangan-box { text-align: center; width: 220px; }
        .tanda-tangan-box p { margin: 0; font-size: 10pt; color: #000; }
        .space-ttd { height: 60px; }
        
        /* Bar Atas Pratinjau Cetak Ukuran Ringkas */
        .preview-action-bar {
            position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; background: #1e293b !important; padding: 6px 15px !important;
            display: flex !important; justify-content: space-between !important; align-items: center !important; z-index: 99999 !important; box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
            color: white !important; visibility: visible !important; opacity: 1 !important;
        }
        body { padding-top: 45px !important; background: #fff !important; font-family: 'Times New Roman', Times, serif !important; }
        
        /* Penyesuaian Tabel Cetak agar tidak terpotong */
        .table-responsive { overflow: visible !important; border: none !important; }
        table { font-size: 8.5pt !important; width: 100% !important; table-layout: auto !important; }
        th, td { border: 1px solid #333 !important; padding: 5px 6px !important; color: #000 !important; word-wrap: break-word; background: #fff !important; }
        th { background-color: #f1f5f9 !important; text-align: center; }
        
        .grid-container { display: flex !important; flex-direction: row !important; gap: 10px !important; margin-bottom: 15px !important; }
        .card { border: 1px solid #333 !important; background: #fff !important; box-shadow: none !important; padding: 6px 10px !important; flex: 1; border-radius: 4px !important; text-align: center; }
        .card h4 { margin: 0 0 4px 0 !important; font-size: 9pt !important; color: #000 !important; }
        .card .value { font-size: 9.5pt !important; }
        h3.section-title { font-size: 10.5pt !important; margin-top: 15px !important; margin-bottom: 6px !important; border-bottom: 1px solid #333 !important; color: #000 !important; padding-bottom: 3px !important; }
        
        @media print {
            .preview-action-bar { display: none !important; }
            body { padding-top: 0 !important; }
        }
        <?php endif; ?>

        .print-kop-surat, .print-meta, .print-tanda-tangan, .preview-action-bar { display: none; }

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
            .action-bar {
                flex-direction: column;
            }
            .action-bar .btn {
                width: 100%;
            }
            .filter-box select {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

<?php if ($is_print_preview): ?>
<!-- Toolbar Khusus Pratinjau Cetak Ukuran Ringkas di Atas Layar -->
<div class="preview-action-bar">
    <div>
        <strong style="font-size: 11px;">Pratinjau Cetak (Landscape)</strong>
    </div>
    <div style="display: flex; gap: 6px;">
        <button onclick="window.print()" class="btn" style="background: #16a34a; color: white; padding: 4px 10px; border-radius: 4px; cursor: pointer; border: none; font-weight: 600; font-size: 11px; width: auto;">🖨️ Cetak</button>
        <a href="laporan.php" class="btn" style="background: #64748b; color: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 11px; width: auto;">✖️ Batal</a>
    </div>
</div>
<?php endif; ?>

<div class="app-container">
    <?php if (!$is_print_preview) include 'sidebar.php'; ?>

    <main class="main-content">
        <?php if (!$is_print_preview): ?>
        <!-- HEADER / TOP BAR DINAMIS -->
        <div class="dashboard-header-flex">
            <div class="dashboard-title-box">
                <h2>KOPERASI BAKKEUM</h2>
                <p>Sistem Informasi Manajemen Anggota Koperasi</p>
            </div>
            <div class="header-live-clock" id="liveClock">
                📅 <span><?php echo date('d M Y'); ?></span> | ⏰ <span id="timeText">--:--:--</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="content">
            <!-- KOP SURAT CETAK -->
			<div class="print-kop-surat">
				<h1>KOPERASI BAKKEUM</h1>
				<p>Laporan Resmi Keuangan, Piutang, dan Riwayat Pembayaran</p>
			</div>

			<div class="print-meta">
				<span>Periode Laporan: <strong><?php echo $is_print_preview ? date('d-m-Y', strtotime($print_tgl_mulai)) . ' s/d ' . date('d-m-Y', strtotime($print_tgl_selesai)) : date('d-m-Y'); ?></strong></span>
				<span>Dicetak pada: <?php echo date('d/m/Y H:i'); ?> WIB</span>
			</div>

			<?php if (!$is_print_preview): ?>
            <div class="action-bar">
                <button onclick="openSaveModal()" class="btn btn-save">💾 Simpan Data</button>
                <button onclick="openPrintModal()" class="btn btn-print">🖨️ Cetak Laporan</button>
            </div>
            <?php endif; ?>

            <h3 class="section-title">📈 Laporan Keuangan Pinjaman & Piutang</h3>
            <?php if (!$is_print_preview): ?>
            <p style="margin-bottom: 15px; color: #64748b; font-size: 13px;">Rekapitulasi sisa pokok piutang serta kewajiban bunga anggota.</p>
            
            <form method="GET" action="" class="filter-box">
                <select name="id_anggota">
                    <option value="">-- Semua Anggota --</option>
                    <?php 
                    $list_anggota_rows = $query_anggota_list->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($list_anggota_rows as $ang) {
                        $selected = ($anggota_filter == $ang['id']) ? 'selected' : '';
                        echo "<option value='".$ang['id']."' $selected>".htmlspecialchars($ang['nama'])."</option>";
                    }
                    ?>
                </select>

                <select name="status">
                    <option value="">-- Semua Status --</option>
                    <option value="Berjalan" <?php echo ($status_filter == 'Berjalan') ? 'selected' : ''; ?>>Berjalan</option>
                    <option value="Lunas" <?php echo ($status_filter == 'Lunas') ? 'selected' : ''; ?>>Lunas</option>
                    <option value="Macet" <?php echo ($status_filter == 'Macet') ? 'selected' : ''; ?>>Macet</option>
                </select>
                
                <div style="display: flex; gap: 8px; flex: 1; min-width: 180px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Filter</button>
                    <a href="laporan.php" class="btn btn-secondary" style="flex: 1; text-align: center;">Reset</a>
                </div>
            </form>
            <?php endif; ?>

            <div class="grid-container">
                <div class="card">
                    <h4>Total Pinjaman Pokok</h4>
                    <p class="value" style="color:#00796b;">Rp <?php echo number_format($grand_total_pinjaman, 0, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Total Bunga</h4>
                    <p class="value" style="color:#f57c00;">Rp <?php echo number_format($grand_total_bunga, 0, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Sisa Pokok (Piutang)</h4>
                    <p class="value text-danger">Rp <?php echo number_format($grand_sisa_pokok, 0, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Sisa Bunga Belum Dibayar</h4>
                    <p class="value text-danger">Rp <?php echo number_format($grand_sisa_bunga, 0, ',', '.'); ?></p>
                </div>
            </div>

            <h3 class="section-title">📊 Rekapitulasi Status Pinjaman & Piutang</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No Pinjaman</th>
                            <th>Nama Anggota</th>
                            <th class="text-center">Jangka Waktu</th>
                            <th class="text-right">Jumlah Pinjaman</th>
                            <th class="text-right">Total Bunga</th>
                            <th class="text-right">Sisa Pokok (Piutang)</th>
                            <th class="text-right">Sisa Bunga Belum Dibayar</th>
                            <th class="text-right">Bunga Sisa Pokok Dibayar</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($data_laporan)) {
                            foreach ($data_laporan as $row) {
                                $badge_class = 'badge-berjalan';
                                if ($row['status'] == 'Lunas') $badge_class = 'badge-lunas';
                                if ($row['status'] == 'Macet') $badge_class = 'badge-macet';
                        ?>
                        <tr>
                            <td class="text-center">PJ-<?php echo sprintf('%03d', $row['no_pinjaman']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_anggota']); ?></td>
                            <td class="text-center"><?php echo $row['tenor_calc']; ?> Bulan</td>
                            <td class="text-right">Rp <?php echo number_format($row['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                            <td class="text-right">Rp <?php echo number_format($row['total_bunga_calc'], 0, ',', '.'); ?> (<?php echo $row['bunga']; ?>%)</td>
                            <td class="text-right text-danger">Rp <?php echo number_format($row['sisa_pokok_calc'], 0, ',', '.'); ?></td>
                            <td class="text-right text-danger">Rp <?php echo number_format($row['sisa_bunga_calc'], 0, ',', '.'); ?></td>
                            <td class="text-right text-success">Rp <?php echo number_format($row['total_bunga_sisa_pokok'], 0, ',', '.'); ?></td>
                            <td class="text-center"><span class="badge <?php echo $badge_class; ?>"><?php echo $row['status']; ?></span></td>
                        </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='9' class='text-center'>Data tidak ditemukan</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <h3 class="section-title">💳 Riwayat Pembayaran Angsuran & Bunga</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="text-center">Tgl Bayar</th>
                            <th class="text-center">No Pinjaman</th>
                            <th>Nama Anggota</th>
                            <th class="text-center">Jenis Bayar</th>
                            <th class="text-right">Nominal Bayar</th>
                            <th class="text-right">Bayar Bunga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (count($rows_riwayat) > 0) {
                            foreach ($rows_riwayat as $rw) {
                                $jenis = $rw['jenis_bayar'] ?? 'Bayar Angsuran';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo date('d/m/Y', strtotime($rw['tanggal'])); ?></td>
                            <td class="text-center">PJ-<?php echo sprintf('%03d', $rw['no_pinjaman']); ?></td>
                            <td><?php echo htmlspecialchars($rw['nama_anggota']); ?></td>
                            <td class="text-center"><strong><?php echo htmlspecialchars($jenis); ?></strong></td>
                            <td class="text-right text-success">Rp <?php echo number_format($rw['jumlah_bayar'] ?? 0, 0, ',', '.'); ?></td>
                            <td class="text-right text-success">Rp <?php echo number_format($rw['bayar_bunga'] ?? 0, 0, ',', '.'); ?></td>
                        </tr>
                        <?php 
                            }
                        ?>
                        <tr style="background-color: #f1f5f9; font-weight: bold;">
                            <td colspan="4" class="text-right">TOTAL KESELURUHAN:</td>
                            <td class="text-right text-success">Rp <?php echo number_format($total_nominal_bayar, 0, ',', '.'); ?></td>
                            <td class="text-right text-success">Rp <?php echo number_format($total_bayar_bunga, 0, ',', '.'); ?></td>
                        </tr>
                        <?php
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>Belum ada transaksi pembayaran pada rentang tanggal ini</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$is_print_preview && $total_pages_riwayat > 1): ?>
            <div class="pagination">
                <?php 
                $query_params_base = $_GET;
                if ($page_riwayat > 1) {
                    $query_params_base['page_riw'] = $page_riwayat - 1;
                    echo '<a href="laporan.php?' . http_build_query($query_params_base) . '">&laquo;</a>';
                } else {
                    echo '<span class="disabled">&laquo;</span>';
                }

                for ($i = 1; $i <= $total_pages_riwayat; $i++) {
                    $query_params_base['page_riw'] = $i;
                    $active_class = ($i == $page_riwayat) ? 'active' : '';
                    echo '<a class="' . $active_class . '" href="laporan.php?' . http_build_query($query_params_base) . '">' . $i . '</a>';
                }

                if ($page_riwayat < $total_pages_riwayat) {
                    $query_params_base['page_riw'] = $page_riwayat + 1;
                    echo '<a href="laporan.php?' . http_build_query($query_params_base) . '">&raquo;</a>';
                } else {
                    echo '<span class="disabled">&raquo;</span>';
                }
                ?>
            </div>
            <?php endif; ?>

            <!-- TANDA TANGAN KHUSUS PRATINJAU CETAK -->
            <div class="print-tanda-tangan">
                <div class="tanda-tangan-box">
                    <p>Mengetahui,</p>
                    <p><strong>Pengurus Koperasi</strong></p>
                    <div class="space-ttd"></div>
                    <p><strong><u>Ketua / Pengelola</u></strong></p>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- MODAL SIMPAN DATA -->
<div id="saveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">💾 Pilih Opsi Simpan Data</div>
        <p style="font-size: 13px; color: #666; margin-bottom: 12px;">Tentukan rentang tanggal riwayat pembayaran:</p>
        <div class="form-group-date">
            <label>Dari Tanggal:</label>
            <input type="date" id="modal_tgl_mulai" value="<?php echo date('Y-01-01'); ?>">
        </div>
        <div class="form-group-date" style="margin-bottom: 15px;">
            <label>Sampai Tanggal:</label>
            <input type="date" id="modal_tgl_selesai" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="modal-options">
            <button type="button" onclick="submitExport('transaksi')" class="opt-transaksi">📊 Simpan Transaksi (Excel)</button>
            <button type="button" onclick="submitExport('database')" class="opt-database">🗄️ Backup Database (.SQL)</button>
            <button type="button" onclick="submitExport('semua')" class="opt-semua">📦 Simpan Semua Data</button>
            <button type="button" onclick="closeSaveModal()" class="btn close-btn" style="width:100%;">Batal</button>
        </div>
    </div>
</div>

<!-- MODAL PILIH RENTANG TANGGAL SEBELUM CETAK -->
<div id="printModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">🖨️ Pilih Rentang Tanggal Laporan</div>
        <p style="font-size: 13px; color: #666; margin-bottom: 12px;">Tentukan rentang tanggal riwayat pembayaran:</p>
        <div class="form-group-date">
            <label>Dari Tanggal:</label>
            <input type="date" id="print_tgl_mulai" value="<?php echo date('Y-01-01'); ?>">
        </div>
        <div class="form-group-date" style="margin-bottom: 15px;">
            <label>Sampai Tanggal:</label>
            <input type="date" id="print_tgl_selesai" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="modal-options">
            <button type="button" onclick="executePrintPreview()" class="opt-print">👁️ Tampilkan Pratinjau</button>
            <button type="button" onclick="closePrintModal()" class="btn close-btn" style="width:100%;">Batal</button>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const timeTextEl = document.getElementById('timeText');
        if (timeTextEl) {
            timeTextEl.textContent = `${hours}:${minutes}:${seconds}`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    function openSaveModal() { document.getElementById('saveModal').style.display = 'flex'; }
    function closeSaveModal() { document.getElementById('saveModal').style.display = 'none'; }
    
    function openPrintModal() { document.getElementById('printModal').style.display = 'flex'; }
    function closePrintModal() { document.getElementById('printModal').style.display = 'none'; }

    function submitExport(type) {
        var tglMulai = document.getElementById('modal_tgl_mulai').value;
        var tglSelesai = document.getElementById('modal_tgl_selesai').value;
        var url = "laporan.php?action_export=" + type + "&exp_tgl_mulai=" + encodeURIComponent(tglMulai) + "&exp_tgl_selesai=" + encodeURIComponent(tglSelesai);
        window.location.href = url;
        closeSaveModal();
    }

    function executePrintPreview() {
        var tglMulai = document.getElementById('print_tgl_mulai').value;
        var tglSelesai = document.getElementById('print_tgl_selesai').value;
        var url = "laporan.php?print_preview=true&print_tgl_mulai=" + encodeURIComponent(tglMulai) + "&print_tgl_selesai=" + encodeURIComponent(tglSelesai);
        window.location.href = url;
    }

    window.onclick = function(event) {
        var saveModal = document.getElementById('saveModal');
        var printModal = document.getElementById('printModal');
        if (event.target == saveModal) saveModal.style.display = 'none';
        if (event.target == printModal) printModal.style.display = 'none';
    }
</script>

</body>
</html>