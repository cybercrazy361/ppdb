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
$result = $conn->query('SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC');
while ($row = $result->fetch_assoc()) {
    $tahunList[] = $row['tahun'];
}

// --- Pilih tahun pelajaran (default tahun berjalan) ---
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

// --- Daftar bulan SPP urut (Juli s/d Juni) ---
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

// --- Hitung index bulan SPP terakhir yg harus tampil ---
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

// --- Ambil semua jenis pembayaran (non-SPP) untuk unit ini ---
$jenis_pembayaran = [];
$res = $conn->query(
    "SELECT id, nama FROM jenis_pembayaran WHERE unit='$unit' AND nama!='SPP' ORDER BY id"
);
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran[] = $r;
}

// --- Ambil semua siswa di unit ini ---
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
        'pembayaran' => [],
    ];
}

// --- Inisialisasi pembayaran tiap kolom ke 0 (termasuk Cashback) ---
foreach ($siswa as &$sis) {
    foreach ($jenis_pembayaran as $jp) {
        $sis['pembayaran'][$jp['nama']] = 0;
        if ($jp['nama'] === 'Uang Pangkal') {
            $sis['pembayaran']['Cashback'] = 0;
        }
    }
    foreach ($bulan_spp_dinamis as $bln) {
        $sis['pembayaran']["SPP $bln"] = 0;
    }
}
unset($sis);

// --- Query rekap pembayaran (jumlah + cashback) ---
$sql = "
SELECT 
    s.id AS siswa_id,
    jp.nama AS jenis,
    pd.bulan,
    SUM(pd.jumlah)   AS total_jumlah,
    SUM(pd.cashback) AS total_cashback
FROM siswa s
JOIN pembayaran p ON s.id=p.siswa_id
JOIN pembayaran_detail pd ON p.id=pd.pembayaran_id
JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id=jp.id
WHERE s.unit='$unit'
  AND p.tahun_pelajaran='$tahun_pelajaran'
GROUP BY s.id,jp.nama,pd.bulan
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $sid = $r['siswa_id'];
    $jenis = $r['jenis'];
    $bulan = $r['bulan'];
    if ($jenis === 'SPP' && $bulan && in_array($bulan, $bulan_spp_dinamis)) {
        $siswa[$sid]['pembayaran']["SPP $bulan"] += $r['total_jumlah'];
    } elseif ($jenis !== 'SPP') {
        $siswa[$sid]['pembayaran'][$jenis] += $r['total_jumlah'];
        if ($jenis === 'Uang Pangkal') {
            $siswa[$sid]['pembayaran']['Cashback'] += $r['total_cashback'];
        }
    }
}

// --- Susun daftar kolom dan total per kolom ---
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

