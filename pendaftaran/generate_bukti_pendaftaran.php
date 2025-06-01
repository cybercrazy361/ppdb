<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require_once __DIR__ . '/../vendor/autoload.php'; // pastikan mPDF sudah di-install

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

$filename = 'bukti_pendaftaran_' . safe($row['no_formulir']) . '.pdf';
$save_path = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/' . $filename;

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 16,
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_bottom' => 12,
    'default_font' => 'Arial'
]);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pendaftaran PDF</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        .table-main { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .table-main th, .table-main td { border: 1px solid #222; padding: 6px 8px; text-align: left; }
        .table-main th { background: #e3eaf7; color: #1a4299; font-weight: 700; }
        .header-logo { width: 90px; height: 90px; vertical-align: top; }
        .kop-title1 { font-size: 18pt; font-weight: 700; color: #163984; }
        .kop-title2 { font-size: 13.5pt; font-weight: 700; color: #163984; }
        .kop-akreditasi, .kop-alamat { font-size: 10pt; color: #163984; }
        .section-title { font-size: 13pt; text-align: center; font-weight: bold; margin: 20px 0 7px 0; }
        .status-box { border: 1px solid #888; padding: 7px; background: #f7faff; margin-top: 13px; font-size: 10.5pt; }
        .footer-ttd { width: 100%; margin-top: 38px; }
        .ttd-right { text-align: right; font-size: 11pt; }
        .ttd-left { text-align: left; font-size: 11pt; }
        .catatan { border: 1px solid #8190ef; background: #f7faff; padding: 10px; margin-top: 14px; font-size: 10.3pt; }
    </style>
</head>
<body>

<!-- KOP -->
<table style="width:100%;border:none;">
<tr>
    <td style="width:100px;vertical-align:top;border:none;">
        <img src="<?= __DIR__.'/../assets/images/logo_trans.png' ?>" class="header-logo">
    </td>
    <td style="text-align:center;border:none;">
        <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi"><b>Terakreditasi “A”</b></div>
        <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
        <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
    </td>
</tr>
</table>
<hr style="border:1.7px solid #163984; margin:5px 0 11px 0">

<div class="section-title">BUKTI PENDAFTARAN CALON MURID BARU<br>
SMA DHARMA KARYA JAKARTA<br>
TAHUN AJARAN 2025/2026</div>

<!-- DATA SISWA -->
<table class="table-main">
    <tr><th>No. Registrasi</th><td><?= safe($row['no_formulir']) ?></td></tr>
    <tr><th>Nama</th><td><?= safe($row['nama']) ?></td></tr>
    <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
    <tr><th>Asal Sekolah</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
    <tr><th>Alamat</th><td><?= safe($row['alamat']) ?></td></tr>
    <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
    <tr><th>No. HP Ortu/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
    <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
</table>

<!-- STATUS DAN KETERANGAN -->
<table class="table-main" style="width:70%;margin-bottom:13px;">
    <tr>
        <th style="width:40%;">Status Pendaftaran</th>
        <td><?= htmlspecialchars($status_pendaftaran) ?></td>
    </tr>
    <tr>
        <th>Keterangan</th>
        <td><?= !empty($keterangan_pendaftaran) ? htmlspecialchars($keterangan_pendaftaran) : '-' ?></td>
    </tr>
</table>

<!-- TAGIHAN -->
<?php if(count($tagihan)): ?>
<table class="table-main" style="width:60%;margin-bottom:13px;">
    <tr><th colspan="2" style="text-align:center;">Keterangan Pembayaran</th></tr>
    <?php foreach($tagihan as $tg): ?>
    <tr>
        <td><?= safe($tg['jenis']) ?></td>
        <td style="text-align:right;font-weight:600">Rp <?= number_format($tg['nominal'], 0, ',', '.') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<div class="status-box" style="color:#bb2222;">Belum ada tagihan yang diverifikasi.</div>
<?php endif; ?>

<!-- RIWAYAT PEMBAYARAN -->
<?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
<div class="section-title" style="font-size:12pt;margin:10px 0 3px 0;">Riwayat Pembayaran</div>
<table class="table-main" style="font-size:10pt;">
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
        <td style="text-align:right;">Rp <?= number_format($b['jumlah'],0,',','.') ?></td>
        <td style="text-align:right;">
            <?= ($b['cashback'] ?? 0) > 0 ? 'Rp ' . number_format($b['cashback'],0,',','.') : '-' ?>
        </td>
        <td><?= safe($b['status_pembayaran']) ?></td>
        <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
        <td><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<!-- STATUS PEMBAYARAN -->
<div class="status-box">
    <b>Status Pembayaran:</b>
    <?= htmlspecialchars($status_pembayaran) ?>
</div>

<!-- CATATAN -->
<div class="catatan">
    <b>Catatan:</b><br>
    <?php if ($status_pembayaran === 'Belum Bayar'): ?>
        1. Apabila telah menyelesaikan administrasi, serahkan kembali form pendaftaran ini ke bagian pendaftaran untuk mendapatkan nomor Formulir.<br>
        2. Form Registrasi ini bukan menjadi bukti siswa tersebut diterima di SMA Dharma Karya.<br>
    <?php elseif ($status_pembayaran === 'Angsuran'): ?>
        Siswa telah melakukan pembayaran sebagian (angsuran). Simpan bukti ini sebagai tanda terima pembayaran.
    <?php elseif ($status_pembayaran === 'Lunas'): ?>
        Siswa telah menyelesaikan seluruh pembayaran. Simpan bukti ini sebagai tanda lunas dan konfirmasi pendaftaran.
    <?php else: ?>
        Status pembayaran tidak diketahui.
    <?php endif; ?>
</div>

<!-- TTD -->
<table class="footer-ttd">
<tr>
    <td class="ttd-left">
        Informasi lebih lanjut hubungi:<br>
        Hotline SMA : <b>081511519271</b> (Bu Puji)
    </td>
    <td class="ttd-right">
        Jakarta, <?= tanggal_id(date('Y-m-d')) ?><br>
        <br><br>
        <b><?= safe($petugas) ?></b><br>
        (Petugas Pendaftaran)
    </td>
</tr>
</table>

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
