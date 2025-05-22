<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// --- 1. Ambil semua jenis pembayaran (non-SPP) ---
$jenis_pembayaran = [];
$result = $conn->query("SELECT id, nama FROM jenis_pembayaran WHERE unit='$unit' AND nama != 'SPP' ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $jenis_pembayaran[] = $row;
}

// --- 2. Daftar Bulan SPP ---
$bulan_spp = ['Juli','Agustus','September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni'];

// --- 3. Ambil semua siswa di unit ini ---
$siswa = [];
$result = $conn->query("SELECT id, no_formulir, nama FROM siswa WHERE unit='$unit' ORDER BY nama");
while ($row = $result->fetch_assoc()) {
    $siswa[$row['id']] = [
        'no_formulir' => $row['no_formulir'],
        'nama' => $row['nama'],
        'pembayaran' => []
    ];
}

// --- 4. Inisialisasi pembayaran tiap kolom ke 0 ---
foreach ($siswa as &$sis) {
    foreach ($jenis_pembayaran as $jp) {
        $sis['pembayaran'][$jp['nama']] = 0;
    }
    foreach ($bulan_spp as $bln) {
        $sis['pembayaran']["SPP $bln"] = 0;
    }
}
unset($sis);

// --- 5. Query rekap semua pembayaran (group by siswa, jenis, bulan) ---
$sql = "
    SELECT s.id AS siswa_id, jp.nama AS jenis, pd.bulan, SUM(pd.jumlah) AS total
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit='$unit'
    GROUP BY s.id, jp.nama, pd.bulan
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sid = $row['siswa_id'];
        $jenis = $row['jenis'];
        $bulan = $row['bulan'];
        $total = $row['total'];
        if ($jenis == 'SPP' && $bulan) {
            $siswa[$sid]['pembayaran']["SPP $bulan"] += $total;
        } elseif ($jenis != 'SPP') {
            $siswa[$sid]['pembayaran'][$jenis] += $total;
        }
    }
}

// --- 6. Susun daftar kolom dan total per kolom ---
$kolom_list = [];
foreach ($jenis_pembayaran as $jp) $kolom_list[] = $jp['nama'];
foreach ($bulan_spp as $bln) $kolom_list[] = "SPP $bln";

$total_kolom = [];
foreach ($kolom_list as $k) $total_kolom[$k] = 0;
$grand_total = 0;

foreach ($siswa as &$sis) {
    $sis['total_bayar'] = 0;
    foreach ($kolom_list as $k) {
        $sis['total_bayar'] += $sis['pembayaran'][$k];
        $total_kolom[$k] += $sis['pembayaran'][$k];
    }
    $grand_total += $sis['total_bayar'];
}
unset($sis);

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pembayaran Siswa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard_keuangan_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable-area, .printable-area * { visibility: visible; box-shadow: none !important; }
            .printable-area { position: absolute; left: 0; top: 0; width: 100%; background: #fff !important; }
            .no-print { display: none; }
            .table-responsive { overflow: visible !important; }
        }
        /* Table head stick (optional, biar tabel tetap rapi pas scroll banyak kolom) */
        .table thead th { vertical-align: middle; text-align: center; }
        .table tfoot td { vertical-align: middle; text-align: center; }
        .table tbody td { vertical-align: middle; text-align: center; }
    </style>
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
                    <span class="me-2"><?= htmlspecialchars($_SESSION['nama']); ?></span>
                    <i class="fas fa-user-circle fa-lg"></i>
                </a>
            </li>
        </ul>
    </nav>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                Rekap Pembayaran Siswa - <?= htmlspecialchars($unit) ?>
            </h1>
            <button type="button" class="btn btn-success no-print" onclick="printTable()">
                <i class="fas fa-print"></i> Cetak
            </button>
        </div>
        <div class="card shadow mb-4 printable-area">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="rekapTable">
                        <thead class="table-primary">
                            <tr>
                                <th>No</th>
                                <th>No Formulir</th>
                                <th>Nama Siswa</th>
                                <?php foreach ($kolom_list as $k): ?>
                                    <th><?= htmlspecialchars($k) ?></th>
                                <?php endforeach; ?>
                                <th>Total Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; foreach ($siswa as $sis): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($sis['no_formulir']) ?></td>
                                <td style="text-align:left;"><?= htmlspecialchars($sis['nama']) ?></td>
                                <?php foreach ($kolom_list as $k): ?>
                                    <td><?= $sis['pembayaran'][$k] > 0 ? 'Rp '.number_format($sis['pembayaran'][$k],0,',','.') : '-' ?></td>
                                <?php endforeach; ?>
                                <td><b><?= $sis['total_bayar'] > 0 ? 'Rp '.number_format($sis['total_bayar'],0,',','.') : '-' ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-secondary fw-bold">
                                <td colspan="3" align="center">Total</td>
                                <?php foreach ($kolom_list as $k): ?>
                                    <td><?= $total_kolom[$k] > 0 ? 'Rp '.number_format($total_kolom[$k],0,',','.') : '-' ?></td>
                                <?php endforeach; ?>
                                <td><?= $grand_total > 0 ? 'Rp '.number_format($grand_total,0,',','.') : '-' ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<footer class="footer bg-white text-center py-3">
    &copy; <?= date('Y'); ?> Sistem Keuangan PPDB
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function printTable() {
        window.print();
    }
</script>
</body>
</html>
