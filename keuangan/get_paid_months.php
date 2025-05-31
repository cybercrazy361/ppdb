<?php
// get_paid_months.php

session_start();
header('Content-Type: application/json');

// Cek autentikasi & otorisasi
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

// Validasi parameter
if (!isset($_GET['no_formulir'], $_GET['tahun'])) {
    echo json_encode(['success'=>false,'message'=>'Missing parameters']);
    exit;
}

include '../database_connection.php';

$no     = $_GET['no_formulir'];
$tahun  = $_GET['tahun'];

// Ambil bulan-bulan SPP yang sudah lunas
$stmt = $conn->prepare("
    SELECT DISTINCT pd.bulan
    FROM pembayaran_detail pd
    JOIN pembayaran p ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE p.no_formulir = ?
      AND p.tahun_pelajaran = ?
      AND LOWER(jp.nama) = 'spp'
");
$stmt->bind_param('ss', $no, $tahun);
$stmt->execute();
$res = $stmt->get_result();

$paid = [];
while ($r = $res->fetch_assoc()) {
    if (!empty($r['bulan'])) {
        $paid[] = $r['bulan'];
    }
}

echo json_encode(['success'=>true,'paid_months'=>$paid]);
