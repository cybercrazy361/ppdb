<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

function safe($str) { return htmlspecialchars($str ?? '-'); }
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') die('Akses tidak diizinkan.');

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
    if ($status === 'lunas') return '<span style="color:#10b556;font-weight:600">🟢 Lunas</span>';
    if ($status === 'angsuran') return '<span style="color:#e5ac17;font-weight:600">🟡 Angsuran</span>';
    return '<span style="color:#e74a3b;font-weight:600">🔴 Belum Bayar</span>';
}

$no_invoice = $row['no_invoice'] ?? '';
$filename = 'bukti_pendaftaran_' . safe($row['no_formulir']) . '.pdf';
$save_path = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/' . $filename;

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
  color: #1e293b;
  background: #f4f7fa;
}
.container {
  background: #fff;
  width: 97%;
  min-height: 297mm;
  margin: 0 auto;
  border-radius: 14px;
  box-sizing: border-box;
  box-shadow: 0 2px 8px rgba(44,62,80,0.06);
  padding: 0;
}
.kop-surat-rel {
  display: flex;
  align-items: center;
  padding: 22px 36px 0 30px;
  gap: 20px;
}
.kop-logo {
  width: 75px;
  height: 75px;
  object-fit: contain;
  flex-shrink: 0;
  border-radius: 100px;
  border: 1.5px solid #e0e0e0;
  background: #fff;
}
.kop-info-center {
  flex-grow: 1;
  text-align: center;
  color: #174aa1;
}
.kop-title1 {
  font-size: 22px;
  font-weight: 700;
  margin: 0 0 3px 0;
  letter-spacing: 1.1px;
  color: #194293;
  text-transform: uppercase;
}
.kop-title2 {
  font-size: 17px;
  font-weight: 700;
  margin: 0 0 2px 0;
  letter-spacing: .8px;
  color: #1751be;
  text-transform: uppercase;
}
.kop-akreditasi {
  font-size: 13px;
  font-weight: 700;
  margin: 0 0 5px 0;
  color: #212c5e;
}
.kop-alamat {
  font-size: 12px;
  margin: 0 0 2px 0;
  color: #2e3643;
}
.kop-garis {
  border-bottom: 2px solid #1d3787;
  margin: 12px 32px 14px 32px;
  width: calc(100% - 64px);
}

.header-content {
  text-align: center;
  margin-bottom: 7px;
}
.header-content .sub-title {
  font-size: 17px;
  font-weight: 700;
  color: #1751be;
  margin: 0 0 3px 0;
}
.header-content .tahun-ajaran {
  font-size: 13px;
  font-weight: 600;
  color: #174aa1;
  margin: 0 0 2px 0;
  letter-spacing: .3px;
}

