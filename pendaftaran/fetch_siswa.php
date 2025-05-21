<?php
session_start();
header('Content-Type: application/json');

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    echo json_encode([]);
    exit();
}

include '../database_connection.php';

$unit = isset($_GET['unit']) ? trim($_GET['unit']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$uang_pangkal_id = 1;
$spp_id = 2;

if ($unit === '' || $status === '') {
    echo json_encode([]);
    exit();
}

$query = "
    SELECT 
        s.id,
        s.nama,
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
    WHERE s.unit = ?
";

if ($status === 'lunas') {
    $query = "SELECT * FROM ($query) x WHERE status_pembayaran = 'Lunas'";
} elseif ($status === 'angsuran') {
    $query = "SELECT * FROM ($query) x WHERE status_pembayaran = 'Angsuran'";
} elseif ($status === 'belum') {
    $query = "SELECT * FROM ($query) x WHERE status_pembayaran = 'Belum Bayar'";
} else {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();

$siswaList = [];
while ($row = $result->fetch_assoc()) {
    $siswaList[] = [
        'nama' => htmlspecialchars($row['nama']),
        'status_pembayaran' => htmlspecialchars($row['status_pembayaran'])
    ];
}

$stmt->close();
$conn->close();

echo json_encode($siswaList);
