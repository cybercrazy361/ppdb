<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    die('Akses tidak diizinkan.');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die('ID siswa tidak valid.');

$stmt = $conn->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die('Data siswa tidak ditemukan.');

function safe($str) { return htmlspecialchars($str ?? '-'); }

// Tanggal Indonesia
function tanggal_id($tgl) {
    if (!$tgl || $tgl == '0000-00-00') return '-';
    $bulan = [
        'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni',
        'July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $date = date('d', strtotime($tgl));
    $month = $bulan[date('F', strtotime($tgl))];
    $year = date('Y', strtotime($tgl));
    return "$date $month $year";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Data Calon Peserta Didik Baru</title>
  <style>
    body { font-family: Arial, sans-serif; }
    table { border-collapse: collapse; width: 80%; margin: 20px auto; }
    th, td { border: 1px solid #222; padding: 8px 10px; font-size: 16px; }
    th { background: #eaf2ce; font-weight: bold; text-align: left;}
    caption { font-size: 20px; font-weight: bold; margin-bottom: 10px;}
    .judul { background: #eaf2ce; font-size: 18px; font-weight: bold; text-align: center; }
  </style>
</head>
<body onload="window.print()">
  <table>
    <tr>
      <th colspan="2" class="judul">DATA CALON PESERTA DIDIK BARU</th>
    </tr>
    <tr>
      <td>No Formulir</td>
      <td><?= safe($row['no_formulir']) ?></td>
    </tr>
    <tr>
      <td>Tanggal Pendaftaran</td>
      <td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td>
    </tr>
    <tr>
      <td>Nama Lengkap</td>
      <td><?= safe($row['nama']) ?></td>
    </tr>
    <tr>
      <td>Jenis Kelamin</td>
      <td><?= safe($row['jenis_kelamin']) ?></td>
    </tr>
    <tr>
      <td>Tempat, Tanggal Lahir</td>
      <td><?= safe($row['tempat_lahir']) ?>, <?= tanggal_id($row['tanggal_lahir']) ?></td>
    </tr>
    <tr>
      <td>Asal Sekolah</td>
      <td><?= safe($row['asal_sekolah']) ?></td>
    </tr>
    <tr>
      <td>Alamat</td>
      <td><?= safe($row['alamat']) ?></td>
    </tr>
    <tr>
      <td>No. HP Siswa</td>
      <td><?= safe($row['no_hp']) ?></td>
    </tr>
    <tr>
      <td>No. HP Orang Tua/Wali</td>
      <td><?= safe($row['no_hp_ortu']) ?></td>
    </tr>
    <tr>
      <td>Unit Pilihan</td>
      <td><?= safe($row['unit']) ?></td>
    </tr>
  </table>
</body>
</html>
