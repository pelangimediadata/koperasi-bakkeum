<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

// Helper function untuk cek keberadaan kolom di SQLite
function getExistingColumn($koneksi, $tabel, $candidates) {
    $stmt = $koneksi->query("PRAGMA table_info(`$tabel`)");
    if ($stmt) {
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = strtolower($row['name']);
        }
        foreach ($candidates as $cand) {
            if (in_array(strtolower($cand), $columns)) return "`$cand`";
        }
    }
    return NULL;
}

// Helper function untuk cek keberadaan tabel di SQLite
function tableExists($koneksi, $tableName) {
    $stmt = $koneksi->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $stmt->execute([$tableName]);
    return ($stmt->fetch() !== false);
}

// Ambil data dari parameter URL (GET)
$id_anggota_shu      = intval($_GET['id'] ?? 0);
$jumlah_shu_tambahan = round(floatval($_GET['jumlah'] ?? 0)); 
$nama_anggota_shu    = urldecode($_GET['nama'] ?? '');
$tahun_periode       = date('Y');

if ($id_anggota_shu > 0 && $jumlah_shu_tambahan > 0) {
    // Pastikan tabel shu_pembayaran ada (menggunakan AUTOINCREMENT SQLite)
    $koneksi->exec("CREATE TABLE IF NOT EXISTS shu_pembayaran (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        id_anggota INTEGER NOT NULL,
        periode_tahun TEXT DEFAULT '$tahun_periode',
        jumlah_dibayar NUMERIC NOT NULL,
        tanggal_dibayar DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'Sudah Dibayarkan'
    )");

    $stmt_cek = $koneksi->prepare("SELECT jumlah_dibayar FROM shu_pembayaran WHERE id_anggota = ? AND periode_tahun = ? LIMIT 1");
    $stmt_cek->execute([$id_anggota_shu, $tahun_periode]);
    $cek_exist = $stmt_cek->fetch();
    
    if (!$cek_exist) {
        $stmt_ins = $koneksi->prepare("INSERT INTO shu_pembayaran (id_anggota, periode_tahun, jumlah_dibayar, status) VALUES (?, ?, ?, 'Sudah Dibayarkan')");
        $stmt_ins->execute([$id_anggota_shu, $tahun_periode, $jumlah_shu_tambahan]);
    } else {
        $total_baru_terbayar = floatval($cek_exist['jumlah_dibayar']) + $jumlah_shu_tambahan;
        $stmt_upd = $koneksi->prepare("UPDATE shu_pembayaran SET jumlah_dibayar = ?, tanggal_dibayar = CURRENT_TIMESTAMP WHERE id_anggota = ? AND periode_tahun = ?");
        $stmt_upd->execute([$total_baru_terbayar, $id_anggota_shu, $tahun_periode]);
    }

    // Catat ke pengeluaran koperasi agar masuk laporan kas
    $tabel_pengeluaran_ada = tableExists($koneksi, 'pengeluaran');
    $ket_keluar = "Pembayaran SHU Anggota: " . $nama_anggota_shu;
    $tgl_sekarang = date('Y-m-d');

    if ($tabel_pengeluaran_ada) {
        $col_ket = getExistingColumn($koneksi, 'pengeluaran', ['keterangan', 'deskripsi', 'nama_pengeluaran']);
        $col_jml = getExistingColumn($koneksi, 'pengeluaran', ['jumlah', 'nominal', 'total']);
        $col_tgl = getExistingColumn($koneksi, 'pengeluaran', ['tanggal', 'tgl']);
        
        if ($col_ket && $col_jml) {
            $sql_ins_pengeluaran = "INSERT INTO pengeluaran ($col_ket, $col_jml" . ($col_tgl ? ", $col_tgl" : "") . ") VALUES (?, ?" . ($col_tgl ? ", ?" : "") . ")";
            $stmt_ins_p = $koneksi->prepare($sql_ins_pengeluaran);
            if ($col_tgl) {
                $stmt_ins_p->execute([$ket_keluar, $jumlah_shu_tambahan, $tgl_sekarang]);
            } else {
                $stmt_ins_p->execute([$ket_keluar, $jumlah_shu_tambahan]);
            }
        }
    } else {
        $koneksi->exec("CREATE TABLE IF NOT EXISTS pengeluaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT,
            keterangan TEXT,
            jumlah NUMERIC
        )");
        $stmt_peng = $koneksi->prepare("INSERT INTO pengeluaran (tanggal, keterangan, jumlah) VALUES (?, ?, ?)");
        $stmt_peng->execute([$tgl_sekarang, $ket_keluar, $jumlah_shu_tambahan]);
    }
    
    $_SESSION['notif_success'] = "Pembayaran SHU untuk anggota $nama_anggota_shu berhasil diproses!";
} else {
    $_SESSION['notif_error'] = "Gagal memproses pembayaran SHU. Nominal tidak valid.";
}

header("Location: laporan_shu.php");
exit();