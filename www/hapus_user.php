<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Validasi akses hanya untuk admin
if (!isset($_SESSION['login_admin']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

include __DIR__ . "/api/koneksi.php";

$id = $_GET['id'] ?? 0;

if ($id > 0) {
    // Mencegah admin menghapus akunnya sendiri yang sedang digunakan untuk login
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
        echo "<script>
                alert('Gagal! Anda tidak bisa menghapus akun yang sedang Anda gunakan saat ini.');
                window.location='users.php';
              </script>";
        exit();
    }

    try {
        // Menggunakan PDO SQLite Prepared Statement
        $stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
        $query = $stmt->execute([$id]);

        if ($query) {
            echo "<script>
                    alert('User berhasil dihapus!');
                    window.location='users.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Gagal menghapus user.');
                    window.location='users.php';
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>
                alert('Gagal menghapus user: " . addslashes($e->getMessage()) . "');
                window.location='users.php';
              </script>";
    }
} else {
    header("Location: users.php");
}
?>