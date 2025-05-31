<?php
// delete_jenis_pembayaran.php

session_start();

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Cek metode request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Koneksi ke database
include '../database_connection.php';

// Validasi CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit();
}

// Mendapatkan aksi
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action !== 'delete') {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

// Mendapatkan dan membersihkan input
$jenis_id = isset($_POST['jenis_id']) ? intval($_POST['jenis_id']) : 0;
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';

// Validasi input
if ($jenis_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Jenis Pembayaran ID tidak valid.']);
    exit();
}

if (!in_array($unit, ['Yayasan', 'SMA', 'SMK'])) {
    echo json_encode(['success' => false, 'message' => 'Unit tidak valid.']);
    exit();
}

// Cek apakah jenis_pembayaran ini sedang digunakan di pembayaran_detail
$stmt = $conn->prepare("SELECT COUNT(*) FROM pembayaran_detail WHERE jenis_pembayaran_id = ?");
$stmt->bind_param("i", $jenis_id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count > 0) {
    echo json_encode(['success' => false, 'message' => 'Jenis Pembayaran ini sedang digunakan dan tidak dapat dihapus.']);
    exit();
}

// Proses penghapusan dengan mempertimbangkan unit
$stmt = $conn->prepare("DELETE FROM jenis_pembayaran WHERE id = ? AND unit = ?");
$stmt->bind_param("is", $jenis_id, $unit);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Jenis Pembayaran berhasil dihapus.']);
    } else {
        // Jika tidak ada baris yang terpengaruh, kemungkinan ID tidak cocok dengan unit
        echo json_encode(['success' => false, 'message' => 'Jenis Pembayaran tidak ditemukan atau tidak sesuai unit.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus Jenis Pembayaran: ' . $stmt->error]);
}
$stmt->close();
