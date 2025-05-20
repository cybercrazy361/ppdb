<?php
// Aktifkan error reporting saat pengembangan (hapus di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['pimpinan']) || !isset($_SESSION['unit'])) {
    header("Location: login_pimpinan");
    exit();
}
include(__DIR__ . '/../database_connection.php');

$unit = $_SESSION['unit'];
// Dapatkan tagihan Uang Pangkal sesuai unit dari DB (pengaturan_nominal)
$tagihan_total = 0;
$id_jenis_pangkal = null;
$res_pangkal = $conn->query("SELECT id FROM jenis_pembayaran WHERE nama='Uang Pangkal' LIMIT 1");
if ($res_pangkal && $row = $res_pangkal->fetch_assoc()) {
    $id_jenis_pangkal = $row['id'];
    $res_nom = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=? AND unit=? LIMIT 1");
    $res_nom->bind_param("is", $id_jenis_pangkal, $unit);
    $res_nom->execute();
    $res_nom->bind_result($tagihan_total);
    $res_nom->fetch();
    $res_nom->close();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Pimpinan <?= htmlspecialchars($unit) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            html, body {
                background: #fff !important;
                color: #000 !important;
                font-family: 'Arial', 'Calibri', sans-serif !important;
                font-size: 12pt !important;
                margin: 0;
                padding: 0;
            }
            .navbar, #searchInput, .btn-cetak, .navbar-text, .d-print-none {
                display: none !important;
            }
            .container {
                width: 96% !important;
                margin: 0 auto !important;
                padding: 0 !important;
            }
            .print-title {
                display: block !important;
                text-align: center;
                color: #000 !important;
                font-size: 20pt !important;
                font-weight: bold !important;
                margin-bottom: 5px;
                margin-top: 30px;
            }
            .print-title small {
                display: block;
                color: #000 !important;
                font-size: 11pt !important;
                font-weight: normal !important;
                margin-bottom: 10px;
            }
            .judul-tabel-print {
                display: block !important;
                text-align: center;
                color: #000 !important;
                font-size: 15pt !important;
                font-weight: bold !important;
                margin-bottom: 18px;
            }
            .table-responsive {
                margin: 0 !important;
            }
            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin: auto;
                font-size: 11pt !important;
                background: #fff !important;
            }
            .table th, .table td {
                border: 1px solid #333 !important;
                padding: 7px 8px !important;
                color: #000 !important;
            }
            .table thead th {
                background: #ececec !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            .badge {
                color: #000 !important;
                background: none !important;
                border: none !important;
                font-weight: bold !important;
                font-size: 11pt !important;
                padding: 0 !important;
            }
        }
        .print-title, .judul-tabel-print {
            display: none;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">PPDB <?= htmlspecialchars($unit) ?></a>
        <span class="navbar-text ms-auto">
            <?= htmlspecialchars($_SESSION['pimpinan']) ?> (<?= htmlspecialchars($unit) ?>)
            | <a href="logout" class="btn btn-sm btn-outline-danger ms-2">Logout</a>
        </span>
    </div>
</nav>

<div class="container mb-4">
    <!-- Judul Print -->
    <div class="print-title">
        DATA SISWA & STATUS PEMBAYARAN <?= strtoupper(htmlspecialchars($unit)) ?><br>
        <small><?= date('d-m-Y H:i') ?> Dicetak oleh: <?= htmlspecialchars($_SESSION['pimpinan']) ?></small>
    </div>
    <div class="judul-tabel-print">
        Data Siswa <?= htmlspecialchars($unit) ?> & Status Pembayaran
    </div>

    <!-- Tombol Cetak -->
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <h2 class="fw-bold m-0" style="color:var(--clr-primary)">Data Siswa <?= htmlspecialchars($unit) ?> & Status Pembayaran</h2>
        <button onclick="window.print()" class="btn btn-cetak btn-success"><i class="fa fa-print"></i> Cetak</button>
    </div>
    <div class="mb-3 d-print-none">
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
