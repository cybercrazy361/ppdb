<?php
session_start();
include '../database_connection.php'; // Pastikan path ini sesuai dengan struktur direktori Anda

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Inisialisasi variabel filter
$filter_unit = isset($_GET['unit']) ? $_GET['unit'] : $_SESSION['unit']; // Default unit dari session
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'Semua';

// Validasi unit
$allowed_units = ['SMA', 'SMK'];
if (!in_array($filter_unit, $allowed_units)) {
    $filter_unit = $_SESSION['unit']; // Fallback ke unit dari session jika nilai tidak valid
}

// Validasi status
$allowed_status = ['Semua', 'Lunas', 'Angsuran', 'Belum Bayar'];
if (!in_array($filter_status, $allowed_status)) {
    $filter_status = 'Semua';
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $date = date('d', strtotime($tanggal));
    $month = $bulan[date('F', strtotime($tanggal))];
    $year = date('Y', strtotime($tanggal));

    return "$date $month $year";
}

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
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN s.id END) AS total_angsuran
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
    $sudahBayarAngsuran = $sudahBayarData['total_angsuran'];
    $stmtSudahBayar->close();

    return [
        'total_siswa' => $resultTotal,
        'belum_bayar' => $belumBayar,
        'sudah_bayar_lunas' => $sudahBayarLunas,
        'sudah_bayar_angsuran' => $sudahBayarAngsuran
    ];
}

$statistik = getStatusPembayaranCounts($conn, $filter_unit);
$totalSiswa = $statistik['total_siswa'];
$belumBayar = $statistik['belum_bayar'];
$sudahBayarLunas = $statistik['sudah_bayar_lunas'];
$sudahBayarAngsuran = $statistik['sudah_bayar_angsuran'];

// Menyiapkan query berdasarkan filter
$query = "SELECT 
            s.*, 
            COALESCE(
                (SELECT p.metode_pembayaran 
                 FROM pembayaran p 
                 WHERE p.siswa_id = s.id 
                 ORDER BY p.tanggal_pembayaran DESC 
                 LIMIT 1),
                'Belum Ada'
            ) AS metode_pembayaran,
            CASE 
                WHEN COUNT(pd.id) = 0 THEN 'Belum Membayar'
                WHEN SUM(CASE WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) > 0 THEN 'Lunas'
                ELSE 'Angsuran'
            END AS status_pembayaran
          FROM siswa s
          LEFT JOIN pembayaran p ON s.id = p.siswa_id
          LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
          WHERE s.unit = ?
          GROUP BY s.id";

// Tambahkan kondisi berdasarkan status filter
if ($filter_status != 'Semua') {
    if ($filter_status == 'Lunas') {
        $query .= " HAVING status_pembayaran = 'Lunas'";
    } elseif ($filter_status == 'Angsuran') {
        $query .= " HAVING status_pembayaran = 'Angsuran'";
    } elseif ($filter_status == 'Belum Bayar') {
        $query .= " HAVING status_pembayaran = 'Belum Membayar'";
    }
}

$query .= " ORDER BY s.id DESC";

// Pagination Settings
$limit = 20; // Jumlah record per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Tambahkan LIMIT dan OFFSET ke query
$query_paginated = $query . " LIMIT ? OFFSET ?";

