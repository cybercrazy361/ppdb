<?php
// admin/admin_dashboard_page.php
session_start();

// Set header untuk mencegah caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Pengecekan apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: admin_login_page.php");
    exit();
}

// Sertakan koneksi ke database dengan path yang benar
include '../database_connection.php';

// Mendapatkan username admin dari sesi
$username = $_SESSION['username'];

// Tutup koneksi
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna Pimpinan - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_pimpinan_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Managemen Pimpinan Unit</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard_page.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php"><i
                                class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <h3>Manajemen Pengguna Pimpinan</h3>
        <!-- Tombol Kembali ke Dashboard dan Tambah Pimpinan -->
        <div class="d-flex justify-content-between mb-3">
            <a href="admin_dashboard_page.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPimpinanModal">
                <i class="fas fa-user-plus"></i> Tambah Pimpinan
            </button>
        </div>
        <!-- Tabel Data Pimpinan -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-striped align-middle shadow">
                <thead class="bg-primary text-white text-center">
                    <tr>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Unit</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php include 'pimpinan_table.php'; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Include Modals -->
    <?php include 'modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Untuk modal edit
        function setEditModalData(id, nama, username, unit) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_unit').value = unit;
        }

        // Untuk modal konfirmasi delete
        function setDeleteModalData(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
        }
    </script>
</body>

</html>
