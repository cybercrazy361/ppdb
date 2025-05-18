<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}

include '../database_connection.php';

$unit         = $_SESSION['unit'];
$bulan_aktif  = date('F');
$tahun_aktif  = date('Y');

// 1. Ambil daftar jenis pembayaran untuk unit ini
$jenis_pembayaran_list = [];
$stmt_jp = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE unit = ? ORDER BY nama ASC");
$stmt_jp->bind_param('s', $unit);
$stmt_jp->execute();
$res_jp = $stmt_jp->get_result();
while ($row = $res_jp->fetch_assoc()) {
    $jenis_pembayaran_list[] = $row['nama'];
}
$stmt_jp->close();

// 2. Query tagihan siswa: SPP bulan ini + tunggakan lain
$query = "
SELECT 
    s.id AS siswa_id,
    s.no_formulir,
    s.nama,
    jp.nama AS jenis_pembayaran,
    pd.jumlah,
    pd.bulan,
    pd.status_pembayaran
FROM siswa s
JOIN pembayaran p ON s.id = p.siswa_id
JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
WHERE s.unit = ?
  AND (
       (jp.nama = 'SPP' AND pd.bulan = ? AND pd.status_pembayaran != 'Lunas')
    OR (jp.nama != 'SPP' AND pd.status_pembayaran != 'Lunas')
  )
ORDER BY s.nama ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $unit, $bulan_aktif);
$stmt->execute();
$result = $stmt->get_result();

$siswa_tagihan = [];
while ($row = $result->fetch_assoc()) {
    $id    = $row['siswa_id'];
    $jenis = $row['jenis_pembayaran'];
    $jml   = (int)$row['jumlah'];

    if (!isset($siswa_tagihan[$id])) {
        $siswa_tagihan[$id] = [
            'no_formulir' => $row['no_formulir'],
            'nama'        => $row['nama'],
            'tagihan'     => []
        ];
    }
    if (!isset($siswa_tagihan[$id]['tagihan'][$jenis])) {
        $siswa_tagihan[$id]['tagihan'][$jenis] = 0;
    }
    $siswa_tagihan[$id]['tagihan'][$jenis] += $jml;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Tagihan Siswa - <?= htmlspecialchars($unit) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print { .no-print{display:none;} body{margin:20px;} }
    table{font-size:12pt;}
    th,td{text-align:center;vertical-align:middle;}
  </style>
</head>
<body>
<div class="container">
  <div class="text-center my-4">
    <h3>Daftar Tagihan Siswa <?= htmlspecialchars($unit) ?></h3>
    <p>Bulan: <?= htmlspecialchars("$bulan_aktif $tahun_aktif") ?></p>
  </div>

  <?php if (empty($siswa_tagihan)): ?>
    <div class="alert alert-success text-center">
      Tidak ada tagihan siswa untuk bulan ini.
    </div>
  <?php else: ?>
    <table class="table table-bordered">
      <thead class="table-primary">
        <tr>
          <th>No</th>
          <th>No Formulir</th>
          <th>Nama Siswa</th>
          <?php foreach ($jenis_pembayaran_list as $jenis): ?>
            <th><?= htmlspecialchars($jenis) ?></th>
          <?php endforeach; ?>
          <th>Total Tagihan</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $no          = 1;
        $grand_total = 0;
        foreach ($siswa_tagihan as $data):
          $total_siswa = 0;
      ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($data['no_formulir']) ?></td>
          <td><?= htmlspecialchars($data['nama']) ?></td>
          <?php foreach ($jenis_pembayaran_list as $jenis): 
            $j = $data['tagihan'][$jenis] ?? 0;
            $total_siswa += $j;
          ?>
            <td>
              <?= $j ? 'Rp '.number_format($j,0,',','.') : '-' ?>
            </td>
          <?php endforeach; ?>
          <td><strong>Rp <?= number_format($total_siswa,0,',','.') ?></strong></td>
        </tr>
      <?php 
        $grand_total += $total_siswa;
        endforeach; 
      ?>
        <tr class="table-success">
          <td colspan="<?= 3 + count($jenis_pembayaran_list) ?>" class="text-end">
            <strong>Total Semua Tagihan:</strong>
          </td>
          <td><strong>Rp <?= number_format($grand_total,0,',','.') ?></strong></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="text-center mt-4 no-print">
    <button class="btn btn-primary" onclick="window.print()">Cetak Tagihan</button>
    <a href="daftar_siswa_keuangan.php" class="btn btn-secondary">Kembali</a>
  </div>
</div>
</body>
</html>
