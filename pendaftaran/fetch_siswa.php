<?php
session_start();
header('Content-Type: application/json');

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

$query = "
SELECT 
    s.id,
    s.nama,
    COALESCE(LOWER(cp.status),'') AS status_pendaftaran,
    -- UANG PANGKAL
    (
        SELECT COUNT(*) FROM pembayaran_detail pd1
        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
        WHERE p1.siswa_id = s.id
        AND pd1.jenis_pembayaran_id = $uang_pangkal_id
    ) AS ada_uang_pangkal,
    (
        SELECT COUNT(*) FROM pembayaran_detail pd1
        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
        WHERE p1.siswa_id = s.id
        AND pd1.jenis_pembayaran_id = $uang_pangkal_id
        AND pd1.status_pembayaran = 'Lunas'
    ) AS uang_pangkal_lunas,
    -- SPP JULI
    (
        SELECT COUNT(*) FROM pembayaran_detail pd2
        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
        WHERE p2.siswa_id = s.id
        AND pd2.jenis_pembayaran_id = $spp_id
        AND pd2.bulan = 'Juli'
    ) AS ada_spp_juli,
    (
        SELECT COUNT(*) FROM pembayaran_detail pd2
        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
        WHERE p2.siswa_id = s.id
        AND pd2.jenis_pembayaran_id = $spp_id
        AND pd2.bulan = 'Juli'
        AND pd2.status_pembayaran = 'Lunas'
    ) AS spp_juli_lunas
FROM siswa s
LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
WHERE s.unit = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $unit);
$stmt->execute();
$res = $stmt->get_result();

$siswaList = [];
while ($r = $res->fetch_assoc()) {
    $status_final = 'Belum Bayar';

    // 1. PPDB Bersama
    if ($r['status_pendaftaran'] === 'ppdb bersama') {
        $status_final = 'PPDB Bersama';
    }
    // 2. Lunas - Uang Pangkal saja
    elseif ($r['uang_pangkal_lunas'] > 0) {
        $status_final = 'Lunas';
    }
    // 3. Angsuran
    elseif (
        ($r['ada_uang_pangkal'] > 0 || $r['ada_spp_juli'] > 0) &&
        $r['status_pendaftaran'] !== 'ppdb bersama'
    ) {
        $status_final = 'Angsuran';
    }
    // 4. Belum Bayar (default)

    // --- FILTER sesuai request ---
    if (
        $status === 'total' ||
        ($status === 'lunas' && $status_final === 'Lunas') ||
        ($status === 'angsuran' && $status_final === 'Angsuran') ||
        ($status === 'belum' && $status_final === 'Belum Bayar') ||
        ($status === 'ppdb' && $status_final === 'PPDB Bersama')
    ) {
        $siswaList[] = [
            'nama' => $r['nama'],
            'status_pembayaran' => $status_final,
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode($siswaList);
?>
