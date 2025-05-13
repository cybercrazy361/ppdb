<?php
// File: update_status.php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit;
}

$id     = $_POST['id']     ?? null;
$status = $_POST['status'] ?? null;
$desc   = $_POST['desc']   ?? null;

// Valid statuses
$allowed = ['Pending','Contacted','Accepted','Rejected'];

if (!$id) {
    echo json_encode(['success' => false, 'msg' => 'ID missing']);
    exit;
}

$updates = [];
$params  = [];
$types   = '';

// Prepare parts
if ($status !== null) {
    if (!in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'msg' => 'Invalid status']);
        exit;
    }
    $updates[] = "status = ?";
    $types   .= 's';
    $params[] = $status;
}
if ($desc !== null) {
    $updates[] = "keterangan = ?";
    $types   .= 's';
    $params[] = $desc;
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'msg' => 'Nothing to update']);
    exit;
}

// Always bind ID last
$types   .= 'i';
$params[] = $id;

$sql = "UPDATE calon_pendaftar SET " . implode(", ", $updates) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => 'DB error']);
}
$stmt->close();
$conn->close();
