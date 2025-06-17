<?php
include '../database_connection.php';
if (
    isset($_POST['username']) &&
    isset($_POST['role']) &&
    isset($_POST['unit'])
) {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $unit = $_POST['unit'];

    $stmt = $conn->prepare(
        'DELETE FROM akses_petugas WHERE petugas_username = ? AND role = ? AND unit = ?'
    );
    $stmt->bind_param('sss', $username, $role, $unit);
    $stmt->execute();
    $stmt->close();
}
header('Location: manage_pendaftaran.php'); // atau ke halaman sesuai aplikasi kamu
exit();
?>
