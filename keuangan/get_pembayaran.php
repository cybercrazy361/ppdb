<?php
// get_pembayaran.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}

$pembayaran_id = intval($_GET['id']);

if ($pembayaran_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

include '../database_connection.php';

$stmt_pembayaran = $conn->prepare("SELECT p.id, p.no_formulir, s.nama, p.jumlah, p.metode_pembayaran, p.tahun_pelajaran, p.keterangan FROM pembayaran p INNER JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ?");
$stmt_pembayaran->bind_param('i', $pembayaran_id);
$stmt_pembayaran->execute();
$result_pembayaran = $stmt_pembayaran->get_result();

if ($result_pembayaran->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pembayaran tidak ditemukan']);
    exit();
}

$pembayaran = $result_pembayaran->fetch_assoc();
$stmt_pembayaran->close();

$stmt_detail = $conn->prepare("SELECT jenis_pembayaran_id, jumlah, bulan, status_pembayaran, cashback FROM pembayaran_detail WHERE pembayaran_id = ?");
$stmt_detail->bind_param('i', $pembayaran_id);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

$details = [];
while ($row = $result_detail->fetch_assoc()) {
    $details[] = [
    'jenis_pembayaran_id' => $row['jenis_pembayaran_id'],
    'jumlah' => $row['jumlah'],
    'bulan' => $row['bulan'],
    'status_pembayaran' => $row['status_pembayaran'],
    'cashback' => $row['cashback'] // ini WAJIB ADA!
];

}
$stmt_detail->close();

echo json_encode(['success' => true, 'data' => $pembayaran, 'details' => $details]);
$conn->close();
?>
