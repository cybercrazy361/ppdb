<?php
// File: review_calon_pendaftar.php

session_start();
include '../database_connection.php';

// Validasi login petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit dari petugas
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($unit);
$stmt->fetch();
$stmt->close();

// Query calon pendaftar sesuai unit
if ($unit === 'Yayasan') {
    $stmt = $conn->prepare("SELECT * FROM calon_pendaftar ORDER BY id DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM calon_pendaftar WHERE pilihan = ? ORDER BY id DESC");
    $stmt->bind_param("s", $unit);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Mapping keterangan status
$status_desc = [
    'Pending'   => 'Menunggu tindak lanjut',
    'Contacted' => 'Sudah dihubungi',
    'Accepted'  => 'Calon diterima',
    'Rejected'  => 'Calon ditolak'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Review Calon Pendaftar</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <!-- Bootstrap & Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/review_calon_pendaftar_styles.css">
</head>
<body>

  <div class="container">
    <h4>Review Calon Pendaftar</h4>
    <a href="pendaftaran_dashboard.php" class="btn-back mb-3"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>

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
          </tr>
        </thead>
        <tbody>
        <?php $no = 1; while ($row = $result->fetch_assoc()):
            // Pilih badge class
            $cls = match($row['status']) {
              'Pending'   => 'badge-pending',
              'Contacted' => 'badge-contacted',
              'Accepted'  => 'badge-accepted',
              'Rejected'  => 'badge-rejected',
              default     => 'badge-secondary'
            };
            $desc = $status_desc[$row['status']] ?? '';
        ?>
          <tr>
            <td><?= $no++ ?></td>
            <td><?= htmlspecialchars($row['nama']) ?></td>
            <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['no_hp']) ?></td>
            <td><?= htmlspecialchars($row['alamat']) ?></td>
            <td><?= htmlspecialchars($row['pilihan']) ?></td>
            <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
            <td class="status-cell">
              <span class="badge <?= $cls ?>"><?= htmlspecialchars($row['status']) ?></span>
              <?php if($desc): ?>
              <div class="status-desc"><?= htmlspecialchars($desc) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- jQuery, Bootstrap & DataTables JS -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

  <script>
  $(document).ready(function() {
    $('#calonTable').DataTable({
      pageLength: 10,
      lengthMenu: [5, 10, 25, 50],
      order: [[0, 'asc']],
      language: {
        search:     "Cari:",
        lengthMenu: "_MENU_ entri per halaman",
        info:       "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
        paginate:   { previous: "Sebelumnya", next: "Berikutnya" }
      }
    });
  });
  </script>
</body>
</html>
