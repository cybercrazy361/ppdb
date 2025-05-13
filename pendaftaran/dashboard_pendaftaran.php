<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}
$unit = $_SESSION['unit']; // SMA atau SMK

function getStats($conn, $unit) {
    $sql = "
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN total_pd = 0 THEN 1 ELSE 0 END)            AS belum,
      SUM(CASE WHEN cnt_pangkal > 0 AND cnt_spp_juli > 0 
               THEN 1 ELSE 0 END)                              AS lunas,
      SUM(CASE WHEN total_pd > 0 
               AND NOT(cnt_pangkal > 0 AND cnt_spp_juli > 0)
               THEN 1 ELSE 0 END)                              AS angsuran
    FROM (
      SELECT 
        s.id,
        COUNT(pd.id) AS total_pd,
        SUM(CASE WHEN pd.jenis_pembayaran_id = 1 THEN 1 ELSE 0 END)                 AS cnt_pangkal,
        SUM(CASE WHEN pd.jenis_pembayaran_id = 2 AND pd.bulan = 'Juli' THEN 1 ELSE 0 END) AS cnt_spp_juli
      FROM siswa s
      LEFT JOIN pembayaran p       ON s.id = p.siswa_id
      LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
      WHERE s.unit = ?
      GROUP BY s.id
    ) AS sub;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
      'total'    => (int)$row['total'],
      'belum'    => (int)$row['belum'],
      'lunas'    => (int)$row['lunas'],
      'angsuran' => (int)$row['angsuran']
    ];
}

$st = getStats($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard <?= htmlspecialchars($unit) ?> – PPDB Online</title>
  <!-- Bootstrap, FontAwesome, Google Fonts, Custom CSS, Chart.js -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <header class="dashboard-header">
    <div class="brand"><i class="fas fa-graduation-cap"></i> Dashboard <?= htmlspecialchars($unit) ?></div>
    <div class="user-info">
      <span>Hai, <?= htmlspecialchars($_SESSION['nama']) ?></span>
      <a href="../logout/logout_pendaftaran.php" class="btn btn-light btn-sm ms-3">Logout</a>
    </div>
  </header>

  <main class="dashboard-main container">

    <section class="stats-grid">
      <div class="stat-card">
        <div class="icon text-primary"><i class="fas fa-users"></i></div>
        <div class="label">Total Siswa</div>
        <div class="value"><?= $st['total'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-danger"><i class="fas fa-times-circle"></i></div>
        <div class="label">Belum Bayar</div>
        <div class="value"><?= $st['belum'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-success"><i class="fas fa-check-circle"></i></div>
        <div class="label">Lunas</div>
        <div class="value"><?= $st['lunas'] ?></div>
      </div>
      <div class="stat-card">
        <div class="icon text-warning"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="label">Angsuran</div>
        <div class="value"><?= $st['angsuran'] ?></div>
      </div>
    </section>

    <section class="chart-section">
      <div class="chart-card">
        <canvas id="chartPembayaran"></canvas>
      </div>
    </section>

    <section class="actions-grid">
      <a href="form_pendaftaran.php" class="action-card text-primary">
        <i class="fas fa-user-plus"></i><span>Tambah Pendaftaran</span>
      </a>
      <a href="daftar_siswa.php" class="action-card text-info">
        <i class="fas fa-list"></i><span>Daftar Siswa</span>
      </a>
      <a href="cetak_laporan_pendaftaran.php" class="action-card text-warning">
        <i class="fas fa-print"></i><span>Cetak Laporan</span>
      </a>
      <a href="review_calon_pendaftar.php" class="action-card text-success">
        <i class="fas fa-search"></i><span>Review Calon</span>
      </a>
    </section>

  </main>

  <script>
    new Chart(document.getElementById('chartPembayaran'), {
      type: 'doughnut',
      data: {
        labels: ['Lunas','Angsuran','Belum Bayar'],
        datasets: [{
          data: [<?= $st['lunas'] ?>,<?= $st['angsuran'] ?>,<?= $st['belum'] ?>],
          backgroundColor: ['#28a745','#ffc107','#dc3545']
        }]
      },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
