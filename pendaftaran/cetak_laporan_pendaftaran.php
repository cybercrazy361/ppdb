<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$filter_unit = isset($_GET['unit']) ? $_GET['unit'] : $_SESSION['unit'];
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'Semua';

$allowed_units = ['SMA', 'SMK'];
if (!in_array($filter_unit, $allowed_units)) $filter_unit = $_SESSION['unit'];
$allowed_status = ['Semua', 'Lunas', 'Angsuran', 'Belum Bayar'];
if (!in_array($filter_status, $allowed_status)) $filter_status = 'Semua';

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

// Statistik dashboard (biarkan tetap)
function getStatusPembayaranCounts($conn, $unit) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit = ?");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    // Belum bayar (belum ada Uang Pangkal & SPP Juli Lunas)
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS belum FROM siswa s
        WHERE s.unit=? AND NOT EXISTS (
            SELECT 1 FROM pembayaran_detail pd1
            JOIN pembayaran p1 ON pd1.pembayaran_id=p1.id
            WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
        ) AND NOT EXISTS (
            SELECT 1 FROM pembayaran_detail pd2
            JOIN pembayaran p2 ON pd2.pembayaran_id=p2.id
            WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
        )
    ");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $belum = $stmt->get_result()->fetch_assoc()['belum']; $stmt->close();

    // Lunas: sudah dua2nya
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS lunas FROM siswa s
        WHERE s.unit=?
        AND EXISTS (
            SELECT 1 FROM pembayaran_detail pd1
            JOIN pembayaran p1 ON pd1.pembayaran_id=p1.id
            WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
        ) AND EXISTS (
            SELECT 1 FROM pembayaran_detail pd2
            JOIN pembayaran p2 ON pd2.pembayaran_id=p2.id
            WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
        )
    ");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $lunas = $stmt->get_result()->fetch_assoc()['lunas']; $stmt->close();

    // Angsuran: salah satu saja yang lunas
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS angsuran FROM siswa s
        WHERE s.unit=?
        AND (
            (EXISTS (
                SELECT 1 FROM pembayaran_detail pd1
                JOIN pembayaran p1 ON pd1.pembayaran_id=p1.id
                WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
            ) AND NOT EXISTS (
                SELECT 1 FROM pembayaran_detail pd2
                JOIN pembayaran p2 ON pd2.pembayaran_id=p2.id
                WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
            )) OR
            (NOT EXISTS (
                SELECT 1 FROM pembayaran_detail pd1
                JOIN pembayaran p1 ON pd1.pembayaran_id=p1.id
                WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
            ) AND EXISTS (
                SELECT 1 FROM pembayaran_detail pd2
                JOIN pembayaran p2 ON pd2.pembayaran_id=p2.id
                WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
            ))
        )
    ");
    $stmt->bind_param("s", $unit); $stmt->execute();
    $angsuran = $stmt->get_result()->fetch_assoc()['angsuran']; $stmt->close();

    return ['total_siswa'=>$total,'belum_bayar'=>$belum,'sudah_bayar_lunas'=>$lunas,'sudah_bayar_angsuran'=>$angsuran];
}
$statistik = getStatusPembayaranCounts($conn, $filter_unit);

$totalSiswa = $statistik['total_siswa'];
$belumBayar = $statistik['belum_bayar'];
$sudahBayarLunas = $statistik['sudah_bayar_lunas'];
$sudahBayarAngsuran = $statistik['sudah_bayar_angsuran'];

// Ambil data siswa + status pembayaran logika baru
$query = "
    SELECT s.*,
    -- Cek status pembayaran Uang Pangkal Lunas
    (SELECT COUNT(*) FROM pembayaran_detail pd1
        JOIN pembayaran p1 ON pd1.pembayaran_id=p1.id
        WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
    ) AS lunas_uang_pangkal,
    -- Cek status pembayaran SPP Juli Lunas
    (SELECT COUNT(*) FROM pembayaran_detail pd2
        JOIN pembayaran p2 ON pd2.pembayaran_id=p2.id
        WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
    ) AS lunas_spp_juli,
    -- Metode pembayaran terakhir
    COALESCE((
        SELECT p.metode_pembayaran FROM pembayaran p
        WHERE p.siswa_id=s.id ORDER BY p.tanggal_pembayaran DESC LIMIT 1
    ),'Belum Ada') AS metode_pembayaran
    FROM siswa s
    WHERE s.unit=?
";

$rows = [];
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $filter_unit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Tentukan status pembayaran sesuai logika:
    $lunas_uang_pangkal = (int)$row['lunas_uang_pangkal'];
    $lunas_spp_juli     = (int)$row['lunas_spp_juli'];

    if ($lunas_uang_pangkal > 0 && $lunas_spp_juli > 0) {
        $row['status_pembayaran'] = 'Lunas';
    } elseif ($lunas_uang_pangkal > 0 || $lunas_spp_juli > 0) {
        $row['status_pembayaran'] = 'Angsuran';
    } else {
        $row['status_pembayaran'] = 'Belum Bayar';
    }
    $rows[] = $row;
}
unset($stmt);unset($result);

// Filter data sesuai status jika bukan Semua
if ($filter_status != 'Semua') {
    $rows = array_filter($rows, function($row) use($filter_status) {
        return $row['status_pembayaran'] == $filter_status;
    });
}

// Pagination manual
$total_records = count($rows);
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page-1)*$limit;
$paged_rows = array_slice($rows, $offset, $limit);
$total_pages = ceil($total_records/$limit);

// Status badge
function getStatusPembayaranLabel($status) {
    if ($status == 'Lunas') return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Lunas</span>';
    if ($status == 'Angsuran') return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle"></i> Angsuran</span>';
    if ($status == 'Belum Bayar') return '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
    return '<span class="badge bg-secondary">Tidak Diketahui</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendaftaran Siswa <?= htmlspecialchars($filter_unit) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/laporan_pendaftaran_styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
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
        <!-- Filter Bar -->
        <form method="get" class="filter-bar mb-3 d-flex flex-wrap gap-2">
            <select name="unit" class="form-select" style="max-width:150px">
                <?php foreach ($allowed_units as $u): ?>
                    <option value="<?= $u ?>" <?= $filter_unit==$u?'selected':''?>><?= $u ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" style="max-width:170px">
                <?php foreach ($allowed_status as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status==$s?'selected':''?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Tampilkan</button>
        </form>
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
                        <?php if (count($paged_rows) > 0): $no = $offset + 1; ?>
                            <?php foreach ($paged_rows as $row): ?>
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
                            <?php endforeach; ?>
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
    <div class="container mb-3 text-center no-print">
        <button class="btn btn-primary btn-print" onclick="window.print();"><i class="fas fa-print"></i> Cetak Laporan</button>
        <a href="dashboard_pendaftaran.php" class="btn btn-secondary btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    <div class="container mb-3">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&unit=<?= urlencode($filter_unit) ?>&status=<?= urlencode($filter_status) ?>" tabindex="-1">Previous</a>
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
