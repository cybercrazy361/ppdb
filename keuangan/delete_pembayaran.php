<?php
// delete_pembayaran.php

session_start();
header('Content-Type: application/json');

// Cek autentikasi dan otorisasi pengguna
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Hanya terima request DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

// Validasi CSRF token
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Validasi ID Pembayaran
if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit();
}
$pembayaran_id = intval($input['id']);
if ($pembayaran_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

include '../database_connection.php';
$unit_petugas = $_SESSION['unit'];

$conn->begin_transaction();
try {
    // Verifikasi kepemilikan pembayaran
    $stmt = $conn->prepare("
        SELECT p.id
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id = s.id
        WHERE p.id = ? AND s.unit = ?
    ");
    $stmt->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $stmt->close();

    // Hapus pembayaran (detail ikut terhapus via ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM pembayaran WHERE id = ?");
    $stmt->bind_param('i', $pembayaran_id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        throw new Exception('Gagal menghapus pembayaran.');
    }
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dihapus.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
