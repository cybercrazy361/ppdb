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

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
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

// Cek jika ada cashback pada detail
$ada_cashback = false;
foreach ($details as $d) {
    if (isset($d['cashback']) && $d['cashback'] > 0) {
        $ada_cashback = true;
        break;
    }
}

// Fetch layout settings berdasarkan unit siswa
$unit = $pembayaran['unit']; // 'Yayasan', 'SMA', atau 'SMK'

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

// Convert layouts to associative array
$layout_settings = [];
$layout_data = [];
$paper_width_mm = 80.0; // Default nilai
$paper_height_mm = 120.0; // Default nilai

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

// Format tanggal pembayaran dan jumlah
$tanggal_pembayaran = date('d-m-Y', strtotime($pembayaran['tanggal_pembayaran']));
$jumlah_pembayaran = number_format($pembayaran['jumlah'], 0, ',', '.');

// Fungsi untuk mendapatkan nilai elemen
function getElementValue($elementName) {
    global $pembayaran, $details, $layout_data;
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
            return 'Rp ' . $GLOBALS['jumlah_pembayaran'];
        case 'metode_pembayaran':
            return htmlspecialchars($pembayaran['metode_pembayaran']);
        case 'tanggal_pembayaran':
            return htmlspecialchars($GLOBALS['tanggal_pembayaran']);
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
            size: <?= htmlspecialchars($paper_width_mm); ?>mm <?= htmlspecialchars($paper_height_mm); ?>mm;
            margin: 0;
        }
        body {
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12pt;
        }
        .receipt-container {
            position: relative;
            width: <?= htmlspecialchars($paper_width_mm); ?>mm;
            height: <?= htmlspecialchars($paper_height_mm); ?>mm;
            padding: 0 10mm 5mm;
            box-sizing: border-box;
            background-color: #fff;
            font-family: 'Courier New', Courier, monospace;
        }
        .receipt-element {
            position: absolute;
            white-space: nowrap;
            background-color: transparent !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .receipt-element table,
        .receipt-element table th,
        .receipt-element table td,
        .receipt-element h3,
        .receipt-element p {
            font-family: 'Courier New', Courier, monospace !important;
            font-size: inherit !important;
        }
        .receipt-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: <?= htmlspecialchars($paper_width_mm - 20); ?>mm;
            text-align: center;
            left: 50%;
            transform: translateX(-50%);
            font-family: 'Courier New', Courier, monospace;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 5mm;
            font-family: 'Courier New', Courier, monospace;
        }
        table, th, td {
            border: 1px solid #000;
        }
        th, td {
            padding: 4px;
            font-size: 10pt;
            font-family: 'Courier New', Courier, monospace;
        }
        .print-button {
            display: none;
        }
        .receipt-element.logo-element img {
            max-width: 100px;
            max-height: 100px;
            pointer-events: none;
        }
        .receipt-element.watermark-element {
            opacity: 0.3;
            transform: rotate(-45deg);
            text-align: center;
            width: 100%;
            pointer-events: none;
            font-family: 'Courier New', Courier, monospace;
        }
    }
    .receipt-container {
        position: relative;
        width: <?= htmlspecialchars($paper_width_mm); ?>mm;
        height: <?= htmlspecialchars($paper_height_mm); ?>mm;
        border: 1px solid #dee2e6;
        padding: 10mm;
        box-sizing: border-box;
        margin: 20px auto;
        background-color: #fff;
        border-radius: 1rem;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        overflow: hidden;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12pt;
    }
    .receipt-element {
        position: absolute;
        background-color: rgba(0, 123, 255, 0.3);
        border: 1px solid #007bff;
        border-radius: 0.5rem;
        padding: 2px 4px;
        font-size: 8pt;
        cursor: move;
        user-select: none;
        white-space: nowrap;
        transition: background-color 0.2s, border-color 0.2s;
        font-family: 'Courier New', Courier, monospace;
        font-weight: 600;
        color: #5a5c69;
    }
    .receipt-element:hover {
        background-color: rgba(0, 123, 255, 0.5);
        border-color: #0056b3;
    }
    .print-button {
        text-align: center;
        margin-top: 20px;
    }
    .receipt-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: <?= htmlspecialchars($paper_width_mm - 20); ?>mm;
        text-align: center;
        left: 50%;
        transform: translateX(-50%);
        font-family: 'Courier New', Courier, monospace;
    }
    .receipt-element.logo-element img {
        max-width: 100px;
        max-height: 100px;
        pointer-events: none;
    }
    .receipt-element.watermark-element {
        opacity: 0.3;
        transform: rotate(-45deg);
        text-align: center;
        width: 100%;
        pointer-events: none;
        font-family: 'Courier New', Courier, monospace;
    }
    @media print {
        .receipt-container {
            border: none !important;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
        .receipt-element {
            background-color: transparent !important;
            border: none !important;
        }
    }
</style>
</head>
<body>
    <div class="receipt-container">
        <?php
        // Tampilkan elemen sesuai layout
        foreach ($layout_settings as $element => $pos) {
            $font_size = $layout_data[$element]['font_size'];
            $font_family = $layout_data[$element]['font_family'];
            $x = htmlspecialchars($pos['x']);
            $y = htmlspecialchars($pos['y']);

            // Inline style dengan font-size dan font-family
            $style_inline = "font-size: {$font_size}pt; font-family: '{$font_family}';";

            switch ($element) {
                case 'logo':
                    if (!empty($layout_data[$element]['image_path'])) {
                        ?>
                        <div class="receipt-element logo-element"
                             style="left: <?= $x; ?>mm; top: <?= $y; ?>mm;">
                            <img src="<?= htmlspecialchars($layout_data[$element]['image_path']); ?>" alt="Logo">
                        </div>
                        <?php
                    }
                    break;

                case 'watermark':
                    if (!empty($layout_data[$element]['watermark_text'])) {
                        ?>
                        <div class="receipt-element watermark-element"
                             style="left: <?= $x; ?>mm; top: <?= $y; ?>mm; <?= $style_inline; ?>">
                            <?= htmlspecialchars($layout_data[$element]['watermark_text']); ?>
                        </div>
                        <?php
                    }
                    break;

                case 'header':
                    ?>
                    <div class="receipt-element receipt-header"
                        style="left: 50%; top: <?= htmlspecialchars($pos['y']); ?>mm; width: <?= htmlspecialchars($paper_width_mm - 20); ?>mm; transform: translateX(-50%); <?= $style_inline; ?>">
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
                         style="left: <?= htmlspecialchars($pos['x']); ?>mm; top: <?= htmlspecialchars($pos['y']); ?>mm; <?= $style_inline; ?>">
                        <p style="<?= $style_inline; ?>">
                            <strong><?= ucfirst(str_replace('_', ' ', $element)); ?>:</strong>
                            <?= getElementValue($element); ?>
                        </p>
                    </div>
                    <?php
                    break;

                case 'details':
                    ?>
                    <div class="receipt-element"
                         style="left: <?= htmlspecialchars($pos['x']); ?>mm; top: <?= htmlspecialchars($pos['y']); ?>mm; width: 60mm; <?= $style_inline; ?>">
                         <div style="width: <?= htmlspecialchars($paper_width_mm - 20); ?>mm; text-align: center;">
                            <h3 style="<?= $style_inline; ?> margin: 5px 0; padding: 0; display: inline-block;">Rincian Pembayaran</h3>
                        </div>
                        <?php if (!empty($details)) : ?>
                            <table style="width: <?= htmlspecialchars($paper_width_mm - 20); ?>mm !important; margin:0; padding:0; <?= $style_inline; ?>">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; width:8mm; <?= $style_inline; ?>">No</th>
                                        <th style="text-align:left; width:50mm; <?= $style_inline; ?>">Jenis Pembayaran</th>
                                        <th style="text-align:left; width:30mm; <?= $style_inline; ?>">Jumlah</th>
                                        <?php if ($ada_cashback): ?>
                                            <th style="text-align:left; width:20mm; <?= $style_inline; ?>">Cashback</th>
                                        <?php endif; ?>
                                        <th style="text-align:left; width:15mm; <?= $style_inline; ?>">Bulan</th>
                                        <th style="text-align:left; width:30mm; <?= $style_inline; ?>">Status</th>
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
                                            <td style="text-align:left; width:8mm; <?= $style_inline; ?>"><?= $no++; ?></td>
                                            <td style="text-align:left; width:50mm; <?= $style_inline; ?>"><?= htmlspecialchars($detail['jenis_pembayaran_nama']); ?></td>
                                            <td style="text-align:left; width:30mm; <?= $style_inline; ?>">Rp <?= $detail_jumlah; ?></td>
                                            <?php if ($ada_cashback): ?>
                                                <td style="text-align:left; width:20mm; <?= $style_inline; ?>"><?= $cashback; ?></td>
                                            <?php endif; ?>
                                            <td style="text-align:left; width:15mm; <?= $style_inline; ?>"><?= htmlspecialchars($detail['bulan']); ?></td>
                                            <td style="text-align:left; width:30mm; <?= $style_inline; ?>"><?= htmlspecialchars($detail['status_pembayaran']); ?></td>
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
                         style="left: <?= htmlspecialchars($pos['x']); ?>mm; top: <?= htmlspecialchars($pos['y']); ?>mm; <?= $style_inline; ?>">
                        <p style="<?= $style_inline; ?> margin: 0;">Terima kasih atas pembayaran Anda.</p>
                        <p style="<?= $style_inline; ?> margin: 0;">Hormat Kami,</p>
                        <p style="<?= $style_inline; ?> margin-top: 40px;">________________________</p>
                        <p style="<?= $style_inline; ?> margin: 0;">Bagian Keuangan</p>
                    </div>
                    <?php
                    break;

                case 'jumlah':
                    ?>
                    <div class="receipt-element"
                         style="left: <?= htmlspecialchars($pos['x']); ?>mm;
                                top:  <?= htmlspecialchars($pos['y']); ?>mm;
                                <?= $style_inline; ?>">
                        <p style="<?= $style_inline; ?>">
                            <strong>Jumlah Total:</strong>
                            <?= getElementValue('jumlah'); ?>
                        </p>
                    </div>
                    <?php
                    break;

                case 'terbilang':
                    $total = $pembayaran['jumlah'];
                    $angka = ucwords(trim(terbilang($total))) . ' Rupiah';
                    ?>
                    <div class="receipt-element"
                         style="
                           left: <?= htmlspecialchars($pos['x']); ?>mm;
                           top:  <?= htmlspecialchars($pos['y']); ?>mm;
                           font-size: <?= $layout_data['terbilang']['font_size']; ?>pt;
                           font-family: '<?= htmlspecialchars($layout_data['terbilang']['font_family']); ?>';
                         ">
                      Terbilang : <em><?= htmlspecialchars($angka); ?></em>
                    </div>
                    <?php
                    break;

                default:
                    // Elemen tidak dikenali
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
