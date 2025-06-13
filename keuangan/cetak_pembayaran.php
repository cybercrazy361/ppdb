<?php
// cetak_pembayaran.php

if (session_status() == PHP_SESSION_NONE) session_start();

include '../database_connection.php';

/**
 * Mengubah angka menjadi kata (bahasa Indonesia)
 */
function terbilang($angka) {
    $angka = (int) $angka;
    $abil  = ["nol","satu","dua","tiga","empat","lima","enam","tujuh","delapan","sembilan","sepuluh","sebelas"];
    if ($angka < 12) return $abil[$angka];
    elseif ($angka < 20) return terbilang($angka - 10) . " belas";
    elseif ($angka < 100) {
        $puluh = intval($angka / 10);
        $sisa  = $angka % 10;
        return terbilang($puluh) . " puluh" . ($sisa ? " " . terbilang($sisa) : "");
    }
    elseif ($angka < 200) return "seratus" . (($angka-100)? " " . terbilang($angka-100):"");
    elseif ($angka < 1000) {
        $ratus = intval($angka / 100);
        $sisa  = $angka % 100;
        return terbilang($ratus) . " ratus" . ($sisa ? " " . terbilang($sisa) : "");
    }
    elseif ($angka < 2000) return "seribu" . (($angka-1000)? " " . terbilang($angka-1000):"");
    elseif ($angka < 1000000) {
        $ribuan = intval($angka / 1000);
        $sisa   = $angka % 1000;
        return terbilang($ribuan) . " ribu" . ($sisa ? " " . terbilang($sisa) : "");
    }
    elseif ($angka < 1000000000) {
        $juta = intval($angka / 1000000);
        $sisa = $angka % 1000000;
        return terbilang($juta) . " juta" . ($sisa ? " " . terbilang($sisa) : "");
    }
    return "";
}

// Validasi login
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['keuangan', 'admin'])) {
    header('Location: login_keuangan.php'); exit();
}

// Validasi ID pembayaran
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die("ID pembayaran tidak valid.");
$pembayaran_id = (int)$_GET['id'];

