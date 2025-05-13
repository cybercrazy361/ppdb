<?php
session_start();
include '../database_connection.php';

// Validasi login petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

function getStats($conn, $unit) {
    // total pendaftar
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    // belum bayar
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS belum
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id=p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id=pd.pembayaran_id
        WHERE s.unit=? AND pd.id IS NULL
    ");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $belum = $stmt->get_result()->fetch_assoc()['belum']; $stmt->close();

    // lunas & angsuran
    $stmt = $conn->prepare("
        SELECT
          COUNT(DISTINCT CASE WHEN pd.status_pembayaran='Lunas' THEN s.id END) AS lunas,
          COUNT(DISTINCT s.id) AS bayar
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id=p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id=pd.pembayaran_id
        WHERE s.unit=? AND (pd.status_pembayaran='Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

    $lunas = (int)$row['lunas'];
    $bayar = (int)$row['bayar'];
    $angsuran = max(0, $bayar - $lunas);

    return [
      'total'=>$total, 'belum'=>$belum,
      'lunas'=>$lunas, 'angsuran'=>$angsuran,
      'bayar'=>$bayar
    ];
}

$stat = getStats($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard <?=htmlspecialchars($unit)?> – PPDB</title>
  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link rel="stylesheet" href="../assets/css/dashboard_ppdb.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Sidebar -->
  <nav class="sidebar">
    <div class="brand">PPDB <?=htmlspecialchars($unit)?></div>
    <a href="pendaftaran_dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span> Dashboard</span></a>
    <a href="form_pendaftaran.php"      class="nav-link"><i class="fas fa-user-plus"></i><span> Input</span></a>
    <a href="daftar_siswa.php"          class="nav-link"><i class="fas fa-users"></i><span> Daftar</span></a>
    <a href="cetak_laporan_pendaftaran.php" class="nav-link"><i class="fas fa-file-alt"></i><span> Cetak</span></a>
    <a href="review_calon_pendaftar.php" class="nav-link"><i class="fas fa-check-circle"></i><span> Review</span></a>
  </nav>

  <!-- Main -->
  <div class="main">
    <header class="navbar">
      <button class="toggle-btn"><i class="fas fa-bars"></i></button>
      <div class="title">Dashboard <?=htmlspecialchars($unit)?></div>
      <div class="user-menu">
        <small>Halo, <?=htmlspecialchars($_SESSION['nama'])?></small>
        <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
      </div>
    </header>

    <!-- Statistik Cards -->
    <section class="dashboard-cards">
      <div class="card">
        <div class="icon text-primary"><i class="fas fa-user-graduate"></i></div>
        <div class="title">Total Pendaftar</div>
        <div class="count"><?=$stat['total']?></div>
        <div class="subtext">Unit <?=htmlspecialchars($unit)?></div>
      </div>
      <div class="card">
        <div class="icon text-success"><i class="fas fa-money-bill-wave"></i></div>
        <div class="title">Sudah Bayar</div>
        <div class="count"><?=$stat['bayar']?></div>
        <div class="subtext">
          <i class="fas fa-check-circle text-success"></i> <?=$stat['lunas']?> Lunas<br>
          <i class="fas fa-wallet text-warning"></i> <?=$stat['angsuran']?> Angsuran
        </div>
      </div>
      <div class="card" style="cursor:pointer" onclick="showModal('belum')">
        <div class="icon text-danger"><i class="fas fa-exclamation-circle"></i></div>
        <div class="title">Belum Bayar</div>
        <div class="count"><?=$stat['belum']?></div>
        <div class="subtext">Segera follow-up</div>
      </div>
    </section>

    <!-- Chart -->
    <section class="chart-card">
      <h6>Persentase Pembayaran</h6>
      <div class="chart-container">
        <canvas id="chartBayar"></canvas>
      </div>
    </section>
  </div>

  <!-- Modal Daftar Siswa -->
  <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Daftar Siswa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <table class="table table-striped">
            <thead>
              <tr><th>No</th><th>Nama</th><th>Status</th></tr>
            </thead>
            <tbody id="modalBody">
              <tr><td colspan="3" class="text-center">Memuat...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle on small screens
    document.querySelector('.toggle-btn').addEventListener('click', () => {
      document.querySelector('.sidebar').classList.toggle('collapsed');
    });

    // Show modal and fetch data
    function showModal(status) {
      const body = document.getElementById('modalBody');
      body.innerHTML = '<tr><td colspan="3" class="text-center">Memuat...</td></tr>';
      fetch(`fetch_siswa.php?status=${status}&unit=<?=urlencode($unit)?>`)
        .then(r=>r.json())
        .then(data=>{
          if(!data.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>';
            return;
          }
          body.innerHTML = data.map((s,i)=>
            `<tr><td>${i+1}</td><td>${s.nama}</td><td>${s.status_pembayaran}</td></tr>`
          ).join('');
        })
        .catch(()=>{
          body.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Gagal memuat.</td></tr>';
        });
      new bootstrap.Modal('#statusModal').show();
    }

    // Chart.js
    const ctx = document.getElementById('chartBayar').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Sudah Bayar','Belum Bayar'],
        datasets: [{
          data: [<?=$stat['bayar']?>,<?=$stat['belum']?>],
          backgroundColor: [getComputedStyle(document.documentElement).getPropertyValue('--color-success'),
                            getComputedStyle(document.documentElement).getPropertyValue('--color-danger')],
          hoverOffset:20
        }]
      },
      options: {
        responsive:true,
        plugins: {
          legend:{position:'bottom'},
          tooltip:{
            callbacks:{
              label:ctx=>{
                const v=ctx.raw, t=ctx.dataset.data.reduce((a,b)=>a+b,0),
                      p=((v/t)*100).toFixed(1);
                return `${ctx.label}: ${v} (${p}%)`;
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
