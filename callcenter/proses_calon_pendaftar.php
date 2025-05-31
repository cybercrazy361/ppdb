<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Kolom 'email' di tabel harus diisi (NOT NULL)
$email           = "-";
if (isset($_POST['email']) && !empty(trim($_POST['email']))) {
    $email = input($_POST['email']);
}

// Validasi data wajib
if (
    empty($nama) || empty($jenis_kelamin) || empty($asal_sekolah) ||
    empty($no_hp) || empty($alamat) || empty($pendidikan_ortu) ||
    empty($no_hp_ortu) || empty($pilihan)
) {
    header('Location: input_progres_pendaftaran.php?error=empty');
    exit();
}

// SQL Insert TANPA kolom 'created_at', gunakan field yang BENAR
$stmt = $conn->prepare("INSERT INTO calon_pendaftar 
    (nama, jenis_kelamin, asal_sekolah, email, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Tidak Ada Konfirmasi')");
$stmt->bind_param(
    "sssssssss",
    $nama, $jenis_kelamin, $asal_sekolah, $email, $no_hp, $alamat, $pendidikan_ortu, $no_hp_ortu, $pilihan
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
