<?php
// kelola_pembayaran.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../database_connection.php';

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit_petugas = $_SESSION['unit'];

// Fetch tahun_pelajaran list
$tahun_pelajaran_list = [];
$stmt_tahun = $conn->prepare(
    'SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC'
);
if ($stmt_tahun) {
    $stmt_tahun->execute();
    $result_tahun = $stmt_tahun->get_result();
    while ($row = $result_tahun->fetch_assoc()) {
        $tahun_pelajaran_list[] = $row['tahun'];
    }
    $stmt_tahun->close();
} else {
    die('Error preparing statement: ' . $conn->error);
}

// Fetch jenis_pembayaran_list sesuai dengan unit petugas
$jenis_pembayaran_list = [];
$stmt_jenis = $conn->prepare(
    'SELECT id, nama FROM jenis_pembayaran WHERE unit = ? ORDER BY nama ASC'
);
if ($stmt_jenis) {
    $stmt_jenis->bind_param('s', $unit_petugas);
    $stmt_jenis->execute();
    $result_jenis = $stmt_jenis->get_result();
    while ($row = $result_jenis->fetch_assoc()) {
        $jenis_pembayaran_list[] = [
            'id' => $row['id'],
            'nama' => $row['nama'],
        ];
    }
    $stmt_jenis->close();
} else {
    die('Error preparing statement: ' . $conn->error);
}

// Ambil parameter pencarian
$search_no_formulir = isset($_GET['search_no_formulir'])
    ? trim($_GET['search_no_formulir'])
    : '';
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$start = ($page - 1) * $limit;

// Initialize query parameters
$query_params = [];
$param_types = 's'; // 's' for $unit_petugas
$query = "FROM pembayaran 
          INNER JOIN siswa ON pembayaran.no_formulir = siswa.no_formulir
          WHERE siswa.unit = ?";
$query_params[] = $unit_petugas;

// Tambahkan filter pencarian jika ada
if ($search_no_formulir !== '') {
    $query .= ' AND siswa.no_formulir LIKE ?';
    $param_types .= 's';
    $query_params[] = '%' . $search_no_formulir . '%';
}

if ($search_nama !== '') {
    $query .= ' AND siswa.nama LIKE ?';
    $param_types .= 's';
    $query_params[] = '%' . $search_nama . '%';
}

// Hitung total pembayaran
$total_query = 'SELECT COUNT(*) AS total ' . $query;
$stmt_total = $conn->prepare($total_query);
if ($stmt_total) {
    $stmt_total->bind_param($param_types, ...$query_params);
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total / $limit);
    $stmt_total->close();
} else {
    die('Error preparing statement: ' . $conn->error);
}

// Ambil data pembayaran dengan limit dan offset
$select_query =
    "SELECT 
                pembayaran.id AS pembayaran_id,
                siswa.no_formulir,
                siswa.nama,
                siswa.unit,
                pembayaran.jumlah,
                pembayaran.metode_pembayaran,
                pembayaran.tahun_pelajaran,
                pembayaran.tanggal_pembayaran,
                pembayaran.keterangan
              " .
    $query .
    "
              ORDER BY pembayaran.tanggal_pembayaran DESC, pembayaran.id DESC
              LIMIT ?, ?";

$param_types_limit = $param_types . 'ii'; // Tambahkan 'i' untuk $start dan $limit
$query_params_limit = array_merge($query_params, [$start, $limit]);

