<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $unit = $_POST['unit'];

    $sql = "UPDATE petugas SET nama = ?, username = ?, unit = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $nama, $username, $unit, $id);

    if ($stmt->execute()) {
        header('Location: manage_pendaftaran.php?message=updated');
    } else {
        header('Location: manage_pendaftaran.php?message=error');
    }

    $stmt->close();
    $conn->close();
}
?>
