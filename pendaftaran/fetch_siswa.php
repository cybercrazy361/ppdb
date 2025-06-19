<?php
// pendaftaran/fetch_siswa.php
session_start();
header('Content-Type: application/json');

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
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

// --- LOGIKA STATUS PEMBAYARAN BENAR ---
$mainQuery = "
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
          WHEN (
            (
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
                ) = 0
            )
            OR
            (
                (SELECT COUNT(*) FROM pembayaran_detail pd2
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = s.id
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
                AND
                (SELECT COUNT(*) FROM pembayaran_detail pd1
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = s.id
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                      AND pd1.status_pembayaran = 'Lunas'
                ) = 0
            )
          )
          THEN 'Angsuran'
          ELSE 'Belum Bayar'
        END AS status_pembayaran,
        COALESCE(LOWER(cp.status), '') AS status_pendaftaran
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ?
";

// Handle status
if ($status === 'total') {
    // Semua siswa (status pembayaran apapun)
    $query = $mainQuery;
    $paramTypes = 's';
    $params = [$unit];
} elseif ($status === 'lunas') {
    $query = "SELECT * FROM ($mainQuery) AS x WHERE status_pembayaran = 'Lunas'";
    $paramTypes = 's';
    $params = [$unit];
} elseif ($status === 'angsuran') {
    $query = "SELECT * FROM ($mainQuery) AS x WHERE status_pembayaran = 'Angsuran'";
    $paramTypes = 's';
    $params = [$unit];
} elseif ($status === 'belum') {
    $query = "SELECT * FROM ($mainQuery) AS x WHERE status_pembayaran = 'Belum Bayar'";
    $paramTypes = 's';
    $params = [$unit];
} elseif ($status === 'ppdb') {
    // Hanya siswa dengan status_pendaftaran = 'ppdb bersama'
    $query = "SELECT * FROM ($mainQuery) AS x WHERE status_pendaftaran = 'ppdb bersama'";
    $paramTypes = 's';
    $params = [$unit];
} else {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare($query);
$stmt->bind_param($paramTypes, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$siswaList = [];
while ($row = $result->fetch_assoc()) {
    $display_status =
        $row['status_pendaftaran'] === 'ppdb bersama'
            ? 'PPDB Bersama'
            : $row['status_pembayaran'];
    $siswaList[] = [
        'nama' => htmlspecialchars($row['nama']),
        'status_pembayaran' => htmlspecialchars($display_status),
    ];
}

$stmt->close();
$conn->close();

echo json_encode($siswaList);
?>
