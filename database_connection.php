<?php
date_default_timezone_set('Asia/Jakarta');

// LOAD .env
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || trim($line) === '') continue;
        putenv(trim($line));
    }
}

$servername = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$dbname     = getenv('DB_NAME');
$charset    = getenv('DB_CHARSET') ?: 'utf8mb4';

// Buat koneksi ke database
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset($charset);
?>
