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

// ==== BEGIN OUTPUT HTML ====
$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 12,
    'margin_left' => 8,
    'margin_right' => 8,
    'margin_bottom' => 12,
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
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11pt; color: #202b38; margin: 0; background: #f4f6fb;}
        .container { background: #fff; width: 100%; min-height: 270mm; margin: 0; border-radius: 10px; padding: 4mm 7mm 8mm 7mm; border: 1px solid #dde1ee; position: relative; }
        .kop-surat-rel { display: flex; align-items: center; margin-bottom: 2mm; min-height: 75px; }
        .kop-logo-abs { width: 100px; height: 100px; object-fit: contain; }
        .kop-info-center { margin: 0 auto; width: 90%; text-align: center; }
        .kop-title1 { font-size: 20pt; font-weight: 700; color: #163984; margin-bottom: 0; margin-top: 3px; letter-spacing: 0.7px; }
        .kop-title2 { font-size: 15pt; font-weight: 800; color: #163984; margin-bottom: 2px; letter-spacing: 0.8px; }
        .kop-akreditasi { font-size: 12pt; color: #163984; font-weight: 500; margin-bottom: 1.5px; }
        .kop-alamat { font-size: 11pt; color: #163984; font-weight: 400; margin-bottom: 1px; }
        .kop-garis { border-bottom: 2px solid #163984; margin-bottom: 8px; margin-top: 4px; }
        .header-content { text-align: center; margin-bottom: 22px; }
        .sub-title { font-size: 15pt; letter-spacing: 0.1px; color: #163984; margin-bottom: 0; }
        .tahun-ajaran { font-size: 13.5pt; font-weight: 600; color: #163984; margin-bottom: 0; margin-top: 0; }
        .no-reg-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; gap: 14px; width: 100%; }
        .callcenter-badge { display: inline-flex; align-items: center; background: #e6edfa; padding: 5px 18px 5px 12px; border-radius: 16px; font-size: 12pt; font-weight: 600; color: #1a4299; letter-spacing: 0.2px; }
        .callcenter-badge i { margin-right: 7px; color: #2496db; font-size: 13pt; }
        .no-reg-row { display: flex; align-items: center; margin-bottom: 2px; font-size: 12pt; }
        .no-reg-label { min-width: 140px; font-weight: 500; }
        .no-reg-sep { min-width: 12px; text-align: center; font-weight: 700; }
        .no-reg-val { font-weight: 700; font-style: italic; color: #1a53c7; }
        .data-table { border-collapse: collapse; margin-top: 8px; margin-bottom: 4px; width: 100%; background: #f9fafd; font-size: 12pt;}
        .data-table caption { caption-side: top; font-weight: bold; font-size: 13pt; color: #163984; background: #e8ecfa; text-align: center; padding: 7px 0 6px 0; border-radius: 3px 3px 0 0; }
        .data-table th, .data-table td { padding: 5px 6px; border-bottom: 1px solid #e8eaf3; text-align: left; }
        .data-table th { width: 38%; background: #e8ecfa; color: #163984; font-weight: 600; border-right: 1px solid #f0f1fa; }
        .data-table td { background: #fff; }
        .tagihan-table { border-collapse: collapse; width: 100%; background: #f8fafb; margin-top: 6px; font-size: 11pt;}
        .tagihan-table th, .tagihan-table td { border: 1px solid #e5e8f2; padding: 5px 6px; }
        .tagihan-table th { background: #e3eaf7; color: #183688; font-weight: 600; }
        .riwayat-bayar th, .riwayat-bayar td { font-size: 10pt; padding: 4px 5px;}
        .status-row { margin: 7px 0 5px 0; font-size: 12pt; font-weight: 600; }
        .note { margin-top: 7px; padding: 7px 10px 6px 9px; font-size: 11pt; border-radius: 5px; background: #f7faff; color: #213052; border-left: 3px solid #8190ef;}
        .note.lunas { border-left-color: #24b97a; background: #f5fff9; }
        .note.angsuran { border-left-color: #efb91d; background: #fff9ed; }
        .note.belum-bayar { border-left-color: #e14e4e; background: #fff4f4; }
        .footer-ttd-kanan { width: 100%; display: flex; justify-content: flex-end; margin-top: 16px;}
        .ttd-block-kanan { text-align: right; font-size: 12pt;}
        .ttd-tanggal-kanan { margin-bottom: 15px; }
        .ttd-petugas-kanan { font-weight: 700; font-size: 13pt;}
        .ttd-label-kanan { font-size: 11pt; margin-top: 1px; color: #555;}
        .info-contact { font-size: 11pt; margin-top: 11px; margin-bottom: 0.5px; color: #173575;}
        .tgl-lebar { min-width: 60px;}
    </style>
</head>
<body>
<div class="container">
    <div class="kop-surat-rel">
        <img src="<?= __DIR__ . '/../assets/images/logo_trans.png' ?>" class="kop-logo-abs" />
        <div class="kop-info-center">
            <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
            <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
            <div class="kop-akreditasi"><b>Terakreditasi “A”</b></div>
            <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
            <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
        </div>
    </div>
    <div class="kop-garis"></div>
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
    <div class="no-reg-bar">
        <div class="no-reg-row" style="margin-bottom:0;">
            <div class="no-reg-label"><b>No. Registrasi Pendaftaran</b></div>
            <div class="no-reg-sep">:</div>
            <div class="no-reg-val"><b><i><?= safe($row['no_formulir']) ?></i></b></div>
        </div>
        <?php if (!empty($row['reviewed_by'])): ?>
            <span class="callcenter-badge">
                <i class="fas fa-headset"></i>
                <b>Call Center:</b> <?= safe($row['reviewed_by']) ?>
            </span>
        <?php endif; ?>
    </div>
    <?php if ($status_pembayaran !== 'Belum Bayar' && !empty($row['no_invoice'])): ?>
        <div class="no-reg-row">
            <div class="no-reg-label"><b>No. Formulir Pendaftaran</b></div>
            <div class="no-reg-sep">:</div>
            <div class="no-reg-val"><b><i><?= safe($row['no_invoice']) ?></i></b></div>
        </div>
    <?php endif; ?>

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

    <div class="status-keterangan-wrap">
        <table class="status-keterangan-table">
            <tr>
                <td class="status-ket-label">Status Pendaftaran</td>
                <td class="status-ket-sep">:</td>
                <td class="status-ket-value"><?= htmlspecialchars($status_pendaftaran) ?></td>
            </tr>
            <tr>
                <td class="status-ket-label">Keterangan</td>
                <td class="status-ket-sep">:</td>
                <td class="status-ket-value"><?= !empty($keterangan_pendaftaran) ? htmlspecialchars($keterangan_pendaftaran) : '-' ?></td>
            </tr>
        </table>
    </div>

    <table class="tagihan-table" style="margin-top:9px;">
        <tr>
            <th colspan="2" style="background:#e3eaf7;font-size:13.5px;text-align:center">
                <i class="fas fa-coins"></i> Keterangan Pembayaran
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

    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
        <div style="margin:9px 0 2px 0;font-size:12.5px;font-weight:500;">Riwayat Pembayaran:</div>
        <table class="tagihan-table riwayat-bayar" style="margin-bottom:9px;">
            <colgroup>
                <col style="width:18%">
                <col style="width:18%">
                <col style="width:18%">
                <col style="width:14%">
                <col style="width:10%">
                <col style="width:22%">
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
                <td style="text-align:right;">Rp <?= number_format($b['jumlah'],0,',','.') ?></td>
                <td style="text-align:right;">
                    <?= ($b['cashback'] ?? 0) > 0 ? 'Rp ' . number_format($b['cashback'],0,',','.') : '-' ?>
                </td>
                <td><?= safe($b['status_pembayaran']) ?></td>
                <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
                <td class="tgl-lebar"><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="status-row">
        Status Pembayaran: <?= htmlspecialchars($status_pembayaran) ?>
    </div>

    <div class="row-btm">
        <div class="info-contact">
            Informasi lebih lanjut hubungi:<br>
            Hotline SMA : <b>081511519271</b> (Bu Puji)
        </div>
    </div>

    <?php
    $note_class = '';
    if ($status_pembayaran === 'Belum Bayar') $note_class = 'belum-bayar';
    elseif ($status_pembayaran === 'Angsuran') $note_class = 'angsuran';
    elseif ($status_pembayaran === 'Lunas') $note_class = 'lunas';
    ?>
    <div class="note <?= $note_class ?>">
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
