<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit dari sesi login
$unit = $_SESSION['unit']; // Misalnya SMA atau SMK

// Fungsi untuk menghitung status pembayaran
function getStatusPembayaranCounts($conn, $unit) {
    // Total Siswa
    $sqlTotalSiswa = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
    $stmtTotal = $conn->prepare($sqlTotalSiswa);
    $stmtTotal->bind_param("s", $unit);
    $stmtTotal->execute();
    $resultTotal = $stmtTotal->get_result()->fetch_assoc()['total'];
    $stmtTotal->close();

    // Belum Bayar: Siswa tanpa pembayaran_detail
    $sqlBelumBayar = "
        SELECT COUNT(DISTINCT s.id) AS total 
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND pd.id IS NULL
    ";
    $stmtBelumBayar = $conn->prepare($sqlBelumBayar);
    $stmtBelumBayar->bind_param("s", $unit);
    $stmtBelumBayar->execute();
    $belumBayar = $stmtBelumBayar->get_result()->fetch_assoc()['total'];
    $stmtBelumBayar->close();

    // Sudah Bayar: Siswa yang status_pembayaran adalah 'Lunas' atau 'Angsuran ke-N'
    $sqlSudahBayar = "
        SELECT 
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran = 'Lunas' THEN s.id END) AS total_lunas,
            COUNT(DISTINCT s.id) AS total_sudah_bayar
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ";
    $stmtSudahBayar = $conn->prepare($sqlSudahBayar);
    $stmtSudahBayar->bind_param("s", $unit);
    $stmtSudahBayar->execute();
    $sudahBayarData = $stmtSudahBayar->get_result()->fetch_assoc();
    $sudahBayarLunas = $sudahBayarData['total_lunas'];
    $totalSudahBayar = $sudahBayarData['total_sudah_bayar'];
    $stmtSudahBayar->close();

    // Hitung Sudah Bayar Angsuran sebagai total_sudah_bayar - sudah_bayar_lunas
    $sudahBayarAngsuran = $totalSudahBayar - $sudahBayarLunas;
    if ($sudahBayarAngsuran < 0) {
        $sudahBayarAngsuran = 0;
    }

    return [
        'total_siswa' => $resultTotal,
        'belum_bayar' => $belumBayar,
        'sudah_bayar_lunas' => $sudahBayarLunas,
        'sudah_bayar_angsuran' => $sudahBayarAngsuran,
        'total_sudah_bayar' => $totalSudahBayar
    ];
}

