<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$unit = $_SESSION['unit'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$stmt = $conn->prepare("
    SELECT s.nama_lengkap, cp.status AS status_ppdb, s.id
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ? AND s.tanggal_pendaftaran = ?
");
$stmt->bind_param('ss', $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $is_ppdb = strtolower(trim($row['status_ppdb'] ?? '')) === 'ppdb bersama';

    if ($is_ppdb) {
        $status = 'PPDB Bersama';
    } else {
        // Cek status pembayaran
        $cek = "
            SELECT
            CASE
                WHEN 
                    (SELECT COUNT(*) FROM pembayaran_detail pd1 
                        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                        WHERE p1.siswa_id = $id 
                          AND pd1.jenis_pembayaran_id = 1
                          AND pd1.status_pembayaran = 'Lunas') > 0
                AND 
                    (SELECT COUNT(*) FROM pembayaran_detail pd2 
                        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                        WHERE p2.siswa_id = $id 
                          AND pd2.jenis_pembayaran_id = 2
                          AND pd2.bulan = 'Juli'
                          AND pd2.status_pembayaran = 'Lunas') > 0
                THEN 'Lunas'
                WHEN (
                    (SELECT COUNT(*) FROM pembayaran_detail pd1 
                        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                        WHERE p1.siswa_id = $id AND pd1.jenis_pembayaran_id IN (1,2)
                    ) > 0
                )
                THEN 'Angsuran'
                ELSE 'Belum Bayar'
            END AS status_pembayaran
        ";
        $stat =
            $conn->query($cek)->fetch_assoc()['status_pembayaran'] ??
            'Belum Bayar';
        $status = $stat;
    }

    $data[] = [
        'nama' => $row['nama_lengkap'],
        'status' => $status,
    ];
}
$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['siswa' => $data]);