$stmt = $conn->prepare($select_query);
if ($stmt) {
    $stmt->bind_param($param_types_limit, ...$query_params_limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $pembayaran_data = [];
    while ($row = $result->fetch_assoc()) {
        $pembayaran_id = $row['pembayaran_id'];
        $pembayaran_data[$pembayaran_id] = [
            'no_formulir' => $row['no_formulir'],
            'nama' => $row['nama'],
            'unit' => $row['unit'],
            'jumlah' => $row['jumlah'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'tahun_pelajaran' => $row['tahun_pelajaran'],
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'keterangan' => $row['keterangan'],
            'details' => [],
        ];
    }
    $stmt->close();
} else {
    die('Error preparing statement: ' . $conn->error);
}

// Jika ada pembayaran, ambil detailnya
if (!empty($pembayaran_data)) {
    $pembayaran_ids = array_keys($pembayaran_data);
    $placeholders = implode(',', array_fill(0, count($pembayaran_ids), '?'));
    $stmt_detail = $conn->prepare("
    SELECT 
        pembayaran_detail.pembayaran_id,
        jenis_pembayaran.nama AS jenis_pembayaran_nama,
        pembayaran_detail.jumlah AS detail_jumlah,
        pembayaran_detail.bulan,
        pembayaran_detail.status_pembayaran,
        pembayaran_detail.cashback
    FROM pembayaran_detail
    INNER JOIN jenis_pembayaran ON pembayaran_detail.jenis_pembayaran_id = jenis_pembayaran.id
    WHERE pembayaran_detail.pembayaran_id IN ($placeholders)
    ORDER BY pembayaran_detail.bulan ASC
");

    if ($stmt_detail) {
        // Buat tipe parameter untuk pembayaran_id
        $types_detail = str_repeat('i', count($pembayaran_ids));
        $stmt_detail->bind_param($types_detail, ...$pembayaran_ids);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        while ($row = $result_detail->fetch_assoc()) {
            $pembayaran_id = $row['pembayaran_id'];
            if (isset($pembayaran_data[$pembayaran_id])) {
                $pembayaran_data[$pembayaran_id]['details'][] = [
                    'jenis_pembayaran_nama' => $row['jenis_pembayaran_nama'],
                    'jumlah' => $row['detail_jumlah'],
                    'bulan' => $row['bulan'] ?? '',
                    'status_pembayaran' => $row['status_pembayaran'] ?? '',
                    'cashback' => $row['cashback'] ?? 0,
                ];
            }
        }
        $stmt_detail->close();
    } else {
        die('Error preparing detail statement: ' . $conn->error);
    }
}

// Fetch list of siswa untuk autocomplete (opsional)
$siswa_list = [];
$stmt_siswa = $conn->prepare(
    'SELECT no_formulir, nama FROM siswa WHERE unit = ? ORDER BY nama ASC'
);
if ($stmt_siswa) {
    $stmt_siswa->bind_param('s', $unit_petugas);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    while ($row = $result_siswa->fetch_assoc()) {
        $siswa_list[] = $row;
    }
    $stmt_siswa->close();
} else {
    die('Error preparing statement: ' . $conn->error);
}

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
    <title>Kelola Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/kelola_pembayaran_styles.css">
    <script>
        var jenisPembayaranList = <?php echo json_encode(
            $jenis_pembayaran_list
        ); ?>;
        console.log('jenisPembayaranList:', jenisPembayaranList);
    </script>
    <script src="../assets/js/kelola_pembayaran.js" defer></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link" href="#">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars(
                            $_SESSION['nama']
                        ); ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="dashboard-title">Kelola Pembayaran</h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahPembayaran">
                    <i class="fas fa-plus"></i> Tambah Pembayaran
                </button>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3" method="GET" action="kelola_pembayaran.php">
                        <div class="col-md-4">
                            <label for="search_no_formulir" class="form-label">Cari No Formulir</label>
                            <input type="text" class="form-control" id="search_no_formulir" name="search_no_formulir" placeholder="Masukkan No Formulir" value="<?= isset(
                                $_GET['search_no_formulir']
                            )
                                ? htmlspecialchars($_GET['search_no_formulir'])
                                : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="search_nama" class="form-label">Cari Nama Siswa</label>
                            <input type="text" class="form-control" id="search_nama" name="search_nama" placeholder="Masukkan Nama Siswa" value="<?= isset(
                                $_GET['search_nama']
                            )
                                ? htmlspecialchars($_GET['search_nama'])
                                : '' ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-search"></i> Cari</button>
                            <a href="kelola_pembayaran.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-body">
                    <?php if (empty($pembayaran_data)): ?>
                        <div class="alert alert-warning">Tidak ada data pembayaran ditemukan.</div>
                    <?php // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan

                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan
                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan

                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan
                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan

                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan
                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan

                        // Adjust start_link jika end_link mendekati total_pages
                        // Salin parameter pencarian
                        // Tampilkan pagination dengan rentang yang lebih baik
                        // Maksimal link pagination yang ditampilkan
                        // Adjust start_link jika end_link mendekati total_pages
                        else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-primary">
                                    <tr>
                                        <th>No</th>
                                        <th>No Formulir</th>
                                        <th>Nama</th>
                                        <th>Unit</th>
                                        <th>Tahun Pelajaran</th>
                                        <th>Jumlah</th>
                                        <th>Metode</th>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = $start + 1;
                                    foreach (
                                        $pembayaran_data
                                        as $pembayaran_id => $pembayaran
                                    ): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['no_formulir']
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['nama']
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['unit']
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['tahun_pelajaran']
                                            ) ?></td>
                                            <td><?= number_format(
                                                $pembayaran['jumlah'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['metode_pembayaran']
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran[
                                                    'tanggal_pembayaran'
                                                ]
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $pembayaran['keterangan']
                                            ) ?></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm edit-btn" data-id="<?= htmlspecialchars(
                                                    $pembayaran_id
                                                ) ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm delete-btn" data-id="<?= htmlspecialchars(
                                                    $pembayaran_id
                                                ) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <a href="cetak_pembayaran.php?id=<?= htmlspecialchars(
                                                    $pembayaran_id
                                                ) ?>" class="btn btn-primary btn-sm" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="10">
                                                <?php if (
                                                    !empty(
                                                        $pembayaran['details']
                                                    )
                                                ): ?>
                                                    <table class="table table-sm table-bordered">
                                                        <thead>
    <tr>
        <th>Jenis Pembayaran</th>
        <th>Jumlah</th>
        <th>Bulan</th>
        <th>Status Pembayaran</th>
        <th>Cashback</th> <!-- Tambahan -->
    </tr>
