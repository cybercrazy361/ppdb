<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}
include '../database_connection.php';

$unit        = $_SESSION['unit'];
$bulan_aktif = date('F');
$tahun_aktif = date('Y');

// 1. Ambil daftar jenis pembayaran untuk unit ini, plus nominal_max dari pengaturan_nominal
$jenis_pembayaran_list = [];
$stmt = $conn->prepare("
    SELECT jp.nama, COALESCE(pn.nominal_max,0) AS nominal 
    FROM jenis_pembayaran jp
    LEFT JOIN pengaturan_nominal pn 
      ON pn.jenis_pembayaran_id = jp.id 
     AND pn.bulan = ? 
    WHERE jp.unit = ?
    ORDER BY jp.nama
");
$stmt->bind_param('ss', $bulan_aktif, $unit);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran_list[$r['nama']] = (int)$r['nominal'];
}
$stmt->close();

// 2. Hitung jumlah yang sudah dibayar per siswa per jenis
$sudah_bayar = [];
$stmt = $conn->prepare("
    SELECT s.id AS siswa_id, jp.nama AS jenis, SUM(pd.jumlah) AS total_bayar
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit = ?
      AND (
           (jp.nama = 'SPP' AND pd.bulan = ?)
        OR (jp.nama != 'SPP')
      )
      AND pd.status_pembayaran != 'Lunas'
    GROUP BY s.id, jp.nama
");
$stmt->bind_param('ss', $unit, $bulan_aktif);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $sudah_bayar[$r['siswa_id']][$r['jenis']] = (int)$r['total_bayar'];
}
$stmt->close();

// 3. Ambil data siswa yang punya tagihan sesuai syarat
$siswa_tagihan = [];
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.no_formulir, s.nama
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit = ?
      AND (
           (jp.nama = 'SPP' AND pd.bulan = ? AND pd.status_pembayaran != 'Lunas')
        OR (jp.nama != 'SPP' AND pd.status_pembayaran != 'Lunas')
      )
    ORDER BY s.nama
");
$stmt->bind_param('ss', $unit, $bulan_aktif);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $siswa_tagihan[$r['id']] = [
        'no_formulir' => $r['no_formulir'],
        'nama'        => $r['nama'],
    ];
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Rekap Tagihan Siswa <?= htmlspecialchars($unit) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print { .no-print{display:none;} body{margin:20px;} }
    table{font-size:12pt;} th,td{text-align:center;vertical-align:middle;}
  </style>
</head>
<body>
<div class="container">
  <div class="text-center my-4">
    <h3>Rekap Tagihan Siswa <?= htmlspecialchars($unit) ?></h3>
    <p>Bulan <?= htmlspecialchars("$bulan_aktif $tahun_aktif") ?></p>
  </div>

  <?php if (empty($siswa_tagihan)): ?>
    <div class="alert alert-success text-center">Tidak ada tagihan.</div>
  <?php else: ?>
    <table class="table table-bordered">
      <thead class="table-primary">
        <tr>
          <th>No</th>
          <th>No Formulir</th>
          <th>Nama</th>
          <?php foreach ($jenis_pembayaran_list as $jenis => $nominal): ?>
            <th>
              <?= htmlspecialchars($jenis) ?><br>
              <small>(Rp <?= number_format($nominal,0,',','.') ?>)</small>
            </th>
          <?php endforeach; ?>
          <th>Total Sisa</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $no = 1; $grand_total = 0;
        foreach ($siswa_tagihan as $id => $s): 
          $total_sisa = 0;
      ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($s['no_formulir']) ?></td>
          <td><?= htmlspecialchars($s['nama']) ?></td>
          <?php foreach ($jenis_pembayaran_list as $jenis => $nominal): 
            $bayar = $sudah_bayar[$id][$jenis] ?? 0;
            $sisa  = max(0, $nominal - $bayar);
            $total_sisa += $sisa;
          ?>
            <td>
              <div>Bayar: <?= $bayar? 'Rp '.number_format($bayar,0,',','.'):'-' ?></div>
              <div>Sisa: <strong>Rp <?= number_format($sisa,0,',','.') ?></strong></div>
            </td>
          <?php endforeach; ?>
          <td><strong>Rp <?= number_format($total_sisa,0,',','.') ?></strong></td>
        </tr>
      <?php 
        $grand_total += $total_sisa;
        endforeach;
      ?>
        <tr class="table-success">
          <td colspan="<?= 3 + count($jenis_pembayaran_list) ?>" class="text-end">
            <strong>Grand Total Sisa:</strong>
          </td>
          <td><strong>Rp <?= number_format($grand_total,0,',','.') ?></strong></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="text-center mt-4 no-print">
    <button class="btn btn-primary" onclick="window.print()">Cetak</button>
    <a href="daftar_siswa_keuangan.php" class="btn btn-secondary">Kembali</a>
  </div>
</div>
</body>
</html>
