<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// Ambil daftar tahun pelajaran
$tahunList = [];
$result = $conn->query('SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC');
while ($row = $result->fetch_assoc()) {
    $tahunList[] = $row['tahun'];
}

if (
    isset($_GET['tahun_pelajaran']) &&
    in_array($_GET['tahun_pelajaran'], $tahunList)
) {
    $tahun_pelajaran = $_GET['tahun_pelajaran'];
} else {
    $default = '2025/2026';
    if (in_array($default, $tahunList)) {
        $tahun_pelajaran = $default;
    } else {
        $tahun_pelajaran = $tahunList[0];
    }
}

list($awal_tahun, $akhir_tahun) = explode('/', $tahun_pelajaran);

// Daftar bulan SPP urut (Juli s/d Juni)
$bulan_spp = [
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
];

// Hitung index bulan SPP terakhir yg harus tampil
$bulan_now = date('n');
$tahun_now = date('Y');
$idx_terakhir = 11;
if ($tahun_now == $awal_tahun) {
    if ($bulan_now >= 7 && $bulan_now <= 12) {
        $idx_terakhir = $bulan_now - 7;
    } else {
        $idx_terakhir = 0;
    }
} elseif ($tahun_now == $akhir_tahun) {
    if ($bulan_now >= 1 && $bulan_now <= 6) {
        $idx_terakhir = $bulan_now + 5;
    } else {
        $idx_terakhir = 11;
    }
}
$bulan_spp_dinamis = array_slice($bulan_spp, 0, $idx_terakhir + 1);

// Ambil semua jenis pembayaran (non-SPP) untuk unit ini
$jenis_pembayaran = [];
$res = $conn->query(
    "SELECT id, nama FROM jenis_pembayaran WHERE unit='$unit' AND nama!='SPP' ORDER BY id"
);
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran[] = $r;
}

