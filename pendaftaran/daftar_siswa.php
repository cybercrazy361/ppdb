<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../database_connection.php';

// Validasi akses
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit'] ?? '';
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page > 1) ? ($page * $limit) - $limit : 0;

// Ambil kata kunci pencarian
$search = trim($_GET['q'] ?? '');

// Untuk filter query SQL
$searchSql = '';
$searchParam = '';
if ($search !== '') {
    $searchSql = "AND (
        s.no_formulir LIKE ? OR
        s.no_invoice LIKE ? OR
        s.nama LIKE ?
    )";
    $searchParam = '%' . $search . '%';
}

// Hitung total siswa (untuk paginasi)
$sqlCount = "SELECT COUNT(*) AS total FROM siswa s WHERE s.unit = ? $searchSql";
$stmtTotal = $conn->prepare($sqlCount);
if ($search !== '') {
    $stmtTotal->bind_param('ssss', $unit, $searchParam, $searchParam, $searchParam);
} else {
    $stmtTotal->bind_param('s', $unit);
}
$stmtTotal->execute();
$totalResult = $stmtTotal->get_result();
$totalSiswa = $totalResult->fetch_assoc()['total'] ?? 0;
$stmtTotal->close();

$uang_pangkal_id = 1;
$spp_id = 2;

// Ambil data siswa dengan status pembayaran terbaru
$query = "
SELECT 
    s.*, 
    cp.status AS status_pendaftaran,
    COALESCE(
        (SELECT p.metode_pembayaran 
         FROM pembayaran p 
         WHERE p.siswa_id = s.id 
         ORDER BY p.tanggal_pembayaran DESC 
         LIMIT 1),
        'Belum Ada'
    ) AS metode_pembayaran,
CASE
  WHEN 
    (SELECT COUNT(*) FROM pembayaran_detail pd1 
        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
        WHERE p1.siswa_id = s.id 
          AND pd1.jenis_pembayaran_id = $uang_pangkal_id
          AND pd1.status_pembayaran = 'Lunas'
    ) > 0
  AND
    (SELECT COUNT(*) FROM pembayaran_detail pd2 
        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
        WHERE p2.siswa_id = s.id 
          AND pd2.jenis_pembayaran_id = $spp_id
          AND pd2.bulan = 'Juli'
          AND pd2.status_pembayaran = 'Lunas'
    ) > 0
  THEN 'Lunas'
  WHEN 
    (
      (SELECT COUNT(*) FROM pembayaran_detail pd1 
          JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
          WHERE p1.siswa_id = s.id 
            AND pd1.jenis_pembayaran_id = $uang_pangkal_id
      ) > 0
      OR
      (SELECT COUNT(*) FROM pembayaran_detail pd2 
          JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
          WHERE p2.siswa_id = s.id 
            AND pd2.jenis_pembayaran_id = $spp_id
            AND pd2.bulan = 'Juli'
            AND pd2.status_pembayaran = 'Lunas'
      ) > 0
    )
  THEN 'Angsuran'
  ELSE 'Belum Bayar'
