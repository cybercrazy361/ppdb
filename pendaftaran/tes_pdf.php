<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Atur path ke vendor/autoload sesuai lokasi kamu

ob_start();
?>
<h1>TES PDF SEDERHANA</h1>
<p>Kalau PDF ini tampil, berarti proses kamu sudah benar.</p>
<?php
$htmlPDF = ob_get_clean();

file_put_contents(__DIR__ . '/debug.html', $htmlPDF); // Cek hasil HTML
$mpdf = new \Mpdf\Mpdf();
$mpdf->WriteHTML($htmlPDF);
$mpdf->Output(__DIR__ . "/tes.pdf", \Mpdf\Output\Destination::FILE);

echo "DONE. Cek file <b>tes.pdf</b> dan <b>debug.html</b> di folder ini.";
?>