</thead>
<tbody>
<?php foreach ($pembayaran['details'] as $detail): ?>
    <tr>
        <td><?= htmlspecialchars($detail['jenis_pembayaran_nama']) ?></td>
        <td><?= number_format($detail['jumlah'], 0, ',', '.') ?></td>
        <td><?= htmlspecialchars($detail['bulan'] ?? '') ?></td>
        <td><?= htmlspecialchars($detail['status_pembayaran'] ?? '') ?></td>
        <td>
    <?= ($detail['cashback'] ?? 0) > 0
        ? number_format($detail['cashback'], 0, ',', '.')
        : '-' ?>
</td>

    </tr>
<?php endforeach; ?>
</tbody>

                                                    </table>
                                                <?php else: ?>
                                                    <p>Tidak ada rincian pembayaran.</p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php
                                    $query_params_pagination = $query_params;
                                    function buildPageUrl($page, $params)
                                    {
                                        $params['page'] = $page;
                                        return '?' . http_build_query($params);
                                    }
                                    ?>

                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $page - 1,
                                                $query_params_pagination
                                            ) ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    $max_links = 5;
                                    $start_link = max(
                                        1,
                                        $page - floor($max_links / 2)
                                    );
                                    $end_link = min(
                                        $total_pages,
                                        $start_link + $max_links - 1
                                    );

                                    if (
                                        $end_link - $start_link <
                                        $max_links - 1
                                    ) {
                                        $start_link = max(
                                            1,
                                            $end_link - $max_links + 1
                                        );
                                    }

                                    for (
                                        $i = $start_link;
                                        $i <= $end_link;
                                        $i++
                                    ): ?>
                                        <li class="page-item <?= $i == $page
                                            ? 'active'
                                            : '' ?>">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $i,
                                                $query_params_pagination
                                            ) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor;
                                    ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $page + 1,
                                                $query_params_pagination
                                            ) ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>

    <!-- Modal Tambah Pembayaran -->
    <div class="modal fade" id="modalTambahPembayaran" tabindex="-1" aria-labelledby="modalTambahPembayaranLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="tambahPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTambahPembayaranLabel">Tambah Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="form-errors" class="alert alert-danger" style="display: none;"></div>
                        <div class="mb-3 position-relative">
                            <label for="no_formulir" class="form-label">No Formulir</label>
                            <input type="text" name="no_formulir" id="no_formulir" class="form-control" placeholder="Masukkan No Formulir" autocomplete="off" required>
                            <div id="siswa-suggestions" class="dropdown-menu" style="display: none;"></div>
                        </div>
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Siswa</label>
                            <input type="text" name="nama" id="nama" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="tahun_pelajaran" class="form-label">Tahun Pelajaran</label>
                            <select name="tahun_pelajaran" id="tahun_pelajaran" class="form-select" required>
                                <option value="" disabled selected>Pilih Tahun Pelajaran</option>
                                <?php foreach (
                                    $tahun_pelajaran_list
                                    as $tahun
                                ): ?>
                                    <option value="<?= htmlspecialchars(
                                        $tahun
                                    ) ?>"><?= htmlspecialchars(
    $tahun
) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="metode_pembayaran" class="form-label">Metode Pembayaran</label>
                            <select name="metode_pembayaran" id="metode_pembayaran" class="form-select" required>
                                <option value="" disabled selected>Pilih Metode Pembayaran</option>
                                <option value="Cash">Cash</option>
                                <option value="Transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Pembayaran</label>
                            <div id="payment-wrapper"></div>
                            <button type="button" id="add-payment-btn" class="btn btn-info mt-2"><i class="fas fa-plus"></i> Tambah Jenis Pembayaran</button>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" placeholder="Opsional"></textarea>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                            $csrf_token
                        ) ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Modal Edit Pembayaran -->
