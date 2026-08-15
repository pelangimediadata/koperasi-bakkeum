<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_admin'])) { header("Location: login.php"); exit(); }
include __DIR__ . "/api/koneksi.php";

$pesan_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $no_pinjaman   = $_POST['no_pinjaman'] ?? '';
    $jenis_bayar   = $_POST['jenis_bayar'] ?? '';
    $jumlah_bayar  = (int) ($_POST['jumlah_bayar'] ?? 0);
    $tanggal       = date('Y-m-d');

    // Tentukan pembagian nilai jumlah_bayar vs bayar_bunga berdasarkan jenis pembayaran
    if ($jenis_bayar === 'Bunga Saja' || $jenis_bayar === 'Bayar Bunga dari sisa Pokok') {
        $val_jumlah_bayar = 0;
        $val_bayar_bunga  = $jumlah_bayar;
    } else {
        $val_jumlah_bayar = $jumlah_bayar;
        $val_bayar_bunga  = 0;
    }

    try {
        // Mulai Transaksi PDO SQLite
        $koneksi->beginTransaction();

        // 1. Simpan transaksi ke tabel pembayaran menggunakan Prepared Statement
        $stmt_simpan = $koneksi->prepare("INSERT INTO pembayaran (no_pinjaman, jumlah_bayar, bayar_bunga, jenis_bayar, tanggal) VALUES (?, ?, ?, ?, ?)");
        $simpan = $stmt_simpan->execute([$no_pinjaman, $val_jumlah_bayar, $val_bayar_bunga, $jenis_bayar, $tanggal]);
        
        if ($simpan) {
            // 2. Ambil data pinjaman saat ini
            $stmt_p = $koneksi->prepare("SELECT * FROM pinjaman WHERE no_pinjaman = ?");
            $stmt_p->execute([$no_pinjaman]);
            $data_p = $stmt_p->fetch(PDO::FETCH_ASSOC);
            
            if ($data_p) {
                // Ambil nominal pokok awal
                $pokok_awal = $data_p['jumlah_pinjaman'] ?? $data_p['jumlah'] ?? $data_p['pokok'] ?? 0;
                
                // Tentukan sisa pinjaman saat ini
                if (isset($data_p['sisa_pinjaman']) && $data_p['sisa_pinjaman'] !== null && $data_p['sisa_pinjaman'] !== '') {
                    $sisa_lama = (int) $data_p['sisa_pinjaman'];
                } else {
                    $sisa_lama = (int) $pokok_awal;
                }
                
                // Hitung sisa baru berdasarkan jenis pembayaran
                if ($jenis_bayar === 'Bunga Saja' || $jenis_bayar === 'Bayar Bunga dari sisa Pokok') {
                    $sisa_baru = $sisa_lama; 
                } elseif ($jenis_bayar === 'Pelunasan') {
                    $sisa_baru = 0; 
                } else {
                    $sisa_baru = max(0, $sisa_lama - $jumlah_bayar); 
                }

                // Tentukan status pinjaman (jika sisa pokok habis/pelunasan, status menjadi Lunas)
                $status_baru = ($sisa_baru <= 0 || $jenis_bayar === 'Pelunasan') ? 'Lunas' : 'Berjalan';

                // 3. Update data pinjaman
                if (array_key_exists('sisa_pinjaman', $data_p)) {
                    $stmt_upd = $koneksi->prepare("UPDATE pinjaman SET sisa_pinjaman = ?, status = ? WHERE no_pinjaman = ?");
                    $stmt_upd->execute([$sisa_baru, $status_baru, $no_pinjaman]);
                } else {
                    $stmt_upd = $koneksi->prepare("UPDATE pinjaman SET status = ? WHERE no_pinjaman = ?");
                    $stmt_upd->execute([$status_baru, $no_pinjaman]);
                }
            }

            $koneksi->commit();
            echo "<script>alert('Pembayaran Berhasil Disimpan & Sisa Pinjaman/Bunga Diperbarui!'); window.location='bayar.php';</script>";
            exit();
        } else {
            $koneksi->rollBack();
            $pesan_error = "Gagal menyimpan pembayaran ke database.";
        }
    } catch (Exception $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
        $pesan_error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Pembayaran - AprilNet</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .content { width: 100%; max-width: 550px; }

        .dashboard-header-flex, .page-header {
            margin-bottom: 20px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            padding: 15px 20px;
            border-radius: 12px;
            border: 1px solid rgba(0, 242, 255, 0.15);
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 242, 255, 0.05);
        }

        .page-title { color: #00f2ff; font-size: 20px; font-weight: 700; text-shadow: 0 0 10px rgba(0,242,255,0.3); }

        .form-card {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid rgba(0, 242, 255, 0.15);
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #94a3b8; }
        .form-control {
            padding: 11px; border: 1px solid rgba(0, 242, 255, 0.3); border-radius: 6px; font-size: 14px; width: 100%;
            background: rgba(15, 23, 42, 0.9); color: #fff;
        }
        .form-control:focus { outline: none; border-color: #00f2ff; box-shadow: 0 0 8px rgba(0, 242, 255, 0.3); }

        .form-actions {
            display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;
        }

        .btn {
            padding: 10px 18px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s ease;
        }
        .btn:hover { transform: translateY(-1px); filter: brightness(1.1); }
        .btn-primary { background: #00f2ff; color: #0f172a; box-shadow: 0 0 12px rgba(0,242,255,0.3); }
        .btn-danger { background: #dc2626; color: white; box-shadow: 0 0 10px rgba(220,38,38,0.3); }

        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; background-color: rgba(220,38,38,0.2); color: #fca5a5; border: 1px solid rgba(220,38,38,0.4); }
    </style>
</head>
<body>
    <div class="content">
        <div class="page-header">
            <div class="page-title">💳 Input Pembayaran Angsuran</div>
        </div>

        <?php if (!empty($pesan_error)): ?>
            <div class="alert"><?php echo $pesan_error; ?></div>
        <?php endif; ?>

        <form action="tambah_bayar.php" method="POST" class="form-card">
            <div class="form-group">
                <label>Pilih Pinjaman Anggota</label>
                <select name="no_pinjaman" class="form-control" required>
                    <option value="">-- Pilih Pinjaman --</option>
                    <?php
                    // Query mencakup perhitungan total pokok & total bunga terbayar (termasuk jenis 'Bayar Bunga dari sisa Pokok')
                    $q_pinjaman = $koneksi->query("
                        SELECT p.*, a.nama,
                            IFNULL((SELECT SUM(jumlah_bayar) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman AND (pb.jenis_bayar = 'Bayar Angsuran' OR pb.jenis_bayar = 'Pelunasan' OR pb.jenis_bayar IS NULL)), 0) AS total_pokok_terbayar,
                            IFNULL((SELECT SUM(CASE WHEN pb.bayar_bunga > 0 THEN pb.bayar_bunga WHEN pb.jenis_bayar IN ('Bunga Saja', 'Bayar Bunga dari sisa Pokok') THEN pb.jumlah_bayar ELSE 0 END) FROM pembayaran pb WHERE pb.no_pinjaman = p.no_pinjaman), 0) AS total_bunga_terbayar
                        FROM pinjaman p 
                        JOIN anggota a ON p.id_anggota = a.id 
                        ORDER BY a.nama ASC
                    ");
                    $rows_p = $q_pinjaman ? $q_pinjaman->fetchAll(PDO::FETCH_ASSOC) : [];
                    
                    $found_active = false;
                    if (count($rows_p) > 0) {
                        foreach($rows_p as $p) {
                            $no_p = $p['no_pinjaman'];
                            $pokok = (float) ($p['jumlah_pinjaman'] ?? $p['jumlah'] ?? $p['pokok'] ?? 0);
                            $sisa_pokok = max(0, $pokok - (float)$p['total_pokok_terbayar']);
                            
                            $tenor = (int) ($p['tenor'] ?? $p['lama_angsuran'] ?? 1);
                            $total_bunga_pinjaman = (($pokok * (float)$p['bunga']) / 100) * $tenor;
                            $sisa_bunga = max(0, $total_bunga_pinjaman - (float)$p['total_bunga_terbayar']);

                            // Tampilkan jika sisa pokok masih ada ATAU sisa bunga masih ada
                            if ($sisa_pokok > 0 || $sisa_bunga > 0) {
                                $found_active = true;
                                $info_sisa = $sisa_pokok > 0 ? "Sisa Pokok: Rp " . number_format($sisa_pokok, 0, ',', '.') : "Pokok Lunas";
                                if ($sisa_bunga > 0) {
                                    $info_sisa .= " | Sisa Bunga: Rp " . number_format($sisa_bunga, 0, ',', '.');
                                }
                                echo "<option value='".$no_p."'>".htmlspecialchars($p['nama'])." (No: #".$no_p.") - ".$info_sisa."</option>";
                            }
                        }
                    }
                    
                    if (!$found_active) {
                        echo "<option value='' disabled>Tidak ada pinjaman aktif atau sisa bunga tertunggak</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Jenis Pembayaran</label>
                <select name="jenis_bayar" class="form-control" required>
                    <option value="Bayar Angsuran">Bayar Angsuran (Mengurangi Pokok)</option>
                    <option value="Bunga Saja">Bunga Saja (Tidak Mengurangi Pokok)</option>
                    <option value="Bayar Bunga dari sisa Pokok">Bayar Bunga dari sisa Pokok</option>
                    <option value="Pelunasan">Pelunasan (Pinjaman Selesai/Lunas)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Jumlah Bayar (Rp)</label>
                <input type="number" name="jumlah_bayar" class="form-control" placeholder="Masukkan nominal pembayaran" required min="1000">
            </div>

            <div class="form-actions">
                <a href="bayar.php" class="btn btn-danger">❌ Batal</a>
                <button type="submit" name="submit" class="btn btn-primary">💾 Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</body>
</html>