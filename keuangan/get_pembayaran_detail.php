<?php
include '../database_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
    exit();
}

$pembayaran_id = intval($_GET['id']);

$query = "SELECT 
    jenis_pembayaran_id, jumlah, bulan, status_pembayaran, cashback 
    FROM pembayaran_detail 
    WHERE pembayaran_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $pembayaran_id);
$stmt->execute();
$result = $stmt->get_result();

$details = [];
while ($row = $result->fetch_assoc()) {
    $details[] = $row;
}

if (!empty($details)) {
    echo json_encode(['success' => true, 'data' => $details]);
} else {
    echo json_encode(['success' => false, 'message' => 'Detail pembayaran tidak ditemukan.']);
}
$conn->close();
exit();
?>