// Ambil semua siswa di unit ini
$siswa = [];
$res = $conn->query("
    SELECT s.id, s.no_formulir, s.nama, cp.status as status_ppdb
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit='$unit'
    ORDER BY s.nama
");
while ($r = $res->fetch_assoc()) {
    $siswa[$r['id']] = [
        'no_formulir' => $r['no_formulir'],
        'nama' => $r['nama'],
        'status_ppdb' => strtolower(trim($r['status_ppdb'] ?? '')),
        'tagihan' => [],
    ];
}

// Inisialisasi tagihan tiap kolom ke 0
foreach ($siswa as &$sis) {
    foreach ($jenis_pembayaran as $jp) {
        $sis['tagihan'][$jp['nama']] = 0;
        if ($jp['nama'] === 'Uang Pangkal') {
            $sis['tagihan']['Cashback'] = 0;
        }
    }
    foreach ($bulan_spp_dinamis as $bln) {
        $sis['tagihan']["SPP $bln"] = 0;
    }
}
unset($sis);

// Ambil nominal tagihan tiap jenis & SPP per bulan
$nominal_pembayaran = [];
$res = $conn->query(
    "SELECT jp.nama, pn.nominal_max, pn.bulan 
     FROM jenis_pembayaran jp 
     LEFT JOIN pengaturan_nominal pn ON pn.jenis_pembayaran_id = jp.id AND pn.unit = '$unit'
     WHERE jp.unit = '$unit'
    "
);
while ($r = $res->fetch_assoc()) {
    if ($r['nama'] == 'SPP' && $r['bulan']) {
        $nominal_pembayaran['SPP ' . $r['bulan']] = (int) $r['nominal_max'];
    } else {
        $nominal_pembayaran[$r['nama']] = (int) $r['nominal_max'];
    }
}

// Query total sudah dibayar & cashback per siswa per jenis per bulan di tahun ajaran ini
$sudah_bayar = [];
$cashback = [];
$res = $conn->query("
    SELECT s.id AS siswa_id, jp.nama AS jenis, pd.bulan, SUM(pd.jumlah) AS total_bayar, SUM(pd.cashback) AS total_cashback
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit = '$unit'
      AND p.tahun_pelajaran = '$tahun_pelajaran'
    GROUP BY s.id, jp.nama, pd.bulan
");
while ($r = $res->fetch_assoc()) {
    $sid = $r['siswa_id'];
    $jenis = $r['jenis'];
    $bulan = $r['bulan'];
    if ($jenis === 'SPP' && $bulan && in_array($bulan, $bulan_spp_dinamis)) {
        $sudah_bayar[$sid]["SPP $bulan"] = (int) $r['total_bayar'];
    } elseif ($jenis !== 'SPP') {
        $sudah_bayar[$sid][$jenis] = (int) $r['total_bayar'];
        if ($jenis === 'Uang Pangkal') {
            $cashback[$sid] = (int) $r['total_cashback'];
        }
    }
}

// Susun daftar kolom
$kolom_list = [];
foreach ($jenis_pembayaran as $jp) {
    $kolom_list[] = $jp['nama'];
    if ($jp['nama'] === 'Uang Pangkal') {
        $kolom_list[] = 'Cashback';
    }
}
foreach ($bulan_spp_dinamis as $bln) {
    $kolom_list[] = "SPP $bln";
}

// Hanya tampilkan siswa yang masih punya tagihan
$siswa_tagihan = [];
foreach ($siswa as $id => $sis) {
    $ada_tagihan = false;
    $tagihan_siswa = [];
    $total_tagihan = 0;
    foreach ($kolom_list as $kol) {
        $nominal = $nominal_pembayaran[$kol] ?? 0;
        $bayar = $sudah_bayar[$id][$kol] ?? 0;
        $cb = $cashback[$id] ?? 0;

        // LOGIKA SISA SESUAI REKAP
        if ($kol === 'Uang Pangkal') {
            $sisa = max(0, $nominal - $bayar - $cb);
        } elseif ($kol === 'Cashback') {
            $sisa = 0;
        } else {
            $sisa = max(0, $nominal - $bayar);
        }
        $tagihan_siswa[$kol] = $sisa;
        if ($sisa > 0 && $kol !== 'Cashback') {
            $ada_tagihan = true;
        }
        if ($kol !== 'Cashback') {
            $total_tagihan += $sisa;
        }
    }
    if ($ada_tagihan) {
        $sis['tagihan'] = $tagihan_siswa;
        $sis['total_tagihan'] = $total_tagihan;
        $siswa_tagihan[] = $sis;
    }
}
unset($siswa);

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Sisa Tagihan Siswa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    @media print { .no-print{display:none;} body{margin:20px;} }
    table { font-size:12pt; }
    th, td { text-align:center; vertical-align:middle; }
    .table-info { background:rgb(112,194,238)!important; }
    </style>
</head>
<body>
<div class="container">

    <!-- Dropdown Tahun Pelajaran -->
    <form method="get" class="row g-2 align-items-end no-print my-3">
        <div class="col-auto">
            <label for="tahun_pelajaran" class="form-label">Tahun Pelajaran</label>
            <select name="tahun_pelajaran" id="tahun_pelajaran" class="form-select" onchange="this.form.submit()">
                <?php foreach ($tahunList as $tp): ?>
                    <option value="<?= htmlspecialchars($tp) ?>" <?= $tp ===
$tahun_pelajaran
    ? 'selected'
    : '' ?>>
                        <?= htmlspecialchars($tp) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <div class="text-center my-4">
        <h3>Rekap Sisa Tagihan Siswa <?= htmlspecialchars($unit) ?></h3>
        <p>Tahun Pelajaran <?= htmlspecialchars($tahun_pelajaran) ?></p>
    </div>

    <div class="alert alert-info">
        <b>Keterangan:</b> Nilai pada kolom di bawah adalah <u>sisa tagihan</u> (jumlah yang belum dibayar per jenis/bulan).
    </div>

    <?php if (empty($siswa_tagihan)): ?>
        <div class="alert alert-success text-center">Tidak ada tagihan.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-primary">
                <tr>
                    <th>No</th>
                    <th>No Formulir</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <?php foreach ($kolom_list as $k): ?>
                        <th><?= $k ?><br>
                        <small>(Sisa: Rp <?= number_format(
                            $nominal_pembayaran[$k] ?? 0,
                            0,
                            ',',
                            '.'
                        ) ?>)</small>
                        </th>
                    <?php endforeach; ?>
                    <th>Total Sisa</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;
            $grand_total = 0;
            foreach ($siswa_tagihan as $sis):
                $grand_total += $sis['total_tagihan']; ?>
            <tr<?= $sis['status_ppdb'] === 'ppdb bersama'
                ? ' class="table-info"'
                : '' ?>>
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($sis['no_formulir']) ?></td>
                <td style="text-align:left;"><?= htmlspecialchars(
                    $sis['nama']
                ) ?></td>
                <td>
                    <?php if ($sis['status_ppdb'] === 'ppdb bersama'): ?>
                        <span class="badge bg-info text-dark">PPDB Bersama</span>
                    <?php elseif ($sis['status_ppdb']): ?>
                        <?= htmlspecialchars($sis['status_ppdb']) ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <?php foreach ($kolom_list as $k): ?>
                    <td>
                        <?= $sis['tagihan'][$k] > 0
                            ? '<b>Rp ' .
                                number_format(
                                    $sis['tagihan'][$k],
                                    0,
                                    ',',
                                    '.'
                                ) .
                                '</b>'
                            : '-' ?>
                    </td>
                <?php endforeach; ?>
                <td>
                    <b><?= $sis['total_tagihan'] > 0
                        ? 'Rp ' .
                            number_format($sis['total_tagihan'], 0, ',', '.')
                        : '-' ?></b>
                </td>
            </tr>
            <?php
            endforeach;
            ?>
            </tbody>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="<?= 4 +
                        count($kolom_list) ?>" class="text-end">
                        Grand Total Sisa:
                    </td>
                    <td>
                        <b>Rp <?= number_format(
                            $grand_total,
                            0,
                            ',',
                            '.'
                        ) ?></b>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4 no-print">
        <button class="btn btn-primary" onclick="window.print()">Cetak</button>
        <a href="daftar_siswa_keuangan.php" class="btn btn-secondary">Kembali</a>
    </div>
</div>
</body>
</html>
