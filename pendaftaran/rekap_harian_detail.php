<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    exit(json_encode(['error' => 'Akses ditolak']));
}

$unit = $_SESSION['unit'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT s.nama, cp.status AS status_ppdb, 
    CASE
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd1 
             JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
             WHERE p1.siswa_id = s.id 
               AND pd1.jenis_pembayaran_id = 1
               AND pd1.status_pembayaran = 'Lunas') > 0
        AND 
            (SELECT COUNT(*) FROM pembayaran_detail pd2 
             JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
             WHERE p2.siswa_id = s.id 
               AND pd2.jenis_pembayaran_id = 2
               AND pd2.bulan = 'Juli'
               AND pd2.status_pembayaran = 'Lunas') > 0
        THEN 'Lunas'
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd 
             JOIN pembayaran p ON pd.pembayaran_id = p.id
             WHERE p.siswa_id = s.id AND 
                   ((pd.jenis_pembayaran_id = 1) OR 
                   (pd.jenis_pembayaran_id = 2 AND pd.bulan = 'Juli' AND pd.status_pembayaran = 'Lunas'))
            ) > 0
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
    END AS status_pembayaran
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ? AND DATE(s.tanggal_pendaftaran) = ?
");
$stmt->bind_param('ss', $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'nama' => $row['nama'],
        'status_ppdb' => $row['status_ppdb'] ?? '-',
        'status_pembayaran' => $row['status_pembayaran'] ?? 'Belum Bayar',
    ];
}

echo json_encode($data);
