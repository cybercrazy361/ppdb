<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $unit = $_POST['unit'];

    // Cek duplikat
    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM akses_petugas WHERE petugas_username = ? AND role = ? AND unit = ?'
    );
    $stmt->bind_param('sss', $username, $role, $unit);
    $stmt->execute();
    $stmt->bind_result($ada);
    $stmt->fetch();
    $stmt->close();

    if ($ada == 0) {
        $stmt = $conn->prepare(
            'INSERT INTO akses_petugas (petugas_username, role, unit) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $username, $role, $unit);
        $stmt->execute();
        $stmt->close();
        header('Location: manage_pendaftaran.php?success=1');
        exit();
    } else {
        header('Location: manage_pendaftaran.php?error=akses_sudah_ada');
        exit();
    }
}
?>
