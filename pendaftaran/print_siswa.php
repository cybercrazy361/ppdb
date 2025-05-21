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
    width: 800px; max-width: 100%; margin: 22px auto; background: #fff;
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

.footer-ttd-kanan {
  width: 100%;
  display: flex;
  justify-content: flex-end;
  align-items: flex-end;
  margin-top: 65px;
  min-height: 90px;
}

.ttd-block-kanan {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 200px;
}

.ttd-tanggal-kanan {
  font-size: 17px;
  margin-bottom: 70px;
  text-align: center;
  width: 100%;
}

.ttd-petugas-kanan {
  font-weight: normal;
  font-size: 21px;
  margin-bottom: 1px;
  text-align: center;
  width: 100%;
}

.ttd-label-kanan {
  font-weight: normal;
  font-size: 17px;
  text-align: center;
  width: 100%;
}


/* PRINTING - supaya layout rapi dan warna tetap */
@media print {
  @page {
    size: A4 portrait;
    margin: 12mm;
  }
  html, body {
    width: 210mm;
    height: 297mm;
    background: #fff !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .container {
    width: 100% !important;
    max-width: 185mm !important;
    min-height: 250mm;
    margin: 0 auto !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    border: none !important;
    page-break-after: avoid;
  }
  .no-print {
    display: none !important;
  }
  .data-table caption,
  .data-table th,
  .note {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .footer-ttd-kanan {
    margin-top: 75px !important;
  }
}

</style>

</head>
<body>
  <!-- Tombol Cetak, hanya tampil di layar -->
  <button class="no-print btn-cetak" onclick="window.print()" style="padding:8px 20px;background:#25a244;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer">
    <i class="fas fa-print"></i> Cetak
  </button>
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
    <div class="footer-ttd-kanan">
    <div class="ttd-block-kanan">
        <div class="ttd-tanggal-kanan"><?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
        <div class="ttd-label-kanan">Petugas Pendaftaran</div>
    </div>
    </div>
  </div>
  <!-- Icon FontAwesome agar tombol print ada ikon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</body>
</html>
