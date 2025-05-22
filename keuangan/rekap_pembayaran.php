<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

$unit = $_SESSION['unit'];

// 1. Ambil daftar tahun pelajaran dari tabel tahun_pelajaran
$tahunList = [];
$res = $conn->query("SELECT tahun FROM tahun_pelajaran ORDER BY tahun DESC");
while ($r = $res->fetch_assoc()) {
    $tahunList[] = $r['tahun'];
}

// 2. Tentukan tahun_pelajaran yang dipilih (via GET) atau default ke tahun ajaran berjalan
if (isset($_GET['tahun_pelajaran']) && in_array($_GET['tahun_pelajaran'], $tahunList)) {
    $tahun_pelajaran = $_GET['tahun_pelajaran'];
} else {
    $y = date('Y');
    $m = date('n');
    if ($m >= 7) {
        $tahun_pelajaran = "$y/".($y+1);
    } else {
        $tahun_pelajaran = ($y-1)."/$y";
    }
}
list($awal_tahun, $akhir_tahun) = explode('/', $tahun_pelajaran);

// 3. Daftar bulan SPP
$bulan_spp = [
    'Juli','Agustus','September','Oktober','November','Desember',
    'Januari','Februari','Maret','April','Mei','Juni'
];
// Hitung slice sesuai bulan & tahun sekarang
$thisY = date('Y'); $thisM = date('n');
$idx = 11;
if ($thisY == $awal_tahun && $thisM >= 7) $idx = $thisM - 7;
elseif ($thisY == $akhir_tahun && $thisM <= 6) $idx = $thisM + 5;
$bulan_spp = array_slice($bulan_spp, 0, $idx+1);

// 4. Ambil semua jenis pembayaran non-SPP
$jenis_pembayaran = [];
$res = $conn->query("SELECT id,nama FROM jenis_pembayaran WHERE unit='$unit' AND nama!='SPP' ORDER BY id");
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran[] = $r;
}

// 5. Ambil semua siswa untuk unit ini
$siswa = [];
$res = $conn->query("SELECT id,no_formulir,nama FROM siswa WHERE unit='$unit' ORDER BY nama");
while ($r = $res->fetch_assoc()) {
    $siswa[$r['id']] = [
        'no_formulir'  => $r['no_formulir'],
        'nama'         => $r['nama'],
        'pembayaran'   => [],
        'total_bayar'  => 0
    ];
}

// 6. Inisialisasi setiap kolom bayar & kolom cashback
foreach ($siswa as &$s) {
    foreach ($jenis_pembayaran as $jp) {
        $s['pembayaran'][$jp['nama']] = 0;
        if ($jp['nama'] === 'Uang Pangkal') {
            $s['pembayaran']['Cashback'] = 0;
        }
    }
    foreach ($bulan_spp as $b) {
        $s['pembayaran']["SPP $b"] = 0;
    }
}
unset($s);

// 7. Rekap data pembayaran + cashback
$sql = "
    SELECT s.id AS sid,
           jp.nama AS jenis,
           pd.bulan,
           SUM(pd.jumlah)   AS tot_jml,
           SUM(pd.cashback) AS tot_cb
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit='$unit'
      AND p.tahun_pelajaran = '$tahun_pelajaran'
    GROUP BY s.id,jp.nama,pd.bulan
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $sid   = $r['sid'];
    $jenis = $r['jenis'];
    $bln   = $r['bulan'];

    if ($jenis === 'SPP' && in_array($bln, $bulan_spp)) {
        $siswa[$sid]['pembayaran']["SPP $bln"] += $r['tot_jml'];
    } else {
        $siswa[$sid]['pembayaran'][$jenis] += $r['tot_jml'];
        if ($jenis === 'Uang Pangkal') {
            $siswa[$sid]['pembayaran']['Cashback'] += $r['tot_cb'];
        }
    }
}

// 8. Susun kolom header dan hitung grand totals
$kolom_list = [];
foreach ($jenis_pembayaran as $jp) {
    $kolom_list[] = $jp['nama'];
    if ($jp['nama'] === 'Uang Pangkal') {
        $kolom_list[] = 'Cashback';
    }
}
foreach ($bulan_spp as $b) {
    $kolom_list[] = "SPP $b";
}

$total_kolom = array_fill_keys($kolom_list, 0);
$grand_total = 0;

foreach ($siswa as &$s) {
    foreach ($kolom_list as $k) {
        $val = $s['pembayaran'][$k];
        $s['total_bayar'] += $val;
        $total_kolom[$k]  += $val;
    }
    $grand_total += $s['total_bayar'];
}
unset($s);

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Pembayaran Siswa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/sidebar.css">
  <style>
    @media print {
      body * { visibility: hidden; }
      .printable-area, .printable-area * { visibility: visible; }
      .no-print { display: none; }
    }
    table th, table td { text-align: center; vertical-align: middle; }
    td.name { text-align: left; }
  </style>
</head>
<body>
  <?php include '../includes/sidebar.php'; ?>

  <div class="container-fluid printable-area my-4">
    <div class="d-flex justify-content-between mb-3">
      <h4>Rekap Pembayaran Siswa — Unit <?=htmlspecialchars($unit)?></h4>
      <button class="btn btn-success no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak
      </button>
    </div>

    <!-- Pilih Tahun Pelajaran -->
    <form method="get" class="mb-3 no-print">
      <label><strong>Tahun Pelajaran:</strong></label>
      <select name="tahun_pelajaran" onchange="this.form.submit()" class="form-select d-inline-block w-auto ms-2">
        <?php foreach ($tahunList as $tp): ?>
          <option value="<?=$tp?>" <?=($tp==$tahun_pelajaran?'selected':'')?>><?=$tp?></option>
        <?php endforeach; ?>
      </select>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="table-primary">
          <tr>
            <th>No</th>
            <th>No Formulir</th>
            <th>Nama Siswa</th>
            <?php foreach ($kolom_list as $k): ?>
              <th><?=htmlspecialchars($k)?></th>
            <?php endforeach; ?>
            <th>Total Bayar</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach ($siswa as $s): ?>
            <tr>
              <td><?=$no++?></td>
              <td><?=htmlspecialchars($s['no_formulir'])?></td>
              <td class="name"><?=htmlspecialchars($s['nama'])?></td>
              <?php foreach ($kolom_list as $k): 
                $v = $s['pembayaran'][$k];
                $fmt = $v>0 ? 'Rp '.number_format($v,0,',','.') : '-';
              ?>
                <td><?=$fmt?></td>
              <?php endforeach; ?>
              <td><strong><?= $s['total_bayar']>0 ? 'Rp '.number_format($s['total_bayar'],0,',','.') : '-' ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
          <tr>
            <td colspan="3" class="text-center"><strong>Total</strong></td>
            <?php foreach ($kolom_list as $k):
              $v = $total_kolom[$k];
              $fmt = $v>0 ? 'Rp '.number_format($v,0,',','.') : '-';
            ?>
              <td><strong><?=$fmt?></strong></td>
            <?php endforeach; ?>
            <td><strong><?=$grand_total>0?'Rp '.number_format($grand_total,0,',','.'):'-'?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    function printTable() {
      window.print();
    }
  </script>
</body>
</html>
