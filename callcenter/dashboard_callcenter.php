<?php
session_start();
include '../database_connection.php';

// Validasi login call center
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    header('Location: login_callcenter.php');
    exit();
}

$unit = $_SESSION['unit'];

// Statistik utama calon pendaftar
function getCallCenterStats($conn, $unit)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM calon_pendaftar WHERE pilihan = ?'
    );
    $stmt->bind_param('s', $unit);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    $statusList = [
        'PPDB Bersama' => 0,
        'Sudah Bayar' => 0,
        'Uang Titipan' => 0,
        'Akan Bayar' => 0,
        'Menunggu Negeri' => 0,
        'Menunggu Progres' => 0,
        'Tidak Ada Konfirmasi' => 0,
        'Tidak Jadi' => 0,
    ];

    $stmt2 = $conn->prepare(
        'SELECT status, COUNT(*) as jumlah FROM calon_pendaftar WHERE pilihan = ? GROUP BY status'
    );
    $stmt2->bind_param('s', $unit);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $statusList[$row['status']] = $row['jumlah'];
    }
    $stmt2->close();

    return [
        'total' => $total,
        'status' => $statusList,
    ];
}

$stat = getCallCenterStats($conn, $unit);
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Call Center – SPMB</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/callcenter_dashboard_styles.css" />
    <link rel="stylesheet" href="../assets/css/sidebar_callcenter_styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php $active = 'dashboard'; ?>
<?php include 'sidebar_callcenter.php'; ?>

<div class="main">
    <!-- Navbar / Topbar -->
    <header class="navbar">
        <div class="title">Dashboard Call Center (<?= htmlspecialchars(
            $unit
        ) ?>)</div>
        <div class="user-menu">
            <small>Halo, <?= htmlspecialchars($_SESSION['nama']) ?></small>
            <a href="../callcenter/logout_callcenter.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <!-- Dashboard Cards -->
    <section class="dashboard-cards">
        <div class="card shadow" onclick="showModal('sudahbayar')" style="cursor:pointer;">
            <div class="icon text-success"><i class="fas fa-cash-register"></i></div>
            <div class="title">Sudah Bayar</div>
            <div class="count"><?= $stat['status']['Sudah Bayar'] ?></div>
            <div class="subtext">Sudah Melunasi</div>
        </div>
        <div class="card shadow" onclick="showModal('all')" style="cursor:pointer;">
            <div class="icon text-primary"><i class="fas fa-users"></i></div>
            <div class="title">Total Calon Pendaftar</div>
            <div class="count"><?= $stat['total'] ?></div>
            <div class="subtext">Unit <?= htmlspecialchars($unit) ?></div>
        </div>
        <div class="card shadow" onclick="showModal('ppdb')" style="cursor:pointer;">
            <div class="icon text-success"><i class="fas fa-user-check"></i></div>
            <div class="title">PPDB Bersama</div>
            <div class="count"><?= $stat['status']['PPDB Bersama'] ?></div>
            <div class="subtext">Follow-up Berhasil</div>
        </div>
        <div class="card shadow" onclick="showModal('titipan')" style="cursor:pointer;">
            <div class="icon text-info"><i class="fas fa-money-bill-wave"></i></div>
            <div class="title">Uang Titipan</div>
            <div class="count"><?= $stat['status']['Uang Titipan'] ?></div>
            <div class="subtext">Sudah Titip Uang</div>
        </div>
        <div class="card shadow" onclick="showModal('akanbayar')" style="cursor:pointer;">
            <div class="icon text-warning"><i class="fas fa-hourglass-half"></i></div>
            <div class="title">Akan Bayar</div>
            <div class="count"><?= $stat['status']['Akan Bayar'] ?></div>
            <div class="subtext">Akan Melakukan Pembayaran</div>
        </div>
        <div class="card shadow" onclick="showModal('nunggunegeri')" style="cursor:pointer;">
            <div class="icon" style="color:#8f9dff;"><i class="fas fa-user-clock"></i></div>
            <div class="title">Menunggu Negeri</div>
            <div class="count"><?= $stat['status']['Menunggu Negeri'] ?></div>
            <div class="subtext">Menunggu Sekolah Negeri</div>
        </div>
        <div class="card shadow" onclick="showModal('nungguprogres')" style="cursor:pointer;">
            <div class="icon" style="color:#6d5eff;"><i class="fas fa-spinner"></i></div>
            <div class="title">Menunggu Progres</div>
            <div class="count"><?= $stat['status']['Menunggu Progres'] ?></div>
            <div class="subtext">Proses Seleksi</div>
        </div>
        <div class="card shadow" onclick="showModal('pending')" style="cursor:pointer;">
            <div class="icon text-danger"><i class="fas fa-user-question"></i></div>
            <div class="title">Tidak Ada Konfirmasi</div>
            <div class="count"><?= $stat['status'][
                'Tidak Ada Konfirmasi'
            ] ?></div>
            <div class="subtext">Belum dihubungi</div>
        </div>
        <div class="card shadow" onclick="showModal('tidakjadi')" style="cursor:pointer;">
            <div class="icon" style="color:#727a86;"><i class="fas fa-user-slash"></i></div>
            <div class="title">Tidak Jadi</div>
            <div class="count"><?= $stat['status']['Tidak Jadi'] ?></div>
            <div class="subtext">Batal Mendaftar</div>
        </div>
    </section>

    <!-- Chart Section -->
    <section class="chart-card">
        <h6>Status Pendaftar</h6>
        <div class="chart-container">
            <canvas id="chartStatus"></canvas>
        </div>
    </section>