// Fetch data pembayaran
$stmt = $conn->prepare("
    SELECT 
        pembayaran.id AS pembayaran_id,
        siswa.no_formulir,
        siswa.nama,
        siswa.unit,
        pembayaran.jumlah,
        pembayaran.metode_pembayaran,
        pembayaran.tahun_pelajaran,
        pembayaran.tanggal_pembayaran,
        pembayaran.keterangan
    FROM pembayaran
    INNER JOIN siswa ON pembayaran.no_formulir = siswa.no_formulir
    WHERE pembayaran.id = ?
");
$stmt->bind_param('i', $pembayaran_id);
$stmt->execute();
$result = $stmt->get_result();
$pembayaran = $result->fetch_assoc();
$stmt->close();
if (!$pembayaran) die("Data pembayaran tidak ditemukan.");

// Fetch detail pembayaran
$stmt_detail = $conn->prepare("
    SELECT 
        pembayaran_detail.jenis_pembayaran_id,
        jenis_pembayaran.nama AS jenis_pembayaran_nama,
        pembayaran_detail.jumlah AS detail_jumlah,
        pembayaran_detail.cashback,
        pembayaran_detail.bulan,
        pembayaran_detail.status_pembayaran
    FROM pembayaran_detail
    INNER JOIN jenis_pembayaran ON pembayaran_detail.jenis_pembayaran_id = jenis_pembayaran.id
    WHERE pembayaran_detail.pembayaran_id = ?
    ORDER BY pembayaran_detail.bulan ASC
");
$stmt_detail->bind_param('i', $pembayaran_id);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$details = [];
while ($row = $result_detail->fetch_assoc()) $details[] = $row;
$stmt_detail->close();

// Cek jika ada cashback pada detail
$ada_cashback = false;
foreach ($details as $d) if (isset($d['cashback']) && $d['cashback'] > 0) { $ada_cashback = true; break; }

// Fetch layout settings
$unit = $pembayaran['unit'];
$stmt_layout = $conn->prepare("SELECT element_name, x_position_mm, y_position_mm, visible, font_size, font_family, paper_width_mm, paper_height_mm FROM receipt_layout WHERE unit = ? ORDER BY id ASC");
$stmt_layout->bind_param('s', $unit);
$stmt_layout->execute();
$result_layout = $stmt_layout->get_result();
$layouts = $result_layout->fetch_all(MYSQLI_ASSOC);
$stmt_layout->close();

$layout_settings = [];
$layout_data = [];
$paper_width_mm = 240.0; // Standar
$paper_height_mm = 140.0;

foreach ($layouts as $l) {
    $layout_data[$l['element_name']] = $l;
    if ($l['visible']) $layout_settings[$l['element_name']] = ['x' => $l['x_position_mm'], 'y' => $l['y_position_mm']];
    $paper_width_mm = $l['paper_width_mm'];
    $paper_height_mm = $l['paper_height_mm'];
}

// Format tanggal dan jumlah
$tanggal_pembayaran = date('d-m-Y', strtotime($pembayaran['tanggal_pembayaran']));
$jumlah_pembayaran = number_format($pembayaran['jumlah'], 0, ',', '.');

// Fungsi dapat nilai elemen
function getElementValue($elementName) {
    global $pembayaran, $details, $layout_data, $jumlah_pembayaran, $tanggal_pembayaran;
    switch ($elementName) {
        case 'no_formulir': return htmlspecialchars($pembayaran['no_formulir']);
        case 'nama': return htmlspecialchars($pembayaran['nama']);
        case 'unit': return htmlspecialchars($pembayaran['unit']);
        case 'tahun_pelajaran': return htmlspecialchars($pembayaran['tahun_pelajaran']);
        case 'jumlah': return 'Rp ' . $jumlah_pembayaran;
        case 'metode_pembayaran': return htmlspecialchars($pembayaran['metode_pembayaran']);
        case 'tanggal_pembayaran': return htmlspecialchars($tanggal_pembayaran);
        case 'keterangan': return htmlspecialchars($pembayaran['keterangan']);
        default: return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kwitansi Pembayaran</title>
    <style>
        @media print {
            @page { size: 240mm 140mm; margin: 0; }
            html, body {
                width: 240mm; height: 140mm; margin:0; padding:0;
                background: #fff !important; color: #000 !important;
                font-family: 'Courier New', Courier, monospace !important; font-size: 13pt !important;
            }
            .receipt-container { width: 240mm; height: 140mm; position: relative; margin: 0; padding: 0; }
            .receipt-element {
                position: absolute !important; background: none !important; border: none !important;
                font-family: 'Courier New', Courier, monospace !important;
                font-size: 13pt !important; color: #000 !important; padding: 0 !important; margin: 0 !important; white-space: pre-line !important;
            }
            table {
                border-collapse: collapse !important;
                width: 210mm !important;
                margin-left: auto; margin-right: auto;
                font-size: 12pt !important;
            }
            th, td {
                border: 1px solid #000 !important; padding: 2px 4px !important; font-size: 12pt !important;
            }
            .print-button { display: none !important; }
        }
        body { font-family: 'Courier New', Courier, monospace; font-size: 13pt; background: #fff; }
        .receipt-container { width: 240mm; min-height: 140mm; position: relative; margin: 0 auto; padding: 0; }
        .print-button { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="receipt-container">
    <?php
    // --- PENEMPATAN ELEMEN, Silakan atur X, Y di admin layout ---
    foreach ($layout_settings as $element => $pos) {
        $font_size = $layout_data[$element]['font_size'];
        $font_family = $layout_data[$element]['font_family'];
        $x = htmlspecialchars($pos['x']);
        $y = htmlspecialchars($pos['y']);
        $style_inline = "font-size: {$font_size}pt; font-family: '{$font_family}';";
        switch ($element) {
            case 'header':
                ?>
                <div class="receipt-element" style="left: 0mm; top: <?= $y ?>mm; width: 240mm; text-align:center; <?= $style_inline ?>">
                    <strong style="font-size:15pt;">KUITANSI PEMBAYARAN</strong><br>
                    <span style="font-size:12pt;"><?= $unit; ?> DHARMA KARYA</span>
                </div>
                <?php break;

            case 'no_formulir':
            case 'nama':
            case 'unit':
            case 'tahun_pelajaran':
            case 'metode_pembayaran':
            case 'tanggal_pembayaran':
            case 'keterangan':
                ?>
                <div class="receipt-element" style="left: <?= $x ?>mm; top: <?= $y ?>mm; <?= $style_inline ?>">
                    <strong><?= ucfirst(str_replace('_', ' ', $element)); ?>:</strong> <?= getElementValue($element); ?>
                </div>
                <?php break;

            case 'details':
                ?>
                <div class="receipt-element" style="left: <?= $x ?>mm; top: <?= $y ?>mm; width: 210mm; <?= $style_inline ?>">
                    <strong>Rincian Pembayaran</strong>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:10mm;">No</th>
                                <th style="width:70mm;">Jenis Pembayaran</th>
                                <th style="width:30mm;">Jumlah</th>
                                <?php if ($ada_cashback): ?>
                                <th style="width:20mm;">Cashback</th>
                                <?php endif; ?>
                                <th style="width:20mm;">Bulan</th>
                                <th style="width:30mm;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; foreach ($details as $detail): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($detail['jenis_pembayaran_nama']) ?></td>
                                <td>Rp <?= number_format($detail['detail_jumlah'],0,',','.') ?></td>
                                <?php if ($ada_cashback): ?>
                                <td><?= $detail['cashback'] > 0 ? 'Rp ' . number_format($detail['cashback'],0,',','.') : '-' ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($detail['bulan']) ?></td>
                                <td><?= htmlspecialchars($detail['status_pembayaran']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <?php break;

            case 'jumlah':
                ?>
                <div class="receipt-element" style="left: <?= $x ?>mm; top: <?= $y ?>mm; <?= $style_inline ?>">
                    <strong>Jumlah Total:</strong> <?= getElementValue('jumlah'); ?>
                </div>
                <?php break;

            case 'terbilang':
                $total = $pembayaran['jumlah'];
                $angka = ucwords(trim(terbilang($total))) . ' Rupiah';
                ?>
                <div class="receipt-element" style="left: <?= $x ?>mm; top: <?= $y ?>mm; <?= $style_inline ?>">
                    <em>Terbilang: <?= htmlspecialchars($angka) ?></em>
                </div>
                <?php break;

            case 'footer':
                ?>
                <div class="receipt-element" style="left: <?= $x ?>mm; top: <?= $y ?>mm; width: 240mm; text-align:center; <?= $style_inline ?>">
                    <br>Terima kasih atas pembayaran Anda.<br>
                    Hormat Kami,<br><br><br><br>
                    ________________________<br>
                    Bagian Keuangan
                </div>
                <?php break;
        }
    }
    ?>
    </div>
    <div class="print-button">
        <button onclick="window.print()" class="btn btn-primary">Cetak Kwitansi</button>
    </div>
</body>
</html>
