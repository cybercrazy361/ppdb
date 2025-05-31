<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $unit = $_POST['unit'];

    // Jika password diisi, update password juga
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $sql = "UPDATE callcenter SET nama = ?, username = ?, password = ?, unit = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssi', $nama, $username, $password, $unit, $id);
    } else {
        $sql = "UPDATE callcenter SET nama = ?, username = ?, unit = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssi', $nama, $username, $unit, $id);
    }

    if ($stmt->execute()) {
        header('Location: manage_callcenter.php?message=updated');
    } else {
        header('Location: manage_callcenter.php?message=error');
    }

    $stmt->close();
    $conn->close();
}
?>
