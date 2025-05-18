<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login dan berhak akses
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// Ambil data total
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
$stmt->bind_param('s', $unit);
$stmt->execute();
$total_siswa = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Ambil data sudah bayar
$sql = "
SELECT COUNT(DISTINCT s.id) AS sudah_bayar
FROM siswa s
JOIN pembayaran p ON s.id = p.siswa_id
JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
WHERE s.unit = ?
  AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $unit);
$stmt->execute();
$total_sudah_bayar = $stmt->get_result()->fetch_assoc()['sudah_bayar'] ?? 0;
$stmt->close();

// Ambil data belum bayar
$sql = "
SELECT COUNT(*) AS belum_bayar
FROM siswa s
WHERE s.unit = ?
  AND NOT EXISTS (
    SELECT 1 FROM pembayaran p
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    WHERE p.siswa_id = s.id
      AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
  )
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $unit);
$stmt->execute();
$total_belum_bayar = $stmt->get_result()->fetch_assoc()['belum_bayar'] ?? 0;
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard Keuangan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/sidebar.css" rel="stylesheet">
  <link href="../assets/css/dashboard_keuangan_styles.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include '../includes/sidebar.php'; ?>

  <main class="main-content">
    <nav class="topbar navbar navbar-expand bg-white shadow-sm">
      <button id="sidebarToggle" class="btn btn-link d-md-none me-3">
        <i class="fas fa-bars"></i>
      </button>
      <div class="ms-auto d-flex align-items-center">
        <span class="user-name me-2"><?= htmlspecialchars($_SESSION['nama']); ?></span>
        <i class="fas fa-user-circle fa-2x text-secondary"></i>
      </div>
    </nav>

    <section class="container-fluid p-4">
      <div class="page-header mb-4">
        <h1>Dashboard Keuangan</h1>
        <p class="text-muted">Unit: <strong><?= htmlspecialchars($unit); ?></strong></p>
      </div>

      <div class="row g-4 mb-5">
        <div class="col-lg-4">
          <div class="info-card shadow-sm">
            <div class="card-icon"><i class="fas fa-users"></i></div>
            <div class="info-content">
              <h5>Total Siswa</h5>
              <h2><?= htmlspecialchars($total_siswa); ?></h2>
            </div>
            <small class="text-muted">Jumlah total siswa terdaftar</small>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="info-card shadow-sm">
            <div class="card-icon success"><i class="fas fa-user-check"></i></div>
            <div class="info-content">
              <h5>Sudah Membayar</h5>
              <h2><?= htmlspecialchars($total_sudah_bayar); ?></h2>
            </div>
            <small class="text-muted">Siswa yang telah menyelesaikan pembayaran</small>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="info-card shadow-sm">
            <div class="card-icon danger"><i class="fas fa-user-times"></i></div>
            <div class="info-content">
              <h5>Belum Membayar</h5>
              <h2><?= htmlspecialchars($total_belum_bayar); ?></h2>
            </div>
            <small class="text-muted">Siswa yang belum melakukan pembayaran</small>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-5">
        <div class="card-header">
          <h3>Statistik Pembayaran</h3>
        </div>
        <div class="card-body">
          <canvas id="paymentChart" style="height:300px;"></canvas>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer text-center py-3">
    &copy; <?= date('Y'); ?> Sistem Keuangan PPDB
  </footer>

  <script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Membayar','Belum Membayar'],
        datasets: [{
          data: [<?= $total_sudah_bayar; ?>,<?= $total_belum_bayar; ?>],
          backgroundColor: ['#1abc9c','#e74c3c'],
          hoverOffset: 8,
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.raw, t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                return `${ctx.label}: ${v} (${(v/t*100).toFixed(1)}%)`;
              }
            }
          }
        },
        animation: { animateScale: true }
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/sidebar.js"></script>
</body>
</html>
