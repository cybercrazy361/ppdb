<?php
session_start();
include '../database_connection.php';

// Validasi login petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

// === PAKAI LOGIKA STATUS PEMBAYARAN SAMA SEPERTI daftar_siswa.php ===
$uang_pangkal_id = 1;
$spp_id = 2;

function getStats($conn, $unit, $uang_pangkal_id, $spp_id) {
    // Total pendaftar
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $total = $row ? intval($row['total']) : 0;
    }
    $stmt->close();

    // Ambil status pembayaran
    $sql = "
    SELECT s.id,
      CASE
        WHEN 
            (SELECT COUNT(*) FROM pembayaran_detail pd1 
                JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                WHERE p1.siswa_id = s.id 
                  AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                  AND pd1.status_pembayaran = 'Lunas'
            ) > 0
        AND
            (SELECT COUNT(*) FROM pembayaran_detail pd2 
                JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                WHERE p2.siswa_id = s.id 
                  AND pd2.jenis_pembayaran_id = $spp_id
                  AND pd2.bulan = 'Juli'
                  AND pd2.status_pembayaran = 'Lunas'
            ) > 0
        THEN 'Lunas'
        WHEN 
            (
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = s.id 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                ) > 0
                OR
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = s.id 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            )
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
      END AS status_pembayaran
    FROM siswa s
    WHERE s.unit = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $result = $stmt->get_result();

    $lunas = $angsuran = $belum = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['status_pembayaran'] === 'Lunas') $lunas++;
        elseif ($row['status_pembayaran'] === 'Angsuran') $angsuran++;
        else $belum++;
    }
    $stmt->close();

    return [
      'total'    => $total,
      'lunas'    => $lunas,
      'angsuran' => $angsuran,
      'belum'    => $belum,
      'bayar'    => $lunas + $angsuran
    ];
}

$stat = getStats($conn, $unit, $uang_pangkal_id, $spp_id);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard <?=htmlspecialchars($unit)?> – SPMB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/pendaftaran_dashboard_styles.css" />
  <link rel="stylesheet" href="../assets/css/sidebar_pendaftaran_styles.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php $active = 'dashboard'; ?>
  <?php include 'sidebar_pendaftaran.php'; ?>

  <div class="main">
    <header class="navbar">
      <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      <div class="title">Dashboard <?=htmlspecialchars($unit)?></div>
      <div class="user-menu">
        <small>Halo, <?=htmlspecialchars($_SESSION['nama'])?></small>
        <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
      </div>
    </header>

    <section class="dashboard-cards">
      <div class="card" onclick="showModal('total')" style="cursor:pointer;">
        <div class="icon text-primary"><i class="fas fa-user-graduate"></i></div>
        <div class="title">Total Pendaftar</div>
        <div class="count"><?=$stat['total']?></div>
        <div class="subtext">Unit <?=htmlspecialchars($unit)?></div>
      </div>
      <div class="card" onclick="showModal('lunas')" style="cursor:pointer;">
        <div class="icon text-success"><i class="fas fa-check-circle"></i></div>
        <div class="title">Lunas</div>
        <div class="count"><?=$stat['lunas']?></div>
        <div class="subtext">Sudah lunas semua</div>
      </div>
      <div class="card" onclick="showModal('angsuran')" style="cursor:pointer;">
        <div class="icon text-warning"><i class="fas fa-wallet"></i></div>
        <div class="title">Angsuran</div>
        <div class="count"><?=$stat['angsuran']?></div>
        <div class="subtext">Sebagian lunas</div>
      </div>
      <div class="card" onclick="showModal('belum')" style="cursor:pointer;">
        <div class="icon text-danger"><i class="fas fa-exclamation-circle"></i></div>
        <div class="title">Belum Bayar</div>
        <div class="count"><?=$stat['belum']?></div>
        <div class="subtext">Segera follow-up</div>
      </div>
    </section>

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
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/sidebar_pendaftaran.js"></script>
  <script>
      function showModal(status) {
        const body = document.getElementById('modalBody');
        body.innerHTML = '<tr><td colspan="3" class="text-center">Memuat...</td></tr>';
        fetch(`fetch_siswa.php?status=${status}&unit=<?=urlencode($unit)?>`)
          .then(r => r.json())
          .then(data => {
            if(!data.length) {
              body.innerHTML = '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>';
              return;
            }
            body.innerHTML = data.map((s,i) =>
              `<tr><td>${i+1}</td><td>${s.nama}</td><td>${s.status_pembayaran}</td></tr>`
            ).join('');
          })
          .catch(() => {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Gagal memuat.</td></tr>';
          });
        new bootstrap.Modal('#statusModal').show();
      }

      // Chart.js: Atur legend agar lebih terang & modern
      const ctx = document.getElementById('chartBayar').getContext('2d');
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Lunas','Angsuran','Belum Bayar'],
          datasets: [{
            data: [<?=$stat['lunas']?>, <?=$stat['angsuran']?>, <?=$stat['belum']?>],
            backgroundColor: ['#198754','#ffc107','#dc3545'],
            hoverOffset: 20
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                color: '#f7fbff',         // Warna putih terang
                font: {
                  family: "'Poppins', 'Segoe UI', Arial, sans-serif", // Sesuaikan dengan dashboard
                  weight: 'bold',
                  size: 16
                },
                boxWidth: 28,
                padding: 18,
                usePointStyle: true // Lebih modern (bisa diaktifkan/disable sesuai selera)
              }
            },
            tooltip: {
              callbacks: {
                label: ctx => {
                  const v = ctx.raw,
                        t = ctx.dataset.data.reduce((a,b) => a+b, 0),
                        p = ((v/t)*100).toFixed(1);
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
