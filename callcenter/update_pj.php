<?php
include '../database_connection.php';
$id = intval($_POST['id'] ?? 0);
$pj_username = $_POST['pj_username'] ?? '';
if ($id <= 0) die(json_encode(['success'=>false,'msg'=>'ID tidak valid']));
if ($pj_username == '') $pj_username = null;
// Optional: cek apakah username valid di callcenter (tambahkan pengecekan kalau mau)
$stmt = $conn->prepare("UPDATE calon_pendaftar SET pj_username=? WHERE id=?");
$stmt->bind_param('si', $pj_username, $id);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'msg'=>'DB error']);
}
