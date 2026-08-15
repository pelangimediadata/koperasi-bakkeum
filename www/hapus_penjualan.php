<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

if (!isset($_SESSION['login_admin'])) {
    header("Location: login.php");
    exit();
}

$is_admin = (isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin') || (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');
if (!$is_admin) {
    header("Location: toko.php");
    exit();
}

if (isset($_GET['id'])) {
    $id_penjualan = $_GET['id'];
    
    $stmt_jual = $koneksi->prepare("SELECT p.*, b.harga_jual, b.harga_beli FROM penjualan p LEFT JOIN barang b ON p.id_barang = b.id_barang WHERE p.id_penjualan = ?");
    $stmt_jual->execute([$id_penjualan]);
    $d_jual = $stmt_jual->fetch();
    
    if ($d_jual) {
        $id_barang_jual = $d_jual['id_barang'];
        $jumlah_terjual = (int) $d_jual['jumlah'];
        
        $margin_satuan = ($d_jual['harga_jual'] ?? 0) - ($d_jual['harga_beli'] ?? 0);
        $total_margin_batal = $margin_satuan * $jumlah_terjual;

        // Kembalikan stok
        $stmt_upd = $koneksi->prepare("UPDATE barang SET stok = stok + ? WHERE id_barang = ?");
        $stmt_upd->execute([$jumlah_terjual, $id_barang_jual]);

        // Cek tabel kas menggunakan SQLite sqlite_master
        $stmt_chk = $koneksi->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='kas'");
        $stmt_chk->execute();
        $check_kas = $stmt_chk->fetch();

        if ($check_kas && $total_margin_batal > 0) {
            $tgl_sekarang = date('Y-m-d');
            $keterangan_batal = "Pembatalan Margin Penjualan ID: " . $id_penjualan;
            $stmt_kas = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, 'Keluar', ?, ?)");
            $stmt_kas->execute([$tgl_sekarang, $total_margin_batal, $keterangan_batal]);
        }

        // Hapus transaksi penjualan
        $stmt_del = $koneksi->prepare("DELETE FROM penjualan WHERE id_penjualan = ?");
        $stmt_del->execute([$id_penjualan]);
    }
}

header("Location: toko.php");
exit();
?>