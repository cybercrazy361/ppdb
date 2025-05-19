<?php
session_start();
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $unit = trim($_POST['unit'] ?? '');

    // Validasi
    if ($username === '' || $password === '' || $unit === '') {
        header("Location: login_pimpinan.php?error=Semua field wajib diisi!");
        exit();
    }

    $sql = "SELECT * FROM pimpinan WHERE username=? AND unit=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $unit);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Sukses login
            $_SESSION['pimpinan'] = $row['nama'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['unit'] = $row['unit'];
            session_regenerate_id(true);
            header("Location: dashboard_pimpinan.php");
            exit();
        } else {
            header("Location: login_pimpinan.php?error=Password salah!");
            exit();
        }
    } else {
        header("Location: login_pimpinan.php?error=Username/unit tidak ditemukan!");
        exit();
    }
}
header("Location: login_pimpinan.php");
exit();
?>
