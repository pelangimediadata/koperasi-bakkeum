<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

$id_penjualan = $_GET['id'] ?? null;
$tanggal_param = $_GET['tanggal'] ?? null;
$id_anggota_param = $_GET['id_anggota'] ?? null;

if (!$id_penjualan && !$tanggal_param) {
    die("Nota tidak ditemukan.");
}

if ($id_penjualan) {
    $stmt_cek = $koneksi->prepare("SELECT * FROM penjualan WHERE id_penjualan = ?");
    $stmt_cek->execute([$id_penjualan]);
    $trx_ini = $stmt_cek->fetch(PDO::FETCH_ASSOC);
    if (!$trx_ini) die("Data transaksi tidak ditemukan.");
    $tanggal_param = $trx_ini['tanggal_transaksi'];
    $id_anggota_param = $trx_ini['id_anggota'];
    $nama_umum_param = $trx_ini['nama_umum'];
}

// Ambil semua item pada tanggal dan pembeli yang sama
if (!empty($id_anggota_param) && $id_anggota_param !== 'umum') {
    $stmt_items = $koneksi->prepare("
        SELECT p.*, b.nama_barang, b.harga_beli, a.nama AS nama_anggota 
        FROM penjualan p
        LEFT JOIN barang b ON p.id_barang = b.id_barang
        LEFT JOIN anggota a ON p.id_anggota = a.id
        WHERE p.tanggal_transaksi = ? AND p.id_anggota = ?
    ");
    $stmt_items->execute([$tanggal_param, $id_anggota_param]);
} else {
    $stmt_items = $koneksi->prepare("
        SELECT p.*, b.nama_barang, b.harga_beli, a.nama AS nama_anggota 
        FROM penjualan p
        LEFT JOIN barang b ON p.id_barang = b.id_barang
        LEFT JOIN anggota a ON p.id_anggota = a.id
        WHERE p.tanggal_transaksi = ? AND (p.nama_umum = ? OR (p.id_anggota IS NULL AND ? = 'Masyarakat Umum'))
    ");
    $nama_pencarian = $nama_umum_param ?? 'Masyarakat Umum';
    $stmt_items->execute([$tanggal_param, $nama_pencarian, $nama_pencarian]);
}

$semua_item = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
if (empty($semua_item)) die("Item belanja tidak ditemukan.");

$first_item = $semua_item[0];
$nama_pembeli = (!empty($first_item['id_anggota']) && !empty($first_item['nama_anggota'])) 
    ? $first_item['nama_anggota'] . " (Anggota)" 
    : (!empty($first_item['nama_umum']) ? $first_item['nama_umum'] : "Masyarakat Umum");

// Hitung total belanja & total margin keuntungan maksimal
$grand_total = 0;
$total_margin_seluruh = 0;
foreach ($semua_item as $it) {
    $grand_total += $it['total_harga'];
    $total_margin_seluruh += ($it['harga_satuan'] - $it['harga_beli']) * $it['jumlah'];
}

// Hitung sisa piutang saat ini (jika sudah pernah bayar sebagian)
$sisa_piutang_saat_ini = $grand_total;
$status_saat_ini = $first_item['status_bayar'] ?? 'BELUM BAYAR / TAGIHAN';
if ($status_saat_ini === 'BAYAR SEBAGIAN') {
    $sisa_piutang_saat_ini = $grand_total - (float)$first_item['jumlah_bayar'];
}

// PROSES KETIKA TOMBOL BAYAR DIKLIK (MEMPENGARUHI KAS & PIUTANG)
$pesan_sukses = "";
$pesan_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_bayar'])) {
    $nominal_bayar_input = (float)($_POST['nominal_bayar'] ?? 0);

    // VALIDASI: Nominal tidak boleh melebihi sisa piutang / total belanja
    if ($nominal_bayar_input > $grand_total) {
        $pesan_error = "Gagal! Nominal pembayaran (Rp " . number_format($nominal_bayar_input, 0, ',', '.') . ") tidak boleh melebihi total belanja / piutang (Rp " . number_format($grand_total, 0, ',', '.') . ").";
    } else {
        if ($nominal_bayar_input >= $grand_total) {
            $status_teks = 'LUNAS';
            $jumlah_dibayar = $grand_total;
            $jumlah_masuk_kas = $total_margin_seluruh; // Keuntungan masuk kas
        } elseif ($nominal_bayar_input > 0) {
            $status_teks = 'BAYAR SEBAGIAN';
            $jumlah_dibayar = $nominal_bayar_input;
            // Proporsi kas masuk dari nominal yang dibayar
            $jumlah_masuk_kas = ($grand_total > 0) ? ($nominal_bayar_input / $grand_total) * $total_margin_seluruh : 0;
        } else {
            $status_teks = 'BELUM BAYAR / TAGIHAN';
            $jumlah_dibayar = 0;
            $jumlah_masuk_kas = 0;
        }

        // Update status bayar & jumlah bayar ke semua item penjualan terkait
        foreach ($semua_item as $it) {
            $stmt_upd = $koneksi->prepare("UPDATE penjualan SET status_bayar = ?, jumlah_bayar = ? WHERE id_penjualan = ?");
            $stmt_upd->execute([$status_teks, $jumlah_dibayar, $it['id_penjualan']]);
        }

        // Catat ke Kas jika ada uang masuk dari tombol bayar nota
        if ($jumlah_masuk_kas > 0) {
            $ket_kas = "Pembayaran Penjualan Toko ($status_teks) - Pembeli: $nama_pembeli";
            $stmt_kas = $koneksi->prepare("INSERT INTO kas (tanggal, jenis, jumlah, keterangan) VALUES (?, 'Masuk', ?, ?)");
            $stmt_kas->execute([$tanggal_param, $jumlah_masuk_kas, $ket_kas]);
        }

        $pesan_sukses = "Pembayaran berhasil diproses, piutang/tagihan diperbarui, dan kas dicatat!";
        
        // Refresh data item terbaru
        $stmt_items->execute();
        $semua_item = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        $first_item = $semua_item[0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi - Koperasi Bakkeum</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; background: #f4f6f9; margin: 20px; }
        .nota-container { width: 320px; margin: 0 auto; padding: 20px; background: #fff; border: 1px dashed #333; box-shadow: 0 4px 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .center { text-align: center; }
        .right { text-align: right; }
        .line { border-bottom: 1px dashed #333; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 4px 0; font-size: 12px; }
        
        .action-panel { width: 320px; margin: 20px auto; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .btn { padding: 10px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; text-align: center; font-size: 12px; color: white; width: 100%; display: block; text-decoration: none; box-sizing: border-box; }
        .btn-bayar { background: #28a745; margin-top: 8px; }
        .btn-print { background: #007bff; margin-top: 8px; }
        .btn-keluar { background: #dc3545; margin-top: 8px; } /* Tombol Keluar berwarna merah */
        .alert { background: #d4edda; color: #155724; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 11px; text-align: center; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 11px; text-align: center; border: 1px solid #f5c6cb; }
        @media print {
            .action-panel { display: none; }
            body { background: #fff; margin: 0; }
            .nota-container { border: none; box-shadow: none; width: 100%; padding: 0; }
        }
    </style>
</head>
<body>

<div class="nota-container">
    <div class="center">
        <strong>KOPERASI BAKKEUM</strong><br>
        <span style="font-size: 11px;">Nota Transaksi Penjualan Toko</span>
    </div>
    
    <div class="line"></div>
    
    <div>
        Tanggal : <?php echo date('d-m-Y', strtotime($tanggal_param)); ?><br>
        Pembeli : <strong><?php echo htmlspecialchars($nama_pembeli); ?></strong><br>
        Status  : <strong><?php echo htmlspecialchars($first_item['status_bayar'] ?? 'BELUM BAYAR / TAGIHAN'); ?></strong>
    </div>
    
    <div class="line"></div>
    
    <table>
        <tr>
            <th style="text-align: left;">Item / Barang</th>
            <th class="center">Qty</th>
            <th class="right">Subtotal</th>
        </tr>
        <?php foreach ($semua_item as $item) { ?>
        <tr>
            <td colspan="3" style="padding-top: 6px;">
                <strong><?php echo htmlspecialchars($item['nama_barang']); ?></strong><br>
                <span style="font-size: 11px; color: #555;">@ Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="center"><?php echo $item['jumlah']; ?></td>
            <td class="right">Rp <?php echo number_format($item['total_harga'], 0, ',', '.'); ?></td>
        </tr>
        <?php } ?>
    </table>
    
    <div class="line"></div>
    
    <table>
        <tr>
            <td><strong>TOTAL:</strong></td>
            <td class="right"><strong>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
        </tr>
        <?php if (($first_item['status_bayar'] ?? '') === 'BAYAR SEBAGIAN'): ?>
        <tr>
            <td>Dibayar:</td>
            <td class="right">Rp <?php echo number_format($first_item['jumlah_bayar'], 0, ',', '.'); ?></td>
        </tr>
        <tr>
            <td><strong>Sisa Piutang:</strong></td>
            <td class="right"><strong>Rp <?php echo number_format($grand_total - $first_item['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
        </tr>
        <?php elseif (($first_item['status_bayar'] ?? '') === 'BELUM BAYAR / TAGIHAN' || empty($first_item['status_bayar'])): ?>
        <tr>
            <td><strong>Sisa Tagihan:</strong></td>
            <td class="right"><strong>Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></strong></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <div class="line"></div>
    
    <div class="center" style="font-size: 11px;">
        Terima kasih atas kunjungan Anda.<br>
        -- Simpan nota ini sebagai bukti belanja --
    </div>
</div>

<!-- PANEL AKSI & PEMBAYARAN -->
<div class="action-panel">
    <?php if (!empty($pesan_sukses)): ?>
        <div class="alert"><?php echo $pesan_sukses; ?></div>
    <?php endif; ?>

    <?php if (!empty($pesan_error)): ?>
        <div class="alert-error"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div style="background: #fdfdfe; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <label style="font-size: 11px; font-weight: bold; color: #555;">Masukkan Nominal Uang Pembayaran (Rp):</label>
            <input type="number" name="nominal_bayar" value="<?php echo ($first_item['jumlah_bayar'] > 0) ? $first_item['jumlah_bayar'] : $grand_total; ?>" placeholder="0" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; font-weight: bold; box-sizing: border-box;" min="0" max="<?php echo $grand_total; ?>" required>
            <div style="font-size: 10px; color: #666; margin-top: 4px; margin-bottom: 8px;">* Nominal tidak boleh melebihi total belanja (Maks: Rp <?php echo number_format($grand_total, 0, ',', '.'); ?>).</div>
            
            <button type="submit" name="proses_bayar" class="btn btn-bayar">💾 Bayar</button>
        </div>
    </form>

    <button onclick="window.print()" class="btn btn-print">🖨️ Cetak / Print Nota</button>
    
    <!-- Tombol Keluar (Menutup halaman atau kembali ke toko jika tab tidak bisa ditutup) -->
    <button onclick="window.close()" class="btn btn-keluar">❌ Keluar</button>
</div>

</body>
</html>