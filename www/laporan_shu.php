<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

$tgl_awal         = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-01-01');
$tgl_akhir        = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-12-31');
$periode_aktif    = $tgl_awal . " s/d " . $tgl_akhir;

$cetak_periode    = isset($_GET['cetak_periode']) ? $_GET['cetak_periode'] : 'semua';
$total_alokasi_shu= isset($_POST['total_alokasi_shu']) ? floatval($_POST['total_alokasi_shu']) : (isset($_GET['total_alokasi_shu']) ? floatval($_GET['total_alokasi_shu']) : 10000000);
$persen_shu_ang   = isset($_POST['persen_shu_ang']) ? floatval($_POST['persen_shu_ang']) : (isset($_GET['persen_shu_ang']) ? floatval($_GET['persen_shu_ang']) : 40);
$persen_jma       = isset($_POST['persen_jma']) ? floatval($_POST['persen_jma']) : (isset($_GET['persen_jma']) ? floatval($_GET['persen_jma']) : 20);
$persen_jua       = isset($_POST['persen_jua']) ? floatval($_POST['persen_jua']) : (isset($_GET['persen_jua']) ? floatval($_GET['persen_jua']) : 20);

$total_shu_input = ($persen_shu_ang / 100) * $total_alokasi_shu;
$alokasi_jma     = ($persen_jma / 100) * $total_alokasi_shu;
$alokasi_jua     = ($persen_jua / 100) * $total_alokasi_shu;

// Pastikan struktur tabel shu_pembayaran lengkap
$koneksi->exec("CREATE TABLE IF NOT EXISTS shu_pembayaran (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_anggota INTEGER NOT NULL,
    periode TEXT DEFAULT '',
    jma NUMERIC DEFAULT 0,
    jua NUMERIC DEFAULT 0,
    total_shu NUMERIC DEFAULT 0,
    jumlah_dibayar NUMERIC DEFAULT 0,
    tanggal_dibayar DATETIME DEFAULT NULL,
    status TEXT DEFAULT 'Belum Dibayar'
)");

// Cek dan tambahkan kolom jika belum ada pada database lama
$col_check = $koneksi->query("PRAGMA table_info(shu_pembayaran)");
$has_periode = false; $has_jma = false; $has_jua = false; $has_total_shu = false; $has_status = false;
while($col = $col_check->fetch()) {
    if(strtolower($col['name']) == 'periode') $has_periode = true;
    if(strtolower($col['name']) == 'jma') $has_jma = true;
    if(strtolower($col['name']) == 'jua') $has_jua = true;
    if(strtolower($col['name']) == 'total_shu') $has_total_shu = true;
    if(strtolower($col['name']) == 'status') $has_status = true;
}
if(!$has_periode) $koneksi->exec("ALTER TABLE shu_pembayaran ADD COLUMN periode TEXT DEFAULT ''");
if(!$has_jma) $koneksi->exec("ALTER TABLE shu_pembayaran ADD COLUMN jma NUMERIC DEFAULT 0");
if(!$has_jua) $koneksi->exec("ALTER TABLE shu_pembayaran ADD COLUMN jua NUMERIC DEFAULT 0");
if(!$has_total_shu) $koneksi->exec("ALTER TABLE shu_pembayaran ADD COLUMN total_shu NUMERIC DEFAULT 0");
if(!$has_status) $koneksi->exec("ALTER TABLE shu_pembayaran ADD COLUMN status TEXT DEFAULT 'Belum Dibayar'");

// Ambil master anggota & total keseluruhan simpanan/transaksi global
$master_anggota = [];
$grand_total_simpanan = 0;
$grand_total_transaksi = 0;

$q_member = $koneksi->query("SELECT name FROM sqlite_master WHERE type='table' AND name='anggota'");
if ($q_member && $q_member->fetch()) {
    $members_query = $koneksi->query("SELECT * FROM anggota");
    while ($m = $members_query->fetch()) {
        $id_anggota = $m['id_anggota'] ?? $m['id'] ?? null;
        $nama_anggota = $m['nama_anggota'] ?? $m['nama'] ?? 'Tanpa Nama';

        $total_simpanan = 0;
        $stmt_simp = $koneksi->prepare("SELECT SUM(jumlah) as total FROM simpanan WHERE id_anggota = ?");
        $stmt_simp->execute([$id_anggota]);
        if ($r_simp = $stmt_simp->fetch()) $total_simpanan = floatval($r_simp['total'] ?? 0);

        $total_transaksi = 0;
        $stmt_pinj = $koneksi->prepare("SELECT SUM(jumlah_pinjaman) as total FROM pinjaman WHERE id_anggota = ?");
        $stmt_pinj->execute([$id_anggota]);
        if ($r_pinj = $stmt_pinj->fetch()) $total_transaksi += floatval($r_pinj['total'] ?? 0);

        $stmt_toko = $koneksi->prepare("SELECT SUM(jumlah * harga_satuan) as total FROM penjualan WHERE id_anggota = ?");
        $stmt_toko->execute([$id_anggota]);
        if ($r_toko = $stmt_toko->fetch()) $total_transaksi += floatval($r_toko['total'] ?? 0);

        $grand_total_simpanan += $total_simpanan;
        $grand_total_transaksi += $total_transaksi;

        $master_anggota[$id_anggota] = [
            'nama' => $nama_anggota,
            'total_simpanan' => $total_simpanan,
            'total_transaksi' => $total_transaksi
        ];
    }
}

