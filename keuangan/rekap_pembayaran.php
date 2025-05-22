<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// --- Ambil daftar tahun pelajaran ---
$tahunList = [];
$result = $conn->query("SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC");
while($row = $result->fetch_assoc()) {
    $tahunList[] = $row['tahun'];
}

// --- Pilih tahun pelajaran (default tahun berjalan) ---
if (isset($_GET['tahun_pelajaran']) && in_array($_GET['tahun_pelajaran'], $tahunList)) {
    $tahun_pelajaran = $_GET['tahun_pelajaran'];
} else {
    // Pilih tahun aktif (otomatis sesuai tanggal hari ini)
    $tahun_now = date('Y');
    $bulan_now = date('n');
    if ($bulan_now >= 7) {
        $tahun_pelajaran = $tahun_now . '/' . ($tahun_now+1);
    } else {
        $tahun_pelajaran = ($tahun_now-1) . '/' . $tahun_now;
    }
}
list($awal_tahun, $akhir_tahun) = explode('/', $tahun_pelajaran);

// --- Daftar bulan SPP urut ---
$bulan_spp = [
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'
];

// --- Hitung index bulan SPP terakhir yang harus tampil (dinamis sesuai bulan & tahun berjalan) ---
$bulan_now = date('n');
$tahun_now = date('Y');
$idx_terakhir = 11; // default semua bulan

if ($tahun_now == $awal_tahun) {
    // Tahun ajaran baru mulai Juli-Desember tahun awal
    if ($bulan_now >= 7 && $bulan_now <= 12) {
        $idx_terakhir = $bulan_now - 7;
    } else {
        $idx_terakhir = 0; // kalau bulan < 7, berarti belum ada SPP tahun ajaran baru
    }
} elseif ($tahun_now == $akhir_tahun) {
    // Januari-Juni tahun kedua
    if ($bulan_now >= 1 && $bulan_now <= 6) {
        $idx_terakhir = $bulan_now + 5;
    } else {
        $idx_terakhir = 11;
    }
} else {
    $idx_terakhir = 11;
}
$bulan_spp_dinamis = array_slice($bulan_spp, 0, $idx_terakhir+1);

// --- 1. Ambil semua jenis pembayaran (non-SPP) untuk unit ini ---
$jenis_pembayaran = [];
$result = $conn->query("SELECT id, nama FROM jenis_pembayaran WHERE unit='$unit' AND nama != 'SPP' ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $jenis_pembayaran[] = $row;
}

// --- 2. Ambil semua siswa di unit ini ---
$siswa = [];
$result = $conn->query("SELECT id, no_formulir, nama FROM siswa WHERE unit='$unit' ORDER BY nama");
while ($row = $result->fetch_assoc()) {
    $siswa[$row['id']] = [
        'no_formulir' => $row['no_formulir'],
        'nama' => $row['nama'],
        'pembayaran' => []
    ];
}

// --- 3. Inisialisasi pembayaran tiap kolom ke 0 ---
foreach ($siswa as &$sis) {
    foreach ($jenis_pembayaran as $jp) {
        $sis['pembayaran'][$jp['nama']] = 0;
    }
    foreach ($bulan_spp_dinamis as $bln) {
        $sis['pembayaran']["SPP $bln"] = 0;
    }
}
unset($sis);

// --- 4. Query rekap pembayaran sesuai tahun pelajaran (group by siswa, jenis, bulan) ---
$sql = "
    SELECT s.id AS siswa_id, jp.nama AS jenis, pd.bulan, SUM(pd.jumlah) AS total
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit='$unit'
      AND p.tahun_pelajaran = '$tahun_pelajaran'
    GROUP BY s.id, jp.nama, pd.bulan
";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sid = $row['siswa_id'];
        $jenis = $row['jenis'];
        $bulan = $row['bulan'];
        $total = $row['total'];
        if ($jenis == 'SPP' && $bulan && in_array($bulan, $bulan_spp_dinamis)) {
            $siswa[$sid]['pembayaran']["SPP $bulan"] += $total;
        } elseif ($jenis != 'SPP') {
            $siswa[$sid]['pembayaran'][$jenis] += $total;
        }
    }
}

// --- 5. Susun daftar kolom dan total per kolom ---
$kolom_list = [];
foreach ($jenis_pembayaran as $jp) $kolom_list[] = $jp['nama'];
foreach ($bulan_spp_dinamis as $bln) $kolom_list[] = "SPP $bln";

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
        <!-- Dropdown Tahun Pelajaran -->
        <form method="get" class="mb-3">
            <label for="tahun_pelajaran"><b>Tahun Pelajaran:</b></label>
            <select name="tahun_pelajaran" id="tahun_pelajaran" onchange="this.form.submit()" class="form-select d-inline-block w-auto ms-2">
                <?php foreach ($tahunList as $tp): ?>
                    <option value="<?= htmlspecialchars($tp) ?>" <?= ($tp == $tahun_pelajaran ? "selected" : "") ?>><?= htmlspecialchars($tp) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="card shadow mb-4 printable-area">
            <div class="card-body">
               <div class="table-responsive">
    <table class="table table-bordered table-hover" id="rekapTable">
        <thead class="table-primary">
            <tr>
                <th colspan="<?= 3 + count($kolom_list) + 1 ?>" class="text-center fs-5" style="font-weight:bold;">
                    Tahun Pelajaran: <?= htmlspecialchars($tahun_pelajaran) ?>
                </th>
            </tr>
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
<script src="../assets/js/sidebar.js"></script>

<script>
    function printTable() {
        window.print();
    }
</script>
</body>
</html>
