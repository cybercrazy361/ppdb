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

// AMBIL DATA SISWA
$stmt = $conn->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die('Data siswa tidak ditemukan.');

// AMBIL PETUGAS
$petugas = '-';
$username_petugas = $_SESSION['username'] ?? '';
if ($username_petugas) {
    $stmt_petugas = $conn->prepare("SELECT nama FROM petugas WHERE username = ?");
    $stmt_petugas->bind_param('s', $username_petugas);
    $stmt_petugas->execute();
    $result_petugas = $stmt_petugas->get_result();
    $data_petugas = $result_petugas->fetch_assoc();
    if ($data_petugas && !empty($data_petugas['nama'])) {
        $petugas = $data_petugas['nama'];
    } else {
        $petugas = $username_petugas;
    }
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

// CEK STATUS PEMBAYARAN
$status_pembayaran = 'Belum Bayar';
$uang_pangkal_id = 1;
$spp_id = 2;
$stmtStatus = $conn->prepare("
    SELECT
      CASE
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd1 
                JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                WHERE p1.siswa_id = ? 
                  AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                  AND pd1.status_pembayaran = 'Lunas'
            ) > 0
        AND
            (SELECT COUNT(*) FROM pembayaran_detail pd2 
                JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                WHERE p2.siswa_id = ? 
                  AND pd2.jenis_pembayaran_id = $spp_id
                  AND pd2.bulan = 'Juli'
                  AND pd2.status_pembayaran = 'Lunas'
            ) > 0
        THEN 'Lunas'
        WHEN 
            (
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = ? 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                      AND pd1.status_pembayaran = 'Lunas'
                ) > 0
                OR
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = ? 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            )
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
      END AS status_pembayaran
");
$stmtStatus->bind_param('iiii', $id, $id, $id, $id);
$stmtStatus->execute();
$resultStatus = $stmtStatus->get_result();
if ($rStatus = $resultStatus->fetch_assoc()) {
    $status_pembayaran = $rStatus['status_pembayaran'] ?? 'Belum Bayar';
}
$stmtStatus->close();

// TAGIHAN AWAL
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <link rel="stylesheet" href="../assets/css/print_bukti_pendaftaran_pembayaran.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    <!-- TABEL TAGIHAN AWAL -->
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
    <div class="row-btm">
      <div class="info-contact">
        Informasi lebih lanjut hubungi:<br>
        Hotline SMA : <b>081511519271</b> (Bu Puji)<br>
        Hotline SMK : <b>085880120889</b> (Bu Ina)
      </div>
    </div>
    <!-- STATUS PEMBAYARAN SECTION -->
    <?php if ($status_pembayaran === 'Lunas' || $status_pembayaran === 'Angsuran'): ?>
      <div class="note-success">
        <span class="status-badge"><?= strtoupper($status_pembayaran) ?></span>
        <b>Pembayaran sudah dilakukan.</b><br>
        Status pembayaran: <b><?= strtoupper($status_pembayaran) ?></b><br>
        Berikut rincian pembayaran terakhir:
      </div>
      <table class="tagihan-table" style="margin-top:11px;">
        <tr>
          <th>Jenis</th>
          <th>Bulan</th>
          <th>Nominal</th>
          <th>Status</th>
          <th>Tanggal</th>
        </tr>
        <?php
        $sqlPembayaran = $conn->prepare("
          SELECT pd.jenis_pembayaran_id, jp.nama as jenis, pd.bulan, pd.nominal, pd.status_pembayaran, p.tanggal_pembayaran
          FROM pembayaran_detail pd
          JOIN pembayaran p ON pd.pembayaran_id = p.id
          JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
          WHERE p.siswa_id = ?
          ORDER BY p.tanggal_pembayaran DESC
          LIMIT 5
        ");
        $sqlPembayaran->bind_param('i', $id);
        $sqlPembayaran->execute();
        $rsPembayaran = $sqlPembayaran->get_result();
        if ($rsPembayaran->num_rows): while($d = $rsPembayaran->fetch_assoc()): ?>
        <tr>
          <td><?= safe($d['jenis']) ?></td>
          <td><?= safe($d['bulan']) ?></td>
          <td>Rp <?= number_format($d['nominal'],0,',','.') ?></td>
          <td><?= safe($d['status_pembayaran']) ?></td>
          <td><?= tanggal_id($d['tanggal_pembayaran']) ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" style="text-align:center;">Belum ada pembayaran tercatat.</td></tr>
        <?php endif; $sqlPembayaran->close(); ?>
      </table>
    <?php else: ?>
      <div class="note status-belum">
        <span class="status-badge status-belum">BELUM BAYAR</span>
        <b>Catatan:</b><br>
        Bukti pendaftaran ini hanya menyatakan Anda telah mendaftar.<br>
        Silakan lakukan pembayaran di bagian keuangan.<br>
        Siswa dinyatakan diterima apabila telah menyelesaikan administrasi.
      </div>
    <?php endif; ?>
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
