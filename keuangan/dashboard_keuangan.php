<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai keuangan
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// Ambil total siswa pada unit tersebut
$sql_total = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param('s', $unit);
$stmt_total->execute();
$result_total = $stmt_total->get_result()->fetch_assoc();
$total_siswa = $result_total['total'] ?? 0;
$stmt_total->close();

// Ambil jumlah siswa yang sudah membayar
$sql_sudah = "
  SELECT COUNT(DISTINCT s.id) AS sudah_bayar 
  FROM siswa s
  INNER JOIN pembayaran p ON s.id = p.siswa_id
  INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
  WHERE s.unit = ? 
    AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
";
$stmt_sudah = $conn->prepare($sql_sudah);
$stmt_sudah->bind_param('s', $unit);
$stmt_sudah->execute();
$result_sudah = $stmt_sudah->get_result()->fetch_assoc();
$total_sudah_bayar = $result_sudah['sudah_bayar'] ?? 0;
$stmt_sudah->close();

// Ambil jumlah siswa yang belum membayar
$sql_belum = "
  SELECT COUNT(DISTINCT s.id) AS belum_bayar
  FROM siswa s
  WHERE s.unit = ?
    AND NOT EXISTS (
      SELECT 1 
      FROM pembayaran p
      JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
      WHERE p.siswa_id = s.id
        AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    )
";
$stmt_belum = $conn->prepare($sql_belum);
$stmt_belum->bind_param('s', $unit);
$stmt_belum->execute();
$result_belum = $stmt_belum->get_result()->fetch_assoc();
$total_belum_bayar = $result_belum['belum_bayar'] ?? 0;
$stmt_belum->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Keuangan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/sidebar.css" rel="stylesheet">
  <link href="../assets/css/dashboard_keuangan_styles.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include '../includes/sidebar.php'; ?>

  <div class="main-content">
    <!-- Topbar -->
    <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 shadow">
      <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
        <i class="fas fa-bars"></i>
      </button>
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="#">
            <span class="me-2 d-none d-lg-inline text-gray-600 small">
              <?= htmlspecialchars($_SESSION['nama']); ?>
            </span>
            <i class="fas fa-user-circle fa-lg"></i>
          </a>
        </li>
      </ul>
    </nav>

    <div class="container-fluid">
      <h1 class="dashboard-title">Dashboard Keuangan</h1>

      <div class="row gx-4 gy-4 mb-4">
        <div class="col-md-4">
          <div class="card card-metric total-card h-100">
            <div class="card-body d-flex align-items-center">
              <div class="card-icon bg-primary text-white">
                <i class="fas fa-users"></i>
              </div>
              <div class="ms-3">
                <p class="card-title">Total Siswa</p>
                <p class="card-text"><?= number_format($total_siswa); ?></p>
              </div>
            </div>
            <div class="card-footer">
              <small class="text-muted">Jumlah total siswa</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card card-metric paid-card h-100">
            <div class="card-body d-flex align-items-center">
              <div class="card-icon bg-success text-white">
                <i class="fas fa-user-check"></i>
              </div>
              <div class="ms-3">
                <p class="card-title">Sudah Membayar</p>
                <p class="card-text"><?= number_format($total_sudah_bayar); ?></p>
              </div>
            </div>
            <div class="card-footer">
              <small class="text-muted">Siswa yang telah membayar</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card card-metric unpaid-card h-100">
            <div class="card-body d-flex align-items-center">
              <div class="card-icon bg-danger text-white">
                <i class="fas fa-user-times"></i>
              </div>
              <div class="ms-3">
                <p class="card-title">Belum Membayar</p>
                <p class="card-text"><?= number_format($total_belum_bayar); ?></p>
              </div>
            </div>
            <div class="card-footer">
              <small class="text-muted">Siswa yang belum membayar</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistik Pembayaran -->
      <div class="card shadow mb-4">
        <div class="card-header py-3">
          <h3 class="mb-0">Statistik Pembayaran</h3>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="paymentChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer bg-white text-center py-3">
    &copy; <?= date('Y'); ?> Sistem Keuangan PPDB
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Membayar', 'Belum Membayar'],
        datasets: [{
          data: [<?= $total_sudah_bayar; ?>, <?= $total_belum_bayar; ?>],
          borderColor: '#fff',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { family: 'Nunito, sans-serif', size: 14 },
              color: '#333'
            }
          },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.raw;
                const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                const p = ((v/total)*100).toFixed(2);
                return `${ctx.label}: ${v} siswa (${p}%)`;
              }
            }
          }
        },
        animation: { animateScale: true }
      }
    });
  </script>
</body>
</html>
