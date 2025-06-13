<?php
// cetak_pembayaran.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../database_connection.php';

/**
 * Mengubah angka menjadi kata (bahasa Indonesia)
 */
function terbilang($angka) {
    $angka = (int) $angka;
    $abil  = ["nol","satu","dua","tiga","empat","lima","enam","tujuh","delapan","sembilan","sepuluh","sebelas"];

    if ($angka < 12) {
        return $abil[$angka];
    } elseif ($angka < 20) {
        return terbilang($angka - 10) . " belas";
    } elseif ($angka < 100) {
        $puluh = intval($angka / 10);
        $sisa  = $angka % 10;
        return terbilang($puluh) . " puluh" . ($sisa ? " " . terbilang($sisa) : "");
    } elseif ($angka < 200) {
        $sisa = $angka - 100;
        return "seratus" . ($sisa ? " " . terbilang($sisa) : "");
    } elseif ($angka < 1000) {
        $ratus = intval($angka / 100);
        $sisa  = $angka % 100;
        return terbilang($ratus) . " ratus" . ($sisa ? " " . terbilang($sisa) : "");
    } elseif ($angka < 2000) {
        $sisa = $angka - 1000;
        return "seribu" . ($sisa ? " " . terbilang($sisa) : "");
    } elseif ($angka < 1000000) {
        $ribuan = intval($angka / 1000);
        $sisa   = $angka % 1000;
        return terbilang($ribuan) . " ribu" . ($sisa ? " " . terbilang($sisa) : "");
    } elseif ($angka < 1000000000) {
        $juta = intval($angka / 1000000);
        $sisa = $angka % 1000000;
        return terbilang($juta) . " juta" . ($sisa ? " " . terbilang($sisa) : "");
    }

    return "";
}

// Validasi session
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['keuangan', 'admin'])) {
    header('Location: login_keuangan.php');
    exit();
}

