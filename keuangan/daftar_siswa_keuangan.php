<?php
// daftar_siswa_keuangan.php

session_start();

// Pastikan pengguna sudah login dan memiliki peran yang sesuai
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}

// Include koneksi database
include '../database_connection.php';

// Ambil unit petugas dari sesi
$petugas_unit = $_SESSION['unit'];

// Generate CSRF Token (Jika diperlukan untuk fitur lainnya)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Ambil parameter pencarian
$search_no_formulir = isset($_GET['search_no_formulir'])
    ? trim($_GET['search_no_formulir'])
    : '';
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';

$years = [];
$resYears = $conn->query(
    'SELECT DISTINCT tahun_pelajaran FROM pembayaran ORDER BY tahun_pelajaran DESC'
);
while ($r = $resYears->fetch_assoc()) {
    $years[] = $r['tahun_pelajaran'];
}

// Pagination setup
$limit = 10; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$start = ($page - 1) * $limit;

// Step 1: Retrieve distinct student IDs based on search criteria and unit
$query_ids = "SELECT DISTINCT s.id 
             FROM siswa s 
             INNER JOIN pembayaran p ON s.id = p.siswa_id 
             INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id 
             INNER JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id 
             WHERE s.unit = ?";

$param_types_ids = 's';
$param_values_ids = [$petugas_unit];

// Tambahkan kondisi pencarian
if ($search_no_formulir !== '') {
    $query_ids .= ' AND s.no_formulir LIKE ?';
    $param_types_ids .= 's';
    $param_values_ids[] = '%' . $search_no_formulir . '%';
}

if ($search_nama !== '') {
    $query_ids .= ' AND s.nama LIKE ?';
    $param_types_ids .= 's';
    $param_values_ids[] = '%' . $search_nama . '%';
}

$query_ids .= ' ORDER BY s.nama ASC LIMIT ? OFFSET ?';

$param_types_ids .= 'ii';
$param_values_ids[] = $limit;
$param_values_ids[] = $start;

$stmt_ids = $conn->prepare($query_ids);
if ($stmt_ids) {
    $stmt_ids->bind_param($param_types_ids, ...$param_values_ids);
    $stmt_ids->execute();
    $result_ids = $stmt_ids->get_result();
    $student_ids = [];
    while ($row = $result_ids->fetch_assoc()) {
        $student_ids[] = $row['id'];
    }
    $stmt_ids->close();
} else {
    die('Error preparing statement for IDs: ' . $conn->error);
}

