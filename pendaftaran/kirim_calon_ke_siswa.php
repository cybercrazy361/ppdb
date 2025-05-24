<?php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

// Cek login dan role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

$data = $_POST;

// Validasi sederhana
$fields = ['id','nama','jenis_kelamin','asal_sekolah','no_hp','alamat','pendidikan_ortu','no_hp_ortu','tanggal_daftar','unit'];
foreach ($fields as $f) {
    if (!isset($data[$f])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }
}

$nama           = $data['nama'];
$jenis_kelamin  = $data['jenis_kelamin'];
$asal_sekolah   = $data['asal_sekolah'];
$no_hp          = $data['no_hp'];
$alamat         = $data['alamat'];
$pendidikan_ortu= $data['pendidikan_ortu'];
$no_hp_ortu     = $data['no_hp_ortu'];
$tanggal_daftar = $data['tanggal_daftar'];
$unit           = $data['unit'];

// Cek apakah sudah ada (supaya tidak double)
$stmt = $conn->prepare("SELECT COUNT(*) as jml FROM siswa WHERE nama=? AND tanggal_pendaftaran=?");
$stmt->bind_param("ss", $nama, $tanggal_daftar);
$stmt->execute();
$res = $stmt->get_result();
$cek = $res->fetch_assoc()['jml'] ?? 0;
$stmt->close();
if ($cek > 0) {
    echo json_encode(['success' => false, 'message' => 'Sudah terkirim.']);
    exit();
}

// Generate no_formulir unik (misal: SMA20240001)
$prefix = strtoupper($unit);
$no_urut = 1;
$max = $conn->query("SELECT MAX(no_formulir) as maxf FROM siswa WHERE unit='$unit' AND no_formulir LIKE '$prefix%'")->fetch_assoc()['maxf'];
if ($max) {
    $angka = intval(substr($max, strlen($prefix)));
    $no_urut = $angka + 1;
}
$no_formulir = $prefix . date('Y') . str_pad($no_urut, 4, '0', STR_PAD_LEFT);

// Insert ke tabel siswa
$stmt = $conn->prepare("INSERT INTO siswa
    (no_formulir, nama, unit, jenis_kelamin, asal_sekolah, alamat, no_hp, no_hp_ortu, tanggal_pendaftaran)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "sssssssss",
    $no_formulir, $nama, $unit, $jenis_kelamin, $asal_sekolah, $alamat, $no_hp, $no_hp_ortu, $tanggal_daftar
);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'no_formulir' => $no_formulir]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal insert data siswa']);
}
$stmt->close();
?>
