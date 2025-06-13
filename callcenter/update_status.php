<?php
// callcenter/update_status.php
session_start();
header('Content-Type: application/json');
include '../database_connection.php';

// 1. Validasi session
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    echo json_encode(['success'=>false,'msg'=>'Unauthorized']);
    exit;
}

// 2. Ambil data POST
$id     = $_POST['id']     ?? null;
$status = $_POST['status'] ?? null;
$notes  = $_POST['notes']  ?? null;

// 3. Validasi ID
if (!$id) {
    echo json_encode(['success'=>false,'msg'=>'ID missing']);
    exit;
}

// 4. Status yang diperbolehkan (SAMA dengan ENUM di DB)
$allowed = [
    'PPDB Bersama',
    'Sudah Bayar',
    'Uang Titipan',
    'Akan Bayar',
    'Menunggu Negeri',
    'Menunggu Proses',        // <<< Tambahkan!
    'Tidak Ada Konfirmasi',
    'Tidak Jadi'
];

$updates = [];
$params  = [];
$types   = '';

// 5. Update status jika ada
if ($status !== null) {
    if ($status === '') {
        $updates[] = "status = NULL";
        // Tidak usah tambahkan ke $params/$types
    } else {
        if (!in_array($status, $allowed, true)) {
            echo json_encode(['success'=>false,'msg'=>'Invalid status: '.$status]);
            exit;
        }
        $updates[] = "status = ?";
        $types   .= 's';
        $params[] = $status;
    }
}

// 6. Update notes jika ada
if ($notes !== null) {
    $updates[] = "notes = ?";
    $types   .= 's';
    $params[] = $notes;
}

// 7. Pastikan ada yang diupdate
if (empty($updates)) {
    echo json_encode(['success'=>false,'msg'=>'Nothing to update']);
    exit;
}

// 8. Tambah ID di akhir
$types   .= 'i';
$params[] = $id;

// 9. Eksekusi query update
$sql = "UPDATE calon_pendaftar SET ".implode(", ",$updates)." WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success'=>false,'msg'=>'Prepare failed: '.$conn->error]);
    exit;
}
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'msg'=>'DB error: '.$stmt->error]);
}
$stmt->close();
$conn->close();
?>
