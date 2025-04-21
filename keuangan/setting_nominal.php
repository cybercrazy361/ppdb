<?php
// setting_nominal.php

session_start();

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

// Ambil unit petugas dari sesi
$petugas_unit = $_SESSION['unit'];

// Koneksi ke database
include '../database_connection.php';

// Fetch data pengaturan_nominal dengan unit petugas
$query = "SELECT pn.id, pn.jenis_pembayaran_id, pn.nominal_max, jp.nama AS jenis_pembayaran, pn.bulan
          FROM pengaturan_nominal pn
          JOIN jenis_pembayaran jp ON pn.jenis_pembayaran_id = jp.id
          WHERE jp.unit = ?
          ORDER BY pn.id ASC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('s', $petugas_unit);
$stmt->execute();
$result = $stmt->get_result();

$pengaturan_nominal = [];
while ($row = $result->fetch_assoc()) {
    $pengaturan_nominal[] = $row;
}
$stmt->close();

// Fetch jenis_pembayaran yang sesuai dengan unit petugas
$query_pembayaran = "SELECT id, nama FROM jenis_pembayaran WHERE unit = ? ORDER BY nama ASC";
$stmt_pembayaran = $conn->prepare($query_pembayaran);
if (!$stmt_pembayaran) {
    die("Error preparing jenis_pembayaran statement: " . $conn->error);
}
$stmt_pembayaran->bind_param('s', $petugas_unit);
$stmt_pembayaran->execute();
$result_pembayaran = $stmt_pembayaran->get_result();

$jenis_pembayaran = [];
while ($row = $result_pembayaran->fetch_assoc()) {
    $jenis_pembayaran[$row['id']] = $row['nama'];
}

