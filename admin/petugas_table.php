<?php
include '../database_connection.php';

// Tentukan jumlah data per halaman
$data_per_halaman = 5;

// Ambil nomor halaman dari URL, jika tidak ada set ke halaman 1
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($halaman < 1) {
    $halaman = 1;
}

// Hitung OFFSET
$offset = ($halaman - 1) * $data_per_halaman;

// Ambil data dengan LIMIT dan OFFSET
$sql = "SELECT * FROM petugas ORDER BY id ASC LIMIT $data_per_halaman OFFSET $offset";
$result = $conn->query($sql);

// Hitung total data
$total_data_sql = "SELECT COUNT(*) AS total FROM petugas";
$total_data_result = $conn->query($total_data_sql);
$total_data_row = $total_data_result->fetch_assoc();
$total_data = $total_data_row['total'];

// Hitung total halaman
$total_halaman = ceil($total_data / $data_per_halaman);

// Tampilkan data
if ($result->num_rows > 0) {
    $no = $offset + 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td class='text-center'>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td class='text-center'>" . htmlspecialchars($row['unit']) . "</td>";
        echo "<td class='text-center'>
            <button 
                class='btn btn-warning btn-sm' 
                data-bs-toggle='modal' 
                data-bs-target='#editPetugasModal' 
                onclick=\"setEditModalData('{$row['id']}', '{$row['nama']}', '{$row['username']}', '{$row['unit']}')\">
                <i class='fas fa-edit'></i> Edit
            </button>
            <button 
                class='btn btn-danger btn-sm' 
                data-bs-toggle='modal' 
                data-bs-target='#deleteConfirmationModal' 
                onclick=\"setDeleteModalData('{$row['id']}', '{$row['nama']}')\">
                <i class='fas fa-trash-alt'></i> Delete
            </button>
        </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' class='text-center'>Tidak ada data petugas.</td></tr>";
}
$conn->close();
?>
