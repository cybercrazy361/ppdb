<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// Ambil total siswa pada unit tersebut
$sql_total = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param('s', $unit);
$stmt_total->execute();
$result_total = $stmt_total->get_result()->fetch_assoc();
$total_siswa = $result_total['total'] ?? 0;
$stmt_total->close();

// Ambil jumlah siswa yang sudah membayar (punya status_pembayaran = 'Lunas' atau 'Angsuran ke-x')
$sql_sudah = "
SELECT COUNT(DISTINCT s.id) AS sudah_bayar 
FROM siswa s
INNER JOIN pembayaran p ON s.id = p.siswa_id
INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
";
$stmt_sudah = $conn->prepare($sql_sudah);
$stmt_sudah->bind_param('s', $unit);
$stmt_sudah->execute();
$result_sudah = $stmt_sudah->get_result()->fetch_assoc();
$total_sudah_bayar = $result_sudah['sudah_bayar'] ?? 0;
$stmt_sudah->close();

// Ambil jumlah siswa yang belum membayar (tidak ada pembayaran lunas atau angsuran)
$sql_belum = "
SELECT COUNT(DISTINCT s.id) AS belum_bayar
FROM siswa s
WHERE s.unit = ?
  AND NOT EXISTS (
    SELECT 1 
    FROM pembayaran p
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    WHERE p.siswa_id = s.id
      AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
  )
";
$stmt_belum = $conn->prepare($sql_belum);
$stmt_belum->bind_param('s', $unit);
$stmt_belum->execute();
$result_belum = $stmt_belum->get_result()->fetch_assoc();
$total_belum_bayar = $result_belum['belum_bayar'] ?? 0;
$stmt_belum->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard_keuangan_styles.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link" href="#">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="dashboard-title">Dashboard Keuangan</h1>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card total-card shadow h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="card-icon bg-primary text-white">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="card-title">Total Siswa</h5>
                                <h2 class="card-text"><?php echo htmlspecialchars($total_siswa); ?></h2>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">Jumlah total siswa</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card paid-card shadow h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="card-icon bg-success text-white">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="card-title">Sudah Membayar</h5>
                                <h2 class="card-text"><?php echo htmlspecialchars($total_sudah_bayar); ?></h2>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">Siswa yang telah membayar</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card unpaid-card shadow h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="card-icon bg-danger text-white">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="card-title">Belum Membayar</h5>
                                <h2 class="card-text"><?php echo htmlspecialchars($total_belum_bayar); ?></h2>
                            </div>
                        </div>
                        <div class="card-footer">
                            <small class="text-muted">Siswa yang belum membayar</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart Pembayaran -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h3 class="mb-0">Statistik Pembayaran</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:40vh; width:80vw">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>

    <script>
    const ctx = document.getElementById('paymentChart').getContext('2d');

    const paymentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Sudah Membayar', 'Belum Membayar'],
            datasets: [{
                data: [<?php echo $total_sudah_bayar; ?>, <?php echo $total_belum_bayar; ?>],
                backgroundColor: ['#28a745', '#dc3545'],
                hoverBackgroundColor: ['#218838', '#c82333'],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 14,
                            family: 'Nunito, sans-serif'
                        },
                        color: '#333'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const label = context.label;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(2);
                            return `${label}: ${value} siswa (${percentage}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true
            }
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
