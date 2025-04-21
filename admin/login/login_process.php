<?php
// login_process.php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mendapatkan data dari form
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validasi input
    if (empty($username) || empty($password)) {
        echo "<script>alert('Username dan Password harus diisi!'); window.location.href='../admin_login_page.php';</script>";
        exit();
    }

    // Sertakan koneksi ke database dengan path yang benar
    include '../../database_connection.php';

    // Pastikan koneksi berhasil
    if (!$conn) {
        echo "<script>alert('Koneksi database gagal!'); window.location.href='../admin_login_page.php';</script>";
        exit();
    }

    // Query untuk memeriksa username
    $sql = "SELECT * FROM admin WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // Jangan menampilkan detail kesalahan ke pengguna
        error_log("Prepare failed: " . htmlspecialchars($conn->error));
        echo "<script>alert('Terjadi kesalahan pada server. Silakan coba lagi nanti.'); window.location.href='../admin_login_page.php';</script>";
        exit();
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Login berhasil
            $_SESSION['username'] = $username;
            // Regenerasi ID sesi untuk mencegah session fixation
            session_regenerate_id(true);
            header("Location: ../admin_dashboard_page.php");
            exit();
        } else {
            echo "<script>alert('Password salah!'); window.location.href='../admin_login_page.php';</script>";
        }
    } else {
        echo "<script>alert('Username tidak ditemukan!'); window.location.href='../admin_login_page.php';</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    // Jika bukan POST, arahkan ke halaman login
    header("Location: ../admin_login_page.php");
    exit();
}
?>
