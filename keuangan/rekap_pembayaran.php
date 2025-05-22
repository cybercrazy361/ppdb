<?php
session_start();
include '../database_connection.php';

// Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}
$unit = $_SESSION['unit'];

// --- 1. Ambil jenis pembayaran dan SPP per bulan ---
$jenis_pembayaran = [];
$spp_bulan = [];

// Jenis pembayaran non-SPP
$result = $conn->query("SELECT id, nama FROM jenis_pembayaran WHERE unit='$unit' AND nama != 'SPP' ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $jenis_pembayaran[] = $row;
}

// SPP per bulan
$bulan_spp = ['Juli','Agustus','September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni'];
foreach ($bulan_spp as $bln) {
    $spp_bulan[] = $bln;
}

// --- 2. Ambil data siswa ---
$siswa = [];
$result = $conn->query("SELECT id, no_formulir, nama FROM siswa WHERE unit='$unit' ORDER BY nama");
while ($row = $result->fetch_assoc()) {
    $siswa[$row['id']] = [
        'no_formulir' => $row['no_formulir'],
        'nama' => $row['nama'],
        'pembayaran' => []
    ];
}

// --- 3. Inisialisasi array rekap & total ---
foreach ($siswa as &$sis) {
    // Inisialisasi tiap jenis pembayaran
    foreach ($jenis_pembayaran as $jp) {
        $sis['pembayaran'][$jp['nama']] = 0;
    }
    foreach ($spp_bulan as $bln) {
        $sis['pembayaran']["SPP $bln"] = 0;
    }
}
unset($sis);

// --- 4. Query semua pembayaran siswa, mapping ke array di atas ---
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

// --- 5. Hitung total per siswa & total per kolom ---
$kolom_list = [];
foreach ($jenis_pembayaran as $jp) $kolom_list[] = $jp['nama'];
foreach ($spp_bulan as $bln) $kolom_list[] = "SPP $bln";

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

// --- 6. Tampilkan tabel ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pembayaran Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="container-fluid mt-4">
        <h1 class="h4 mb-4">Rekap Pembayaran Siswa Unit <?= htmlspecialchars($unit) ?></h1>
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>No</th>
                        <th>No Formulir</th>
                        <th>Nama Siswa</th>
                        <?php foreach ($kolom_list as $k): ?>
                            <th><?= htmlspecialchars($k) ?></th>
                        <?php endforeach; ?>
                        <th><b>Total Bayar</b></th>
                    </tr>
                </thead>
                <tbody>
                <?php $no=1; foreach ($siswa as $sis): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($sis['no_formulir']) ?></td>
                        <td><?= htmlspecialchars($sis['nama']) ?></td>
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
</body>
</html>
