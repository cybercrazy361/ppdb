<?php
session_start();
include '../database_connection.php';

// Pastikan login valid sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit otomatis dari session (SMA atau SMK)
$filter_unit   = $_SESSION['unit'];
$filter_status = $_GET['status'] ?? 'Semua';
$allowed_status = ['Semua','Lunas','Angsuran','Belum Bayar'];
if (!in_array($filter_status, $allowed_status)) {
    $filter_status = 'Semua';
}

// Format tanggal ke "DD NamaBulan YYYY"
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal || $tanggal === '0000-00-00') return '-';
    $bulan = [
      'January'=>'Januari','February'=>'Februari','March'=>'Maret',
      'April'=>'April','May'=>'Mei','June'=>'Juni',
      'July'=>'Juli','August'=>'Agustus','September'=>'September',
      'October'=>'Oktober','November'=>'November','December'=>'Desember'
    ];
    $d = date('d', strtotime($tanggal));
    $m = $bulan[date('F', strtotime($tanggal))];
    $y = date('Y', strtotime($tanggal));
    return "$d $m $y";
}

// Hitung statistik (total, lunas, angsuran, belum bayar)
function getStats($conn, $unit) {
    // Total siswa
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM siswa WHERE unit=?");
    $stmt->bind_param('s',$unit); $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total']; $stmt->close();

    // Lunas: ada Lunas untuk uang pangkal (id=1) & SPP Juli (id=2)
    $sqlLunas = "
      SELECT COUNT(*) AS lunas FROM siswa s
      WHERE s.unit=?
      AND EXISTS(
        SELECT 1 FROM pembayaran_detail pd JOIN pembayaran p 
        ON pd.pembayaran_id=p.id
        WHERE p.siswa_id=s.id 
          AND pd.jenis_pembayaran_id=1 AND pd.status_pembayaran='Lunas'
      )
      AND EXISTS(
        SELECT 1 FROM pembayaran_detail pd JOIN pembayaran p 
        ON pd.pembayaran_id=p.id
        WHERE p.siswa_id=s.id 
          AND pd.jenis_pembayaran_id=2 AND pd.bulan='Juli' AND pd.status_pembayaran='Lunas'
      )
    ";
    $stmt = $conn->prepare($sqlLunas);
    $stmt->bind_param('s',$unit); $stmt->execute();
    $lunas = $stmt->get_result()->fetch_assoc()['lunas']; $stmt->close();

    // Belum bayar: tidak lunas pangkal & tidak lunas SPP Juli
    $sqlBelum = "
      SELECT COUNT(*) AS belum FROM siswa s
      WHERE s.unit=?
      AND NOT EXISTS(
        SELECT 1 FROM pembayaran_detail pd JOIN pembayaran p 
        ON pd.pembayaran_id=p.id
        WHERE p.siswa_id=s.id AND pd.jenis_pembayaran_id=1 AND pd.status_pembayaran='Lunas'
      )
      AND NOT EXISTS(
        SELECT 1 FROM pembayaran_detail pd JOIN pembayaran p 
        ON pd.pembayaran_id=p.id
        WHERE p.siswa_id=s.id 
          AND pd.jenis_pembayaran_id=2 AND pd.bulan='Juli' AND pd.status_pembayaran='Lunas'
      )
    ";
    $stmt = $conn->prepare($sqlBelum);
    $stmt->bind_param('s',$unit); $stmt->execute();
    $belum = $stmt->get_result()->fetch_assoc()['belum']; $stmt->close();

    // Angsuran = total bayar - lunas
    $bayar = $total - $belum;
    $angsuran = max(0, $bayar - $lunas);

    return compact('total','lunas','angsuran','belum');
}

$stat = getStats($conn, $filter_unit);

// Ambil semua siswa, hitung flag pangkal & SPP Juli, lalu tentukan status
$sql = "
  SELECT s.*, 
    (SELECT p.metode_pembayaran FROM pembayaran p 
     WHERE p.siswa_id=s.id ORDER BY p.tanggal_pembayaran DESC LIMIT 1
    ) AS metode_pembayaran,
    (SELECT COUNT(*) FROM pembayaran_detail pd JOIN pembayaran p 
      ON pd.pembayaran_id=p.id
     WHERE p.siswa_id=s.id 
       AND pd.jenis_pembayaran_id=1 
       AND pd.status_pembayaran='Lunas'
    ) AS flag_pangkal,
    (SELECT COUNT(*) FROM pembayaran_detail pd JOIN pembayaran p 
      ON pd.pembayaran_id=p.id
     WHERE p.siswa_id=s.id 
       AND pd.jenis_pembayaran_id=2 
       AND pd.bulan='Juli' 
       AND pd.status_pembayaran='Lunas'
    ) AS flag_spp_juli
  FROM siswa s
  WHERE s.unit=?
  ORDER BY s.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s',$filter_unit);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Terapkan filter status dan paging manual
$filtered = [];
foreach($data as $r){
    $lp = (int)$r['flag_pangkal'];
    $ls = (int)$r['flag_spp_juli'];
    if($lp>0 && $ls>0)       $st='Lunas';
    elseif($lp>0||$ls>0)    $st='Angsuran';
    else                    $st='Belum Bayar';
    if($filter_status==='Semua' || $filter_status===$st){
        $r['status_pembayaran'] = $st;
        $filtered[] = $r;
    }
}
$total_records = count($filtered);
$limit = 20;
$page  = max(1,(int)($_GET['page']??1));
$offset=($page-1)*$limit;
$paged = array_slice($filtered,$offset,$limit);
$total_pages = ceil($total_records/$limit);

