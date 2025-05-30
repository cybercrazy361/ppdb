<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';

require_once __DIR__ . '/../vendor/autoload.php';

// === Wablas API config ===
define('WABLAS_API_KEY', 'iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2'); 
define('WABLAS_API_URL', 'https://console.wablas.com/api/v2/send-document');

// Helper: Kirim PDF ke WhatsApp
if (!function_exists('kirimPDFKeWhatsApp')) {
    function kirimPDFKeWhatsApp($noHP, $pdfFilePath, $pesan = '') {
        $curl = curl_init();
        $token = WABLAS_API_KEY;
        // Format nomor: 08xxx -> 628xxx
        $phone = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $noHP));
        $data = [
            "phone" => $phone,
            "caption" => $pesan ?: "Bukti Pendaftaran Siswa Baru",
            "document" => new CURLFile($pdfFilePath)
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Authorization: $token"]);
        curl_setopt($curl, CURLOPT_URL, WABLAS_API_URL);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}

// Sanitasi output
function safe($str) { return htmlspecialchars($str ?? '-'); }

// Validasi sesi user
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    die('Akses tidak diizinkan.');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die('ID siswa tidak valid.');

$stmt = $conn->prepare("SELECT * FROM siswa WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die('Data siswa tidak ditemukan.');

// Ambil data petugas
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

// Ambil status & notes dari calon_pendaftar (jika ada)
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

// Format tanggal lokal Indonesia
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

// Status pembayaran (ambil dari DB, sama seperti kode kamu sebelumnya)
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

function getStatusBadge($status) {
    $status = strtolower($status);
    if ($status === 'lunas') return '<span style="color:#1cc88a;font-weight:bold"><i class="fas fa-check-circle"></i> Lunas</span>';
    if ($status === 'angsuran') return '<span style="color:#f6c23e;font-weight:bold"><i class="fas fa-hourglass-half"></i> Angsuran</span>';
    return '<span style="color:#e74a3b;font-weight:bold"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}

$note_class = '';
if ($status_pembayaran === 'Belum Bayar') $note_class = 'belum-bayar';
elseif ($status_pembayaran === 'Angsuran') $note_class = 'angsuran';
elseif ($status_pembayaran === 'Lunas') $note_class = 'lunas';

// No Invoice, pastikan kolom ini ada di tabel siswa!
$no_invoice = $row['no_invoice'] ?? '';

// ========================
// === Proses PDF & WA ====
// ========================
if (isset($_GET['send_wa']) && $_GET['send_wa'] == '1') {
    // Render ulang HTML ke variabel menggunakan view khusus
    ob_start();
    include __DIR__ . '/print_siswa_view.php'; // hanya view (tidak ada logic/function duplikat)
    $html = ob_get_clean();

    $mpdf = new \Mpdf\Mpdf(['format'=>'A4']);
    $mpdf->WriteHTML($html);
    $pdfFile = sys_get_temp_dir() . '/bukti_daftar_' . $row['id'] . '_' . time() . '.pdf';
    $mpdf->Output($pdfFile, \Mpdf\Output\Destination::FILE);

    $pesan = "Halo, berikut kami lampirkan bukti pendaftaran atas nama {$row['nama']} di SMA Dharma Karya.";
    $result = kirimPDFKeWhatsApp($row['no_hp_ortu'], $pdfFile, $pesan);

if (file_exists($pdfFile)) unlink($pdfFile);

// Cek isi respon dari Wablas
$resArr = json_decode($result, true);
if (isset($resArr['status']) && $resArr['status']) {
    echo "<script>alert('Bukti pendaftaran berhasil dikirim ke WhatsApp orang tua!');window.location='halaman_berikutnya.php';</script>";
    exit;
} else {
    echo "<pre>GAGAL KIRIM WA:\n";
    print_r($resArr);
    echo "\nRAW RESPONSE:\n$result</pre>";
    exit;
}


    if (file_exists($pdfFile)) unlink($pdfFile);

    echo "<script>alert('Bukti pendaftaran berhasil dikirim ke WhatsApp orang tua!');window.location='halaman_berikutnya.php';</script>";
    exit;
}

// Jika bukan proses PDF/WA, render halaman seperti biasa:
include __DIR__ . '/print_siswa_view.php';
