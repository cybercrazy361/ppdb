<?php
// get_jenis_pembayaran.php
include '../database_connection.php';
session_start();

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$unit_petugas = $_SESSION['unit'];

// Fetch jenis_pembayaran list
$jenis_pembayaran_list = [];
$stmt_jenis = $conn->prepare("SELECT id, nama FROM jenis_pembayaran ORDER BY nama ASC");
$stmt_jenis->execute();
$result_jenis = $stmt_jenis->get_result();
while ($row = $result_jenis->fetch_assoc()) {
    $jenis_pembayaran_list[] = $row;
}
$stmt_jenis->close();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $jenis_pembayaran_list]);
?>
