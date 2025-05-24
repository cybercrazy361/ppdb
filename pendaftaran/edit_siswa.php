<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $no_formulir = $_POST['no_formulir']; // Tambah ini!
    $nama = $_POST['nama'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $asal_sekolah = $_POST['asal_sekolah'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];
    $no_hp_ortu = $_POST['no_hp_ortu'] ?? null;

    // Tambahkan update no_formulir juga
    $query = "UPDATE siswa SET 
                no_formulir = ?,
                nama = ?, 
                jenis_kelamin = ?,
                tempat_lahir = ?,
                tanggal_lahir = ?,
                asal_sekolah = ?,
                alamat = ?,
                no_hp = ?,
                no_hp_ortu = ?
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        'sssssssssi',
        $no_formulir,
        $nama,
        $jenis_kelamin,
        $tempat_lahir,
        $tanggal_lahir,
        $asal_sekolah,
        $alamat,
        $no_hp,
        $no_hp_ortu,
        $id
    );

    if ($stmt->execute()) {
        header('Location: daftar_siswa.php');
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
