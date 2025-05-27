<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';

function safe($str) { return htmlspecialchars($str ?? '-'); }

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

$petugas = '-';
$username_petugas = $_SESSION['username'] ?? '';
if ($username_petugas) {
    $stmt_petugas = $conn->prepare("SELECT nama FROM petugas WHERE username = ?");
    $stmt_petugas->bind_param('s', $username_petugas);
    $stmt_petugas->execute();
    $result_petugas = $stmt_petugas->get_result();
    $data_petugas = $result_petugas->fetch_assoc();
    $petugas = ($data_petugas && !empty($data_petugas['nama'])) ? $data_petugas['nama'] : $username_petugas;
    $stmt_petugas->close();
}

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

// AMBIL STATUS PROGRES
$uang_pangkal_id = 1; // Pastikan sama dengan DB
$spp_id = 2;

$query_status = "
    SELECT
        CASE
            WHEN 
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = s.id 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                      AND pd1.status_pembayaran = 'Lunas'
                ) > 0
            AND
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = s.id 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            THEN 'Lunas'
            WHEN 
                (
                    (SELECT COUNT(*) FROM pembayaran_detail pd1 
                        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                        WHERE p1.siswa_id = s.id 
                          AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                          AND pd1.status_pembayaran = 'Lunas'
                    ) > 0
                    OR
                    (SELECT COUNT(*) FROM pembayaran_detail pd2 
                        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                        WHERE p2.siswa_id = s.id 
                          AND pd2.jenis_pembayaran_id = $spp_id
                          AND pd2.bulan = 'Juli'
                          AND pd2.status_pembayaran = 'Lunas'
                    ) > 0
                )
            THEN 'Angsuran'
            ELSE 'Belum Bayar'
        END AS status_pembayaran
    FROM siswa s
    WHERE s.id = ?
";
$stmtStatus = $conn->prepare($query_status);
$stmtStatus->bind_param('i', $id);
$stmtStatus->execute();
$resultStatus = $stmtStatus->get_result();
$status_pembayaran = $resultStatus->fetch_assoc()['status_pembayaran'] ?? 'Belum Bayar';
$stmtStatus->close();

// Tagihan awal
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
$res = $stmtTagihan->get_result();
while ($t = $res->fetch_assoc()) $tagihan[] = $t;
$stmtTagihan->close();

