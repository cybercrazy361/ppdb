<?php
// admin/manage_callcenter.php
session_start();

// Set header untuk mencegah caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Pengecekan apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header('Location: admin_login_page.php');
    exit();
}

// Sertakan koneksi ke database dengan path yang benar
include '../database_connection.php';

// Konfigurasi paginasi (optional, kalau mau paging data)
$limit = 10;
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
if ($halaman < 1) {
    $halaman = 1;
}
$offset = ($halaman - 1) * $limit;

// Hitung total data
$result_total = $conn->query('SELECT COUNT(*) as total FROM callcenter');
$total_data = $result_total->fetch_assoc()['total'] ?? 0;
$total_halaman = ceil($total_data / $limit);

// Tutup koneksi
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Petugas Call Center - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_callcenter_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Manajemen Petugas Call Center</a>
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
                        <a class="nav-link btn btn-danger text-white" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
<!-- Main Content -->
<div class="container mt-5">
    <h3>Manajemen Petugas Call Center</h3>
    <div class="d-flex justify-content-between mb-3">
        <a href="admin_dashboard_page.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCallCenterModal">
            <i class="fas fa-user-plus"></i> Tambah Call Center
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-striped align-middle shadow">
            <thead class="bg-primary text-white text-center">
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Unit</th>
                    <th>Akses Lain</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php include 'callcenter_table.php'; ?>
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <p>Total Data: <strong><?= $total_data ?></strong></p>
            <!-- ...pagination... -->
        </div>
    </div>
</div>

<!-- Modal Tambah Akses Call Center -->
<div class="modal fade" id="tambahAksesModal" tabindex="-1" aria-labelledby="tambahAksesModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="proses_tambah_akses.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tambahAksesModalLabel">Tambah Akses Petugas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="username" id="akses_username">
        <div class="mb-3">
          <label for="akses_role" class="form-label">Role / Akses</label>
          <select class="form-select" name="role" id="akses_role" required>
            <option value="">-- Pilih Role --</option>
            <option value="pendaftaran">Petugas Pendaftaran</option>
            <option value="keuangan">Petugas Keuangan</option>
            <option value="callcenter">Petugas Call Center</option>
            <option value="pimpinan">Pimpinan</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="akses_unit" class="form-label">Unit</label>
          <select class="form-select" name="unit" id="akses_unit" required>
            <option value="">-- Pilih Unit --</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
            <option value="Yayasan">Yayasan</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Tambah Akses</button>
      </div>
    </form>
  </div>
</div>

    <!-- Include Modals -->
    <?php include 'callcenter_modals.php'; ?>

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
