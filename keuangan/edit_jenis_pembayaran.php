<?php
// edit_jenis_pembayaran.php

session_start();
header('Content-Type: application/json');

// Pastikan pengguna sudah login dan memiliki peran 'keuangan'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit();
}

// Cek metode permintaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak diizinkan.']);
    exit();
}

// Cek token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit();
}

// Validasi dan ambil data
if (!isset($_POST['jenis_id']) || empty(trim($_POST['jenis_id']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Jenis Pembayaran tidak valid.']);
    exit();
}

if (!isset($_POST['nama_jenis_pembayaran']) || empty(trim($_POST['nama_jenis_pembayaran']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran harus diisi.']);
    exit();
}

$jenis_id = intval($_POST['jenis_id']);
$nama_jenis_pembayaran = trim($_POST['nama_jenis_pembayaran']);

// Koneksi ke database
include 'database_connection.php';

// Cek apakah nama jenis pembayaran sudah ada untuk id lain
$stmt = $conn->prepare("SELECT id FROM jenis_pembayaran WHERE nama = ? AND id != ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Persiapan pernyataan SQL gagal: ' . $conn->error]);
    exit();
}

$stmt->bind_param("si", $nama_jenis_pembayaran, $jenis_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Jenis Pembayaran sudah ada.']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Update jenis pembayaran
$stmt = $conn->prepare("UPDATE jenis_pembayaran SET nama = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Persiapan pernyataan SQL gagal: ' . $conn->error]);
    exit();
}

$stmt->bind_param("si", $nama_jenis_pembayaran, $jenis_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Jenis Pembayaran berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada perubahan yang dilakukan.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Eksekusi pernyataan SQL gagal: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