// Riwayat pembayaran terakhir
$pembayaran_terakhir = [];
if ($status_pembayaran !== 'Belum Bayar') {
    $stmtBayar = $conn->prepare("
        SELECT jp.nama AS jenis, pd.jumlah, pd.status_pembayaran, pd.bulan, p.tanggal_pembayaran
        FROM pembayaran_detail pd
        JOIN pembayaran p ON pd.pembayaran_id = p.id
        JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
        WHERE p.siswa_id = ?
        ORDER BY p.tanggal_pembayaran DESC, pd.id DESC
        LIMIT 6
    ");
    $stmtBayar->bind_param('i', $id);
    $stmtBayar->execute();
    $resBayar = $stmtBayar->get_result();
    while ($b = $resBayar->fetch_assoc()) $pembayaran_terakhir[] = $b;
    $stmtBayar->close();
}

// Badge warna status
function getStatusBadge($status) {
    $status = strtolower($status);
    if ($status === 'lunas') return '<span style="color:#1cc88a;font-weight:bold"><i class="fas fa-check-circle"></i> Lunas</span>';
    if ($status === 'angsuran') return '<span style="color:#f6c23e;font-weight:bold"><i class="fas fa-hourglass-half"></i> Angsuran</span>';
    return '<span style="color:#e74a3b;font-weight:bold"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f8fa; }
  .container { width: 800px; max-width: 100%; margin: 22px auto; background: #fff; border-radius: 10px; box-shadow: 0 0 8px rgba(60,60,60,0.13); padding: 28px 34px 24px 34px; border: 1px solid #d1dbe7; }
  .header { position: relative; min-height: 80px; border-bottom: 2px solid #d1d5db; margin-bottom: 18px; padding-bottom: 9px; }
  .logo { width: 150px; height: 150px; object-fit: contain; position: absolute; left: 0; top: -40px; z-index: 2; }
  .header-content { width: 100%; position: absolute; left: 0; top: 0; height: 80px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; pointer-events: none; }
  @media print { .logo { width: 120px; height: 120px; top: -25px; } }
  .sekolah-title { font-size: 19px; font-weight: 700; color: #193871; text-transform: uppercase; letter-spacing: 1px;}
  .sub-title { font-size: 16px; font-weight: 500; }
  .tahun-ajaran { font-size: 15px; margin-bottom: 7px; }
  .no-reg { margin-bottom: 10px; font-weight: 500; }
  .data-table caption { background: #eaf2ce; font-weight: bold; font-size: 16px; padding: 5px 0; border-radius: 4px 4px 0 0; margin-bottom: 0; }
  .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px;}
  .data-table th, .data-table td { border: 1px solid #777; padding: 7px 12px; font-size: 15px;}
  .data-table th { background: #f2f6e9; text-align: left; width: 34%; }
  .data-table td { background: #fff; }
  .tagihan-table { width: 60%; min-width:270px; margin: 0 auto 18px auto; border-collapse: collapse; background: #f9f9fa; border-radius: 8px; overflow: hidden; }
  .tagihan-table th, .tagihan-table td { border: 1px solid #b5b5b5; padding: 8px 18px; font-size: 15px; text-align: left; }
  .tagihan-table th { background: #ecf3fd; text-align: left;}
  .tagihan-table td { background: #fff; }
  .status-row { font-size: 17px; font-weight: bold; color: #222; text-align: center; background: #f6f7fc; border-radius: 7px; padding: 12px 0 8px 0; margin-bottom: 20px; border: 1px solid #c5d1e8; }
  .row-btm { display: flex; justify-content: flex-start; align-items: flex-start; margin-top: 18px; }
  .info-contact { font-size: 14px; line-height: 1.7; }
  .note { font-size: 13px; margin-top: 15px; color: #333; background: #f7f7fc; border-left: 3.5px solid #0497df; padding: 10px 18px 8px 14px; }
  .footer-ttd-kanan { width: 100%; display: flex; justify-content: flex-end; align-items: flex-end; margin-top: 65px; min-height: 90px; }
  .ttd-block-kanan { display: flex; flex-direction: column; align-items: center; min-width: 200px; }
  .ttd-tanggal-kanan { font-size: 17px; margin-bottom: 70px; text-align: center; width: 100%; }
  .ttd-petugas-kanan { font-weight: normal; font-size: 21px; margin-bottom: 1px; text-align: center; width: 100%; }
  .ttd-label-kanan { font-weight: normal; font-size: 17px; text-align: center; width: 100%; }
  @media print {
    @page { size: A4 portrait; margin: 12mm; }
    html, body { width: 210mm; height: 297mm; background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .container { width: 100% !important; max-width: 185mm !important; min-height: 250mm; margin: 0 auto !important; border-radius: 0 !important; box-shadow: none !important; border: none !important; page-break-after: avoid; }
    .no-print { display: none !important; }
    .data-table caption, .data-table th, .note { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .footer-ttd-kanan { margin-top: 75px !important; }
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
      <img src="../assets/images/logo_trans.png" alt="Logo" class="logo" onerror="this.style.display='none'">
      <div class="header-content">
        <div class="sekolah-title">SMA/SMK DHARMA KARYA JAKARTA</div>
        <div class="sub-title">BUKTI PENDAFTARAN CALON PESERTA DIDIK BARU</div>
        <div class="tahun-ajaran">TAHUN AJARAN 2025/2026</div>
      </div>
    </div>

    <div class="no-reg"><b>No. Reg / No Formulir :</b> <?= safe($row['no_formulir']) ?></div>
    <div class="status-row">
      Status Pembayaran: <?= getStatusBadge($status_pembayaran) ?>
    </div>

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

    <!-- TAGIHAN -->
    <table class="tagihan-table" style="margin-top:25px;">
      <tr>
        <th colspan="2" style="background:#e3eaf7;font-size:15.5px;text-align:center">
          <i class="fas fa-coins"></i> Proses pembayaran awal
        </th>
      </tr>
      <?php if(count($tagihan)): foreach($tagihan as $tg): ?>
      <tr>
        <td><?= safe($tg['jenis']) ?></td>
        <td style="text-align:right;font-weight:600">
          Rp <?= number_format($tg['nominal'], 0, ',', '.') ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="2" style="text-align:center;color:#bb2222;">Belum ada tagihan yang diverifikasi.</td>
      </tr>
      <?php endif; ?>
    </table>

    <!-- RIWAYAT PEMBAYARAN JIKA SUDAH ADA PEMBAYARAN -->
    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
      <div style="margin:18px 0 4px 0;font-size:15.2px;font-weight:500;">Riwayat Pembayaran:</div>
      <table class="tagihan-table" style="margin-bottom:18px;">
        <tr>
          <th>Jenis</th>
          <th>Nominal</th>
          <th>Status</th>
          <th>Bulan</th>
          <th>Tanggal</th>
        </tr>
        <?php foreach($pembayaran_terakhir as $b): ?>
        <tr>
          <td><?= safe($b['jenis']) ?></td>
          <td style="text-align:right;">Rp <?= number_format($b['jumlah'],0,',','.') ?></td>
          <td><?= safe($b['status_pembayaran']) ?></td>
          <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
          <td><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

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
  Siswa dinyatakan diterima apabila telah menyelesaikan administrasi.
</div>

<div class="footer-ttd-kanan">
  <div class="ttd-block-kanan">
    <div class="ttd-tanggal-kanan"><?= tanggal_id(date('Y-m-d')) ?></div>
    <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
    <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
  </div>
</div>
</div> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
</body> 
</html>