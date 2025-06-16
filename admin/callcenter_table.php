<?php
include '../database_connection.php';

// Paging
$data_per_halaman = 5;
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
if ($halaman < 1) {
    $halaman = 1;
}
$offset = ($halaman - 1) * $data_per_halaman;

// Ambil semua username yang punya akses callcenter dari akses_petugas
$sql =
    "SELECT DISTINCT petugas_username FROM akses_petugas WHERE role = 'callcenter'";
$resultUsernames = $conn->query($sql);

$usernames = [];
while ($row = $resultUsernames->fetch_assoc()) {
    $usernames[] = $row['petugas_username'];
}

if (count($usernames) > 0) {
    // Gabungkan semua username dari tabel petugas dan callcenter (cari nama/unit dsb)
    $usernames_str =
        "'" . implode("','", array_map('addslashes', $usernames)) . "'";
    $sqlDetail = "
        SELECT username, nama, unit FROM petugas WHERE username IN ($usernames_str)
        UNION
        SELECT username, nama, unit FROM callcenter WHERE username IN ($usernames_str)
    ";
    $detailResult = $conn->query($sqlDetail);

    $no = $offset + 1;
    while ($row = $detailResult->fetch_assoc()) {
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
            $akses[] = ucfirst($ar['role']) . ' (' . $ar['unit'] . ')';
        }
        $stmtAkses->close();
        $akses_text = empty($akses) ? '-' : implode(', ', $akses);

        echo '<tr>';
        echo "<td class='text-center'>" . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
        echo '<td>' . htmlspecialchars($row['username']) . '</td>';
        echo "<td class='text-center'>" .
            htmlspecialchars($row['unit']) .
            '</td>';
        echo '<td>' . $akses_text . '</td>';
        echo "<td class='text-center'>
            <button class='btn btn-secondary btn-sm mb-1'
                data-bs-toggle='modal'
                data-bs-target='#tambahAksesModal'
                onclick=\"setTambahAksesModal('{$row['username']}')\">
                <i class='fas fa-user-shield'></i> Tambah Akses
            </button>
            <!-- tombol edit dan delete opsional, custom sesuai sistemmu -->
        </td>";
        echo '</tr>';
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Tidak ada data petugas call center.</td></tr>";
}
$conn->close();
?>
