<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['login_admin'])) { 
    header("Location: login.php"); 
    exit(); 
}

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    echo "<script>alert('Akses ditolak! Petugas tidak memiliki izin untuk menghapus data.'); window.location='bayar.php';</script>";
    exit();
}

include __DIR__ . "/api/koneksi.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Cek data pembayaran dengan fleksibilitas kolom primary key menggunakan PDO[cite: 24]
    $stmt_b = $koneksi->prepare("SELECT * FROM pembayaran WHERE id_bayar = ? OR id = ? OR no_bayar = ?");
    $stmt_b->execute([$id, $id, $id]);
    $data_b = $stmt_b->fetch();
    
    if ($data_b) {
        $no_pinjaman  = $data_b['no_pinjaman'] ?? '';
        $jumlah_bayar = (int) ($data_b['jumlah_bayar'] ?? 0);
        $jenis_bayar  = $data_b['jenis_bayar'] ?? '';

        if (!empty($no_pinjaman)) {
            $stmt_p = $koneksi->prepare("SELECT * FROM pinjaman WHERE no_pinjaman = ?");
            $stmt_p->execute([$no_pinjaman]);
            $data_p = $stmt_p->fetch();

            if ($data_p) {
                if ($jenis_bayar !== 'Bunga Saja' && $jumlah_bayar > 0) {
                    $sisa_lama = (int) ($data_p['sisa_pinjaman'] ?? $data_p['jumlah_pinjaman'] ?? 0);
                    $sisa_baru = $sisa_lama + $jumlah_bayar;
                    
                    $stmt_upd = $koneksi->prepare("UPDATE pinjaman SET sisa_pinjaman = ?, status = 'Berjalan' WHERE no_pinjaman = ?");
                    $stmt_upd->execute([$sisa_baru, $no_pinjaman]);
                }
            }
        }

        // Hapus data pembayaran berdasarkan kolom yang tersedia
        if (isset($data_b['id_bayar'])) {
            $stmt_del = $koneksi->prepare("DELETE FROM pembayaran WHERE id_bayar = ?");
            $stmt_del->execute([$id]);
        } elseif (isset($data_b['id'])) {
            $stmt_del = $koneksi->prepare("DELETE FROM pembayaran WHERE id = ?");
            $stmt_del->execute([$id]);
        } else {
            $stmt_del = $koneksi->prepare("DELETE FROM pembayaran WHERE no_bayar = ?");
            $stmt_del->execute([$id]);
        }
    }
}

header("Location: bayar.php");
exit();
?>