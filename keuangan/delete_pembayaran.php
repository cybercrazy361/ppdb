<?php
// delete_pembayaran.php

session_start();
header('Content-Type: application/json');

// Cek autentikasi dan otorisasi pengguna
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Pastikan metode request adalah DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Mendapatkan input JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validasi input JSON
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

// Mendapatkan unit_petugas dari sesi
$unit_petugas = $_SESSION['unit']; // Pastikan sesi memiliki 'unit'

// Mulai transaksi untuk memastikan konsistensi data
$conn->begin_transaction();

try {
    // Verifikasi bahwa pembayaran_id ada dan milik unit_petugas
    $stmt_verifikasi = $conn->prepare("
        SELECT p.id
        FROM pembayaran p
        INNER JOIN siswa s ON p.siswa_id = s.id
        WHERE p.id = ? AND s.unit = ?
    ");
    if (!$stmt_verifikasi) {
        throw new Exception("Error preparing verifikasi pembayaran statement: " . $conn->error);
    }
    $stmt_verifikasi->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmt_verifikasi->execute();
    $res_verifikasi = $stmt_verifikasi->get_result();
    if ($res_verifikasi->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $stmt_verifikasi->close();
    
    // Cek apakah pembayaran memiliki detail pembayaran
    $stmt_check_detail = $conn->prepare("SELECT COUNT(*) AS detail_count FROM pembayaran_detail WHERE pembayaran_id = ?");
    if (!$stmt_check_detail) {
        throw new Exception("Error preparing cek detail pembayaran statement: " . $conn->error);
    }
    $stmt_check_detail->bind_param('i', $pembayaran_id);
    $stmt_check_detail->execute();
    $res_detail = $stmt_check_detail->get_result();
    $detail_count = 0;
    if ($res_detail->num_rows > 0) {
        $detail_row = $res_detail->fetch_assoc();
        $detail_count = intval($detail_row['detail_count']);
    }
    $stmt_check_detail->close();
    
    if ($detail_count > 0) {
        throw new Exception('Pembayaran ini memiliki detail pembayaran dan tidak dapat dihapus.');
    }
    
    // Jika semua cek lolos, hapus pembayaran
    $stmt_delete = $conn->prepare("DELETE FROM pembayaran WHERE id = ?");
    if (!$stmt_delete) {
        throw new Exception("Error preparing hapus pembayaran statement: " . $conn->error);
    }
    $stmt_delete->bind_param('i', $pembayaran_id);
    if (!$stmt_delete->execute()) {
        throw new Exception('Gagal menghapus pembayaran: ' . $stmt_delete->error);
    }
    
    if ($stmt_delete->affected_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau sudah dihapus.');
    }
    
    $stmt_delete->close();
    
    // Commit transaksi
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dihapus.']);
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
