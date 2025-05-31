<?php
// search_student.php

// Mulai sesi
session_start();

// Set header ke JSON
header('Content-Type: application/json');

// Cek autentikasi dan peran pengguna
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Cek parameter 'query'
if (!isset($_GET['query'])) {
    echo json_encode(['success' => false, 'message' => 'No query provided']);
    exit();
}

$query = trim($_GET['query']);

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit();
}

// Koneksi ke database
include '../database_connection.php';

// Ambil unit petugas dari sesi
$unit_petugas = $_SESSION['unit'];

// Siapkan dan eksekusi query
$stmt = $conn->prepare("SELECT no_formulir, nama FROM siswa WHERE unit = ? AND (no_formulir LIKE ? OR nama LIKE ?) LIMIT 10");
$search_term = '%' . $query . '%';
$stmt->bind_param('sss', $unit_petugas, $search_term, $search_term);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'no_formulir' => $row['no_formulir'],
            'nama' => $row['nama']
        ];
    }
    echo json_encode(['success' => true, 'data' => $students]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failed']);
}

$stmt->close();
$conn->close();
?>
