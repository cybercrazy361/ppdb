<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$data = json_decode($_POST['data'] ?? '[]', true);

if ($id <= 0 || empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Map nama ke id jenis_pembayaran
$jenis_map = [
    'Uang Pangkal' => 1,
    'SPP'          => 2,
    'Seragam'      => 3,
    'Kegiatan'     => 4,
    // dst, sesuai DB kamu
];

$verified_by = $_SESSION['username'];
$now = date('Y-m-d H:i:s');
$ok = true;

foreach ($data as $jenis => $nominal) {
    if (!isset($jenis_map[$jenis])) continue;
    $jenis_id = $jenis_map[$jenis];
    $stmt = $conn->prepare("INSERT INTO siswa_tagihan_awal (siswa_id, jenis_pembayaran_id, nominal, verified_by, verified_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE nominal=VALUES(nominal), verified_by=VALUES(verified_by), verified_at=VALUES(verified_at)
    ");
    $stmt->bind_param('iisss', $id, $jenis_id, $nominal, $verified_by, $now);
    if (!$stmt->execute()) $ok = false;
    $stmt->close();
}

echo json_encode(['success' => $ok]);
?>
