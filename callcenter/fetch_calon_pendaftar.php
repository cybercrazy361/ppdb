<?php
include '../database_connection.php';
$unit = $_GET['unit'] ?? '';
$status = $_GET['status'] ?? '';
$params = [$unit];
$sql = "SELECT nama, status, no_hp, notes FROM calon_pendaftar WHERE pilihan = ?";
if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}
$stmt = $conn->prepare($sql);
if (count($params) === 2) {
    $stmt->bind_param("ss", $params[0], $params[1]);
} else {
    $stmt->bind_param("s", $params[0]);
}
$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) $data[] = $row;
echo json_encode($data);
