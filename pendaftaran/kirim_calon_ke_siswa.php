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
$fields = [
    'id','nama','jenis_kelamin','asal_sekolah','no_hp',
    'alamat','pendidikan_ortu','no_hp_ortu','tanggal_daftar','unit'
];
foreach ($fields as $f) {
    if (!isset($data[$f])) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit();
    }
}

// Ambil data
$nama            = trim($data['nama']);
$jenis_kelamin   = trim($data['jenis_kelamin']);
$asal_sekolah    = trim($data['asal_sekolah']);
$no_hp           = trim($data['no_hp']);
$alamat          = trim($data['alamat']);
$pendidikan_ortu = trim($data['pendidikan_ortu']);
$no_hp_ortu      = trim($data['no_hp_ortu']);
$tanggal_daftar  = trim($data['tanggal_daftar']);
$unit            = trim($data['unit']);

// --- Cek apakah sudah ada (anti duplikat) ---
// Cek kombinasi: nama + no_hp_ortu + unit
$stmt = $conn->prepare("SELECT COUNT(*) as jml FROM siswa WHERE nama=? AND no_hp_ortu=? AND unit=?");
$stmt->bind_param("sss", $nama, $no_hp_ortu, $unit);
$stmt->execute();
$res = $stmt->get_result();
$cek = $res->fetch_assoc()['jml'] ?? 0;
$stmt->close();
if ($cek > 0) {
    echo json_encode(['success' => false, 'message' => 'Data sudah ada di siswa, tidak boleh dobel!']);
    exit();
}

// --- Generate no_formulir unik (misal: SMA20240001) ---
$prefix = strtoupper($unit);
$no_urut = 1;
$max = $conn->query("SELECT MAX(no_formulir) as maxf FROM siswa WHERE unit='$unit' AND no_formulir LIKE '$prefix%'")->fetch_assoc()['maxf'];
if ($max) {
    $angka = intval(substr($max, strlen($prefix)));
    $no_urut = $angka + 1;
}
$no_formulir = $prefix . date('Y') . str_pad($no_urut, 4, '0', STR_PAD_LEFT);

// --- Insert ke tabel siswa, pakai tanggal dari form (bukan default) ---
$stmt = $conn->prepare("INSERT INTO siswa
    (no_formulir, nama, unit, jenis_kelamin, asal_sekolah, alamat, no_hp, no_hp_ortu, tanggal_pendaftaran, pendidikan_ortu)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ssssssssss",
    $no_formulir, $nama, $unit, $jenis_kelamin, $asal_sekolah, $alamat, $no_hp, $no_hp_ortu, $tanggal_daftar, $pendidikan_ortu
);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'no_formulir' => $no_formulir]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal insert data siswa']);
}
$stmt->close();
?>
