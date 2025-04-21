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

if ($unit === '' || $status === '') {
    echo json_encode([]);
    exit();
}

if ($status === 'sudah') {
    // Query untuk siswa yang sudah membayar (Lunas atau Angsuran)
    // Menggunakan GROUP_CONCAT untuk menggabungkan status_pembayaran
    $query = "
        SELECT 
            s.nama,
            GROUP_CONCAT(pd.status_pembayaran SEPARATOR ', ') AS status_pembayaran
        FROM siswa s
        INNER JOIN pembayaran p ON s.id = p.siswa_id
        INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
        GROUP BY s.id
    ";
} elseif ($status === 'belum') {
    // Query untuk siswa yang belum membayar (tidak ada pembayaran sama sekali atau hanya Pending)
    $query = "
        SELECT 
            s.nama,
            'Belum Bayar' AS status_pembayaran
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (p.id IS NULL OR pd.status_pembayaran = 'Pending')
        GROUP BY s.id
    ";
} else {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => 'Query preparation failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();

$siswaList = [];
while ($row = $result->fetch_assoc()) {
    // Jika status_pembayaran adalah gabungan, pisahkan menjadi array
    if ($status === 'sudah') {
        $statuses = explode(', ', $row['status_pembayaran']);
        // Preferensi: jika ada 'Lunas', prioritaskan 'Lunas'
        if (in_array('Lunas', $statuses)) {
            $finalStatus = 'Lunas';
        } else {
            $finalStatus = implode(', ', $statuses);
        }
    } else {
        $finalStatus = $row['status_pembayaran'];
    }

    $siswaList[] = [
        'nama' => htmlspecialchars($row['nama']),
        'status_pembayaran' => htmlspecialchars($finalStatus)
    ];
}

$stmt->close();
$conn->close();

echo json_encode($siswaList);
?>
