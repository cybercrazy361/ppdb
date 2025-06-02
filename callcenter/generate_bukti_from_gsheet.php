<?php
file_put_contents(__DIR__.'/bukti/debug.txt', date('c').' '.json_encode($_POST).PHP_EOL, FILE_APPEND);

date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';
require_once __DIR__ . '/../vendor/autoload.php'; // mPDF

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) die(json_encode(['success'=>false, 'message'=>'ID tidak valid.']));

// Ambil data calon_pendaftar
$stmt = $conn->prepare("SELECT * FROM calon_pendaftar WHERE id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) die(json_encode(['success'=>false, 'message'=>'Data tidak ditemukan.']));

file_put_contents(__DIR__.'/bukti/debug.txt', "PROSES PDF UNTUK ID: $id\n", FILE_APPEND);

function tanggal_id($tgl) {
    if (!$tgl || $tgl == '0000-00-00') return '-';
    $bulan = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
        '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
    ];
    $p = explode('-', $tgl);
    return isset($p[2]) ? $p[2] . ' ' . $bulan[$p[1]] . ' ' . $p[0] : $tgl;
}
function safe($str) {
    return htmlspecialchars($str ?? '-');
}

// Lokasi & nama file
$dir_pdf = __DIR__ . '/bukti/';
if (!is_dir($dir_pdf)) mkdir($dir_pdf, 0777, true);
$filename = "bukti_contoh_CALON_{$id}_" . date('YmdHis') . ".pdf";
$save_path = $dir_pdf . $filename;
$pdf_url = "https://ppdbdk.pakarinformatika.web.id/callcenter/bukti/" . $filename;

// ==== Generate PDF: TAMPILAN PERSIS SESUAI LAYOUT ====
$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_top'=>5, 'margin_left'=>7, 'margin_right'=>7, 'margin_bottom'=>8, 'default_font'=>'Arial']);
ob_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<title>Bukti Pendaftaran Siswa Baru</title>
<style>
  body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #202b38;
    margin: 0;
    padding: 0;
  }
  .container {
    background: #fff;
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    padding: 5mm 10mm 10mm 10mm;
    box-sizing: border-box;
  }
  table.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
  }
  table.data-table th,
  table.data-table td {
    border: 1px solid #dbe4f3;
    padding: 6px 10px;
    text-align: left;
  }
  table.data-table th {
    background: #e6edfa;
    color: #163984;
    font-weight: 600;
    width: 34%;
  }
</style>
</head>
<body>
  <div class="container">
    <!-- KOP SURAT -->
    <table width="100%" style="margin-bottom: 6px;">
      <tr>
        <!-- LOGO KIRI -->
        <td style="width:110px; text-align:left; vertical-align:middle;">
          <img src="https://ppdbdk.pakarinformatika.web.id/assets/images/logo_trans.png"
               alt="Logo"
               style="height:80px; object-fit:contain;">
        </td>
        <!-- INFO KOP CENTER -->
        <td style="text-align:center; vertical-align:middle;">
          <div style="font-size: 18px; font-weight: bold; letter-spacing: 2px; color: #163984;">
              YAYASAN PENDIDIKAN DHARMA KARYA
          </div>
          <div style="font-size: 17px; font-weight: bold; letter-spacing: 1px; color: #163984;">
              SMA/SMK DHARMA KARYA
          </div>
          <div style="font-size: 14px; font-weight: bold; color: #163984; margin-bottom: 2px;">
              <b>Terakreditasi “A”</b>
          </div>
          <div style="font-size: 12px; color: #163984;">
              Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan
          </div>
          <div style="font-size: 12px; color: #163984;">
              Telp. 021-7398578 / 7250224
          </div>
        </td>
        <!-- DUMMY -->
        <td style="width:110px;"></td>
      </tr>
    </table>
    <div style="border-bottom: 4px solid #163984; margin: 0 10mm 18px 10mm; width: calc(100% - 20mm);"></div>

    <div class="header-content" style="text-align:center;margin-bottom:18px;">
      <div style="font-size: 17px; font-weight: 700; color: #163984; margin: 0 0 5px 0;"><b>BUKTI PENDAFTARAN CALON MURID BARU</b></div>
      <div style="font-size:13px;font-weight:600;color:#163984;margin:0;">SISTEM PENERIMAAN MURID BARU (SPMB)</div>
      <div style="font-size:13px;font-weight:600;color:#163984;margin:0;">SMA/SMK DHARMA KARYA JAKARTA</div>
      <div style="font-size:12px;"><b>TAHUN AJARAN 2025/2026</b></div>
    </div>

    <div style="font-weight:bold; font-size:18px; color:#1a53c7; text-align:center; margin-bottom:5px; text-transform:uppercase;">
      DATA CALON PESERTA DIDIK BARU
    </div>
    <table class="data-table">
      <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_daftar']) ?></td></tr>
      <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['pilihan']) ?></td></tr>
    </table>

    <div style="margin-top:12px; font-size:12.5px;">
      <b>Catatan:</b><br>
      1. Simpan file ini sebagai bukti telah mengisi form pendaftaran.<br>
      2. Panitia akan menghubungi Anda untuk proses selanjutnya.
    </div>

    <div style="width:100%;margin-top:28px;text-align:right;font-size:12px;">
      Jakarta, <?= tanggal_id(date('Y-m-d')) ?><br><br>
      (Panitia SPMB)
    </div>
  </div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf->WriteHTML($html);
$mpdf->Output($save_path, \Mpdf\Output\Destination::FILE);

// ==== Kirim ke WhatsApp siswa & ortu (Wablas) ====
$token = "iMfsMR63WRfAMjEuVCEu2CJKpSZYVrQoW6TKlShzENJN2YNy2cZAwL2";
$secret_key = "PAtwrvlV";
$wa_numbers = [];
if (preg_match('/^628[0-9]{7,13}$/', $row['no_hp'])) $wa_numbers[] = $row['no_hp'];
if (preg_match('/^628[0-9]{7,13}$/', $row['no_hp_ortu'])) $wa_numbers[] = $row['no_hp_ortu'];

$payload = ['data' => []];
foreach ($wa_numbers as $no_wa) {
    $payload['data'][] = [
        "phone" => $no_wa,
        "document" => $pdf_url,
        "caption" => "Bukti Pendaftaran Siswa Baru atas nama " . $row['nama']
    ];
}

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
file_put_contents(__DIR__.'/bukti/debug.txt', "WA RESPONSE: ".$result.PHP_EOL, FILE_APPEND);

if ($err) {
    echo json_encode(['success'=>false, 'message'=>"PDF ok, tapi gagal WA: $err", 'pdf'=>$pdf_url]);
} else {
    echo json_encode(['success'=>true, 'message'=>'PDF & WA sukses!', 'pdf'=>$pdf_url]);
}
?>
