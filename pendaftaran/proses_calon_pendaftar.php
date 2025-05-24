<?php
// proses_calon_pendaftar.php

// Untuk debugging (hapus/baris ini di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../database_connection.php';

// Fungsi membersihkan input
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Proses jika POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi CSRF token (jika digunakan)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Permintaan tidak valid (CSRF token salah).");
    }

    // Ambil & bersihkan semua field (TIDAK ADA EMAIL)
    $nama            = sanitizeInput($_POST['nama'] ?? '');
    $jenis_kelamin   = sanitizeInput($_POST['jenis_kelamin'] ?? '');
    $asal_sekolah    = sanitizeInput($_POST['asal_sekolah'] ?? '');
    $no_hp           = sanitizeInput($_POST['no_hp'] ?? '');
    $alamat          = sanitizeInput($_POST['alamat'] ?? '');
    $pendidikan_ortu = sanitizeInput($_POST['pendidikan_ortu'] ?? '');
    $no_hp_ortu      = sanitizeInput($_POST['no_hp_ortu'] ?? '');
    $pilihan         = sanitizeInput($_POST['pilihan'] ?? '');

    $errors = [];

    // Validasi
    if (empty($nama)) $errors[] = "Nama lengkap harus diisi.";
    if (empty($jenis_kelamin) || !in_array($jenis_kelamin, ['Laki-laki','Perempuan'])) $errors[] = "Jenis kelamin harus diisi.";
    if (empty($asal_sekolah)) $errors[] = "Asal sekolah harus diisi.";
    if (empty($no_hp) || !preg_match("/^[0-9]{10,13}$/", $no_hp)) $errors[] = "No HP calon peserta didik wajib diisi & 10-13 digit.";
    if (empty($alamat)) $errors[] = "Alamat harus diisi.";
    if (empty($pendidikan_ortu)) $errors[] = "Pendidikan orang tua/wali harus diisi.";
    if (empty($no_hp_ortu) || !preg_match("/^[0-9]{10,13}$/", $no_hp_ortu)) $errors[] = "No HP orang tua/wali wajib diisi & 10-13 digit.";
    if (empty($pilihan) || !in_array($pilihan, ['SMA','SMK'])) $errors[] = "Pilihan sekolah harus diisi.";

    if (empty($errors)) {
        // INSERT ke database (TIDAK ADA KOLOM EMAIL)
        $sql = "INSERT INTO calon_pendaftar 
            (nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ssssssss", $nama, $jenis_kelamin, $asal_sekolah, $no_hp, $alamat, $pendidikan_ortu, $no_hp_ortu, $pilihan);
            if ($stmt->execute()) {
                // Sukses: redirect ke halaman sukses
                header("Location: sukses_pendaftaran.php");
                exit();
            } else {
                echo "<div style='color:red'>Terjadi kesalahan menyimpan data: {$stmt->error}</div>";
            }
            $stmt->close();
        } else {
            echo "<div style='color:red'>Gagal menyiapkan statement: {$conn->error}</div>";
        }
    } else {
        // Tampilkan error
        foreach ($errors as $err) {
            echo "<div style='color:red;margin-bottom:3px;'>$err</div>";
        }
        echo "<p><a href='pendaftaran_awal.php'>Kembali ke form pendaftaran</a></p>";
    }
} else {
    // Jika bukan submit, redirect ke form
    header("Location: pendaftaran_awal.php");
    exit();
}

$conn->close();
?>
