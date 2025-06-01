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
        .container { background: #fff; width: 98%; margin: 0 auto; border-radius: 11px; border: 1.6px solid #d5deef; padding: 16px 18px 18px 18px; }
        .kop-table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
        .kop-table td { vertical-align: top; }
        .kop-logo { width: 70px; height: 70px; }
        .kop-tengah { text-align: center; }
        .kop-title1 { font-size: 21px; font-weight: 700; color: #173c88; letter-spacing: 1.2px; }
        .kop-title2 { font-size: 16px; font-weight: 700; color: #173c88; }
        .kop-akreditasi { font-size: 13.3px; color: #173c88; font-weight: 700; }
        .kop-alamat { font-size: 10.6px; color: #173c88; }
        .kop-garis { border-bottom: 2.7px solid #193e92; margin: 8px 0 15px 0; }
        /* NO REGISTRASI & CALL CENTER */
        .reg-row {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 7px;
        }
        .reg-left {
            font-size: 12.2px;
            font-weight: 600;
            color: #222;
        }
        .reg-value {
            color: #0d3eaa;
            font-weight: 700;
            font-size: 12.2px;
        }
        .reg-right {
            font-size: 11.7px;
            color: #1455ad;
            font-weight: 500;
            text-align: right;
            min-width: 180px;
        }
        .cc-badge {
            font-size: 11.7px;
            font-weight: 600;
            color: #0d3eaa;
            background: none;
            border-radius: 0;
            padding: 0;
        }
        /* JUDUL */
        .judul-bukti { font-size: 18px; font-weight: 800; color: #1942a3; text-align: center; letter-spacing: 0.5px; margin-top: 10px; margin-bottom:0;}
        .judul-sub { font-size: 13.3px; font-weight: 700; color: #1942a3; text-align: center; margin-bottom:0;}
        .judul-thn { font-size: 10.7px; font-weight: 700; color: #1942a3; text-align: center; margin-bottom: 14px; }
        /* TABEL DATA SISWA */
        .data-table { width: 100%; border-collapse: collapse; font-size: 11.3px; margin-top: 6px; margin-bottom: 8px;}
        .data-table caption { background: #e6edfa; color: #173e8e; font-size: 13px; font-weight: 700; padding: 6px 0 6px 0; border-radius: 5px 5px 0 0; letter-spacing: 0.2px; }
        .data-table th { background: #e6edfa; color: #1643a4; font-weight: 600; width: 32%; padding: 5px 10px; border-right: 1.5px solid #f4f6ff; text-align: left;}
        .data-table td { background: #fff; color: #232a3c; font-weight: 500; padding: 5px 10px; border-bottom: 1.3px solid #f1f3fa; }
        /* PANEL STATUS PENDAFTARAN */
        .panel-status {
            width: 100%;
            background: #e9f3fc;
            border-radius: 5px;
            margin: 0 0 14px 0;
            padding: 12px 13px 9px 13px;
            font-size: 15px;
            border: none;
        }
        .panel-status td { border: none; background: none; }
        .ps-label { color: #2470c6; font-weight: 600; font-size: 16px; width: 155px; }
        .ps-sep { color: #2470c6; font-weight: 600; width: 10px; }
        .ps-value { color: #1976b6; font-weight: 500; font-size: 16px; }
        /* PANEL KETERANGAN PEMBAYARAN */
        .center-panel {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 15px auto;
        }
        .panel-keterangan {
            background: #f4f8fc;
            border: 1px solid #dde8f4;
            border-radius: 8px;
            padding: 16px 22px 13px 22px;
            width: 500px;           /* atau 480px kalau mau lebih kecil */
            max-width: 95vw;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }

        .pk-title { color: #2570c6; font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .pk-icon { font-size: 15px; margin-right: 5px; color: #2570c6;}
        .pk-isi { color: #ca1818; font-size: 14px; font-weight: 500; }
        .pk-merah { color: #ca1818; }
        /* STATUS PEMBAYARAN */
        .panel-status-bayar {
            font-size: 15.3px; 
            font-weight: 600; 
            color: #be2020; 
            margin: 15px 0 3px 0;
        }
        .sb-icon { color: #be2020; font-size: 17px; vertical-align: middle; margin-right: 5px; }
        .sb-label { color: #be2020; font-weight: 700; margin-right: 4px; }
        .sb-value { color: #be2020; font-weight: 700; }
        /* INFO KONTAK */
        .info-contact { font-size: 10.1px; color: #173575; margin-top: 9px; margin-bottom: 4px; }
        .info-contact b { font-weight: bold; font-size: 11.5px; color: #113180; }
        /* BOX CATATAN */
        .note { margin-top: 13px; padding: 9px 13px 9px 13px; font-size: 11.2px; border-radius: 7px; background: #fff6f6; color: #1a2336; border-left: 4px solid #e9402e; }
        .note-title { font-weight: 700; margin-bottom: 5px; }
        .note-list { margin-left: 15px; margin-bottom: 0; }
        /* FOOTER */
        .footer-ttd-kanan { width: 100%; margin-top: 28px;}
        .ttd-block-kanan { text-align: right; font-size: 11px;}
        .ttd-tanggal-kanan { margin-bottom: 11px; color:#1942a3; font-size:11.7px;}
        .ttd-petugas-kanan { font-weight: 700; font-size: 13.5px;}
        .ttd-label-kanan { font-size: 10.3px; margin-top: 1px; color: #555;}
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
    <div class="reg-row">
        <div class="reg-left">
            No. Registrasi Pendaftaran : <span class="reg-value"><?= safe($row['no_formulir']) ?></span>
        </div>
        <?php if (!empty($row['reviewed_by'])): ?>
        <div class="reg-right">
            Call Center: <span class="cc-badge"><?= safe($row['reviewed_by']) ?></span>
        </div>
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
    <table class="panel-status">
        <tr>
            <td class="ps-label">Status Pendaftaran</td>
            <td class="ps-sep">:</td>
            <td class="ps-value"><?= safe($status_pendaftaran) ?></td>
        </tr>
        <tr>
            <td class="ps-label">Keterangan</td>
            <td class="ps-sep">:</td>
            <td class="ps-value"><?= safe($keterangan_pendaftaran) ?></td>
        </tr>
    </table>

    <!-- KETERANGAN PEMBAYARAN (CENTER) -->

        <div class="panel-keterangan">
            <div class="pk-title"><span class="pk-icon">&#9432;</span> Keterangan Pembayaran</div>
            <div class="pk-isi">
                <?php if(count($tagihan)): ?>
                    <?php foreach($tagihan as $tg): ?>
                        <?= safe($tg['jenis']) ?>: <b>Rp <?= number_format($tg['nominal'], 0, ',', '.') ?></b><br>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="pk-merah">Belum ada tagihan yang diverifikasi.</span>
                <?php endif; ?>
            </div>
        </div>

    <!-- STATUS PEMBAYARAN -->
    <div class="panel-status-bayar">
        <span class="sb-icon">&#9888;</span>
        <span class="sb-label">Status Pembayaran:</span>
        <span class="sb-value"><?= strtoupper($status_pembayaran) ?></span>
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
