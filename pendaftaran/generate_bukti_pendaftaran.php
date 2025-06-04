<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

function safe($str) {
    return htmlspecialchars($str ?? '-');
}

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

// Ambil status & notes dari calon_pendaftar
$status_pendaftaran = '-';
$keterangan_pendaftaran = '-';
if (!empty($row['calon_pendaftar_id'])) {
    $stmtStatus = $conn->prepare("SELECT status, notes FROM calon_pendaftar WHERE id = ?");
    $stmtStatus->bind_param('i', $row['calon_pendaftar_id']);
    $stmtStatus->execute();
    $rsStatus = $stmtStatus->get_result()->fetch_assoc();
    $status_pendaftaran = $rsStatus['status'] ?? '-';
    $keterangan_pendaftaran = $rsStatus['notes'] ?? '-';
    $stmtStatus->close();
}

function tanggal_id($tgl) {
    if (!$tgl || $tgl == '0000-00-00') return '-';
    $bulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
        'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
        'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $date = date('d', strtotime($tgl));
    $month = $bulan[date('F', strtotime($tgl))];
    $year = date('Y', strtotime($tgl));
    return "$date $month $year";
}

// Status progres pembayaran
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
    if ($status === 'lunas') return '<img src="https://ppdbdk.pakarinformatika.web.id/assets/images/icon_lunas.png" alt="Lunas" style="height:18px;vertical-align:middle;margin-right:3px;"><span style="color:#1cc88a;font-weight:bold">Lunas</span>';
    if ($status === 'angsuran') return '<img src="https://ppdbdk.pakarinformatika.web.id/assets/images/icon_angsuran.png" alt="Angsuran" style="height:18px;vertical-align:middle;margin-right:3px;"><span style="color:#f6c23e;font-weight:bold">Angsuran</span>';
    return '<img src="https://ppdbdk.pakarinformatika.web.id/assets/images/icon_belum.png" alt="Belum Bayar" style="height:18px;vertical-align:middle;margin-right:3px;"><span style="color:#e74a3b;font-weight:bold">Belum Bayar</span>';
}

$note_class = '';
if ($status_pembayaran === 'Belum Bayar') $note_class = 'belum-bayar';
elseif ($status_pembayaran === 'Angsuran') $note_class = 'angsuran';
elseif ($status_pembayaran === 'Lunas') $note_class = 'lunas';

$no_invoice = $row['no_invoice'] ?? '';

// Lokasi simpan PDF
$no_formulir = safe($row['no_formulir']);
$dir_pdf = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/';
$pattern = $dir_pdf . "bukti_pendaftaran_{$no_formulir}_v*.pdf";

// Cari versi terakhir
$versi_tertinggi = 1;
foreach (glob($pattern) as $file) {
    if (preg_match('/_v(\d+)\.pdf$/', $file, $m)) {
        $v = intval($m[1]);
        if ($v > $versi_tertinggi) $versi_tertinggi = $v;
    }
}
$versi_tertinggi++; // Versi baru untuk file baru
$filename = "bukti_pendaftaran_{$no_formulir}_v{$versi_tertinggi}.pdf";
$save_path = $dir_pdf . $filename;


$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 5,
    'margin_left' => 7,
    'margin_right' => 7,
    'margin_bottom' => 8,
    'default_font' => 'Arial'
]);

ob_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
<style>
  body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #202b38;
    margin: 0;
    padding: 0;
  }
  .container {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 5mm 10mm 10mm 10mm;
    box-sizing: border-box;
  }

.kop-surat-flex {
  display: flex;
  align-items: center;        /* Vertikal tengah */
  justify-content: center;    /* Tengah horizontal seluruh konten */
  margin-bottom: 8px;
  width: 100%;
  gap: 30px;                  /* Jarak logo dan kop info */
}

.kop-logo-abs {
  width: 85px;
  height: 85px;
  object-fit: contain;
  flex-shrink: 0;
}

.kop-info-center {
  text-align: left;
  font-family: Arial, sans-serif;
  color: #163984;
  line-height: 1.2;
}

.kop-title1 {
  font-size: 20px;
  font-weight: 700;
  letter-spacing: 1.1px;
  margin-bottom: 2px;
}

.kop-title2 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 2px;
}

.kop-akreditasi {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 5px;
}

