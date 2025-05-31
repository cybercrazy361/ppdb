<?php
// wablas_callback.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Baca JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Simpan ke file log atau database
file_put_contents(__DIR__ . '/wablas_callback_log.txt',
    date('Y-m-d H:i:s') . " => " . $json . PHP_EOL,
    FILE_APPEND
);

// Kirim HTTP 200 OK
http_response_code(200);
echo 'OK';
?>