</div>

<!-- Modal Calon Pendaftar -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Daftar Calon Pendaftar</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <table class="table table-striped">
            <thead>
                <tr><th>No</th><th>Nama</th><th>Status</th><th>No HP</th><th>Keterangan</th></tr>
            </thead>
            <tbody id="modalBody">
                <tr><td colspan="5" class="text-center">Memuat...</td></tr>
            </tbody>
            </table>
        </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar_callcenter.js"></script>
<script>
function showModal(status) {
    const body = document.getElementById('modalBody');
    body.innerHTML = '<tr><td colspan="5" class="text-center">Memuat...</td></tr>';
    let url = `fetch_calon_pendaftar.php?unit=<?= urlencode($unit) ?>`;
    if (status === 'ppdb') url += "&status=PPDB%20Bersama";
    else if (status === 'sudahbayar') url += "&status=Sudah%20Bayar";
    else if (status === 'titipan') url += "&status=Uang%20Titipan";
    else if (status === 'akanbayar') url += "&status=Akan%20Bayar";
    else if (status === 'nunggunegeri') url += "&status=Menunggu%20Negeri";
    else if (status === 'nungguprogres') url += "&status=Menunggu%20Progres";
    else if (status === 'pending') url += "&status=Tidak%20Ada%20Konfirmasi";
    else if (status === 'tidakjadi') url += "&status=Tidak%20Jadi";
    // "all" tidak tambah param status
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                body.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada data.</td></tr>';
                return;
            }
            body.innerHTML = data.map((s, i) =>
                `<tr>
                  <td>${i+1}</td>
                  <td>${s.nama}</td>
                  <td>${s.status}</td>
                  <td>${s.no_hp}</td>
                  <td>${s.notes ? s.notes : '-'}</td>
                </tr>`
            ).join('');
        })
        .catch(() => {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Gagal memuat.</td></tr>';
        });
    new bootstrap.Modal('#statusModal').show();
}
const ctx = document.getElementById('chartStatus').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [
            'PPDB Bersama',
            'Sudah Bayar',           
            'Uang Titipan',
            'Akan Bayar',
            'Menunggu Negeri',
            'Menunggu Progres',
            'Tidak Ada Konfirmasi',
            'Tidak Jadi'
        ],
        datasets: [{
            data: [
                <?= $stat['status']['PPDB Bersama'] ?>,
                <?= $stat['status']['Sudah Bayar'] ?>,   
                <?= $stat['status']['Uang Titipan'] ?>,
                <?= $stat['status']['Akan Bayar'] ?>,
                <?= $stat['status']['Menunggu Negeri'] ?>,
                <?= $stat['status']['Menunggu Progres'] ?>,
                <?= $stat['status']['Tidak Ada Konfirmasi'] ?>,
                <?= $stat['status']['Tidak Jadi'] ?>
            ],
            backgroundColor: [
                '#3ec86b', // PPDB Bersama
                '#12c15c', // Sudah Bayar
                '#36b9cc', // Uang Titipan
                '#f6c23e', // Akan Bayar
                '#8f9dff', // Menunggu Negeri
                '#6d5eff', // Menunggu Progres
                '#e75151', // Tidak Ada Konfirmasi
                '#727a86'  // Tidak Jadi
            ],
            hoverOffset: 18
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#e0eaff',  // warna legend teks lebih terang
                    font: {
                        family: "'Poppins', 'Segoe UI', Arial, sans-serif",
                        weight: 'bold',
                        size: 14
                    },
                    usePointStyle: true,
                    padding: 14
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const v = ctx.raw,
                            t = ctx.dataset.data.reduce((a,b) => a+b, 0),
                            p = ((v/t)*100).toFixed(1);
                        return `${ctx.label}: ${v} (${p}%)`;
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>
