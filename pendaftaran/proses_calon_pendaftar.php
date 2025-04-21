<?php
// proses_calon_pendaftar.php

// Untuk sementara, aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Mengikutkan file koneksi database menggunakan path yang benar
require_once '../database_connection.php'; // Sesuaikan path jika diperlukan

// Fungsi untuk membersihkan input agar aman dari serangan XSS
function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Memeriksa apakah form telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Memeriksa CSRF Token (jika Anda mengimplementasikan)
    /*
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Permintaan tidak valid.");
    }
    */

    // Mengambil dan membersihkan data dari form
    $nama = sanitizeInput($_POST['nama']);
    $asal_sekolah = sanitizeInput($_POST['asal_sekolah']);
    $email = sanitizeInput($_POST['email']);
    $no_hp = sanitizeInput($_POST['no_hp']);
    $alamat = sanitizeInput($_POST['alamat']);
    $pilihan = sanitizeInput($_POST['pilihan']);

    // Validasi server-side tambahan (optional)
    $errors = [];

    // Validasi Nama
    if (empty($nama)) {
        $errors[] = "Nama lengkap harus diisi.";
    }

    // Validasi Asal Sekolah
    if (empty($asal_sekolah)) {
        $errors[] = "Asal sekolah harus diisi.";
    }

    // Validasi Email
    if (empty($email)) {
        $errors[] = "Email harus diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }

    // Validasi No HP
    if (empty($no_hp)) {
        $errors[] = "Nomor telepon harus diisi.";
    } elseif (!preg_match("/^[0-9]{10,13}$/", $no_hp)) {
        $errors[] = "Format nomor telepon tidak valid. Harus 10-13 digit angka.";
    }

    // Validasi Alamat
    if (empty($alamat)) {
        $errors[] = "Alamat harus diisi.";
    }

    // Validasi Pilihan Sekolah
    $allowed_pilihan = ['SMA', 'SMK'];
    if (empty($pilihan)) {
        $errors[] = "Pilihan sekolah harus diisi.";
    } elseif (!in_array($pilihan, $allowed_pilihan)) {
        $errors[] = "Pilihan sekolah tidak valid.";
    }

    // Jika tidak ada error, lanjutkan ke proses insert
    if (empty($errors)) {
        // Menyiapkan statement SQL dengan prepared statements
        $stmt = $conn->prepare("INSERT INTO calon_pendaftar (nama, asal_sekolah, email, no_hp, alamat, pilihan) VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            // Mengikat parameter ke statement
            $stmt->bind_param("ssssss", $nama, $asal_sekolah, $email, $no_hp, $alamat, $pilihan);

            // Menjalankan statement
            if ($stmt->execute()) {
                // Sukses: Redirect atau tampilkan pesan sukses
                // Misalnya, redirect ke halaman sukses:
                header("Location: sukses_pendaftaran.php");
                exit();
            } else {
                // Gagal menjalankan statement
                echo "Terjadi kesalahan saat menyimpan data: " . $stmt->error;
            }

            // Menutup statement
            $stmt->close();
        } else {
            // Gagal menyiapkan statement
            echo "Terjadi kesalahan saat mempersiapkan query: " . $conn->error;
        }
    } else {
        // Jika ada error, tampilkan error
        // Anda dapat menampilkan error ini di halaman form atau menggunakan session flash messages
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
        echo "<p><a href='pendaftaran_awal.php'>Kembali ke form pendaftaran</a></p>";
    }
} else {
    // Jika form tidak disubmit, redirect ke form pendaftaran
    header("Location: pendaftaran_awal.php");
    exit();
}

// Menutup koneksi
$conn->close();
?>
