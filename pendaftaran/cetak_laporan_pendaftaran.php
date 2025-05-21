<?php
session_start();
include '../database_connection.php';

// Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Filter unit dan status
$filter_unit = isset($_GET['unit']) ? $_GET['unit'] : $_SESSION['unit'];
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'Semua';

// Validasi
$allowed_units = ['SMA', 'SMK'];
if (!in_array($filter_unit, $allowed_units)) $filter_unit = $_SESSION['unit'];
$allowed_status = ['Semua', 'Lunas', 'Angsuran', 'Belum Bayar'];
if (!in_array($filter_status, $allowed_status)) $filter_status = 'Semua';

// Fungsi format tanggal
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    $bulan = [
        'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
        'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
        'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $date = date('d', strtotime($tanggal));
    $month = $bulan[date('F', strtotime($tanggal))];
    $year = date('Y', strtotime($tanggal));
    return "$date $month $year";
}

// Fungsi statistik pembayaran (realtime)
function getStatusPembayaranCounts($conn, $unit) {
    // Total siswa
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Belum bayar
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS belum
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND pd.id IS NULL
    ");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $belum = $stmt->get_result()->fetch_assoc()['belum']; $stmt->close();

    // Sudah bayar (Lunas/Angsuran)
    $stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran = 'Lunas' THEN s.id END) AS lunas,
            COUNT(DISTINCT CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN s.id END) AS angsuran
        FROM siswa s
        LEFT JOIN pembayaran p ON s.id = p.siswa_id
        LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
        WHERE s.unit = ? AND (pd.status_pembayaran = 'Lunas' OR pd.status_pembayaran LIKE 'Angsuran%')
    ");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

    return [
        'total'     => $total,
        'belum'     => $belum,
        'lunas'     => $row['lunas'],
        'angsuran'  => $row['angsuran']
    ];
}
$stat = getStatusPembayaranCounts($conn, $filter_unit);

// Query detail siswa by status
$query = "SELECT 
    s.*, 
    COALESCE(
        (SELECT p.metode_pembayaran 
         FROM pembayaran p 
         WHERE p.siswa_id = s.id 
         ORDER BY p.tanggal_pembayaran DESC LIMIT 1),
        'Belum Ada'
    ) AS metode_pembayaran,
    CASE 
        WHEN COUNT(pd.id) = 0 THEN 'Belum Bayar'
        WHEN SUM(CASE WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) > 0 THEN 'Lunas'
        ELSE 'Angsuran'
    END AS status_pembayaran
    FROM siswa s
    LEFT JOIN pembayaran p ON s.id = p.siswa_id
    LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    WHERE s.unit = ?
    GROUP BY s.id
";
if ($filter_status != 'Semua') {
    $query .= " HAVING status_pembayaran = ?";
}
$query .= " ORDER BY s.id DESC";

// Pagination
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$query .= " LIMIT ? OFFSET ?";

