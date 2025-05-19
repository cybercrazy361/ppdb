<?php
// Aktifkan error reporting saat pengembangan (hapus di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['pimpinan']) || !isset($_SESSION['unit'])) {
    header("Location: login_pimpinan.php");
    exit();
}
include '../database_connection.php';

$unit = $_SESSION['unit'];
$tagihan_total = 5000000; // <--- GANTI sesuai jumlah tagihan seharusnya!

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pimpinan <?= htmlspecialchars($unit) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">PPDB <?= htmlspecialchars($unit) ?></a>
        <span class="navbar-text ms-auto">
            <?= htmlspecialchars($_SESSION['pimpinan']) ?> (<?= htmlspecialchars($unit) ?>)
            | <a href="logout.php" class="btn btn-sm btn-outline-danger ms-2">Logout</a>
        </span>
    </div>
</nav>

<div class="container mb-4">
    <h2 class="mb-3 fw-bold" style="color:var(--clr-primary)">Data Siswa <?= htmlspecialchars($unit) ?> & Status Pembayaran</h2>
    <div class="mb-3">
        <input type="text" class="form-control" id="searchInput" placeholder="Cari nama / formulir...">
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-primary">
                <tr>
                    <th>No</th>
                    <th>No Formulir</th>
                    <th>Nama Siswa</th>
                    <th>JK</th>
                    <th>Asal Sekolah</th>
                    <th>Status Pembayaran</th>
                    <th>Metode</th>
                    <th>Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody id="tabelSiswa">
                <?php
                // Query siswa + total bayar + status pembayaran dinamis + metode terakhir
                $sql = "SELECT s.*, 
                            COALESCE((SELECT SUM(jumlah) FROM pembayaran WHERE siswa_id=s.id),0) AS total_bayar,
                            (SELECT metode_pembayaran FROM pembayaran WHERE siswa_id=s.id ORDER BY tanggal_pembayaran DESC, id DESC LIMIT 1) AS metode_terakhir,
                            (SELECT COUNT(*) FROM pembayaran WHERE siswa_id=s.id) AS jumlah_transaksi
                        FROM siswa s
                        WHERE s.unit = ?
                        ORDER BY s.nama ASC";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    echo "<tr><td colspan='8' class='text-danger'>Terjadi kesalahan: " . htmlspecialchars($conn->error) . "</td></tr>";
                } else {
                    $stmt->bind_param("s", $unit);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $no = 1;
                    while ($row = $result->fetch_assoc()):
                        $total_bayar = (float)$row['total_bayar'];
                        $jumlah_transaksi = (int)$row['jumlah_transaksi'];
                        if ($jumlah_transaksi == 0) {
                            $status = "Belum Bayar";
                            $badge = "danger";
                        } elseif ($total_bayar >= $tagihan_total) {
                            $status = "Lunas";
                            $badge = "success";
                        } else {
                            $status = "Angsuran";
                            $badge = "warning";
                        }
                        $metode = $row['metode_terakhir'] ?? 'Belum Ada';
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['no_formulir']) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td><?= substr($row['jenis_kelamin'], 0, 1) ?></td>
                        <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td><?= htmlspecialchars($metode) ?></td>
                        <td><?= htmlspecialchars($row['tanggal_pendaftaran']) ?></td>
                    </tr>
                <?php
                    endwhile;
                    $stmt->close();
                }
                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search/filter tabel realtime
document.getElementById('searchInput').addEventListener('keyup', function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll('#tabelSiswa tr');
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>
