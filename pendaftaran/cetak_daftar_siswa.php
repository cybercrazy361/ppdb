<?php
session_start();
include '../database_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

$unit = $_SESSION['unit'] ?? '';
$uang_pangkal_id = 1;
$spp_id = 2;

// Ambil semua siswa lengkap (ambil juga calon_pendaftar.status)
$query = "
    SELECT 
      s.*,
      cp.status as status_cp,
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
        THEN 'Lunas'
        WHEN
          (SELECT COUNT(*) FROM pembayaran_detail pd1
           JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
           WHERE p1.siswa_id = s.id
             AND pd1.jenis_pembayaran_id = $uang_pangkal_id
          ) > 0
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
      END AS status_pembayaran
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ?
    ORDER BY s.nama ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
// Rekap stat
$lunas = $angsuran = $belum = $ppdb = 0;
while ($row = $result->fetch_assoc()) {
    // -- NORMALISASI STATUS --
    $status_cp = strtolower(trim($row['status_cp'] ?? ''));
    $status_pembayaran = strtolower(trim($row['status_pembayaran'] ?? ''));
    // -- LOGIKA STATUS FINAL --
    if ($status_cp === 'ppdb bersama') {
        $status_final = 'PPDB Bersama';
        $ppdb++;
    } else {
        switch ($status_pembayaran) {
            case 'lunas':
                $status_final = 'Lunas';
                $lunas++;
                break;
            case 'angsuran':
                $status_final = 'Angsuran';
                $angsuran++;
                break;
            default:
                $status_final = 'Belum Bayar';
                $belum++;
                break;
        }
    }
    $row['status_final'] = $status_final;
    $rows[] = $row;
}

function formatTanggal($t)
{
    if (!$t || $t === '0000-00-00') {
        return '-';
    }
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember',
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
    @media print {
      body, table { margin: 0; }
      .no-print { display: none !important; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 4px; font-size: 12px; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
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
    th, td { border: 1px solid #ccc; padding: 6px; font-size: 14px; text-align: center; }
    th.th-nama, td.td-nama { text-align: left !important; }
  </style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()">🖨️ Cetak</button>
    <button onclick="window.close()">✖ Tutup</button>
  </div>

  <h1>Daftar Siswa <?= htmlspecialchars($unit) ?> 2025/2026</h1>

  <div class="stats no-print">
    <div>Total: <?= count($rows) ?></div>
    <div>Lunas: <?= $lunas ?></div>
    <div>Angsuran: <?= $angsuran ?></div>
    <div>Belum Bayar: <?= $belum ?></div>
    <div>SPMB Bersama: <?= $ppdb ?></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>No Formulir</th>
        <th>Nama Siswa</th>
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
      <?php
      $i = 1;
      foreach ($rows as $row): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= htmlspecialchars($row['no_invoice']) ?></td>
        <td class="td-nama"><?= htmlspecialchars($row['nama']) ?></td>
        <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
        <td>
          <?= htmlspecialchars($row['tempat_lahir']) ?>, 
          <?= formatTanggal($row['tanggal_lahir']) ?>
        </td>
        <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
        <td><?= htmlspecialchars($row['alamat']) ?></td>
        <td><?= htmlspecialchars($row['no_hp']) ?></td>
        <td><?= htmlspecialchars($row['no_hp_ortu']) ?></td>
        <td><?= htmlspecialchars($row['status_final']) ?></td>
        <td><?= htmlspecialchars($row['metode_pembayaran']) ?></td>
        <td><?= formatTanggal($row['tanggal_pendaftaran']) ?></td>
      </tr>
      <?php endforeach;
      ?>
    </tbody>
  </table>

</body>
</html>
