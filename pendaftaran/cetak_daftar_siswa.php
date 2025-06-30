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
        WHEN (
          SELECT 
            IFNULL(SUM(pd.jumlah - IFNULL(pd.cashback,0)),0)
          FROM pembayaran_detail pd
          JOIN pembayaran p ON pd.pembayaran_id = p.id
          WHERE p.siswa_id = s.id
            AND pd.jenis_pembayaran_id = $uang_pangkal_id
        ) >= (
          SELECT 
            IFNULL(sta.nominal,0)
          FROM siswa_tagihan_awal sta
          WHERE sta.siswa_id = s.id AND sta.jenis_pembayaran_id = $uang_pangkal_id
        )
        AND (
          SELECT 
            IFNULL(sta.nominal,0)
          FROM siswa_tagihan_awal sta
          WHERE sta.siswa_id = s.id AND sta.jenis_pembayaran_id = $uang_pangkal_id
        ) > 0
        THEN 'Lunas'
        WHEN (
          SELECT 
            IFNULL(SUM(pd.jumlah - IFNULL(pd.cashback,0)),0)
          FROM pembayaran_detail pd
          JOIN pembayaran p ON pd.pembayaran_id = p.id
          WHERE p.siswa_id = s.id
            AND pd.jenis_pembayaran_id = $uang_pangkal_id
        ) > 0
        THEN 'Angsuran'
        ELSE 'Belum Bayar'
      END AS status_pembayaran
    FROM siswa s
    LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
    WHERE s.unit = ?
    ORDER BY s.id
";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $unit);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$lunas = $angsuran = $belum = $ppdb = 0;
while ($row = $result->fetch_assoc()) {
    // Cek status PPDB bersama
    $status_final = '';
    if (strtolower(trim($row['status_cp'] ?? '')) === 'ppdb bersama') {
        $status_final = 'PPDB Bersama';
        $ppdb++;
    } else {
        $status_final = $row['status_pembayaran'];
        switch (strtolower($status_final)) {
            case 'lunas':
                $lunas++;
                break;
            case 'angsuran':
                $angsuran++;
                break;
            default:
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
    <div>PPDB Bersama: <?= $ppdb ?></div>
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
      <?php
      $i = 1;
      foreach ($rows as $row): ?>
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
