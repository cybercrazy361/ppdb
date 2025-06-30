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

$stmt = $conn->prepare(
    'SELECT id, calon_pendaftar_id FROM siswa WHERE unit=? AND DATE(tanggal_pendaftaran)=?'
);
$stmt->bind_param('ss', $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$lunas = $angsuran = $belum = $total = $ppdb = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $calon_pendaftar_id = $row['calon_pendaftar_id'];
    $status_ppdb = '';

    if ($calon_pendaftar_id) {
        // Pakai prepared statement untuk cek status
        $stmt2 = $conn->prepare(
            'SELECT status FROM calon_pendaftar WHERE id = ? LIMIT 1'
        );
        $stmt2->bind_param('i', $calon_pendaftar_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $r = $res2->fetch_assoc();
        $status_ppdb = strtolower(trim($r['status'] ?? ''));
        $stmt2->close();
    }

    if ($status_ppdb === 'ppdb bersama') {
        $ppdb++;
    } else {
        // Cek pembayaran status
        // Cek pembayaran status
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
        THEN 'Lunas'
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd1 
                JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                WHERE p1.siswa_id = $id 
                  AND pd1.jenis_pembayaran_id = $uang_pangkal_id
            ) > 0
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