// ==========================================
// HANDLING SIMPAN / UPDATE HITUNG SHU ANGGOTA (STATUS: BELUM DIBAYAR)
// ==========================================
if (isset($_POST['simpan_hitung_shu'])) {
    $id_anggota_shu      = intval($_POST['id_anggota_shu'] ?? 0);
    $target_periode      = $_POST['periode_target'] ?? $periode_aktif;
    
    if ($id_anggota_shu == -1) {
        $jumlah_sukses = 0;
        foreach ($master_anggota as $id_agt => $agt) {
            $jma_calc = round(($agt['total_simpanan'] / ($grand_total_simpanan > 0 ? $grand_total_simpanan : 1)) * ($persen_jma / 100) * $total_alokasi_shu);
            $jua_calc = round(($agt['total_transaksi'] / ($grand_total_transaksi > 0 ? $grand_total_transaksi : 1)) * ($persen_jua / 100) * $total_alokasi_shu);
            $total_calc = $jma_calc + $jua_calc;

            if ($total_calc > 0) {
                $stmt_cek = $koneksi->prepare("SELECT id, status FROM shu_pembayaran WHERE id_anggota = ? AND periode = ? LIMIT 1");
                $stmt_cek->execute([$id_agt, $target_periode]);
                $cek_exist = $stmt_cek->fetch();
                
                if (!$cek_exist) {
                    // Sesuai Gambar 1: Status awal Belum Dibayar, belum masuk kas/pengeluaran
                    $stmt_ins = $koneksi->prepare("INSERT INTO shu_pembayaran (id_anggota, periode, jma, jua, total_shu, jumlah_dibayar, status, tanggal_dibayar) VALUES (?, ?, ?, ?, ?, 0, 'Belum Dibayar', NULL)");
                    $stmt_ins->execute([$id_agt, $target_periode, $jma_calc, $jua_calc, $total_calc]);
                } else if ($cek_exist['status'] !== 'Lunas Terbayar') {
                    $stmt_upd = $koneksi->prepare("UPDATE shu_pembayaran SET jma = ?, jua = ?, total_shu = ? WHERE id_anggota = ? AND periode = ?");
                    $stmt_upd->execute([$jma_calc, $jua_calc, $total_calc, $id_agt, $target_periode]);
                }
                $jumlah_sukses++;
            }
        }
        $_SESSION['notif_success'] = "Berhasil menghitung dan menyimpan data SHU untuk $jumlah_sukses Anggota periode $target_periode!";
    } else {
        $manual_jma          = round(floatval($_POST['jumlah_jma_hitung'] ?? 0)); 
        $manual_jua          = round(floatval($_POST['jumlah_jua_hitung'] ?? 0));
        $manual_total_shu    = round(floatval($_POST['jumlah_shu_bayar'] ?? ($manual_jma + $manual_jua)));
        $nama_anggota_shu    = $_POST['nama_anggota_shu'] ?? '';
        
        if ($id_anggota_shu > 0 && $manual_total_shu > 0) {
            $stmt_cek = $koneksi->prepare("SELECT id, status FROM shu_pembayaran WHERE id_anggota = ? AND periode = ? LIMIT 1");
            $stmt_cek->execute([$id_anggota_shu, $target_periode]);
            $cek_exist = $stmt_cek->fetch();
            
            if (!$cek_exist) {
                // Sesuai Gambar 1: Status awal Belum Dibayar
                $stmt_ins = $koneksi->prepare("INSERT INTO shu_pembayaran (id_anggota, periode, jma, jua, total_shu, jumlah_dibayar, status, tanggal_dibayar) VALUES (?, ?, ?, ?, ?, 0, 'Belum Dibayar', NULL)");
                $stmt_ins->execute([$id_anggota_shu, $target_periode, $manual_jma, $manual_jua, $manual_total_shu]);
            } else if ($cek_exist['status'] !== 'Lunas Terbayar') {
                $stmt_upd = $koneksi->prepare("UPDATE shu_pembayaran SET jma = ?, jua = ?, total_shu = ? WHERE id_anggota = ? AND periode = ?");
                $stmt_upd->execute([$manual_jma, $manual_jua, $manual_total_shu, $id_anggota_shu, $target_periode]);
            }
            $_SESSION['notif_success'] = "Hasil Hitung SHU untuk anggota $nama_anggota_shu periode $target_periode berhasil disimpan!";
        } else {
            $_SESSION['notif_error'] = "Gagal memproses data SHU. Nominal tidak valid.";
        }
    }
    header("Location: laporan_shu.php?tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir&total_alokasi_shu=$total_alokasi_shu&persen_shu_ang=$persen_shu_ang&persen_jma=$persen_jma&persen_jua=$persen_jua");
    exit();
}

