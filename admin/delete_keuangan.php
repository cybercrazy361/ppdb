<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    $sql = "DELETE FROM keuangan WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        header('Location: manage_keuangan.php?message=deleted');
    } else {
        header('Location: manage_keuangan.php?message=error');
    }

    $stmt->close();
    $conn->close();
}
?>
