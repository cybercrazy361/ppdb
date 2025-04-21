<?php
include '../database_connection.php';

$no_formulir = $_GET['no_formulir'] ?? '';

$response = ['success' => false];

if (!empty($no_formulir)) {
    $stmt = $conn->prepare("SELECT nama FROM siswa WHERE no_formulir = ? LIMIT 1");
    $stmt->bind_param('s', $no_formulir);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['nama'] = $row['nama'];
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>
