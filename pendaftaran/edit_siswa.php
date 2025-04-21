<?php
include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $asal_sekolah = $_POST['asal_sekolah'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];

    $query = "UPDATE siswa SET 
                nama = '$nama', 
                jenis_kelamin = '$jenis_kelamin',
                tempat_lahir = '$tempat_lahir',
                tanggal_lahir = '$tanggal_lahir',
                asal_sekolah = '$asal_sekolah',
                alamat = '$alamat',
                no_hp = '$no_hp'
              WHERE id = $id";

    if ($conn->query($query)) {
        header('Location: daftar_siswa.php');
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
