<?php
session_start();
include '../database_connection.php'; // sesuaikan path

// Otentikasi petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit otomatis dari session
$filter_unit   = $_SESSION['unit'];  // 'SMA' atau 'SMK'
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'Semua';
$allowed_status = ['Semua','Lunas','Angsuran','Belum Bayar'];
if (!in_array($filter_status,$allowed_status)) {
    $filter_status = 'Semua';
}

// Format tanggal
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal || $tanggal=='0000-00-00') return '-';
    $bulan = [
      'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
      'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
      'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $d = date('d',strtotime($tanggal));
    $m = $bulan[date('F',strtotime($tanggal))];
    $y = date('Y',strtotime($tanggal));
    return "$d $m $y";
}

// Statistik kartu
function getStatusPembayaranCounts($conn,$unit) {
    // total siswa
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit=?");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $total=$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
    // belum bayar (belum lunas 2 komponen)
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS belum
      FROM siswa s
      WHERE s.unit=?
        AND NOT EXISTS(
          SELECT 1 FROM pembayaran_detail pd1 JOIN pembayaran p1 ON p1.id=pd1.pembayaran_id
          WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
        )
        AND NOT EXISTS(
          SELECT 1 FROM pembayaran_detail pd2 JOIN pembayaran p2 ON p2.id=pd2.pembayaran_id
          WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
        )
    ");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $belum=$stmt->get_result()->fetch_assoc()['belum']; $stmt->close();
    // lunas (kedua lunas)
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS lunas
      FROM siswa s
      WHERE s.unit=?
        AND EXISTS(
          SELECT 1 FROM pembayaran_detail pd1 JOIN pembayaran p1 ON p1.id=pd1.pembayaran_id
          WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
        )
        AND EXISTS(
          SELECT 1 FROM pembayaran_detail pd2 JOIN pembayaran p2 ON p2.id=pd2.pembayaran_id
          WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
        )
    ");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $lunas=$stmt->get_result()->fetch_assoc()['lunas']; $stmt->close();
    // angsuran (salah satu lunas saja)
    $stmt = $conn->prepare("
      SELECT COUNT(*) AS angsuran
      FROM siswa s
      WHERE s.unit=?
        AND (
             EXISTS(
               SELECT 1 FROM pembayaran_detail pd1 JOIN pembayaran p1 ON p1.id=pd1.pembayaran_id
               WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas'
             ) XOR
             EXISTS(
               SELECT 1 FROM pembayaran_detail pd2 JOIN pembayaran p2 ON p2.id=pd2.pembayaran_id
               WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas'
             )
        )
    ");
    $stmt->bind_param("s",$unit); $stmt->execute();
    $angsuran=$stmt->get_result()->fetch_assoc()['angsuran']; $stmt->close();

    return ['total'=>$total,'belum'=>$belum,'lunas'=>$lunas,'angsuran'=>$angsuran];
}

$stat = getStatusPembayaranCounts($conn,$filter_unit);

// Ambil daftar siswa + hitung status di PHP
$sql = "
  SELECT s.*,
    (SELECT COUNT(*) FROM pembayaran_detail pd1 JOIN pembayaran p1 ON p1.id=pd1.pembayaran_id
       WHERE p1.siswa_id=s.id AND pd1.jenis_pembayaran_id=1 AND pd1.status_pembayaran='Lunas') AS up,
    (SELECT COUNT(*) FROM pembayaran_detail pd2 JOIN pembayaran p2 ON p2.id=pd2.pembayaran_id
       WHERE p2.siswa_id=s.id AND pd2.jenis_pembayaran_id=2 AND pd2.bulan='Juli' AND pd2.status_pembayaran='Lunas') AS sj,
    COALESCE((SELECT p.metode_pembayaran FROM pembayaran p
       WHERE p.siswa_id=s.id ORDER BY p.tanggal_pembayaran DESC LIMIT 1),'Belum Ada') AS metode
  FROM siswa s
  WHERE s.unit=?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s",$filter_unit);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while($r=$result->fetch_assoc()){
  $up = (int)$r['up']; $sj=(int)$r['sj'];
  if($up>0 && $sj>0)       $r['status']='Lunas';
  elseif($up>0||$sj>0)     $r['status']='Angsuran';
  else                     $r['status']='Belum Bayar';
  $rows[]=$r;
}
$stmt->close();
$conn->close();

// filter status
if($filter_status!='Semua'){
  $rows = array_filter($rows,fn($r)=>$r['status']==$filter_status);
}

// paging
$page  = max(1,intval($_GET['page']??1));
$limit = 20;
$total = count($rows);
$pages = ceil($total/$limit);
$offset=($page-1)*$limit;
$show  = array_slice($rows,$offset,$limit);