// Fungsi badge status
function badgeStatus($s){
  if($s==='Lunas')      return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Lunas</span>';
  if($s==='Angsuran')   return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle"></i> Angsuran</span>';
  return '<span class="badge bg-danger"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>PPDB <?=htmlspecialchars($filter_unit)?> – Laporan</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap & Font Awesome & Poppins -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- CSS Kustom -->
  <link rel="stylesheet" href="../assets/css/laporan_pendaftaran_styles.css">
  <style>
    body{font-family:'Poppins',sans-serif;background:#f4f6f9;}
    .card-stat{border-radius:8px;} .filter-bar .form-select{width:auto;}
  </style>
</head>
<body>
<div class="container py-4">

  <!-- Header -->
  <div class="text-center mb-4">
    <h2 class="fw-bold">Laporan Pendaftaran Siswa <?=htmlspecialchars($filter_unit)?></h2>
    <small class="text-muted">Tanggal Cetak: <?=formatTanggalIndonesia(date('Y-m-d'))?></small>
  </div>

  <!-- Statistik Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card card-stat shadow-sm text-center p-3">
        <div class="fs-1 text-primary"><i class="fas fa-users"></i></div>
        <div class="fw-semibold">Total Siswa</div>
        <div class="fs-3"><?=$stat['total']?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat shadow-sm text-center p-3">
        <div class="fs-1 text-success"><i class="fas fa-check-circle"></i></div>
        <div class="fw-semibold">Lunas</div>
        <div class="fs-3"><?=$stat['lunas']?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat shadow-sm text-center p-3">
        <div class="fs-1 text-warning"><i class="fas fa-wallet"></i></div>
        <div class="fw-semibold">Angsuran</div>
        <div class="fs-3"><?=$stat['angsuran']?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card card-stat shadow-sm text-center p-3">
        <div class="fs-1 text-danger"><i class="fas fa-exclamation-circle"></i></div>
        <div class="fw-semibold">Belum Bayar</div>
        <div class="fs-3"><?=$stat['belum']?></div>
      </div>
    </div>
  </div>

  <!-- Filter Status -->
  <form class="filter-bar d-flex align-items-center gap-2 mb-3" method="get">
    <input type="hidden" name="unit" value="<?=$filter_unit?>">
    <select name="status" class="form-select">
      <?php foreach($allowed_status as $s): ?>
        <option value="<?=$s?>" <?=$s===$filter_status?'selected':''?>><?=$s?></option>
      <?php endforeach;?>
    </select>
    <button class="btn btn-primary"><i class="fas fa-filter"></i> Terapkan</button>
  </form>

  <!-- Tabel -->
  <div class="table-responsive mb-4">
    <table class="table table-hover table-bordered align-middle bg-white shadow-sm">
      <thead class="table-light">
        <tr>
          <th>No</th><th>No Formulir</th><th>Nama</th><th>JK</th>
          <th>TTL</th><th>Asal Sekolah</th><th>Alamat</th>
          <th>No HP</th><th>Status</th><th>Metode</th><th>Tgl Daftar</th>
        </tr>
      </thead>
      <tbody>
        <?php if($paged): $no=$offset+1; foreach($paged as $r): ?>
          <tr>
            <td><?=$no++?></td>
            <td><?=htmlspecialchars($r['no_formulir'])?></td>
            <td><?=htmlspecialchars($r['nama'])?></td>
            <td><?=htmlspecialchars($r['jenis_kelamin'])?></td>
            <td><?=htmlspecialchars($r['tempat_lahir']).', '.formatTanggalIndonesia($r['tanggal_lahir'])?></td>
            <td><?=htmlspecialchars($r['asal_sekolah'])?></td>
            <td><?=htmlspecialchars($r['alamat'])?></td>
            <td><?=htmlspecialchars($r['no_hp'])?></td>
            <td><?=badgeStatus($r['status_pembayaran'])?></td>
            <td><?=htmlspecialchars($r['metode_pembayaran']?:'-')?></td>
            <td><?=formatTanggalIndonesia($r['tanggal_pendaftaran'])?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="11" class="text-center py-3">Tidak ada data.</td></tr>
        <?php endif;?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
      <li class="page-item <?=$page<=1?'disabled':''?>">
        <a class="page-link" href="?page=<?=$page-1?>&unit=<?=$filter_unit?>&status=<?=$filter_status?>">Previous</a>
      </li>
      <?php 
        $start=max(1,$page-2);
        $end=min($total_pages,$page+2);
        for($i=$start;$i<=$end;$i++): ?>
        <li class="page-item <?=$i==$page?'active':''?>">
          <a class="page-link" href="?page=<?=$i?>&unit=<?=$filter_unit?>&status=<?=$filter_status?>"><?=$i?></a>
        </li>
      <?php endfor;?>
      <li class="page-item <?=$page>=$total_pages?'disabled':''?>">
        <a class="page-link" href="?page=<?=$page+1?>&unit=<?=$filter_unit?>&status=<?=$filter_status?>">Next</a>
      </li>
    </ul>
  </nav>

  <!-- Cetak & Kembali -->
  <div class="text-center mt-3 no-print">
    <button class="btn btn-success me-2" onclick="window.print()">
      <i class="fas fa-print"></i> Cetak
    </button>
    <a href="dashboard_pendaftaran.php" class="btn btn-secondary">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
