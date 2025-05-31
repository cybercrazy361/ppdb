<?php
include '../database_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['nama']) && !empty($_POST['username']) && !empty($_POST['password']) && !empty($_POST['unit'])) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_BCRYPT); // Hash password
    $unit = trim($_POST['unit']);

    $sql = "INSERT INTO pimpinan (nama, username, password, unit) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nama, $username, $password, $unit);

    if ($stmt->execute()) {
        echo "<script>alert('Pimpinan berhasil ditambahkan.'); window.location.href='manage_pimpinan.php';</script>";
    } else {
        echo "<script>alert('Gagal menambahkan pimpinan.'); window.location.href='manage_pimpinan.php';</script>";
    }

    $stmt->close();
}

$conn->close();
?>
