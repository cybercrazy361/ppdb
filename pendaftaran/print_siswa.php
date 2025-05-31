<?php
// Aktifkan error reporting (untuk development; nonaktifkan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';     // Pastikan path ini sesuai
require_once __DIR__ . '/../vendor/autoload.php'; // mPDF via Composer

function safe($str) {
    return htmlspecialchars($str ?? '-');
}

// === Fungsi untuk mengirim PDF ke WhatsApp lewat Wablas ===
function kirimPDFKeWhatsApp($no_wa, $pdf_url, $token, $secret_key) {
    $curl = curl_init();
    $payload = [
        "data" => [
            [
                'phone'    => $no_wa,
                'document' => $pdf_url,
                // 'caption' => 'Bukti Pendaftaran', // jika perlu, bisa di‐uncomment
            ]
        ]
    ];
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: $token.$secret_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curl, CURLOPT_URL, "https://bdg.wablas.com/api/v2/send-document");
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($curl);
    $error  = curl_error($curl);
    curl_close($curl);

    return [
        'result' => $result,
        'error'  => $error
    ];
}

// --- CEK AKSES: hanya petugas dengan role 'pendaftaran' yang boleh akses ---
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    die('Akses tidak diizinkan.');
}

// --- Ambil ID Siswa dari parameter GET ---
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('ID siswa tidak valid.');
}

// --- Cek jika ada notifikasi dari reload sebelumnya ---
$notif_admin = '';
if (!empty($_GET['notif'])) {
    $notif_admin = htmlspecialchars($_GET['notif']);
}

// --- Ambil data siswa dari tabel 'siswa' ---
$stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    die('Data siswa tidak ditemukan.');
}

// --- Ambil nama petugas (jika ada) ---
$petugas = '-';
$username_petugas = $_SESSION['username'] ?? '';
if ($username_petugas) {
    $stmt_petugas = $conn->prepare("SELECT nama FROM petugas WHERE username = ?");
    $stmt_petugas->bind_param('s', $username_petugas);
    $stmt_petugas->execute();
    $data_petugas = $stmt_petugas->get_result()->fetch_assoc();
    $petugas = ($data_petugas && !empty($data_petugas['nama']))
        ? $data_petugas['nama']
        : $username_petugas;
    $stmt_petugas->close();
}

