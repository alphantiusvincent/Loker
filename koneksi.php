<?php
// koneksi.php
$db_host = "localhost";
$db_user = "root";      // <<< GANTI DENGAN USER DATABASE ANDA
$db_pass = "";          // <<< GANTI DENGAN PASSWORD DATABASE ANDA (kosong jika default XAMPP/WAMP)
$db_name = "bawamap";

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>