END AS status_pembayaran
FROM siswa s
LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
WHERE s.unit = ?
$searchSql
ORDER BY s.id DESC
LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
if ($search !== '') {
    $stmt->bind_param('ssssii', $unit, $searchParam, $searchParam, $searchParam, $limit, $offset);
} else {
    $stmt->bind_param('sii', $unit, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$totalPages = ceil($totalSiswa / $limit);

function formatTanggalIndonesia($tanggal) {
    if (!$tanggal || $tanggal === '0000-00-00') return '-';
    $bulan = [
        'January' => 'Januari','February' => 'Februari','March' => 'Maret',
        'April'   => 'April',  'May'      => 'Mei',     'June'  => 'Juni',
        'July'    => 'Juli',   'August'   => 'Agustus', 'September' => 'September',
        'October' => 'Oktober','November' => 'November','December'  => 'Desember'
    ];
    $d = date('d', strtotime($tanggal));
    $m = $bulan[date('F', strtotime($tanggal))] ?? date('F', strtotime($tanggal));
    $y = date('Y', strtotime($tanggal));
    return "$d $m $y";
}

function getStatusPembayaranLabel($status) {
    switch (strtolower($status)) {
        case 'lunas':
            return '<span class="status-lunas"><i class="fas fa-check-circle"></i> Lunas</span>';
        case 'angsuran':
            return '<span class="status-cicilan"><i class="fas fa-exclamation-circle"></i> Angsuran</span>';
        default:
            return '<span class="status-pending"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Siswa <?= htmlspecialchars($unit) ?> – SPMB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/sidebar_pendaftaran_styles.css">
  <link rel="stylesheet" href="../assets/css/daftar_siswa_styles.css">
  <style>
/* Hide No Registrasi, No Formulir, Tempat/Tgl Lahir, dan Alamat */
th.no-regis-col, td.no-regis-col,
th.ttl-col, td.ttl-col,
th.alamat-col, td.alamat-col {
  display: none !important;
}
</style>

</head>
<body>

  <?php $active = 'progres'; include 'sidebar_pendaftaran.php'; ?>

  <div class="main">
    <header class="navbar">
      <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="title">Daftar Siswa <?= htmlspecialchars($unit) ?></div>
      <div class="user-menu">
        <small>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? '') ?></small>
        <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
      </div>
    </header>

    <?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    // Hapus setelah ditampilkan
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    endif;
    ?>

    <div class="container mt-1">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <form method="get" class="d-flex" style="gap:6px;">
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Cari No Formulir, No Invoice, Nama...">
          <button type="submit" class="btn btn-outline-secondary"><i class="fas fa-search"></i></button>
          <?php if($search): ?>
            <a href="<?= strtok($_SERVER["REQUEST_URI"],'?') ?>" class="btn btn-outline-danger" title="Reset Cari"><i class="fas fa-times"></i></a>
          <?php endif; ?>
        </form>
        <a href="cetak_daftar_siswa.php" target="_blank" class="btn btn-primary">
          <i class="fas fa-print"></i> Cetak Daftar Lengkap
        </a>
      </div>
      <div class="table-responsive">
        <div class="table-responsive">
  <table class="table table-hover table-bordered align-middle" style="min-width:1800px;">
    <thead>
      <tr>
        <th style="min-width:50px;">No</th>
        <th class="no-regis-col" style="min-width:120px;">No Registrasi</th>
        <th style="min-width:120px;">No Formulir</th>
        <th style="min-width:180px;">Nama</th>
        <th style="min-width:120px;">Jenis Kelamin</th>
        <th class="ttl-col" style="min-width:180px;">Tempat/Tgl Lahir</th>
        <th style="min-width:160px;">Asal Sekolah</th>
        <th class="alamat-col" style="min-width:220px;">Alamat</th>
        <th style="min-width:120px;">No HP</th>
        <th style="min-width:130px;">No HP Ortu</th>
        <th style="min-width:180px;">Progres Pembayaran</th>
        <th style="min-width:150px;">Metode Pembayaran</th>
        <th style="min-width:160px;">Tgl Pendaftaran</th>
        <th style="min-width:130px;">Status Pendaftaran</th>
        <th style="min-width:150px;">Aksi</th>
      </tr>
    </thead>
    <tbody>
            <?php if ($result->num_rows): $no = $offset + 1;
              while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td class="no-regis-col"><?= htmlspecialchars($row['no_registrasi'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['no_invoice']   ?? '') ?></td>
                  <td><?= htmlspecialchars($row['nama']          ?? '') ?></td>
                  <td><?= htmlspecialchars($row['jenis_kelamin'] ?? '') ?></td>
                  <td class="ttl-col">
                    <?= htmlspecialchars($row['tempat_lahir'] ?? '') ?>,
                    <?= formatTanggalIndonesia($row['tanggal_lahir'] ?? '') ?>
                  </td>
                  <td><?= htmlspecialchars($row['asal_sekolah']    ?? '') ?></td>
                  <td class="alamat-col"><?= htmlspecialchars($row['alamat'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['no_hp']            ?? '') ?></td>
                  <td><?= htmlspecialchars($row['no_hp_ortu']       ?? '') ?></td>
                  <td>
                    <?php
                      $statusPendaftaran = strtolower(trim($row['status_pendaftaran'] ?? ''));
                      if ($statusPendaftaran === 'ppdb bersama') {
                        echo '<span class="badge bg-info text-dark">PPDB Bersama</span>';
                      } else {
                        echo getStatusPembayaranLabel($row['status_pembayaran'] ?? '');
                      }
                    ?>
                  </td>
                  <td><?= htmlspecialchars($row['metode_pembayaran'] ?? '') ?></td>
                  <td><?= formatTanggalIndonesia($row['tanggal_pendaftaran'] ?? '') ?></td>
                  <td>
                      <?php
                        $statusPendaftaran = strtolower(trim($row['status_pendaftaran'] ?? ''));
                      if ($statusPendaftaran === 'ppdb bersama') {
                        echo '<span class="badge bg-info text-dark">PPDB Bersama</span>';
                      } elseif ($statusPendaftaran === 'menunggu proses') { // <--- Tambah ini
                        echo '<span class="badge bg-primary">Menunggu Proses</span>';
                      } elseif ($statusPendaftaran === 'sudah bayar') {
                        echo '<span class="badge bg-success">Sudah Bayar</span>';
                      } elseif ($statusPendaftaran === 'terverifikasi') {
                        echo '<span class="badge bg-success">Terverifikasi</span>';
                      } elseif ($statusPendaftaran === 'belum verifikasi') {
                        echo '<span class="badge bg-warning text-dark">Belum Verifikasi</span>';
                      } elseif ($statusPendaftaran === 'ditolak') {
                        echo '<span class="badge bg-danger">Ditolak</span>';
                      } elseif ($statusPendaftaran === '') {
                        echo '<span class="badge bg-secondary">-</span>';
                      } else {
                        echo '<span class="badge bg-secondary">'.htmlspecialchars($row['status_pendaftaran']).'</span>';
                      }
                      ?>
                  </td>
                  <td class="text-center">
                  <a href="print_siswa.php?id=<?= $row['id'] ?>"
                    class="btn btn-success btn-sm" target="_blank">
                    <i class="fas fa-print"></i> Print
                  </a>
                  <button class="btn btn-info btn-sm verifyBtn"
                          data-id="<?= $row['id'] ?>"
                          data-nama="<?= htmlspecialchars($row['nama'] ?? '') ?>"
                          data-no_formulir="<?= htmlspecialchars($row['no_formulir'] ?? '') ?>"
                          data-no_invoice="<?= htmlspecialchars($row['no_invoice'] ?? '') ?>"
                          data-jenis_kelamin="<?= htmlspecialchars($row['jenis_kelamin'] ?? '') ?>"
                          data-asal_sekolah="<?= htmlspecialchars($row['asal_sekolah'] ?? '') ?>"
                          data-no_hp="<?= htmlspecialchars($row['no_hp'] ?? '') ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat'] ?? '') ?>"
                          data-no_hp_ortu="<?= htmlspecialchars($row['no_hp_ortu'] ?? '') ?>"
                          data-bs-toggle="modal" data-bs-target="#verifyModal">
                    <i class="fas fa-check-double"></i> Verifikasi
                  </button>
                  <button class="btn btn-warning btn-sm editBtn"
                          data-id="<?= $row['id'] ?>"
                          data-no_formulir="<?= htmlspecialchars($row['no_formulir'] ?? '') ?>"
                          data-no_invoice="<?= htmlspecialchars($row['no_invoice'] ?? '') ?>"
                          data-nama="<?= htmlspecialchars($row['nama'] ?? '') ?>"
                          data-jenis_kelamin="<?= htmlspecialchars($row['jenis_kelamin'] ?? '') ?>"
                          data-tempat_lahir="<?= htmlspecialchars($row['tempat_lahir'] ?? '') ?>"
                          data-tanggal_lahir="<?= htmlspecialchars($row['tanggal_lahir'] ?? '') ?>"
                          data-asal_sekolah="<?= htmlspecialchars($row['asal_sekolah'] ?? '') ?>"
                          data-alamat="<?= htmlspecialchars($row['alamat'] ?? '') ?>"
                          data-no_hp="<?= htmlspecialchars($row['no_hp'] ?? '') ?>"
                          data-no_hp_ortu="<?= htmlspecialchars($row['no_hp_ortu'] ?? '') ?>"
                          data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <button class="btn btn-danger btn-sm deleteBtn"
                          data-id="<?= $row['id'] ?>"
                          data-nama="<?= htmlspecialchars($row['nama'] ?? '') ?>"
                          data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="fas fa-trash-alt"></i> Delete
                  </button>
                </td>
                </tr>
              <?php endwhile;
            else: ?>
              <tr><td colspan="14" class="text-center">Tidak ada data siswa.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
            <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">Previous</a>
          </li>
          <?php
          $max_links = 5;
          $start = max(1, $page - floor($max_links/2));
          $end = min($totalPages, $start + $max_links - 1);
          if ($end - $start < $max_links) {
            $start = max(1, $end - $max_links + 1);
          }
          for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= ($i === $page ? 'active' : '') ?>">
              <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
            <a class="page-link" href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>

  <!-- Modal Edit -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
      <form action="edit_siswa.php" method="POST">
        <input type="hidden" id="editId" name="id">
        <div class="modal-header">
          <h5 class="modal-title">Edit Data Siswa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">No Registrasi</label>
            <input type="text" class="form-control" id="editNoFormulir" name="no_formulir" required>
          </div>
          <div class="mb-3"><label class="form-label">No Formulir</label>
            <input type="text" class="form-control" id="editNoInvoice" name="no_invoice" required>
          </div>
          <div class="mb-3"><label class="form-label">Nama</label>
            <input type="text" class="form-control" id="editNama" name="nama" required>
          </div>
          <div class="mb-3"><label class="form-label">Jenis Kelamin</label>
            <select class="form-select" id="editJenisKelamin" name="jenis_kelamin" required>
              <option value="">-- Pilih --</option>
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>
          <div class="mb-3"><label class="form-label">Tempat Lahir</label>
            <input type="text" class="form-control" id="editTempatLahir" name="tempat_lahir" required>
          </div>
          <div class="mb-3"><label class="form-label">Tanggal Lahir</label>
            <input type="date" class="form-control" id="editTanggalLahir" name="tanggal_lahir" required>
          </div>
          <div class="mb-3"><label class="form-label">Asal Sekolah</label>
            <input type="text" class="form-control" id="editAsalSekolah" name="asal_sekolah" required>
          </div>
          <div class="mb-3"><label class="form-label">Alamat</label>
            <textarea class="form-control" id="editAlamat" name="alamat" required></textarea>
          </div>
          <div class="mb-3"><label class="form-label">No HP</label>
            <input type="text" class="form-control" id="editNoHp" name="no_hp" required>
          </div>
          <div class="mb-3"><label class="form-label">No HP Orang Tua</label>
            <input type="text" class="form-control" id="editNoHpOrtu" name="no_hp_ortu" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
      </form>
    </div></div>
  </div>

  <!-- Modal Delete -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
      <form action="delete_siswa.php" method="POST">
        <input type="hidden" id="deleteId" name="id">
        <div class="modal-header">
          <h5 class="modal-title">Hapus Data Siswa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Yakin ingin menghapus <strong id="deleteNama"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Hapus</button>
        </div>
      </form>
    </div></div>
  </div>

  <!-- Modal Verifikasi -->
<div class="modal fade" id="verifyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="verifyForm">
        <div class="modal-header">
          <h5 class="modal-title">Verifikasi Jenis Pembayaran</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="verifyId" name="id">
          <div class="mb-2"><b>Nama:</b> <span id="verifyNama"></span></div>
          <div class="mb-2"><b>No Registrasi Pendaftaran:</b> <span id="verifyNoFormulir"></span></div>
          <div class="mb-3">
            <label class="form-label">Pilih pembayaran yang akan diverifikasi:</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="Uang Pangkal" id="cbUangPangkal" name="jenis_bayar[]">
              <label class="form-check-label" for="cbUangPangkal">
                Uang Pangkal (<span class="nominal" id="uangPangkalNominal">Rp -</span>)
              </label>
              <input type="number" min="0" step="1000" class="form-control form-control-sm mt-1" id="uangPangkalInput" placeholder="Nominal" style="max-width:140px;display:inline-block;">
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" value="SPP" id="cbSPP" name="jenis_bayar[]">
              <label class="form-check-label" for="cbSPP">
                SPP (<span class="nominal" id="sppNominal">Rp -</span>)
              </label>
              <input type="number" min="0" step="1000" class="form-control form-control-sm mt-1" id="sppInput" placeholder="Nominal" style="max-width:140px;display:inline-block;">
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" value="Seragam" id="cbSeragam" name="jenis_bayar[]">
              <label class="form-check-label" for="cbSeragam">
                Seragam (<span class="nominal" id="seragamNominal">Rp -</span>)
              </label>
              <input type="number" min="0" step="1000" class="form-control form-control-sm mt-1" id="seragamInput" placeholder="Nominal" style="max-width:140px;display:inline-block;">
            </div>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" value="Kegiatan" id="cbKegiatan" name="jenis_bayar[]">
              <label class="form-check-label" for="cbKegiatan">
                Kegiatan (<span class="nominal" id="kegiatanNominal">Rp -</span>)
              </label>
              <input type="number" min="0" step="1000" class="form-control form-control-sm mt-1" id="kegiatanInput" placeholder="Nominal" style="max-width:140px;display:inline-block;">
            </div>
            <!-- Tambah jenis pembayaran lain jika perlu -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-success">Simpan Verifikasi</button>
        </div>
      </form>
    </div>
  </div>
</div>

  <script>
    // Edit button event listener
    document.querySelectorAll('.editBtn').forEach(button => {
      button.addEventListener('click', () => {
        document.getElementById('editId').value          = button.getAttribute('data-id');
        document.getElementById('editNoFormulir').value  = button.getAttribute('data-no_formulir');
        document.getElementById('editNoInvoice').value   = button.getAttribute('data-no_invoice');
        document.getElementById('editNama').value        = button.getAttribute('data-nama');
        document.getElementById('editJenisKelamin').value = button.getAttribute('data-jenis_kelamin');
        document.getElementById('editTempatLahir').value = button.getAttribute('data-tempat_lahir');
        document.getElementById('editTanggalLahir').value = button.getAttribute('data-tanggal_lahir');
        document.getElementById('editAsalSekolah').value = button.getAttribute('data-asal_sekolah');
        document.getElementById('editAlamat').value      = button.getAttribute('data-alamat');
        document.getElementById('editNoHp').value        = button.getAttribute('data-no_hp');
        document.getElementById('editNoHpOrtu').value    = button.getAttribute('data-no_hp_ortu');
      });
    });

    // Delete button event listener
    document.querySelectorAll('.deleteBtn').forEach(button => {
      button.addEventListener('click', () => {
        document.getElementById('deleteId').value     = button.getAttribute('data-id');
        document.getElementById('deleteNama').innerText = button.getAttribute('data-nama');
      });
    });

    // Toggle sidebar
    document.getElementById('sidebarToggle').addEventListener('click', () => {
      document.querySelector('.sidebar').classList.toggle('active');
    });

    document.querySelectorAll('.verifyBtn').forEach(button => {
      button.addEventListener('click', () => {
        document.getElementById('verifyId').value = button.getAttribute('data-id');
        document.getElementById('verifyNama').innerText = button.getAttribute('data-nama');
        document.getElementById('verifyNoFormulir').innerText = button.getAttribute('data-no_formulir');
        // Reset form saat buka modal
        ['cbUangPangkal','cbSPP','cbSeragam','cbKegiatan'].forEach(id => {
          document.getElementById(id).checked = false;
        });
        ['uangPangkalInput','sppInput','seragamInput','kegiatanInput'].forEach(id => {
          document.getElementById(id).value = '';
        });
        // Optional: ambil nominal default dari server via AJAX
      });
    });

    // Format nominal jadi rupiah
    function formatRupiah(angka) {
      return 'Rp ' + (angka ? angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") : "-");
    }
    ['uangPangkalInput','sppInput','seragamInput','kegiatanInput'].forEach(id => {
      document.getElementById(id).addEventListener('input', function() {
        var nominalSpan = document.getElementById(id.replace('Input','Nominal'));
        nominalSpan.innerText = formatRupiah(this.value);
      });
    });

    // Tangani submit (AJAX/simpan manual sesuai backend)
    document.getElementById('verifyForm').addEventListener('submit', function(e){
      e.preventDefault();
      const id = document.getElementById('verifyId').value;
      const values = {};
      if(document.getElementById('cbUangPangkal').checked)
        values['Uang Pangkal'] = document.getElementById('uangPangkalInput').value;
      if(document.getElementById('cbSPP').checked)
        values['SPP'] = document.getElementById('sppInput').value;
      if(document.getElementById('cbSeragam').checked)
        values['Seragam'] = document.getElementById('seragamInput').value;
      if(document.getElementById('cbKegiatan').checked)
        values['Kegiatan'] = document.getElementById('kegiatanInput').value;

      fetch('verifikasi_pembayaran.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `id=${encodeURIComponent(id)}&data=${encodeURIComponent(JSON.stringify(values))}`
      })
      .then(res => res.json())
      .then(res => {
        if(res.success) {
          alert('Data verifikasi berhasil disimpan!');
          bootstrap.Modal.getInstance(document.getElementById('verifyModal')).hide();
          location.reload();
        } else {
          alert('Gagal menyimpan: ' + res.message);
        }
      });
    });

  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/sidebar_pendaftaran.js"></script>
</body>
</html>
