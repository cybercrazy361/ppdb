<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];

    // Hapus data berdasarkan ID
    $query = "DELETE FROM siswa WHERE id = $id";

    if ($conn->query($query)) {
        // Reset auto increment hanya jika tabel kosong
        $resetQuery = "SET @num := 0; 
                       UPDATE siswa SET id = (@num := @num + 1); 
                       ALTER TABLE siswa AUTO_INCREMENT = 1;";
        if ($conn->multi_query($resetQuery)) {
            do {
                // Consume all results
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }

        header('Location: daftar_siswa.php');
    } else {
        echo "Error: " . $conn->error;
    }
}

?>
