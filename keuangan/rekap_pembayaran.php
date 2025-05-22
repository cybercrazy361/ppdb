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
    $y = date('Y');
    $m = date('n');
    if ($m >= 7) $tahun_pelajaran = "$y/".($y+1);
    else           $tahun_pelajaran = ($y-1)."/$y";
}
list($awal_tahun, $akhir_tahun) = explode('/', $tahun_pelajaran);

// --- Bulan SPP dinamis (Juli–Juni) ---
$bulan_spp = ['Juli','Agustus','September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni'];
// tentukan slice sesuai bulan sekarang
$idx = 11;
$thisY = date('Y'); $thisM = date('n');
if ($thisY == $awal_tahun && $thisM >= 7)      $idx = $thisM - 7;
elseif ($thisY == $akhir_tahun && $thisM <= 6) $idx = $thisM + 5;
$bulan_spp = array_slice($bulan_spp, 0, $idx+1);

// --- 1. Semua jenis non-SPP ---
$jenis_pembayaran = [];
$r = $conn->query("SELECT id,nama FROM jenis_pembayaran WHERE unit='$unit' AND nama!='SPP' ORDER BY id");
while($row=$r->fetch_assoc()) $jenis_pembayaran[]=$row;

// --- 2. Semua siswa ---
$siswa = [];
$r = $conn->query("SELECT id,no_formulir,nama FROM siswa WHERE unit='$unit' ORDER BY nama");
while($row=$r->fetch_assoc()){
    $siswa[$row['id']] = [
        'no_formulir'=>$row['no_formulir'],
        'nama'=>$row['nama'],
        'pembayaran'=>[],
        'cashback'=>0,
        'total_bayar'=>0
    ];
}

// --- 3. Init kolom pembayaran & cashback ---
foreach($siswa as &$s){
    foreach($jenis_pembayaran as $jp){
        $s['pembayaran'][$jp['nama']] = 0;
        if ($jp['nama']==='Uang Pangkal') {
            // sisipkan tempat untuk cashback nanti
            $s['pembayaran']['__CASHBACK__'] = 0;
        }
    }
    foreach($bulan_spp as $b) {
        $s['pembayaran']["SPP $b"] = 0;
    }
}
unset($s);

// --- 4. Rekap pembayaran + cashback ---
$sql = "
  SELECT s.id AS sid,
         jp.nama AS jenis,
         pd.bulan,
         SUM(pd.jumlah) AS tot_jml,
         SUM(pd.cashback) AS tot_cb
  FROM siswa s
  JOIN pembayaran p ON s.id=p.siswa_id
  JOIN pembayaran_detail pd ON p.id=pd.pembayaran_id
  JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id=jp.id
  WHERE s.unit='$unit'
    AND p.tahun_pelajaran='$tahun_pelajaran'
  GROUP BY s.id,jp.nama,pd.bulan
";
$r = $conn->query($sql);
while($row=$r->fetch_assoc()){
    $sid = $row['sid'];
    $jenis = $row['jenis'];
    $bln   = $row['bulan'];
    if ($jenis==='SPP' && in_array($bln,$bulan_spp)) {
        $siswa[$sid]['pembayaran']["SPP $bln"] += $row['tot_jml'];
    } else {
        $siswa[$sid]['pembayaran'][$jenis] += $row['tot_jml'];
        if ($jenis==='Uang Pangkal') {
            $siswa[$sid]['pembayaran']['__CASHBACK__'] += $row['tot_cb'];
        }
    }
}

// --- 5. Susun kolom & hitung total ---
$kolom_list = [];
foreach($jenis_pembayaran as $jp){
    $kolom_list[] = $jp['nama'];
    if ($jp['nama']==='Uang Pangkal') {
        $kolom_list[] = 'Cashback';
    }
}
foreach($bulan_spp as $b) $kolom_list[] = "SPP $b";

$total_kolom = array_fill_keys($kolom_list,0);
$grand_total = 0;

foreach($siswa as &$s){
    foreach($kolom_list as $k){
        if ($k==='Cashback'){
            $val = $s['pembayaran']['__CASHBACK__'];
        } else {
            $val = $s['pembayaran'][$k];
        }
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print{
      body *{visibility:hidden;}
      .printable-area, .printable-area *{visibility:visible;}
      .no-print{display:none;}
    }
    table th, table td{text-align:center; vertical-align:middle;}
    td.name{text-align:left;}
  </style>
</head>
<body>
<div class="container my-4 printable-area">
  <div class="d-flex justify-content-between mb-3">
    <h4>Rekap Pembayaran Siswa (Unit <?=htmlspecialchars($unit)?>)</h4>
    <button class="btn btn-success no-print" onclick="window.print()">
      <i class="fas fa-print"></i> Cetak
    </button>
  </div>
  <form method="get" class="mb-3 no-print">
    <label><b>Tahun Pelajaran:</b></label>
    <select name="tahun_pelajaran" onchange="this.form.submit()">
      <?php foreach($tahunList as $tp):?>
      <option value="<?=$tp?>" <?=($tp==$tahun_pelajaran?'selected':'')?>><?=$tp?></option>
      <?php endforeach;?>
    </select>
  </form>

  <div class="table-responsive">
  <table class="table table-bordered">
    <thead class="table-primary">
      <tr>
        <th>No</th>
        <th>No Formulir</th>
        <th>Nama Siswa</th>
        <?php foreach($kolom_list as $k):?>
          <th><?=htmlspecialchars($k)?></th>
        <?php endforeach;?>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      <?php $no=1; foreach($siswa as $s):?>
      <tr>
        <td><?=$no++?></td>
        <td><?=htmlspecialchars($s['no_formulir'])?></td>
        <td class="name"><?=htmlspecialchars($s['nama'])?></td>
        <?php foreach($kolom_list as $k):?>
          <?php
            if ($k==='Cashback') $v = $s['pembayaran']['__CASHBACK__'];
            else                 $v = $s['pembayaran'][$k];
            $fmt = $v>0 ? 'Rp '.number_format($v,0,',','.') : '-';
          ?>
          <td><?=$fmt?></td>
        <?php endforeach;?>
        <td><b><?=$s['total_bayar']>0?'Rp '.number_format($s['total_bayar'],0,',','.'):'-'?></b></td>
      </tr>
      <?php endforeach;?>
    </tbody>
    <tfoot class="table-secondary">
      <tr>
        <td colspan="3" class="text-center"><b>Total</b></td>
        <?php foreach($kolom_list as $k):
          $v = $total_kolom[$k];
          $fmt = $v>0?'Rp '.number_format($v,0,',','.'):'-';
        ?>
        <td><b><?=$fmt?></b></td>
        <?php endforeach;?>
        <td><b><?=$grand_total>0?'Rp '.number_format($grand_total,0,',','.'):'-'?></b></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
