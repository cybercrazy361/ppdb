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

// Ambil status progres
$uang_pangkal_id = 1;
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

// Riwayat pembayaran terakhir + cashback
$pembayaran_terakhir = [];
if ($status_pembayaran !== 'Belum Bayar') {
    $stmtBayar = $conn->prepare("
        SELECT jp.nama AS jenis, pd.jumlah, pd.status_pembayaran, pd.bulan, p.tanggal_pembayaran, pd.cashback
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

function getStatusBadge($status) {
    $status = strtolower($status);
    if ($status === 'lunas') return '<span style="color:#1cc88a;font-weight:bold"><i class="fas fa-check-circle"></i> Lunas</span>';
    if ($status === 'angsuran') return '<span style="color:#f6c23e;font-weight:bold"><i class="fas fa-hourglass-half"></i> Angsuran</span>';
    return '<span style="color:#e74a3b;font-weight:bold"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}

$note_class = '';
if ($status_pembayaran === 'Belum Bayar') $note_class = 'belum-bayar';
elseif ($status_pembayaran === 'Angsuran') $note_class = 'angsuran';
elseif ($status_pembayaran === 'Lunas') $note_class = 'lunas';

// No Invoice, pastikan kolom ini ada di tabel siswa!
$no_invoice = $row['no_invoice'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/print_bukti_pendaftaran.css" />
</head>
<body>
  <button class="no-print btn-cetak" onclick="window.print()">
    <i class="fas fa-print"></i> Cetak
  </button>
  <div class="container">
    <!-- Kop Surat -->
    <div class="kop-surat">
      <div class="kop-logo">
        <img src="../assets/images/logo_trans.png" alt="Logo Sekolah">
      </div>
      <div class="kop-center">
        <div class="kop-title-1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title-2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi">Terakreditasi <b>“A”</b></div>
        <div class="kop-alamat">Jl. Melawai XII No.2 Kav. 207A Kebayoran Baru, Jakarta Selatan</div>
        <div class="kop-telp">Telp. 021-7398578 / 7250224</div>
      </div>
    </div>
    <div class="kop-garis"></div>

    <!-- Header -->
    <div class="header">
      <?php if ($status_pembayaran === 'Lunas' || $status_pembayaran === 'Angsuran'): ?>
        <div class="sub-title">BUKTI PENDAFTARAN MURID BARU</div>
      <?php else: ?>
        <div class="sub-title">BUKTI PENDAFTARAN CALON MURID BARU</div>
      <?php endif; ?>
      <div class="tahun-ajaran">SISTEM PENERIMAAN MURID BARU (SPMB)</div>
      <div class="tahun-ajaran">SMA DHARMA KARYA JAKARTA</div>
      <div class="tahun-ajaran tahun-ajaran-thin">TAHUN AJARAN 2025/2026</div>
    </div>

    <!-- Data Registrasi -->
    <div class="reg-row">
      <span class="reg-label">No. Registrasi Pendaftaran</span>
      <span class="reg-sep">:</span>
      <span class="reg-val"><?= safe($row['no_formulir']) ?></span>
    </div>
    <?php if ($status_pembayaran !== 'Belum Bayar' && !empty($no_invoice)): ?>
    <div class="reg-row">
      <span class="reg-label">No. Formulir Pendaftaran</span>
      <span class="reg-sep">:</span>
      <span class="reg-val"><?= safe($no_invoice) ?></span>
    </div>
    <?php endif; ?>

    <!-- Data Siswa -->
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

    <!-- Tagihan -->
    <table class="tagihan-table">
      <tr>
        <th colspan="2"><i class="fas fa-coins"></i> Keterangan Pembayaran</th>
      </tr>
      <?php if(count($tagihan)): foreach($tagihan as $tg): ?>
      <tr>
        <td><?= safe($tg['jenis']) ?></td>
        <td class="text-right"><b>Rp <?= number_format($tg['nominal'], 0, ',', '.') ?></b></td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="2" class="text-center muted">Belum ada tagihan yang diverifikasi.</td>
      </tr>
      <?php endif; ?>
    </table>

    <!-- Riwayat Pembayaran -->
    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
    <div class="riwayat-title">Riwayat Pembayaran:</div>
    <table class="tagihan-table riwayat-bayar">
      <tr>
        <th>Jenis</th>
        <th>Nominal</th>
        <th>Cashback</th>
        <th>Status</th>
        <th>Bulan</th>
        <th>Tanggal</th>
      </tr>
      <?php foreach($pembayaran_terakhir as $b): ?>
      <tr>
        <td><?= safe($b['jenis']) ?></td>
        <td class="text-right">Rp <?= number_format($b['jumlah'],0,',','.') ?></td>
        <td class="text-right"><?= ($b['cashback'] ?? 0) > 0 ? 'Rp ' . number_format($b['cashback'],0,',','.') : '-' ?></td>
        <td><?= safe($b['status_pembayaran']) ?></td>
        <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
        <td><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <!-- Status pembayaran -->
    <div class="status-row">
      Status Pembayaran: <?= getStatusBadge($status_pembayaran) ?>
    </div>

    <!-- Kontak -->
    <div class="info-contact">
      Informasi lebih lanjut hubungi: <br>
      Hotline SMA: <b>081511519271</b> (Bu Puji)
    </div>

    <!-- Catatan -->
    <div class="note <?= $note_class ?>">
      <?php if ($status_pembayaran === 'Belum Bayar'): ?>
        <b>Catatan:</b><br>
        1. Setelah administrasi selesai, serahkan form ini ke bagian pendaftaran untuk mendapatkan nomor pendaftaran.<br>
        2. Form ini bukan bukti diterima, status diterima setelah pembayaran dan dapat nomor pendaftaran.
      <?php elseif ($status_pembayaran === 'Angsuran'): ?>
        <b>Catatan:</b><br>
        Siswa telah melakukan pembayaran sebagian (angsuran). Simpan bukti ini.
      <?php elseif ($status_pembayaran === 'Lunas'): ?>
        <b>Catatan:</b><br>
        Siswa telah menyelesaikan seluruh pembayaran. Simpan bukti ini sebagai tanda lunas.
      <?php else: ?>
        <b>Catatan:</b><br>
        Status pembayaran tidak diketahui.
      <?php endif; ?>
    </div>

    <!-- Tanda Tangan -->
    <div class="footer-ttd">
      <div class="footer-ttd-right">
        <div class="ttd-tanggal"><?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="ttd-nama"><?= safe($petugas) ?></div>
        <div class="ttd-label">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>
</body>
</html>

