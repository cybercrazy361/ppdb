<?php
include '../database_connection.php';

// Paging
$data_per_halaman = 5;
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
if ($halaman < 1) {
    $halaman = 1;
}
$offset = ($halaman - 1) * $data_per_halaman;

// Ambil username yang punya akses callcenter dari akses_petugas
$sqlAkses =
    "SELECT DISTINCT petugas_username FROM akses_petugas WHERE role = 'callcenter'";
$resultUsernames = $conn->query($sqlAkses);

$usernames = [];
while ($row = $resultUsernames->fetch_assoc()) {
    $usernames[] = $row['petugas_username'];
}

// Jika tidak ada user sama sekali
if (count($usernames) === 0) {
    echo "<tr><td colspan='6' class='text-center'>Tidak ada data petugas call center.</td></tr>";
    $conn->close();
    return;
}

// Paging manual (karena gabungan)
$total_data = count($usernames);
$total_halaman = ceil($total_data / $data_per_halaman);
$usernames_paged = array_slice($usernames, $offset, $data_per_halaman);

if (count($usernames_paged) === 0) {
    echo "<tr><td colspan='6' class='text-center'>Tidak ada data petugas call center di halaman ini.</td></tr>";
    $conn->close();
    return;
}

// Query detail ke dua tabel
$usernames_str =
    "'" . implode("','", array_map('addslashes', $usernames_paged)) . "'";
$sqlDetail = "
    SELECT username, nama, unit, 'petugas' as asal_tabel FROM petugas WHERE username IN ($usernames_str)
    UNION
    SELECT username, nama, unit, 'callcenter' as asal_tabel FROM callcenter WHERE username IN ($usernames_str)
";
$detailResult = $conn->query($sqlDetail);

// Simpan agar bisa akses asal_tabel untuk tiap username
$data_detail = [];
while ($row = $detailResult->fetch_assoc()) {
    $data_detail[$row['username']] = $row;
}

$no = $offset + 1;
foreach ($usernames_paged as $username) {
    if (!isset($data_detail[$username])) {
        continue;
    }
    $row = $data_detail[$username];
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
    echo "<td class='text-center'>" . htmlspecialchars($row['unit']) . '</td>';
    echo '<td>' . $akses_text . '</td>';
    echo "<td class='text-center'>";
    // Tombol tambah akses selalu ada
    echo "<button 
        class='btn btn-secondary btn-sm mb-1'
        data-bs-toggle='modal'
        data-bs-target='#tambahAksesModal'
        onclick=\"setTambahAksesModal('{$row['username']}')\">
        <i class='fas fa-user-shield'></i> Tambah Akses
    </button>";
    // Edit & hapus aktif jika asal dari callcenter
    if ($row['asal_tabel'] === 'callcenter') {
        echo "<button 
            class='btn btn-warning btn-sm mb-1' 
            data-bs-toggle='modal' 
            data-bs-target='#editCallCenterModal' 
            onclick=\"setEditModalData('{$row['username']}', '" .
            htmlspecialchars($row['nama'], ENT_QUOTES) .
            "', '{$row['username']}', '{$row['unit']}')\">
            <i class='fas fa-edit'></i> Edit
        </button>";
        echo "<button 
            class='btn btn-danger btn-sm mb-1' 
            data-bs-toggle='modal' 
            data-bs-target='#deleteCallCenterModal' 
            onclick=\"setDeleteModalData('{$row['username']}', '" .
            htmlspecialchars($row['nama'], ENT_QUOTES) .
            "')\">
            <i class='fas fa-trash-alt'></i> Delete
        </button>";
    } else {
        // Jika bukan dari callcenter, disable
        echo "<button class='btn btn-warning btn-sm mb-1' disabled><i class='fas fa-edit'></i> Edit</button>";
        echo "<button class='btn btn-danger btn-sm mb-1' disabled><i class='fas fa-trash-alt'></i> Delete</button>";
    }
    echo '</td>';
    echo '</tr>';
}
$conn->close();
?>
