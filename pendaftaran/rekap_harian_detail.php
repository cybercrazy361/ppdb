<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    exit(json_encode(['error' => 'Akses ditolak']));
}

$unit = $_SESSION['unit'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Ambil data siswa yang membayar di tanggal tersebut
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.nama, cp.status AS status_ppdb
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ? AND DATE(p.tanggal_pembayaran) = ?
");
$stmt->bind_param('ss', $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $siswa_id = (int) $row['id'];

    // Ambil total uang pangkal & spp juli yang lunas
    $cek = "
        SELECT 
            SUM(CASE 
                WHEN pd.jenis_pembayaran_id = 1 AND pd.status_pembayaran = 'Lunas' THEN pd.jumlah 
                ELSE 0 
            END) AS total_uang_pangkal,
            SUM(CASE 
                WHEN pd.jenis_pembayaran_id = 2 AND pd.bulan = 'Juli' AND pd.status_pembayaran = 'Lunas' THEN pd.jumlah 
                ELSE 0 
            END) AS total_spp_juli,
            COUNT(CASE 
                WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE NULL 
            END) AS jumlah_bayar_lunas,
            COUNT(*) AS jumlah_semua
        FROM pembayaran_detail pd
        JOIN pembayaran p ON pd.pembayaran_id = p.id
        WHERE p.siswa_id = $siswa_id
    ";
    $res = $conn->query($cek)->fetch_assoc();

    $uang_pangkal = (int) $res['total_uang_pangkal'];
    $spp_juli = (int) $res['total_spp_juli'];
    $jumlah_bayar_lunas = (int) $res['jumlah_bayar_lunas'];
    $jumlah_semua = (int) $res['jumlah_semua'];

    // Status pembayarannya
    if ($uang_pangkal > 0 && $spp_juli > 0) {
        $status = 'Lunas';
    } elseif ($jumlah_bayar_lunas > 0) {
        $status = 'Angsuran';
    } else {
        $status = 'Belum Bayar';
    }

    $data[] = [
        'nama' => $row['nama'],
        'status_ppdb' => $row['status_ppdb'] ?? '-',
        'status_pembayaran' => $status,
    ];
}

echo json_encode($data);
