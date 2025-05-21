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
      <td>Tanggal Pendaftaran</td>
      <td><?= safe($row['tanggal_pendaftaran']) ?></td>
    </tr>
    <tr>
      <td>Nama Calon Peserta Didik</td>
      <td><?= safe($row['nama']) ?></td>
    </tr>
    <tr>
      <td>Jenis Kelamin</td>
      <td><?= safe($row['jenis_kelamin']) ?></td>
    </tr>
    <tr>
      <td>Asal Sekolah SMP/MTs</td>
      <td><?= safe($row['asal_sekolah']) ?></td>
    </tr>
    <tr>
      <td>Nama Orang Tua/Wali</td>
      <td><?= safe($row['nama_ortu'] ?? $row['nama_ortu_wali'] ?? '-') ?></td>
    </tr>
    <tr>
      <td>Pendidikan Terakhir</td>
      <td><?= safe($row['pendidikan_terakhir'] ?? '-') ?></td>
    </tr>
    <tr>
      <td>Pekerjaan Orang Tua/Wali</td>
      <td><?= safe($row['pekerjaan_ortu'] ?? '-') ?></td>
    </tr>
    <tr>
      <td>Alamat Rumah</td>
      <td><?= safe($row['alamat']) ?></td>
    </tr>
    <tr>
      <td>No. HP Orang Tua/Wali</td>
      <td><?= safe($row['no_hp_ortu'] ?? '-') ?></td>
    </tr>
    <tr>
      <td>No. HP Siswa</td>
      <td><?= safe($row['no_hp']) ?></td>
    </tr>
    <tr>
      <td>Pilihan Sekolah/Jurusan</td>
      <td><?= safe($row['unit']) ?></td>
    </tr>
    <tr>
      <td>Email</td>
      <td><?= safe($row['email'] ?? '-') ?></td>
    </tr>
  </table>
</body>
</html>
