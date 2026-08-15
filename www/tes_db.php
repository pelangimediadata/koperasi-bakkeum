<?php
include '../api/koneksi.php';

echo "<h2>1. Cek Kolom Tabel Simpanan:</h2>";
$res_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM simpanan");
if ($res_kolom) {
    while ($row = mysqli_fetch_assoc($res_kolom)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Tabel simpanan tidak ditemukan: " . mysqli_error($koneksi);
}

echo "<h2>2. Cek Isi Data Tabel Simpanan:</h2>";
$res_data = mysqli_query($koneksi, "SELECT * FROM simpanan");
if ($res_data && mysqli_num_rows($res_data) > 0) {
    echo "<table border='1' cellpadding='5'>";
    $first = true;
    while ($row = mysqli_fetch_assoc($res_data)) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $col) echo "<th>$col</th>";
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($row as $val) echo "<td>$val</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Data simpanan Kosong!";
}
?>