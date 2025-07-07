<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    exit(json_encode(['error' => 'Akses ditolak']));
}

$unit = $_SESSION['unit'] ?? '';
$uang_pangkal_id = 1;
$spp_id = 2;
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Hanya ambil siswa yang pembayaran pertamanya pada tanggal itu
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.calon_pendaftar_id
    FROM siswa s
    JOIN pembayaran p ON p.siswa_id = s.id
    WHERE s.unit = ?
      AND DATE(p.tanggal_pembayaran) = ?
      AND (
        SELECT MIN(DATE(p2.tanggal_pembayaran))
        FROM pembayaran p2
        WHERE p2.siswa_id = s.id
      ) = ?
");
$stmt->bind_param('sss', $unit, $tanggal, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$lunas = $angsuran = $belum = $total = $ppdb = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $calon_pendaftar_id = intval($row['calon_pendaftar_id'] ?? 0);
    $status_ppdb = '';

    if ($calon_pendaftar_id > 0) {
        $stmt2 = $conn->prepare(
            'SELECT status FROM calon_pendaftar WHERE id = ? LIMIT 1'
        );
        $stmt2->bind_param('i', $calon_pendaftar_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $status_ppdb = strtolower(trim($res2->fetch_assoc()['status'] ?? ''));
        $stmt2->close();
    }

    if ($status_ppdb === 'ppdb bersama') {
        $ppdb++;
    } else {
        // Cek status pembayaran uang pangkal
        $cek = "
        SELECT
        CASE
            WHEN 
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                 JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                 WHERE p1.siswa_id = $id 
                   AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                   AND pd1.status_pembayaran = 'Lunas') > 0
            THEN 'Lunas'
            WHEN 
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                 JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                 WHERE p1.siswa_id = $id 
                   AND pd1.jenis_pembayaran_id = $uang_pangkal_id) > 0
            THEN 'Angsuran'
            ELSE 'Belum Bayar'
        END AS status_pembayaran
        ";
        $q = $conn->query($cek);
        $stat = $q->fetch_assoc()['status_pembayaran'] ?? '';

        if ($stat === 'Lunas') {
            $lunas++;
        } elseif ($stat === 'Angsuran') {
            $angsuran++;
        } else {
            $belum++;
        }
    }

    $total++;
}

$stmt->close();
$conn->close();

echo json_encode([
    'total' => $total,
    'lunas' => $lunas,
    'angsuran' => $angsuran,
    'belum' => $belum,
    'ppdb' => $ppdb,
]);
