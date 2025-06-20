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
$sql_total = 'SELECT COUNT(*) AS total FROM siswa WHERE unit = ?';
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param('s', $unit);
$stmt_total->execute();
$result_total = $stmt_total->get_result()->fetch_assoc();
$total_siswa = $result_total['total'] ?? 0;
$stmt_total->close();

// Hitung jumlah siswa PPDB Bersama
$sql_ppdb_bersama = "
SELECT COUNT(*) AS ppdb_bersama
FROM siswa s
LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
WHERE s.unit = ? AND LOWER(cp.status) = 'ppdb bersama'
";
$stmt_ppdb = $conn->prepare($sql_ppdb_bersama);
$stmt_ppdb->bind_param('s', $unit);
$stmt_ppdb->execute();
$result_ppdb = $stmt_ppdb->get_result()->fetch_assoc();
$total_ppdb_bersama = $result_ppdb['ppdb_bersama'] ?? 0;
$stmt_ppdb->close();

// Hitung belum bayar (kecualikan ppdb bersama)
$total_belum_bayar = $total_siswa - $total_sudah_bayar - $total_ppdb_bersama;
if ($total_belum_bayar < 0) {
    $total_belum_bayar = 0;
}

// Ambil jumlah siswa yang sudah membayar
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

// Hitung sisa
$total_belum_bayar = $total_siswa - $total_sudah_bayar;

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard_keuangan_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content p-4">
        <nav class="navbar navbar-expand navbar-light bg-white mb-4 shadow-sm">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="#">
                        <span class="me-2"><?= htmlspecialchars(
                            $_SESSION['nama']
                        ) ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <h1 class="h3 text-dark mb-4">Dashboard Keuangan</h1>

        <div class="row g-4 mb-4 dashboard-cards">
            <div class="col-12 col-md-4">
                <div class="card total-card text-center shadow-sm">
                    <div class="card-body">
                        <div class="card-icon mb-2">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="card-title">Total Siswa</h5>
                        <p class="card-text fs-4"><?= htmlspecialchars(
                            $total_siswa
                        ) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card paid-card text-center shadow-sm">
                    <div class="card-body">
                        <div class="card-icon mb-2">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h5 class="card-title">Sudah Membayar</h5>
                        <p class="card-text fs-4"><?= htmlspecialchars(
                            $total_sudah_bayar
                        ) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card unpaid-card text-center shadow-sm">
                    <div class="card-body">
                        <div class="card-icon mb-2">
                            <i class="fas fa-user-times"></i>
                        </div>
                        <h5 class="card-title">Belum Membayar</h5>
                        <p class="card-text fs-4"><?= htmlspecialchars(
                            $total_belum_bayar
                        ) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card ppdb-card text-center shadow-sm">
                <div class="card-body">
                    <div class="card-icon mb-2">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h5 class="card-title">PPDB Bersama</h5>
                    <p class="card-text fs-4"><?= htmlspecialchars(
                        $total_ppdb_bersama
                    ) ?></p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Statistik Pembayaran</h3>
            </div>
            <div class="card-body">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>

    <footer class="footer text-center">
        &copy; <?= date('Y') ?> Sistem Keuangan PPDB
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        const ctx = document.getElementById('paymentChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
            labels: ['Sudah Membayar', 'PPDB Bersama', 'Belum Membayar'],
            datasets: [{
                data: [<?= $total_sudah_bayar ?>, <?= $total_ppdb_bersama ?>, <?= $total_belum_bayar ?>]
            }]

            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
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
