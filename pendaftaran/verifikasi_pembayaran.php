<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$id = intval($_POST['id'] ?? 0);
$data = json_decode($_POST['data'] ?? '', true); // Ambil dari JSON

if ($id <= 0 || empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Ambil data jenis pembayaran: mapping NAMA KE ID
$jenis_map = [];
$q = $conn->query("SELECT id, nama FROM jenis_pembayaran");
while ($r = $q->fetch_assoc()) {
    $jenis_map[strtolower($r['nama'])] = $r['id'];
}

$stmt = $conn->prepare("
    INSERT INTO siswa_tagihan_awal
    (siswa_id, jenis_pembayaran_id, nominal, verified_by, verified_at)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        nominal = VALUES(nominal),
        verified_by = VALUES(verified_by),
        verified_at = VALUES(verified_at)
");

foreach ($data as $jenis => $nominal) {
    $jenis_id = $jenis_map[strtolower($jenis)] ?? 0;
    if (!$jenis_id || floatval($nominal) < 1) continue; // skip jika tidak valid
    $verified_by = $_SESSION['username'];
    $verified_at = date('Y-m-d H:i:s');
    $stmt->bind_param('iisss', $id, $jenis_id, $nominal, $verified_by, $verified_at);
    $stmt->execute();
}

$stmt->close();
echo json_encode(['success' => true]);