// Prepare & bind
if ($filter_status != 'Semua') {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssii', $filter_unit, $filter_status, $limit, $offset);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sii', $filter_unit, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Pagination: count total sesuai filter
$count_query = "SELECT COUNT(*) AS total FROM (
    SELECT s.id FROM siswa s
    LEFT JOIN pembayaran p ON s.id = p.siswa_id
    LEFT JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    WHERE s.unit = ?
    GROUP BY s.id
    HAVING 
        (? = 'Semua')
        OR (? = 'Lunas' AND SUM(CASE WHEN pd.status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) > 0)
        OR (? = 'Angsuran' AND SUM(CASE WHEN pd.status_pembayaran LIKE 'Angsuran%' THEN 1 ELSE 0 END) > 0)
        OR (? = 'Belum Bayar' AND COUNT(pd.id) = 0)
) AS sub";
$stmt_count = $conn->prepare($count_query);
$stmt_count->bind_param('sssss', $filter_unit, $filter_status, $filter_status, $filter_status, $filter_status);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Fungsi status label & icon
function getStatusPembayaranLabel($status) {
    $status = strtolower($status);
    if ($status == 'lunas') return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Lunas</span>';
    if ($status == 'angsuran') return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle"></i> Angsuran</span>';
    if ($status == 'belum bayar') return '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
    return '<span class="badge bg-secondary">Tidak Diketahui</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendaftaran Siswa <?= htmlspecialchars($filter_unit) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap, FontAwesome, Google Fonts, Custom CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/laporan_pendaftaran_styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .summary-cards {display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem;}
        .summary-card {
            flex:1 1 160px; min-width:170px; padding:1.2rem 1rem; border-radius:1.1rem;
            background: #f7fafc; box-shadow: 0 2px 8px rgba(30,144,255,0.06);
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            font-weight:600; position:relative;
        }
        .summary-card i {font-size:2rem; margin-bottom:8px;}
        .summary-card.lunas      {border-left:4px solid #198754;}
        .summary-card.angsuran   {border-left:4px solid #ffc107;}
        .summary-card.belum      {border-left:4px solid #dc3545;}
        .summary-card.total      {border-left:4px solid #0d6efd;}
        .filter-bar {margin-bottom: 24px; display: flex; align-items: center; gap: 1rem;}
        @media(max-width: 600px) {
            .summary-cards {flex-direction:column;}
            .filter-bar {flex-direction:column;align-items:flex-start;}
        }
    </style>
</head>
<body>
<div class="container mt-3" id="printableArea">
    <!-- Header Laporan -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-2 text-center">
            <img src="../assets/images/logo_trans.png" alt="Logo Institusi" class="img-fluid logo-institusi">
        </div>
        <div class="col-md-8 text-center">
            <h2>Laporan Pendaftaran Siswa <?= htmlspecialchars($filter_unit) ?></h2>
            <p>Tanggal Cetak: <?= formatTanggalIndonesia(date('Y-m-d')); ?></p>
        </div>
        <div class="col-md-2 text-center d-none d-md-block"></div>
    </div>

    <!-- Summary Cards (Statistik) -->
    <div class="summary-cards">
        <div class="summary-card total">
            <i class="fas fa-users text-primary"></i>
            <div>Total Siswa</div>
            <div class="fs-4"><?= $stat['total'] ?></div>
        </div>
        <div class="summary-card lunas">
            <i class="fas fa-check-circle text-success"></i>
            <div>Lunas</div>
            <div class="fs-5"><?= $stat['lunas'] ?></div>
        </div>
        <div class="summary-card angsuran">
            <i class="fas fa-exclamation-circle text-warning"></i>
            <div>Angsuran</div>
            <div class="fs-5"><?= $stat['angsuran'] ?></div>
        </div>
        <div class="summary-card belum">
            <i class="fas fa-times-circle text-danger"></i>
            <div>Belum Bayar</div>
            <div class="fs-5"><?= $stat['belum'] ?></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="get" class="filter-bar">
        <div>
            <select name="unit" class="form-select">
                <?php foreach ($allowed_units as $u): ?>
                    <option value="<?= $u ?>" <?= $filter_unit==$u?'selected':''?>><?= $u ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select name="status" class="form-select">
                <?php foreach ($allowed_status as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status==$s?'selected':''?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Tampilkan</button>
    </form>

    <!-- Tabel Detail Siswa -->
    <div class="row mb-3">
        <div class="col-12">
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
                <?php if ($result->num_rows > 0): $no = $offset + 1; ?>
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

<!-- Tombol Cetak & Kembali -->
<div class="container mb-3 text-center no-print">
    <button class="btn btn-primary btn-print" onclick="window.print();"><i class="fas fa-print"></i> Cetak Laporan</button>
    <a href="dashboard_pendaftaran.php" class="btn btn-secondary btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<!-- Pagination -->
<div class="container mb-3">
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&unit=<?= urlencode($filter_unit) ?>&status=<?= urlencode($filter_status) ?>">Previous</a>
            </li>
            <?php
            $adjacents = 2;
            $start = ($page - $adjacents) > 1 ? $page - $adjacents : 1;
            $end = ($page + $adjacents) < $total_pages ? $page + $adjacents : $total_pages;
            if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">1</a></li>';
                if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            for ($i = $start; $i <= $end; $i++) {
                if ($i == $page) echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                else echo '<li class="page-item"><a class="page-link" href="?page=' . $i . '&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">' . $i . '</a></li>';
            }
            if ($end < $total_pages) {
                if ($end < ($total_pages - 1)) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&unit=' . urlencode($filter_unit) . '&status=' . urlencode($filter_status) . '">' . $total_pages . '</a></li>';
            }
            ?>
            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&unit=<?= urlencode($filter_unit) ?>&status=<?= urlencode($filter_status) ?>">Next</a>
            </li>
        </ul>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
