<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';

function safe($str) {
    return htmlspecialchars($str ?? '-');
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    die('Akses tidak diizinkan.');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die('ID siswa tidak valid.');

// Ambil data siswa
$stmt = $conn->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die('Data siswa tidak ditemukan.');

// Ambil nama petugas
$petugas = '-';
$username_petugas = $_SESSION['username'] ?? '';
if ($username_petugas) {
    $stmt_petugas = $conn->prepare("SELECT nama FROM petugas WHERE username = ?");
    $stmt_petugas->bind_param('s', $username_petugas);
    $stmt_petugas->execute();
    $data_petugas = $stmt_petugas->get_result()->fetch_assoc();
    $stmt_petugas->close();
    $petugas = (!empty($data_petugas['nama'])) ? $data_petugas['nama'] : $username_petugas;
}

function tanggal_id($tgl) {
    if (!$tgl || $tgl == '0000-00-00') return '-';
    $bulan = [
        'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
        'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
        'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $d = date('d', strtotime($tgl));
    $m = $bulan[date('F', strtotime($tgl))] ?? date('F', strtotime($tgl));
    $y = date('Y', strtotime($tgl));
    return "$d $m $y";
}

// Ambil tagihan awal
$tagihan = [];
$stmtTagihan = $conn->prepare("
    SELECT jp.nama AS jenis, sta.nominal
    FROM siswa_tagihan_awal sta
    JOIN jenis_pembayaran jp ON sta.jenis_pembayaran_id = jp.id
    WHERE sta.siswa_id = ?
    ORDER BY jp.id ASC
");
$stmtTagihan->bind_param('i', $id);
$stmtTagihan->execute();
$resTagihan = $stmtTagihan->get_result();
while ($t = $resTagihan->fetch_assoc()) {
    $tagihan[] = $t;
}
$stmtTagihan->close();

// Hitung status pembayaran
$uang_pangkal_id = 1;
$spp_id = 2;
$stmtCount = $conn->prepare("
    SELECT 
      SUM(CASE WHEN pd.jenis_pembayaran_id = ? AND pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) AS cnt_pangkal,
      SUM(CASE WHEN pd.jenis_pembayaran_id = ? AND pd.bulan = 'Juli' AND pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) AS cnt_spp
    FROM pembayaran_detail pd
    JOIN pembayaran p ON pd.pembayaran_id = p.id
    WHERE p.siswa_id = ?
");
$stmtCount->bind_param('iii', $uang_pangkal_id, $spp_id, $id);
$stmtCount->execute();
$dataCount = $stmtCount->get_result()->fetch_assoc();
$stmtCount->close();

$cntPangkal = intval($dataCount['cnt_pangkal']);
$cntSpp    = intval($dataCount['cnt_spp']);
if ($cntPangkal > 0 && $cntSpp > 0) {
    $statusPembayaran = 'Lunas';
} elseif ($cntPangkal > 0 || $cntSpp > 0) {
    $statusPembayaran = 'Angsuran';
} else {
    $statusPembayaran = 'Belum Bayar';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f8fa; }
    .container {
      width: 800px; max-width: 100%; margin: 22px auto; background: #fff;
      border-radius: 10px; box-shadow: 0 0 8px rgba(60,60,60,0.13);
      padding: 28px 34px 24px 34px; border: 1px solid #d1dbe7;
    }
    .header { position: relative; min-height: 80px; border-bottom: 2px solid #d1d5db; margin-bottom: 18px; padding-bottom: 9px; }
    .logo { width: 150px; height: 150px; object-fit: contain; position: absolute; left: 0; top: -40px; z-index: 2; }
    .header-content {
      width: 100%; position: absolute; left: 0; top: 0; height: 80px;
      display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
    }
    .sekolah-title { font-size: 19px; font-weight: 700; color: #193871; text-transform: uppercase; letter-spacing: 1px; }
    .sub-title { font-size: 16px; font-weight: 500; }
    .tahun-ajaran { font-size: 15px; margin-bottom: 7px; }
    .no-reg { margin-bottom: 10px; font-weight: 500; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .data-table caption { background: #eaf2ce; font-weight: bold; font-size: 16px; padding: 5px 0; border-radius: 4px 4px 0 0; margin-bottom: 0; }
    .data-table th, .data-table td { border: 1px solid #777; padding: 7px 12px; font-size: 15px; }
    .data-table th { background: #f2f6e9; text-align: left; width: 34%; }
    .tagihan-table {
      width: 60%; min-width:270px; margin: 0 auto 18px auto; border-collapse: collapse;
      background: #f9f9fa; border-radius: 8px; overflow: hidden;
    }
    .tagihan-table th, .tagihan-table td {
      border: 1px solid #b5b5b5; padding: 8px 18px; font-size: 15px; text-align: left;
    }
    .tagihan-table th { background: #ecf3fd; }
    .status-label { font-size: 15px; margin: 18px 0; text-align: center; }
    .status-lunas { color: #1cc88a; font-weight: bold; }
    .status-angsuran { color: #f6c23e; font-weight: bold; }
    .status-pending { color: #e74a3b; font-weight: bold; }
    .info-contact { font-size: 14px; line-height: 1.7; margin-top: 18px; }
    .note {
      font-size: 13px; margin-top: 15px; color: #333;
      background: #f7f7fc; border-left: 3.5px solid #0497df; padding: 10px 18px 8px 14px;
    }
    .footer-ttd-kanan { display: flex; justify-content: flex-end; align-items: flex-end; margin-top: 65px; min-height: 90px; }
    .ttd-block-kanan { display: flex; flex-direction: column; align-items: center; min-width: 200px; }
    .ttd-tanggal-kanan { font-size: 17px; margin-bottom: 70px; text-align: center; width: 100%; }
    .ttd-petugas-kanan { font-size: 21px; margin-bottom: 1px; text-align: center; width: 100%; }
    .ttd-label-kanan { font-size: 17px; text-align: center; width: 100%; }
    .no-print { margin-bottom: 10px; }
    @media print {
      @page { size: A4 portrait; margin: 12mm; }
      html, body { width: 210mm; height: 297mm; background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
      .container { width: 100% !important; max-width: 185mm !important; margin: 0 auto !important; border-radius: 0 !important; box-shadow: none !important; border: none !important; page-break-after: avoid; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <button class="no-print" onclick="window.print()" style="padding:8px 20px;background:#25a244;color:#fff;border:none;border-radius:6px;font-size:16px;cursor:pointer">
    Cetak
  </button>
  <div class="container">
    <div class="header">
      <img src="../assets/images/logo_trans.png" alt="Logo" class="logo" onerror="this.style.display='none'">
      <div class="header-content">
        <div class="sekolah-title">SMA/SMK DHARMA KARYA JAKARTA</div>
        <div class="sub-title">BUKTI PENDAFTARAN CALON PESERTA DIDIK BARU</div>
        <div class="tahun-ajaran">TAHUN AJARAN 2025/2026</div>
      </div>
    </div>

    <div class="no-reg"><strong>No. Reg / No Formulir :</strong> <?= safe($row['no_formulir']) ?></div>
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

    <table class="tagihan-table">
      <tr><th colspan="2">Proses Pembayaran Awal</th></tr>
      <?php if (count($tagihan)): foreach ($tagihan as $tg): ?>
      <tr>
        <td><?= safe($tg['jenis']) ?></td>
        <td style="text-align:right;"><?= number_format($tg['nominal'],0,',','.') ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="2" style="text-align:center;color:#bb2222;">Belum ada tagihan yang diverifikasi.</td>
      </tr>
      <?php endif; ?>
    </table>

    <div class="status-label">
      <strong>Status Pembayaran:</strong>
      <?php if ($statusPembayaran === 'Lunas'): ?>
        <span class="status-lunas">Lunas</span>
      <?php elseif ($statusPembayaran === 'Angsuran'): ?>
        <span class="status-angsuran">Angsuran</span>
      <?php else: ?>
        <span class="status-pending">Belum Bayar</span>
      <?php endif; ?>
    </div>

    <div class="info-contact">
      Informasi lebih lanjut hubungi:<br>
      Hotline SMA : <b>081511519271</b><br>
      Hotline SMK : <b>085880120889</b>
    </div>

    <div class="note">
      <strong>Catatan:</strong><br>
      Bukti pendaftaran ini bukan bukti diterima. Siswa dinyatakan diterima apabila telah menyelesaikan administrasi.
    </div>

    <div class="footer-ttd-kanan">
      <div class="ttd-block-kanan">
        <div class="ttd-tanggal-kanan"><?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
        <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>
</body>
</html>