// badge helper
function badge($s){
  return match($s){
    'Lunas'      => '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Lunas</span>',
    'Angsuran'   => '<span class="badge bg-warning text-dark"><i class="fas fa-wallet"></i> Angsuran</span>',
    default      => '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Belum Bayar</span>',
  };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan Pendaftaran <?=htmlspecialchars($filter_unit)?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body{font-family:'Poppins',sans-serif;background:#f4f7fc;color:#333;}
    h2{font-weight:600;}
    .filter-bar .btn, .filter-bar .form-select{white-space:nowrap;}
    /* print rules */
    @media print {
      .no-print, .pagination { display: none!important; }
      body * { visibility: hidden; }
      #printableArea, #printableArea * { visibility: visible; }
      #printableArea { position: absolute; top:0; left:0; width:100%; }
      thead { display: table-header-group; }
      tr, td, th { page-break-inside: avoid !important; }
      .table-responsive { display: block!important; }
      .container { width:100%!important; padding:0!important; }
    }
  </style>
</head>
<body>

<div class="container py-4" id="printableArea">

  <div class="row align-items-center mb-4">
    <div class="col-2 text-center">
      <img src="../assets/images/logo_trans.png" class="img-fluid" style="max-height:80px">
    </div>
    <div class="col-8 text-center">
      <h2>PPDB <?=htmlspecialchars($filter_unit)?></h2>
      <p class="mb-0">Laporan Pendaftaran Siswa</p>
      <small><?=formatTanggalIndonesia(date('Y-m-d'));?></small>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <i class="fas fa-user-graduate fa-2x text-primary"></i>
          <h5 class="mt-2">Total Siswa</h5>
          <h3 class="fw-bold"><?=$stat['total']?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <i class="fas fa-check-circle fa-2x text-success"></i>
          <h5 class="mt-2">Lunas</h5>
          <h3 class="fw-bold"><?=$stat['lunas']?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <i class="fas fa-wallet fa-2x text-warning"></i>
          <h5 class="mt-2">Angsuran</h5>
          <h3 class="fw-bold"><?=$stat['angsuran']?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <i class="fas fa-times-circle fa-2x text-danger"></i>
          <h5 class="mt-2">Belum Bayar</h5>
          <h3 class="fw-bold"><?=$stat['belum']?></h3>
        </div>
      </div>
    </div>
  </div>

  <form class="row g-2 align-items-center mb-3 filter-bar no-print">
    <div class="col-auto">
      <select name="status" class="form-select" onchange="this.form.submit()">
        <?php foreach($allowed_status as $s): ?>
          <option value="<?=$s?>" <?=$filter_status==$s?'selected':''?>><?=$s?></option>
        <?php endforeach;?>
      </select>
    </div>
  </form>

  <div class="table-responsive mb-4">
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>No</th>
          <th>No Formulir</th>
          <th>Nama</th>
          <th>Jenis Kelamin</th>
          <th>TTL</th>
          <th>Asal Sekolah</th>
          <th>No HP</th>
          <th>Status Pembayaran</th>
          <th>Metode</th>
        </tr>
      </thead>
      <tbody>
        <?php if($total): $n=$offset+1; foreach($show as $r): ?>
        <tr>
          <td><?=$n++?></td>
          <td><?=htmlspecialchars($r['no_formulir'])?></td>
          <td><?=htmlspecialchars($r['nama'])?></td>
          <td><?=htmlspecialchars($r['jenis_kelamin'])?></td>
          <td><?=htmlspecialchars($r['tempat_lahir']).', '.formatTanggalIndonesia($r['tanggal_lahir'])?></td>
          <td><?=htmlspecialchars($r['asal_sekolah'])?></td>
          <td><?=htmlspecialchars($r['no_hp'])?></td>
          <td><?=badge($r['status'])?></td>
          <td><?=htmlspecialchars($r['metode'])?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="9" class="text-center">Tidak ada data.</td></tr>
        <?php endif;?>
      </tbody>
    </table>
  </div>

</div>

<div class="container mb-4 text-center no-print">
  <button class="btn btn-success me-2" onclick="window.print()">
    <i class="fas fa-print me-1"></i> Cetak Semua
  </button>
  <a href="dashboard_pendaftaran.php" class="btn btn-secondary">
    <i class="fas fa-arrow-left me-1"></i> Kembali
  </a>
</div>

<?php if($pages>1): ?>
<div class="container no-print">
  <nav><ul class="pagination justify-content-center">
    <li class="page-item <?=$page<=1?'disabled':''?>">
      <a class="page-link" href="?page=<?=$page-1?>&status=<?=$filter_status?>">Previous</a>
    </li>
    <?php for($i=1;$i<=$pages;$i++): ?>
      <li class="page-item <?=$i==$page?'active':''?>">
        <a class="page-link" href="?page=<?=$i?>&status=<?=$filter_status?>"><?=$i?></a>
      </li>
    <?php endfor;?>
    <li class="page-item <?=$page>=$pages?'disabled':''?>">
      <a class="page-link" href="?page=<?=$page+1?>&status=<?=$filter_status?>">Next</a>
    </li>
  </ul></nav>
</div>
<?php endif;?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
