<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

// Fungsi asli menghitung status pembayaran per unit
function getStatusPembayaranCounts($conn, $unit) {
    // Total Siswa
    $sqlTotal = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
    $stmt = $conn->prepare($sqlTotal);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Belum Bayar: siswa tanpa detail pembayaran sama sekali
    $sqlBelum = "
      SELECT COUNT(DISTINCT s.id) AS belum
      FROM siswa s
      LEFT JOIN pembayaran p ON s.id = p.siswa_id
      LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
      WHERE s.unit = ? AND pd.id IS NULL
    ";
    $stmt = $conn->prepare($sqlBelum);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $belum = (int)$stmt->get_result()->fetch_assoc()['belum'];
    $stmt->close();

    // Sudah Bayar Lunas & Angsuran
    $sqlSudah = "
      SELECT
        COUNT(DISTINCT CASE WHEN pd.status_pembayaran = 'Lunas' THEN s.id END) AS lunas,
        COUNT(DISTINCT CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN CONCAT(s.id,'-',pd.angsuran_ke) END) AS angsuran,
        COUNT(DISTINCT s.id) AS total_sudah
      FROM siswa s
      LEFT JOIN pembayaran p ON s.id = p.siswa_id
      LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
      WHERE s.unit = ?
        AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ";
    $stmt = $conn->prepare($sqlSudah);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $lunas     = (int)$row['lunas'];
    $angsuran  = (int)$row['angsuran'];
    $totalSudah= (int)$row['total_sudah'];
    $stmt->close();

    return [
      'total'    => $total,
      'belum'    => $belum,
      'lunas'    => $lunas,
      'angsuran' => $angsuran,
      'sudah'    => $totalSudah
    ];
}

$stats = getStatusPembayaranCounts($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard <?= htmlspecialchars($unit) ?> – PPDB Online</title>
  <!-- Bootstrap & FontAwesome -->
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
  <header class="dashboard-header">
    <div class="brand">
      <i class="fas fa-graduation-cap"></i>
      <span>Dashboard <?= htmlspecialchars($unit) ?></span>
    </div>
    <div class="user-info">
      <span>Hai, <?= htmlspecialchars($_SESSION['nama']) ?></span>
      <a href="../logout/logout_pendaftaran.php" class="btn btn-light btn-sm">Logout</a>
    </div>
  </header>

  <main class="container dashboard-main">

    <!-- STATISTICS GRID -->
    <section class="stats-grid">
      <div class="stat-card">
        <div class="icon text-primary"><i class="fas fa-users"></i></div>
        <div class="label">Total Siswa</div>
        <div class="value"><?= $stats['total'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-danger"><i class="fas fa-times-circle"></i></div>
        <div class="label">Belum Bayar</div>
        <div class="value"><?= $stats['belum'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-success"><i class="fas fa-check-circle"></i></div>
        <div class="label">Lunas</div>
        <div class="value"><?= $stats['lunas'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-warning"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="label">Angsuran</div>
        <div class="value"><?= $stats['angsuran'] ?></div>
      </div>
    </section>

    <!-- PAYMENT CHART -->
    <section class="chart-section">
      <div class="chart-card">
        <canvas id="chartPembayaran"></canvas>
      </div>
    </section>

    <!-- ACTION CARDS -->
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

  <!-- CHART SCRIPT -->
  <script>
    const ctx = document.getElementById('chartPembayaran').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Lunas','Angsuran','Belum Bayar'],
        datasets: [{
          data: [<?= $stats['lunas'] ?>,<?= $stats['angsuran'] ?>,<?= $stats['belum'] ?>],
          backgroundColor: ['#28a745','#ffc107','#dc3545']
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const v = ctx.raw,
                      t = ctx.dataset.data.reduce((a,b)=>a+b,0),
                      p = ((v/t)*100).toFixed(1);
                return `${ctx.label}: ${v} (${p}%)`;
              }
            }
          }
        }
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
