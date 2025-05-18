<!-- File: dashboard_keuangan.php -->
<?php
session_start();
include '../database_connection.php';
if (!isset($_SESSION['username']) || $_SESSION['role']!=='keuangan') {
    header('Location: login_keuangan.php'); exit();
}
$unit = $_SESSION['unit'];
// Ambil data statistik
$stmt = $conn->prepare("SELECT COUNT(*) total FROM siswa WHERE unit = ?");
$stmt->bind_param('s',$unit); $stmt->execute();
$total_siswa = $stmt->get_result()->fetch_assoc()['total'] ?? 0; $stmt->close();

$stmt = $conn->prepare("
  SELECT COUNT(DISTINCT s.id) as sudah 
  FROM siswa s
  JOIN pembayaran p ON s.id=p.siswa_id
  JOIN pembayaran_detail pd ON p.id=pd.pembayaran_id
  WHERE s.unit=? AND (pd.status_pembayaran='Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
");
$stmt->bind_param('s',$unit); $stmt->execute();
$total_sudah = $stmt->get_result()->fetch_assoc()['sudah'] ?? 0; $stmt->close();

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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <?php include '../includes/sidebar.php'; ?>

  <main class="main-content">
    <header class="topbar shadow-sm">
      <div class="container-fluid d-flex align-items-center justify-content-between">
        <h1 class="page-title">Dashboard Keuangan</h1>
        <div class="user-dropdown">
          <span class="me-2"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
          <i class="fas fa-user-circle fa-lg"></i>
        </div>
      </div>
    </header>

    <section class="container-fluid pt-4">
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="icon bg-primary">
                  <i class="fas fa-users"></i>
                </div>
                <div class="ps-3">
                  <h5>Total Siswa</h5>
                  <h2><?php echo $total_siswa; ?></h2>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <small>Jumlah seluruh siswa unit <?php echo htmlspecialchars($unit); ?></small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="icon bg-success">
                  <i class="fas fa-user-check"></i>
                </div>
                <div class="ps-3">
                  <h5>Sudah Membayar</h5>
                  <h2><?php echo $total_sudah; ?></h2>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <small>Siswa dengan status pembayaran lengkap/angsuran</small>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card stat-card shadow-sm h-100">
            <div class="card-body">
              <div class="d-flex align-items-center">
                <div class="icon bg-danger">
                  <i class="fas fa-user-times"></i>
                </div>
                <div class="ps-3">
                  <h5>Belum Membayar</h5>
                  <h2><?php echo $total_belum; ?></h2>
                </div>
              </div>
            </div>
            <div class="card-footer">
              <small>Siswa yang belum melakukan pembayaran</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card mt-4 shadow-sm">
        <div class="card-header">
          <h3>Statistik Pembayaran</h3>
        </div>
        <div class="card-body">
          <canvas id="paymentChart" style="height:300px;"></canvas>
        </div>
      </div>
    </section>

    <footer class="footer text-center py-3 shadow-sm">
      &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/sidebar.js"></script>
  <script>
    // Sidebar toggle
    document.getElementById('sidebarToggle').addEventListener('click',function(){
      document.querySelector('.sidebar').classList.toggle('collapsed');
      document.querySelector('.main-content').classList.toggle('collapsed');
      document.querySelector('.footer').classList.toggle('collapsed');
    });

    // Chart.js
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx,{
      type:'doughnut',
      data:{
        labels:['Sudah Membayar','Belum Membayar'],
        datasets:[{
          data:[<?php echo $total_sudah;?>,<?php echo $total_belum;?>],
          backgroundColor:['#1abc9c','#e74c3c'],
          hoverOffset:10
        }]
      },
      options:{
        responsive:true,
        plugins:{
          legend:{position:'bottom',labels:{padding:20,font:{size:14}}},
          tooltip:{callbacks:{label(ctx){let v=ctx.raw,T=ctx.dataset.data.reduce((a,b)=>a+b),p=(v/T*100).toFixed(1);return`${ctx.label}: ${v} (${p}%)`;}}}
        }
      }
    });
  </script>
</body>
</html>
