<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: form_pendaftaran.php');
    exit();
}

// Ambil dan bersihkan input
$no_formulir   = trim($_POST['no_formulir']   ?? '');
$no_invoice    = trim($_POST['no_invoice']    ?? '');
$nama          = trim($_POST['nama']          ?? '');
$jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
$tempat_lahir  = trim($_POST['tempat_lahir']  ?? '');
$tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
$asal_sekolah  = trim($_POST['asal_sekolah']  ?? '');
$no_hp         = trim($_POST['no_hp']         ?? '');
$no_hp_ortu    = trim($_POST['no_hp_ortu']    ?? '');
$alamat        = trim($_POST['alamat']        ?? '');

// Validasi setiap field wajib
$fields = [
    'No Formulir'      => $no_formulir,
    'No Invoice'       => $no_invoice,
    'Nama Lengkap'     => $nama,
    'Jenis Kelamin'    => $jenis_kelamin,
    'Tempat Lahir'     => $tempat_lahir,
    'Tanggal Lahir'    => $tanggal_lahir,
    'Asal Sekolah'     => $asal_sekolah,
    'No HP Siswa'      => $no_hp,
    'No HP Orang Tua'  => $no_hp_ortu,
    'Alamat Lengkap'   => $alamat,
];

foreach ($fields as $label => $value) {
    if ($value === '') {
        $_SESSION['error_message'] = "Kolom “{$label}” wajib diisi.";
        header('Location: form_pendaftaran.php');
        exit();
    }
}

// Semua validasi lolos, siapkan simpan ke database
$status_pembayaran = 'Pending';
$unit = $_SESSION['unit'];

// Prepare SQL INSERT (dengan no_formulir dan no_invoice)
$sql = "
    INSERT INTO siswa (
        no_formulir,
        no_invoice,
        nama,
        jenis_kelamin,
        tempat_lahir,
        tanggal_lahir,
        asal_sekolah,
        no_hp,
        no_hp_ortu,
        alamat,
        status_pembayaran,
        unit
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error_message'] = "Gagal mempersiapkan database: " . $conn->error;
    header('Location: form_pendaftaran.php');
    exit();
}

$stmt->bind_param(
    'ssssssssssss',
    $no_formulir,
    $no_invoice,
    $nama,
    $jenis_kelamin,
    $tempat_lahir,
    $tanggal_lahir,
    $asal_sekolah,
    $no_hp,
    $no_hp_ortu,
    $alamat,
    $status_pembayaran,
    $unit
);

if ($stmt->execute()) {
    echo "<script>
            alert('Pendaftaran berhasil!');
            window.location.href = 'dashboard_pendaftaran.php';
          </script>";
} else {
    $_SESSION['error_message'] = "Pendaftaran gagal: " . $stmt->error;
    header('Location: form_pendaftaran.php');
}

$stmt->close();
$conn->close();
?>
