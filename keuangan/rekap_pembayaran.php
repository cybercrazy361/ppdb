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

// --- Ambil filter metode pembayaran
$filter_metode = isset($_GET['metode'])
    ? strtolower(trim($_GET['metode']))
    : 'all';

// --- Daftar bulan SPP urut ---
list($awal_tahun, $akhir_tahun) = explode('/', $tahun_pelajaran);
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

// --- Hitung index bulan SPP terakhir yang harus tampil ---
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

// --- 1. Ambil semua jenis pembayaran (non-SPP) untuk unit ini ---
$jenis_pembayaran = [];
$res = $conn->query(
    "SELECT id,nama FROM jenis_pembayaran WHERE unit='$unit' AND nama!='SPP' ORDER BY id"
);
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran[] = $r;
}

// --- 2. Ambil semua siswa di unit ini + status ppdb + metode pembayaran ---
$siswa = [];
$res = $conn->query("
  SELECT s.id, s.no_formulir, s.nama, COALESCE(LOWER(TRIM(cp.status)), '') as status_ppdb
  FROM siswa s
  LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
  WHERE s.unit='$unit'
  ORDER BY s.nama
");
while ($r = $res->fetch_assoc()) {
    $siswa[$r['id']] = [
        'no_formulir' => $r['no_formulir'],
        'nama' => $r['nama'],
        'status_ppdb' => $r['status_ppdb'],
        'metode_pembayaran' => '-', // default
        'pembayaran' => [],
    ];
}

// --- 2b. Ambil metode pembayaran terakhir untuk tiap siswa tahun berjalan ---
$res = $conn->query("
    SELECT s.id AS siswa_id, p.metode_pembayaran
    FROM siswa s
    LEFT JOIN pembayaran p ON s.id=p.siswa_id AND p.tahun_pelajaran='$tahun_pelajaran'
    WHERE s.unit='$unit'
    AND p.id = (
        SELECT MAX(p2.id)
        FROM pembayaran p2
        WHERE p2.siswa_id = s.id AND p2.tahun_pelajaran='$tahun_pelajaran'
    )
");
while ($r = $res->fetch_assoc()) {
    if (isset($siswa[$r['siswa_id']]) && $r['metode_pembayaran']) {
        $siswa[$r['siswa_id']]['metode_pembayaran'] = strtolower(
            trim($r['metode_pembayaran'])
        );
    }
}

// --- 3. Inisialisasi pembayaran tiap kolom ke 0 (termasuk Cashback) ---
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

// --- 4. Query rekap pembayaran (jumlah + cashback) ---
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
        if ($jenis === 'Uang Pangkal') {
            $siswa[$sid]['pembayaran'][$jenis] += $r['total_jumlah'];
            $siswa[$sid]['pembayaran']['Cashback'] += $r['total_cashback'];
        } else {
            $siswa[$sid]['pembayaran'][$jenis] += $r['total_jumlah'];
        }
    }
}

// --- 5. Susun daftar kolom dan total per kolom ---
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

// --- 6. Filter siswa by metode pembayaran jika dipilih
$filtered_siswa = [];
foreach ($siswa as $id => $sis) {
    if (
        $filter_metode == 'all' ||
        $sis['metode_pembayaran'] == $filter_metode
    ) {
        $filtered_siswa[$id] = $sis;
    }
}

