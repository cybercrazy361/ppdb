<?php
// setting_jenis_pembayaran.php

session_start();

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

// Ambil unit petugas dari sesi
$petugas_unit = $_SESSION['unit']; // Pastikan pada saat login, unit sudah diset, misalnya 'SMA', 'SMK', atau 'Yayasan'

// Koneksi ke database
include '../database_connection.php';

// Ambil data jenis_pembayaran berdasarkan unit petugas
$query = "SELECT id, nama, unit FROM jenis_pembayaran WHERE unit = ? ORDER BY nama ASC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('s', $petugas_unit);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Error fetching data: " . $conn->error);
}

$jenis_pembayaran = [];
while ($row = $result->fetch_assoc()) {
    $jenis_pembayaran[] = $row;
}

$result->free();
$stmt->close();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Setting Jenis Pembayaran - Sistem Keuangan PPDB</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/setting_jenis_pembayaran_styles.css">
</head>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Content -->
    <div class="main-content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle Button -->
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Topbar Navbar -->
            <ul class="navbar-nav ms-auto">
                <!-- User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link" href="#">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Container -->
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="dashboard-title">Setting Jenis Pembayaran - <?php echo htmlspecialchars($petugas_unit); ?></h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahJenisPembayaran">
                    <i class="fas fa-plus"></i> Tambah Jenis Pembayaran
                </button>
            </div>

            <!-- Tabel Jenis Pembayaran -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>No</th>
                                    <th>Jenis Pembayaran</th>
                                    <th>Unit</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jenis_pembayaran as $index => $jp): ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td><?= htmlspecialchars($jp['nama']); ?></td>
                                        <td><?= htmlspecialchars($jp['unit']); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm edit-jenis-pembayaran-btn"
                                                    data-id="<?= htmlspecialchars($jp['id']); ?>"
                                                    data-nama="<?= htmlspecialchars($jp['nama']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-jenis-pembayaran-btn"
                                                    data-id="<?= htmlspecialchars($jp['id']); ?>"
                                                    data-nama="<?= htmlspecialchars($jp['nama']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($jenis_pembayaran)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada data jenis pembayaran untuk unit <?php echo htmlspecialchars($petugas_unit); ?>.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (Jika Diperlukan) -->
                    <!-- 
                    <nav>
                        <ul class="pagination justify-content-center">
                            <!-- Pagination Items -->
                    <!-- </ul>
                    </nav>
                    -->
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>

    <!-- Modal Tambah Jenis Pembayaran -->
    <div class="modal fade" id="modalTambahJenisPembayaran" tabindex="-1" aria-labelledby="modalTambahJenisPembayaranLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="tambahJenisPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahJenisPembayaranLabel">Tambah Jenis Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="tambah-jenis-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <!-- Input Nama Jenis Pembayaran -->
                        <div class="mb-3">
                            <label for="tambah_nama_jenis_pembayaran" class="form-label">Nama Jenis Pembayaran</label>
                            <input type="text" name="nama_jenis_pembayaran" id="tambah_nama_jenis_pembayaran" class="form-control" placeholder="Masukkan Nama Jenis Pembayaran" required>
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <!-- Action Type -->
                        <input type="hidden" name="action" value="add">
                        <!-- Unit Petugas -->
                        <input type="hidden" name="unit" value="<?= htmlspecialchars($petugas_unit); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Jenis Pembayaran -->
    <div class="modal fade" id="modalEditJenisPembayaran" tabindex="-1" aria-labelledby="modalEditJenisPembayaranLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editJenisPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditJenisPembayaranLabel">Edit Jenis Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="edit-jenis-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <!-- Input Nama Jenis Pembayaran -->
                        <div class="mb-3">
                            <label for="edit_nama_jenis_pembayaran" class="form-label">Nama Jenis Pembayaran</label>
                            <input type="text" name="nama_jenis_pembayaran" id="edit_nama_jenis_pembayaran" class="form-control" placeholder="Masukkan Nama Jenis Pembayaran" required>
                        </div>

                        <!-- CSRF Token dan ID Jenis Pembayaran -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="jenis_id" id="edit_jenis_id">
                        <!-- Unit Petugas -->
                        <input type="hidden" name="unit" value="<?= htmlspecialchars($petugas_unit); ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Perbarui</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Jenis Pembayaran -->
    <div class="modal fade" id="modalDeleteJenisPembayaran" tabindex="-1" aria-labelledby="modalDeleteJenisPembayaranLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteJenisPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalDeleteJenisPembayaranLabel">Konfirmasi Hapus Jenis Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="delete-jenis-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <p>Apakah Anda yakin ingin menghapus jenis pembayaran berikut?</p>
                        <p><strong>Jenis Pembayaran:</strong> <span id="delete_jenis_nama"></span></p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="jenis_id" id="delete_jenis_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
                        <!-- Unit Petugas -->
                        <input type="hidden" name="unit" value="<?= htmlspecialchars($petugas_unit); ?>">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <!-- Bootstrap Bundle JS (Termasuk Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (Jika Diperlukan) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/sidebar.js"></script>
    <script src="../assets/js/setting_jenis_pembayaran.js"></script>
</body>

</html>
