<?php
session_start();
include '../database_connection.php';

// 1) Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// 2) Ambil unit petugas
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($unit);
$stmt->fetch();
$stmt->close();

// 3) Query data calon_pendaftar
$sql = "SELECT id, nama, asal_sekolah, email, no_hp, alamat, pilihan, tanggal_daftar, status, notes
        FROM calon_pendaftar " .
       ($unit !== 'Yayasan' ? "WHERE pilihan = ? " : "") .
       "ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($unit !== 'Yayasan') {
    $stmt->bind_param("s", $unit);
}
$stmt->execute();
$result = $stmt->get_result();
$calon = [];
while ($row = $result->fetch_assoc()) $calon[] = $row;
$stmt->close();

// 4) Daftar status dinamis & inisialisasi rekap
$status_list = [
    'PPDB Bersama'        => 'Sudah melakukan pembayaran/PPDB Bersama',
    'Uang Titipan'        => 'Uang titipan sudah masuk',
    'Akan Bayar'          => 'Akan melakukan pembayaran',
    'Menunggu Negeri'     => 'Menunggu sekolah negeri',
    'Tidak Ada Konfirmasi'=> 'Tidak ada konfirmasi',
    'Tidak Jadi'          => 'Tidak jadi daftar'
];
$rekap = array_fill_keys(array_keys($status_list), 0);
// Hitung jumlah tiap status
foreach ($calon as $row) {
    $st = $row['status'];
    if (isset($rekap[$st])) $rekap[$st]++;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Review Calon Pendaftar</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    .status-ppdb-bersama { background:#198754 !important; color:#fff !important; }
    .status-uang-titipan { background:#6f42c1 !important; color:#fff !important; }
    .status-akan-bayar { background:#fd7e14 !important; color:#fff !important; }
    .status-menunggu-negeri { background:#ffc107 !important; color:#333 !important; }
    .status-tidak-ada-konfirmasi { background:#6c757d !important; color:#fff !important; }
    .status-tidak-jadi { background:#dc3545 !important; color:#fff !important; }
    .rekap-box { margin-bottom:16px; }
    .rekap-box .badge { font-size:1rem; padding:.5em 1em; }
    .btn-back { float:right; }
    @media (max-width:600px){
      .rekap-box{ font-size:.9rem;}
    }
  </style>
  <link rel="stylesheet" href="../assets/css/review_calon_pendaftar_styles.css">
</head>
<body>
  <div class="main-container">
    <div class="card-wrapper">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Review Calon Pendaftar</h4>
        <a href="dashboard_pendaftaran.php" class="btn btn-secondary btn-sm btn-back">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
      </div>

      <!-- Box Rekap Status -->
      <div class="rekap-box mb-3">
        <?php foreach($rekap as $key => $count): 
          $cls = 'status-'.strtolower(str_replace(' ', '-', $key));
        ?>
          <span class="badge <?= $cls ?>"><?= $key ?>: <?= $count ?></span>
        <?php endforeach; ?>
      </div>

      <div class="table-responsive">
        <table id="calonTable" class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Asal Sekolah</th>
              <th>Email</th>
              <th>No HP</th>
              <th>Alamat</th>
              <th>Pilihan</th>
              <th>Tanggal Daftar</th>
              <th>Status</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $no=1; foreach ($calon as $row):
              $current = $row['status'];
              $notes   = $row['notes'];
          ?>
            <tr data-id="<?= $row['id'] ?>">
              <td><?= $no++ ?></td>
              <td><?= htmlspecialchars($row['nama']) ?></td>
              <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><?= htmlspecialchars($row['no_hp']) ?></td>
              <td><?= htmlspecialchars($row['alamat']) ?></td>
              <td><?= htmlspecialchars($row['pilihan']) ?></td>
              <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
              <td class="text-center">
                <select class="status-select form-select form-select-sm status-<?= strtolower(str_replace(' ', '-', $current)) ?>">
                  <?php foreach ($status_list as $st => $desc): ?>
                  <option value="<?= $st ?>" <?= $st === $current ? 'selected' : '' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" class="desc-input form-control form-control-sm"
                       value="<?= htmlspecialchars($notes) ?>"
                       placeholder="Keterangan..." />
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- JS: jQuery, Bootstrap, DataTables -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

  <script>
  $(function(){
    // Inisialisasi DataTable
    const table = $('#calonTable').DataTable({
      pageLength: 10,
      lengthMenu: [5,10,25,50],
      order: [[0,'asc']],
      language:{
        search:     "Cari:",
        lengthMenu: "_MENU_ entri per halaman",
        info:       "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
        paginate:{ previous:"Sebelumnya", next:"Berikutnya" }
      }
    });

    // Mapping kelas warna
    const classes = {
      'PPDB Bersama':'status-ppdb-bersama',
      'Uang Titipan':'status-uang-titipan',
      'Akan Bayar':'status-akan-bayar',
      'Menunggu Negeri':'status-menunggu-negeri',
      'Tidak Ada Konfirmasi':'status-tidak-ada-konfirmasi',
      'Tidak Jadi':'status-tidak-jadi'
    };

    // Update status ke server & update warna
    $('#calonTable').on('change', '.status-select', function(){
      const $sel = $(this),
            $row = $sel.closest('tr'),
            id   = $row.data('id'),
            status = $sel.val();
      // AJAX post ke update_status.php (wajib Anda buat juga!)
      $.post('update_status.php', {id, status}, function(res){
        if(res.success){
          $sel.removeClass().addClass('status-select form-select form-select-sm ' + classes[status]);
          // Update rekap box
          location.reload(); // Reload page untuk update rekap
        } else {
          alert('Error menyimpan status');
        }
      }, 'json');
    });

    // Update notes ke server
    $('#calonTable').on('blur', '.desc-input', function(){
      const $inp = $(this),
            $row = $inp.closest('tr'),
            id   = $row.data('id'),
            notes = $inp.val();
      $.post('update_status.php', {id, notes}, function(res){
        if(!res.success) alert('Error menyimpan keterangan');
      }, 'json');
    });
  });
  </script>
</body>
</html>
