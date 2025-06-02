<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/../database_connection.php');

$unit = isset($_GET['unit']) ? $_GET['unit'] : '';
$unit_label = '';
if ($unit == 'SMA') $unit_label = 'SMA Dharma Karya';
if ($unit == 'SMK') $unit_label = 'SMK Dharma Karya';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Siswa Terdaftar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        .unit-card {
            cursor: pointer;
            border: 2px solid #e6e6e6;
            transition: box-shadow 0.2s, border-color 0.2s;
        }
        .unit-card.selected, .unit-card:hover {
            border-color: #4a00e0;
            box-shadow: 0 4px 20px rgba(74,0,224,0.09);
        }
        .unit-card .card-title {
            font-size: 1.7rem;
            font-weight: 700;
            color: #4a00e0;
        }
    </style>
</head>
<body class="bg-light">

<div class="container my-5">
    <h2 class="fw-bold mb-4 text-center">Lihat Daftar Siswa Berdasarkan Unit</h2>

    <!-- Card Pilihan Unit -->
    <div class="row justify-content-center mb-4" id="unitCards">
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit=='SMA'?' selected':'' ?>" onclick="pilihUnit('SMA')">
                <div class="card-body">
                    <i class="fa fa-graduation-cap fa-3x mb-2 text-primary"></i>
                    <div class="card-title">SMA Dharma Karya</div>
                    <div class="text-secondary">Lihat siswa SMA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit=='SMK'?' selected':'' ?>" onclick="pilihUnit('SMK')">
                <div class="card-body">
                    <i class="fa fa-cogs fa-3x mb-2 text-success"></i>
                    <div class="card-title">SMK Dharma Karya</div>
                    <div class="text-secondary">Lihat siswa SMK</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Siswa -->
    <div id="areaTabel">
        <?php if ($unit): ?>
        <h4 class="mb-3 mt-4 text-center fw-bold">Daftar Siswa <?= htmlspecialchars($unit_label) ?></h4>
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
                // Tagihan total (optional, jika mau status lunas/angsuran)
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
                $sql = "SELECT s.*, 
                            COALESCE((SELECT SUM(jumlah) FROM pembayaran WHERE siswa_id=s.id),0) AS total_bayar,
                            (SELECT metode_pembayaran FROM pembayaran WHERE siswa_id=s.id ORDER BY tanggal_pembayaran DESC, id DESC LIMIT 1) AS metode_terakhir,
                            (SELECT COUNT(*) FROM pembayaran WHERE siswa_id=s.id) AS jumlah_transaksi
                        FROM siswa s
                        WHERE s.unit = ?
                        ORDER BY s.nama ASC";
                $stmt = $conn->prepare($sql);
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
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <a href="/" class="btn btn-secondary mt-4">Kembali ke Beranda</a>
</div>

<!-- Bootstrap & font-awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<script>
function pilihUnit(unit) {
    // reload halaman dengan parameter unit
    window.location.href = 'daftar_siswa.php?unit=' + unit;
}

// Search/filter tabel realtime
document.addEventListener('DOMContentLoaded', function() {
    const search = document.getElementById('searchInput');
    if(search) {
        search.addEventListener('keyup', function() {
            var filter = this.value.toLowerCase();
            var rows = document.querySelectorAll('#tabelSiswa tr');
            rows.forEach(function(row) {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>
