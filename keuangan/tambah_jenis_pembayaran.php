<?php
// tambah_jenis_pembayaran.php

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

// Validasi dan ambil nama jenis pembayaran
if (!isset($_POST['nama_jenis_pembayaran']) || empty(trim($_POST['nama_jenis_pembayaran']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran harus diisi.']);
    exit();
}

$nama_jenis_pembayaran = trim($_POST['nama_jenis_pembayaran']);

// Koneksi ke database
include 'database_connection.php';

// Cek apakah nama jenis pembayaran sudah ada
$stmt = $conn->prepare("SELECT id FROM jenis_pembayaran WHERE nama = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Persiapan pernyataan SQL gagal: ' . $conn->error]);
    exit();
}

$stmt->bind_param("s", $nama_jenis_pembayaran);
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

// Insert jenis pembayaran baru
$stmt = $conn->prepare("INSERT INTO jenis_pembayaran (nama) VALUES (?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Persiapan pernyataan SQL gagal: ' . $conn->error]);
    exit();
}

$stmt->bind_param("s", $nama_jenis_pembayaran);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Jenis Pembayaran berhasil ditambahkan.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Eksekusi pernyataan SQL gagal: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
