<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit']; // Misalnya 'SMA' atau 'SMK'

// Fungsi menghitung status pembayaran
function getStatusPembayaranCounts($conn, $unit) {
    // Total Siswa
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $totalSiswa = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Belum Bayar
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND pd.id IS NULL
    ");
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $belumBayar = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Sudah Bayar Lunas & Angsuran
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran = 'Lunas' THEN s.id END) AS lunas,
            COUNT(DISTINCT s.id) AS sudah_bayar
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ");
    $stmt->bind_param("s", $unit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $lunas = $row['lunas'];
    $sudahBayar = $row['sudah_bayar'];
    $stmt->close();

    $angsuran = max(0, $sudahBayar - $lunas);

    return [
        'total'       => $totalSiswa,
        'belum'       => $belumBayar,
        'lunas'       => $lunas,
        'angsuran'    => $angsuran,
        'sudah_bayar' => $sudahBayar
    ];
}

$stat = getStatusPembayaranCounts($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

<header>
    <div class="container-fluid">
        <div>
            <h4>Dashboard <?= htmlspecialchars($unit) ?></h4>
            <small>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?></small>
        </div>
        <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
    </div>
</header>

<div class="container">
    <!-- Statistik Utama -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center p-4">
                <div class="card-icon text-primary mx-auto"><i class="fas fa-user-graduate"></i></div>
                <h5 class="card-title">Total Siswa</h5>
                <div class="card-count"><?= $stat['total'] ?></div>
                <div class="card-footer">Total pendaftar <?= htmlspecialchars($unit) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-4">
                <div class="card-icon text-success mx-auto"><i class="fas fa-money-bill-wave"></i></div>
                <h5 class="card-title">Sudah Membayar</h5>
                <div class="card-count"><?= $stat['sudah_bayar'] ?></div>
                <small><i class="fas fa-check-circle text-success"></i> <?= $stat['lunas'] ?> Lunas</small><br>
                <small><i class="fas fa-wallet text-warning"></i> <?= $stat['angsuran'] ?> Angsuran</small>
                <div class="card-footer">Pendaftar yang sudah melakukan pembayaran</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-4" onclick="showModal('belum')" style="cursor:pointer">
                <div class="card-icon text-danger mx-auto"><i class="fas fa-exclamation-circle"></i></div>
                <h5 class="card-title">Belum Bayar</h5>
                <div class="card-count"><?= $stat['belum'] ?></div>
                <div class="card-footer">Pendaftar yang belum membayar</div>
            </div>
        </div>
    </div>

    <!-- Diagram Pembayaran -->
    <div class="card chart-card mt-5">
        <h6>Diagram Pembayaran</h6>
        <div class="chart-container">
            <canvas id="grafikPembayaran"></canvas>
        </div>
    </div>

    <!-- Menu Navigasi Cepat -->
    <div class="nav-menu">
        <div class="card">
            <i class="fas fa-user-plus fa-2x text-primary mb-2"></i>
            <h6>Input Pendaftaran</h6>
            <a href="form_pendaftaran.php" class="btn btn-primary btn-sm">Input</a>
        </div>
        <div class="card">
            <i class="fas fa-users fa-2x text-info mb-2"></i>
            <h6>Daftar Siswa</h6>
            <a href="daftar_siswa.php" class="btn btn-info btn-sm">Lihat</a>
        </div>
        <div class="card">
            <i class="fas fa-file-alt fa-2x text-warning mb-2"></i>
            <h6>Cetak Laporan</h6>
            <a href="cetak_laporan_pendaftaran.php" class="btn btn-warning btn-sm">Cetak</a>
        </div>
        <div class="card">
            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
            <h6>Review Calon Pendaftar</h6>
            <a href="review_calon_pendaftar.php" class="btn btn-success btn-sm">Review</a>
        </div>
    </div>
</div>

<!-- Modal Daftar Siswa Belum/Sudah Bayar -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="statusModalLabel">Daftar Siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Status Pembayaran</th>
            </tr>
          </thead>
          <tbody id="modalContent">
            <!-- Diisi via JavaScript -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap & Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function showModal(status) {
        const title = document.getElementById('statusModalLabel');
        const body  = document.getElementById('modalContent');
        title.textContent = status === 'belum' ? 'Daftar Belum Bayar' : 'Daftar Sudah Bayar';
        body.innerHTML = '<tr><td colspan="3" class="text-center">Memuat...</td></tr>';

        fetch(`fetch_siswa.php?status=${status}&unit=<?= urlencode($unit) ?>`)
            .then(res => res.json())
            .then(data => {
                if (!data.length) {
                    body.innerHTML = '<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>';
                    return;
                }
                body.innerHTML = data.map((s, i) => `
                    <tr>
                        <td>${i+1}</td>
                        <td>${s.nama}</td>
                        <td>${s.status_pembayaran}</td>
                    </tr>
                `).join('');
            })
            .catch(() => {
                body.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Gagal memuat data.</td></tr>';
            });

        new bootstrap.Modal(document.getElementById('statusModal')).show();
    }

    // Chart.js Doughnut
    const ctx = document.getElementById('grafikPembayaran').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Sudah Membayar', 'Belum Bayar'],
            datasets: [{
                data: [<?= $stat['sudah_bayar'] ?>, <?= $stat['belum'] ?>],
                backgroundColor: ['var(--color-success)', 'var(--color-danger)'],
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 20 } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
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
