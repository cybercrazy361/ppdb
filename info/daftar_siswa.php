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
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0e7ff 0%, #fff 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .unit-card {
            cursor: pointer;
            border: none;
            border-radius: 1.3rem;
            transition: box-shadow 0.2s, border-color 0.2s, transform 0.14s;
            box-shadow: 0 4px 20px rgba(74,0,224,0.08);
            background: #fff;
        }
        .unit-card.selected, .unit-card:hover {
            border: 2.5px solid #4a00e0;
            box-shadow: 0 6px 28px rgba(74,0,224,0.11);
            transform: translateY(-4px) scale(1.03);
        }
        .unit-card .card-title {
            font-size: 1.7rem;
            font-weight: 800;
            color: #4a00e0;
            letter-spacing: -1px;
        }
        .unit-card .icon-container {
            width: 70px;
            height: 70px;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 2.3rem;
            background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%);
        }
        .unit-card.selected .icon-container,
        .unit-card:hover .icon-container {
            background: linear-gradient(135deg, #4a00e0 0%, #8e2de2 100%);
            color: #fff;
        }
        .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #2d2c55;
            margin-bottom: 0.9rem;
            letter-spacing: -1.5px;
        }
        .search-box {
            max-width: 320px;
            margin: 0 auto 1.2rem;
            box-shadow: 0 2px 12px rgba(74,0,224,0.06);
        }
        .table-responsive {
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(74,0,224,0.07);
            background: #fff;
            padding: 18px 6px;
        }
        .table th, .table td {
            vertical-align: middle;
            font-size: 1rem;
        }
        .badge.bg-success {background-color: #45c39d !important;}
        .badge.bg-warning {background-color: #ffa726 !important; color:#fff;}
        .badge.bg-danger {background-color: #f44336 !important;}
        .btn-secondary {
            border-radius: 1.3rem;
            font-weight: 600;
            letter-spacing: 0.1px;
        }
        /* Custom scrollbar for table */
        ::-webkit-scrollbar {height:8px; width:8px;}
        ::-webkit-scrollbar-thumb {background:#c9c9ec; border-radius:10px;}
        ::-webkit-scrollbar-track {background:transparent;}
        @media (max-width: 767.98px) {
            .unit-card .card-title { font-size: 1.12rem; }
            .unit-card { padding: 1rem 0 !important; }
            .section-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="container py-5">
    <h2 class="section-title text-center">Lihat Daftar Siswa SPMB</h2>
    <div class="row justify-content-center mb-4" id="unitCards">
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit=='SMA'?' selected':'' ?>" onclick="pilihUnit('SMA')">
                <div class="card-body">
                    <div class="icon-container"><i class="fa fa-graduation-cap"></i></div>
                    <div class="card-title">SMA Dharma Karya</div>
                    <div class="text-secondary">Lihat siswa SMA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit=='SMK'?' selected':'' ?>" onclick="pilihUnit('SMK')">
                <div class="card-body">
                    <div class="icon-container"><i class="fa fa-cogs"></i></div>
                    <div class="card-title">SMK Dharma Karya</div>
                    <div class="text-secondary">Lihat siswa SMK</div>
                </div>
            </div>
        </div>
    </div>

    <div id="areaTabel">
        <?php if ($unit): ?>
        <h4 class="mb-3 mt-4 text-center fw-bold"><?= htmlspecialchars($unit_label) ?></h4>
        <div class="mb-3 search-box">
            <input type="text" class="form-control form-control-lg" id="searchInput" placeholder="Cari nama atau no formulir...">
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width:42px;">No</th>
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
                    <td>
                        <?php
                        $status_ppdb = '-';
                        if (!empty($row['calon_pendaftar_id'])) {
                            $res_status = $conn->prepare("SELECT status FROM calon_pendaftar WHERE id=? LIMIT 1");
                            $res_status->bind_param("i", $row['calon_pendaftar_id']);
                            $res_status->execute();
                            $res_status->bind_result($status_ppdb);
                            $res_status->fetch();
                            $res_status->close();
                            $status_ppdb = trim(strtolower($status_ppdb));
                        }
                        if ($status_ppdb === 'ppdb bersama') {
                            echo '<span class="badge bg-info text-dark">PPDB Bersama</span>';
                        } else {
                            echo '<span class="badge bg-'.$badge.'">'.htmlspecialchars($status).'</span>';
                        }
                        ?>
                        </td>
                    <td><?= htmlspecialchars($metode) ?></td>
                    <td><?= htmlspecialchars($row['tanggal_pendaftaran']) ?></td>
                </tr>
                <?php endwhile; $stmt->close(); ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <a href="/" class="btn btn-secondary mt-4 px-5 py-2 d-block mx-auto" style="max-width:220px;">Kembali ke Beranda</a>
</div>

<script>
function pilihUnit(unit) {
    window.location.href = 'daftar_siswa.php?unit=' + unit;
}
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