// Eksekusi query dengan pagination
$stmt = $conn->prepare($query_paginated);
if ($filter_status != 'Semua') {
    $stmt->bind_param('sii', $filter_unit, $limit, $offset);
} else {
    $stmt->bind_param('sii', $filter_unit, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Menghitung total halaman berdasarkan filter yang benar
$count_query = "SELECT COUNT(*) AS total FROM (
                    SELECT s.id
                    FROM siswa s
                    LEFT JOIN pembayaran p ON s.id = p.siswa_id
                    LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
                    WHERE s.unit = ?
                    GROUP BY s.id
                    HAVING 
                        (? = 'Semua') OR 
                        (? = 'Lunas' AND SUM(CASE WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) > 0) OR 
                        (? = 'Angsuran' AND SUM(CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN 1 ELSE 0 END) > 0) OR 
                        (? = 'Belum Bayar' AND COUNT(pd.id) = 0)
                ) AS sub";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param('sssss', $filter_unit, $filter_status, $filter_status, $filter_status, $filter_status);
$stmt_count->execute();
$result_count = $stmt_count->get_result()->fetch_assoc();
$total_records = $result_count['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Fungsi untuk mendapatkan label status pembayaran dengan warna yang berbeda
function getStatusPembayaranLabel($status_pembayaran) {
    if ($status_pembayaran == 'Lunas') {
        return '<span class="badge bg-success">Lunas</span>';
    } elseif ($status_pembayaran == 'Angsuran') {
        return '<span class="badge bg-warning text-dark">Angsuran</span>';
    } elseif ($status_pembayaran == 'Belum Membayar') {
        return '<span class="badge bg-danger">Belum Bayar</span>';
    } else {
        return '<span class="badge bg-secondary">Tidak Diketahui</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Pendaftaran Siswa <?= htmlspecialchars($filter_unit) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/laporan_pendaftaran_styles.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>

<body>
    <div class="container mt-3" id="printableArea">
        <!-- Header Laporan -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-2 text-center">
                <!-- Ganti dengan logo institusi Anda -->
                <img src="../assets/images/logo_trans.png" alt="Logo Institusi" class="img-fluid logo-institusi">
            </div>
            <div class="col-md-8 text-center">
                <h2>Laporan Pendaftaran Siswa <?= htmlspecialchars($filter_unit) ?></h2>
                <p>Tanggal Cetak: <?= formatTanggalIndonesia(date('Y-m-d')); ?></p>
            </div>
            <div class="col-md-2 text-center d-none d-md-block">
                <!-- Placeholder untuk elemen tambahan jika diperlukan -->
            </div>
        </div>

        <!-- Tabel Statistik -->
        <div class="row mb-3">
            <div class="col-12">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th>Total Siswa</th>
                            <td><?= $totalSiswa ?></td>
                        </tr>
                        <tr>
                            <th>Sudah Bayar (Lunas)</th>
                            <td><?= $sudahBayarLunas ?></td>
                        </tr>
                        <tr>
                            <th>Sudah Bayar (Angsuran)</th>
                            <td><?= $sudahBayarAngsuran ?></td>
                        </tr>
                        <tr>
                            <th>Belum Bayar</th>
                            <td><?= $belumBayar ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabel Detail Siswa -->
        <div class="row mb-3">
            <div class="col-12">
                <h4 class="mb-3">Detail Siswa</h4>
                <table class="table table-striped table-bordered detail-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No Formulir</th>
                            <th>Nama</th>
                            <th>Jenis Kelamin</th>
                            <th>Tempat/Tanggal Lahir</th>
                            <th>Asal Sekolah</th>
                            <th>Alamat</th>
                            <th>No HP</th>
                            <th>Status Pembayaran</th>
                            <th>Metode Pembayaran</th>
                            <th>Tanggal Pendaftaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $no = $offset + 1; ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['no_formulir']); ?></td>
                                    <td><?= htmlspecialchars($row['nama']); ?></td>
                                    <td><?= htmlspecialchars($row['jenis_kelamin']); ?></td>
                                    <td><?= htmlspecialchars($row['tempat_lahir']) . ", " . formatTanggalIndonesia($row['tanggal_lahir']); ?></td>
                                    <td><?= htmlspecialchars($row['asal_sekolah']); ?></td>
                                    <td><?= htmlspecialchars($row['alamat']); ?></td>
                                    <td><?= htmlspecialchars($row['no_hp']); ?></td>
                                    <td><?= getStatusPembayaranLabel($row['status_pembayaran']); ?></td>
                                    <td><?= htmlspecialchars($row['metode_pembayaran']); ?></td>
                                    <td><?= formatTanggalIndonesia($row['tanggal_pendaftaran']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">Tidak ada data siswa.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tombol Cetak dan Kembali -->
    <div class="container mb-3 text-center no-print">
        <button class="btn btn-primary btn-print" onclick="window.print();"><i class="fas fa-print"></i> Cetak Laporan</button>
        <a href="dashboard_pendaftaran.php" class="btn btn-secondary btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <!-- Pagination -->
    <div class="container mb-3">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- Link Previous -->
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&unit=<?= urlencode($filter_unit) ?>&status=<?= urlencode($filter_status) ?>" tabindex="-1">Previous</a>
                </li>

                <!-- Link Pages -->
                <?php
                // Tentukan jumlah link halaman yang ingin ditampilkan
                $adjacents = 2;
                $start = ($page - $adjacents) > 1 ? $page - $adjacents : 1;
                $end = ($page + $adjacents) < $total_pages ? $page + $adjacents : $total_pages;

                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $page) {
                        echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                    } else {
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">' . $i . '</a></li>';
                    }
                }

                if ($end < $total_pages) {
                    if ($end < ($total_pages - 1)) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">' . $total_pages . '</a></li>';
                }
                ?>

                <!-- Link Next -->
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&unit=<?= urlencode($filter_unit) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
