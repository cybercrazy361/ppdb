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
    body { font-family: Arial, sans-serif; font-size: 13px; color: #202b38; background: #f7f9fc; margin:0; }
    .pdf-main { background: #fff; border-radius: 11px; border: 1px solid #cdd8ec; margin: 10px auto; padding: 20px 30px; width: 650px; }
    .header-box { display: flex; flex-wrap:wrap; align-items: center; margin-bottom:10px;}
    .header-logo { width:78px; height:78px; margin-right:15px;}
    .header-info { flex:1; text-align: left; }
    .header-title { font-size:21px; font-weight:800; color: #163984; line-height:1.1;}
    .header-sub { font-size:15px; font-weight:700; color: #163984; }
    .header-addr { font-size:12px; font-weight:400; color:#163984; }
    .akreditasi { font-size:14px; color: #163984; font-weight:600;}
    .garis { border-bottom: 2.5px solid #163984; margin: 6px 0 15px 0; }
    .judul-box {text-align:center; margin-bottom:8px;}
    .judul-main {font-size:16.5px; font-weight:800; color:#205ec8;}
    .judul-sub {font-size:12.5px; font-weight:600; color:#1a2330;}
    .tahun-ajaran { font-size:13px; font-weight:700; color:#183688;}
    .data-row { margin-bottom: 6px; }
    .label { min-width:150px; display:inline-block; font-weight:600; color:#183688;}
    .reg-no { font-size:13px; font-weight:700; color:#163984; font-style:italic; }
    .call-center { float:right; font-size:11.5px; color:#1a53c7; margin-top:4px;}
    .tbl-data { width:100%; border-collapse:collapse; margin: 10px 0 10px 0;}
    .tbl-data th, .tbl-data td { font-size:12.5px; padding:5px 9px; border:1px solid #d2dbe7;}
    .tbl-data th { background:#e8ecfa; color:#183688; text-align:left;}
    .tbl-data td { background:#f7faff; }
    .status-info {background:#e8ecfa; border-radius:7px; padding:10px 15px 6px 15px; margin-bottom:8px; border:1px solid #c8d7ee;}
    .status-label {color:#205ec8; font-weight:700;}
    .status-row2 { font-size:13px; margin:6px 0; font-weight:600; }
    .status-belum { color: #e74a3b; font-weight:700;}
    .status-lunas { color: #1cc88a; font-weight:700;}
    .status-angsuran { color: #f6c23e; font-weight:700;}
    .box-ket { background:#f7fafd; border-radius:7px; border:1px solid #bdd1f7; padding:9px 13px; margin-bottom:10px;}
    .box-pembayaran { border:1px solid #c5d6ee; background:#f8fcff; border-radius:8px; margin:10px 0;}
    .box-pembayaran th, .box-pembayaran td {font-size:12px; padding:5px 10px;}
    .catatan-box { background: #fff1f1; border:1px solid #e74a3b; border-radius:8px; margin-top:12px; padding:9px 14px; font-size:12.5px;}
    .ttd-kanan { text-align:right; margin-top:35px; }
    .ttd-label { font-size:11.5px; color:#555; }
    .no-print { display:none; }
  </style>
</head>
<body>
<div class="pdf-main">
  <div class="header-box">
    <img src="https://ppdbdk.pakarinformatika.web.id/assets/images/logo_trans.png" class="header-logo" />
    <div class="header-info">
      <div class="header-title">YAYASAN PENDIDIKAN DHARMA KARYA</div>
      <div class="header-sub">SMA/SMK DHARMA KARYA</div>
      <div class="akreditasi">Terakreditasi “A”</div>
      <div class="header-addr">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
      <div class="header-addr">Telp. 021-7398578 / 7250224</div>
    </div>
  </div>
  <div class="garis"></div>
  <div class="judul-box">
    <div class="judul-main">BUKTI PENDAFTARAN CALON MURID BARU</div>
    <div class="judul-sub">SISTEM PENERIMAAN MURID BARU (SPMB)<br><b>SMA DHARMA KARYA JAKARTA</b></div>
    <div class="tahun-ajaran">TAHUN AJARAN 2025/2026</div>
  </div>
  <div style="margin-bottom:8px;">
    <span class="label">No. Registrasi Pendaftaran</span> : <span class="reg-no"><?= safe($row['no_formulir']) ?></span>
    <span class="call-center">Call Center: Tri Puji</span>
  </div>

  <table class="tbl-data">
    <tr><th width="38%">Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td></tr>
    <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
    <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
    <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
    <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
    <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
    <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
    <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
  </table>

  <div class="status-info">
    <span class="status-label">Status Pendaftaran</span> : <?= safe($status_pendaftaran) ?><br/>
    <span class="status-label">Keterangan</span> : <?= safe($keterangan_pendaftaran) ?>
  </div>

  <div class="box-ket">
    <b><u>Keterangan Pembayaran</u></b><br>
    <span style="color:#e74a3b; font-weight:600;">Belum ada tagihan yang diverifikasi.</span>
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

  <div style="font-size:12px; margin:5px 0 7px 0;">
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
