<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    http_response_code(403);
    exit(json_encode(['error' => 'Akses ditolak']));
}

$unit = $_SESSION['unit'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$data = [];

// 1. Siswa yang pembayaran pertamanya pada tanggal ini
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.nama, cp.status AS status_ppdb
    FROM siswa s
    JOIN pembayaran p ON p.siswa_id = s.id
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
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

while ($row = $result->fetch_assoc()) {
    $siswa_id = (int) $row['id'];
    $status_pembayaran = 'Belum Bayar';

    // Ambil tagihan uang pangkal
    $tagihan_up = 0;
    $stmt1 = $conn->prepare(
        'SELECT nominal FROM siswa_tagihan_awal WHERE siswa_id=? AND jenis_pembayaran_id=1'
    );
    $stmt1->bind_param('i', $siswa_id);
    $stmt1->execute();
    $rs1 = $stmt1->get_result();
    if ($d = $rs1->fetch_assoc()) {
        $tagihan_up = (int) $d['nominal'];
    }
    $stmt1->close();

    // Total pembayaran uang pangkal dikurangi cashback
    $total_bayar_up = 0;
    $stmt2 = $conn->prepare("
        SELECT SUM(pd.jumlah - IFNULL(pd.cashback,0)) AS total_bayar
        FROM pembayaran_detail pd
        JOIN pembayaran p ON pd.pembayaran_id = p.id
        WHERE p.siswa_id = ? AND pd.jenis_pembayaran_id = 1
    ");
    $stmt2->bind_param('i', $siswa_id);
    $stmt2->execute();
    $rs2 = $stmt2->get_result();
    if ($d2 = $rs2->fetch_assoc()) {
        $total_bayar_up = (int) $d2['total_bayar'];
    }
    $stmt2->close();

    // Hitung status berdasarkan total bayar vs tagihan
    if ($tagihan_up > 0 && $total_bayar_up >= $tagihan_up) {
        $status_pembayaran = 'Lunas';
    } elseif ($total_bayar_up > 0) {
        $status_pembayaran = 'Angsuran';
    }

    // Sinkronkan status dengan transaksi terakhir
    $stmt3 = $conn->prepare("
        SELECT pd.status_pembayaran 
        FROM pembayaran_detail pd
        JOIN pembayaran p ON pd.pembayaran_id = p.id
        WHERE p.siswa_id = ? AND pd.jenis_pembayaran_id = 1
        ORDER BY p.tanggal_pembayaran DESC, pd.id DESC
        LIMIT 1
    ");
    $stmt3->bind_param('i', $siswa_id);
    $stmt3->execute();
    $rs3 = $stmt3->get_result();
    if ($d3 = $rs3->fetch_assoc()) {
        $stat = strtolower($d3['status_pembayaran'] ?? '');
        if (strpos($stat, 'lunas') !== false) {
            $status_pembayaran = 'Lunas';
        } elseif (strpos($stat, 'angsuran') !== false) {
            $status_pembayaran = 'Angsuran';
        }
    }
    $stmt3->close();

    $data[] = [
        'nama' => $row['nama'],
        'status_ppdb' => $row['status_ppdb'] ?? '-',
        'status_pembayaran' => $status_pembayaran,
    ];
}
$stmt->close();

// 2. Tambah siswa yang belum pernah bayar sama sekali (hingga tanggal itu), status_pembayaran = "Belum Bayar"
$stmt_belum = $conn->prepare("
    SELECT s.id, s.nama, cp.status AS status_ppdb
    FROM siswa s
    LEFT JOIN (
        SELECT siswa_id, MIN(DATE(tanggal_pembayaran)) AS tanggal_pertama
        FROM pembayaran
        GROUP BY siswa_id
    ) x ON x.siswa_id = s.id
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ?
      AND (x.tanggal_pertama IS NULL OR x.tanggal_pertama > ?)
      AND (cp.status IS NULL OR LOWER(cp.status) != 'ppdb bersama')
");
$stmt_belum->bind_param('ss', $unit, $tanggal);
$stmt_belum->execute();
$res_belum = $stmt_belum->get_result();

while ($row = $res_belum->fetch_assoc()) {
    $data[] = [
        'nama' => $row['nama'],
        'status_ppdb' => $row['status_ppdb'] ?? '-',
        'status_pembayaran' => 'Belum Bayar',
    ];
}
$stmt_belum->close();

header('Content-Type: application/json');
echo json_encode($data);
