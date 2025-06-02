<?php
// receive_from_gsheet.php

file_put_contents(__DIR__.'/debug_receive.txt', json_encode($_POST) . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json');
include '../database_connection.php';

// Validasi hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

// Helper functions
function formatHp($hp) {
    $hp = trim($hp);
    $hp = preg_replace('/[^0-9]/', '', ltrim($hp, '+'));
    if (substr($hp, 0, 2) === '62') {
        return $hp;
    } elseif (substr($hp, 0, 1) === '0') {
        return '62' . substr($hp, 1);
    } elseif (substr($hp, 0, 1) === '8') {
        return '62' . $hp;
    }
    return $hp;
}
function validJenisKelamin($jk) {
    $jk = strtolower(trim($jk));
    if ($jk === 'laki-laki' || $jk === 'l') return 'Laki-laki';
    if ($jk === 'perempuan' || $jk === 'p') return 'Perempuan';
    return null;
}
function normalisasiPendidikan($input) {
    $str = strtolower(str_replace(['-', '.', ','], ['', '', '/'], $input));
    $str = preg_replace('/\s+/', '', $str);
    if (strpos($str, 'sd') !== false) return 'SD/Sederajat';
    if (strpos($str, 'smp') !== false || strpos($str, 'mts') !== false) return 'SMP/Sederajat';
    if (strpos($str, 'sma') !== false || strpos($str, 'smk') !== false || strpos($str, 'ma') !== false) return 'SMA/Sederajat';
    if (strpos($str, 'd3') !== false) return 'D3';
    if (strpos($str, 'd1') !== false || strpos($str, 'd2') !== false) return 'D3';
    if (strpos($str, 's1') !== false) return 'S1';
    if (strpos($str, 's2') !== false) return 'S2';
    if (strpos($str, 's3') !== false) return 'S3';
    return 'Lainnya';
}
function parseTanggalDaftar($tgl) {
    $tgl = trim($tgl);
    // Jika format: m/d/Y atau m/d/yy (dari Google Sheet/Forms, region default US)
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $tgl, $m)) {
        // US region biasanya: month/day/year
        $mth = intval($m[1]);
        $day = intval($m[2]);
        $yr  = intval($m[3]);
        if ($yr < 100) $yr += 2000;
        return sprintf('%04d-%02d-%02d', $yr, $mth, $day);
    }
    // Jika format: yyyy-mm-dd
    if (preg_match('#^\d{4}-\d{2}-\d{2}#', $tgl)) return $tgl;
    $time = strtotime($tgl);
    if ($time) return date('Y-m-d', $time);
    return null;
}

// Ambil data dari POST
$nama            = trim($_POST['nama'] ?? '');
$jenis_kelamin   = validJenisKelamin($_POST['jenis_kelamin'] ?? '');
$asal_sekolah    = trim($_POST['asal_sekolah'] ?? '');
$no_hp           = formatHp(trim($_POST['no_hp'] ?? ''));
$alamat          = trim($_POST['alamat'] ?? '');
$pendidikan_ortu = normalisasiPendidikan(trim($_POST['pendidikan_ortu'] ?? ''));
$no_hp_ortu      = formatHp(trim($_POST['no_hp_ortu'] ?? ''));
$tanggal_daftar  = parseTanggalDaftar(trim($_POST['tanggal_daftar'] ?? ''));
$unit            = trim($_POST['unit'] ?? ''); // <- AMBIL LANGSUNG DARI SPREADSHEET

// Validasi minimal wajib
if ($nama === '' || !$jenis_kelamin || $tanggal_daftar === null) {
    echo json_encode(['success' => false, 'message' => 'Data wajib (nama/jenis_kelamin/tanggal_daftar) kosong atau salah']);
    exit;
}
if (!preg_match('/^628[0-9]{7,13}$/', $no_hp) || !preg_match('/^628[0-9]{7,13}$/', $no_hp_ortu)) {
    echo json_encode(['success' => false, 'message' => 'Format no HP/Ortu salah']);
    exit;
}
if ($unit === '') {
    echo json_encode(['success' => false, 'message' => 'Unit/pilihan wajib diisi!']);
    exit;
}

// Cek data double
$stmt = $conn->prepare("SELECT COUNT(*) as jml FROM calon_pendaftar WHERE nama=? AND tanggal_daftar=?");
$stmt->bind_param("ss", $nama, $tanggal_daftar);
$stmt->execute();
$cek = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($cek['jml'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Data sudah ada sebelumnya!']);
    exit;
}

// Insert ke database
$stmt = $conn->prepare("INSERT INTO calon_pendaftar
    (nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, tanggal_daftar)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
    "sssssssss",
    $nama,
    $jenis_kelamin,
    $asal_sekolah,
    $no_hp,
    $alamat,
    $pendidikan_ortu,
    $no_hp_ortu,
    $unit, // <- SIMPAN SESUAI YANG DIPILIH DI SPREADSHEET
    $tanggal_daftar
);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Data berhasil disimpan!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal simpan: '.$stmt->error]);
}
$stmt->close();
