<?php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

// Ambil data ID calon pendaftar dari POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid!']);
    exit();
}

// 1. Cek apakah sudah ada di siswa (anti dobel!)
$stmt = $conn->prepare("SELECT id FROM siswa WHERE calon_pendaftar_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Sudah terkirim ke siswa.']);
    exit();
}
$stmt->close();

// 2. Ambil data lengkap calon pendaftar
$stmt = $conn->prepare("SELECT * FROM calon_pendaftar WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Data calon tidak ditemukan!']);
    exit();
}
$calon = $res->fetch_assoc();
$stmt->close();

// 3. Generate no_formulir (misal: SMA20240001)
$unit = strtoupper($calon['pilihan']);
$max = $conn->query("SELECT MAX(no_formulir) as maxf FROM siswa WHERE unit='$unit' AND no_formulir LIKE '$unit%'")->fetch_assoc()['maxf'];
$no_urut = 1;
if ($max) {
    $angka = intval(substr($max, strlen($unit)));
    $no_urut = $angka + 1;
}
$no_formulir = $unit . date('Y') . str_pad($no_urut, 4, '0', STR_PAD_LEFT);

// 4. Masukkan ke tabel siswa
$stmt = $conn->prepare("INSERT INTO siswa
    (no_formulir, nama, unit, jenis_kelamin, tempat_lahir, tanggal_lahir, asal_sekolah, alamat, no_hp, no_hp_ortu, tanggal_pendaftaran, calon_pendaftar_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "sssssssssssi",
    $no_formulir,
    $calon['nama'],
    $calon['pilihan'],
    $calon['jenis_kelamin'],
    $calon['tempat_lahir'],
    $calon['tanggal_lahir'] ?? date('Y-m-d'),
    $calon['asal_sekolah'],
    $calon['alamat'],
    $calon['no_hp'],
    $calon['no_hp_ortu'],
    date('Y-m-d', strtotime($calon['tanggal_daftar'])),
    $calon['id']
);

if ($stmt->execute()) {
    // 5. Update status calon_pendaftar
    $stmt2 = $conn->prepare("UPDATE calon_pendaftar SET status = 'PPDB Bersama' WHERE id = ?");
    $stmt2->bind_param("i", $calon['id']);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode(['success' => true, 'message' => 'Data berhasil dikirim ke siswa', 'no_formulir' => $no_formulir]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal insert ke siswa: ' . $stmt->error]);
}
$stmt->close();
?>
