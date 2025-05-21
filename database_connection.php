<?php
date_default_timezone_set('Asia/Jakarta');

$servername = "localhost";
$db_username = "u732059733_ardi";
$db_password = "Tutukhi123#";
$dbname = "u732059733_ppdb_online";

// Buat koneksi ke database
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Mengatur charset menjadi utf8mb4
$conn->set_charset("utf8mb4");
?>


