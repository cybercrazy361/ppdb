<?php
// File: review_calon_pendaftar.php

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
$sql = "SELECT id,nama,asal_sekolah,email,no_hp,alamat,pilihan,tanggal_daftar,status,notes
        FROM calon_pendaftar " .
       ($unit !== 'Yayasan' ? "WHERE pilihan = ? " : "") .
       "ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($unit !== 'Yayasan') {
    $stmt->bind_param("s", $unit);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// 4) Daftar status & keterangan default
$status_desc = [
    'Pending'   => 'Menunggu tindak lanjut',
    'Contacted' => 'Sudah dihubungi',
    'Accepted'  => 'Calon diterima',
    'Rejected'  => 'Calon ditolak'
];
$status_list = array_keys($status_desc);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Review Calon Pendaftar</title>

  <!-- Google Fonts, Bootstrap, FontAwesome, DataTables CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <!-- Custom Responsive CSS -->
  <link rel="stylesheet" href="../assets/css/review_calon_pendaftar_styles.css">
</head>
<body>

  <div class="main-container">
    <div class="card-wrapper">
      <div class="card-header">
        <h4>Review Calon Pendaftar</h4>
        <a href="pendaftaran_dashboard.php" class="btn-back">
          <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
      </div>

      <div class="table-responsive">
        <table id="calonTable" class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th>No</th><th>Nama</th><th>Asal Sekolah</th><th>Email</th>
              <th>No HP</th><th>Alamat</th><th>Pilihan</th>
              <th>Tanggal Daftar</th><th>Status</th><th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $no=1; while ($row = $result->fetch_assoc()):
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

              <!-- Status dropdown berwarna -->
              <td class="text-center">
                <select class="status-select status-<?= strtolower($current) ?> form-select form-select-sm">
                  <?php foreach ($status_list as $st): ?>
                  <option value="<?= $st ?>" <?= $st=== $current?'selected':'' ?>>
                    <?= $st ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </td>

              <!-- Keterangan editable -->
              <td>
                <input type="text"
                       class="desc-input"
                       value="<?= htmlspecialchars($notes) ?>"
                       placeholder="Keterangan..." />
              </td>
            </tr>
          <?php endwhile; ?>
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
    $('#calonTable').DataTable({
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
      'Pending':'status-pending',
      'Contacted':'status-contacted',
      'Accepted':'status-accepted',
      'Rejected':'status-rejected'
    };

    // Update status
    $('#calonTable').on('change','.status-select',function(){
      const $sel = $(this),
            $row = $sel.closest('tr'),
            id   = $row.data('id'),
            status = $sel.val();
      $.post('update_status.php',{id,status},res=>{
        if(res.success){
          $sel
            .removeClass('status-pending status-contacted status-accepted status-rejected')
            .addClass(classes[status]);
        } else alert('Error menyimpan status');
      },'json').fail(()=>alert('Request gagal'));
    });

    // Update notes
    $('#calonTable').on('blur','.desc-input',function(){
      const $inp = $(this),
            $row = $inp.closest('tr'),
            id   = $row.data('id'),
            notes = $inp.val();
      $.post('update_status.php',{id,notes},res=>{
        if(!res.success) alert('Error menyimpan keterangan');
      },'json').fail(()=>alert('Request gagal'));
    });
  });
  </script>
</body>
</html>
