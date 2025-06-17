<?php
include '../database_connection.php';

$data_per_halaman = 5;
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
if ($halaman < 1) {
    $halaman = 1;
}
$offset = ($halaman - 1) * $data_per_halaman;

// Ambil data petugas
$sql = "SELECT * FROM petugas ORDER BY id ASC LIMIT $data_per_halaman OFFSET $offset";
$result = $conn->query($sql);

// Hitung total data & halaman
$total_data_sql = 'SELECT COUNT(*) AS total FROM petugas';
$total_data_result = $conn->query($total_data_sql);
$total_data_row = $total_data_result->fetch_assoc();
$total_data = $total_data_row['total'];
$total_halaman = ceil($total_data / $data_per_halaman);

if ($result->num_rows > 0) {
    $no = $offset + 1;
    while ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        // Ambil akses lain dari akses_petugas
        $akses = [];
        $stmtAkses = $conn->prepare(
            'SELECT role, unit FROM akses_petugas WHERE petugas_username = ?'
        );
        $stmtAkses->bind_param('s', $username);
        $stmtAkses->execute();
        $resultAkses = $stmtAkses->get_result();
        while ($ar = $resultAkses->fetch_assoc()) {
            $role = ucfirst($ar['role']);
            $unit = htmlspecialchars($ar['unit']);
            $akses[] =
                $role .
                " ($unit) " .
                "<button type='button' class='btn btn-sm btn-danger ms-1'
                    data-bs-toggle='modal'
                    data-bs-target='#hapusAksesModal'
                    onclick=\"setHapusAksesModal('{$username}', '{$ar['role']}', '{$ar['unit']}')\"
                    title='Hapus Akses'>
                        <i class='fas fa-times'></i>
                </button>";
        }
        $stmtAkses->close();
        $akses_text = empty($akses) ? '-' : implode('<br>', $akses);

        echo '<tr>';
        echo "<td class='text-center'>{$no}</td>";
        echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
        echo '<td>' . htmlspecialchars($row['username']) . '</td>';
        echo "<td class='text-center'>" .
            htmlspecialchars($row['unit']) .
            '</td>';
        echo '<td>' . $akses_text . '</td>';
        echo "<td class='text-center'>
            <button type='button'
                class='btn btn-secondary btn-sm mb-1'
                data-bs-toggle='modal'
                data-bs-target='#tambahAksesModal'
                onclick=\"setTambahAksesModal('{$row['username']}')\">
                <i class='fas fa-user-shield'></i> Tambah Akses
            </button>
            <button type='button'
                class='btn btn-warning btn-sm mb-1'
                data-bs-toggle='modal'
                data-bs-target='#editPetugasModal'
                onclick=\"setEditModalData('{$row['id']}', '" .
            htmlspecialchars($row['nama'], ENT_QUOTES) .
            "', '{$row['username']}', '{$row['unit']}')\">
                <i class='fas fa-edit'></i> Edit
            </button>
            <button type='button'
                class='btn btn-danger btn-sm mb-1'
                data-bs-toggle='modal'
                data-bs-target='#deleteConfirmationModal'
                onclick=\"setDeleteModalData('{$row['id']}', '" .
            htmlspecialchars($row['nama'], ENT_QUOTES) .
            "')\">
                <i class='fas fa-trash-alt'></i> Delete
            </button>
        </td>";
        echo '</tr>';
        $no++;
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Tidak ada data petugas.</td></tr>";
}
$conn->close();
?>
