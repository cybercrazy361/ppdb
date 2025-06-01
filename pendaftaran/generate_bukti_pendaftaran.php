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

$filename = 'bukti_pendaftaran_' . safe($row['no_formulir']) . '.pdf';
$save_path = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/' . $filename;

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 8,
    'margin_left' => 8,
    'margin_right' => 8,
    'margin_bottom' => 10,
    'default_font' => 'Arial'
]);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pendaftaran Siswa Baru</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0; }
        .container { background: #fff; width: 98%; margin: 0 auto; border-radius: 12px; border: 1.5px solid #d5deef; padding: 13px 16px 15px 16px; }
        .kop-table { width: 100%; border-collapse: collapse; }
        .kop-table td { vertical-align: top; }
        .kop-logo { width: 70px; height: 70px; }
        .kop-tengah { text-align: center; }
        .kop-title1 { font-size: 20px; font-weight: 700; color: #173c88; letter-spacing: 1.3px; }
        .kop-title2 { font-size: 16px; font-weight: 700; color: #173c88; margin-bottom: 1px; }
        .kop-akreditasi { font-size: 13px; color: #173c88; font-weight: 700; }
        .kop-alamat { font-size: 11px; color: #173c88; margin-top: 2px;}
        .kop-garis { border-bottom: 2.5px solid #193e92; margin: 8px 0 15px 0; }
        .judul-bukti { font-size: 18px; font-weight: 800; color: #1942a3; text-align: center; letter-spacing: 0.4px; margin-top: 10px; margin-bottom:0;}
        .judul-sub { font-size: 13px; font-weight: 700; color: #1942a3; text-align: center; }
        .judul-thn { font-size: 10.6px; font-weight: 700; color: #1942a3; text-align: center; margin-bottom: 12px; }
        .row-reg { width: 100%; margin-bottom: 5px; display: flex; align-items: center; }
        .reg-label { font-size: 12px; font-weight: 600; }
        .reg-value { color: #1044af; font-weight: 700; font-size: 12.2px; }
        .cc-badge { display: inline-block; background: #e6f3fd; border-radius: 12px; font-size: 11px; color: #1942a3; font-weight: 600; padding: 2.5px 11px 2.5px 8px; margin-left: 9px; }
        .cc-badge:before { content: "\1F4DE"; font-size: 12.5px; vertical-align: middle; margin-right: 3px; }
        /* TABLE DATA SISWA */
        .data-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; }
        .data-table caption { background: #e6edfa; color: #173e8e; font-size: 13px; font-weight: 700; padding: 6px 0 6px 0; border-radius: 5px 5px 0 0; letter-spacing: 0.2px; }
        .data-table th { background: #e6edfa; color: #1643a4; font-weight: 600; width: 31%; padding: 4px 8px; border-right: 1.5px solid #f4f6ff; text-align: left;}
        .data-table td { background: #fff; color: #1a2336; font-weight: 500; padding: 4px 8px; border-bottom: 1.3px solid #f1f3fa; }
        /* PANEL STATUS PENDAFTARAN */
        .status-panel { background: #e7f2fc; border-radius: 8px; margin: 16px 0 6px 0; padding: 10px 13px 8px 13px; font-size: 12px; }
        .status-panel th, .status-panel td { border: none; background: transparent; padding: 2px 7px 2px 1px; font-size: 12.2px;}
        .status-label { color: #156bc4; font-weight: 700; width:120px;}
        .status-value { font-weight: 600; color: #293d67; }
        /* PANEL KETERANGAN PEMBAYARAN */
        .keterangan-panel { background: #f3f7fb; border: 1.1px solid #e6e9ee; border-radius: 5px 5px 7px 7px; margin: 7px 0 7px 0;}
        .keterangan-panel th, .keterangan-panel td { border: none; background: transparent; padding: 5px 8px; text-align: left; font-size: 11.4px;}
        .panel-title { font-weight:700; color: #225da7; font-size: 12px; display: flex; align-items:center;}
        .panel-title .icon-info { display: inline-block; background:#d4eafd; border-radius:50%; width:17px; height:17px; text-align:center; margin-right:6px; font-size:12.6px;}
        .keterangan-warning { color: #c31e1e; font-size: 11.1px; font-weight: 500; padding:2px 2px 6px 0;}
        /* STATUS PEMBAYARAN */
        .status-row { font-size: 12px; font-weight: 600; color: #c31e1e; margin:10px 0 2px 0;}
        .status-row .status-icon { display:inline-block; vertical-align:middle; font-size:13px; margin-right:4px;}
        /* INFO KONTAK */
        .info-contact { font-size: 10px; color: #173575; margin-top: 9px; margin-bottom: 4px; }
        .info-contact b { font-weight: bold; font-size: 11.3px; color: #113180; }
        /* BOX CATATAN */
        .note { margin-top: 12px; padding: 9px 12px 8px 12px; font-size: 11px; border-radius: 7px; background: #fff6f6; color: #1a2336; border-left: 4px solid #e9402e; }
        .note-title { font-weight: 700; margin-bottom: 5px; }
        .note-list { margin-left: 15px; margin-bottom: 0; }
        /* FOOTER */
        .footer-ttd-kanan { width: 100%; margin-top: 26px;}
        .ttd-block-kanan { text-align: right; font-size: 11px;}
        .ttd-tanggal-kanan { margin-bottom: 11px; color:#1942a3; font-size:11.6px;}
        .ttd-petugas-kanan { font-weight: 700; font-size: 13px;}
        .ttd-label-kanan { font-size: 10px; margin-top: 1px; color: #555;}
    </style>
</head>
<body>
<div class="container">

    <!-- HEADER -->
    <table class="kop-table">
        <tr>
            <td style="width:80px;">
                <img src="<?= __DIR__ . '/../assets/images/logo_trans.png' ?>" class="kop-logo">
            </td>
            <td class="kop-tengah">
                <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
                <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
                <div class="kop-akreditasi">Terakreditasi “A”</div>
                <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
                <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
            </td>
        </tr>
    </table>
    <div class="kop-garis"></div>

    <!-- NOMOR REGISTRASI DAN CALL CENTER -->
    <div class="row-reg">
        <div class="reg-label">
            No. Registrasi Pendaftaran : <span class="reg-value"><?= safe($row['no_formulir']) ?></span>
        </div>
        <div style="flex:1"></div>
        <?php if (!empty($row['reviewed_by'])): ?>
            <span class="cc-badge">Call Center: <?= safe($row['reviewed_by']) ?></span>
        <?php endif; ?>
    </div>

    <!-- JUDUL UTAMA -->
    <div class="judul-bukti">BUKTI PENDAFTARAN CALON MURID BARU</div>
    <div class="judul-sub">SISTEM PENERIMAAN MURID BARU (SPMB)<br>SMA DHARMA KARYA JAKARTA</div>
    <div class="judul-thn">TAHUN AJARAN 2025/2026</div>

    <!-- TABEL DATA SISWA -->
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

    <!-- PANEL STATUS PENDAFTARAN -->
    <table class="status-panel">
        <tr>
            <th class="status-label">Status Pendaftaran</th>
            <td class="status-value">: <?= safe($status_pendaftaran) ?></td>
        </tr>
        <tr>
            <th class="status-label">Keterangan</th>
            <td class="status-value">: <?= safe($keterangan_pendaftaran) ?></td>
        </tr>
    </table>

    <!-- KETERANGAN PEMBAYARAN -->
    <table class="keterangan-panel">
        <tr>
            <th colspan="2" class="panel-title">
                <span class="icon-info">&#9432;</span> Keterangan Pembayaran
            </th>
        </tr>
        <tr>
            <td colspan="2" class="keterangan-warning">
                <?php if(count($tagihan)): ?>
                    <?php foreach($tagihan as $tg): ?>
                        <?= safe($tg['jenis']) ?>: <b>Rp <?= number_format($tg['nominal'], 0, ',', '.') ?></b><br>
                    <?php endforeach; ?>
                <?php else: ?>
                    Belum ada tagihan yang diverifikasi.
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- STATUS PEMBAYARAN -->
    <div class="status-row">
        <span class="status-icon">&#128308;</span> Status Pembayaran: <b>Belum Bayar</b>
    </div>

    <div class="info-contact">
        Informasi lebih lanjut hubungi:<br>
        Hotline SMA : <b>081511519271</b> (Bu Puji)
    </div>

    <!-- CATATAN -->
    <div class="note">
        <div class="note-title">Catatan:</div>
        <ol class="note-list">
            <li>Apabila telah menyelesaikan administrasi, serahkan kembali form pendaftaran ini ke bagian pendaftaran untuk mendapatkan nomor Formulir.</li>
            <li>Form Registrasi ini bukan menjadi bukti siswa tersebut diterima di SMA Dharma Karya. Siswa dinyatakan diterima apabila telah menyelesaikan administrasi dan mendapatkan nomor Formulir.</li>
        </ol>
    </div>

    <!-- FOOTER TTD -->
    <div class="footer-ttd-kanan">
        <div class="ttd-block-kanan">
            <div class="ttd-tanggal-kanan">Jakarta, <?= tanggal_id(date('Y-m-d')) ?></div>
            <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
            <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
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
