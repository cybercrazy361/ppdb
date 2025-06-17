<?php
session_start();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['username'])) {
    header('Location: admin_login_page.php');
    exit();
}
include '../database_connection.php';

// Paging config
$halaman = isset($_GET['halaman']) ? (int) $_GET['halaman'] : 1;
$limit = 10;
$start = $halaman > 1 ? $halaman * $limit - $limit : 0;

$total_data = $conn->query('SELECT COUNT(*) FROM petugas')->fetch_row()[0];
$total_halaman = ceil($total_data / $limit);

$sql = "SELECT * FROM petugas ORDER BY id DESC LIMIT $start, $limit";
$petugas = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Petugas Pendaftaran - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage_pendaftaran_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Manajemen Petugas Pendaftaran</a>
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
        <h3>Manajemen Petugas Pendaftaran</h3>
        <div class="d-flex justify-content-between mb-3">
            <a href="admin_dashboard_page.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPetugasModal">
                <i class="fas fa-user-plus"></i> Tambah Petugas
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
                    <?php
                    $i = $start + 1;
                    while ($row = $petugas->fetch_assoc()):

                        $username = $row['username'];
                        $id = $row['id'];
                        $unit = $row['unit'];
                        $nama = $row['nama'];

                        // Query akses lain (sekaligus tampilkan tombol hapus akses)
                        $akses = [];
                        $stmtAkses = $conn->prepare(
                            'SELECT role, unit FROM akses_petugas WHERE petugas_username = ?'
                        );
                        $stmtAkses->bind_param('s', $username);
                        $stmtAkses->execute();
                        $resultAkses = $stmtAkses->get_result();
                        while ($ar = $resultAkses->fetch_assoc()) {
                            $roleBtn = ucfirst($ar['role']);
                            $unitBtn = htmlspecialchars($ar['unit']);
                            $akses[] =
                                $roleBtn .
                                " ($unitBtn) " .
                                "<button type='button' class='btn btn-sm btn-danger ms-1'
                                    data-bs-toggle='modal'
                                    data-bs-target='#hapusAksesModal'
                                    onclick=\"setHapusAksesModal('{$username}', '{$ar['role']}', '{$ar['unit']}')\"
                                    title='Hapus Akses'>
                                        <i class='fas fa-times'></i>
                                </button>";
                        }
                        $stmtAkses->close();
                        $akses_text = empty($akses)
                            ? '-'
                            : implode('<br>', $akses);
                        ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($nama) ?></td>
                        <td><?= htmlspecialchars($username) ?></td>
                        <td><?= htmlspecialchars($unit) ?></td>
                        <td><?= $akses_text ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-secondary mb-1" data-bs-toggle="modal" data-bs-target="#tambahAksesModal"
                                onclick="setTambahAksesModal('<?= $username ?>')">
                                <i class="fas fa-user-shield"></i> Tambah Akses
                            </button>
                            <button type="button" class="btn btn-sm btn-warning mb-1" data-bs-toggle="modal"
                                data-bs-target="#editPetugasModal"
                                onclick="setEditModalData('<?= $id ?>', '<?= htmlspecialchars(
    $nama,
    ENT_QUOTES
) ?>', '<?= $username ?>', '<?= $unit ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-danger mb-1" data-bs-toggle="modal"
                                data-bs-target="#deleteConfirmationModal"
                                onclick="setDeleteModalData('<?= $id ?>', '<?= htmlspecialchars(
    $nama,
    ENT_QUOTES
) ?>')">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </td>
                    </tr>
                    <?php
                    endwhile;
                    ?>
                </tbody>
            </table>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <p>Total Data: <strong><?= $total_data ?></strong></p>
                <nav>
                    <ul class="pagination">
                        <li class="page-item <?= $halaman <= 1
                            ? 'disabled'
                            : '' ?>">
                            <a class="page-link" href="?halaman=<?= $halaman -
                                1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $total_halaman; $p++): ?>
                            <li class="page-item <?= $p == $halaman
                                ? 'active'
                                : '' ?>">
                                <a class="page-link" href="?halaman=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $halaman >= $total_halaman
                            ? 'disabled'
                            : '' ?>">
                            <a class="page-link" href="?halaman=<?= $halaman +
                                1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Akses -->
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

    <!-- Modal-modal lain (Tambah/Edit/Hapus Petugas + Hapus Akses) -->
    <?php include 'petugas_modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setEditModalData(id, nama, username, unit) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_unit').value = unit;
        }
        function setDeleteModalData(id, nama) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_nama').textContent = nama;
        }
        function setTambahAksesModal(username) {
            document.getElementById('akses_username').value = username;
        }
        function setHapusAksesModal(username, role, unit) {
            document.getElementById('hapus_akses_username').value = username;
            document.getElementById('hapus_akses_role').value = role;
            document.getElementById('hapus_akses_unit').value = unit;
            document.getElementById('hapus_akses_label').textContent =
                role.charAt(0).toUpperCase() + role.slice(1) + ' (' + unit + ') untuk ' + username;
        }
    </script>
</body>
</html>
