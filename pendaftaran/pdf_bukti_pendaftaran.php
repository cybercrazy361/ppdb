<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require_once __DIR__ . '/../vendor/autoload.php'; // mPDF

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
        'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni',
        'July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $date = date('d', strtotime($tgl));
    $month = $bulan[date('F', strtotime($tgl))];
    $year = date('Y', strtotime($tgl));
    return "$date $month $year";
}

// Status progres
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

$no_invoice = $row['no_invoice'] ?? '';

$pdf_folder = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/';
$pdf_public_url = 'https://ppdbdk.pakarinformatika.web.id/pendaftaran/bukti/';
$pdf_filename = "bukti_pendaftaran_" . safe($row['no_formulir']) . ".pdf";
$pdf_fullpath = $pdf_folder . $pdf_filename;
$pdf_url = $pdf_public_url . $pdf_filename;

ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #222; background: #f8faff; }
    .main-wrap { width: 100%; max-width: 750px; background: #fff; border: 1px solid #d2e4f7; border-radius: 10px; margin: 10px auto; padding: 18px 26px; }
    .kop-table { width:100%; }
    .kop-logo { width:75px; }
    .kop-title1 { font-size: 20px; font-weight: 700; color: #1a3680; }
    .kop-title2 { font-size: 16px; font-weight: 600; color: #1a3680; }
    .kop-akreditasi { font-size: 13px; color: #1a3680; font-weight: 500; }
    .kop-alamat { font-size: 11px; color: #1a3680; }
    .kop-garis { border-bottom:2px solid #183688; margin:10px 0 12px 0; }
    .judul { text-align: center; font-size: 16px; color: #215dc8; font-weight: 700; margin:10px 0 3px 0;}
    .judul2 { text-align:center; font-size:12px; color:#183688; margin-bottom:2px;}
    .ta-th { color:#183688;font-size:12px;}
    .no-reg-row { margin-bottom: 8px; font-size:13px;}
    .call-center { float:right; font-size:12px; color:#1a53c7;}
    .tbl-data { width:100%; border-collapse:collapse; margin: 10px 0;}
    .tbl-data th, .tbl-data td { border:1px solid #d2dbe7; font-size:12.5px; padding:5px 8px;}
    .tbl-data th { background:#e8ecfa; color:#183688; font-weight:600; width:38%; text-align:left;}
    .tbl-data td { background:#f7faff; }
    .status-box { background:#e8ecfa; border:1px solid #c5d6ee; border-radius:7px; padding:9px 12px; margin:10px 0 8px 0;}
    .pembayaran-box { background:#f7fafd; border:1px solid #bdd1f7; border-radius:6px; padding:8px 12px; margin-bottom:7px; }
    .pembayaran-title { font-weight:700; color:#1a3680; text-decoration:underline;}
    .pembayaran-info { color:#e74a3b; font-weight:600; }
    .status-row2 { font-size:13px; margin:7px 0; font-weight:600; }
    .status-belum { color: #e74a3b; font-weight:700;}
    .status-lunas { color: #1cc88a; font-weight:700;}
    .status-angsuran { color: #f6c23e; font-weight:700;}
    .hotline { font-size:12px; margin:4px 0 5px 0; }
    .hotline b { font-weight:700; }
    .catatan-box { background:#fff5f5; border:1px solid #e74a3b; border-radius:8px; font-size:12px; color:#222; margin-top:7px; padding:10px 12px;}
    .ttd-kanan { text-align:right; margin-top:22px; font-size:12px;}
    .ttd-kanan .ttd-label { font-size:11px; color:#555; }
  </style>
</head>
<body>
<div class="main-wrap">
  <table class="kop-table">
    <tr>
      <td style="width:85px;" valign="top">
        <img src="https://ppdbdk.pakarinformatika.web.id/assets/images/logo_trans.png" class="kop-logo" />
      </td>
      <td valign="top">
        <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi">Terakreditasi “A”</div>
        <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
        <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
      </td>
    </tr>
  </table>
  <div class="kop-garis"></div>
  <div class="judul">BUKTI PENDAFTARAN CALON MURID BARU</div>
  <div class="judul2">SISTEM PENERIMAAN MURID BARU (SPMB)<br><b>SMA DHARMA KARYA JAKARTA</b></div>
  <div class="ta-th">TAHUN AJARAN 2025/2026</div>
  <div class="no-reg-row">
    <b>No. Registrasi Pendaftaran</b> : <span style="color:#183688;font-style:italic;font-weight:600;"><?= safe($row['no_formulir']) ?></span>
    <span class="call-center">Call Center: Tri Puji</span>
  </div>

  <table class="tbl-data">
    <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td></tr>
    <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
    <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
    <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
    <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
    <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
    <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
    <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
  </table>

  <div class="status-box">
    <b>Status Pendaftaran</b> : <?= safe($status_pendaftaran) ?><br>
    <b>Keterangan</b> : <?= safe($keterangan_pendaftaran) ?>
  </div>

  <div class="pembayaran-box">
    <span class="pembayaran-title">Keterangan Pembayaran</span><br>
    <span class="pembayaran-info">Belum ada tagihan yang diverifikasi.</span>
  </div>

  <div class="status-row2">
    Status Pembayaran:
    <?php if ($status_pembayaran == 'Lunas'): ?>
      <span class="status-lunas">Lunas</span>
    <?php elseif ($status_pembayaran == 'Angsuran'): ?>
      <span class="status-angsuran">Angsuran</span>
    <?php else: ?>
      <span class="status-belum">Belum Bayar</span>
    <?php endif; ?>
  </div>

  <div class="hotline">
    Informasi lebih lanjut hubungi:<br>
    Hotline SMA : <b>081511519271 (Bu Puji)</b>
  </div>

  <div class="catatan-box">
    <b>Catatan:</b><br>
    1. Apabila telah menyelesaikan administrasi, serahkan kembali form pendaftaran ini ke bagian pendaftaran untuk mendapatkan nomor Formulir.<br>
    2. Form Registrasi ini bukan menjadi bukti siswa tersebut diterima di SMA Dharma Karya. Siswa dinyatakan diterima apabila telah menyelesaikan administrasi dan mendapatkan nomor Formulir.
  </div>

  <div class="ttd-kanan">
    Jakarta, <?= tanggal_id(date('Y-m-d')) ?><br><br>
    <b><?= safe($petugas) ?></b><br>
    <span class="ttd-label">(Petugas Pendaftaran)</span>
  </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 4,
    'margin_right' => 4,
    'margin_top' => 5,
    'margin_bottom' => 5,
]);
$mpdf->WriteHTML($html);
$mpdf->Output($pdf_fullpath, \Mpdf\Output\Destination::FILE);

// Kirim ke WA Ortu
$token = "iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2";
$secret_key = "PAtwrvlV";
$no_wa = preg_replace('/[^0-9]/', '', $row['no_hp_ortu']);
if (substr($no_wa, 0, 1) === '0') $no_wa = '62' . substr($no_wa, 1);
if (substr($no_wa, 0, 2) !== '62') $no_wa = '62' . ltrim($no_wa, '0');

$data = [
    'phone' => $no_wa,
    'document' => $pdf_url,
    'caption' => 'Bukti Pendaftaran Siswa'
];
$curl = curl_init();
curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: $token.$secret_key"]);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($curl, CURLOPT_URL,  "https://bdg.wablas.com/api/send-document");
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

$result = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

// Feedback ke user (admin/operator)
echo "<h2>PDF sudah dibuat: <a href='$pdf_url' target='_blank'>$pdf_url</a></h2>";
echo "<h3>Nomor WA Ortu: $no_wa</h3>";
echo "<h3>Wablas response:</h3>";
echo "<pre>";
echo htmlspecialchars($result);
echo "</pre>";
if ($err) {
    echo "<br><b style='color:red'>CURL Error:</b> $err";
}
?>