$total_kolom = array_fill_keys($kolom_list, 0);
$grand_total = 0;
foreach ($siswa as &$sis) {
    // --- Khusus Uang Pangkal + Cashback
    $total_bayar = 0;
    foreach ($kolom_list as $k) {
        if ($k === 'Uang Pangkal') {
            $uang_pangkal = $sis['pembayaran']['Uang Pangkal'] ?? 0;
            $cashback = $sis['pembayaran']['Cashback'] ?? 0;
            $total_bayar += $uang_pangkal + $cashback;
        } elseif ($k === 'Cashback') {
            // Jangan dijumlahkan ke total_bayar (sudah ikut Uang Pangkal)
        } else {
            $total_bayar += $sis['pembayaran'][$k];
        }
    }
    $sis['total_bayar'] = $total_bayar;
    foreach ($kolom_list as $k) {
        if ($k === 'Uang Pangkal') {
            $uang_pangkal = $sis['pembayaran']['Uang Pangkal'] ?? 0;
            $cashback = $sis['pembayaran']['Cashback'] ?? 0;
            $total_kolom[$k] += $uang_pangkal + $cashback;
        } elseif ($k === 'Cashback') {
            $total_kolom[$k] += $sis['pembayaran']['Cashback'];
        } else {
            $total_kolom[$k] += $sis['pembayaran'][$k];
        }
    }
    $grand_total += $total_bayar;
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
      body * { visibility:hidden; }
      .printable-area, .printable-area * { visibility:visible; box-shadow:none!important; }
      .printable-area { position:absolute; top:0; left:0; width:100%; background:#fff!important;}
      .no-print { display:none; }
      .table-responsive { overflow:visible!important; }
    }
    .table thead th, .table tfoot td, .table tbody td {
      vertical-align:middle; text-align:center;
    }
    .table-info {
      background:rgb(112, 194, 238) !important;
    }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="main-content p-4">
  <nav class="navbar navbar-expand navbar-light bg-white mb-4 shadow-sm">
    <button id="sidebarToggle" class="btn btn-link rounded-circle"><i class="fas fa-bars"></i></button>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item"><a class="nav-link" href="#"><span class="me-2"><?= htmlspecialchars(
          $_SESSION['nama']
      ) ?></span><i class="fas fa-user-circle fa-lg"></i></a></li>
    </ul>
  </nav>
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3 text-gray-800">Rekap Pembayaran Siswa - <?= htmlspecialchars(
          $unit
      ) ?></h1>
      <button class="btn btn-success no-print" onclick="printTable()"><i class="fas fa-print"></i> Cetak</button>
    </div>
    <form method="get" class="mb-3">
      <label><b>Tahun Pelajaran:</b></label>
      <select name="tahun_pelajaran" onchange="this.form.submit()" class="form-select d-inline-block w-auto ms-2">
        <?php foreach ($tahunList as $tp): ?>
          <option value="<?= $tp ?>" <?= $tp == $tahun_pelajaran
    ? 'selected'
    : '' ?>><?= $tp ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <div class="card shadow mb-4 printable-area">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-primary">
              <tr>
                <th colspan="<?= 4 +
                    count($kolom_list) +
                    1 ?>" class="text-center fs-5 fw-bold">
                  Tahun Pelajaran: <?= htmlspecialchars($tahun_pelajaran) ?>
                </th>
              </tr>
              <tr>
                <th>No</th>
                <th>No Formulir</th>
                <th>Nama Siswa</th>
                <th>Status</th>
                <?php foreach ($kolom_list as $k): ?>
                  <th><?= $k ?></th>
                <?php endforeach; ?>
                <th>Total Bayar</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;
            foreach ($siswa as $sis): ?>
            <tr<?= $sis['status_ppdb'] === 'ppdb bersama'
                ? ' class="table-info"'
                : '' ?>>
              <td><?= $no++ ?></td>
              <td><?= $sis['no_formulir'] ?></td>
              <td style="text-align:left;"><?= $sis['nama'] ?></td>
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
                  <?php if ($k === 'Uang Pangkal') {
                      $uang_pangkal = $sis['pembayaran']['Uang Pangkal'] ?? 0;
                      $cashback = $sis['pembayaran']['Cashback'] ?? 0;
                      $total_up = $uang_pangkal + $cashback;
                      echo $total_up > 0
                          ? 'Rp ' . number_format($total_up, 0, ',', '.')
                          : '-';
                  } elseif ($k === 'Cashback') {
                      echo $sis['pembayaran'][$k] > 0
                          ? 'Rp ' .
                              number_format($sis['pembayaran'][$k], 0, ',', '.')
                          : '-';
                  } else {
                      echo $sis['pembayaran'][$k] > 0
                          ? 'Rp ' .
                              number_format($sis['pembayaran'][$k], 0, ',', '.')
                          : '-';
                  } ?>
                </td>
              <?php endforeach; ?>
              <td><b><?= $sis['total_bayar'] > 0
                  ? 'Rp ' . number_format($sis['total_bayar'], 0, ',', '.')
                  : '-' ?></b></td>
            </tr>
            <?php endforeach;
            ?>
            </tbody>
            <tfoot>
              <tr class="table-secondary fw-bold">
                <td colspan="4" class="text-center">Total</td>
                <?php foreach ($kolom_list as $k): ?>
                  <td>
                    <?php if ($k === 'Uang Pangkal') {
                        echo $total_kolom[$k] > 0
                            ? 'Rp ' .
                                number_format($total_kolom[$k], 0, ',', '.')
                            : '-';
                    } elseif ($k === 'Cashback') {
                        echo $total_kolom[$k] > 0
                            ? 'Rp ' .
                                number_format($total_kolom[$k], 0, ',', '.')
                            : '-';
                    } else {
                        echo $total_kolom[$k] > 0
                            ? 'Rp ' .
                                number_format($total_kolom[$k], 0, ',', '.')
                            : '-';
                    } ?>
                  </td>
                <?php endforeach; ?>
                <td>
                  <?= $grand_total > 0
                      ? 'Rp ' . number_format($grand_total, 0, ',', '.')
                      : '-' ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<footer class="footer bg-white text-center py-3">&copy; <?= date(
    'Y'
) ?> Sistem Keuangan PPDB</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar.js"></script>
<script>function printTable(){window.print()}</script>
</body>
</html>