<div class="modal fade" id="modalEditPembayaran" tabindex="-1" aria-labelledby="modalEditPembayaranLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editPembayaranForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditPembayaranLabel">Edit Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="edit-form-errors" class="alert alert-danger" style="display: none;"></div>
                    <input type="hidden" id="editPembayaranId" name="pembayaran_id">
                    <input type="hidden" id="editCsrfToken" name="csrf_token" value="<?= htmlspecialchars(
                        $csrf_token
                    ) ?>">
                    <div class="mb-3">
                        <label for="edit_no_formulir" class="form-label">No Formulir</label>
                        <input type="text" id="edit_no_formulir" name="edit_no_formulir" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama" class="form-label">Nama Siswa</label>
                        <input type="text" id="edit_nama" name="edit_nama" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tahun_pelajaran" class="form-label">Tahun Pelajaran</label>
                        <input type="text" id="edit_tahun_pelajaran" name="edit_tahun_pelajaran" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_metode_pembayaran" class="form-label">Metode Pembayaran</label>
                        <select id="edit_metode_pembayaran" name="edit_metode_pembayaran" class="form-select" required>
                            <option value="" disabled>Pilih Metode Pembayaran</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Pembayaran</label>
                        <div id="edit-payment-wrapper"></div>
                        <button type="button" id="add-edit-payment-btn" class="btn btn-info mt-2">
                            <i class="fas fa-plus"></i> Tambah Jenis Pembayaran
                        </button>
                    </div>
                    <div class="mb-3">
                        <label for="edit_keterangan" class="form-label">Keterangan</label>
                        <textarea id="edit_keterangan" name="edit_keterangan" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="modalHapusPembayaran" tabindex="-1" aria-labelledby="modalHapusPembayaranLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="hapusPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalHapusPembayaranLabel">Konfirmasi Hapus Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="hapus-form-errors" class="alert alert-danger" style="display: none;"></div>
                        <p>Apakah Anda yakin ingin menghapus pembayaran ini?</p>
                        <p><strong>No Formulir:</strong> <span id="hapus_no_formulir"></span></p>
                        <p><strong>Nama:</strong> <span id="hapus_nama"></span></p>
                        <p><strong>Jumlah:</strong> <span id="hapus_jumlah"></span></p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" id="hapusPembayaranId" name="pembayaran_id">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                            $csrf_token
                        ) ?>">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Library JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
