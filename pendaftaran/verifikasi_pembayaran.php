<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$id = intval($_POST['id'] ?? 0);
$data = $_POST['data'] ?? [];

if ($id <= 0 || empty($data)) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Simpan data ke tabel baru, misal: pembayaran_verifikasi
$stmt = $conn->prepare("INSERT INTO pembayaran_verifikasi (siswa_id, jenis_pembayaran, nominal) VALUES (?, ?, ?)");

foreach ($data as $jenis => $nominal) {
    $stmt->bind_param('isd', $id, $jenis, $nominal);
    $stmt->execute();
}

$stmt->close();

echo json_encode(['success' => true]);
?>
