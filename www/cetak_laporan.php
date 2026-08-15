<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . "/api/koneksi.php";

$jenis      = $_GET['jenis'] ?? 'semua';
$id_anggota = $_GET['id_anggota'] ?? 'semua';
$tgl_cetak  = date('d-m-Y H:i:s');

// Filter Nama Anggota untuk Judul
$nama_anggota_filter = "Semua Anggota";
if ($id_anggota !== 'semua') {
    $q_filter = mysqli_query($koneksi, "SELECT nama FROM anggota WHERE id = '$id_anggota'");
    if ($d_filter = mysqli_fetch_assoc($q_filter)) {
        $nama_anggota_filter = $d_filter['nama'];
    }
}

// Fungsi Pengecekan Status Pinjaman (Lunas, Macet, atau Berjalan)
function getStatusPinjaman($row, $koneksi) {
    $jumlah_pinjaman = (float) $row['jumlah_pinjaman'];
    $bunga_persen = (float) ($row['bunga'] ?? 0);
    $tenor = (int) ($row['tenor'] ?? $row['lama_angsuran'] ?? 1);
    $no_pinjaman = $row['no_pinjaman'];

    // Hitung Total Bayar
    $q_pay = mysqli_query($koneksi, "SELECT COALESCE(SUM(jumlah_bayar),0) AS total_pokok, COALESCE(SUM(bayar_bunga),0) AS total_bunga, MAX(tanggal) AS tgl_terakhir FROM pembayaran WHERE no_pinjaman = '$no_pinjaman'");
    $d_pay = mysqli_fetch_assoc($q_pay);
    
    $total_terbayar = (float)$d_pay['total_pokok'] + (float)$d_pay['total_bunga'];
    $total_kewajiban = $jumlah_pinjaman + (($jumlah_pinjaman * $bunga_persen / 100) * $tenor);

    if ($total_terbayar >= $total_kewajiban || strtolower($row['status']) === 'lunas') {
        return '<span style="color: green; font-weight: bold;">Lunas</span>';
    }

    // Cek Tanggal Terakhir Bayar / Tanggal Pinjam
    $tgl_terakhir = $d_pay['tgl_terakhir'] ?? $row['tgl_pinjam'] ?? $row['tanggal'] ?? date('Y-m-d');
    $selisih_hari = (time() - strtotime($tgl_terakhir)) / (60 * 60 * 24);

    // Jika >= 90 Hari (3 Bulan) tidak ada pembayaran
    if ($selisih_hari >= 90) {
        return '<span style="color: red; font-weight: bold;">Macet (≥3 Bkn Tidak Bayar)</span>';
    }

    return '<span style="color: #b8860b; font-weight: bold;">Berjalan</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - <?php echo $nama_anggota_filter; ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #044b3b; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #044b3b; text-transform: uppercase; }
        .header p { margin: 4px 0 0; color: #555; }
        .meta-info { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: bold; background: #f9f9f9; padding: 8px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        table, th, td { border: 1px solid #999; }
        th { background-color: #044b3b; color: #ffffff; padding: 8px; text-align: left; }
        td { padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background-color: #e2f0ed; }
        .section-title { font-size: 14px; font-weight: bold; color: #044b3b; margin-top: 15px; margin-bottom: 8px; text-transform: uppercase; border-left: 4px solid #044b3b; padding-left: 8px; }
        .ttd-container { margin-top: 30px; float: right; text-align: center; width: 200px; }
        .ttd-space { height: 60px; }
        @media print { body { margin: 0; } @page { size: auto; margin: 15mm; } }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2>KOPERASI BAKKEUM</h2>
        <p>LAPORAN REKAPITULASI KEUANGAN</p>
    </div>

    <div class="meta-info">
        <span>Anggota: <?php echo htmlspecialchars($nama_anggota_filter); ?></span>
        <span>Jenis Laporan: <?php echo ucfirst($jenis); ?></span>
        <span>Waktu Cetak: <?php echo $tgl_cetak; ?></span>
    </div>

    <?php
    $where_simpanan  = ($id_anggota !== 'semua') ? "WHERE simpanan.id_anggota = '$id_anggota'" : "";
    $where_pinjaman  = ($id_anggota !== 'semua') ? "WHERE pinjaman.id_anggota = '$id_anggota'" : "";
    $where_pembayaran = ($id_anggota !== 'semua') ? "WHERE pinjaman.id_anggota = '$id_anggota'" : "";
    ?>

    <!-- 1. TABEL SIMPANAN -->
    <?php if ($jenis === 'simpanan' || $jenis === 'semua'): ?>
        <div class="section-title">📊 Data Simpanan</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" width="5%">No</th>
                    <th class="text-center">Tanggal</th>
                    <th>Nama Anggota</th>
                    <th>Jenis Simpanan</th>
                    <th class="text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT simpanan.*, anggota.nama FROM simpanan JOIN anggota ON simpanan.id_anggota = anggota.id $where_simpanan";
                $query = mysqli_query($koneksi, $sql);
                $no = 1; $total_simpanan = 0;

                if ($query && mysqli_num_rows($query) > 0) {
                    while ($row = mysqli_fetch_assoc($query)) {
                        $jumlah = (float) ($row['jumlah'] ?? $row['jumlah_simpanan'] ?? $row['nominal'] ?? 0);
                        $total_simpanan += $jumlah;
                        $tgl_raw = $row['tgl_simpan'] ?? $row['tanggal'] ?? $row['created_at'] ?? 'now';
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center"><?php echo date('d-m-Y', strtotime($tgl_raw)); ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td><?php echo htmlspecialchars($row['jenis_simpanan'] ?? $row['jenis'] ?? 'Simpanan'); ?></td>
                            <td class="text-right">Rp <?php echo number_format($jumlah, 0, ',', '.'); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='5' class='text-center'>Data simpanan tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4" class="text-right">TOTAL SIMPANAN:</td>
                    <td class="text-right">Rp <?php echo number_format($total_simpanan, 0, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- 2. TABEL PINJAMAN -->
    <?php if ($jenis === 'pinjaman' || $jenis === 'semua'): ?>
        <div class="section-title">📄 Data Pinjaman</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" width="5%">No</th>
                    <th class="text-center">No Pinjaman</th>
                    <th>Nama Anggota</th>
                    <th class="text-right">Jumlah Pinjaman</th>
                    <th class="text-center">Tenor</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT pinjaman.*, anggota.nama FROM pinjaman JOIN anggota ON pinjaman.id_anggota = anggota.id $where_pinjaman";
                $query = mysqli_query($koneksi, $sql);
                $no = 1; $total_pinjaman = 0;

                if ($query && mysqli_num_rows($query) > 0) {
                    while ($row = mysqli_fetch_assoc($query)) {
                        $jumlah = (float) $row['jumlah_pinjaman'];
                        $total_pinjaman += $jumlah;
                        $tenor = $row['tenor'] ?? $row['lama_angsuran'] ?? 1;
                        $status_text = getStatusPinjaman($row, $koneksi);
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center">#<?php echo htmlspecialchars($row['no_pinjaman']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td class="text-right">Rp <?php echo number_format($jumlah, 0, ',', '.'); ?></td>
                            <td class="text-center"><?php echo $tenor; ?> Bulan</td>
                            <td class="text-center"><?php echo $status_text; ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='6' class='text-center'>Data pinjaman tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL PINJAMAN:</td>
                    <td class="text-right">Rp <?php echo number_format($total_pinjaman, 0, ',', '.'); ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- 3. TABEL PEMBAYARAN -->
    <?php if ($jenis === 'pembayaran' || $jenis === 'semua'): ?>
        <div class="section-title">💳 Data Pembayaran / Angsuran</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" width="5%">No</th>
                    <th class="text-center">ID Bayar</th>
                    <th class="text-center">Tanggal</th>
                    <th>Nama Anggota</th>
                    <th class="text-center">No Pinjaman</th>
                    <th class="text-center">Jenis Pembayaran</th>
                    <th class="text-right">Jumlah Bayar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT pembayaran.*, anggota.nama FROM pembayaran 
                        JOIN pinjaman ON pembayaran.no_pinjaman = pinjaman.no_pinjaman 
                        JOIN anggota ON pinjaman.id_anggota = anggota.id $where_pembayaran";
                $query = mysqli_query($koneksi, $sql);
                $no = 1; $total_bayar = 0;

                if ($query && mysqli_num_rows($query) > 0) {
                    while ($row = mysqli_fetch_assoc($query)) {
                        $id_bayar = $row['id_bayar'] ?? $row['id'] ?? $no;
                        $nominal = ($row['jumlah_bayar'] > 0) ? $row['jumlah_bayar'] : ($row['bayar_bunga'] ?? 0);
                        $total_bayar += $nominal;
                        $tgl = date('d-m-Y', strtotime($row['tanggal'] ?? 'now'));

                        // Penentuan Jenis Pembayaran secara fleksibel
                        $jenis_pembayaran = $row['jenis_bayar'] ?? $row['jenis_pembayaran'] ?? $row['keterangan'] ?? '';
                        if (empty($jenis_pembayaran)) {
                            if (isset($row['jumlah_bayar']) && isset($row['bayar_bunga'])) {
                                if ($row['jumlah_bayar'] > 0 && $row['bayar_bunga'] > 0) {
                                    $jenis_pembayaran = 'Angsuran Pokok + Bunga';
                                } elseif ($row['bayar_bunga'] > 0 && $row['jumlah_bayar'] == 0) {
                                    $jenis_pembayaran = 'Bayar Bunga';
                                } else {
                                    $jenis_pembayaran = 'Angsuran Pokok';
                                }
                            } else {
                                $jenis_pembayaran = 'Angsuran Pokok';
                            }
                        }
                        ?>
                        <tr>
                            <td class="text-center"><?php echo $no++; ?></td>
                            <td class="text-center">#TRX-<?php echo $id_bayar; ?></td>
                            <td class="text-center"><?php echo $tgl; ?></td>
                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                            <td class="text-center">#<?php echo htmlspecialchars($row['no_pinjaman']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($jenis_pembayaran); ?></td>
                            <td class="text-right">Rp <?php echo number_format($nominal, 0, ',', '.'); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center'>Data pembayaran tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="6" class="text-right">TOTAL ANGSURAN MASUK:</td>
                    <td class="text-right">Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <div class="ttd-container">
        <p>Pengelola Koperasi,</p>
        <div class="ttd-space"></div>
        <p><strong>( Admin Koperasi )</strong></p>
    </div>

</body>
</html>