// ==========================================
// HANDLING TOMBOL BAYARKAN SHU (UBAH JADI LUNAS & MASUK LAPORAN KAS)
// ==========================================
if (isset($_POST['bayarkan_shu_aksi'])) {
    $id_agt = intval($_POST['id_anggota_shu'] ?? 0);
    $target_periode = $_POST['periode_target'] ?? $periode_aktif;
    $tgl_sekarang = date('Y-m-d H:i:s');

    $stmt_dt = $koneksi->prepare("SELECT total_shu FROM shu_pembayaran WHERE id_anggota = ? AND periode = ? LIMIT 1");
    $stmt_dt->execute([$id_agt, $target_periode]);
    $dt_row = $stmt_dt->fetch();

    if ($dt_row) {
        $nominal_bayar = floatval($dt_row['total_shu']);
        $nama_agt = $master_anggota[$id_agt]['nama'] ?? 'Anggota';

        // Sesuai Gambar 1 & 2: Update status menjadi Lunas, catat tanggal, dan masukkan ke pengeluaran kas (Pembayaran SHU Anggota)
        $stmt_upd = $koneksi->prepare("UPDATE shu_pembayaran SET jumlah_dibayar = ?, status = 'Lunas Terbayar', tanggal_dibayar = ? WHERE id_anggota = ? AND periode = ?");
        $stmt_upd->execute([$nominal_bayar, $tgl_sekarang, $id_agt, $target_periode]);

        // Masukkan ke tabel pengeluaran kas
        $koneksi->exec("CREATE TABLE IF NOT EXISTS pengeluaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT,
            keterangan TEXT,
            jumlah NUMERIC
        )");
        $ket_keluar = "Pembayaran SHU Anggota (" . $target_periode . "): " . $nama_agt;
        $stmt_peng = $koneksi->prepare("INSERT INTO pengeluaran (tanggal, keterangan, jumlah) VALUES (?, ?, ?)");
        $stmt_peng->execute([date('Y-m-d'), $ket_keluar, $nominal_bayar]);

        $_SESSION['notif_success'] = "SHU untuk anggota $nama_agt berhasil dibayarkan dan otomatis masuk ke laporan pengeluaran kas!";
    }
    header("Location: laporan_shu.php?tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir&total_alokasi_shu=$total_alokasi_shu&persen_shu_ang=$persen_shu_ang&persen_jma=$persen_jma&persen_jua=$persen_jua");
    exit();
}

// ==========================================
// HANDLING PEMBATALAN PEMBAYARAN / HAPUS SHU
// ==========================================
if (isset($_POST['batal_bayar_shu'])) {
    $id_anggota_shu = intval($_POST['id_anggota_shu'] ?? 0);
    $target_periode = $_POST['periode_target'] ?? $periode_aktif;
    if ($id_anggota_shu > 0) {
        $stmt_cek = $koneksi->prepare("SELECT total_shu, status FROM shu_pembayaran WHERE id_anggota = ? AND periode = ? LIMIT 1");
        $stmt_cek->execute([$id_anggota_shu, $target_periode]);
        $row_cek = $stmt_cek->fetch();

        if ($row_cek && $row_cek['status'] === 'Lunas Terbayar') {
            $nama_agt = $master_anggota[$id_anggota_shu]['nama'] ?? '';
            $ket_keluar = "Pembayaran SHU Anggota (" . $target_periode . "): " . $nama_agt;
            
            // Hapus pencatatan terkait dari tabel pengeluaran kas
            $stmt_del_peng = $koneksi->prepare("DELETE FROM pengeluaran WHERE keterangan = ?");
            $stmt_del_peng->execute([$ket_keluar]);
        }

        $stmt_del = $koneksi->prepare("DELETE FROM shu_pembayaran WHERE id_anggota = ? AND periode = ?");
        $stmt_del->execute([$id_anggota_shu, $target_periode]);
        
        $_SESSION['notif_success'] = "Data riwayat SHU periode $target_periode berhasil dihapus/dibatalkan.";
    }
    header("Location: laporan_shu.php?tgl_awal=$tgl_awal&tgl_akhir=$tgl_akhir");
    exit();
}

// Ambil daftar periode unik murni berdasarkan data yang benar-benar tersimpan di database
$list_periode = [];
$q_per = $koneksi->query("SELECT DISTINCT periode FROM shu_pembayaran WHERE periode IS NOT NULL AND periode != ''");
if ($q_per) {
    while ($row_p = $q_per->fetch()) {
        $p_val = $row_p['periode'];
        
        // Validasi pastikan periode tersebut benar-benar memiliki data anggota
        $q_cek_data = $koneksi->prepare("SELECT COUNT(*) as jml FROM shu_pembayaran WHERE periode = ?");
        $q_cek_data->execute([$p_val]);
        $res_cek = $q_cek_data->fetch();
        
        if ($res_cek && intval($res_cek['jml']) > 0) {
            if (!in_array($p_val, $list_periode)) {
                $list_periode[] = $p_val;
            }
        }
    }
}
rsort($list_periode);

