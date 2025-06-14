<?php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

// Cek login dan role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

// Ambil data ID calon pendaftar dari POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid!']);
    exit();
}

// 1. Cek apakah sudah ada di siswa (anti dobel)
$stmt = $conn->prepare('SELECT id FROM siswa WHERE calon_pendaftar_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode([
        'success' => false,
        'message' => 'Siswa tersebut sudah terkirim ke tim pendaftaran.',
    ]);
    exit();
}
$stmt->close();

// 2. Ambil data lengkap calon_pendaftar (semua field)
$stmt = $conn->prepare('SELECT * FROM calon_pendaftar WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Data calon tidak ditemukan!',
    ]);
    exit();
}
$calon = $res->fetch_assoc();
$stmt->close();

// Siapkan semua field yang dipakai di siswa
$unit = strtoupper($calon['pilihan']);
$nama = $calon['nama'] ?? '-';
$jenis_kelamin = $calon['jenis_kelamin'] ?? 'Laki-laki';
$tempat_lahir = $calon['tempat_lahir'] ?? '-';
$tanggal_lahir =
    $calon['tanggal_lahir'] && $calon['tanggal_lahir'] != '0000-00-00'
        ? $calon['tanggal_lahir']
        : date('Y-m-d');
$asal_sekolah = $calon['asal_sekolah'] ?? '-';
$alamat = $calon['alamat'] ?? '-';
$no_hp = $calon['no_hp'] ?? '-';
$no_hp_ortu = $calon['no_hp_ortu'] ?? '-';
$tanggal_pendaftaran = date(
    'Y-m-d',
    strtotime($calon['tanggal_daftar'] ?? date('Y-m-d'))
);

// === Ambil nama petugas callcenter ===
$reviewed_by = $_SESSION['nama'] ?? ($_SESSION['username'] ?? '-');
// Jika mau catat waktu review juga:
$reviewed_at = date('Y-m-d H:i:s'); // Pastikan kolom ini ada jika dipakai

// 3. Generate no_formulir unik (format: REGdmY001)
$tgl = date('d');
$bln = date('m');
$thn = date('Y');
$prefix = 'REG' . date('dmY'); // Contoh: REG29052025

// Cari urutan hari ini
$stmtUrut = $conn->prepare(
    "SELECT MAX(no_formulir) as maxf FROM siswa WHERE no_formulir LIKE CONCAT(?, '%')"
);
$stmtUrut->bind_param('s', $prefix);
$stmtUrut->execute();
$maxData = $stmtUrut->get_result()->fetch_assoc();
$stmtUrut->close();

$no_urut = 1;
if ($maxData && $maxData['maxf']) {
    $angka = intval(substr($maxData['maxf'], 11, 3)); // <--- Perhatikan index berubah jadi 11
    $no_urut = $angka + 1;
}
$no_formulir = $prefix . str_pad($no_urut, 3, '0', STR_PAD_LEFT);

// 4. Masukkan ke tabel siswa
$stmt = $conn->prepare("
    INSERT INTO siswa
    (no_formulir, nama, unit, jenis_kelamin, tempat_lahir, tanggal_lahir, asal_sekolah, alamat, no_hp, no_hp_ortu, tanggal_pendaftaran, calon_pendaftar_id, reviewed_by, reviewed_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    'sssssssssssiss',
    $no_formulir,
    $nama,
    $unit,
    $jenis_kelamin,
    $tempat_lahir,
    $tanggal_lahir,
    $asal_sekolah,
    $alamat,
    $no_hp,
    $no_hp_ortu,
    $tanggal_pendaftaran,
    $id,
    $reviewed_by,
    $reviewed_at
);

if ($stmt->execute()) {
    // 5. Update status calon_pendaftar
    $stmt2 = $conn->prepare(
        "UPDATE calon_pendaftar SET status = 'PPDB Bersama' WHERE id = ?"
    );
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode([
        'success' => true,
        'message' => 'Siswa Berhasil Dikirim ke tim Pendaftaran',
        'no_formulir' => $no_formulir,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal insert ke siswa: ' . $stmt->error,
    ]);
}
$stmt->close();
?>