.info-box, .status-pendaftaran-box {
  background: #e8f3fd;
  border-radius: 10px;
  margin-bottom: 10px;
  padding: 12px 24px 10px 22px;
}
.status-pendaftaran-box {
  margin-bottom: 16px;
  display: flex;
  flex-direction: row;
  align-items: flex-start;
}
.status-pendaftaran-label {
  font-size: 15px;
  font-weight: 700;
  color: #155ea7;
  margin-right: 14px;
  min-width: 145px;
  display: inline-block;
}
.status-pendaftaran-value {
  font-size: 13.5px;
  font-weight: 600;
  color: #194293;
}
.status-pendaftaran-keterangan {
  margin-left: 40px;
  font-size: 13px;
}
.no-reg-bar {
  margin-bottom: 6px;
  margin-left: 1px;
  display: flex;
  gap: 20px;
}
.no-reg-row {
  font-size: 12.5px;
  font-weight: 600;
  margin-bottom: 3px;
  display: flex;
  gap: 8px;
}
.no-reg-label {
  color: #36394b;
  min-width: 180px;
}
.no-reg-val {
  color: #1958b5;
  font-weight: bold;
  font-style: italic;
}
.no-formulir-row {
  margin-left: 40px;
  font-size: 12px;
  margin-bottom: 2px;
}
.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-bottom: 15px;
  background: #f5f9ff;
  border-radius: 12px;
  overflow: hidden;
}
.data-table caption {
  background: #e1eafe;
  color: #133a72;
  font-weight: 700;
  font-size: 13.2px;
  padding: 8px 0 7px 0;
  border-radius: 12px 12px 0 0;
  text-align: left;
  text-indent: 22px;
  letter-spacing: .6px;
}
.data-table th,
.data-table td {
  border: none;
  padding: 7px 15px 7px 16px;
  text-align: left;
  font-size: 12.3px;
}
.data-table th {
  background: #e8f3fd;
  color: #174aa1;
  font-weight: 600;
  width: 33%;
}
.data-table tr {
  background: #fff;
  border-bottom: 1px solid #e6eaf6;
}
.data-table tr:last-child {
  border-bottom: none;
}
.data-table td {
  color: #293350;
  font-weight: 600;
}
.tagihan-table, .riwayat-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  margin-bottom: 7px;
  background: #f6faff;
  border-radius: 12px;
  overflow: hidden;
  font-size: 12px;
}
.tagihan-table th,
.tagihan-table td,
.riwayat-table th,
.riwayat-table td {
  border: none;
  padding: 6.5px 12px;
}
.tagihan-table th {
  background: #e4eefb;
  color: #1958b5;
  font-weight: 700;
  font-size: 13px;
  text-align: center;
}
.riwayat-table th {
  background: #e4eefb;
  color: #1958b5;
  font-weight: 700;
  font-size: 12px;
  text-align: center;
}
.riwayat-table td {
  text-align: center;
  font-size: 12px;
}
.riwayat-table td,
.tagihan-table td {
  font-weight: 600;
}
.status-row {
  font-weight: 700;
  font-size: 14px;
  margin: 14px 0 4px 1px;
}
.status-row span {
  vertical-align: middle;
}
.info-contact {
  font-size: 11.8px;
  margin: 13px 0 6px 2px;
  color: #174aa1;
}
.info-contact b {
  font-weight: 700;
  color: #164290;
}
.note {
  margin: 10px 0 0 1px;
  padding: 11px 18px;
  font-size: 12.5px;
  background: #e4f9ee;
  border-radius: 10px;
  color: #16402d;
  border-left: 6px solid #21b883;
}
.footer-ttd-kanan {
  width: 100%;
  margin-top: 34px;
  margin-bottom: 10px;
  text-align: right;
  font-size: 11.2px;
  color: #273765;
}
.footer-ttd-kanan .ttd-petugas-kanan {
  font-weight: 700;
  font-size: 14px;
  color: #1340aa;
  margin-top: 37px;
}
</style>
</head>
<body>
  <div class="container">
    <div class="kop-surat-rel">
      <img src="https://ppdbdk.pakarinformatika.web.id/assets/images/logo_trans.png" alt="Logo" class="kop-logo" />
      <div class="kop-info-center">
        <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi">Terakreditasi “A”</div>
        <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
        <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
      </div>
    </div>
    <div class="kop-garis"></div>
    <div class="header-content">
      <div class="sub-title"><b>BUKTI PENDAFTARAN MURID BARU</b></div>
      <div class="tahun-ajaran"><b>SISTEM PENERIMAAN MURID BARU (SPMB)</b></div>
      <div class="tahun-ajaran"><b>SMA DHARMA KARYA JAKARTA</b></div>
      <div class="tahun-ajaran" style="font-size:12.3px;"><b>TAHUN AJARAN 2025/2026</b></div>
    </div>
    <div class="no-reg-bar">
      <div class="no-reg-row">
        <span class="no-reg-label">No. Registrasi Pendaftaran :</span>
        <span class="no-reg-val"><?= safe($row['no_formulir']) ?></span>
      </div>
      <?php if ($status_pembayaran !== 'Belum Bayar' && !empty($no_invoice)): ?>
      <div class="no-reg-row">
        <span class="no-reg-label">No. Formulir Pendaftaran :</span>
        <span class="no-reg-val"><?= safe($no_invoice) ?></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="status-pendaftaran-box">
      <div>
        <div class="status-pendaftaran-label">Status Pendaftaran</div>
        <div class="status-pendaftaran-label">Keterangan</div>
      </div>
      <div>
        <div class="status-pendaftaran-value">: <?= safe($status_pendaftaran) ?></div>
        <div class="status-pendaftaran-keterangan">: <?= safe($keterangan_pendaftaran) ?></div>
      </div>
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
    <table class="tagihan-table">
      <tr>
        <th colspan="2" style="text-align:center; font-weight:bold; font-size:14px;">
          Keterangan Pembayaran
        </th>
      </tr>
      <?php if(count($tagihan)): ?>
        <?php foreach($tagihan as $tg): ?>
          <tr>
            <td><?= safe($tg['jenis']) ?></td>
            <td style="text-align:right; font-weight:700;">
              Rp <?= number_format($tg['nominal'], 0, ',', '.') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="2" style="text-align:center; color:#bb2222;">Belum ada tagihan yang diverifikasi.</td></tr>
      <?php endif; ?>
    </table>
    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
      <div style="margin:10px 0 3px 0; font-weight:600; color:#1958b5">Riwayat Pembayaran:</div>
      <table class="riwayat-table">
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
    <div class="status-row">
      Status Pembayaran: <?= getStatusBadge($status_pembayaran) ?>
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
      Jakarta, <?= tanggal_id(date('Y-m-d')) ?><br><br>
      <span class="ttd-petugas-kanan"><?= safe($petugas) ?></span><br>
      (Petugas Pendaftaran)
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
