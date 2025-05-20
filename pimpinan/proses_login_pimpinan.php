<?php
// Aktifkan error reporting saat pengembangan/debug (hapus di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../database_connection.php';

// Cek request POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = trim($_POST['role'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $unit     = trim($_POST['unit'] ?? '');

    // Validasi
    if ($role === '') {
        header("Location: login_umum.php?error=Role belum dipilih!");
        exit();
    }
    if ($username === '' || $password === '') {
        header("Location: login_umum.php?error=Username dan password wajib diisi!");
        exit();
    }
    if ($role === 'pimpinan' && $unit === '') {
        header("Location: login_umum.php?error=Unit wajib diisi untuk pimpinan!");
        exit();
    }

    if ($role === 'pimpinan') {
        // Login untuk pimpinan SMA/SMK
        $sql = "SELECT * FROM pimpinan WHERE username = ? AND unit = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            header("Location: login_umum.php?error=Kesalahan server. Coba lagi nanti!");
            exit();
        }
        $stmt->bind_param("ss", $username, $unit);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Sukses login pimpinan
                $_SESSION['pimpinan'] = $row['nama'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['unit']     = $row['unit'];
                session_regenerate_id(true);
                header("Location: dashboard_pimpinan.php");
                exit();
            } else {
                header("Location: login_umum.php?error=Password salah!");
                exit();
            }
        } else {
            header("Location: login_umum.php?error=Username/unit tidak ditemukan!");
            exit();
        }
        $stmt->close();

    } elseif ($role === 'yayasan') {
        // Login untuk yayasan
        // Ganti dengan nama table yayasan yang sesuai, atau gunakan table admin jika hanya satu user
        $sql = "SELECT * FROM yayasan WHERE username = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            header("Location: login_umum.php?error=Kesalahan server. Coba lagi nanti!");
            exit();
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                // Sukses login yayasan
                $_SESSION['yayasan']  = $row['nama'];
                $_SESSION['username'] = $row['username'];
                session_regenerate_id(true);
                header("Location: dashboard_yayasan.php");
                exit();
            } else {
                header("Location: login_umum.php?error=Password salah!");
                exit();
            }
        } else {
            header("Location: login_umum.php?error=Username yayasan tidak ditemukan!");
            exit();
        }
        $stmt->close();

    } else {
        header("Location: login_umum.php?error=Role tidak valid!");
        exit();
    }

    $conn->close();

} else {
    // Bukan request POST
    header("Location: login_umum.php");
    exit();
}
?>
