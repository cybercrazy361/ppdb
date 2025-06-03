<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']); // selalu gunakan intval

    // Hapus data berdasarkan ID dengan prepared statement
    $stmt = $conn->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        // Cek apakah tabel siswa sudah kosong, kalau iya reset auto increment
        $result = $conn->query("SELECT COUNT(*) AS total FROM siswa");
        $row = $result->fetch_assoc();
        if ($row['total'] == 0) {
            $conn->query("ALTER TABLE siswa AUTO_INCREMENT = 1");
        }
        // Redirect
        header('Location: daftar_siswa.php');
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>
