<?php
// cetak_tagihan_siswa.php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}

include '../database_connection.php';

// Unit petugas
$unit = $_SESSION['unit'];

// Tentukan bulan dan tahun berjalan
$bulan_aktif = date('F');
$tahun_aktif = date('Y');

// Query untuk mendapatkan siswa dengan tagihan SPP bulan berjalan dan tunggakan lainnya
$query = "
SELECT s.no_formulir, s.nama, jp.nama AS jenis_pembayaran, pd.jumlah, pd.bulan, pd.status_pembayaran, pd.angsuran_ke
FROM siswa s
JOIN pembayaran p ON s.id = p.siswa_id
JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
WHERE s.unit = ? 
AND (
    (jp.nama = 'SPP' AND pd.bulan = ? AND pd.status_pembayaran != 'Lunas') OR
    (jp.nama != 'SPP' AND pd.status_pembayaran != 'Lunas')
)
ORDER BY s.nama ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $unit, $bulan_aktif);
$stmt->execute();
$result = $stmt->get_result();

$tagihan_siswa = [];
while ($row = $result->fetch_assoc()) {
    $tagihan_siswa[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Tagihan Siswa - <?= htmlspecialchars($unit); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 20px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="text-center my-4">
        <h3>Daftar Tagihan Siswa <?= htmlspecialchars($unit); ?></h3>
        <p>Bulan: <?= htmlspecialchars($bulan_aktif) . " " . htmlspecialchars($tahun_aktif); ?></p>
    </div>

    <?php if (empty($tagihan_siswa)): ?>
        <div class="alert alert-success text-center">Tidak ada tagihan di bulan ini.</div>
    <?php else: ?>
        <table class="table table-bordered">
            <thead class="table-primary">
                <tr>
                    <th>No</th>
                    <th>No Formulir</th>
                    <th>Nama Siswa</th>
                    <th>Jenis Tagihan</th>
                    <th>Bulan</th>
                    <th>Angsuran ke</th>
                    <th>Jumlah Tagihan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($tagihan_siswa as $ts): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><?= htmlspecialchars($ts['no_formulir']); ?></td>
                        <td><?= htmlspecialchars($ts['nama']); ?></td>
                        <td><?= htmlspecialchars($ts['jenis_pembayaran']); ?></td>
                        <td><?= htmlspecialchars($ts['bulan']); ?></td>
                        <td><?= $ts['angsuran_ke'] ? htmlspecialchars($ts['angsuran_ke']) : '-'; ?></td>
                        <td>Rp <?= number_format($ts['jumlah'],0,',','.'); ?></td>
                        <td><?= htmlspecialchars($ts['status_pembayaran']); ?></td>
                    </tr>
                <?php endforeach; ?>
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