.kop-alamat {
  font-size: 12px;
  margin-bottom: 1px;
}
.kop-garis {
  border-bottom: 2px solid #163984;
  margin: 0 10mm 18px 10mm;
  width: calc(100% - 20mm);
}

  .header-content {
    text-align: center;
    margin-bottom: 20px;
  }
  .sub-title {
    font-size: 17px;
    font-weight: 700;
    color: #163984;
    margin: 0 0 5px 0;
  }
  .tahun-ajaran {
    font-size: 13px;
    font-weight: 600;
    color: #163984;
    margin: 0;
  }
  .no-reg-bar {
    margin-bottom: 12px;
  }
  .no-reg-row {
    font-size: 12px;
    margin-bottom: 4px;
  }
  .no-reg-label {
    font-weight: 600;
    display: inline-block;
    min-width: 160px;
    color: #333;
  }
  .no-reg-val {
    font-style: italic;
    font-weight: 700;
    color: #1a53c7;
  }
  .callcenter-badge {
    display: inline-block;
    background: #d4f1fd;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    color: #1a4299;
    margin-left: 20px;
  }
  table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
  }
  table.data-table caption {
    background: #e6edfa !important;
    color: #1a53c7 !important;
    font-weight: 900 !important;
    font-size: 18px !important;
    padding: 10px 0 !important;
    border-radius: 6px 6px 0 0 !important;
    text-align: center !important;
    text-transform: uppercase !important;
}

  table.data-table th,
  table.data-table td {
    border: 1px solid #dbe4f3;
    padding: 6px 10px;
    text-align: left;
  }
  table.data-table th {
    background: #e6edfa;
    color: #163984;
    font-weight: 600;
    width: 30%;
  }
  table.tagihan-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    font-size: 12px;
  }
  table.tagihan-table th,
  table.tagihan-table td {
    border: 1px solid #e5e8f2;
    padding: 6px 8px;
    text-align: left;
  }
  table.tagihan-table th {
    background: #e3eaf7;
    color: #183688;
    font-weight: 600;
  }
  table.tagihan-table td {
    font-weight: 600;
  }
  table.riwayat-bayar th,
  table.riwayat-bayar td {
    font-size: 11.5px;
    padding: 5px 8px;
  }
  .status-row {
    font-weight: 700;
    font-size: 14px;
    margin-top: 12px;
  }
  .status-row span {
    vertical-align: middle;
  }
  .info-contact {
    font-size: 11px;
    margin-top: 15px;
    color: #173575;
  }
  .info-contact b {
    font-weight: 700;
    color: #113180;
  }
  .note {
    margin-top: 15px;
    padding: 10px 12px;
    font-size: 12px;
    background: #f5fff9;
    border-left: 5px solid #24b97a;
    border-radius: 5px;
    color: #213052;
  }
  .footer-ttd-kanan {
    width: 100%;
    margin-top: 25px;
    text-align: right;
    font-size: 11px;
  }
  .footer-ttd-kanan .ttd-petugas-kanan {
    font-weight: 700;
    font-size: 13px;
  }
</style>
</head>
<body>
  <div class="container">
<!-- KOP SURAT -->
<table width="100%" style="margin-bottom: 6px;">
  <tr>
    <!-- KIRI: LOGO -->
    <td style="width:110px; text-align:left; vertical-align:middle;">
      <img src="https://ppdbdk.pakarinformatika.web.id/assets/images/logo_trans.png"
           alt="Logo"
           style="height:80px; object-fit:contain;">
    </td>
    <!-- TENGAH: KOP INFO (CENTER, FULL WIDTH) -->
    <td style="text-align:center; vertical-align:middle;">
    <div style="font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #163984;">
        YAYASAN PENDIDIKAN DHARMA KARYA
    </div>
    <div style="font-size: 17px; font-weight: bold; letter-spacing: 1px; color: #163984;">
        SMA/SMK DHARMA KARYA
    </div>
    <div style="font-size: 14px; font-weight: bold; color: #163984; margin-bottom: 2px;">
        <b>Terakreditasi “A”</b>
    </div>
    <div style="font-size: 12px; color: #163984;">
        Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan
    </div>
    <div style="font-size: 12px; color: #163984;">
        Telp. 021-7398578 / 7250224
    </div>
    </td>

    <!-- KANAN: DUMMY (BIAR CENTER SEMPURNA) -->
    <td style="width:110px;"></td>
  </tr>
</table>
<div style="border-bottom: 4px solid #163984; margin: 0 10mm 18px 10mm; width: calc(100% - 20mm);"></div>

    <div class="header-content">
      <?php if ($status_pembayaran === 'Lunas' || $status_pembayaran === 'Angsuran'): ?>
        <div class="sub-title"><b>BUKTI PENDAFTARAN MURID BARU</b></div>
      <?php else: ?>
        <div class="sub-title"><b>BUKTI PENDAFTARAN CALON MURID BARU</b></div>
      <?php endif; ?>
      <div class="tahun-ajaran"><b>SISTEM PENERIMAAN MURID BARU (SPMB)</b></div>
      <div class="tahun-ajaran"><b>SMA DHARMA KARYA JAKARTA</b></div>
      <div class="tahun-ajaran" style="font-size:12px;"><b>TAHUN AJARAN 2025/2026</b></div>
    </div>

<div class="no-reg-bar" style="margin-bottom:12px;">
  <table width="100%" style="border-collapse: collapse;">
    <tr>
      <td style="width: 50%; padding: 0; vertical-align: middle;">
        <span class="no-reg-label">No. Registrasi Pendaftaran</span>
        <span class="no-reg-sep">:</span>
        <span class="no-reg-val"><i><b><?= safe($row['no_formulir']) ?></b></i></span>
      </td>
      <?php if (!empty($row['reviewed_by'])): ?>
      <td style="width: 50%; padding: 0; vertical-align: middle; text-align: right;">
        <span class="no-reg-label">Call Center</span>
        <span class="no-reg-sep">:</span>
        <span class="callcenter-badge"><?= safe($row['reviewed_by']) ?></span>
      </td>
      <?php else: ?>
      <td style="width: 50%;"></td>
      <?php endif; ?>
    </tr>
  </table>

