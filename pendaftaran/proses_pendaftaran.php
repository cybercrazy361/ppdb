<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_formulir = $_POST['no_formulir'];
    $nama = $_POST['nama'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $asal_sekolah = $_POST['asal_sekolah'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];

    // Status pembayaran default = Pending
    $status_pembayaran = 'Pending';

    // Ambil unit dari sesi login
    $unit = $_SESSION['unit']; // Pastikan unit disimpan di sesi saat login

    // Simpan data ke tabel siswa
    $sql = "INSERT INTO siswa (no_formulir, nama, tempat_lahir, tanggal_lahir, asal_sekolah, alamat, no_hp, status_pembayaran, unit)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssssss', $no_formulir, $nama, $tempat_lahir, $tanggal_lahir, $asal_sekolah, $alamat, $no_hp, $status_pembayaran, $unit);

    if ($stmt->execute()) {
        echo "<script>alert('Pendaftaran berhasil!'); window.location.href='dashboard_pendaftaran.php';</script>";
    } else {
        echo "<script>alert('Pendaftaran gagal: " . $stmt->error . "'); history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
