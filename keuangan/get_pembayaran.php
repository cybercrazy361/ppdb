<?php
session_start();
header('Content-Type: application/json');

// 1. Auth Check
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// 2. Param Check
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

// 3. Fetch Header Data
$stmt = $conn->prepare("
    SELECT 
        p.id, 
        p.no_formulir, 
        s.nama, 
        p.jumlah,
        p.tahun_pelajaran, 
        p.metode_pembayaran, 
        p.keterangan 
    FROM pembayaran p 
    INNER JOIN siswa s ON p.siswa_id = s.id 
    WHERE p.id = ?
");
$stmt->bind_param('i', $pembayaran_id);
$stmt->execute();
$result = $stmt->get_result();
$header = $result->fetch_assoc();
$stmt->close();

if (!$header) {
    echo json_encode(['success' => false, 'message' => 'Pembayaran tidak ditemukan']);
    exit();
}

// 4. Fetch Detail
$stmt = $conn->prepare("
    SELECT 
        jenis_pembayaran_id, 
        jumlah, 
        bulan, 
        status_pembayaran, 
        cashback 
    FROM pembayaran_detail 
    WHERE pembayaran_id = ?
");
$stmt->bind_param('i', $pembayaran_id);
$stmt->execute();
$result = $stmt->get_result();
$details = [];
while ($row = $result->fetch_assoc()) {
    $details[] = [
        'jenis_pembayaran_id' => $row['jenis_pembayaran_id'],
        'jumlah'              => $row['jumlah'],
        'bulan'               => $row['bulan'],
        'status_pembayaran'   => $row['status_pembayaran'],
        'cashback'            => $row['cashback']
    ];
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => [
        'id'                 => $header['id'],
        'no_formulir'        => $header['no_formulir'],
        'nama'               => $header['nama'],
        'jumlah'             => $header['jumlah'],
        'tahun_pelajaran'    => $header['tahun_pelajaran'],
        'metode_pembayaran'  => $header['metode_pembayaran'],
        'keterangan'         => $header['keterangan']
    ],
    'details' => $details
]);
$conn->close();
exit();
?>