// Ambil data riwayat SHU tersimpan
$data_riwayat_shu = [];
$q_pay_all = $koneksi->query("SELECT * FROM shu_pembayaran ORDER BY id DESC");
while ($r_pay = $q_pay_all->fetch()) {
    $id_agt = $r_pay['id_anggota'];
    $p_item = $r_pay['periode'] !== '' ? $r_pay['periode'] : $periode_aktif;
    
    if (isset($master_anggota[$id_agt])) {
        $agt_val = $master_anggota[$id_agt];
        
        $data_riwayat_shu[] = [
            'id_anggota'     => $id_agt,
            'nama'           => $agt_val['nama'],
            'periode'        => $p_item,
            'simpanan'       => $agt_val['total_simpanan'],
            'jma'            => floatval($r_pay['jma'] ?? 0),
            'transaksi'      => $agt_val['total_transaksi'],
            'jua'            => floatval($r_pay['jua'] ?? 0),
            'total_shu'      => floatval($r_pay['total_shu'] ?? 0),
            'sudah_dibayar'  => floatval($r_pay['jumlah_dibayar'] ?? 0),
            'tgl_pembayaran' => $r_pay['tanggal_dibayar'] ?? '',
            'status_db'      => $r_pay['status'] ?? 'Belum Dibayar'
        ];
    }
}

// HITUNG NILAI RINGKASAN ATAS BERDASARKAN PERIODE CETAK
$sum_total_shu_diterima = 0;
foreach ($data_riwayat_shu as $row) {
    if ($cetak_periode === 'semua' || $row['periode'] === $cetak_periode) {
        $sum_total_shu_diterima += $row['total_shu'];
    }
}

if ($cetak_periode !== 'semua' && $sum_total_shu_diterima > 0) {
    $total_alokasi_shu_cetak = $sum_total_shu_diterima / ($persen_shu_ang / 100);
    $total_shu_input_cetak   = $sum_total_shu_diterima;
    $alokasi_jma_cetak       = ($persen_jma / 100) * $total_alokasi_shu_cetak;
    $alokasi_jua_cetak       = ($persen_jua / 100) * $total_alokasi_shu_cetak;
} else {
    $total_alokasi_shu_cetak = $total_alokasi_shu;
    $total_shu_input_cetak   = $total_shu_input;
    $alokasi_jma_cetak       = $alokasi_jma;
    $alokasi_jua_cetak       = $alokasi_jua;
}

