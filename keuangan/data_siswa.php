<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

// Ambil unit petugas dari session
$unit = $_SESSION['unit'];

// Ambil filter dari query parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Query berdasarkan filter
if ($filter === 'sudah_bayar') {
    $sql = "SELECT * FROM siswa WHERE unit = ? AND status_pembayaran = 'Lunas'";
} elseif ($filter === 'belum_bayar') {
    $sql = "SELECT * FROM siswa WHERE unit = ? AND status_pembayaran = 'Pending'";
} else {
    $sql = "SELECT * FROM siswa WHERE unit = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/dashboard_keuangan_styles.css">
    <link rel="stylesheet" href="../assets/styles/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <main class="content">
            <div class="container-fluid mt-4">
                <h1>Data Siswa</h1>
                <p class="text-muted">Unit: <strong><?php echo htmlspecialchars($unit); ?></strong></p>
                <hr>

                <!-- Tabel Data Siswa -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4>
                            <?php
                            if ($filter === 'sudah_bayar') {
                                echo 'Siswa Sudah Membayar';
                            } elseif ($filter === 'belum_bayar') {
                                echo 'Siswa Belum Membayar';
                            } else {
                                echo 'Total Siswa';
                            }
                            ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>No Formulir</th>
                                    <th>Nama</th>
                                    <th>Jenis Kelamin</th>
                                    <th>Status Pembayaran</th>
                                    <th>Metode Pembayaran</th>
                                    <th>Tanggal Pendaftaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($row['no_formulir']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($row['jenis_kelamin']); ?></td>
                                            <td>
                                                <?php
                                                if ($row['status_pembayaran'] === 'Lunas') {
                                                    echo '<span class="badge bg-success">Lunas</span>';
                                                } elseif ($row['status_pembayaran'] === 'Cicilan') {
                                                    echo '<span class="badge bg-warning text-dark">Cicilan</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Pending</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['metode_pembayaran'] ?: 'Belum memilih'); ?></td>
                                            <td><?php echo htmlspecialchars($row['tanggal_pendaftaran']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Tidak ada data siswa untuk kategori ini.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

 
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
