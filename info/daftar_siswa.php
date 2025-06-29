<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../database_connection.php';

function tanggal_indo($tgl)
{
    $bulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];
    $p = explode('-', $tgl);
    return $p[2] . ' ' . $bulan[(int) $p[1]] . ' ' . $p[0];
}

$unit = isset($_GET['unit']) ? $_GET['unit'] : '';
$unit_label = '';
if ($unit == 'SMA') {
    $unit_label = 'SMA Dharma Karya';
}
if ($unit == 'SMK') {
    $unit_label = 'SMK Dharma Karya';
}
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
        .rekap-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            justify-content: center;
            margin-bottom: 1.4rem;
        }
        .rekap-card {
            min-width: 150px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 3px 18px rgba(74,0,224,0.09);
            padding: 18px 24px;
            text-align: center;
            font-weight: 600;
            transition: box-shadow 0.15s, transform 0.13s;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .rekap-card .icon {
            font-size: 2.2rem;
            margin-bottom: 8px;
        }
        .rekap-card.total   { border-left: 5px solid #1976d2; }
        .rekap-card.lunas   { border-left: 5px solid #45c39d; }
        .rekap-card.angsuran { border-left: 5px solid #ffa726; }
        .rekap-card.belum   { border-left: 5px solid #f44336; }
        .rekap-card.ppdb    { border-left: 5px solid #00bcd4; }
        .rekap-card .label {
            font-size: 1.05rem;
            margin-top: 3px;
            font-weight: 600;
        }
        .rekap-card .jumlah {
            font-size: 1.7rem;
            font-weight: 900;
            letter-spacing: 0.5px;
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
        .badge {
        box-shadow: 0 2px 10px 0 rgba(0,0,0,0.08);
        }

        ::-webkit-scrollbar {height:8px; width:8px;}
        ::-webkit-scrollbar-thumb {background:#c9c9ec; border-radius:10px;}
        ::-webkit-scrollbar-track {background:transparent;}
        @media (max-width: 767.98px) {
            .unit-card .card-title { font-size: 1.12rem; }
            .unit-card { padding: 1rem 0 !important; }
            .section-title { font-size: 1.4rem; }
            .rekap-row { flex-direction: column; gap: 8px; }
            .rekap-card { min-width: unset; width: 100%; }
        }
        .rekap-card:hover {
        transform: translateY(-6px) scale(1.04);
        box-shadow: 0 8px 40px 0 rgba(32,40,100,0.15);
        }

    </style>
</head>
<body>

<div class="container py-5">
    <h2 class="section-title text-center">Daftar Siswa SPMB</h2>
    <div class="row justify-content-center mb-2" id="unitCards">
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit == 'SMA'
                ? ' selected'
                : '' ?>" onclick="pilihUnit('SMA')">
                <div class="card-body">
                    <div class="icon-container"><i class="fa fa-graduation-cap"></i></div>
                    <div class="card-title">SMA Dharma Karya</div>
                    <div class="text-secondary">Klik untuk melihat</div>
                </div>
            </div>
        </div>
        <?php
/*
        <div class="col-md-4 mb-3">
            <div class="card unit-card text-center py-4<?= $unit=='SMK'?' selected':'' ?>" onclick="pilihUnit('SMK')">
                <div class="card-body">
                    <div class="icon-container"><i class="fa fa-cogs"></i></div>
                    <div class="card-title">SMK Dharma Karya</div>
                    <div class="text-secondary">Lihat siswa SMK</div>
                </div>
            </div>
        </div>
        */
?>
    </div>

    <div id="areaTabel">
        <?php if ($unit): ?>
        <div class="mb-3 search-box">
            <input type="text" class="form-control form-control-lg" id="searchInput" placeholder="Cari nama atau no formulir...">
        </div>
        <?php
        // --- Query Siswa & Summary ---
        $tagihan_total = 0;
        $id_jenis_pangkal = null;
        $res_pangkal = $conn->query(
            "SELECT id FROM jenis_pembayaran WHERE nama='Uang Pangkal' LIMIT 1"
        );
        if ($res_pangkal && ($row = $res_pangkal->fetch_assoc())) {
            $id_jenis_pangkal = $row['id'];
            $res_nom = $conn->prepare(
                'SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=? AND unit=? LIMIT 1'
            );
            $res_nom->bind_param('is', $id_jenis_pangkal, $unit);
            $res_nom->execute();
            $res_nom->bind_result($tagihan_total);
            $res_nom->fetch();
            $res_nom->close();
        }
        $sql = "SELECT s.*, 
            COALESCE((SELECT SUM(jumlah) FROM pembayaran WHERE siswa_id=s.id),0) AS total_bayar,
            (SELECT metode_pembayaran FROM pembayaran WHERE siswa_id=s.id ORDER BY tanggal_pembayaran DESC, id DESC LIMIT 1) AS metode_terakhir,
            (SELECT COUNT(*) FROM pembayaran WHERE siswa_id=s.id) AS jumlah_transaksi,
            (SELECT no_invoice FROM pembayaran WHERE siswa_id=s.id ORDER BY tanggal_pembayaran DESC, id DESC LIMIT 1) AS no_invoice_terakhir
        FROM siswa s
        WHERE s.unit = ?
        ORDER BY s.nama ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $unit);
        $stmt->execute();
        $result = $stmt->get_result();
        $no = 1;
        $total = $lunas = $angsuran = $belum = $ppdb_bersama = 0;
        $rows_html = '';

        while ($row = $result->fetch_assoc()):
            $status = '';
            $badge = '';
            $status_ppdb = '-';

            // Ambil status PPDB
            if (!empty($row['calon_pendaftar_id'])) {
                $res_status = $conn->prepare(
                    'SELECT status FROM calon_pendaftar WHERE id=? LIMIT 1'
                );
                $res_status->bind_param('i', $row['calon_pendaftar_id']);
                $res_status->execute();
                $res_status->bind_result($status_ppdb);
                $res_status->fetch();
                $res_status->close();
                $status_ppdb = trim(strtolower($status_ppdb));
            }

            // =========== LOGIKA BARU (SAMA PERSIS DASHBOARD) ==============
            if ($status_ppdb === 'ppdb bersama') {
                $ppdb_bersama++;
                $badge = 'info';
                $status = 'PPDB Bersama';
            } else {
                // CEK UANG PANGKAL PER SISWA
                $status_pembayaran = 'Belum Bayar';
                $stmtUP = $conn->prepare("
            SELECT 
                SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as lunas,
                COUNT(*) as total
            FROM pembayaran_detail pd
            JOIN pembayaran p ON pd.pembayaran_id = p.id
            WHERE p.siswa_id = ? AND pd.jenis_pembayaran_id = 1
        ");
                $stmtUP->bind_param('i', $row['id']);
                $stmtUP->execute();
                $resUP = $stmtUP->get_result()->fetch_assoc();
                $stmtUP->close();

                if ($resUP['lunas'] > 0) {
                    $lunas++;
                    $badge = 'success';
                    $status = 'Lunas';
                } elseif ($resUP['total'] > 0) {
                    $angsuran++;
                    $badge = 'warning';
                    $status = 'Angsuran';
                } else {
                    $belum++;
                    $badge = 'danger';
                    $status = 'Belum Bayar';
                }
            }
            $total++;

            $metode = $row['metode_terakhir'] ?? 'Belum Ada';

            $rows_html .= '<tr>';
            $rows_html .= '<td>' . $no++ . '</td>';
            $rows_html .=
                '<td>' . htmlspecialchars($row['no_invoice'] ?? '-') . '</td>';
            $rows_html .= '<td>' . htmlspecialchars($row['nama']) . '</td>';
            $rows_html .=
                '<td>' . substr($row['jenis_kelamin'], 0, 1) . '</td>';
            $rows_html .=
                '<td>' . htmlspecialchars($row['asal_sekolah']) . '</td>';
            //$rows_html .= '<td><span class="badge bg-' . $badge . ($badge=='info'?' text-dark':'') . '">' . htmlspecialchars($status) . '</span></td>';
            //$rows_html .= '<td>' . htmlspecialchars($metode) . '</td>';
            // Format tanggal jadi tgl bulan tahun
            $rows_html .=
                '<td>' .
                htmlspecialchars(tanggal_indo($row['tanggal_pendaftaran'])) .
                '</td>';
            $rows_html .= '</tr>';
        endwhile;
        $stmt->close();
        ?>
        <!-- REKAP MODERN -->
        <div class="rekap-row mb-4">
            <div class="rekap-card total">
                <div class="icon text-primary"><i class="fas fa-users"></i></div>
                <span class="jumlah"><?= $total ?></span>
                <span class="label">Total Siswa</span>
            </div>
            <div class="rekap-card lunas">
                <div class="icon" style="color:#45c39d"><i class="fas fa-check-circle"></i></div>
                <span class="jumlah"><?= $lunas ?></span>
                <span class="label">Lunas</span>
            </div>
            <div class="rekap-card angsuran">
                <div class="icon" style="color:#ffa726"><i class="fas fa-coins"></i></div>
                <span class="jumlah"><?= $angsuran ?></span>
                <span class="label">Angsuran</span>
            </div>
            <div class="rekap-card belum">
                <div class="icon" style="color:#f44336"><i class="fas fa-exclamation-circle"></i></div>
                <span class="jumlah"><?= $belum ?></span>
                <span class="label">Belum Bayar</span>
            </div>
            <div class="rekap-card ppdb">
                <div class="icon" style="color:#00bcd4"><i class="fas fa-handshake"></i></div>
                <span class="jumlah"><?= $ppdb_bersama ?></span>
                <span class="label">PPDB Bersama</span>
            </div>
        </div>
        <!-- END REKAP MODERN -->

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width:42px;">No</th>
                        <th>No Formulir</th>
                        <th>Nama Siswa</th>
                        <th>JK</th>
                        <th>Asal Sekolah</th>
                        <!-- <th>Status Pembayaran</th> -->
                        <!-- <th>Metode</th> -->
                        <th>Tanggal Daftar</th>
                    </tr>
                </thead>
                <tbody id="tabelSiswa">
                    <?= $rows_html ?>
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
