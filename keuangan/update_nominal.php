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
include '../database_connection.php';

// Fungsi untuk mengirim respons JSON dan keluar
function send_response($success, $message)
{
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
if (
    !isset($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    send_response(false, 'Token CSRF tidak valid.');
}

// Ambil aksi
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'add') {
    // Tambah
    $jenis_pembayaran_id = isset($_POST['jenis_pembayaran'])
        ? intval($_POST['jenis_pembayaran'])
        : 0;
    $nominal_max = isset($_POST['nominal_max'])
        ? trim($_POST['nominal_max'])
        : '';
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : null;

    if ($jenis_pembayaran_id <= 0) {
        send_response(false, 'Jenis Pembayaran tidak valid.');
    }

    // Cek apakah jenis_pembayaran_id ada dan sesuai unit_petugas
    $stmt = $conn->prepare(
        'SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?'
    );
    if (!$stmt) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param('is', $jenis_pembayaran_id, $unit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        send_response(
            false,
            'Jenis Pembayaran tidak ditemukan atau tidak sesuai unit.'
        );
    }
    $jenis_pembayaran = strtolower($result->fetch_assoc()['nama']);
    $stmt->close();

    // Validasi bulan jika SPP
    if ($jenis_pembayaran === 'spp') {
        if (empty($bulan)) {
            send_response(
                false,
                'Bulan harus dipilih untuk jenis pembayaran SPP.'
            );
        }
    } else {
        $bulan = null;
    }

    // Handle nominal_max bisa kosong/strip/0/angka
    $nominal_max_clean = null;
    if ($nominal_max === '' || $nominal_max === '-') {
        $nominal_max_clean = null;
    } else {
        $nominal_max_clean = floatval(
            str_replace(['.', ','], '', $nominal_max)
        );
        if ($nominal_max_clean < 0) {
            send_response(false, 'Nominal Maksimum tidak boleh kurang dari 0.');
        }
    }

    // Insert
    if ($nominal_max_clean === null) {
        $stmt = $conn->prepare(
            'INSERT INTO pengaturan_nominal (jenis_pembayaran_id, nominal_max, bulan) VALUES (?, NULL, ?)'
        );
        $stmt->bind_param('is', $jenis_pembayaran_id, $bulan);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO pengaturan_nominal (jenis_pembayaran_id, nominal_max, bulan) VALUES (?, ?, ?)'
        );
        $stmt->bind_param(
            'ids',
            $jenis_pembayaran_id,
            $nominal_max_clean,
            $bulan
        );
    }
    if ($stmt->execute()) {
        $stmt->close();
        send_response(true, 'Pengaturan nominal berhasil ditambahkan.');
    } else {
        if ($conn->errno === 1062) {
            $stmt->close();
            send_response(
                false,
                'Pengaturan nominal untuk jenis pembayaran dan bulan tersebut sudah ada.'
            );
        } else {
            $stmt->close();
            send_response(
                false,
                'Gagal menambahkan pengaturan nominal: ' . $conn->error
            );
        }
    }
} elseif ($action === 'edit') {
    // Edit
    $pengaturan_id = isset($_POST['pengaturan_id'])
        ? intval($_POST['pengaturan_id'])
        : 0;
    $jenis_pembayaran_id = isset($_POST['jenis_pembayaran'])
        ? intval($_POST['jenis_pembayaran'])
        : 0;
    $nominal_max = isset($_POST['nominal_max'])
        ? trim($_POST['nominal_max'])
        : '';
    $bulan = isset($_POST['bulan']) ? $_POST['bulan'] : null;

    if ($pengaturan_id <= 0) {
        send_response(false, 'Pengaturan ID tidak valid.');
    }
    if ($jenis_pembayaran_id <= 0) {
        send_response(false, 'Jenis Pembayaran tidak valid.');
    }

    // Cek data pengaturan_nominal
    $stmt = $conn->prepare(
        'SELECT jenis_pembayaran_id FROM pengaturan_nominal WHERE id = ?'
    );
    if (!$stmt) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param('i', $pengaturan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        send_response(false, 'Pengaturan nominal tidak ditemukan.');
    }
    $stmt->close();

    // Cek jenis_pembayaran_id valid
    $stmt = $conn->prepare(
        'SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?'
    );
    if (!$stmt) {
        send_response(false, 'Terjadi kesalahan pada server.');
    }
    $stmt->bind_param('is', $jenis_pembayaran_id, $unit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        send_response(
            false,
            'Jenis Pembayaran tidak ditemukan atau tidak sesuai unit.'
        );
    }
    $jenis_pembayaran = strtolower($result->fetch_assoc()['nama']);
    $stmt->close();

    // Validasi bulan jika SPP
    if ($jenis_pembayaran === 'spp') {
        if (empty($bulan)) {
            send_response(
                false,
                'Bulan harus dipilih untuk jenis pembayaran SPP.'
            );
        }
    } else {
        $bulan = null;
    }

    // Handle nominal_max bisa kosong/strip/0/angka
    $nominal_max_clean = null;
    if ($nominal_max === '' || $nominal_max === '-') {
        $nominal_max_clean = null;
    } else {
        $nominal_max_clean = floatval(
            str_replace(['.', ','], '', $nominal_max)
        );
        if ($nominal_max_clean < 0) {
            send_response(false, 'Nominal Maksimum tidak boleh kurang dari 0.');
        }
    }

    // Update
    if ($nominal_max_clean === null) {
        $stmt = $conn->prepare(
            'UPDATE pengaturan_nominal SET jenis_pembayaran_id = ?, nominal_max = NULL, bulan = ? WHERE id = ?'
        );
        $stmt->bind_param('isi', $jenis_pembayaran_id, $bulan, $pengaturan_id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE pengaturan_nominal SET jenis_pembayaran_id = ?, nominal_max = ?, bulan = ? WHERE id = ?'
        );
        $stmt->bind_param(
            'idsi',
            $jenis_pembayaran_id,
            $nominal_max_clean,
            $bulan,
            $pengaturan_id
        );
    }
    if ($stmt->execute()) {
        $stmt->close();
        send_response(true, 'Pengaturan nominal berhasil diperbarui.');
    } else {
        if ($conn->errno === 1062) {
            $stmt->close();
            send_response(
                false,
                'Pengaturan nominal untuk jenis pembayaran dan bulan tersebut sudah ada.'
            );
        } else {
            $stmt->close();
            send_response(
                false,
                'Gagal memperbarui pengaturan nominal: ' . $conn->error
            );
        }
    }
} else {
    send_response(false, 'Aksi tidak valid.');
}
?>
