<?php
// Aktifkan error reporting saat pengembangan, matikan di produksi
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $unit     = trim($_POST['unit'] ?? '');

    // Validasi input
    if ($username === '' || $password === '' || $unit === '') {
        header("Location: login_pimpinan.php?error=Semua field wajib diisi!");
        exit();
    }

    // Query ke tabel pimpinan dengan unit (Yayasan/SMA/SMK)
    $sql = "SELECT * FROM pimpinan WHERE username = ? AND unit = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        header("Location: login_pimpinan.php?error=Kesalahan server. Coba lagi nanti!");
        exit();
    }
    $stmt->bind_param("ss", $username, $unit);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Login sukses
            $_SESSION['pimpinan'] = $row['nama'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['unit']     = $row['unit'];
            session_regenerate_id(true);

            // Redirect ke dashboard sesuai unit
            if ($unit === 'Yayasan') {
                header("Location: dashboard_yayasan.php");
                exit();
            } else {
                header("Location: dashboard_pimpinan.php");
                exit();
            }
        } else {
            // Password salah
            header("Location: login_pimpinan.php?error=Password salah!");
            exit();
        }
    } else {
        // Username/unit tidak ditemukan
        header("Location: login_pimpinan.php?error=Username/unit tidak ditemukan!");
        exit();
    }
    $stmt->close();
    $conn->close();
} else {
    // Jika bukan POST, kembalikan ke login
    header("Location: login_pimpinan.php");
    exit();
}
?>
