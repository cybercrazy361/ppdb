<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit'] ?? '';
$uang_pangkal_id = 1;
$spp_id          = 2;

// Ambil semua siswa tanpa limit, lengkap dengan metode & status pembayaran
$query = "
    SELECT 
      s.*,
      COALESCE(
        (SELECT p.metode_pembayaran
         FROM pembayaran p
         WHERE p.siswa_id = s.id
         ORDER BY p.tanggal_pembayaran DESC
         LIMIT 1),
        'Belum Ada'
      ) AS metode_pembayaran,
      CASE
        WHEN
          (SELECT COUNT(*) FROM pembayaran_detail pd1
           JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
           WHERE p1.siswa_id = s.id
             AND pd1.jenis_pembayaran_id = $uang_pangkal_id
             AND pd1.status_pembayaran = 'Lunas'
          ) > 0
        AND
          (SELECT COUNT(*) FROM pembayaran_detail pd2
           JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
           WHERE p2.siswa_id = s.id
             AND pd2.jenis_pembayaran_id = $spp_id
             AND pd2.bulan = 'Juli'
             AND pd2.status_pembayaran = 'Lunas'
          ) > 0
        THEN 'Lunas'
        WHEN
          (
            (SELECT COUNT(*) FROM pembayaran_detail pd1
             JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
             WHERE p1.siswa_id = s.id
               AND pd1.jenis_pembayaran_id = $uang_pangkal_id
               AND pd1.status_pembayaran = 'Lunas'
            ) > 0
            OR
            (SELECT COUNT(*) FROM pembayaran_detail pd2
             JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
             WHERE p2.siswa_id = s.id
               AND pd2.jenis_pembayaran_id = $spp_id
               AND pd2.bulan = 'Juli'
               AND pd2.status_pembayaran = 'Lunas'
            ) > 0
          )
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
      END AS status_pembayaran
    FROM siswa s
    WHERE s.unit = ?
    ORDER BY s.id
";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();

// Fetch semua baris ke array untuk menghitung statistik
$rows = $result->fetch_all(MYSQLI_ASSOC);
$lunas = $angsuran = $belum = 0;
foreach ($rows as $r) {
    switch (strtolower($r['status_pembayaran'])) {
        case 'lunas':    $lunas++;    break;
        case 'angsuran': $angsuran++; break;
        default:         $belum++;    break;
    }
}

function formatTanggal($t){
    if (!$t || $t === '0000-00-00') return '-';
    $bulan = [
        'January'   => 'Januari',
        'February'  => 'Februari',
        'March'     => 'Maret',
        'April'     => 'April',
        'May'       => 'Mei',
        'June'      => 'Juni',
        'July'      => 'Juli',
        'August'    => 'Agustus',
        'September' => 'September',
        'October'   => 'Oktober',
        'November'  => 'November',
        'December'  => 'Desember'
    ];
    $f = date('F', strtotime($t));
    $n = $bulan[$f] ?? $f;
    return date('d', strtotime($t)) . ' ' . $n . ' ' . date('Y', strtotime($t));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Daftar Siswa <?= htmlspecialchars($unit) ?></title>
  <style>
    /* Print-only */
    @media print {
      body, table { margin: 0; }
      .no-print { display: none !important; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 4px; font-size: 12px; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
    /* Screen */
    body { font-family: sans-serif; padding: 1rem; }
    h1 { text-align: center; margin-bottom: 0.5rem; }
    .no-print { margin-bottom: 1rem; }
    button { padding: 0.5rem 1rem; margin-right: 0.5rem; }
    .stats {
      margin: 1rem 0;
      display: flex;
      gap: 1.5rem;
      font-size: 0.95rem;
    }
    .stats div {
      background: #f1f3f5;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
    }
    table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
    th { background: #2e59d9; color: #fff; }
    th, td { border: 1px solid #ccc; padding: 6px; text-align: center; font-size: 14px; }
  </style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()">🖨️ Cetak</button>
    <button onclick="window.close()">✖ Tutup</button>
  </div>

  <h1>Daftar Siswa <?= htmlspecialchars($unit) ?></h1>

  <div class="stats no-print">
    <div>Total: <?= count($rows) ?></div>
    <div>Lunas: <?= $lunas ?></div>
    <div>Angsuran: <?= $angsuran ?></div>
    <div>Belum Bayar: <?= $belum ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>No Formulir</th>
        <th>Nama</th>
        <th>JK</th>
        <th>TTL</th>
        <th>Asal Sekolah</th>
        <th>Alamat</th>
        <th>No HP</th>
        <th>No HP Ortu</th>
        <th>Status</th>
        <th>Metode</th>
        <th>Tgl Daftar</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($rows as $row): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['no_formulir']) ?></td>
        <td><?= htmlspecialchars($row['nama']) ?></td>
        <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
        <td>
          <?= htmlspecialchars($row['tempat_lahir']) ?>, 
          <?= formatTanggal($row['tanggal_lahir']) ?>
        </td>
        <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
        <td><?= htmlspecialchars($row['alamat']) ?></td>
        <td><?= htmlspecialchars($row['no_hp']) ?></td>
        <td><?= htmlspecialchars($row['no_hp_ortu']) ?></td>
        <td><?= htmlspecialchars($row['status_pembayaran']) ?></td>
        <td><?= htmlspecialchars($row['metode_pembayaran']) ?></td>
        <td><?= formatTanggal($row['tanggal_pendaftaran']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</body>
</html>
