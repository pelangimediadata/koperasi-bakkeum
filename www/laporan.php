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

// Ambil input filter periode
$tgl_awal         = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-01-01');
$tgl_akhir        = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-12-31');

if (isset($_GET['action_export'])) {
    $export_type = $_GET['action_export'];
    if ($export_type === 'transaksi' || $export_type === 'semua') {
        if ($export_type === 'transaksi') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=Rekap_Transaksi_Koperasi_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['--- REKAP PINJAMAN & PIUTANG ---']);
            fputcsv($output, ['No Pinjaman', 'Nama Anggota', 'Jangka Waktu', 'Jumlah Pinjaman', 'Bunga (%)', 'Sisa Pokok', 'Sisa Bunga', 'Status']);

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

                fputcsv($output, ['PJ-' . sprintf('%03d', $r['no_pinjaman']), $r['nama_anggota'], $tenor . ' Bulan', $jumlah_pinjaman, $bunga_persen, $sisa_pokok, $sisa_bunga, $r['status']]);
            }
            fclose($output);
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
        IFNULL((SELECT SUM(
            CASE 
                WHEN pb.bayar_bunga > 0 THEN pb.bayar_bunga 
                WHEN pb.jenis_bayar IN ('Bunga Saja') THEN pb.jumlah_bayar 
                ELSE 0 
            END
        ) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_terbayar,
        IFNULL((SELECT SUM(
            CASE 
                WHEN pb.jenis_bayar = 'Bayar Bunga dari sisa Pokok' THEN pb.bayar_bunga 
                ELSE 0 
            END
        ) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_sisa_pokok
    FROM pinjaman p
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_sql
    ORDER BY p.no_pinjaman DESC
");
$stmt_laporan->execute($params_laporan);
$rows_laporan = $stmt_laporan->fetchAll(PDO::FETCH_ASSOC);

$grand_total_pinjaman = 0; $grand_total_bunga = 0; $grand_sisa_pokok = 0; $grand_sisa_bunga = 0;
$data_laporan = [];
if (count($rows_laporan) > 0) {
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
}

$where_riwayat = ["1=1"];
$params_riwayat = [];
if (!empty($anggota_filter)) {
    $where_riwayat[] = "p.id_anggota = ?";
    $params_riwayat[] = $anggota_filter;
}
if (!empty($status_filter)) {
    $where_riwayat[] = "p.status = ?";
    $params_riwayat[] = $status_filter;
}
$where_riwayat_sql = implode(" AND ", $where_riwayat);

$stmt_riwayat = $koneksi->prepare("
    SELECT pb.*, a.nama AS nama_anggota
    FROM pembayaran pb
    JOIN pinjaman p ON pb.no_pinjaman = p.no_pinjaman
    JOIN anggota a ON p.id_anggota = a.id
    WHERE $where_riwayat_sql
    ORDER BY pb.tanggal DESC, pb.id_bayar DESC
");
$stmt_riwayat->execute($params_riwayat);
$rows_riwayat = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);
// Hitung total keseluruhan untuk riwayat pembayaran
$total_nominal_bayar = 0;
$total_bayar_bunga = 0;
foreach ($rows_riwayat as $rw) {
    $total_nominal_bayar += (float)($rw['jumlah_bayar'] ?? 0);
    $total_bayar_bunga += (float)($rw['bayar_bunga'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Utama - Koperasi Bakkeum</title>
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
        
        .sidebar {
            width: 70px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            transition: width 0.3s ease;
            overflow: hidden;
            z-index: 100;
        }
        .sidebar:hover { width: 260px; }
        .sidebar .menu-text { opacity: 0; visibility: hidden; transition: opacity 0.2s ease; white-space: nowrap; }
        .sidebar:hover .menu-text { opacity: 1; visibility: visible; }
        .sidebar .arrow { opacity: 0; transition: opacity 0.2s ease; }
        .sidebar:hover .arrow { opacity: 1; }

        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; background: transparent; }

        h2 { color: #ffffff; margin-bottom: 20px; text-shadow: 0 2px 4px rgba(0,0,0,0.3); font-size: 24px; }

        .content {
            background: rgba(255, 255, 255, 0.96);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .action-bar { margin-bottom: 25px; display: flex; gap: 10px; }
        
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-save { background: #00796b; color: white; }
        .btn-save:hover { background: #004d40; }
        .btn-print { background: #475569; color: white; }
        .btn-print:hover { background: #334155; }
        .btn-primary { background: #0288d1; color: white; }
        .btn-primary:hover { background: #01579b; }
        .btn-secondary { background: #9e9e9e; color: white; }
        .btn-secondary:hover { background: #616161; }

        .filter-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-box select {
            flex: 1;
            min-width: 200px;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .card h4 { font-size: 13px; color: #64748b; margin-bottom: 8px; font-weight: 600; }
        .card .value { font-size: 18px; font-weight: bold; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; }
        tr:hover { background-color: #f8fafc; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: #dc2626; }
        .text-success { color: #16a34a; }

        .badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-berjalan { background: #e0f2fe; color: #0369a1; }
        .badge-lunas { background: #dcfce7; color: #15803d; }
        .badge-macet { background: #fee2e2; color: #b91c1c; }

        h3.section-title {
            color: #1e293b;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto; padding: 25px;
            border-radius: 12px; width: 90%; max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-header { font-weight: bold; font-size: 16px; margin-bottom: 10px; color: #1e293b; }
        .modal-options { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; }
        .modal-options a, .modal-options button {
            padding: 10px 15px; border-radius: 8px; text-align: center;
            font-weight: 600; text-decoration: none; font-size: 14px; border: none; cursor: pointer;
        }
        .opt-transaksi { background: #e0f2fe; color: #0369a1; }
        .opt-database { background: #fef3c7; color: #b45309; }
        .opt-semua { background: #dcfce7; color: #15803d; }
        .close-btn { background: #e2e8f0; color: #475569; }

		@media print {
			@page { size: A4; margin: 20mm; }
			body { background: white !important; color: #000 !important; font-family: 'Times New Roman', Times, serif !important; font-size: 12pt; }
			.sidebar, .action-bar, .filter-box, .modal, form { display: none !important; }
			.app-container { display: block !important; }
			.main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
			.content { box-shadow: none !important; padding: 0 !important; background: white !important; border-radius: 0 !important; }

			h2 { text-align: center; color: #000 !important; font-size: 18pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; text-shadow: none !important; }

			.print-kop-surat { display: block !important; text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
			.print-kop-surat h1 { font-size: 16pt; font-weight: bold; margin: 0; text-transform: uppercase; }
			.print-kop-surat p { font-size: 10pt; margin: 3px 0 0 0; }

			.print-meta { display: flex !important; justify-content: space-between; font-size: 10pt; margin-bottom: 15px; font-style: italic; }

			table { width: 100% !important; border-collapse: collapse !important; margin-top: 10px !important; margin-bottom: 20px !important; font-size: 10pt !important; }
			th, td { border: 1px solid #333 !important; padding: 6px 8px !important; color: #000 !important; }
			th { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; text-align: center; }
			
			.card { border: 1px solid #333 !important; background: #fff !important; box-shadow: none !important; padding: 10px !important; margin-bottom: 10px !important; }

			.print-tanda-tangan { display: flex !important; justify-content: flex-end; margin-top: 40px; page-break-inside: avoid; }
			.tanda-tangan-box { text-align: center; width: 250px; }
			.tanda-tangan-box p { margin: 0; font-size: 11pt; }
			.space-ttd { height: 70px; }
		}

		.print-kop-surat, .print-meta, .print-tanda-tangan { display: none; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <h2>Laporan Utama Simpan Pinjam</h2>

        <div class="content">
			<div class="print-kop-surat">
				<h1>KOPERASI BAKKEUM</h1>
				<p>Laporan Resmi Keuangan dan Piutang</p>
			</div>

			<div class="print-meta">
				<span>Periode Tahun: <?php echo date('Y'); ?></span>
				<span>Dicetak pada: <?php echo date('d/m/Y H:i'); ?> WIB</span>
			</div>

			<?php if (isset($_SESSION['notif_success'])): ?>
                <div class="alert-success"><?php echo $_SESSION['notif_success']; unset($_SESSION['notif_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['notif_error'])): ?>
                <div class="alert-error"><?php echo $_SESSION['notif_error']; unset($_SESSION['notif_error']); ?></div>
            <?php endif; ?>

            <div class="action-bar">
                <button onclick="openSaveModal()" class="btn btn-save">💾 Simpan Data</button>
                <button onclick="window.print()" class="btn btn-print">🖨️ Cetak Laporan</button>
            </div>

            <h3 class="section-title">📈 Laporan Keuangan Pinjaman & Piutang</h3>
            <p style="margin-bottom: 15px; color: #64748b; font-size: 14px;">Rekapitulasi sisa pokok piutang serta kewajiban bunga anggota.</p>
            
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
                
                <button type="submit" class="btn btn-primary">Filter Data</button>
                <a href="laporan.php" class="btn btn-secondary">Reset</a>
            </form>

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
            <table>
                <thead>
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
					</tr>>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center'>Data tidak ditemukan</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <h3 class="section-title">💳 Riwayat Pembayaran Angsuran & Bunga</h3>
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
                        // Baris Keterangan Total di Bawah
                    ?>
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="4" class="text-right">TOTAL KESELURUHAN:</td>
                        <td class="text-right text-success">Rp <?php echo number_format($total_nominal_bayar, 0, ',', '.'); ?></td>
                        <td class="text-right text-success">Rp <?php echo number_format($total_bayar_bunga, 0, ',', '.'); ?></td>
                    </tr>
                    <?php
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>Belum ada transaksi pembayaran</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="saveModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">💾 Pilih Opsi Simpan Data</div>
                <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Silakan pilih format/data yang ingin Anda simpan atau unduh:</p>
                <div class="modal-options">
                    <a href="laporan.php?action_export=transaksi" class="opt-transaksi">📄 Simpan Data Transaksi (.CSV)</a>
                    <a href="laporan.php?action_export=database" class="opt-database">🗄️ Simpan Backup Database (.SQL)</a>
                    <a href="laporan.php?action_export=semua" class="opt-semua">📦 Simpan Semua Data</a>
                    <button onclick="closeSaveModal()" class="btn close-btn">Batal</button>
                </div>
            </div>
        </div>
    </main>
	<div class="print-tanda-tangan">
    <div class="tanda-tangan-box">
        <p>Mengetahui,</p>
        <p><strong>Pengurus Koperasi</strong></p>
        <div class="space-ttd"></div>
        <p><strong><u>Ketua / Pengelola</u></strong></p>
    </div>
</div>
</div>

<script>
    function openSaveModal() { document.getElementById('saveModal').style.display = 'block'; }
    function closeSaveModal() { document.getElementById('saveModal').style.display = 'none'; }
    window.onclick = function(event) {
        var modal = document.getElementById('saveModal');
        if (event.target == modal) modal.style.display = 'none';
    }
</script>

</body>
</html>