<?php
// File: update_status.php

session_start();
header('Content-Type: application/json');
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit();
}

$id     = $_POST['id']     ?? null;
$status = $_POST['status'] ?? null;
$allowed = ['Pending','Contacted','Accepted','Rejected'];

if (!$id || !in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'msg' => 'Invalid parameters']);
    exit();
}

$stmt = $conn->prepare("UPDATE calon_pendaftar SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => 'DB error']);
}
$stmt->close();
$conn->close();