// ==========================================
// SETUP PAGINATION (10 DATA PER HALAMAN)
// ==========================================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$total_data = count($data_riwayat_shu);
$total_pages = ceil($total_data / $limit);
if ($total_pages == 0) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;
$start_index = ($page - 1) * $limit;
$paginated_data = array_slice($data_riwayat_shu, $start_index, $limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Sisa Hasil Usaha (SHU) - Koperasi</title>
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
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; background: transparent; }

        h2.page-title {
            color: #ffffff;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            font-size: 24px;
        }

        .content {
            background: rgba(255, 255, 255, 0.98);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }

        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        .top-section-layout {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 20px;
            margin-bottom: 25px;
            align-items: start;
        }

        .form-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 0;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .form-row label {
            width: 220px;
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
        }
        .form-row input, .form-row select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            width: 240px;
            outline: none;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary { background: #0288d1; color: white; }
        .btn-primary:hover { background: #01579b; }
        .btn-print { background: #00796b; color: white; }
        .btn-print:hover { background: #004d40; }
        .btn-bayarkan { background: #2e7d32; color: white; padding: 5px 10px; font-size: 11px; }
        .btn-bayarkan:hover { background: #1b5e20; }
        .btn-cetak-kwitansi { background: #0288d1; color: white; padding: 5px 10px; font-size: 11px; }
        .btn-cetak-kwitansi:hover { background: #01579b; }

        .summary-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .s-box {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 14px 18px;
            border-radius: 10px;
        }
        .s-box h4 { font-size: 11px; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
        .s-box p { font-size: 16px; font-weight: bold; color: #0f172a; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
            font-size: 11px;
        }
        th, td { padding: 8px 8px; text-align: left; border: 1px solid #cbd5e1; }
        th { background-color: #f1f5f9; color: #1e293b; font-weight: 700; text-align: center; text-transform: uppercase; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; display: inline-block; }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #475569;
        }
        .pagination {
            display: flex;
            gap: 5px;
        }
        .pagination a, .pagination span {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
            background: #f8fafc;
        }
        .pagination a:hover {
            background: #e2e8f0;
        }
        .pagination .active {
            background: #0288d1;
            color: white;
            border-color: #0288d1;
        }

        .ttd-section { display: none; }
        .print-kop { display: none; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .modal-box {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        .modal-box h3 { margin-bottom: 15px; font-size: 16px; color: #1e293b; }
        .modal-box select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            outline: none;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        @media print {
            body { background: white !important; padding: 0 !important; }
            .sidebar, .top-section-layout, .form-card, .btn, h2.page-title, .btn-bayarkan, .btn-batal, .btn-cetak-kwitansi, form, .modal-overlay, .pagination-container { display: none !important; }
            .app-container { display: block !important; }
            .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .content { box-shadow: none !important; padding: 0 !important; background: white !important; border-radius: 0 !important; }
            .print-kop { display: block !important; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 15px; text-align: center; }
            .print-kop h2 { margin: 0; font-size: 16px; text-transform: uppercase; }
            .print-kop h1 { margin: 2px 0; font-size: 20px; text-transform: uppercase; }
            .print-kop p { margin: 0; font-size: 11px; font-style: italic; }
            
            <?php if ($cetak_periode !== 'semua'): ?>
            tr.row-data { display: none !important; }
            tr.row-periode-<?php echo md5($cetak_periode); ?> { display: table-row !important; }
            <?php endif; ?>

            table { font-size: 10px !important; }
            th, td { border: 1px solid #000 !important; padding: 4px 5px !important; }
            th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print-col { display: none !important; }
            .ttd-section { display: flex !important; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
            .ttd-box { width: 250px; text-align: center; font-size: 12px; }
            .ttd-space { height: 60px; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <h2 class="page-title">Sisa Hasil Usaha (SHU) Anggota</h2>

        <div class="content">
            <?php if (isset($_SESSION['notif_success'])): ?>
                <div class="alert-success"><?php echo $_SESSION['notif_success']; unset($_SESSION['notif_success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['notif_error'])): ?>
                <div class="alert-error"><?php echo $_SESSION['notif_error']; unset($_SESSION['notif_error']); ?></div>
            <?php endif; ?>

            <div class="print-kop">
                <h2>KOPERASI SERBA USAHA</h2>
                <h1>KOPERASI BAKKEUM</h1>
                <p>Pusat Pelayanan Keuangan Anggota dan Usaha Niaga Terpadu</p>
                <?php if ($cetak_periode !== 'semua'): ?>
                    <p style="margin-top: 5px; font-weight: bold;">Periode Laporan: <?php echo htmlspecialchars($cetak_periode); ?></p>
                <?php else: ?>
                    <p style="margin-top: 5px; font-weight: bold;">Periode Laporan: Keseluruhan Riwayat Tersimpan</p>
                <?php endif; ?>
            </div>

            <div class="top-section-layout">
                <form method="GET" action="laporan_shu.php" class="form-card" id="formFilterParameter">
                    <h3 style="font-size: 15px; margin-bottom: 15px; color: #1e293b;">Pengaturan & Periode Laporan SHU</h3>
                    <div class="form-row">
                        <label>Periode Tanggal Awal:</label>
                        <input type="date" name="tgl_awal" value="<?php echo htmlspecialchars($tgl_awal); ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Periode Tanggal Akhir:</label>
                        <input type="date" name="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Total SHU Bersih Koperasi (Rp):</label>
                        <input type="number" name="total_alokasi_shu" value="<?php echo $total_alokasi_shu; ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Alokasi SHU Anggota (%):</label>
                        <input type="number" step="0.1" name="persen_shu_ang" value="<?php echo $persen_shu_ang; ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Persentase Jasa Modal (%):</label>
                        <input type="number" step="0.1" name="persen_jma" value="<?php echo $persen_jma; ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Persentase Jasa Usaha (%):</label>
                        <input type="number" step="0.1" name="persen_jua" value="<?php echo $persen_jua; ?>" required>
                    </div>
                </form>

                <div class="summary-info">
                    <div class="s-box">
                        <h4>Total SHU Bersih</h4>
                        <p>Rp <?php echo number_format($total_alokasi_shu_cetak, 0, ',', '.'); ?></p>
                    </div>
                    <div class="s-box">
                        <h4>Alokasi SHU Anggota (<?php echo $persen_shu_ang; ?>%)</h4>
                        <p>Rp <?php echo number_format($total_shu_input_cetak, 0, ',', '.'); ?></p>
                    </div>
                    <div class="s-box">
                        <h4>Jasa Modal (<?php echo $persen_jma; ?>% dari Total SHU)</h4>
                        <p>Rp <?php echo number_format($alokasi_jma_cetak, 0, ',', '.'); ?></p>
                    </div>
                    <div class="s-box">
                        <h4>Jasa Usaha (<?php echo $persen_jua; ?>% dari Total SHU)</h4>
                        <p>Rp <?php echo number_format($alokasi_jua_cetak, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 15px; text-align: center;">
                <h3 style="font-size: 15px; text-transform: uppercase; text-decoration: underline;">RIWAYAT PEMBAYARAN & RINCIAN SHU ANGGOTA</h3>
                <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Hanya menampilkan data yang sudah di-update atau dihitung (tersimpan)</p>
            </div>

            <div class="form-card" style="background: #ffffff; border: 2px dashed #0288d1; margin-bottom: 20px;">
                <h3 style="font-size: 14px; margin-bottom: 10px; color: #0288d1;">+ Hitung & Simpan SHU Anggota untuk Periode: <?php echo $periode_aktif; ?></h3>
                <p style="font-size: 11px; color: #64748b; margin-bottom: 12px;">Pilih anggota atau pilih "Semua Anggota" untuk menghitung dan menyimpan data SHU (status awal: Belum Dibayar).</p>
                <form method="POST" action="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>">
                    <input type="hidden" name="simpan_hitung_shu" value="1">
                    <input type="hidden" name="periode_target" value="<?php echo htmlspecialchars($periode_aktif); ?>">
                    
                    <input type="hidden" name="total_alokasi_shu" value="<?php echo $total_alokasi_shu; ?>">
                    <input type="hidden" name="persen_shu_ang" value="<?php echo $persen_shu_ang; ?>">
                    <input type="hidden" name="persen_jma" value="<?php echo $persen_jma; ?>">
                    <input type="hidden" name="persen_jua" value="<?php echo $persen_jua; ?>">

                    <div class="form-row">
                        <label>Pilih Anggota:</label>
                        <select name="id_anggota_shu" id="selectAnggotaHitung" onchange="hitungOtomatisForm(this)" required>
                            <option value="">-- Pilih Anggota --</option>
                            <option value="-1" data-nama="SEMUA ANGGOTA">-- Semua Anggota --</option>
                            <?php foreach ($master_anggota as $id_agt => $agt): 
                                $jma_calc = ($agt['total_simpanan'] / ($grand_total_simpanan > 0 ? $grand_total_simpanan : 1)) * ($persen_jma / 100) * $total_alokasi_shu;
                                $jua_calc = ($agt['total_transaksi'] / ($grand_total_transaksi > 0 ? $grand_total_transaksi : 1)) * ($persen_jua / 100) * $total_alokasi_shu;
                                $tot_calc = $jma_calc + $jua_calc;
                            ?>
                                <option value="<?php echo $id_agt; ?>" 
                                    data-nama="<?php echo htmlspecialchars($agt['nama'], ENT_QUOTES); ?>"
                                    data-jma="<?php echo $jma_calc; ?>"
                                    data-jua="<?php echo $jua_calc; ?>"
                                    data-total="<?php echo $tot_calc; ?>">
                                    <?php echo htmlspecialchars($agt['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="nama_anggota_shu" id="inputNamaAnggota">
                    </div>
                    <div class="form-row">
                        <label>JMA (Jasa Modal) (Rp):</label>
                        <input type="number" step="any" name="jumlah_jma_hitung" id="inputJmaHitung" readonly required style="background: #f1f5f9;">
                    </div>
                    <div class="form-row">
                        <label>JUA (Jasa Usaha) (Rp):</label>
                        <input type="number" step="any" name="jumlah_jua_hitung" id="inputJuaHitung" readonly required style="background: #f1f5f9;">
                    </div>
                    <div class="form-row">
                        <label>Total SHU (Rp):</label>
                        <input type="number" step="any" name="jumlah_shu_bayar" id="inputTotalHitung" readonly required style="background: #f1f5f9; font-weight: bold; color: #1e293b;">
                    </div>
                    
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary" onclick="submitDuaForm(event)">💾 Simpan / Hitung Data SHU</button>
                        <button type="button" onclick="bukaModalCetak()" class="btn btn-print">🖨️ Cetak Laporan SHU</button>
                    </div>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;">No</th>
                        <th>Nama Anggota</th>
                        <th>Periode</th>
                        <th>Simpanan Anggota</th>
                        <th>JMA (Jasa Modal)</th>
                        <th>Volume Transaksi</th>
                        <th>JUA (Jasa Usaha)</th>
                        <th>Total SHU Diterima</th>
                        <th>Jumlah Dibayar</th>
                        <th>Tgl Pembayaran</th>
                        <th>Status Pembayaran</th>
                        <th class="no-print-col" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = $start_index + 1;
                    $tot_shu_all = 0; 
                    $tot_dibayar_all = 0; 
                    $tot_simp_all = 0;
                    $tot_jma_all = 0;
                    $tot_trans_all = 0;
                    $tot_jua_all = 0;

                    foreach ($data_riwayat_shu as $row) {
                        if ($cetak_periode === 'semua' || $row['periode'] === $cetak_periode) {
                            $tot_simp_all += $row['simpanan'];
                            $tot_jma_all += $row['jma'];
                            $tot_trans_all += $row['transaksi'];
                            $tot_jua_all += $row['jua'];
                            $tot_shu_all += $row['total_shu'];
                            if ($row['status_db'] === 'Lunas Terbayar') {
                                $tot_dibayar_all += $row['sudah_dibayar'];
                            }
                        }
                    }

                    if (empty($paginated_data)) {
                        echo '<tr><td colspan="12" class="text-center" style="padding: 20px; color: #64748b; font-style: italic;">Belum ada riwayat SHU yang di-update atau dihitung. Silakan lakukan penyimpanan data melalui panel hitung di atas.</td></tr>';
                    } else {
                        foreach ($paginated_data as $row) {
                            $total_shu_terima = $row['total_shu'];
                            $is_lunas = ($row['status_db'] === 'Lunas Terbayar');
                            
                            $sudah_dibayar_nominal = $is_lunas ? $row['sudah_dibayar'] : 0;
                            $tgl_tampil = ($is_lunas && !empty($row['tgl_pembayaran'])) ? date('d/m/Y H:i', strtotime($row['tgl_pembayaran'])) : '-';
                            $class_periode = 'row-data row-periode-' . md5($row['periode']);
                    ?>
                    <tr class="<?php echo $class_periode; ?>">
                        <td class="text-center"><?php echo $no++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                        <td class="text-center"><?php echo htmlspecialchars($row['periode']); ?></td>
                        <td class="text-right">Rp <?php echo number_format($row['simpanan'], 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #00796b;">Rp <?php echo number_format($row['jma'], 0, ',', '.'); ?></td>
                        <td class="text-right">Rp <?php echo number_format($row['transaksi'], 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #00796b;">Rp <?php echo number_format($row['jua'], 0, ',', '.'); ?></td>
                        <td class="text-right" style="font-weight: bold; color: #00796b;">
                            Rp <?php echo number_format($total_shu_terima, 0, ',', '.'); ?>
                        </td>
                        <td class="text-right" style="font-weight: bold; color: #155724;">
                            <?php if ($is_lunas): ?>
                                Rp <?php echo number_format($sudah_dibayar_nominal, 0, ',', '.'); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $tgl_tampil; ?></td>
                        <td class="text-center">
                            <?php if ($is_lunas): ?>
                                <span class="badge" style="background-color: #d4edda; color: #155724;">Lunas</span>
                            <?php else: ?>
                                <span class="badge" style="background-color: #fef3c7; color: #b45309;">Belum Dibayar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center no-print-col">
                            <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                                <?php if (!$is_lunas): ?>
                                    <!-- Kolom Aksi Sesuai Gambar 1: Tombol Bayarkan dan tombol hapus silang merah -->
                                    <form method="POST" action="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>" style="margin: 0;">
                                        <input type="hidden" name="id_anggota_shu" value="<?php echo $row['id_anggota']; ?>">
                                        <input type="hidden" name="periode_target" value="<?php echo htmlspecialchars($row['periode']); ?>">
                                        <button type="submit" name="bayarkan_shu_aksi" class="btn btn-bayarkan" title="Bayarkan SHU">Bayarkan</button>
                                    </form>
                                    <form method="POST" action="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>" style="margin: 0;">
                                        <input type="hidden" name="id_anggota_shu" value="<?php echo $row['id_anggota']; ?>">
                                        <input type="hidden" name="periode_target" value="<?php echo htmlspecialchars($row['periode']); ?>">
                                        <input type="submit" name="batal_bayar_shu" value="❌" title="Hapus Data" style="padding: 5px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px;">
                                    </form>
                                <?php else: ?>
                                    <!-- Kolom Aksi Sesuai Gambar 1: Tombol cetak kwitansi biru & tombol batal/hapus silang merah -->
                                    <a href="cetak_bukti_shu.php?id=<?php echo $row['id_anggota']; ?>&tgl=<?php echo urlencode($row['tgl_pembayaran']); ?>" target="_blank" class="btn btn-cetak-kwitansi" title="Cetak Bukti Kwitansi">🖨️</a>
                                    <form method="POST" action="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>" style="margin: 0;">
                                        <input type="hidden" name="id_anggota_shu" value="<?php echo $row['id_anggota']; ?>">
                                        <input type="hidden" name="periode_target" value="<?php echo htmlspecialchars($row['periode']); ?>">
                                        <input type="submit" name="batal_bayar_shu" value="❌" title="Batalkan & Hapus" style="padding: 5px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px;">
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>                
                    </tr>
                    <?php 
                        }
                    } 
                    ?>
                </tbody>
                <?php if (!empty($data_riwayat_shu)): ?>
                <tfoot>
                    <tr style="background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="3" class="text-center">TOTAL KESELURUHAN</td>
                        <td class="text-right">Rp <?php echo number_format($tot_simp_all, 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #00796b;">Rp <?php echo number_format($tot_jma_all, 0, ',', '.'); ?></td>
                        <td class="text-right">Rp <?php echo number_format($tot_trans_all, 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #00796b;">Rp <?php echo number_format($tot_jua_all, 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #00796b;">Rp <?php echo number_format($tot_shu_all, 0, ',', '.'); ?></td>
                        <td class="text-right" style="color: #155724;">Rp <?php echo number_format($tot_dibayar_all, 0, ',', '.'); ?></td>
                        <td colspan="2" class="text-center"></td>
                        <td class="no-print-col"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container no-print-col">
                <div>Menampilkan halaman <strong><?php echo $page; ?></strong> dari <strong><?php echo $total_pages; ?></strong> (Total <?php echo $total_data; ?> data)</div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>&page=<?php echo ($page - 1); ?>&total_alokasi_shu=<?php echo $total_alokasi_shu; ?>&persen_shu_ang=<?php echo $persen_shu_ang; ?>&persen_jma=<?php echo $persen_jma; ?>&persen_jua=<?php echo $persen_jua; ?>">« Sebelumnya</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>&page=<?php echo $i; ?>&total_alokasi_shu=<?php echo $total_alokasi_shu; ?>&persen_shu_ang=<?php echo $persen_shu_ang; ?>&persen_jma=<?php echo $persen_jma; ?>&persen_jua=<?php echo $persen_jua; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="laporan_shu.php?tgl_awal=<?php echo $tgl_awal; ?>&tgl_akhir=<?php echo $tgl_akhir; ?>&page=<?php echo ($page + 1); ?>&total_alokasi_shu=<?php echo $total_alokasi_shu; ?>&persen_shu_ang=<?php echo $persen_shu_ang; ?>&persen_jma=<?php echo $persen_jma; ?>&persen_jua=<?php echo $persen_jua; ?>">Selanjutnya »</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="ttd-section">
                <div class="ttd-box">
                    <p>Mengetahui,<br><strong>Ketua Koperasi</strong></p>
                    <div class="ttd-space"></div>
                    <p><strong>( H. Ahmad Subagja )</strong></p>
                </div>
                <div class="ttd-box">
                    <p>Tanggal: <?php echo date('d-m-Y'); ?><br><strong>Bendahara / Kasir</strong></p>
                    <div class="ttd-space"></div>
                    <p><strong>( Administrator )</strong></p>
                </div>
            </div>

        </div>
    </main>
</div>

<div id="modalCetak" class="modal-overlay">
    <div class="modal-box">
        <h3>Pilih Periode Laporan yang Ingin Dicetak</h3>
        <select id="pilihanPeriodeCetak">
            <option value="semua">-- Cetak Semua Periode --</option>
            <?php foreach ($list_periode as $p_opt): ?>
                <option value="<?php echo htmlspecialchars($p_opt); ?>"><?php echo htmlspecialchars($p_opt); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="modal-buttons">
            <button type="button" onclick="tutupModalCetak()" class="btn" style="background: #cbd5e1; color: #1e293b;">Batal</button>
            <button type="button" onclick="prosesCetak()" class="btn btn-print">Lanjutkan Cetak</button>
        </div>
    </div>
</div>

<script>
function hitungOtomatisForm(selectObj) {
    let selectedOption = selectObj.options[selectObj.selectedIndex];
    if (selectedOption.value === "-1") {
        document.getElementById('inputJmaHitung').value = "0.00";
        document.getElementById('inputJuaHitung').value = "0.00";
        document.getElementById('inputTotalHitung').value = "0.00";
        document.getElementById('inputNamaAnggota').value = "SEMUA ANGGOTA";
    } else if (selectedOption.value) {
        let jma = parseFloat(selectedOption.getAttribute('data-jma')) || 0;
        let jua = parseFloat(selectedOption.getAttribute('data-jua')) || 0;
        let total = parseFloat(selectedOption.getAttribute('data-total')) || 0;
        let nama = selectedOption.getAttribute('data-nama') || '';

        document.getElementById('inputJmaHitung').value = jma.toFixed(2);
        document.getElementById('inputJuaHitung').value = jua.toFixed(2);
        document.getElementById('inputTotalHitung').value = total.toFixed(2);
        document.getElementById('inputNamaAnggota').value = nama;
    } else {
        document.getElementById('inputJmaHitung').value = '';
        document.getElementById('inputJuaHitung').value = '';
        document.getElementById('inputTotalHitung').value = '';
        document.getElementById('inputNamaAnggota').value = '';
    }
}

function bukaModalCetak() {
    document.getElementById('modalCetak').style.display = 'flex';
}
function tutupModalCetak() {
    document.getElementById('modalCetak').style.display = 'none';
}
function prosesCetak() {
    let periodePilihan = document.getElementById('pilihanPeriodeCetak').value;
    let currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('cetak_periode', periodePilihan);
    window.location.href = currentUrl.toString();
}

function submitDuaForm(event) {
    event.preventDefault();
    let formFilter = document.getElementById('formFilterParameter');
    let formHitung = event.target.closest('form');

    let tglAwal = formFilter.querySelector('[name="tgl_awal"]').value;
    let tglAkhir = formFilter.querySelector('[name="tgl_akhir"]').value;
    let totalAlokasi = formFilter.querySelector('[name="total_alokasi_shu"]').value;
    let persenShuAng = formFilter.querySelector('[name="persen_shu_ang"]').value;
    let persenJma = formFilter.querySelector('[name="persen_jma"]').value;
    let persenJua = formFilter.querySelector('[name="persen_jua"]').value;

    formHitung.querySelector('[name="total_alokasi_shu"]').value = totalAlokasi;
    formHitung.querySelector('[name="persen_shu_ang"]').value = persenShuAng;
    formHitung.querySelector('[name="persen_jma"]').value = persenJma;
    formHitung.querySelector('[name="persen_jua"]').value = persenJua;

    formHitung.action = "laporan_shu.php?tgl_awal=" + encodeURIComponent(tglAwal) + 
                        "&tgl_akhir=" + encodeURIComponent(tglAkhir) + 
                        "&total_alokasi_shu=" + encodeURIComponent(totalAlokasi) + 
                        "&persen_shu_ang=" + encodeURIComponent(persenShuAng) + 
                        "&persen_jma=" + encodeURIComponent(persenJma) + 
                        "&persen_jua=" + encodeURIComponent(persenJua);
    
    formHitung.submit();
}

window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('cetak_periode')) {
        window.print();
    }
};
</script>

</body>
</html>