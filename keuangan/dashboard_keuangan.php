<?php
session_start();
include '../database_connection.php';

// Cek login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// Query data
$stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE unit = ?");
$stmt->bind_param('s', $unit);
$stmt->execute();
$total_siswa = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id)
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
");
$stmt->bind_param('s', $unit);
$stmt->execute();
$total_sudah = $stmt->get_result()->fetch_row()[0];
$stmt->close();

$total_belum = $total_siswa - $total_sudah;
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
  <script src="https://kit.fontawesome.com/a2e0b4f5c7.js" crossorigin="anonymous"></script>
</head>
<body class="bg-light">
  <?php include '../includes/sidebar.php'; ?>

  <main class="main-content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-secondary mb-0">Dashboard Keuangan</h2>
      <div class="text-end">
        <span class="me-2 text-secondary">Hai, <?= htmlspecialchars($_SESSION['nama']); ?></span>
        <i class="fas fa-user-circle fa-lg text-secondary"></i>
      </div>
    </div>

    <div class="row gy-4">
      <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm hover-up">
          <div class="card-body d-flex align-items-center">
            <div class="icon bg-primary text-white">
              <i class="fas fa-users"></i>
            </div>
            <div class="ms-3">
              <h6 class="text-secondary mb-1">Total Siswa</h6>
              <h3 class="fw-bold"><?= $total_siswa; ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm hover-up">
          <div class="card-body d-flex align-items-center">
            <div class="icon bg-success text-white">
              <i class="fas fa-user-check"></i>
            </div>
            <div class="ms-3">
              <h6 class="text-secondary mb-1">Sudah Membayar</h6>
              <h3 class="fw-bold"><?= $total_sudah; ?></h3>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card shadow-sm hover-up">
          <div class="card-body d-flex align-items-center">
            <div class="icon bg-danger text-white">
              <i class="fas fa-user-times"></i>
            </div>
            <div class="ms-3">
              <h6 class="text-secondary mb-1">Belum Membayar</h6>
              <h3 class="fw-bold"><?= $total_belum; ?></h3>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-4">
      <div class="card-header bg-white border-0">
        <h5 class="mb-0 text-secondary">Statistik Pembayaran</h5>
      </div>
      <div class="card-body">
        <canvas id="paymentChart" height="150"></canvas>
      </div>
    </div>
  </main>

  <footer class="footer text-center text-secondary py-3">
    &copy; <?= date('Y'); ?> Sistem Keuangan PPDB
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Membayar','Belum Membayar'],
        datasets: [{ data: [<?= $total_sudah; ?>,<?= $total_belum; ?>] }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const val = ctx.raw;
                const tot = ctx.dataset.data.reduce((a,b)=>a+b,0);
                const pct = ((val/tot)*100).toFixed(1);
                return `${ctx.label}: ${val} (${pct}%)`;
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
