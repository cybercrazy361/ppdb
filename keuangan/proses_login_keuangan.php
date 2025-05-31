<?php
session_start();
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $unit = $_POST['unit'];

    // Validasi input kosong
    if (empty($username) || empty($password) || empty($unit)) {
        header('Location: login_keuangan.php?error=empty_fields');
        exit();
    }

    // Ambil data dari tabel keuangan
    $sql = "SELECT * FROM keuangan WHERE username = ? AND unit = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $username, $unit);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        // Verifikasi password
        if (password_verify($password, $row['password'])) {
            // Simpan data ke session
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama'] = $row['nama'];
            $_SESSION['unit'] = $row['unit'];
            $_SESSION['role'] = 'keuangan';
            header('Location: dashboard_keuangan.php');
            exit();
        } else {
            header('Location: login_keuangan.php?error=invalid_credentials');
            exit();
        }
    } else {
        header('Location: login_keuangan.php?error=invalid_credentials');
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
