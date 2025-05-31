<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $unit = $_POST['unit'];

    $sql = "INSERT INTO keuangan (nama, username, password, unit) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $nama, $username, $password, $unit);

    if ($stmt->execute()) {
        header('Location: manage_keuangan.php?message=success');
    } else {
        header('Location: manage_keuangan.php?message=error');
    }

    $stmt->close();
    $conn->close();
}
?>
