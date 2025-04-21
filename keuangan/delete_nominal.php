<?php
// delete_nominal.php

session_start();

// Nonaktifkan tampilan kesalahan (sesuaikan untuk produksi)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Mulai output buffering
ob_start();

// Set header untuk respons JSON
header('Content-Type: application/json');

// Sertakan koneksi database
include '../database_connection.php'; // Sesuaikan path jika diperlukan

// Fungsi untuk mengirim respons JSON dan keluar
function send_response($success, $message) {
    // Bersihkan output buffer
    ob_clean();
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Cek apakah pengguna sudah login dan memiliki peran 'keuangan'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    send_response(false, 'Akses tidak diizinkan.');
}

// Pastikan unit tersedia
if (!isset($_SESSION['unit'])) {
    send_response(false, 'Unit tidak ditemukan dalam sesi.');
}
$unit = $_SESSION['unit'];

// Cek metode request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, 'Metode request tidak valid.');
}

// Validasi token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    send_response(false, 'Token CSRF tidak valid.');
}

// Ambil aksi, harusnya 'delete'
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($action !== 'delete') {
    send_response(false, 'Aksi tidak valid.');
}

// Ambil pengaturan_id
$pengaturan_id = isset($_POST['pengaturan_id']) ? intval($_POST['pengaturan_id']) : 0;

// Validasi pengaturan_id
if ($pengaturan_id <= 0) {
    send_response(false, 'Pengaturan ID tidak valid.');
}

// Cek apakah pengaturan_nominal ada dan sesuai unit melalui jenis_pembayaran
$stmt = $conn->prepare("
    SELECT jp.unit 
    FROM pengaturan_nominal pn
    JOIN jenis_pembayaran jp ON pn.jenis_pembayaran_id = jp.id
    WHERE pn.id = ?
");
if (!$stmt) {
    send_response(false, 'Terjadi kesalahan pada server.');
}
$stmt->bind_param("i", $pengaturan_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    send_response(false, 'Pengaturan nominal tidak ditemukan.');
}
$row = $result->fetch_assoc();
$stmt->close();

// Cek apakah unit data cocok dengan unit petugas
if ($row['unit'] !== $unit) {
    send_response(false, 'Anda tidak diizinkan menghapus pengaturan nominal dari unit lain.');
}

// Cek apakah pengaturan_nominal digunakan dalam pembayaran_detail
$stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM pembayaran_detail WHERE jenis_pembayaran_id = ?");
if (!$stmt_check) {
    send_response(false, 'Terjadi kesalahan pada server.');
}
$stmt_check->bind_param("i", $pengaturan_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
$count = 0;
if ($res_check->num_rows > 0) {
    $count = intval($res_check->fetch_assoc()['count']);
}
$stmt_check->close();

if ($count > 0) {
    send_response(false, 'Pengaturan Nominal ini sedang digunakan dalam pembayaran dan tidak dapat dihapus.');
}

// Hapus pengaturan_nominal
$stmt_delete = $conn->prepare("DELETE FROM pengaturan_nominal WHERE id = ?");
if ($stmt_delete === false) {
    send_response(false, 'Terjadi kesalahan pada server.');
}
$stmt_delete->bind_param("i", $pengaturan_id);
if ($stmt_delete->execute()) {
    $stmt_delete->close();
    send_response(true, 'Pengaturan nominal berhasil dihapus.');
} else {
    $stmt_delete->close();
    send_response(false, 'Gagal menghapus pengaturan nominal: ' . $conn->error);
}

$conn->close();
?>