// --- 7. Hitung total kolom & total bayar (hanya yang difilter)
$total_kolom = array_fill_keys($kolom_list, 0);
$grand_total = 0;
foreach ($filtered_siswa as &$sis) {
    $sis['total_bayar'] = 0;
    foreach ($kolom_list as $k) {
        // Untuk kolom total bayar: Uang Pangkal dikurangi Cashback, kolom Cashback tidak dijumlahkan
        if ($k === 'Uang Pangkal') {
            $sis['total_bayar'] +=
                $sis['pembayaran']['Uang Pangkal'] -
                $sis['pembayaran']['Cashback'];
            $grand_total +=
                $sis['pembayaran']['Uang Pangkal'] -
                $sis['pembayaran']['Cashback'];
        } elseif ($k !== 'Cashback') {
            $sis['total_bayar'] += $sis['pembayaran'][$k];
            $grand_total += $sis['pembayaran'][$k];
        }
        $total_kolom[$k] += $sis['pembayaran'][$k];
    }
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
  @page {
    size: landscape;
    margin: 0mm;
  }
  html, body {
    font-size: 11px !important;
    background: #fff !important;
    color: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1.2 !important;
    width: 100% !important;
    height: 100% !important;
  }
  .printable-area, .printable-area * {
    visibility: visible !important;
    box-shadow: none !important;
    font-size: 11px !important;
  }
  .printable-area {
    position: absolute !important;
    top: 0; left: 0;
    width: 100% !important;
    background: #fff !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .table {
    font-size: 10px !important;
    margin: 0 !important;
    width: 100% !important;
    page-break-inside: auto !important;
  }
  .table th, .table td {
    padding: 2px 4px !important;
    font-size: 10px !important;
    line-height: 1.2 !important;
    vertical-align: middle !important;
    text-align: center !important;
  }
  .table .nama-col, .nama-col {
    text-align: left !important;
    font-size: 10px !important;
    padding-left: 8px !important;
  }
  .table-bordered th, .table-bordered td {
    border: 1px solid #888 !important;
  }
  .h3, h1, h2 {
    font-size: 15px !important;
    margin: 0 0 4px 0 !important;
  }
  .no-print, .no-print * {
    display: none !important;
  }
  .card, .card-body, .main-content, .container-fluid {
    box-shadow: none !important;
    background: #fff !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  nav, .sidebar, .footer, .navbar, .sidebar *, .footer * {
    display: none !important;
  }
  .table-responsive {
    overflow: visible !important;
  }
  /* Total hanya di halaman terakhir (setelah tabel), bukan di tfoot tabel utama */
  .total-print-only { display: block !important; page-break-before: always; }
  tfoot { display: none !important; }
}
.total-print-only { display: none; }
.table thead th, .table tfoot td, .table tbody td {
  vertical-align:middle; text-align:center;
}
.table-info {
  background:rgb(112, 194, 238) !important;
}
.nama-col { text-align: left; }
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
    <form method="get" class="row row-cols-lg-auto g-2 align-items-center mb-3">
      <div class="col-auto">
        <label><b>Tahun Pelajaran:</b></label>
        <select name="tahun_pelajaran" onchange="this.form.submit()" class="form-select d-inline-block w-auto ms-2">
          <?php foreach ($tahunList as $tp): ?>
            <option value="<?= $tp ?>" <?= $tp == $tahun_pelajaran
    ? 'selected'
    : '' ?>><?= $tp ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <label><b>Metode:</b></label>
        <select name="metode" onchange="this.form.submit()" class="form-select d-inline-block w-auto ms-2">
          <option value="all"<?= $filter_metode == 'all'
              ? ' selected'
              : '' ?>>Semua</option>
          <option value="cash"<?= $filter_metode == 'cash'
              ? ' selected'
              : '' ?>>Cash</option>
          <option value="transfer"<?= $filter_metode == 'transfer'
              ? ' selected'
              : '' ?>>Transfer</option>
        </select>
      </div>
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
              Tahun Pelajaran: <?= htmlspecialchars($tahun_pelajaran) ?> | 
              Metode: <?= $filter_metode == 'all'
                  ? 'Semua'
                  : ucfirst($filter_metode) ?>
            </th>
          </tr>
          <tr>
            <th>No</th>
            <th>No Formulir</th>
            <th class="nama-col">Nama Siswa</th>
            <th>Metode Pembayaran</th>
            <?php foreach ($kolom_list as $k): ?>
              <th<?= $k == 'Cashback'
                  ? ' class="bg-warning text-dark"'
                  : '' ?>><?= $k ?></th>
            <?php endforeach; ?>
            <th>Total Bayar</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        foreach ($filtered_siswa as $sis): ?>
        <tr<?= $sis['status_ppdb'] === 'ppdb bersama'
            ? ' class="table-info"'
            : '' ?>>
          <td><?= $no++ ?></td>
          <td><?= $sis['no_formulir'] ?></td>
          <td class="nama-col"><?= $sis['nama'] ?></td>
          <td><?= htmlspecialchars(ucfirst($sis['metode_pembayaran'])) ?></td>
          <?php foreach ($kolom_list as $k): ?>
            <td<?= $k == 'Cashback' ? ' class="bg-warning text-dark"' : '' ?>>
              <?= $sis['pembayaran'][$k] > 0
                  ? 'Rp ' . number_format($sis['pembayaran'][$k], 0, ',', '.')
                  : '-' ?>
            </td>
          <?php endforeach; ?>
          <td><b><?= $sis['total_bayar'] > 0
              ? 'Rp ' . number_format($sis['total_bayar'], 0, ',', '.')
              : '-' ?></b></td>
        </tr>
        <?php endforeach;
        ?>
        </tbody>
      </table>
      <!-- TOTAL HANYA DI HALAMAN AKHIR CETAK -->
      <div class="total-print-only" style="display:none">
        <table class="table table-bordered" style="margin-top:2px; width:100%">
          <tr class="table-secondary fw-bold">
            <td colspan="4" class="text-center">Total</td>
            <?php foreach ($kolom_list as $k): ?>
              <td<?= $k == 'Cashback' ? ' class="bg-warning text-dark"' : '' ?>>
                <?= $total_kolom[$k] > 0
                    ? 'Rp ' . number_format($total_kolom[$k], 0, ',', '.')
                    : '-' ?>
              </td>
            <?php endforeach; ?>
            <td>
              <?= $grand_total > 0
                  ? 'Rp ' . number_format($grand_total, 0, ',', '.')
                  : '-' ?>
            </td>
          </tr>
        </table>
      </div>
      <!-- END TOTAL -->
    </div>
  </div>
</div>
<footer class="footer bg-white text-center py-3">&copy; <?= date(
    'Y'
) ?> Sistem Keuangan PPDB</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar.js"></script>
<script>
function printTable(){
  window.print();
}
</script>
</body>
</html>
