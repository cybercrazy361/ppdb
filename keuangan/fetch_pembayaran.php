<?php
// fetch_pembayaran.php

header('Content-Type: application/json');

// Start session
session_start();

// Check if user is logged in and has the 'keuangan' role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Include database connection
include '../database_connection.php';

// Ambil parameter pencarian dan pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_unit = isset($_GET['unit']) ? trim($_GET['unit']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5;
$start = ($page - 1) * $limit;

// Unit petugas
$unit_petugas = $_SESSION['unit'];

// Membangun query dasar
$query_base = "FROM pembayaran
               INNER JOIN siswa ON pembayaran.no_formulir = siswa.no_formulir
               WHERE siswa.unit = ?";

// Array untuk parameter binding
$params = [];
$param_types = "s"; // 's' untuk string (unit_petugas)
$params[] = $unit_petugas;

// Menambahkan kondisi pencarian jika ada
if (!empty($search)) {
    $query_base .= " AND (siswa.no_formulir LIKE ? OR siswa.nama LIKE ?)";
    $search_param = '%' . $search . '%';
    $param_types .= "ss"; // Dua string tambahan
    $params[] = $search_param;
    $params[] = $search_param;
}

// Menambahkan filter unit jika dipilih (selain unit petugas)
if (!empty($filter_unit)) {
    $query_base .= " AND siswa.unit = ?";
    $param_types .= "s";
    $params[] = $filter_unit;
}

// Query total data dengan kondisi pencarian
$total_query = "SELECT COUNT(DISTINCT pembayaran.id) AS total " . $query_base;
$stmt_total = $conn->prepare($total_query);
$stmt_total->bind_param($param_types, ...$params);
$stmt_total->execute();
$total_result = $stmt_total->get_result();
$total = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);
$stmt_total->close();

// Query data pembayaran dengan pembayaran_detail dan kondisi pencarian
$select_fields = "SELECT 
                    pembayaran.id AS pembayaran_id,
                    siswa.no_formulir,
                    siswa.nama,
                    siswa.unit,
                    pembayaran.jumlah,
                    pembayaran.metode_pembayaran,
                    pembayaran.tanggal_pembayaran,
                    pembayaran.keterangan,
                    pembayaran_detail.jenis_pembayaran_id,
                    jenis_pembayaran.nama AS jenis_pembayaran_nama,
                    pembayaran_detail.jumlah AS detail_jumlah ";

$query_data = $select_fields . $query_base . "
              LEFT JOIN pembayaran_detail ON pembayaran.id = pembayaran_detail.pembayaran_id
              LEFT JOIN jenis_pembayaran ON pembayaran_detail.jenis_pembayaran_id = jenis_pembayaran.id
              ORDER BY pembayaran.tanggal_pembayaran DESC, pembayaran.id DESC
              LIMIT ?, ?";

$params_with_limit = $params;
$param_types_with_limit = $param_types . "ii"; // Dua integer tambahan
$start_int = $start;
$limit_int = $limit;
$params_with_limit[] = $start_int;
$params_with_limit[] = $limit_int;

// Prepare statement
$stmt = $conn->prepare($query_data);
$stmt->bind_param($param_types_with_limit, ...$params_with_limit);
$stmt->execute();
$result = $stmt->get_result();

// Mengelompokkan data pembayaran dan detailnya
$pembayaran_data = [];
while ($row = $result->fetch_assoc()) {
    $pembayaran_id = $row['pembayaran_id'];
    if (!isset($pembayaran_data[$pembayaran_id])) {
        $pembayaran_data[$pembayaran_id] = [
            'no_formulir' => $row['no_formulir'],
            'nama' => $row['nama'],
            'unit' => $row['unit'],
            'jumlah' => $row['jumlah'],
            'metode_pembayaran' => $row['metode_pembayaran'],
            'tanggal_pembayaran' => $row['tanggal_pembayaran'],
            'keterangan' => $row['keterangan'],
            'details' => []
        ];
    }
    if ($row['jenis_pembayaran_id']) {
        $pembayaran_data[$pembayaran_id]['details'][] = [
            'jenis_pembayaran_id' => $row['jenis_pembayaran_id'],
            'jenis_pembayaran_nama' => $row['jenis_pembayaran_nama'],
            'jumlah' => $row['detail_jumlah']
        ];
    }
}
$stmt->close();

// Membangun HTML tabel pembayaran
ob_start(); // Mulai buffer output

if (count($pembayaran_data) > 0) {
    $no = $start + 1;
    foreach ($pembayaran_data as $pembayaran_id => $pembayaran) : ?>
        <tr>
            <td><?= $no++; ?></td>
            <td><?= htmlspecialchars($pembayaran['no_formulir']); ?></td>
            <td><?= htmlspecialchars($pembayaran['nama']); ?></td>
            <td><?= htmlspecialchars($pembayaran['unit']); ?></td>
            <td><?= number_format($pembayaran['jumlah'], 0, ',', '.'); ?></td>
            <td><?= htmlspecialchars($pembayaran['metode_pembayaran']); ?></td>
            <td><?= htmlspecialchars($pembayaran['tanggal_pembayaran']); ?></td>
            <td><?= htmlspecialchars($pembayaran['keterangan']); ?></td>
            <td>
                <button class="btn btn-warning btn-sm edit-btn" data-id="<?= htmlspecialchars($pembayaran_id); ?>">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-danger btn-sm delete-btn" data-id="<?= htmlspecialchars($pembayaran_id); ?>">
                    <i class="fas fa-trash"></i>
                </button>
                <a href="cetak_pembayaran.php?id=<?= htmlspecialchars($pembayaran_id); ?>" class="btn btn-primary btn-sm" target="_blank">
                    <i class="fas fa-print"></i>
                </a>
            </td>
        </tr>
        <tr>
            <td colspan="9">
                <?php if (!empty($pembayaran['details'])) : ?>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Jenis Pembayaran</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pembayaran['details'] as $detail) : ?>
                                <tr>
                                    <td><?= htmlspecialchars($detail['jenis_pembayaran_nama']); ?></td>
                                    <td><?= number_format($detail['jumlah'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>Tidak ada rincian pembayaran.</p>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
} else {
    echo '<tr><td colspan="9" class="text-center">Tidak ada data pembayaran ditemukan.</td></tr>';
}

$table_html = ob_get_clean(); // Dapatkan isi buffer dan bersihkan buffer

// Membangun HTML pagination
$pagination_html = '';
if ($total_pages > 1) {
    $pagination_html .= '<nav><ul class="pagination justify-content-center">';

    // Previous button
    if ($page > 1) {
        $pagination_html .= '<li class="page-item">
                                <a class="page-link" href="#" data-page="' . ($page - 1) . '" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                             </li>';
    }

    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $page) ? 'active' : '';
        $pagination_html .= '<li class="page-item ' . $active . '">
                                <a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a>
                             </li>';
    }

    // Next button
    if ($page < $total_pages) {
        $pagination_html .= '<li class="page-item">
                                <a class="page-link" href="#" data-page="' . ($page + 1) . '" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                             </li>';
    }

    $pagination_html .= '</ul></nav>';
}

// Kembalikan data dalam format JSON
echo json_encode([
    'success' => true,
    'table_html' => $table_html,
    'pagination_html' => $pagination_html
]);

?>
