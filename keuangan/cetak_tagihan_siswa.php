<?php
// cetak_tagihan_siswa.php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}
include '../database_connection.php';

$unit        = $_SESSION['unit'];
$bulan_aktif = date('F');
$tahun_now   = date('Y');
$bulan_now   = date('n');

// 0) Hitung default tahun ajaran berdasarkan bulan saat ini:
//    Jika bulan >= 7 (Juli), maka tahun ajarannya "$tahun_now/($tahun_now+1)"
//    Jika bulan < 7, maka "$tahun_now-1/$tahun_now"
if ($bulan_now >= 7) {
    $default_tp = $tahun_now . '/' . ($tahun_now + 1);
} else {
    $default_tp = ($tahun_now - 1) . '/' . $tahun_now;
}

// 1) Ambil daftar tahun pelajaran yang ada di tabel pembayaran untuk unit ini
$tahun_pelajaran_list = [];
$stmt = $conn->prepare("
    SELECT DISTINCT p.tahun_pelajaran
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    WHERE s.unit = ?
    ORDER BY p.tahun_pelajaran DESC
");
$stmt->bind_param('s', $unit);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $tahun_pelajaran_list[] = $r['tahun_pelajaran'];
}
$stmt->close();

// 2) Pilih tahun ajaran: dari GET jika ada, else gunakan $default_tp
$tp_selected = $_GET['tp'] ?? $default_tp;

// 3) Ambil daftar jenis pembayaran + nominal_max
$jenis_pembayaran_list = [];
$stmt = $conn->prepare("
    SELECT jp.nama,
           COALESCE(pn.nominal_max,0) AS nominal_max
    FROM jenis_pembayaran jp
    LEFT JOIN pengaturan_nominal pn 
      ON pn.jenis_pembayaran_id = jp.id
     AND pn.bulan = ?
     AND pn.unit  = ?
    WHERE jp.unit = ?
    ORDER BY jp.nama
");
$stmt->bind_param('sss', $bulan_aktif, $unit, $unit);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $jenis_pembayaran_list[$r['nama']] = (int)$r['nominal_max'];
}
$stmt->close();

// 4) Hitung total sudah dibayar per siswa per jenis di tahun ajaran terpilih
$sudah_bayar = [];
$stmt = $conn->prepare("
    SELECT s.id           AS siswa_id,
           jp.nama        AS jenis,
           SUM(pd.jumlah) AS total_bayar
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit = ?
      AND p.tahun_pelajaran = ?
      AND pd.status_pembayaran != 'Lunas'
      AND (
           (jp.nama = 'SPP' AND pd.bulan = ?)
        OR (jp.nama != 'SPP')
      )
    GROUP BY s.id, jp.nama
");
$stmt->bind_param('sss', $unit, $tp_selected, $bulan_aktif);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $sudah_bayar[$r['siswa_id']][$r['jenis']] = (int)$r['total_bayar'];
}
$stmt->close();

// 5) Ambil daftar siswa yang punya tunggakan di tahun ajaran & bulan ini
$siswa_tagihan = [];
$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.no_formulir, s.nama
    FROM siswa s
    JOIN pembayaran p ON s.id = p.siswa_id
    JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.unit = ?
      AND p.tahun_pelajaran = ?
      AND pd.status_pembayaran != 'Lunas'
      AND (
           (jp.nama = 'SPP' AND pd.bulan = ?)
        OR (jp.nama != 'SPP')
      )
    ORDER BY s.nama
");
$stmt->bind_param('sss', $unit, $tp_selected, $bulan_aktif);
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
    table { font-size:12pt; }
    th, td { text-align:center; vertical-align:middle; }
  </style>
</head>
<body>
<div class="container">

  <!-- Dropdown Tahun Pelajaran -->
  <form method="get" class="row g-2 align-items-end no-print my-3">
    <div class="col-auto">
      <label for="tp" class="form-label">Tahun Ajaran</label>
      <select name="tp" id="tp" class="form-select" onchange="this.form.submit()">
        <?php foreach($tahun_pelajaran_list as $tp): ?>
          <option value="<?= htmlspecialchars($tp) ?>"
            <?= $tp === $tp_selected ? 'selected' : '' ?>>
            <?= htmlspecialchars($tp) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="text-center my-4">
    <h3>Rekap Tagihan Siswa <?= htmlspecialchars($unit) ?></h3>
    <p>Bulan <?= htmlspecialchars("$bulan_aktif $tp_selected") ?></p>
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
          <?php foreach ($jenis_pembayaran_list as $jenis => $nominal_max): ?>
            <th>
              <?= htmlspecialchars($jenis) ?><br>
              <small>(Rp <?= number_format($nominal_max,0,',','.') ?>)</small>
            </th>
          <?php endforeach; ?>
          <th>Total Sisa</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $no          = 1;
        $grand_total = 0;
        foreach ($siswa_tagihan as $id => $s):
          $total_sisa = 0;
      ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($s['no_formulir']) ?></td>
          <td><?= htmlspecialchars($s['nama']) ?></td>

          <?php foreach ($jenis_pembayaran_list as $jenis => $nominal_max):
            $bayar = $sudah_bayar[$id][$jenis] ?? 0;
            $sisa  = max(0, $nominal_max - $bayar);
            $total_sisa += $sisa;
          ?>
            <td>
              <div>Bayar: <?= $bayar ? 'Rp '.number_format($bayar,0,',','.') : '-' ?></div>
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