// Validasi ID pembayaran
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID pembayaran tidak valid.");
}
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
if ($stmt) {
    $stmt->bind_param('i', $pembayaran_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pembayaran = $result->fetch_assoc();
    $stmt->close();

    if (!$pembayaran) {
        die("Data pembayaran tidak ditemukan.");
    }
} else {
    die("Error preparing statement: " . $conn->error);
}

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
$details = [];
if ($stmt_detail) {
    $stmt_detail->bind_param('i', $pembayaran_id);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
    while ($row = $result_detail->fetch_assoc()) {
        $details[] = $row;
    }
    $stmt_detail->close();
} else {
    die("Error preparing detail statement: " . $conn->error);
}
$ada_cashback = false;
foreach ($details as $d) {
    if (isset($d['cashback']) && $d['cashback'] > 0) {
        $ada_cashback = true;
        break;
    }
}

// Fetch layout settings
$unit = $pembayaran['unit'];
$stmt_layout = $conn->prepare("SELECT element_name, x_position_mm, y_position_mm, visible, font_size, font_family, paper_width_mm, paper_height_mm, image_path, watermark_text FROM receipt_layout WHERE unit = ? ORDER BY id ASC");
if ($stmt_layout) {
    $stmt_layout->bind_param('s', $unit);
    $stmt_layout->execute();
    $result_layout = $stmt_layout->get_result();
    $layouts = $result_layout->fetch_all(MYSQLI_ASSOC);
    $stmt_layout->close();
} else {
    die("Error preparing layout statement: " . $conn->error);
}
$layout_settings = [];
$layout_data = [];
$paper_width_mm = 240.0;
$paper_height_mm = 140.0;

foreach ($layouts as $l) {
    $layout_data[$l['element_name']] = $l;
    if ($l['visible']) {
        $layout_settings[$l['element_name']] = [
            'x' => $l['x_position_mm'],
            'y' => $l['y_position_mm']
        ];
    }
    $paper_width_mm = $l['paper_width_mm'];
    $paper_height_mm = $l['paper_height_mm'];
}

$tanggal_pembayaran = date('d-m-Y', strtotime($pembayaran['tanggal_pembayaran']));
$jumlah_pembayaran = number_format($pembayaran['jumlah'], 0, ',', '.');

function getElementValue($elementName) {
    global $pembayaran, $details, $layout_data, $jumlah_pembayaran, $tanggal_pembayaran;
    switch ($elementName) {
        case 'no_formulir':
            return htmlspecialchars($pembayaran['no_formulir']);
        case 'nama':
            return htmlspecialchars($pembayaran['nama']);
        case 'unit':
            return htmlspecialchars($pembayaran['unit']);
        case 'tahun_pelajaran':
            return htmlspecialchars($pembayaran['tahun_pelajaran']);
        case 'jumlah':
            return 'Rp ' . $jumlah_pembayaran;
        case 'metode_pembayaran':
            return htmlspecialchars($pembayaran['metode_pembayaran']);
        case 'tanggal_pembayaran':
            return htmlspecialchars($tanggal_pembayaran);
        case 'keterangan':
            return htmlspecialchars($pembayaran['keterangan']);
        default:
            return '-';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kuitansi Pembayaran</title>
    <style>
@media print {
    @page {
        size: 240mm 140mm;
        margin: 0;
    }
    html, body {
        width: 240mm;
        height: 140mm;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        font-family: 'Courier New', Courier, monospace !important;
        font-size: 12pt !important;
    }
    .receipt-container {
        width: 240mm !important;
        height: 140mm !important;
        margin: 0 !important;
        padding: 0 !important;
        position: relative !important;
        background: #fff !important;
        border: none !important;
        box-shadow: none !important;
    }
    .print-button { display: none !important; }
}
body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12pt;
    background: #fff;
}
.receipt-container {
    position: relative;
    width: 240mm;
    min-height: 140mm;
    margin: 0 auto;
    padding: 0;
    background: #fff;
}
.receipt-header {
    width: 220mm;
    text-align: center;
    left: 50%;
    transform: translateX(-50%);
    font-size: 15pt;
    margin-bottom: 4mm;
    position: absolute;
}
.receipt-element {
    position: absolute;
    padding: 0;
    margin: 0;
    background: none;
    border: none;
    box-shadow: none;
    color: #000;
    white-space: normal;
}
table.rincian-table {
    border-collapse: collapse;
    width: 200mm !important;
    margin: 0;
    font-size: 11pt;
    background: #fff;
}
.rincian-table th, .rincian-table td {
    border: 1px solid #000;
    padding: 2px 4px;
    font-size: 11pt;
}
    </style>
</head>
<body>
<div class="receipt-container">
<?php
foreach ($layout_settings as $element => $pos) {
    $font_size = $layout_data[$element]['font_size'];
    $font_family = $layout_data[$element]['font_family'];
    $x = htmlspecialchars($pos['x']);
    $y = htmlspecialchars($pos['y']);
    $style_inline = "font-size: {$font_size}pt; font-family: '{$font_family}';";

    switch ($element) {
        case 'header':
?>
    <div class="receipt-element receipt-header"
        style="left: 50%; top: <?= htmlspecialchars($pos['y']); ?>mm; width: 220mm; transform: translateX(-50%); <?= $style_inline; ?>">
        <h2 style="<?= $style_inline; ?> margin: 0;">KUITANSI PEMBAYARAN</h2>
        <p style="<?= $style_inline; ?> margin:5px 0 0 0;"><?= $unit; ?> DHARMA KARYA</p>
    </div>
<?php
            break;
case 'no_formulir':
case 'nama':
case 'unit':
case 'tahun_pelajaran':
case 'metode_pembayaran':
case 'tanggal_pembayaran':
case 'keterangan':
?>
<div class="receipt-element"
     style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; <?= $style_inline; ?>">
    <p style="<?= $style_inline; ?>">
        <strong>
            <?php
                // Kalau elemen no_formulir, ganti label jadi No Register
                if ($element === 'no_formulir') {
                    echo 'No Register';
                } else {
                    echo ucfirst(str_replace('_', ' ', $element));
                }
            ?>:
        </strong>
        <?= getElementValue($element); ?>
    </p>
</div>
<?php
    break;

        case 'details':
?>
    <div class="receipt-element"
         style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; width:200mm; <?= $style_inline; ?>">
        <h3 style="<?= $style_inline; ?> margin: 5px 0; padding: 0; text-align:center; width:100%;">Rincian Pembayaran</h3>
        <?php if (!empty($details)) : ?>
        <table class="rincian-table">
            <thead>
            <tr>
                <th style="width:8mm;">No</th>
                <th style="width:48mm;">Jenis</th>
                <th style="width:27mm;">Jumlah</th>
                <?php if ($ada_cashback): ?>
                <th style="width:15mm;">Cashback</th>
                <?php endif; ?>
                <th style="width:15mm;">Bulan</th>
                <th style="width:27mm;">Status</th>
            </tr>
            </thead>
            <tbody>
            <?php 
            $no = 1;
            foreach ($details as $detail) : 
                $detail_jumlah = number_format($detail['detail_jumlah'], 0, ',', '.');
                $cashback = isset($detail['cashback']) && $detail['cashback'] > 0 
                    ? 'Rp ' . number_format($detail['cashback'], 0, ',', '.') 
                    : '-';
            ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td><?= htmlspecialchars($detail['jenis_pembayaran_nama']); ?></td>
                    <td>Rp <?= $detail_jumlah; ?></td>
                    <?php if ($ada_cashback): ?>
                        <td><?= $cashback; ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($detail['bulan']); ?></td>
                    <td><?= htmlspecialchars($detail['status_pembayaran']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
            <p style="<?= $style_inline; ?>">Tidak ada rincian pembayaran.</p>
        <?php endif; ?>
    </div>
<?php
            break;
        case 'footer':
?>
    <div class="receipt-element"
         style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; <?= $style_inline; ?>">
        <p style="<?= $style_inline; ?> margin: 0;">Terima kasih atas pembayaran Anda.</p>
        <p style="<?= $style_inline; ?> margin: 0;">Hormat Kami,</p><br>
        
        <?php if(isset($_SESSION['nama'])): ?>
            <p style="<?= $style_inline; ?> margin-top: 40px; font-weight:bold;">
                <?= htmlspecialchars($_SESSION['nama']); ?>
            </p>
        <?php endif; ?>
        <p style="<?= $style_inline; ?> margin: 0;">________________________</p>
    </div>
<?php
            break;
        case 'jumlah':
?>
    <div class="receipt-element"
         style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; <?= $style_inline; ?>">
        <p style="<?= $style_inline; ?>"><strong>Jumlah Total:</strong> <?= getElementValue('jumlah'); ?></p>
    </div>
<?php
            break;
        case 'terbilang':
            $total = $pembayaran['jumlah'];
            $angka = ucwords(trim(terbilang($total))) . ' Rupiah';
?>
    <div class="receipt-element"
         style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; font-size: <?= $layout_data['terbilang']['font_size']; ?>pt; font-family: '<?= htmlspecialchars($layout_data['terbilang']['font_family']); ?>';">
        Terbilang : <em><?= htmlspecialchars($angka); ?></em>
    </div>
<?php
            break;
    }
}
?>
</div>
<div class="print-button">
    <button onclick="window.print()" class="btn btn-primary">Cetak Kuitansi</button>
</div>
</body>
</html>
