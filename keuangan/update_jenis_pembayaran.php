<?php
// update_jenis_pembayaran.php

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

// Ambil unit dari form (ini diinput hidden di file setting_jenis_pembayaran.php)
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';

// Validasi unit
if (!in_array($unit, ['Yayasan', 'SMA', 'SMK'])) {
    echo json_encode(['success' => false, 'message' => 'Unit tidak valid.']);
    exit();
}

// Mendapatkan aksi
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Menangani aksi
switch ($action) {
    case 'add':
        addJenisPembayaran($conn, $unit);
        break;

    case 'edit':
        editJenisPembayaran($conn, $unit);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

function addJenisPembayaran($conn, $unit)
{
    // Mendapatkan dan membersihkan input
    $nama = isset($_POST['nama_jenis_pembayaran']) ? trim($_POST['nama_jenis_pembayaran']) : '';

    // Validasi input
    if (empty($nama)) {
        echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran harus diisi.']);
        exit();
    }

    // Cek apakah nama sudah ada pada unit yang sama
    $stmt = $conn->prepare("SELECT id FROM jenis_pembayaran WHERE nama = ? AND unit = ?");
    $stmt->bind_param("ss", $nama, $unit);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran sudah ada untuk unit tersebut.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // Insert data
    $stmt = $conn->prepare("INSERT INTO jenis_pembayaran (nama, unit) VALUES (?, ?)");
    $stmt->bind_param("ss", $nama, $unit);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Jenis Pembayaran berhasil ditambahkan.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan Jenis Pembayaran: ' . $stmt->error]);
    }
    $stmt->close();
}

function editJenisPembayaran($conn, $unit)
{
    // Mendapatkan dan membersihkan input
    $jenis_id = isset($_POST['jenis_id']) ? intval($_POST['jenis_id']) : 0;
    $nama = isset($_POST['nama_jenis_pembayaran']) ? trim($_POST['nama_jenis_pembayaran']) : '';

    // Validasi input
    if ($jenis_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jenis Pembayaran ID tidak valid.']);
        exit();
    }

    if (empty($nama)) {
        echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran harus diisi.']);
        exit();
    }

    // Cek apakah nama sudah ada (tidak termasuk ID saat ini) pada unit yang sama
    $stmt = $conn->prepare("SELECT id FROM jenis_pembayaran WHERE nama = ? AND unit = ? AND id != ?");
    $stmt->bind_param("ssi", $nama, $unit, $jenis_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Nama Jenis Pembayaran sudah ada untuk unit tersebut.']);
        $stmt->close();
        exit();
    }
    $stmt->close();

    // Update data sesuai unit dan ID
    $stmt = $conn->prepare("UPDATE jenis_pembayaran SET nama = ? WHERE id = ? AND unit = ?");
    $stmt->bind_param("sis", $nama, $jenis_id, $unit);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Jenis Pembayaran berhasil diperbarui.']);
        } else {
            // Jika tidak ada baris yang terpengaruh, kemungkinan ID tersebut tidak cocok dengan unit
            echo json_encode(['success' => false, 'message' => 'Tidak ada perubahan. Pastikan ID dan unit sesuai.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui Jenis Pembayaran: ' . $stmt->error]);
    }
    $stmt->close();
}
