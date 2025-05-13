<?php
// File: review_calon_pendaftar.php

session_start();
include '../database_connection.php';

// 1) Validasi login petugas pendaftaran
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

// 3) Query calon_pendaftar (termasuk kolom notes)
if ($unit === 'Yayasan') {
    $stmt = $conn->prepare("SELECT id,nama,asal_sekolah,email,no_hp,alamat,pilihan,tanggal_daftar,status,notes FROM calon_pendaftar ORDER BY id DESC");
} else {
    $stmt = $conn->prepare("SELECT id,nama,asal_sekolah,email,no_hp,alamat,pilihan,tanggal_daftar,status,notes FROM calon_pendaftar WHERE pilihan = ? ORDER BY id DESC");
    $stmt->bind_param("s", $unit);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Daftar status yang diperbolehkan
$status_list = ['Pending','Contacted','Accepted','Rejected'];
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
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/review_calon_pendaftar_styles.css">
</head>
<body>

<div class="container">
  <h4>Review Calon Pendaftar</h4>
  <a href="dashboard_pendaftaran.php" class="btn-back mb-3">
    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
  </a>

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
      <?php $no = 1; while ($row = $result->fetch_assoc()):
          // Ambil nilai awal dari DB
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

          <!-- Dropdown Status -->
          <td class="text-center">
            <select class="status-select status-<?= strtolower($current) ?> form-select form-select-sm">
              <?php foreach ($status_list as $st): ?>
                <option value="<?= $st ?>" <?= $st === $current ? 'selected' : '' ?>>
                  <?= $st ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>

          <!-- Input Keterangan/Notes -->
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

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  // Inisialisasi DataTable
  $('#calonTable').DataTable({
    pageLength: 10,
    lengthMenu: [5,10,25,50],
    order: [[0,'asc']],
    language: {
      search:     "Cari:",
      lengthMenu: "_MENU_ entri per halaman",
      info:       "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
      paginate:   { previous: "Sebelumnya", next: "Berikutnya" }
    }
  });

  // Mapping warna untuk dropdown
  const statusClasses = {
    'Pending':   'status-pending',
    'Contacted': 'status-contacted',
    'Accepted':  'status-accepted',
    'Rejected':  'status-rejected'
  };

  // Update status + notes via AJAX
  $('#calonTable').on('change', '.status-select', function() {
    const $sel = $(this);
    const $row = $sel.closest('tr');
    const id   = $row.data('id');
    const status = $sel.val();

    $.post('update_status.php', { id, status }, function(resp) {
      if (resp.success) {
        // Update kelas warna
        $sel
          .removeClass('status-pending status-contacted status-accepted status-rejected')
          .addClass(statusClasses[status] || '');
      } else {
        alert('Gagal menyimpan status: ' + (resp.msg||''));
      }
    }, 'json').fail(() => {
      alert('Request gagal.');
    });
  });

  // Update notes via AJAX
  $('#calonTable').on('blur', '.desc-input', function() {
    const $inp = $(this);
    const $row = $inp.closest('tr');
    const id   = $row.data('id');
    const notes = $inp.val();

    $.post('update_status.php', { id, notes }, function(resp) {
      if (!resp.success) {
        alert('Gagal menyimpan keterangan.');
      }
    }, 'json').fail(() => {
      alert('Request gagal.');
    });
  });
});
</script>
</body>
</html>
