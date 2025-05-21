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

// Ambil nama petugas (dari database jika ada field nama, atau dari username)
$petugas = isset($_SESSION['nama_petugas']) ? $_SESSION['nama_petugas'] : $_SESSION['username'];

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
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f8fa; }
    .container {
      width: 800px; margin: 22px auto; background: #fff;
      border-radius: 10px; box-shadow: 0 0 8px rgba(60,60,60,0.13);
      padding: 28px 34px 24px 34px; border: 1px solid #d1dbe7;
    }
    .header {
      text-align: center; border-bottom: 2px solid #d1d5db; margin-bottom: 18px; padding-bottom: 9px;
    }
    .logo { width: 72px; height: 72px; object-fit: contain; margin-bottom: 2px;}
    .sekolah-title { font-size: 19px; font-weight: 700; color: #193871; text-transform: uppercase; letter-spacing: 1px;}
    .sub-title { font-size: 16px; font-weight: 500; }
    .tahun-ajaran { font-size: 15px; margin-bottom: 7px; }
    .no-reg { margin-bottom: 10px; font-weight: 500; }
    .data-table caption {
      background: #eaf2ce; font-weight: bold; font-size: 16px; padding: 5px 0;
      border-radius: 4px 4px 0 0;
      margin-bottom: 0;
    }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px;}
    .data-table th, .data-table td {
      border: 1px solid #777; padding: 7px 12px; font-size: 15px;
    }
    .data-table th { background: #f2f6e9; text-align: left; width: 34%; }
    .data-table td { background: #fff; }
    .row-btm {
      display: flex; justify-content: flex-start; align-items: flex-start; margin-top: 18px;
    }
    .info-contact { font-size: 14px; line-height: 1.7; }
    .note {
      font-size: 13px; margin-top: 15px; color: #333;
      background: #f7f7fc; border-left: 3.5px solid #0497df; padding: 10px 18px 8px 14px;
    }
    .ttd-box { text-align: right; margin-top: 38px; margin-right: 35px;}
    .ttd-petugas { margin-top: 65px; font-weight: bold; border-top: 1px dashed #666; padding-top: 4px; text-align: center; font-size: 16px; width: 220px;}
    @media print {
      body { background: #fff; }
      .container { box-shadow: none; border: none; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body onload="window.print()">
  <div class="container">
    <div class="header">
      <img src="../assets/logo_sekolah.png" alt="Logo" class="logo" onerror="this.style.display='none'">
      <div class="sekolah-title">SMA/SMK DHARMA KARYA JAKARTA</div>
      <div class="sub-title">BUKTI PENDAFTARAN CALON PESERTA DIDIK BARU</div>
      <div class="tahun-ajaran">TAHUN AJARAN <?= date('Y') . "/" . (date('Y')+1) ?></div>
    </div>
    <div class="no-reg"><b>No. Reg :</b> <?= safe($row['no_formulir']) ?></div>
    <table class="data-table">
      <caption>DATA CALON PESERTA DIDIK BARU</caption>
      <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td></tr>
      <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
      <tr><th>Email</th><td><?= safe($row['email'] ?? '-') ?></td></tr>
    </table>
    <div class="row-btm">
      <div class="info-contact">
        Informasi lebih lanjut hubungi:<br>
        Hotline SMA : <b>081511519271</b> (Bu Puji)<br>
        Hotline SMK : <b>085880120889</b> (Bu Ina)
      </div>
    </div>
    <div class="note">
      <b>Catatan:</b><br>
      Bukti pendaftaran ini bukan menjadi bukti siswa tersebut diterima di SMA/SMK Dharma Karya.<br>
      Siswa dinyatakan diterima apabila telah menyelesaikan administrasi dan mendapatkan nomor pendaftaran.
    </div>
    <div class="ttd-box">
      <div>
        <div><?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="ttd-petugas"><?= safe($petugas) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
