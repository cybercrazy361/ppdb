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
  <title>Bukti Pendaftaran Siswa Baru</title>
  <style>
    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      background: #f6f8fa;
    }
    .container {
      width: 650px;
      margin: 24px auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 0 8px rgba(60,60,60,0.13);
      padding: 36px 36px 20px 36px;
    }
    .header {
      text-align: center;
      border-bottom: 2px solid #d1d5db;
      margin-bottom: 24px;
      padding-bottom: 14px;
    }
    .logo {
      width: 74px; height: 74px; object-fit: contain;
      margin-bottom: 6px;
    }
    .sekolah-title {
      font-size: 21px; font-weight: 700; color: #29367d;
      text-transform: uppercase;
    }
    .alamat {
      color: #444; font-size: 13px; margin-top: 2px; margin-bottom: 0;
    }
    .bukti-title {
      text-align: center;
      font-size: 19px;
      font-weight: bold;
      margin: 24px 0 22px 0;
      letter-spacing: 1px;
      color: #1c2b5a;
    }
    table.data-siswa {
      width: 100%;
      font-size: 16px;
      border-collapse: collapse;
      margin-bottom: 18px;
    }
    table.data-siswa td {
      padding: 8px 10px 8px 0;
      border: none;
      vertical-align: top;
    }
    table.data-siswa td.label {
      width: 36%; color: #222; font-weight: 600;
    }
    .catatan {
      font-size: 14px; margin-top: 12px; margin-bottom: 22px; color: #555;
      background: #f3f4f6;
      border-left: 4px solid #3b82f6;
      padding: 10px 18px;
    }
    .ttd-box {
      display: flex; justify-content: flex-end; margin-top: 36px;
    }
    .ttd {
      text-align: left;
      margin-right: 50px;
    }
    .ttd .tgl {
      font-size: 15px;
    }
    .ttd .petugas {
      margin-top: 60px;
      font-weight: bold;
      border-top: 1px dashed #666;
      padding-top: 4px;
      text-align: center;
      font-size: 16px;
    }
    @media print {
      body { background: #fff; }
      .container { box-shadow: none; border: none; }
    }
  </style>
</head>
<body onload="window.print()">
  <div class="container">
    <div class="header">
      <!-- Ganti src logo dengan logo sekolah -->
      <img src="../assets/logo_sekolah.png" alt="Logo" class="logo" onerror="this.style.display='none'">
      <div class="sekolah-title">SMA / SMK CONTOH MULIA</div>
      <div class="alamat">Jl. Contoh Alamat No.123, Kota Contoh, Telp: 0812-XXXX-XXXX</div>
    </div>
    <div class="bukti-title">
      Bukti Pendaftaran Siswa Baru<br>
      Tahun Pelajaran <?= date('Y') . "/" . (date('Y')+1) ?>
    </div>
    <table class="data-siswa">
      <tr>
        <td class="label">No Formulir</td>
        <td>: <?= safe($row['no_formulir']) ?></td>
      </tr>
      <tr>
        <td class="label">Nama Lengkap</td>
        <td>: <?= safe($row['nama']) ?></td>
      </tr>
      <tr>
        <td class="label">Jenis Kelamin</td>
        <td>: <?= safe($row['jenis_kelamin']) ?></td>
      </tr>
      <tr>
        <td class="label">Tempat, Tanggal Lahir</td>
        <td>: <?= safe($row['tempat_lahir']) ?>, <?= tanggal_id($row['tanggal_lahir']) ?></td>
      </tr>
      <tr>
        <td class="label">Asal Sekolah</td>
        <td>: <?= safe($row['asal_sekolah']) ?></td>
      </tr>
      <tr>
        <td class="label">Alamat</td>
        <td>: <?= safe($row['alamat']) ?></td>
      </tr>
      <tr>
        <td class="label">No. HP Siswa</td>
        <td>: <?= safe($row['no_hp']) ?></td>
      </tr>
      <tr>
        <td class="label">No. HP Orang Tua/Wali</td>
        <td>: <?= safe($row['no_hp_ortu']) ?></td>
      </tr>
      <tr>
        <td class="label">Unit Pilihan</td>
        <td>: <?= safe($row['unit']) ?></td>
      </tr>
      <tr>
        <td class="label">Tanggal Pendaftaran</td>
        <td>: <?= tanggal_id($row['tanggal_pendaftaran']) ?></td>
      </tr>
    </table>
    <div class="catatan">
      <b>Catatan:</b><br>
      Bukti ini agar disimpan dan dibawa saat daftar ulang.<br>
      Pastikan semua data telah sesuai. Untuk perbaikan data silakan hubungi panitia PPDB.
    </div>
    <div class="ttd-box">
      <div class="ttd">
        <div class="tgl">................., <?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="petugas">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>
</body>
</html>
