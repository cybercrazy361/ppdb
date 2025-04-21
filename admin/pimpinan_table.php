<?php
include '../database_connection.php';

$sql = "SELECT * FROM pimpinan ORDER BY id ASC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $no = 1;
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
                data-bs-target='#editPimpinanModal' 
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
    echo "<tr><td colspan='5' class='text-center'>Tidak ada data pimpinan.</td></tr>";
}
$conn->close();
?>
