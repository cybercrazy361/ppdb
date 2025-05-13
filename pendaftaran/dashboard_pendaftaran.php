<?php
session_start();
include '../database_connection.php';

// Pastikan petugas pendaftaran sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

function getStatusPembayaranCounts($conn, $unit) {
    // 1) Total Siswa
    $sqlTotal = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
    $stmt = $conn->prepare($sqlTotal);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // 2) Belum Bayar: tidak ada record pembayaran_detail
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

    // 3) Lunas: harus punya BOTH pembayaran Uang Pangkal & SPP Juli
    $sqlLunas = "
      SELECT COUNT(*) AS lunas
      FROM siswa s
      WHERE s.unit = ?
        AND EXISTS (
          SELECT 1 FROM pembayaran p
          JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
          WHERE p.siswa_id = s.id
            AND pd.jenis_pembayaran_id = 1
        )
        AND EXISTS (
          SELECT 1 FROM pembayaran p2
          JOIN pembayaran_detail pd2 ON p2.id = pd2.pembayaran_id
          WHERE p2.siswa_id = s.id
            AND pd2.jenis_pembayaran_id = 2
            AND pd2.bulan = 'Juli'
        )
    ";
    $stmt = $conn->prepare($sqlLunas);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $lunas = (int)$stmt->get_result()->fetch_assoc()['lunas'];
    $stmt->close();

    // 4) Total sudah bayar = semua siswa minus belum bayar
    $sudahBayar = $total - $belum;

    // 5) Angsuran = yang sudah bayar tapi bukan lunas
    $angsuran = max(0, $sudahBayar - $lunas);

    return [
      'total_siswa'          => $total,
      'belum_bayar'          => $belum,
      'sudah_bayar_lunas'    => $lunas,
      'sudah_bayar_angsuran' => $angsuran,
      'total_sudah_bayar'    => $sudahBayar
    ];
}

$statistik        = getStatusPembayaranCounts($conn, $unit);
$totalSiswa       = $statistik['total_siswa'];
$belumBayar       = $statistik['belum_bayar'];
$sudahLunas       = $statistik['sudah_bayar_lunas'];
$sudahAngsuran    = $statistik['sudah_bayar_angsuran'];
$totalSudahBayar  = $statistik['total_sudah_bayar'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard <?= htmlspecialchars($unit) ?> - PPDB</title>
  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Header -->
  <header class="bg-primary text-white p-3 shadow">
    <div class="container-fluid d-flex justify-content-between">
      <div>
        <h4 class="mb-1">Dashboard <?= htmlspecialchars($unit) ?></h4>
        <small>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?></small>
      </div>
      <a href="../logout/logout_pendaftaran.php" class="btn btn-light btn-sm">Logout</a>
    </div>
  </header>

  <!-- Main Content -->
  <main class="container mt-4">

    <!-- Statistik -->
    <div class="row gy-4">
      <div class="col-md-4">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
            <h5 class="card-title">Total Siswa</h5>
            <h3><?= $totalSiswa ?></h3>
          </div>
          <div class="card-footer">
            <small>Total siswa di unit <?= htmlspecialchars($unit) ?></small>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
            <h5 class="card-title">Lunas</h5>
            <h3><?= $sudahLunas ?></h3>
          </div>
          <div class="card-footer">
            <small>Uang Pangkal & SPP Juli lunas</small>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <i class="fas fa-hand-holding-usd fa-2x text-warning mb-2"></i>
            <h5 class="card-title">Angsuran</h5>
            <h3><?= $sudahAngsuran ?></h3>
          </div>
          <div class="card-footer">
            <small>Sudah bayar tapi belum lunas penuh</small>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
            <h5 class="card-title">Belum Bayar</h5>
            <h3><?= $belumBayar ?></h3>
          </div>
          <div class="card-footer">
            <small>Belum ada pembayaran</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Diagram Pembayaran -->
    <div class="row mt-4">
      <div class="col">
        <div class="card p-3 shadow-sm text-center">
          <h6>Diagram Pembayaran</h6>
          <canvas id="grafikPembayaran" style="max-height:300px;"></canvas>
        </div>
      </div>
    </div>

  </main>

  <!-- Chart Script -->
  <script>
    const data = [<?= $sudahLunas ?>, <?= $sudahAngsuran ?>, <?= $belumBayar ?>];
    new Chart(document.getElementById('grafikPembayaran'), {
      type: 'doughnut',
      data: {
        labels: ['Lunas','Angsuran','Belum Bayar'],
        datasets: [{
          data,
          backgroundColor: ['#28a745','#ffc107','#dc3545']
        }]
      },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
