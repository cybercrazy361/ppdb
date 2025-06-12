<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    exit(json_encode(['error'=>'Akses ditolak']));
}
$unit = $_SESSION['unit'] ?? '';
$uang_pangkal_id = 1;
$spp_id = 2;
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$stmt = $conn->prepare("SELECT id FROM siswa WHERE unit=? AND DATE(tanggal_pendaftaran)=?");
$stmt->bind_param("ss", $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();
$lunas = $angsuran = $belum = $total = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $cek = "
    SELECT
    CASE
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd1 
                JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                WHERE p1.siswa_id = $id 
                  AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                  AND pd1.status_pembayaran = 'Lunas'
            ) > 0
        AND
            (SELECT COUNT(*) FROM pembayaran_detail pd2 
                JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                WHERE p2.siswa_id = $id 
                  AND pd2.jenis_pembayaran_id = $spp_id
                  AND pd2.bulan = 'Juli'
                  AND pd2.status_pembayaran = 'Lunas'
            ) > 0
        THEN 'Lunas'
        WHEN 
            (
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = $id 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                ) > 0
                OR
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = $id 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            )
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
    END AS status_pembayaran
    ";
    $q = $conn->query($cek);
    $stat = $q->fetch_assoc()['status_pembayaran'] ?? '';
    if ($stat === 'Lunas') $lunas++;
    elseif ($stat === 'Angsuran') $angsuran++;
    else $belum++;
    $total++;
}

echo json_encode([
  'total'    => $total,
  'lunas'    => $lunas,
  'angsuran' => $angsuran,
  'belum'    => $belum
]);
