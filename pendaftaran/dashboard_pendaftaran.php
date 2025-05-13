<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // Misalnya 'SMA' atau 'SMK'

// Hitung statistik pembayaran
function getStatusPembayaranCounts($conn, $unit) {
    // Total siswa
    $sqlTotal   = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
    $stmtTotal  = $conn->prepare($sqlTotal);
    $stmtTotal->bind_param("s", $unit);
    $stmtTotal->execute();
    $total = $stmtTotal->get_result()->fetch_assoc()['total'];
    $stmtTotal->close();

    // Belum bayar
    $sqlBelum   = "
        SELECT COUNT(DISTINCT s.id) AS total
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND pd.id IS NULL
    ";
    $stmtBelum  = $conn->prepare($sqlBelum);
    $stmtBelum->bind_param("s", $unit);
    $stmtBelum->execute();
    $belum = $stmtBelum->get_result()->fetch_assoc()['total'];
    $stmtBelum->close();

    // Sudah bayar tunai & angsuran
    $sqlSudah   = "
        SELECT 
          SUM(pd.status_pembayaran = 'Lunas')      AS lunas,
          SUM(pd.status_pembayaran LIKE 'Angsuran%') AS angsuran
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ?
    ";
    $stmtSudah  = $conn->prepare($sqlSudah);
    $stmtSudah->bind_param("s", $unit);
    $stmtSudah->execute();
    $row = $stmtSudah->get_result()->fetch_assoc();
    $stmtSudah->close();

    $lunas     = (int)$row['lunas'];
    $angsuran  = (int)$row['angsuran'];
    $sudah     = $lunas + $angsuran;

    return [
        'total_siswa'       => $total,
        'belum_bayar'       => $belum,
        'sudah_bayar_lunas' => $lunas,
        'sudah_bayar_angsuran' => $angsuran,
        'total_sudah_bayar' => $sudah
    ];
}

$stat = getStatusPembayaranCounts($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard <?= htmlspecialchars($unit) ?> - PPDB</title>
  <!-- Google Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Custom Styles -->
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Header -->
  <header>
    <div>
      <h4>Dashboard <?= htmlspecialchars($unit) ?></h4>
      <small>Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?></small>
    </div>
    <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
  </header>

  <!-- Main -->
  <div class="container">
    <div class="row g-4 mb-4">
      <!-- Total Siswa -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <i class="fas fa-user-graduate text-primary"></i>
            <h5 class="card-title">Total Siswa</h5>
            <div class="card-count"><?= $stat['total_siswa'] ?></div>
          </div>
          <div class="card-footer"><small>Total yang mendaftar</small></div>
        </div>
      </div>
      <!-- Sudah Bayar -->
      <div class="col-md-4">
        <div class="card">
          <div class="card-body">
            <i class="fas fa-money-bill-wave text-success"></i>
            <h5 class="card-title">Sudah Membayar</h5>
            <div class="card-count"><?= $stat['total_sudah_bayar'] ?></div>
            <p>
              <small><i class="fas fa-check-circle"></i> <?= $stat['sudah_bayar_lunas'] ?> Lunas</small><br>
              <small><i class="fas fa-exclamation-circle"></i> <?= $stat['sudah_bayar_angsuran'] ?> Angsuran</small>
            </p>
          </div>
          <div class="card-footer"><small>Detail pembayaran</small></div>
        </div>
      </div>
      <!-- Belum Bayar -->
      <div class="col-md-4">
        <div class="card cursor-pointer" onclick="showModal('belum')">
          <div class="card-body">
            <i class="fas fa-money-check-alt text-danger"></i>
            <h5 class="card-title">Belum Bayar</h5>
            <div class="card-count"><?= $stat['belum_bayar'] ?></div>
          </div>
          <div class="card-footer"><small>Menunggu pembayaran</small></div>
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="row mb-5">
      <div class="col">
        <div class="card p-4 text-center">
          <h6>Diagram Pembayaran</h6>
          <div class="chart-container">
            <canvas id="grafikPembayaran"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigasi -->
    <div class="row g-3 text-center">
      <?php 
      $menu = [
        ['icon'=>'user-plus','label'=>'Input Pendaftaran','link'=>'form_pendaftaran.php','btn'=>'Input','btnClass'=>'primary'],
        ['icon'=>'users','label'=>'Daftar Siswa','link'=>'daftar_siswa.php','btn'=>'Lihat','btnClass'=>'info'],
        ['icon'=>'file-alt','label'=>'Cetak Laporan','link'=>'cetak_laporan_pendaftaran.php','btn'=>'Cetak','btnClass'=>'warning'],
        ['icon'=>'check-circle','label'=>'Review Calon Pendaftar','link'=>'review_calon_pendaftar.php','btn'=>'Review','btnClass'=>'success'],
      ];
      foreach($menu as $m): ?>
      <div class="col-md-3">
        <div class="card nav-card">
          <i class="fas fa-<?= $m['icon'] ?>"></i>
          <h6><?= $m['label'] ?></h6>
          <a href="<?= $m['link'] ?>" class="btn btn-<?= $m['btnClass'] ?>"><?= $m['btn'] ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Modal Daftar Siswa -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="statusModalLabel">Daftar Siswa</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>No</th><th>Nama</th><th>Status</th>
              </tr>
            </thead>
            <tbody id="modalContent">
              <tr><td colspan="3" class="text-center py-4">Memuat data…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function showModal(status) {
      const label = status === 'belum' ? 'Belum Bayar' : 'Sudah Bayar';
      document.getElementById('statusModalLabel').textContent = 'Daftar Siswa ' + label;
      fetch(`fetch_siswa.php?status=${status}&unit=<?= urlencode($unit) ?>`)
        .then(r => r.json())
        .then(data => {
          const tb = document.getElementById('modalContent');
          tb.innerHTML = '';
          if (data.length) {
            data.forEach((s,i) => {
              tb.innerHTML += `<tr>
                <td>${i+1}</td>
                <td>${s.nama}</td>
                <td>${s.status_pembayaran}</td>
              </tr>`;
            });
          } else {
            tb.innerHTML = '<tr><td colspan="3" class="text-center py-4">Tidak ada data.</td></tr>';
          }
        });
      new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    // Chart.js
    const ctx = document.getElementById('grafikPembayaran').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Membayar','Belum Bayar'],
        datasets: [{
          data: [<?= $stat['total_sudah_bayar'] ?>, <?= $stat['belum_bayar'] ?>],
          backgroundColor: [var(--success), var(--danger)],
          borderColor: '#fff',
          borderWidth: 2
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth:12, font:{size:14} } },
          tooltip: {
            callbacks: {
              label: ctx => {
                let v = ctx.raw, t = ctx.dataset.data.reduce((a,b)=>a+b,0);
                return `${ctx.label}: ${v} siswa (${(v/t*100).toFixed(1)}%)`;
              }
            }
          }
        },
        maintainAspectRatio: false
      }
    });
  </script>
</body>
</html>
