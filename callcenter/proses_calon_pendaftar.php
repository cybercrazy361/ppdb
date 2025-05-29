<?php
session_start();
include '../database_connection.php';

// Cek session dan role call center
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    header('Location: login_callcenter.php');
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("CSRF Token Tidak Valid.");
}

// Ambil data dari form, dan lakukan sanitasi dasar
function input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$nama            = input($_POST['nama'] ?? '');
$jenis_kelamin   = input($_POST['jenis_kelamin'] ?? '');
$asal_sekolah    = input($_POST['asal_sekolah'] ?? '');
$no_hp           = input($_POST['no_hp'] ?? '');
$alamat          = input($_POST['alamat'] ?? '');
$pendidikan_ortu = input($_POST['pendidikan_ortu'] ?? '');
$no_hp_ortu      = input($_POST['no_hp_ortu'] ?? '');
$pilihan         = input($_POST['pilihan'] ?? '');

// Validasi data wajib
if (
    empty($nama) || empty($jenis_kelamin) || empty($asal_sekolah) ||
    empty($no_hp) || empty($alamat) || empty($pendidikan_ortu) ||
    empty($no_hp_ortu) || empty($pilihan)
) {
    header('Location: input_progres_pendaftaran.php?error=empty');
    exit();
}

// SQL Insert
$stmt = $conn->prepare("INSERT INTO calon_pendaftar 
    (nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Tidak Ada Konfirmasi', NOW())");
$stmt->bind_param(
    "ssssssss",
    $nama, $jenis_kelamin, $asal_sekolah, $no_hp, $alamat, $pendidikan_ortu, $no_hp_ortu, $pilihan
);

if ($stmt->execute()) {
    $_SESSION['success_pendaftaran'] = true;
    header('Location: sukses_pendaftaran.php');
    exit();
} else {
    // Jika gagal simpan, arahkan kembali ke form dengan pesan error
    header('Location: input_progres_pendaftaran.php?error=gagal');
    exit();
}

$stmt->close();
$conn->close();
?>
