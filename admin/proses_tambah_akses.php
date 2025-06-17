<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $role = $_POST['role'];
    $unit = $_POST['unit'];

    // Cek duplikat akses
    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM akses_petugas WHERE petugas_username = ? AND role = ? AND unit = ?'
    );
    $stmt->bind_param('sss', $username, $role, $unit);
    $stmt->execute();
    $stmt->bind_result($ada);
    $stmt->fetch();
    $stmt->close();

    if ($ada == 0) {
        // Insert akses ke akses_petugas
        $stmt = $conn->prepare(
            'INSERT INTO akses_petugas (petugas_username, role, unit) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $username, $role, $unit);
        $stmt->execute();
        $stmt->close();

        // Jika role callcenter, insert juga ke tabel callcenter jika belum ada
        if ($role === 'callcenter') {
            // 1. Cek dulu apakah user sudah ada di callcenter untuk unit tsb
            $cek = $conn->prepare(
                'SELECT COUNT(*) FROM callcenter WHERE username = ? AND unit = ?'
            );
            $cek->bind_param('ss', $username, $unit);
            $cek->execute();
            $cek->bind_result($sudah);
            $cek->fetch();
            $cek->close();

            if ($sudah == 0) {
                // 2. Ambil data nama dan password dari petugas
                $qry = $conn->prepare(
                    'SELECT nama, password FROM petugas WHERE username = ?'
                );
                $qry->bind_param('s', $username);
                $qry->execute();
                $qry->bind_result($nama, $password);

                if ($qry->fetch()) {
                    $qry->close(); // Tutup dulu SEBELUM buat statement baru!

                    // 3. Insert ke callcenter
                    $ins = $conn->prepare(
                        'INSERT INTO callcenter (username, password, nama, unit) VALUES (?, ?, ?, ?)'
                    );
                    $ins->bind_param(
                        'ssss',
                        $username,
                        $password,
                        $nama,
                        $unit
                    );
                    $ins->execute();
                    $ins->close();
                } else {
                    $qry->close();
                }
            }
        }

        header('Location: manage_pendaftaran.php?success=1');
        exit();
    } else {
        header('Location: manage_pendaftaran.php?error=akses_sudah_ada');
        exit();
    }
}
?>
