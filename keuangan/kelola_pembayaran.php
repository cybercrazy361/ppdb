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
            'id'   => $row['id'],
            'nama' => $row['nama']
        ];
    }
    $stmt_jenis->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Ambil parameter pencarian
$search_no_formulir = isset($_GET['search_no_formulir']) ? trim($_GET['search_no_formulir']) : '';
$search_nama        = isset($_GET['search_nama'])        ? trim($_GET['search_nama'])        : '';

// Pagination setup
$limit = 5;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Build dynamic query
$query_params = [$unit_petugas];
$param_types  = 's';
$where        = "WHERE siswa.unit = ?";

if ($search_no_formulir !== '') {
    $where .= " AND siswa.no_formulir LIKE ?";
    $param_types .= 's';
    $query_params[] = "%{$search_no_formulir}%";
}
if ($search_nama !== '') {
    $where .= " AND siswa.nama LIKE ?";
    $param_types .= 's';
    $query_params[] = "%{$search_nama}%";
}

// Hitung total
$total_sql   = "SELECT COUNT(*) AS total FROM pembayaran
                INNER JOIN siswa ON pembayaran.no_formulir = siswa.no_formulir
                {$where}";
$stmt_total  = $conn->prepare($total_sql);
$stmt_total->bind_param($param_types, ...$query_params);
$stmt_total->execute();
$total        = $stmt_total->get_result()->fetch_assoc()['total'];
$total_pages  = ceil($total / $limit);
$stmt_total->close();

// Ambil data
$select_sql = "SELECT 
    pembayaran.id AS pembayaran_id,
    siswa.no_formulir, siswa.nama, siswa.unit,
    pembayaran.jumlah, pembayaran.metode_pembayaran,
    pembayaran.tahun_pelajaran, pembayaran.tanggal_pembayaran,
    pembayaran.keterangan
    FROM pembayaran
    INNER JOIN siswa ON pembayaran.no_formulir = siswa.no_formulir
    {$where}
    ORDER BY pembayaran.tanggal_pembayaran DESC, pembayaran.id DESC
    LIMIT ?, ?";
$stmt = $conn->prepare($select_sql);
$param_types_limit = $param_types . 'ii';
$stmt->bind_param($param_types_limit, ...array_merge($query_params, [$start, $limit]));
$stmt->execute();
$result = $stmt->get_result();

$pembayaran_data = [];
while ($row = $result->fetch_assoc()) {
    $pembayaran_data[$row['pembayaran_id']] = array_merge($row, ['details'=>[]]);
}
$stmt->close();

