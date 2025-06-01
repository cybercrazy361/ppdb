<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';

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

$filename = 'bukti_pendaftaran_' . safe($row['no_formulir']) . '.pdf';
$filepath = '/home/pakarinformatika.web.id/ppdbdk/pendaftaran/bukti/' . $filename;
$pdf_url = "https://ppdbdk.pakarinformatika.web.id/pendaftaran/bukti/$filename";

// --- Cek file exist ---
if (!file_exists($filepath)) {
    die("<div style='color:red;font-size:16px;padding:16px;'>File PDF belum digenerate.<br>
    Silakan klik <a href='generate_bukti_pendaftaran.php?id=$id' style='color:blue;'>Generate & Simpan PDF</a> dulu.</div>");
}

$no_wa = $row['no_hp_ortu']; // format: 628xxx
if (empty($no_wa)) {
    die("<div style='color:red;font-size:16px;padding:16px;'>Nomor WhatsApp orang tua tidak tersedia di database!</div>");
}

$token = "iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2";
$secret_key = "PAtwrvlV";

// --- Kirim ke Wablas ---
$payload = [
    "data" => [
        [
            "phone" => $no_wa,
            "document" => $pdf_url,
            "caption" => "Bukti Pendaftaran Siswa Baru (" . safe($row['nama']) . ")"
        ]
    ]
];
$curl = curl_init();
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
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "<div style='color:red;font-size:16px;padding:16px;'>Gagal mengirim ke WhatsApp: $err</div>";
} else {
    echo "<div style='color:green;font-size:16px;padding:16px;'>PDF berhasil dikirim ke WhatsApp orang tua!<br>
    <a href='$pdf_url' target='_blank'>Download PDF</a></div>
    <pre style='background:#eee;padding:12px;border-radius:7px;'>$result</pre>";
}
?>