$stmt_pembayaran->close();

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
    <title>Setting Nominal Maksimum - Sistem Keuangan PPDB</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/setting_nominal_styles.css">
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
                <h1 class="dashboard-title">Setting Nominal Maksimum - <?php echo htmlspecialchars($petugas_unit); ?></h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahNominal">
                    <i class="fas fa-plus"></i> Tambah Pengaturan
                </button>
            </div>

            <!-- Tabel Pengaturan Nominal -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>No</th>
                                    <th>Jenis Pembayaran</th>
                                    <th>Nominal Maksimum (Rp)</th>
                                    <th>Bulan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pengaturan_nominal as $index => $pn): ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td><?= htmlspecialchars($pn['jenis_pembayaran']); ?></td>
                                        <td><?= number_format($pn['nominal_max'], 0, ',', '.'); ?></td>
                                        <td>
                                            <?php
                                            if ($pn['bulan'] !== NULL) {
                                                echo htmlspecialchars($pn['bulan']);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-sm edit-nominal-btn"
                                                data-id="<?= htmlspecialchars($pn['id']); ?>"
                                                data-jenis_pembayaran_id="<?= htmlspecialchars($pn['jenis_pembayaran_id']); ?>"
                                                data-nominal="<?= htmlspecialchars($pn['nominal_max']); ?>"
                                                data-bulan="<?= htmlspecialchars($pn['bulan']); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-nominal-btn"
                                                data-id="<?= htmlspecialchars($pn['id']); ?>"
                                                data-jenis="<?= htmlspecialchars($pn['jenis_pembayaran']); ?>"
                                                data-nominal="<?= htmlspecialchars($pn['nominal_max']); ?>"
                                                data-bulan="<?= htmlspecialchars($pn['bulan']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pengaturan_nominal)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Tidak ada pengaturan nominal ditemukan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination (Jika Diperlukan) -->
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>

    <!-- Modal Tambah Pengaturan Nominal -->
    <div class="modal fade" id="modalTambahNominal" tabindex="-1" aria-labelledby="modalTambahNominalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="tambahNominalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahNominalLabel">Tambah Pengaturan Nominal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="tambah-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <!-- Pilih Jenis Pembayaran -->
                        <div class="mb-3">
                            <label for="tambah_jenis_pembayaran" class="form-label">Jenis Pembayaran</label>
                            <select name="jenis_pembayaran" id="tambah_jenis_pembayaran" class="form-select" required>
                                <option value="" disabled selected>Pilih Jenis Pembayaran</option>
                                <?php
                                foreach ($jenis_pembayaran as $id => $nama) {
                                    echo '<option value="' . htmlspecialchars($id) . '">' . htmlspecialchars($nama) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Input Nominal Maksimum -->
                        <div class="mb-3">
                            <label for="tambah_nominal_max" class="form-label">Nominal Maksimum (Rp)</label>
                            <input type="text" name="nominal_max" id="tambah_nominal_max" class="form-control nominal-input" placeholder="Masukkan Nominal Maksimum" required>
                        </div>

                        <!-- Input Bulan (Hanya untuk SPP) -->
                        <div class="mb-3" id="tambah_bulan_container" style="display: none;">
                            <label for="tambah_bulan" class="form-label">Bulan</label>
                            <select name="bulan" id="tambah_bulan" class="form-select">
                                <option value="" disabled selected>Pilih Bulan</option>
                                <option value="Juli">Juli</option>
                                <option value="Agustus">Agustus</option>
                                <option value="September">September</option>
                                <option value="Oktober">Oktober</option>
                                <option value="November">November</option>
                                <option value="Desember">Desember</option>
                                <option value="Januari">Januari</option>
                                <option value="Februari">Februari</option>
                                <option value="Maret">Maret</option>
                                <option value="April">April</option>
                                <option value="Mei">Mei</option>
                                <option value="Juni">Juni</option>
                            </select>
                        </div>

                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <!-- Action Type -->
                        <input type="hidden" name="action" value="add">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Pengaturan Nominal -->
    <div class="modal fade" id="modalEditNominal" tabindex="-1" aria-labelledby="modalEditNominalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editNominalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditNominalLabel">Edit Pengaturan Nominal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="edit-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <!-- Pilih Jenis Pembayaran -->
                        <div class="mb-3">
                            <label for="edit_jenis_pembayaran" class="form-label">Jenis Pembayaran</label>
                            <select name="jenis_pembayaran" id="edit_jenis_pembayaran" class="form-select" required>
                                <option value="" disabled>Pilih Jenis Pembayaran</option>
                                <?php
                                foreach ($jenis_pembayaran as $id => $nama) {
                                    echo '<option value="' . htmlspecialchars($id) . '">' . htmlspecialchars($nama) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Input Nominal Maksimum -->
                        <div class="mb-3">
                            <label for="edit_nominal_max" class="form-label">Nominal Maksimum (Rp)</label>
                            <input type="text" name="nominal_max" id="edit_nominal_max" class="form-control nominal-input" placeholder="Masukkan Nominal Maksimum" required>
                        </div>

                        <!-- Input Bulan (Hanya untuk SPP) -->
                        <div class="mb-3" id="edit_bulan_container" style="display: none;">
                            <label for="edit_bulan" class="form-label">Bulan</label>
                            <select name="bulan" id="edit_bulan" class="form-select">
                                <option value="" disabled selected>Pilih Bulan</option>
                                <option value="Juli">Juli</option>
                                <option value="Agustus">Agustus</option>
                                <option value="September">September</option>
                                <option value="Oktober">Oktober</option>
                                <option value="November">November</option>
                                <option value="Desember">Desember</option>
                                <option value="Januari">Januari</option>
                                <option value="Februari">Februari</option>
                                <option value="Maret">Maret</option>
                                <option value="April">April</option>
                                <option value="Mei">Mei</option>
                                <option value="Juni">Juni</option>
                            </select>
                        </div>

                        <!-- CSRF Token dan ID Pengaturan Nominal -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="pengaturan_id" id="edit_pengaturan_id">
                        <input type="hidden" name="action" value="edit">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Perbarui</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Pengaturan Nominal -->
    <div class="modal fade" id="modalDeleteNominal" tabindex="-1" aria-labelledby="modalDeleteNominalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteNominalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalDeleteNominalLabel">Konfirmasi Hapus Pengaturan Nominal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Error Container -->
                        <div id="delete-form-errors" class="alert alert-danger" style="display: none;"></div>

                        <p>Apakah Anda yakin ingin menghapus pengaturan nominal berikut?</p>
                        <p><strong>Jenis Pembayaran:</strong> <span id="delete_pengaturan_jenis"></span></p>
                        <p><strong>Nominal Maksimum:</strong> <span id="delete_pengaturan_nominal"></span></p>
                        <p><strong>Bulan:</strong> <span id="delete_pengaturan_bulan"></span></p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="pengaturan_id" id="delete_pengaturan_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
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
    <script src="../assets/js/setting_nominal.js"></script>
</body>

</html>