if (empty($student_ids)) {
    $siswa_data = []; // No students found
    $total = 0;
    $total_pages = 0;
} else {
    // Step 2: Count total distinct students for pagination
    $count_query = "SELECT COUNT(DISTINCT s.id) AS total 
                   FROM siswa s 
                   INNER JOIN pembayaran p ON s.id = p.siswa_id 
                   INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id 
                   INNER JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id 
                   WHERE s.unit = ?";

    $param_types_count = 's';
    $param_values_count = [$petugas_unit];

    // Tambahkan kondisi pencarian
    if ($search_no_formulir !== '') {
        $count_query .= ' AND s.no_formulir LIKE ?';
        $param_types_count .= 's';
        $param_values_count[] = '%' . $search_no_formulir . '%';
    }

    if ($search_nama !== '') {
        $count_query .= ' AND s.nama LIKE ?';
        $param_types_count .= 's';
        $param_values_count[] = '%' . $search_nama . '%';
    }

    $stmt_count = $conn->prepare($count_query);
    if ($stmt_count) {
        $stmt_count->bind_param($param_types_count, ...$param_values_count);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $total = $result_count->fetch_assoc()['total'];
        $total_pages = ceil($total / $limit);
        $stmt_count->close();
    } else {
        die('Error preparing count statement: ' . $conn->error);
    }

    // Now, fetch payment details for the retrieved student IDs
    // To prevent SQL injection, use prepared statements with dynamic IN clauses
    // MySQLi does not support binding an array directly, so we'll build the placeholders
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    $select_query = "SELECT 
    s.id AS siswa_id,
    s.no_formulir,
    s.nama,
    s.unit,
    p.id AS pembayaran_id,
    p.jumlah AS pembayaran_jumlah,
    p.metode_pembayaran AS pembayaran_metode,
    p.tahun_pelajaran,
    p.tanggal_pembayaran,
    p.keterangan AS pembayaran_keterangan,
    pd.id AS pembayaran_detail_id,
    jp.nama AS jenis_pembayaran,
    pd.jumlah AS detail_jumlah,
    pd.bulan,
    pd.status_pembayaran AS detail_status,
    pd.angsuran_ke,
    pd.cashback AS detail_cashback   -- << tambahkan ini
FROM siswa s
INNER JOIN pembayaran p ON s.id = p.siswa_id
INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
INNER JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
WHERE s.id IN ($placeholders)
ORDER BY s.nama ASC, p.tanggal_pembayaran DESC";

    $stmt = $conn->prepare($select_query);
    if ($stmt) {
        // Build the types string dynamically
        $types_select = str_repeat('i', count($student_ids));
        $stmt->bind_param($types_select, ...$student_ids);
        $stmt->execute();
        $result = $stmt->get_result();

        // Struktur data untuk menampung data siswa dan pembayaran mereka
        $siswa_data = [];
        while ($row = $result->fetch_assoc()) {
            $siswa_id = $row['siswa_id'];
            if (!isset($siswa_data[$siswa_id])) {
                $siswa_data[$siswa_id] = [
                    'no_formulir' => $row['no_formulir'],
                    'nama' => $row['nama'],
                    'unit' => $row['unit'],
                    'pembayaran' => [],
                ];
            }

            if ($row['pembayaran_id']) {
                $pembayaran_id = $row['pembayaran_id'];
                if (
                    !isset($siswa_data[$siswa_id]['pembayaran'][$pembayaran_id])
                ) {
                    $siswa_data[$siswa_id]['pembayaran'][$pembayaran_id] = [
                        'jumlah' => $row['pembayaran_jumlah'],
                        'metode_pembayaran' => $row['pembayaran_metode'],
                        'tahun_pelajaran' => $row['tahun_pelajaran'],
                        'tanggal_pembayaran' => $row['tanggal_pembayaran'],
                        'keterangan' => $row['pembayaran_keterangan'],
                        'details' => [],
                    ];
                }

                if ($row['pembayaran_detail_id']) {
                    $siswa_data[$siswa_id]['pembayaran'][$pembayaran_id][
                        'details'
                    ][] = [
                        'jenis_pembayaran' => $row['jenis_pembayaran'],
                        'jumlah' => $row['detail_jumlah'],
                        'bulan' => $row['bulan'],
                        'status_pembayaran' => $row['detail_status'],
                        'angsuran_ke' => $row['angsuran_ke'],
                        'cashback' => $row['detail_cashback'],
                    ];
                }
            }
        }
        $stmt->close();
    } else {
        die('Error preparing select statement: ' . $conn->error);
    }
}

// 1) Tentukan $tahun_cetak setelah data siswa & pembayaran terisi
if (isset($_GET['tahun_pelajaran']) && $_GET['tahun_pelajaran'] !== '') {
    $tahun_cetak = htmlspecialchars($_GET['tahun_pelajaran']);
} elseif (!empty($siswa_data)) {
    // Ambil siswa pertama
    $firstSiswa = reset($siswa_data);
    // Ambil pembayaran pertama dari siswa pertama
    $firstPemb = reset($firstSiswa['pembayaran']);
    $tahun_cetak = $firstPemb['tahun_pelajaran'];
} else {
    $tahun_cetak = '-';
}

// 2) Tutup koneksi setelah $tahun_cetak sudah benar
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Siswa Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/keuangan_siswa_styles.css">
    <style>
  /* CSS untuk tampilan cetak */
  @media print {
      body * {
          visibility: hidden;
      }
      .printable-area,
      .printable-area * {
          visibility: visible;
          /* Hapus semua shadow saat print */
          box-shadow: none !important;
      }
      .printable-area {
          position: absolute;
          left: 0;
          top: 0;
          width: 100%;
          /* Pastikan background putih */
          background: #fff !important;
      }
      /* Hilangkan scrollbar pada tabel saat dicetak */
      .table-responsive {
          overflow: visible !important;
          box-shadow: none !important;
      }
      /* Atur lebar tabel agar sesuai halaman */
      table {
          width: 100%;
      }
      /* Sembunyikan tombol cetak */
      .no-print {
          display: none;
      }
  }
