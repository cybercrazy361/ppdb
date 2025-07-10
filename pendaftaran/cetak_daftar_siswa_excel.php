<?php
require '../vendor/autoload.php'; // composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit'] ?? '';
$search = trim($_GET['q'] ?? '');

// Query filter
$searchSql = '';
$searchParam = '';
if ($search !== '') {
    $searchSql = "AND (
        s.no_formulir LIKE ? OR
        s.no_invoice LIKE ? OR
        s.nama LIKE ?
    )";
    $searchParam = '%' . $search . '%';
}

$query = "
SELECT 
    s.*, 
    cp.status AS status_pendaftaran,
    COALESCE(
        (SELECT p.metode_pembayaran 
         FROM pembayaran p 
         WHERE p.siswa_id = s.id 
         ORDER BY p.tanggal_pembayaran DESC 
         LIMIT 1),
        'Belum Ada'
    ) AS metode_pembayaran
FROM siswa s
LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
WHERE s.unit = ?
$searchSql
ORDER BY s.id DESC
";

if ($search !== '') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssss', $unit, $searchParam, $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $unit);
}
$stmt->execute();
$result = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul kolom
$sheet->setCellValue('A1', 'No');
$sheet->setCellValue('B1', 'No Formulir');
$sheet->setCellValue('C1', 'Nama');
$sheet->setCellValue('D1', 'Jenis Kelamin');
$sheet->setCellValue('E1', 'Tempat/Tgl Lahir');
$sheet->setCellValue('F1', 'Asal Sekolah');
$sheet->setCellValue('G1', 'Alamat');
$sheet->setCellValue('H1', 'No HP');
$sheet->setCellValue('I1', 'No HP Ortu');
$sheet->setCellValue('J1', 'Progres Pembayaran');
$sheet->setCellValue('K1', 'Metode Pembayaran');
$sheet->setCellValue('L1', 'Tgl Pendaftaran');
$sheet->setCellValue('M1', 'Status Pendaftaran');

$rowNum = 2;
$no = 1;
while ($row = $result->fetch_assoc()) {
    $ttl = $row['tempat_lahir'] . ', ' . ($row['tanggal_lahir'] ?? '-');
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['no_invoice'] ?? '');
    $sheet->setCellValue('C' . $rowNum, $row['nama'] ?? '');
    $sheet->setCellValue('D' . $rowNum, $row['jenis_kelamin'] ?? '');
    $sheet->setCellValue('E' . $rowNum, $ttl);
    $sheet->setCellValue('F' . $rowNum, $row['asal_sekolah'] ?? '');
    $sheet->setCellValue('G' . $rowNum, $row['alamat'] ?? '');
    $sheet->setCellValue('H' . $rowNum, $row['no_hp'] ?? '');
    $sheet->setCellValue('I' . $rowNum, $row['no_hp_ortu'] ?? '');
    $sheet->setCellValue('J' . $rowNum, $row['status_pembayaran'] ?? '');
    $sheet->setCellValue('K' . $rowNum, $row['metode_pembayaran'] ?? '');
    $sheet->setCellValue('L' . $rowNum, $row['tanggal_pendaftaran'] ?? '');
    $sheet->setCellValue('M' . $rowNum, $row['status_pendaftaran'] ?? '');
    $rowNum++;
}

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);
header(
    'Content-Disposition: attachment;filename="daftar_siswa_' . $unit . '.xlsx"'
);
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