// --- Ambil status & notes dari calon_pendaftar (jika ada) ---
$status_pendaftaran = '-';
$keterangan_pendaftaran = '-';
if (!empty($row['calon_pendaftar_id'])) {
    $stmtStatus = $conn->prepare("
        SELECT status, notes 
        FROM calon_pendaftar 
        WHERE id = ?
    ");
    $stmtStatus->bind_param('i', $row['calon_pendaftar_id']);
    $stmtStatus->execute();
    $rsStatus = $stmtStatus->get_result()->fetch_assoc();
    $status_pendaftaran     = $rsStatus['status'] ?? '-';
    $keterangan_pendaftaran = $rsStatus['notes'] ?? '-';
    $stmtStatus->close();
}

// --- Fungsi format tanggal ke Bahasa Indonesia ---
function tanggal_id($tgl) {
    if (!$tgl || $tgl === '0000-00-00') return '-';
    $bulan = [
        'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
        'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
        'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $date  = date('d', strtotime($tgl));
    $month = $bulan[date('F', strtotime($tgl))];
    $year  = date('Y', strtotime($tgl));
    return "$date $month $year";
}

// --- Ambil status pembayaran (Uang Pangkal + SPP Juli) ---
$uang_pangkal_id = 1;
$spp_id          = 2;
$query_status = "
    SELECT
        CASE
            WHEN 
                (SELECT COUNT(*) 
                 FROM pembayaran_detail pd1 
                 JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                 WHERE p1.siswa_id = s.id 
                   AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                   AND pd1.status_pembayaran = 'Lunas'
                ) > 0
            AND
                (SELECT COUNT(*) 
                 FROM pembayaran_detail pd2 
                 JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                 WHERE p2.siswa_id = s.id 
                   AND pd2.jenis_pembayaran_id = $spp_id
                   AND pd2.bulan = 'Juli'
                   AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            THEN 'Lunas'
            WHEN 
                (
                    (SELECT COUNT(*) 
                     FROM pembayaran_detail pd1 
                     JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                     WHERE p1.siswa_id = s.id 
                       AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                       AND pd1.status_pembayaran = 'Lunas'
                    ) > 0
                    OR
                    (SELECT COUNT(*) 
                     FROM pembayaran_detail pd2 
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
$status_pembayaran = $stmtStatus->get_result()->fetch_assoc()['status_pembayaran'] ?? 'Belum Bayar';
$stmtStatus->close();

// --- Ambil tagihan awal (daftar jenis pembayaran + nominal) ---
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
$resTagihan = $stmtTagihan->get_result();
while ($t = $resTagihan->fetch_assoc()) {
    $tagihan[] = $t;
}
$stmtTagihan->close();

// --- Ambil riwayat pembayaran terakhir (max 6 record) jika status bukan 'Belum Bayar' ---
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
    while ($b = $resBayar->fetch_assoc()) {
        $pembayaran_terakhir[] = $b;
    }
    $stmtBayar->close();
}

// --- Fungsi untuk menampilkan badge status pembayaran ---
function getStatusBadge($status) {
    $s = strtolower($status);
    if ($s === 'lunas') {
        return '<span style="color:#1cc88a;font-weight:bold"><i class="fas fa-check-circle"></i> Lunas</span>';
    }
    if ($s === 'angsuran') {
        return '<span style="color:#f6c23e;font-weight:bold"><i class="fas fa-hourglass-half"></i> Angsuran</span>';
    }
    return '<span style="color:#e74a3b;font-weight:bold"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}

$note_class = '';
if ($status_pembayaran === 'Belum Bayar') {
    $note_class = 'belum-bayar';
} elseif ($status_pembayaran === 'Angsuran') {
    $note_class = 'angsuran';
} elseif ($status_pembayaran === 'Lunas') {
    $note_class = 'lunas';
}

// --- Ambil no_invoice (jika sudah generate di tabel siswa) ---
$no_invoice = $row['no_invoice'] ?? '';

// ===============================================
// PROSES GENERATE HTML DAN PDF MENGGUNAKAN mPDF
// ===============================================
ob_start(); // mulai output buffering
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']); ?>)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/print_bukti_pendaftaran.css" />
</head>
<body>
    <?php if (!empty($notif_admin)): ?>
        <div style="background:#e3f6d8;color:#157a10;padding:8px 12px;margin-bottom:8px;border-radius:6px;">
            <?= $notif_admin; ?>
        </div>
    <?php endif; ?>
    <button id="btnPrint" onclick="window.print()" style="display:inline-block;margin:10px 0 16px 0;padding:7px 18px;font-size:14px;background:#213b82;color:#fff;border:none;border-radius:6px;cursor:pointer;">
        <i class="fas fa-print"></i> Cetak / Simpan PDF
    </button>
    <div class="container">
        <!-- KOP SURAT -->
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
                <div class="no-reg-val"><b><i><?= safe($row['no_formulir']); ?></i></b></div>
            </div>
            <?php if (!empty($row['reviewed_by'])): ?>
                <span class="callcenter-badge">
                    <i class="fas fa-headset"></i>
                    <b>Call Center:</b> <?= safe($row['reviewed_by']); ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($status_pembayaran !== 'Belum Bayar' && !empty($no_invoice)): ?>
            <div class="no-reg-row">
                <div class="no-reg-label"><b>No. Formulir Pendaftaran</b></div>
                <div class="no-reg-sep">:</div>
                <div class="no-reg-val"><b><i><?= safe($no_invoice); ?></i></b></div>
            </div>
        <?php endif; ?>

        <table class="data-table">
            <caption>DATA CALON PESERTA DIDIK BARU</caption>
            <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']); ?></td></tr>
            <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']); ?></td></tr>
            <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']); ?></td></tr>
            <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']); ?></td></tr>
            <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']); ?></td></tr>
            <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']); ?></td></tr>
            <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']); ?></td></tr>
            <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']); ?></td></tr>
        </table>

        <div class="status-keterangan-wrap">
            <table class="status-keterangan-table">
                <tr>
                    <td class="status-ket-label">Status Pendaftaran</td>
                    <td class="status-ket-sep">:</td>
                    <td class="status-ket-value"><?= htmlspecialchars($status_pendaftaran); ?></td>
                </tr>
                <tr>
                    <td class="status-ket-label">Keterangan</td>
                    <td class="status-ket-sep">:</td>
                    <td class="status-ket-value"><?= !empty($keterangan_pendaftaran) ? htmlspecialchars($keterangan_pendaftaran) : '-'; ?></td>
                </tr>
            </table>
        </div>

        <table class="tagihan-table" style="margin-top:9px;">
            <tr>
                <th colspan="2" style="background:#e3eaf7;font-size:13.5px;text-align:center">
                    <i class="fas fa-coins"></i> Keterangan Pembayaran
                </th>
            </tr>
            <?php if (count($tagihan)): ?>
                <?php foreach ($tagihan as $tg): ?>
                    <tr>
                        <td><?= safe($tg['jenis']); ?></td>
                        <td style="text-align:right;font-weight:600">
                            Rp <?= number_format($tg['nominal'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
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
                <?php foreach ($pembayaran_terakhir as $b): ?>
                    <tr>
                        <td><?= safe($b['jenis']); ?></td>
                        <td style="text-align:right;">Rp <?= number_format($b['jumlah'], 0, ',', '.'); ?></td>
                        <td style="text-align:right;">
                            <?= ($b['cashback'] ?? 0) > 0
                                ? 'Rp ' . number_format($b['cashback'], 0, ',', '.')
                                : '-'; ?>
                        </td>
                        <td><?= safe($b['status_pembayaran']); ?></td>
                        <td><?= $b['bulan'] ? safe($b['bulan']) : '-'; ?></td>
                        <td class="tgl-lebar"><?= tanggal_id($b['tanggal_pembayaran']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="status-row">
            Status Pembayaran: <?= getStatusBadge($status_pembayaran); ?>
        </div>

        <div class="row-btm">
            <div class="info-contact">
                Informasi lebih lanjut hubungi:<br>
                Hotline SMA : <b>081511519271</b> (Bu Puji)
            </div>
        </div>

        <div class="note <?= $note_class; ?>">
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
                <div class="ttd-tanggal-kanan">Jakarta, <?= tanggal_id(date('Y-m-d')); ?></div>
                <div class="ttd-petugas-kanan"><?= safe($petugas); ?></div>
                <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// ===========================================
// PROSES EXPORT PDF DAN KIRIM KE WHATSAPP
// ===========================================
if (empty($_GET['notif'])) {
    // Ambil seluruh HTML yang telah ditangkap di buffer
    $html = ob_get_clean();

    // Nama dan lokasi file PDF
    $pdf_file = "bukti_pendaftaran_{$row['no_formulir']}.pdf";
    $folder_bukti = "/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti";
    $pdf_path   = "$folder_bukti/$pdf_file";
    $pdf_url    = "https://ppdbdk.pakarinformatika.web.id/pendaftaran/bukti/$pdf_file"; // pastikan HTTPS

    // Jika folder belum ada, buat dengan permission 0755
    if (!is_dir($folder_bukti)) {
        mkdir($folder_bukti, 0755, true);
    }

    // Generate PDF via mPDF
    $mpdf = new \Mpdf\Mpdf([
        'format'        => 'A4',
        'margin_left'   => 0,
        'margin_right'  => 0,
        'margin_top'    => 0,
        'margin_bottom' => 0
    ]);
    $mpdf->SetCompression(true);
    $mpdf->WriteHTML($html);
    $mpdf->Output($pdf_path, \Mpdf\Output\Destination::FILE);

    // Pastikan file PDF bisa dibaca publik
    chmod($pdf_path, 0644);
    clearstatcache();

    if (!file_exists($pdf_path) || filesize($pdf_path) < 5000) {
        // Jika file PDF gagal dibuat atau ukurannya terlalu kecil (< 5KB)
        $notif_admin = "PDF GAGAL atau KOSONG, tidak akan dikirim ke WA! Path: $pdf_path | Size: " .
            (file_exists($pdf_path) ? filesize($pdf_path) : 0) . " bytes";
    } else {
        // Persiapan kirim ke WA
        $token      = "iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2";
        $secret_key = "kkt9za3q";
        $no_wa_ortu = $row['no_hp_ortu'];

        // Pastikan URL diawali https://
        if (stripos($pdf_url, 'http://') === 0) {
            $pdf_url = 'https://' . substr($pdf_url, 7);
        }

        // Cek apakah file PDF bisa diakses publik
        $head = @get_headers($pdf_url);
        if (!$head || strpos($head[0], '200') === false) {
            $notif_admin = "PDF TIDAK BISA DIAKSES PUBLIK: $pdf_url. Cek permission/folder/domain!";
        } else {
            // Kirim request ke Wablas
            $res = kirimPDFKeWhatsApp($no_wa_ortu, $pdf_url, $token, $secret_key);

            if (!empty($res['error'])) {
                // Curl error
                $notif_admin = "Gagal koneksi ke Wablas: " . htmlspecialchars($res['error']);
            } else {
                $decoded = json_decode($res['result'], true);
                if (isset($decoded['status']) && $decoded['status'] === true) {
                    // Wablas merespons dengan status pending atau sukses
                    // Pesan 'pending' artinya Wablas akan memproses dokumen tersebut
                    $pesanStatus = $decoded['message'] ?? 'Pesan dikirim, menunggu proses selanjutnya.';
                    $notif_admin = "Wablas: " . htmlspecialchars($pesanStatus);
                } else {
                    // Bila status=false => Wablas menolak data
                    $notif_admin = "Gagal kirim ke Wablas: " . htmlspecialchars($res['result']);
                }
            }
        }
    }

    // Setelah mem‐proses kirim, reload halaman dengan parameter notif supaya banner tampil
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=$id&notif=" . urlencode($notif_admin));
    exit;
}
?>
