<?php
// File: update_status.php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    echo json_encode(['success'=>false,'msg'=>'Unauthorized']);
    exit;
}

$id     = $_POST['id']     ?? null;
$status = $_POST['status'] ?? null;
$notes  = $_POST['notes']  ?? null;

// Validasi ID
if (!$id) {
    echo json_encode(['success'=>false,'msg'=>'ID missing']);
    exit;
}

$allowed = ['Pending','Contacted','Accepted','Rejected'];
$updates = [];
$params  = [];
$types   = '';

// Siapkan update status
if ($status !== null) {
    if (!in_array($status,$allowed,true)) {
        echo json_encode(['success'=>false,'msg'=>'Invalid status']);
        exit;
    }
    $updates[] = "status = ?";
    $types   .= 's';
    $params[] = $status;
}

// Siapkan update notes
if ($notes !== null) {
    $updates[] = "notes = ?";
    $types   .= 's';
    $params[] = $notes;
}

if (empty($updates)) {
    echo json_encode(['success'=>false,'msg'=>'Nothing to update']);
    exit;
}

// Tambah ID di akhir
$types   .= 'i';
$params[] = $id;

$sql = "UPDATE calon_pendaftar SET ".implode(", ",$updates)." WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'msg'=>'DB error']);
}
$stmt->close();
$conn->close();
