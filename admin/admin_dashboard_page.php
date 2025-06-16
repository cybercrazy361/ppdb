<?php
// admin/admin_dashboard_page.php
session_start();

// Set header untuk mencegah caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Pengecekan apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header('Location: admin_login_page.php');
    exit();
}

// Sertakan koneksi ke database dengan path yang benar
include '../database_connection.php';

// Mendapatkan username admin dari sesi
$username = $_SESSION['username'];

// Query untuk mendapatkan statistik keseluruhan

// 1. Total Pengguna (Pimpinan, Petugas Pendaftaran, Petugas Keuangan)
$sql_total_pengguna = "
    SELECT 
        (SELECT COUNT(*) FROM pimpinan) +
        (SELECT COUNT(*) FROM petugas) +
        (SELECT COUNT(*) FROM keuangan) AS total_pengguna
";
$stmt_total_pengguna = $conn->prepare($sql_total_pengguna);
$stmt_total_pengguna->execute();
$result_total_pengguna = $stmt_total_pengguna->get_result();
$total_pengguna = $result_total_pengguna->fetch_assoc()['total_pengguna'];
$stmt_total_pengguna->close();

// 2. Total Pendaftaran
$sql_total_pendaftaran =
    'SELECT COUNT(*) AS total_pendaftaran FROM calon_pendaftar';
$stmt_total_pendaftaran = $conn->prepare($sql_total_pendaftaran);
$stmt_total_pendaftaran->execute();
$result_total_pendaftaran = $stmt_total_pendaftaran->get_result();
$total_pendaftaran = $result_total_pendaftaran->fetch_assoc()[
    'total_pendaftaran'
];
$stmt_total_pendaftaran->close();

// 3. Laporan Bulanan
$sql_laporan_bulanan =
    'SELECT COUNT(*) AS laporan_bulanan FROM pembayaran WHERE MONTH(tanggal_pembayaran) = MONTH(CURRENT_DATE())';
$stmt_laporan_bulanan = $conn->prepare($sql_laporan_bulanan);
$stmt_laporan_bulanan->execute();
$result_laporan_bulanan = $stmt_laporan_bulanan->get_result();
$laporan_bulanan = $result_laporan_bulanan->fetch_assoc()['laporan_bulanan'];
$stmt_laporan_bulanan->close();

// 4. Total Pengguna Multi-Akses (yang punya lebih dari 1 role/unit di akses_petugas)
$sql_multi_akses = "
    SELECT COUNT(DISTINCT petugas_username) AS total_multi_akses
    FROM (
        SELECT petugas_username
        FROM akses_petugas
        GROUP BY petugas_username
        HAVING COUNT(*) > 1
    ) AS t
";
$stmt_multi_akses = $conn->prepare($sql_multi_akses);
$stmt_multi_akses->execute();
$result_multi_akses = $stmt_multi_akses->get_result();
$total_multi_akses = $result_multi_akses->fetch_assoc()['total_multi_akses'];
$stmt_multi_akses->close();

// Tutup koneksi (nanti buka lagi untuk tabel bawah)
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Tambahkan beberapa gaya kustom jika diperlukan */
        .custom-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .custom-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        .section-title {
            margin-bottom: 30px;
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">PPDB Online SMA/SMK DHARMA KARYA - Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard_page.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php"><i
                                class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <!-- Section: Statistics -->
        <div class="statistics">
            <h2 class="section-title">Dashboard Statistik Semua Unit</h2>
            <div class="row text-center">
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-users text-primary fa-3x"></i>
                            <h5 class="card-title mt-3">Total Pengguna</h5>
                            <p class="card-text display-6"><?php echo $total_pengguna; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-file-alt text-success fa-3x"></i>
                            <h5 class="card-title mt-3">Total Pendaftaran</h5>
                            <p class="card-text display-6"><?php echo $total_pendaftaran; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-chart-line text-warning fa-3x"></i>
                            <h5 class="card-title mt-3">Laporan Bulanan</h5>
                            <p class="card-text display-6"><?php echo $laporan_bulanan; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-user-shield text-info fa-3x"></i>
                            <h5 class="card-title mt-3">Petugas Multi-Akses</h5>
                            <p class="card-text display-6"><?php echo $total_multi_akses; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: User Management -->
        <div class="mt-5">
            <h2 class="section-title">Manajemen Pengguna Semua Unit</h2>
            <div class="row text-center">
                <!-- Manajemen Pimpinan -->
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-user-tie text-info fa-3x"></i>
                            <h5 class="card-title mt-3">Login Pimpinan</h5>
                            <p class="card-text">Kelola akun login pimpinan SMA & SMK.</p>
                            <a href="manage_pimpinan.php" class="btn btn-primary">Kelola Pimpinan</a>
                        </div>
                    </div>
                </div>
                <!-- Manajemen Petugas Pendaftaran -->
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-user-edit text-danger fa-3x"></i>
                            <h5 class="card-title mt-3">Login Petugas Pendaftaran</h5>
                            <p class="card-text">Kelola akun petugas pendaftaran SMA & SMK.</p>
                            <a href="manage_pendaftaran.php" class="btn btn-primary">Kelola Pendaftaran</a>
                        </div>
                    </div>
                </div>
                <!-- Manajemen Petugas Keuangan -->
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-money-check-alt text-success fa-3x"></i>
                            <h5 class="card-title mt-3">Login Petugas Keuangan</h5>
                            <p class="card-text">Kelola akun petugas keuangan SMA & SMK.</p>
                            <a href="manage_keuangan.php" class="btn btn-primary">Kelola Keuangan</a>
                        </div>
                    </div>
                </div>
                <!-- Manajemen Petugas Call Center -->
                <div class="col-md-3 mb-4">
                    <div class="card custom-card">
                        <div class="card-body">
                            <i class="fas fa-headset text-warning fa-3x"></i>
                            <h5 class="card-title mt-3">Login Petugas Call Center</h5>
                            <p class="card-text">Kelola akun petugas Call Center SMA & SMK.</p>
                            <a href="manage_callcenter.php" class="btn btn-primary">Kelola Call Center</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- List Petugas Multi-Akses -->
        <div class="mt-5">
            <h4 class="section-title">Daftar Petugas dengan Multi-Akses</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Daftar Akses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Re-open koneksi
                        include '../database_connection.php';
                        $q = "SELECT petugas_username, GROUP_CONCAT(CONCAT(role, ' (', unit, ')') SEPARATOR ', ') AS akses
                                FROM akses_petugas
                                GROUP BY petugas_username
                                HAVING COUNT(*) > 1";
                        $multi = $conn->query($q);
                        $no = 1;
                        while ($r = $multi->fetch_assoc()) {
                            echo '<tr>
                                    <td>' .
                                $no++ .
                                '</td>
                                    <td>' .
                                htmlspecialchars($r['petugas_username']) .
                                '</td>
                                    <td>' .
                                htmlspecialchars($r['akses']) .
                                '</td>
                                </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer bg-light text-center py-3 mt-5">
        <p>&copy; 2024 PPDB Online. All Rights Reserved.</p>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
