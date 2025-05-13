<?php
session_start();
include '../database_connection.php';

// Pastikan user terlogin sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

// Hitung statistik pembayaran
function getStats($conn, $unit) {
    $q = $conn->prepare("
        SELECT
          SUM(s1.total)                 AS total_siswa,
          SUM(CASE WHEN pd.id IS NULL THEN 1 ELSE 0 END) AS belum_bayar,
          SUM(CASE WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) AS lunas,
          SUM(CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN 1 ELSE 0 END) AS angsuran
        FROM (
          SELECT s.id, 1 AS total
          FROM siswa s WHERE s.unit = ?
        ) s1
        LEFT JOIN pembayaran p  ON s1.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    ");
    $q->bind_param("s", $unit);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();

    $total = (int)$r['total_siswa'];
    $belum = (int)$r['belum_bayar'];
    $lunas = (int)$r['lunas'];
    $angsuran = (int)$r['angsuran'];
    $sudah  = $lunas + $angsuran;

    return compact('total','belum','lunas','angsuran','sudah');
}

$st = getStats($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard <?= htmlspecialchars($unit) ?> - PPDB Online</title>
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- HEADER -->
  <header class="header">
    <div class="header-brand">
      <i class="fas fa-graduation-cap me-2"></i>
      <span>Dashboard <?= htmlspecialchars($unit) ?></span>
    </div>
    <div class="header-user">
      <span>Hai, <?= htmlspecialchars($_SESSION['nama']) ?></span>
      <a href="../logout/logout_pendaftaran.php" class="btn btn-sm btn-light ms-3">Logout</a>
    </div>
  </header>

  <main class="main-container">

    <!-- STATISTIK GRID -->
    <section class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon text-primary"><i class="fas fa-users"></i></div>
        <div class="stat-label">Total Siswa</div>
        <div class="stat-value"><?= $st['total'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-label">Belum Bayar</div>
        <div class="stat-value"><?= $st['belum'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
        <div class="stat-label">Lunas</div>
        <div class="stat-value"><?= $st['lunas'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon text-warning"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-label">Angsuran</div>
        <div class="stat-value"><?= $st['angsuran'] ?></div>
      </div>
    </section>

    <!-- CHART SECTION -->
    <section class="chart-section">
      <div class="chart-card">
        <canvas id="chartPembayaran"></canvas>
      </div>
    </section>

    <!-- ACTIONS GRID -->
    <section class="actions-grid">
      <a href="form_pendaftaran.php" class="action-card text-primary">
        <i class="fas fa-user-plus"></i>
        <span>Tambah Pendaftaran</span>
      </a>
      <a href="daftar_siswa.php" class="action-card text-info">
        <i class="fas fa-list"></i>
        <span>Daftar Siswa</span>
      </a>
      <a href="cetak_laporan_pendaftaran.php" class="action-card text-warning">
        <i class="fas fa-print"></i>
        <span>Cetak Laporan</span>
      </a>
      <a href="review_calon_pendaftar.php" class="action-card text-success">
        <i class="fas fa-search"></i>
        <span>Review Calon</span>
      </a>
    </section>

  </main>

  <!-- Modal Daftar -->
  <div class="modal fade" id="modalDaftar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Daftar Siswa</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <table class="table table-striped mb-0">
            <thead>
              <tr><th>No</th><th>Nama</th><th>Status</th></tr>
            </thead>
            <tbody id="modalBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart & Modal Script -->
  <script>
    // Chart Pembayaran
    const ctx = document.getElementById('chartPembayaran').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Lunas','Angsuran','Belum Bayar'],
        datasets: [{
          data: [<?= $st['lunas'] ?>,<?= $st['angsuran'] ?>,<?= $st['belum'] ?>],
          backgroundColor: ['#28a745','#ffc107','#dc3545']
        }]
      },
      options: { responsive: true, plugins: { legend:{position:'bottom'} } }
    });

    // Example: open modal (you can wire to a button)
    function openDaftar(status) {
      fetch(`fetch_siswa.php?status=${status}&unit=<?= urlencode($unit) ?>`)
        .then(r=>r.json()).then(data=>{
          const body = document.getElementById('modalBody');
          body.innerHTML = data.length
            ? data.map((s,i)=>`<tr><td>${i+1}</td><td>${s.nama}</td><td>${s.status_pembayaran}</td></tr>`).join('')
            : `<tr><td colspan="3" class="text-center">Tidak ada data</td></tr>`;
          new bootstrap.Modal('#modalDaftar').show();
        });
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
