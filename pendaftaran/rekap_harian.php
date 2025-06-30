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

// Ambil daftar siswa yang melakukan pembayaran pada tanggal tersebut
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.calon_pendaftar_id
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    WHERE s.unit = ? AND DATE(p.tanggal_pembayaran) = ?
");
$stmt->bind_param('ss', $unit, $tanggal);
$stmt->execute();
$result = $stmt->get_result();

$lunas = $angsuran = $belum = $total = $ppdb = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $calon_pendaftar_id = intval($row['calon_pendaftar_id'] ?? 0);
    $status_ppdb = '';

    // Cek status PPDB
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
        // Ambil tagihan uang pangkal
        $tagihan_up = 0;
        $stmt_tagihan = $conn->prepare(
            'SELECT nominal FROM siswa_tagihan_awal WHERE siswa_id=? AND jenis_pembayaran_id=?'
        );
        $stmt_tagihan->bind_param('ii', $id, $uang_pangkal_id);
        $stmt_tagihan->execute();
        $res_tagihan = $stmt_tagihan->get_result();
        if ($row_tagihan = $res_tagihan->fetch_assoc()) {
            $tagihan_up = (int) $row_tagihan['nominal'];
        }
        $stmt_tagihan->close();

        // Hitung total pembayaran uang pangkal dikurangi cashback
        $total_bayar_up = 0;
        $stmt_bayar = $conn->prepare("
            SELECT SUM(pd.jumlah - IFNULL(pd.cashback,0)) AS total_bayar
            FROM pembayaran_detail pd
            JOIN pembayaran p ON pd.pembayaran_id = p.id
            WHERE p.siswa_id = ? AND pd.jenis_pembayaran_id = ?
        ");
        $stmt_bayar->bind_param('ii', $id, $uang_pangkal_id);
        $stmt_bayar->execute();
        $res_bayar = $stmt_bayar->get_result();
        if ($row_bayar = $res_bayar->fetch_assoc()) {
            $total_bayar_up = (int) $row_bayar['total_bayar'];
        }
        $stmt_bayar->close();

        // Status
        if ($tagihan_up > 0 && $total_bayar_up >= $tagihan_up) {
            $stat = 'Lunas';
        } elseif ($total_bayar_up > 0) {
            $stat = 'Angsuran';
        } else {
            $stat = 'Belum Bayar';
        }

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
