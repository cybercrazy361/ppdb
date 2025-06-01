<?php
session_start();
include '../database_connection.php';

// =========================
// SETUP PHPSpreadsheet
// =========================
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Set output JSON
header('Content-Type: application/json');

// =========================
// 1. Validasi session & role
// =========================
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit;
}

// =========================
// 2. Ambil unit petugas
// =========================
$unit = null;
if (isset($_SESSION['unit'])) {
    $unit = $_SESSION['unit'];
} else {
    // fallback: query dari DB
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($unit);
    $stmt->fetch();
    $stmt->close();
}
if (!$unit) {
    echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan!']);
    exit;
}

// =========================
// 3. Proses file upload
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Validasi header
        $header = $rows[0];
        $expected = [
            'Nama', 'Jenis Kelamin', 'Asal Sekolah', 'No HP',
            'Alamat', 'Pendidikan Ortu', 'No HP Ortu', 'Tanggal Daftar'
        ];
        foreach ($expected as $i => $exp) {
            if (strtolower(trim($header[$i])) !== strtolower($exp)) {
                echo json_encode(['success' => false, 'message' => 'Header Excel tidak sesuai!<br>Header yang benar:<br><b>'.implode(' | ', $expected).'</b>']);
                exit;
            }
        }

        // Import data
        $count = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            // Skip baris kosong
            if (!array_filter($row)) continue;

            // Data
            $nama             = trim($row[0] ?? '');
            $jenis_kelamin    = trim($row[1] ?? '');
            $asal_sekolah     = trim($row[2] ?? '');
            $no_hp            = trim($row[3] ?? '');
            $alamat           = trim($row[4] ?? '');
            $pendidikan_ortu  = trim($row[5] ?? '');
            $no_hp_ortu       = trim($row[6] ?? '');
            $tanggal_daftar   = trim($row[7] ?? '');

            // Validasi minimal nama dan tanggal_daftar
            if ($nama === '' || $tanggal_daftar === '') continue;

            $stmt = $conn->prepare("INSERT INTO calon_pendaftar
                (nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, tanggal_daftar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "ssssssss",
                $nama,
                $jenis_kelamin,
                $asal_sekolah,
                $no_hp,
                $alamat,
                $pendidikan_ortu,
                $no_hp_ortu,
                $unit,          // <-- pilihan ISI OTOMATIS dari session
                $tanggal_daftar
            );
            if ($stmt->execute()) $count++;
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => "Berhasil mengimpor <b>$count</b> data."]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'File error: '.$e->getMessage()]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload!']);
    exit;
}
