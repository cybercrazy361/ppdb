<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit dari sesi login
$unit = $_SESSION['unit']; // Misalnya 'SMA' atau 'SMK'

// Fungsi untuk menghitung status pembayaran
function getStatusPembayaranCounts($conn, $unit) {
    // Total Siswa
    $sqlTotalSiswa = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
    $stmtTotal = $conn->prepare($sqlTotalSiswa);
    $stmtTotal->bind_param("s", $unit);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result()->fetch_assoc()['total'];
    $stmtTotal->close();

    // Belum Bayar: Siswa tanpa detail pembayaran
    $sqlBelumBayar = "
        SELECT COUNT(DISTINCT s.id) AS total
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND pd.id IS NULL
    ";
    $stmtBelum = $conn->prepare($sqlBelumBayar);
    $stmtBelum->bind_param("s", $unit);
    $stmtBelum->execute();
    $belumBayar = $stmtBelum->get_result()->fetch_assoc()['total'];
    $stmtBelum->close();

    // Sudah Bayar: Lunas dan Angsuran
    $sqlSudahBayar = "
        SELECT
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran = 'Lunas' THEN s.id END) AS total_lunas,
            COUNT(DISTINCT s.id) AS total_sudah
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ";
    $stmtSudah = $conn->prepare($sqlSudahBayar);
    $stmtSudah->bind_param("s", $unit);
    $stmtSudah->execute();
    $rowSudah = $stmtSudah->get_result()->fetch_assoc();
    $lunas = $rowSudah['total_lunas'];
    $totalSudah = $rowSudah['total_sudah'];
    $stmtSudah->close();

    $angsuran = max(0, $totalSudah - $lunas);

    return [
        'total'    => $resultTotal,
        'belum'    => $belumBayar,
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
  <title>Dashboard <?= htmlspecialchars($unit) ?> - PPDB</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Header -->
  <header class="page-header bg-primary text-white d-flex justify-content-between align-items-center px-3 py-2">
    <div>
      <h4 class="mb-1">Dashboard <?= htmlspecialchars($unit) ?></h4>
      <small>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?></small>
    </div>
    <a href="../logout/logout_pendaftaran.php" class="btn btn-light btn-sm">Logout</a>
  </header>

  <main class="container my-3">

    <!-- Statistik -->
    <div class="statistik row g-4">
      <div class="col-md-4">
        <div class="card text-center shadow">
          <div class="card-body">
            <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
            <h5 class="card-title">Total Siswa</h5>
            <h3 class="card-count"><?= htmlspecialchars($stats['total']) ?></h3>
          </div>
          <div class="card-footer">
            <small class="text-muted">Total siswa yang mendaftar</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center shadow">
          <div class="card-body">
            <i class="fas fa-money-bill-alt fa-2x text-success mb-2"></i>
            <h5 class="card-title">Sudah Membayar</h5>
            <h3 class="card-count"><?= htmlspecialchars($stats['sudah']) ?></h3>
            <p class="mb-0">
              <span><i class="fas fa-check-circle text-success"></i> <?= htmlspecialchars($stats['lunas']) ?> Lunas</span><br>
              <span><i class="fas fa-exclamation-circle text-warning"></i> <?= htmlspecialchars($stats['angsuran']) ?> Angsuran</span>
            </p>
          </div>
          <div class="card-footer">
            <small class="text-muted">Siswa yang telah membayar</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card text-center shadow cursor-pointer" onclick="showModal('belum')">
          <div class="card-body">
            <i class="fas fa-money-check fa-2x text-danger mb-2"></i>
            <h5 class="card-title">Belum Bayar</h5>
            <h3 class="card-count"><?= htmlspecialchars($stats['belum']) ?></h3>
          </div>
          <div class="card-footer">
            <small class="text-muted">Siswa yang belum membayar</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Diagram Pembayaran -->
    <div class="row mt-4">
      <div class="col">
        <div class="card p-3 shadow text-center">
          <h6>Diagram Pembayaran</h6>
          <div class="chart-container mx-auto">
            <canvas id="grafikPembayaran"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigasi Menu -->
    <div class="navigasi row g-3 text-center mt-4">
      <div class="col-md-3">
        <div class="card shadow p-3">
          <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
          <h6 class="card-title">Input Pendaftaran</h6>
          <a href="form_pendaftaran.php" class="btn btn-primary btn-sm">Input</a>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow p-3">
          <i class="fas fa-users fa-2x text-info mb-2"></i>
          <h6 class="card-title">Daftar Siswa</h6>
          <a href="daftar_siswa.php" class="btn btn-info btn-sm">Lihat</a>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow p-3">
          <i class="fas fa-file-alt fa-2x text-warning mb-2"></i>
          <h6 class="card-title">Cetak Laporan</h6>
          <a href="cetak_laporan_pendaftaran.php" class="btn btn-warning btn-sm">Cetak</a>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card shadow p-3">
          <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
          <h6 class="card-title">Review Calon</h6>
          <a href="review_calon_pendaftar.php" class="btn btn-success btn-sm">Review</a>
        </div>
      </div>
    </div>

  </main>

  <!-- Modal Daftar Siswa -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="statusModalLabel">Daftar Siswa</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="modalContent"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Script Chart & Modal -->
  <script>
    function showModal(status) {
      const title = document.getElementById('statusModalLabel');
      const body  = document.getElementById('modalContent');
      title.textContent = status === 'belum' ? 'Daftar Belum Bayar' : 'Daftar Sudah Bayar';
      body.innerHTML = '';
      fetch(`fetch_siswa.php?status=${status}&unit=<?= urlencode($unit) ?>`)
        .then(r => r.json())
        .then(data => {
          if (data.length) {
            data.forEach((s, i) => {
              body.innerHTML += `<tr>
                <td>${i+1}</td>
                <td>${s.nama}</td>
                <td>${s.status_pembayaran}</td>
              </tr>`;
            });
          } else {
            body.innerHTML = `<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>`;
          }
        }).catch(() => {
          body.innerHTML = `<tr><td colspan="3" class="text-danger text-center">Gagal memuat data.</td></tr>`;
        });
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    const ctx = document.getElementById('grafikPembayaran').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Bayar','Belum Bayar'],
        datasets: [{ 
          data: [<?= $stats['sudah'] ?>,<?= $stats['belum'] ?>],
          backgroundColor: ['#28a745','#dc3545'],
          hoverOffset: 8
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