$statistik = getStatusPembayaranCounts($conn, $unit);
$totalSiswa = $statistik['total_siswa'];
$belumBayar = $statistik['belum_bayar'];
$sudahBayarLunas = $statistik['sudah_bayar_lunas'];
$sudahBayarAngsuran = $statistik['sudah_bayar_angsuran'];
$totalSudahBayar = $statistik['total_sudah_bayar'];

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
    <!-- Header -->
    <header class="bg-primary text-white p-3 shadow">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <!-- Bagian Kiri: Dashboard dan Nama Pengguna -->
            <div class="d-flex flex-column">
                <h4 class="mb-1">Dashboard <?= htmlspecialchars($unit) ?></h4>
                <small>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?></small>
            </div>
            <!-- Bagian Kanan: Tombol Logout -->
            <a href="../logout/logout_pendaftaran.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </header>


    <!-- Main Content -->
    <div class="container mt-3">
        <!-- Statistik -->
        <div class="row g-4">
            <!-- Total Siswa -->
            <div class="col-md-4">
                <div class="card text-center shadow">
                    <div class="card-body">
                        <i class="fas fa-user-graduate fa-2x text-primary mb-3"></i>
                        <h5 class="card-title">Total Siswa</h5>
                        <h3 class="card-count"><?= htmlspecialchars($totalSiswa) ?></h3>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">Total Siswa yang mendaftar</small>
                    </div>
                </div>
            </div>
            <!-- Sudah Bayar -->
            <div class="col-md-4">
                <div class="card text-center shadow">
                    <div class="card-body">
                        <i class="fas fa-money-bill-alt fa-2x text-success mb-3"></i>
                        <h5 class="card-title">Sudah Membayar</h5>
                        <h3 class="card-count"><?= htmlspecialchars($totalSudahBayar) ?></h3>
                        <p class="card-text">
                            <span><i class="fas fa-check-circle text-success"></i> <?= htmlspecialchars($sudahBayarLunas) ?> Lunas</span><br>
                            <span><i class="fas fa-exclamation-circle text-warning"></i> <?= htmlspecialchars($sudahBayarAngsuran) ?> Angsuran</span>
                        </p>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">Siswa yang telah membayar</small>
                    </div>
                </div>
            </div>
            <!-- Belum Bayar -->
            <div class="col-md-4">
                <div class="card text-center shadow cursor-pointer" onclick="showModal('belum')">
                    <div class="card-body">
                        <i class="fas fa-money-check fa-2x text-danger mb-3"></i>
                        <h5 class="card-title">Belum Bayar</h5>
                        <h3 class="card-count"><?= htmlspecialchars($belumBayar) ?></h3>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">Siswa yang belum membayar</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diagram Pembayaran -->
        <div class="row mt-3">
            <div class="col">
                <div class="card p-4 shadow text-center">
                    <h6>Diagram Pembayaran</h6>
                    <div class="chart-container">
                        <canvas id="grafikPembayaran"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Navigasi -->
        <div class="row g-3 mt-3 text-center">
            <div class="col-md-3">
                <div class="card shadow text-center p-4">
                    <i class="fas fa-user-plus fa-2x text-primary mb-3"></i>
                    <h6>Input Pendaftaran</h6>
                    <a href="form_pendaftaran.php" class="btn btn-primary btn-sm mt-2">Input</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow text-center p-4">
                    <i class="fas fa-users fa-2x text-info mb-3"></i>
                    <h6>Daftar Siswa</h6>
                    <a href="daftar_siswa.php" class="btn btn-info btn-sm mt-2">Lihat</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow text-center p-4">
                    <i class="fas fa-file-alt fa-2x text-warning mb-3"></i>
                    <h6>Cetak Laporan</h6>
                    <a href="cetak_laporan_pendaftaran.php" class="btn btn-warning btn-sm mt-2">Cetak</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow text-center p-4">
                    <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                    <h6>Review Calon Pendaftar</h6>
                    <a href="review_calon_pendaftar.php" class="btn btn-success btn-sm mt-2">Review</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk Daftar Siswa -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Daftar Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                            <!-- Data akan diisi dengan JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        function showModal(status) {
            const modalTitle = document.getElementById('statusModalLabel');
            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = '';

            if (status === 'sudah') {
                modalTitle.textContent = 'Daftar Siswa Sudah Bayar';
            } else if (status === 'belum') {
                modalTitle.textContent = 'Daftar Siswa Belum Bayar';
            }

            fetch(`fetch_siswa.php?status=${status}&unit=<?= urlencode($unit) ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach((siswa, index) => {
                            modalContent.innerHTML += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${siswa.nama}</td>
                                    <td>${siswa.status_pembayaran}</td>
                                </tr>`;
                        });
                    } else {
                        modalContent.innerHTML = `<tr><td colspan="3" class="text-center">Tidak ada data.</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    modalContent.innerHTML = `<tr><td colspan="3" class="text-center text-danger">Terjadi kesalahan saat memuat data.</td></tr>`;
                });

            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        // Diagram Pembayaran
        const ctx = document.getElementById('grafikPembayaran').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Sudah Membayar', 'Belum Membayar'],
                datasets: [{
                    data: [<?= $totalSudahBayar ?>, <?= $belumBayar ?>],
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
                                family: 'Poppins, sans-serif'
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
