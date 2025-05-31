<?php
// FILE: dashboard_yayasan

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['pimpinan']) || !isset($_SESSION['unit']) || $_SESSION['unit'] !== 'Yayasan') {
    header("Location: login_pimpinan");
    exit();
}
include(__DIR__ . '/../database_connection.php');

// Ambil ID jenis pembayaran Uang Pangkal
$id_jenis_pangkal = null;
$res_pangkal = $conn->query("SELECT id FROM jenis_pembayaran WHERE nama='Uang Pangkal' LIMIT 1");
if ($res_pangkal && $row = $res_pangkal->fetch_assoc()) {
    $id_jenis_pangkal = $row['id'];
}

// Ambil nominal tagihan Uang Pangkal per unit dari DB
$tagihan_unit = [];
if ($id_jenis_pangkal) {
    $q = $conn->query("SELECT unit, nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=$id_jenis_pangkal");
    while ($row = $q->fetch_assoc()) {
        $tagihan_unit[$row['unit']] = (float)$row['nominal_max'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Yayasan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            html, body { background: #fff !important; color: #000 !important; font-family: 'Arial', 'Calibri', sans-serif !important; font-size: 12pt !important; margin: 0; padding: 0; }
            .navbar, #searchInput, .btn-cetak, .navbar-text, .d-print-none, .filter-unit { display: none !important; }
            .container { width: 96% !important; margin: 0 auto !important; padding: 0 !important; }
            .print-title { display: block !important; text-align: center; color: #000 !important; font-size: 20pt !important; font-weight: bold !important; margin-bottom: 5px; margin-top: 30px; }
            .print-title small { display: block; color: #000 !important; font-size: 11pt !important; font-weight: normal !important; margin-bottom: 10px; }
            .judul-tabel-print { display: block !important; text-align: center; color: #000 !important; font-size: 15pt !important; font-weight: bold !important; margin-bottom: 18px; }
            .table-responsive { margin: 0 !important; }
            .table { width: 100% !important; border-collapse: collapse !important; margin: auto; font-size: 11pt !important; background: #fff !important; }
            .table th, .table td { border: 1px solid #333 !important; padding: 7px 8px !important; color: #000 !important; }
            .table thead th { background: #ececec !important; color: #000 !important; font-weight: bold !important; }
            .badge { color: #000 !important; background: none !important; border: none !important; font-weight: bold !important; font-size: 11pt !important; padding: 0 !important; }
        }
        .print-title, .judul-tabel-print { display: none; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Dashboard Yayasan</a>
        <span class="navbar-text ms-auto">
            <?= htmlspecialchars($_SESSION['pimpinan']) ?>
            | <a href="logout" class="btn btn-sm btn-outline-danger ms-2">Logout</a>
        </span>
    </div>
</nav>

<div class="container mb-4">
    <!-- Judul Print -->
    <div class="print-title">
        DATA SISWA & STATUS PEMBAYARAN YAYASAN<br>
        <small><?= date('d-m-Y H:i') ?> Dicetak oleh: <?= htmlspecialchars($_SESSION['pimpinan']) ?></small>
    </div>
    <div class="judul-tabel-print">
        Data Siswa SMA & SMK & Status Pembayaran
    </div>

    <!-- Tombol Cetak dan Filter Unit -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 d-print-none">
        <h2 class="fw-bold m-0" style="color:var(--clr-primary)">Data Siswa SMA & SMK & Status Pembayaran</h2>
        <div>
            <select id="unitFilter" class="form-select filter-unit d-inline-block me-2" style="width:auto;min-width:130px;">
                <option value="">Semua Unit</option>
                <option value="SMA">SMA</option>
                <option value="SMK">SMK</option>
            </select>
            <button onclick="window.print()" class="btn btn-cetak btn-success"><i class="fa fa-print"></i> Cetak</button>
        </div>
    </div>
    <div class="mb-3 d-print-none">
        <input type="text" class="form-control" id="searchInput" placeholder="Cari nama / formulir...">
    </div>
    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="tabelSiswaYayasan">
            <thead class="table-primary">
                <tr>
                    <th>No</th>
                    <th>Unit</th>
                    <th>No Formulir</th>
                    <th>Nama Siswa</th>
                    <th>JK</th>
                    <th>Asal Sekolah</th>
                    <th>Status Pembayaran</th>
                    <th>Metode</th>
                    <th>Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT s.*, 
                            COALESCE((SELECT SUM(jumlah) FROM pembayaran WHERE siswa_id=s.id),0) AS total_bayar,
                            (SELECT metode_pembayaran FROM pembayaran WHERE siswa_id=s.id ORDER BY tanggal_pembayaran DESC, id DESC LIMIT 1) AS metode_terakhir,
                            (SELECT COUNT(*) FROM pembayaran WHERE siswa_id=s.id) AS jumlah_transaksi
                        FROM siswa s
                        ORDER BY s.unit ASC, s.nama ASC";
                $result = $conn->query($sql);
                $no = 1;
                while ($row = $result->fetch_assoc()):
                    $unit = $row['unit'];
                    $total_bayar = (float)$row['total_bayar'];
                    $jumlah_transaksi = (int)$row['jumlah_transaksi'];
                    $tagihan_total = isset($tagihan_unit[$unit]) ? $tagihan_unit[$unit] : 0;
                    if ($jumlah_transaksi == 0) {
                        $status = "Belum Bayar";
                        $badge = "danger";
                    } elseif ($total_bayar >= $tagihan_total && $tagihan_total > 0) {
                        $status = "Lunas";
                        $badge = "success";
                    } else {
                        $status = "Angsuran";
                        $badge = "warning";
                    }
                    $metode = $row['metode_terakhir'] ?? 'Belum Ada';
                ?>
                    <tr data-unit="<?= htmlspecialchars($unit) ?>">
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($unit) ?></td>
                        <td><?= htmlspecialchars($row['no_formulir']) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td><?= substr($row['jenis_kelamin'], 0, 1) ?></td>
                        <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status) ?></span></td>
                        <td><?= htmlspecialchars($metode) ?></td>
                        <td><?= htmlspecialchars($row['tanggal_pendaftaran']) ?></td>
                    </tr>
                <?php endwhile; $conn->close(); ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function resetNomorUrut() {
    let rows = document.querySelectorAll('#tabelSiswaYayasan tbody tr');
    let n = 1;
    rows.forEach(function(row) {
        if (row.style.display !== "none") {
            row.children[0].textContent = n++;
        }
    });
}

document.getElementById('searchInput').addEventListener('keyup', function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll('#tabelSiswaYayasan tbody tr');
    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
    resetNomorUrut();
});

document.getElementById('unitFilter').addEventListener('change', function() {
    var unit = this.value;
    var rows = document.querySelectorAll('#tabelSiswaYayasan tbody tr');
    rows.forEach(function(row) {
        if (unit === "" || row.getAttribute('data-unit') === unit) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
    resetNomorUrut();
});

// Pastikan nomor urut benar saat pertama kali load
window.addEventListener('DOMContentLoaded', resetNomorUrut);
</script>

</body>
</html>
