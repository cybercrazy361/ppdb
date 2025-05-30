<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require '../vendor/autoload.php'; // pastikan sudah composer require mpdf/mpdf

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID siswa tidak valid']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Data siswa tidak ditemukan']);
    exit;
}

// === COPY FUNGSI & QUERY TAMBAHAN DARI print_siswa.php JIKA DIPAKAI ===
// (tanggal_id, status, tagihan, petugas, dsb...)
// Untuk contoh ringkas, kita gunakan data utama saja.

// === Load CSS Print (inline) ===
$css = file_get_contents('../assets/css/print_bukti_pendaftaran.css');

// === Render ulang HTML (ISI BODY dari print_siswa.php, pastikan sesuai yang ingin dicetak) ===
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Bukti Pendaftaran Siswa Baru (<?= htmlspecialchars($row['no_formulir']) ?>)</title>
  <style><?= $css ?></style>
</head>
<body>
  <!-- ISI BODY print_siswa.php (tidak perlu tombol print) -->
  <div class="container">
    <!-- kop surat, data, dsb ... -->
    <div class="kop-surat-rel">
      <img src="../assets/images/logo_trans.png" alt="Logo" class="kop-logo-abs" />
      <div class="kop-info-center">
        <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi"><b>Terakreditasi “A”</b></div>
        <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
        <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
      </div>
    </div>
    <div class="kop-garis"></div>
    <!-- dst... (copy dari print_siswa.php sesuai kebutuhan) -->
    <div class="header-content">
      <div class="sub-title"><b>BUKTI PENDAFTARAN CALON MURID BARU</b></div>
      <div class="tahun-ajaran"><b>SISTEM PENERIMAAN MURID BARU (SPMB)</b></div>
      <div class="tahun-ajaran"><b>SMA DHARMA KARYA JAKARTA</b></div>
      <div class="tahun-ajaran" style="font-size:12px;"><b>TAHUN AJARAN 2025/2026</b></div>
    </div>
    <!-- Data tabel siswa -->
    <table class="data-table">
      <caption>DATA CALON PESERTA DIDIK BARU</caption>
      <tr><th>Nama Calon Peserta Didik</th><td><?= htmlspecialchars($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= htmlspecialchars($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah SMP/MTs</th><td><?= htmlspecialchars($row['asal_sekolah']) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= htmlspecialchars($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= htmlspecialchars($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= htmlspecialchars($row['no_hp_ortu']) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= htmlspecialchars($row['unit']) ?></td></tr>
    </table>
    <div class="footer-ttd-kanan">
      <div class="ttd-block-kanan">
        <div class="ttd-tanggal-kanan">Jakarta, <?= date('d-m-Y') ?></div>
        <div class="ttd-petugas-kanan"><?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();

// ========== Generate PDF ==========
$pdfName = 'bukti_pendaftaran_' . preg_replace('/\D/', '', $row['no_formulir']) . '.pdf';
$pdfDir = __DIR__ . '/../uploads/';
$pdfPath = $pdfDir . $pdfName;
if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

try {
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_left' => 0,
        'margin_right' => 0,
        'margin_top' => 0,
        'margin_bottom' => 0,
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal generate PDF: '.$e->getMessage()]);
    exit;
}

// ========== Kirim ke WA Ortu via Wablas ==========
$apiUrl = 'https://console.wablas.com/api/v2/send-document';
$apiKey = 'iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2'; // GANTI DENGAN punyamu

$host = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$pdfUrl = $host . '/uploads/' . $pdfName;

// Format nomor
$phone = preg_replace('/[^0-9]/', '', $row['no_hp_ortu']);
if (strpos($phone, '62') !== 0 && strpos($phone, '0') === 0) {
    $phone = '62' . substr($phone, 1);
}

$data = [
    "phone"   => $phone,
    "caption" => "Berikut bukti pendaftaran siswa baru atas nama " . $row['nama'] . ".",
    "url"     => $pdfUrl
];

$options = [
    'http' => [
        'header'  => "Authorization: $apiKey\r\nContent-Type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($apiUrl, false, $context);

if ($result === FALSE) {
    echo json_encode(['success' => false, 'message' => 'Gagal koneksi ke Wablas']);
    exit;
}
$resObj = json_decode($result, true);
if (isset($resObj['status']) && $resObj['status']) {
    echo json_encode(['success' => true, 'message' => 'Bukti berhasil dikirim ke WhatsApp orang tua!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal kirim ke WhatsApp', 'debug' => $result]);
}
?>
