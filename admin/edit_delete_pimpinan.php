<?php
include '../database_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Proses Edit
    if (isset($_POST['edit']) && !empty($_POST['id']) && !empty($_POST['nama']) && !empty($_POST['username']) && !empty($_POST['unit'])) {
        $id = $_POST['id'];
        $nama = trim($_POST['nama']);
        $username = trim($_POST['username']);
        $unit = trim($_POST['unit']);

        $sql = "UPDATE pimpinan SET nama = ?, username = ?, unit = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nama, $username, $unit, $id);

        if ($stmt->execute()) {
            echo "<script>alert('Data berhasil diperbarui.'); window.location.href='manage_pimpinan.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui data.'); window.location.href='manage_pimpinan.php';</script>";
        }
        $stmt->close();
    }

    // Proses Delete
    if (isset($_POST['delete']) && !empty($_POST['id'])) {
        $id = $_POST['id'];

        $sql = "DELETE FROM pimpinan WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo "<script>alert('Data berhasil dihapus.'); window.location.href='manage_pimpinan.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data.'); window.location.href='manage_pimpinan.php';</script>";
        }
        $stmt->close();
    }
}

$conn->close();
?>
