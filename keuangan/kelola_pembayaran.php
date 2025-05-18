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
$stmt_tahun = $conn->prepare("SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC");
if ($stmt_tahun) {
    $stmt_tahun->execute();
    $result_tahun = $stmt_tahun->get_result();
    while ($row = $result_tahun->fetch_assoc()) {
        $tahun_pelajaran_list[] = $row['tahun'];
    }
    $stmt_tahun->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Fetch jenis_pembayaran_list sesuai dengan unit petugas
$jenis_pembayaran_list = [];
$stmt_jenis = $conn->prepare("SELECT id, nama FROM jenis_pembayaran WHERE unit = ? ORDER BY nama ASC");
if ($stmt_jenis) {
    $stmt_jenis->bind_param("s", $unit_petugas);
    $stmt_jenis->execute();
    $result_jenis = $stmt_jenis->get_result();
    while ($row = $result_jenis->fetch_assoc()) {
        $jenis_pembayaran_list[] = [
            'id' => $row['id'],
            'nama' => $row['nama']
        ];
    }
    $stmt_jenis->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Ambil parameter pencarian
$search_no_formulir = isset($_GET['search_no_formulir']) ? trim($_GET['search_no_formulir']) : '';
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
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
    $query .= " AND siswa.no_formulir LIKE ?";
    $param_types .= 's';
    $query_params[] = '%' . $search_no_formulir . '%';
}
if ($search_nama !== '') {
    $query .= " AND siswa.nama LIKE ?";
    $param_types .= 's';
    $query_params[] = '%' . $search_nama . '%';
}

// Hitung total pembayaran
$total_query = "SELECT COUNT(*) AS total " . $query;
$stmt_total = $conn->prepare($total_query);
if ($stmt_total) {
    $stmt_total->bind_param($param_types, ...$query_params);
    $stmt_total->execute();
    $total_result = $stmt_total->get_result();
    $total = $total_result->fetch_assoc()['total'];
    $total_pages = ceil($total / $limit);
    $stmt_total->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Ambil data pembayaran dengan limit dan offset
$select_query = "SELECT 
                    pembayaran.id AS pembayaran_id,
                    siswa.no_formulir,
                    siswa.nama,
                    siswa.unit,
                    pembayaran.jumlah,
                    pembayaran.metode_pembayaran,
                    pembayaran.tahun_pelajaran,
                    pembayaran.tanggal_pembayaran,
                    pembayaran.keterangan
                 " . $query . "
                 ORDER BY pembayaran.tanggal_pembayaran DESC, pembayaran.id DESC
                 LIMIT ?, ?";
$param_types_limit = $param_types . 'ii';
$query_params_limit = array_merge($query_params, [$start, $limit]);
$stmt = $conn->prepare($select_query);
if ($stmt) {
    $stmt->bind_param($param_types_limit, ...$query_params_limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $pembayaran_data = [];
    while ($row = $result->fetch_assoc()) {
        $id = $row['pembayaran_id'];
        $pembayaran_data[$id] = [
            'no_formulir' => $row['no_formulir'],
            'nama' => $row['nama'],
            'unit' => $row['unit'],
            'jumlah' => $row['jumlah'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'tahun_pelajaran' => $row['tahun_pelajaran'],
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'keterangan' => $row['keterangan'],
            'details' => []
        ];
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Ambil detail pembayaran jika ada
if (!empty($pembayaran_data)) {
    $ids = array_keys($pembayaran_data);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt_det = $conn->prepare("
        SELECT pd.pembayaran_id, jp.nama AS jenis, pd.jumlah AS jumlah_detail, pd.bulan, pd.status_pembayaran
        FROM pembayaran_detail pd
        JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
        WHERE pd.pembayaran_id IN ($ph)
        ORDER BY pd.bulan ASC
    ");
    if ($stmt_det) {
        $types = str_repeat('i', count($ids));
        $stmt_det->bind_param($types, ...$ids);
        $stmt_det->execute();
        $res_det = $stmt_det->get_result();
        while ($d = $res_det->fetch_assoc()) {
            $pid = $d['pembayaran_id'];
            $pembayaran_data[$pid]['details'][] = [
                'jenis' => $d['jenis'],
                'jumlah' => $d['jumlah_detail'],
                'bulan' => $d['bulan'],
                'status' => $d['status_pembayaran']
            ];
        }
        $stmt_det->close();
    } else {
        die("Error preparing detail statement: " . $conn->error);
    }
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
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/kelola_pembayaran_styles.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <script>
        var jenisPembayaranList = <?= json_encode($jenis_pembayaran_list, JSON_NUMERIC_CHECK); ?>;
        console.log('jenisPembayaranList:', jenisPembayaranList);
    </script>
    <script src="../assets/js/kelola_pembayaran.js" defer></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link"><?= htmlspecialchars($_SESSION['nama']); ?></span>
                </li>
            </ul>
        </nav>
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-4">
                <h1 class="dashboard-title">Kelola Pembayaran</h1>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahPembayaran">
                    <i class="fas fa-plus me-1"></i>Tambah Pembayaran
                </button>
            </div>
            <div class="card mb-4 search-card">
                <div class="card-header"><h5>Filter Pencarian</h5></div>
                <div class="card-body">
                    <form class="row g-3" method="GET">
                        <div class="col-md-4">
                            <label class="form-label" for="search_no_formulir">No Formulir</label>
                            <input type="text" id="search_no_formulir" name="search_no_formulir"
                                   class="form-control" value="<?= htmlspecialchars($search_no_formulir); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="search_nama">Nama Siswa</label>
                            <input type="text" id="search_nama" name="search_nama"
                                   class="form-control" value="<?= htmlspecialchars($search_nama); ?>">
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
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th><th>Formulir</th><th>Nama</th><th>Unit</th>
                                        <th>Tahun</th><th>Jumlah</th><th>Metode</th><th>Tanggal</th>
                                        <th>Keterangan</th><th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no=$start+1; foreach($pembayaran_data as $id=>$p): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($p['no_formulir']); ?></td>
                                        <td><?= htmlspecialchars($p['nama']); ?></td>
                                        <td><?= htmlspecialchars($p['unit']); ?></td>
                                        <td><?= htmlspecialchars($p['tahun_pelajaran']); ?></td>
                                        <td><?= number_format($p['jumlah'],0,',','.'); ?></td>
                                        <td><?= htmlspecialchars($p['metode_pembayaran']); ?></td>
                                        <td><?= htmlspecialchars($p['tanggal_pembayaran']); ?></td>
                                        <td><?= htmlspecialchars($p['keterangan']); ?></td>
                                        <td>
                                            <button class="btn btn-warning btn-sm edit-btn" data-id="<?= $id; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $id; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <a href="cetak_pembayaran.php?id=<?= $id; ?>" class="btn btn-info btn-sm" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr class="table-detail-row">
                                        <td colspan="10" class="p-0">
                                            <?php if (!empty($p['details'])): ?>
                                            <table class="table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Jenis</th><th>Jumlah</th><th>Bulan</th><th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($p['details'] as $d): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($d['jenis']); ?></td>
                                                        <td><?= number_format($d['jumlah'],0,',','.'); ?></td>
                                                        <td><?= htmlspecialchars($d['bulan']); ?></td>
                                                        <td><?= htmlspecialchars($d['status']); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($total_pages>1): ?>
                        <nav>
                            <ul class="pagination justify-content-center mt-3">
                                <?php
                                $params = $_GET;
                                for($i=1;$i<=$total_pages;$i++):
                                    $params['page']=$i;
                                    $url='?'.http_build_query($params);
                                ?>
                                <li class="page-item <?= $i==$page?'active':'';?>">
                                    <a class="page-link" href="<?=$url;?>"><?=$i;?></a>
                                </li>
                                <?php endfor;?>
                            </ul>
                        </nav>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; <?= date('Y'); ?> Sistem Keuangan PPDB
    </footer>

    <!-- Modal Tambah Pembayaran -->
    <div class="modal fade" id="modalTambahPembayaran" tabindex="-1" aria-labelledby="modalTambahPembayaranLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="tambahPembayaranForm">
            <div class="modal-header">
              <h5 class="modal-title" id="modalTambahPembayaranLabel">
                <i class="fas fa-coins me-2"></i>Tambah Pembayaran
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div id="form-errors" class="alert alert-danger d-none"></div>
              <div class="mb-3 position-relative">
                <label class="form-label" for="no_formulir">No Formulir</label>
                <input type="text" id="no_formulir" name="no_formulir" class="form-control" autocomplete="off" required>
                <div id="siswa-suggestions" class="dropdown-menu"></div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="nama">Nama Siswa</label>
                <input type="text" id="nama" name="nama" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label" for="tahun_pembayaran">Tahun Pelajaran</label>
                <select id="tahun_pelajaran" name="tahun_pelajaran" class="form-select" required>
                  <option value="" disabled selected>Pilih Tahun</option>
                  <?php foreach($tahun_pelajaran_list as $t): ?>
                    <option value="<?=htmlspecialchars($t);?>"><?=htmlspecialchars($t);?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label" for="metode_pembayaran">Metode Pembayaran</label>
                <select id="metode_pembayaran" name="metode_pembayaran" class="form-select" required>
                  <option value="" disabled selected>Pilih Metode</option>
                  <option value="Cash">Cash</option>
                  <option value="Transfer">Transfer</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Jenis Pembayaran</label>
                <div id="payment-wrapper"></div>
                <button type="button" id="add-payment-btn" class="btn btn-info mt-2">
                  <i class="fas fa-plus me-1"></i>Tambah Jenis Pembayaran
                </button>
              </div>
              <div class="mb-3">
                <label class="form-label" for="keterangan">Keterangan</label>
                <textarea id="keterangan" name="keterangan" class="form-control" rows="2"></textarea>
              </div>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>Batal
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Simpan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Edit Pembayaran -->
    <div class="modal fade" id="modalEditPembayaran" tabindex="-1" aria-labelledby="modalEditPembayaranLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="editPembayaranForm">
            <div class="modal-header">
              <h5 class="modal-title" id="modalEditPembayaranLabel">
                <i class="fas fa-edit me-2"></i>Edit Pembayaran
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div id="edit-form-errors" class="alert alert-danger d-none"></div>
              <input type="hidden" id="editPembayaranId" name="pembayaran_id">
              <div class="mb-3">
                <label class="form-label" for="edit_no_formulir">No Formulir</label>
                <input type="text" id="edit_no_formulir" name="edit_no_formulir" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label" for="edit_nama">Nama Siswa</label>
                <input type="text" id="edit_nama" name="edit_nama" class="form-control" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label" for="edit_tahun_pelajaran">Tahun Pelajaran</label>
                <input type="text" id="edit_tahun_pelajaran" name="tahun_pelajaran" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label" for="edit_metode_pembayaran">Metode Pembayaran</label>
                <select id="edit_metode_pembayaran" name="metode_pembayaran" class="form-select" required>
                  <option value="" disabled selected>Pilih Metode</option>
                  <option value="Cash">Cash</option>
                  <option value="Transfer">Transfer</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Jenis Pembayaran</label>
                <div id="edit-payment-wrapper"></div>
                <button type="button" id="add-edit-payment-btn" class="btn btn-info mt-2">
                  <i class="fas fa-plus me-1"></i>Tambah Jenis Pembayaran
                </button>
              </div>
              <div class="mb-3">
                <label class="form-label" for="edit_keterangan">Keterangan</label>
                <textarea id="edit_keterangan" name="keterangan" class="form-control" rows="2"></textarea>
              </div>
              <input type="hidden" id="editCsrfToken" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>Batal
              </button>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Perbarui
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Hapus Pembayaran -->
    <div class="modal fade" id="modalHapusPembayaran" tabindex="-1" aria-labelledby="modalHapusPembayaranLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="hapusPembayaranForm">
            <div class="modal-header">
              <h5 class="modal-title" id="modalHapusPembayaranLabel">
                <i class="fas fa-exclamation-triangle me-2 text-danger"></i>Konfirmasi Hapus
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <div id="hapus-form-errors" class="alert alert-danger d-none"></div>
              <p>Yakin menghapus pembayaran untuk:</p>
              <p><strong>Formulir:</strong> <span id="hapus_no_formulir"></span></p>
              <p><strong>Nama:</strong> <span id="hapus_nama"></span></p>
              <p><strong>Jumlah:</strong> <span id="hapus_jumlah"></span></p>
              <input type="hidden" id="hapusPembayaranId" name="pembayaran_id">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>Batal
              </button>
              <button type="submit" class="btn btn-danger">
                <i class="fas fa-trash-alt me-1"></i>Hapus
              </button>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