<?php
$is_ppdb_bersama = (strtoupper(trim($status_pendaftaran)) === 'PPDB BERSAMA');
if (($status_pembayaran !== 'Belum Bayar' || $is_ppdb_bersama) && !empty($no_invoice)): ?>
  <div class="no-reg-row" style="margin-top:8px;">
    <span class="no-reg-label">No. Formulir Pendaftaran</span>
    <span class="no-reg-sep">:</span>
    <span class="no-reg-val"><i><b><?= safe($no_invoice) ?></b></i></span>
  </div>
<?php endif; ?>

</div>

<div style="font-weight:bold; font-size:18px; color:#1a53c7; text-align:center; margin-bottom:5px; text-transform:uppercase;">
  DATA MURID BARU
</div>
    <table class="data-table">
      <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td></tr>
      <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
    </table>

<?php if (strtoupper($status_pendaftaran) !== 'PPDB BERSAMA'): ?>
    <table class="tagihan-table">
      <tr>
        <th colspan="2" style="text-align:center; font-weight:bold; font-size:14px; background:#e3eaf7;">
          Keterangan Pembayaran
        </th>
      </tr>
      <?php if(count($tagihan)): ?>
        <?php foreach($tagihan as $tg): ?>
          <tr>
            <td><?= safe($tg['jenis']) ?></td>
            <td style="text-align:right; font-weight:600;">
              Rp <?= number_format($tg['nominal'], 0, ',', '.') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="2" style="text-align:center; color:#bb2222;">Belum ada tagihan yang diverifikasi.</td></tr>
      <?php endif; ?>
    </table>

    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
      <div style="margin:10px 0 3px 0; font-weight:600;">Riwayat Pembayaran:</div>
      <table class="tagihan-table riwayat-bayar" style="font-size:11px;">
        <colgroup>
          <col style="width:20%">
          <col style="width:20%">
          <col style="width:15%">
          <col style="width:15%">
          <col style="width:10%">
          <col style="width:20%">
        </colgroup>
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
          <td style="text-align:right;">Rp <?= number_format($b['jumlah'], 0, ',', '.') ?></td>
          <td style="text-align:right;"><?= ($b['cashback'] ?? 0) > 0 ? 'Rp ' . number_format($b['cashback'], 0, ',', '.') : '-' ?></td>
          <td><?= safe($b['status_pembayaran']) ?></td>
          <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
          <td><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
<?php endif; ?>
<div class="status-row">
  Status Pembayaran: 
  <?php

    if (strtoupper($status_pendaftaran) === 'PPDB BERSAMA') {
        echo '<span style="color:#31708f;font-weight:600;">PPDB Bersama</span>';
    } else {
        echo getStatusBadge($status_pembayaran);
    }
  ?>
</div>




    <div class="info-contact">
      Informasi lebih lanjut hubungi:<br>
      Hotline SMA : <b>081511519271</b> (Bu Puji)
    </div>

    <div class="note">
      <?php if ($status_pembayaran === 'Belum Bayar'): ?>
        <b>Catatan:</b><br>
        1. Apabila telah menyelesaikan administrasi, serahkan kembali form pendaftaran ini ke bagian pendaftaran untuk mendapatkan nomor Formulir.<br>
        2. Form Registrasi ini bukan menjadi bukti siswa tersebut diterima di SMA Dharma Karya. Siswa dinyatakan diterima apabila telah menyelesaikan administrasi dan mendapatkan nomor Formulir.
      <?php elseif ($status_pembayaran === 'Angsuran'): ?>
        <b>Catatan:</b><br>
        Siswa telah melakukan pembayaran sebagian (angsuran).<br>
        Simpan bukti ini sebagai tanda terima pembayaran.
      <?php elseif ($status_pembayaran === 'Lunas'): ?>
        <b>Catatan:</b><br>
        Siswa telah menyelesaikan seluruh pembayaran.<br>
        Simpan bukti ini sebagai tanda lunas dan konfirmasi pendaftaran.
      <?php else: ?>
        <b>Catatan:</b><br>
        Status pembayaran tidak diketahui.
      <?php endif; ?>
    </div>

    <div class="footer-ttd-kanan">
      <div class="ttd-petugas-kanan">
        Jakarta, <?= tanggal_id(date('Y-m-d')) ?><br><br>
        <?= safe($petugas) ?><br>
        (Petugas Pendaftaran)
      </div>
    </div>
  </div>
</body>
</html>

<?php
$html = ob_get_clean();
$mpdf->WriteHTML($html);
$mpdf->Output($save_path, \Mpdf\Output\Destination::FILE);

$pdf_url = "https://ppdbdk.pakarinformatika.web.id/pendaftaran/bukti/$filename";
echo "<div style='padding:22px;font-size:16px;font-family:Segoe UI,Arial;'>
PDF berhasil dibuat:<br>
<a href='$pdf_url' target='_blank'>$pdf_url</a>
</div>";
?>