// Ambil detail
if (!empty($pembayaran_data)) {
    $ids = array_keys($pembayaran_data);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $types_detail = str_repeat('i', count($ids));
    $stmt_d = $conn->prepare("
        SELECT pd.pembayaran_id, jp.nama AS jenis_pembayaran_nama,
               pd.jumlah AS detail_jumlah, pd.bulan, pd.status_pembayaran
          FROM pembayaran_detail pd
          JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
         WHERE pd.pembayaran_id IN ($ph)
         ORDER BY pd.bulan ASC
    ");
    $stmt_d->bind_param($types_detail, ...$ids);
    $stmt_d->execute();
    $res_d = $stmt_d->get_result();
    while ($d = $res_d->fetch_assoc()) {
        $pembayaran_data[$d['pembayaran_id']]['details'][] = $d;
    }
    $stmt_d->close();
}

// Fetch siswa list untuk autocomplete
$siswa_list = [];
$stmt_s = $conn->prepare("SELECT no_formulir, nama FROM siswa WHERE unit = ? ORDER BY nama ASC");
$stmt_s->bind_param('s', $unit_petugas);
$stmt_s->execute();
$res_s = $stmt_s->get_result();
while ($s = $res_s->fetch_assoc()) {
    $siswa_list[] = $s;
}
$stmt_s->close();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/kelola_pembayaran_styles.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content p-4">
      <!-- Topbar -->
      <nav class="navbar navbar-expand navbar-light bg-white mb-4 shadow-sm">
        <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
          <i class="fas fa-bars"></i>
        </button>
        <ul class="navbar-nav ms-auto align-items-center">
          <li class="nav-item">
            <a class="nav-link text-secondary" href="#">
              <span class="me-2"><?= htmlspecialchars($_SESSION['nama']); ?></span>
              <i class="fas fa-user-circle fa-lg"></i>
            </a>
          </li>
        </ul>
      </nav>

      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1 class="dashboard-title">Kelola Pembayaran</h1>
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahPembayaran">
            <i class="fas fa-plus-circle"></i> Tambah Pembayaran
          </button>
        </div>

        <!-- Filter -->
        <div class="card mb-4 shadow-sm search-card">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Pembayaran</h5>
          </div>
          <div class="card-body">
            <form class="row g-3" method="GET" action="">
              <div class="col-md-4">
                <label for="search_no_formulir" class="form-label">No. Formulir</label>
                <input type="text" id="search_no_formulir" name="search_no_formulir"
                       class="form-control" placeholder="Cari No. Formulir"
                       value="<?= htmlspecialchars($search_no_formulir); ?>">
              </div>
              <div class="col-md-4">
                <label for="search_nama" class="form-label">Nama Siswa</label>
                <input type="text" id="search_nama" name="search_nama"
                       class="form-control" placeholder="Cari Nama Siswa"
                       value="<?= htmlspecialchars($search_nama); ?>">
              </div>
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                  <i class="fas fa-search"></i> Cari
                </button>
                <a href="kelola_pembayaran.php" class="btn btn-outline-secondary">
                  <i class="fas fa-undo"></i> Reset
                </a>
              </div>
            </form>
          </div>
        </div>

        <!-- Table -->
        <div class="card mb-4 shadow-sm">
          <div class="card-body p-0">
            <?php if (!$pembayaran_data): ?>
              <div class="alert alert-warning mb-0">Tidak ada data pembayaran.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>No Formulir</th>
                      <th>Nama</th>
                      <th>Unit</th>
                      <th>Tahun</th>
                      <th>Jumlah</th>
                      <th>Metode</th>
                      <th>Tanggal</th>
                      <th>Keterangan</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php $no = $start + 1;
                  foreach ($pembayaran_data as $id => $p): ?>
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
                        <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $id; ?>">
                          <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $id; ?>">
                          <i class="fas fa-trash-alt"></i>
                        </button>
                        <a href="cetak_pembayaran.php?id=<?= $id; ?>" target="_blank" class="btn btn-sm btn-info">
                          <i class="fas fa-print"></i>
                        </a>
                      </td>
                    </tr>
                    <?php if ($p['details']): ?>
                      <tr class="table-detail-row">
                        <td colspan="10">
                          <table class="table table-sm mb-0">
                            <thead>
                              <tr>
                                <th>Jenis</th><th>Jumlah</th><th>Bulan</th><th>Status</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($p['details'] as $d): ?>
                                <tr>
                                  <td><?= htmlspecialchars($d['jenis_pembayaran_nama']); ?></td>
                                  <td><?= number_format($d['detail_jumlah'],0,',','.'); ?></td>
                                  <td><?= htmlspecialchars($d['bulan']); ?></td>
                                  <td><?= htmlspecialchars($d['status_pembayaran']); ?></td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
              <nav>
                <ul class="pagination justify-content-center mb-0">
                  <?php
                  function pageLink($p,$params){ $params['page']=$p; return '?'.http_build_query($params); }
                  $baseParams = ['search_no_formulir'=>$search_no_formulir,'search_nama'=>$search_nama];
                  if ($page>1): ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= pageLink($page-1,$baseParams) ?>">&laquo;</a>
                    </li>
                  <?php endif;
                  $start_link = max(1,$page-2);
                  $end_link   = min($total_pages,$start_link+4);
                  if ($end_link - $start_link < 4) {
                    $start_link = max(1,$end_link-4);
                  }
                  for($i=$start_link;$i<=$end_link;$i++): ?>
                    <li class="page-item <?= $i==$page?'active':'' ?>">
                      <a class="page-link" href="<?= pageLink($i,$baseParams) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor;
                  if ($page<$total_pages): ?>
                    <li class="page-item">
                      <a class="page-link" href="<?= pageLink($page+1,$baseParams) ?>">&raquo;</a>
                    </li>
                  <?php endif; ?>
                </ul>
              </nav>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>

    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
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
          <div id="form-errors" class="alert alert-danger" style="display: none;"></div>
          <!-- Form fields seperti sebelumnya… -->
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
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editPembayaranForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEditPembayaranLabel">Edit Pembayaran</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="edit-form-errors" class="alert alert-danger" style="display: none;"></div>
                        <input type="hidden" id="editPembayaranId" name="pembayaran_id">
                        <div class="mb-3">
                            <label for="edit_no_formulir" class="form-label">No Formulir</label>
                            <input type="text" name="edit_no_formulir" id="edit_no_formulir" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nama" class="form-label">Nama Siswa</label>
                            <input type="text" name="edit_nama" id="edit_nama" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="edit_tahun_pelajaran" class="form-label">Tahun Pelajaran</label>
                            <input type="text" name="edit_tahun_pelajaran" id="edit_tahun_pelajaran" class="form-control" placeholder="Contoh: 2024/2025" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_metode_pembayaran" class="form-label">Metode Pembayaran</label>
                            <select name="edit_metode_pembayaran" id="edit_metode_pembayaran" class="form-select" required>
                                <option value="" disabled>Pilih Metode Pembayaran</option>
                                <option value="Cash">Cash</option>
                                <option value="Transfer">Transfer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Pembayaran</label>
                            <div id="edit-payment-wrapper"></div>
                            <button type="button" id="add-edit-payment-btn" class="btn btn-info mt-2"><i class="fas fa-plus"></i> Tambah Jenis Pembayaran</button>
                        </div>
                        <div class="mb-3">
                            <label for="edit_keterangan" class="form-label">Keterangan</label>
                            <textarea name="edit_keterangan" id="edit_keterangan" class="form-control" placeholder="Opsional"></textarea>
                        </div>
                        <input type="hidden" id="editCsrfToken" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
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
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script src="../assets/js/kelola_pembayaran.js" defer></script>
</body>
</html>