</style>

</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link" href="#">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars(
                            $_SESSION['nama']
                        ) ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>

<div class="container-fluid">
    <!-- Judul + Tombol Cetak -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Daftar Siswa Keuangan - <?= htmlspecialchars($petugas_unit) ?>
        </h1>
<button type="button" class="btn btn-success no-print"
    onclick="window.open('cetak_siswa_keuangan.php?<?= http_build_query([
        'tahun_pelajaran' => $_GET['tahun_pelajaran'] ?? '',
        'search_no_formulir' => $search_no_formulir,
        'search_nama' => $search_nama,
    ]) ?>', '_blank')">
    <i class="fas fa-print"></i> Cetak Semua
</button>

        
    </div>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3" method="GET" action="daftar_siswa_keuangan.php">
                <div class="col-md-3">
                     <label for="tahun_pelajaran" class="form-label">Tahun Pelajaran</label>
    <select class="form-select" id="tahun_pelajaran" name="tahun_pelajaran">
      <option value="">— Semua —</option>
      <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>"
          <?= isset($_GET['tahun_pelajaran']) && $_GET['tahun_pelajaran'] == $y
              ? 'selected'
              : '' ?>>
          <?= $y ?>
        </option>
      <?php endforeach; ?>
    </select>
                    <label for="search_no_formulir" class="form-label">Cari No Formulir</label>
                    <input type="text" class="form-control" id="search_no_formulir" name="search_no_formulir"
                           placeholder="Masukkan No Formulir"
                           value="<?= htmlspecialchars($search_no_formulir) ?>">
                </div>
                <div class="col-md-3">
                    <label for="search_nama" class="form-label">Cari Nama Siswa</label>
                    <input type="text" class="form-control" id="search_nama" name="search_nama"
                           placeholder="Masukkan Nama Siswa"
                           value="<?= htmlspecialchars($search_nama) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="daftar_siswa_keuangan.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
                <div>
                    <button type="button" class="btn btn-warning no-print me-2" onclick="window.location='cetak_tagihan_siswa.php'">
                        <i class="fas fa-file-invoice"></i> Tagihan
                    </button>
                </div>
            </form>
        </div>
    </div>

            <!-- Siswa Table -->
            <div class="card shadow mb-4 printable-area">
                <div class="card-body">
                    <?php if (empty($siswa_data)): ?>
                        <p class="text-center">Tidak ada data siswa ditemukan.</p>
                    <?php
                        // Kumpulkan semua jenis pembayaran per siswa

                        // Jika tidak ada pembayaran, tampilkan "-"
                        // Format jumlah sebagai mata uang jika bukan '-', misalnya: 2000000 menjadi Rp. 2.000.000
                        // Format tanggal jika bukan '-', misalnya: 2024-12-15 menjadi 15/12/2024
                        // Membuat query string untuk pagination
                        // Maksimal jumlah link halaman yang ditampilkan

                        // Jika tidak cukup halaman di akhir, geser ke kiri
                        // Kumpulkan semua jenis pembayaran per siswa
                        // Jika tidak ada pembayaran, tampilkan "-"
                        // Format jumlah sebagai mata uang jika bukan '-', misalnya: 2000000 menjadi Rp. 2.000.000
                        // Format tanggal jika bukan '-', misalnya: 2024-12-15 menjadi 15/12/2024
                        // Membuat query string untuk pagination
                        // Maksimal jumlah link halaman yang ditampilkan
                        // Jika tidak cukup halaman di akhir, geser ke kiri
                        // Kumpulkan semua jenis pembayaran per siswa

                        // Jika tidak ada pembayaran, tampilkan "-"
                        // Format jumlah sebagai mata uang jika bukan '-', misalnya: 2000000 menjadi Rp. 2.000.000
                        // Format tanggal jika bukan '-', misalnya: 2024-12-15 menjadi 15/12/2024
                        // Membuat query string untuk pagination
                        // Maksimal jumlah link halaman yang ditampilkan

                        // Jika tidak cukup halaman di akhir, geser ke kiri
                        // Kumpulkan semua jenis pembayaran per siswa
                        // Jika tidak ada pembayaran, tampilkan "-"
                        // Format jumlah sebagai mata uang jika bukan '-', misalnya: 2000000 menjadi Rp. 2.000.000
                        // Format tanggal jika bukan '-', misalnya: 2024-12-15 menjadi 15/12/2024
                        // Membuat query string untuk pagination
                        // Maksimal jumlah link halaman yang ditampilkan
                        // Jika tidak cukup halaman di akhir, geser ke kiri
                        else: ?>
                        <div class="printable-area">
    <div class="text-center mb-4 print-header">
      <h2>Daftar Pembayaran Siswa</h2>
      <h4>Tahun Pelajaran <?= $tahun_cetak ?></h4>
    </div>
                        <div class="table-responsive" style="overflow-x: auto; white-space: nowrap;">
                            <table class="table table-bordered table-hover" id="siswaTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No Formulir</th>
                                        <th style="width: 250px; text-align: center;">Nama Siswa</th>
                                        <th>Jenis Pembayaran</th>
                                        <th>Nominal Pembayaran</th> <!-- Kolom Baru -->
                                        <th style="width: 150px; text-align: center;">Cashback</th>
                                        <th>Tanggal Pembayaran</th>
                                        <th>Keterangan</th>
                                        <th>Metode Pembayaran</th>
                                        <th>Status Pembayaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = $start + 1;
                                    foreach (
                                        $siswa_data
                                        as $siswa_id => $siswa
                                    ):

                                        $jenis_pembayaran_list = [];
                                        foreach (
                                            $siswa['pembayaran']
                                            as $pembayaran_id => $pembayaran
                                        ) {
                                            $tanggal_pembayaran =
                                                $pembayaran[
                                                    'tanggal_pembayaran'
                                                ];
                                            $keterangan =
                                                $pembayaran['keterangan'];

                                            foreach (
                                                $pembayaran['details']
                                                as $detail
                                            ) {
                                                $jenis_pembayaran =
                                                    $detail['jenis_pembayaran'];
                                                $bulan = $detail['bulan'];
                                                $metode_pembayaran =
                                                    $pembayaran[
                                                        'metode_pembayaran'
                                                    ];
                                                $status_pembayaran =
                                                    $detail[
                                                        'status_pembayaran'
                                                    ];
                                                $angsuran_ke =
                                                    $detail['angsuran_ke'];

                                                if (
                                                    strtolower(
                                                        $jenis_pembayaran
                                                    ) === 'spp'
                                                ) {
                                                    $status =
                                                        $status_pembayaran ===
                                                        'Lunas'
                                                            ? 'Lunas'
                                                            : 'Angsuran ke-' .
                                                                $angsuran_ke;
                                                    $jenis_pembayaran_list[] = [
                                                        'jenis_pembayaran' => $jenis_pembayaran,
                                                        'jumlah' =>
                                                            $detail['jumlah'],
                                                        'bulan' => $bulan,
                                                        'metode_pembayaran' => $metode_pembayaran,
                                                        'status_pembayaran' => $status,
                                                        'tanggal_pembayaran' => $tanggal_pembayaran,
                                                        'keterangan' => $keterangan,
                                                        'cashback' =>
                                                            $detail['cashback'],
                                                    ];
                                                } else {
                                                    $jenis_pembayaran_list[] = [
                                                        'jenis_pembayaran' => $jenis_pembayaran,
                                                        'jumlah' =>
                                                            $detail['jumlah'],
                                                        'bulan' => '-',
                                                        'metode_pembayaran' => $metode_pembayaran,
                                                        'status_pembayaran' => ucfirst(
                                                            $status_pembayaran
                                                        ),
                                                        'tanggal_pembayaran' => $tanggal_pembayaran,
                                                        'keterangan' => $keterangan,
                                                        'cashback' =>
                                                            $detail['cashback'],
                                                    ];
                                                }
                                            }
                                        }

                                        if (empty($jenis_pembayaran_list)) {
                                            $jenis_pembayaran_list[] = [
                                                'jenis_pembayaran' => '-',
                                                'jumlah' => '-',
                                                'bulan' => '-',
                                                'metode_pembayaran' => '-',
                                                'status_pembayaran' => '-',
                                                'tanggal_pembayaran' => '-',
                                                'keterangan' => '-',
                                            ];
                                        }
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= htmlspecialchars(
                                                $siswa['no_formulir']
                                            ) ?></td>
                                            <td><?= htmlspecialchars(
                                                $siswa['nama']
                                            ) ?></td>
                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        $jp['jenis_pembayaran']
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?php if (
                                                        $jp['jumlah'] !== '-' &&
                                                        !empty($jp['jumlah'])
                                                    ) {
                                                        $jumlah_formatted =
                                                            'Rp. ' .
                                                            number_format(
                                                                $jp['jumlah'],
                                                                0,
                                                                ',',
                                                                '.'
                                                            );
                                                    } else {
                                                        $jumlah_formatted = '-';
                                                    } ?>
                                                    <?= htmlspecialchars(
                                                        $jumlah_formatted
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
<td>
  <?php foreach ($jenis_pembayaran_list as $jp): ?>
    <?= strtolower($jp['jenis_pembayaran']) === 'uang pangkal'
        ? 'Rp. ' . number_format($jp['cashback'], 0, ',', '.')
        : '-' ?>
    <br>
  <?php endforeach; ?>
</td>


                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?php if (
                                                        $jp[
                                                            'tanggal_pembayaran'
                                                        ] !== '-' &&
                                                        !empty(
                                                            $jp[
                                                                'tanggal_pembayaran'
                                                            ]
                                                        )
                                                    ) {
                                                        $tanggal = date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $jp[
                                                                    'tanggal_pembayaran'
                                                                ]
                                                            )
                                                        );
                                                    } else {
                                                        $tanggal = '-';
                                                    } ?>
                                                    <?= htmlspecialchars(
                                                        $tanggal
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        $jp['bulan']
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        $jp['metode_pembayaran']
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php foreach (
                                                    $jenis_pembayaran_list
                                                    as $jp
                                                ): ?>
                                                    <?= htmlspecialchars(
                                                        $jp['status_pembayaran']
                                                    ) ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php
                                    endforeach;
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php
                                    $query_params = [];
                                    if ($search_no_formulir !== '') {
                                        $query_params[
                                            'search_no_formulir'
                                        ] = $search_no_formulir;
                                    }
                                    if ($search_nama !== '') {
                                        $query_params[
                                            'search_nama'
                                        ] = $search_nama;
                                    }

                                    function buildPageUrl($page, $params)
                                    {
                                        $params['page'] = $page;
                                        return '?' . http_build_query($params);
                                    }

                                    $max_links = 5;
                                    $start_page = max(
                                        1,
                                        $page - floor($max_links / 2)
                                    );
                                    $end_page = min(
                                        $total_pages,
                                        $start_page + $max_links - 1
                                    );

                                    if (
                                        $end_page - $start_page <
                                        $max_links - 1
                                    ) {
                                        $start_page = max(
                                            1,
                                            $end_page - $max_links + 1
                                        );
                                    }
                                    ?>

                                    <!-- Tombol "First" -->
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                1,
                                                $query_params
                                            ) ?>" aria-label="First">
                                                <span aria-hidden="true">First</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">First</span>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Tombol "Previous" -->
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $page - 1,
                                                $query_params
                                            ) ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&laquo;</span>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Ellipsis di Awal -->
                                    <?php if ($start_page > 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>

                                    <!-- Link Halaman -->
                                    <?php for (
                                        $i = $start_page;
                                        $i <= $end_page;
                                        $i++
                                    ): ?>
                                        <li class="page-item <?= $i == $page
                                            ? 'active'
                                            : '' ?>">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $i,
                                                $query_params
                                            ) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Ellipsis di Akhir -->
                                    <?php if ($end_page < $total_pages): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>

                                    <!-- Tombol "Next" -->
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $page + 1,
                                                $query_params
                                            ) ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">&raquo;</span>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Tombol "Last" -->
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?= buildPageUrl(
                                                $total_pages,
                                                $query_params
                                            ) ?>" aria-label="Last">
                                                <span aria-hidden="true">Last</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Last</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-white text-center py-3">
        &copy; <?= date('Y') ?> Sistem Keuangan PPDB
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/sidebar.js"></script>
    <script>
        function printTable() {
            window.print();
        }
    </script>
</body>
</html>
