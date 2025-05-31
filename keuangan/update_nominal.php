<?php
// update_nominal.php

session_start();

// Nonaktifkan tampilan kesalahan (hanya untuk produksi)
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

// Ambil unit dari sesi (pastikan sudah diset saat login)
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

// Ambil aksi
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'add') {
    // Tangani aksi Tambah
    $jenis_pembayaran_id = isset($_POST['jenis_pembayaran']) ? intval($_POST['jenis_pembayaran']) : 0;
    $nominal_max = isset($_POST['nominal_max']) ? $_POST['nominal_max'] : '';
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : NULL;

    // Validasi input
    if ($jenis_pembayaran_id <= 0) {
        send_response(false, 'Jenis Pembayaran tidak valid.');
    }

    // Konversi nominal_max ke float
    $nominal_max_clean = floatval(str_replace(['.', ','], '', $nominal_max));
    if ($nominal_max_clean <= 0) {
        send_response(false, 'Nominal Maksimum harus lebih besar dari 0.');
    }

    // Cek apakah jenis_pembayaran_id ada dan sesuai dengan unit_petugas
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    if (!$stmt) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param("is", $jenis_pembayaran_id, $unit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        send_response(false, 'Jenis Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $jenis_pembayaran = strtolower($result->fetch_assoc()['nama']);
    $stmt->close();

    // Jika jenis_pembayaran adalah 'spp', bulan harus diisi
    if ($jenis_pembayaran === 'spp') {
        if (empty($bulan)) {
            send_response(false, 'Bulan harus dipilih untuk jenis pembayaran SPP.');
        }
    } else {
        $bulan = NULL; // Set ke NULL jika bukan SPP
    }

    // Insert ke pengaturan_nominal tanpa kolom 'unit'
    $stmt = $conn->prepare("INSERT INTO pengaturan_nominal (jenis_pembayaran_id, nominal_max, bulan) VALUES (?, ?, ?)");
    if ($stmt === false) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param("ids", $jenis_pembayaran_id, $nominal_max_clean, $bulan);
    if ($stmt->execute()) {
        $stmt->close();
        send_response(true, 'Pengaturan nominal berhasil ditambahkan.');
    } else {
        // Cek jika terjadi duplicate entry
        if ($conn->errno === 1062) {
            $stmt->close();
            send_response(false, 'Pengaturan nominal untuk jenis pembayaran dan bulan tersebut sudah ada.');
        } else {
            $stmt->close();
            send_response(false, 'Gagal menambahkan pengaturan nominal: ' . $conn->error);
        }
    }
} elseif ($action === 'edit') {
    // Tangani aksi Edit
    $pengaturan_id = isset($_POST['pengaturan_id']) ? intval($_POST['pengaturan_id']) : 0;
    $jenis_pembayaran_id = isset($_POST['jenis_pembayaran']) ? intval($_POST['jenis_pembayaran']) : 0;
    $nominal_max = isset($_POST['nominal_max']) ? $_POST['nominal_max'] : '';
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : NULL;

    // Validasi input
    if ($pengaturan_id <= 0) {
        send_response(false, 'Pengaturan ID tidak valid.');
    }

    if ($jenis_pembayaran_id <= 0) {
        send_response(false, 'Jenis Pembayaran tidak valid.');
    }

    // Konversi nominal_max ke float
    $nominal_max_clean = floatval(str_replace(['.', ','], '', $nominal_max));
    if ($nominal_max_clean <= 0) {
        send_response(false, 'Nominal Maksimum harus lebih besar dari 0.');
    }

    // Cek apakah pengaturan_nominal ada
    $stmt = $conn->prepare("SELECT jenis_pembayaran_id FROM pengaturan_nominal WHERE id = ?");
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
    $stmt->close();

    // Cek apakah jenis_pembayaran_id ada dan sesuai dengan unit_petugas
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    if (!$stmt) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param("is", $jenis_pembayaran_id, $unit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        send_response(false, 'Jenis Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $jenis_pembayaran = strtolower($result->fetch_assoc()['nama']);
    $stmt->close();

    // Jika jenis_pembayaran adalah 'spp', bulan harus diisi
    if ($jenis_pembayaran === 'spp') {
        if (empty($bulan)) {
            send_response(false, 'Bulan harus dipilih untuk jenis pembayaran SPP.');
        }
    } else {
        $bulan = NULL; // Set ke NULL jika bukan SPP
    }

    // Update pengaturan_nominal tanpa kolom 'unit'
    $stmt = $conn->prepare("UPDATE pengaturan_nominal SET jenis_pembayaran_id = ?, nominal_max = ?, bulan = ? WHERE id = ?");
    if ($stmt === false) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param("idsi", $jenis_pembayaran_id, $nominal_max_clean, $bulan, $pengaturan_id);
    if ($stmt->execute()) {
        $stmt->close();
        send_response(true, 'Pengaturan nominal berhasil diperbarui.');
    } else {
        // Cek jika terjadi duplicate entry
        if ($conn->errno === 1062) {
            $stmt->close();
            send_response(false, 'Pengaturan nominal untuk jenis pembayaran dan bulan tersebut sudah ada.');
        } else {
            $stmt->close();
            send_response(false, 'Gagal memperbarui pengaturan nominal: ' . $conn->error);
        }
    }
} else {
    // Aksi tidak dikenali
    send_response(false, 'Aksi tidak valid.');
}

?>
