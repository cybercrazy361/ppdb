<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: ../login_keuangan.php');
    exit();
}
include '../database_connection.php';

$petugas_unit = $_SESSION['unit'];
$search_no_formulir = isset($_GET['search_no_formulir'])
    ? trim($_GET['search_no_formulir'])
    : '';
$search_nama = isset($_GET['search_nama']) ? trim($_GET['search_nama']) : '';
$tahun_pelajaran = isset($_GET['tahun_pelajaran'])
    ? trim($_GET['tahun_pelajaran'])
    : '';

// Query tanpa LIMIT/OFFSET
$query_ids = "SELECT DISTINCT s.id 
             FROM siswa s 
             INNER JOIN pembayaran p ON s.id = p.siswa_id 
             INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id 
             INNER JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id 
             WHERE s.unit = ?";
$param_types_ids = 's';
$param_values_ids = [$petugas_unit];

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
if ($tahun_pelajaran !== '') {
    $query_ids .= ' AND p.tahun_pelajaran = ?';
    $param_types_ids .= 's';
    $param_values_ids[] = $tahun_pelajaran;
}

$stmt_ids = $conn->prepare($query_ids);
$stmt_ids->bind_param($param_types_ids, ...$param_values_ids);
$stmt_ids->execute();
$result_ids = $stmt_ids->get_result();
$student_ids = [];
while ($row = $result_ids->fetch_assoc()) {
    $student_ids[] = $row['id'];
}
$stmt_ids->close();

$siswa_data = [];
if (!empty($student_ids)) {
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
        pd.cashback AS detail_cashback
    FROM siswa s
    INNER JOIN pembayaran p ON s.id = p.siswa_id
    INNER JOIN pembayaran_detail pd ON p.id = pd.pembayaran_id
    INNER JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
    WHERE s.id IN ($placeholders)
    ORDER BY s.nama ASC, p.tanggal_pembayaran DESC";

    $stmt = $conn->prepare($select_query);
    $types_select = str_repeat('i', count($student_ids));
    $stmt->bind_param($types_select, ...$student_ids);
    $stmt->execute();
    $result = $stmt->get_result();

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
            if (!isset($siswa_data[$siswa_id]['pembayaran'][$pembayaran_id])) {
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
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Tagihan Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4 landscape;
            margin: 14mm 14mm 14mm 14mm;
        }
        body {
            background: #fff !important;
            font-family: 'Nunito', Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #222;
            width: 297mm;
            height: 210mm;
        }
        .print-title {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }
        .print-subtitle {
            text-align: center;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .table-bordered {
            border: 1.5px solid #1d5cab;
        }
        .table thead th {
            background: #2574e2 !important;
            color: #fff !important;
            vertical-align: middle;
            text-align: center;
            font-weight: bold;
            border-color: #1d5cab !important;
            font-size: 1rem;
        }
        .table tbody td {
            vertical-align: top;
            border-color: #1d5cab !important;
            padding: 4px 7px;
        }
        .no-print {
            display: none !important;
        }
        @media print {
            html, body {
                width: 297mm;
                height: 210mm;
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .no-print { display: none !important; }
            .printable-area, .printable-area * { visibility: visible !important; }
            .container { max-width: 100% !important; }
        }
    </style>
</head>
<body>
<div class="container printable-area">
    <div class="print-title">Rekap Tagihan Siswa</div>
    <div class="print-subtitle">
        Unit: <strong><?= htmlspecialchars($petugas_unit) ?></strong>
        <?php if (
            $tahun_pelajaran
        ): ?> | Tahun Pelajaran: <strong><?= htmlspecialchars(
     $tahun_pelajaran
 ) ?></strong><?php endif; ?>
    </div>
    <table class="table table-bordered table-hover">
        <thead>
        <tr>
            <th style="width:35px">No</th>
            <th style="width:110px">No Formulir</th>
            <th style="width:170px">Nama Siswa</th>
            <th style="width:90px">Jenis Pembayaran</th>
            <th style="width:100px">Nominal</th>
            <th style="width:80px">Cashback</th>
            <th style="width:90px">Tanggal</th>
            <th style="width:90px">Keterangan</th>
            <th style="width:80px">Metode</th>
            <th style="width:70px">Status</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        foreach ($siswa_data as $siswa) {
            $jenis_pembayaran_list = [];
            foreach ($siswa['pembayaran'] as $pembayaran) {
                $tanggal_pembayaran = $pembayaran['tanggal_pembayaran'];
                $keterangan = $pembayaran['keterangan'];
                foreach ($pembayaran['details'] as $detail) {
                    $jenis_pembayaran = $detail['jenis_pembayaran'];
                    $metode_pembayaran = $pembayaran['metode_pembayaran'];
                    $status_pembayaran = $detail['status_pembayaran'];
                    $angsuran_ke = $detail['angsuran_ke'];
                    $status =
                        strtolower($jenis_pembayaran) === 'spp'
                            ? ($status_pembayaran === 'Lunas'
                                ? 'Lunas'
                                : 'Angsuran ke-' . $angsuran_ke)
                            : ucfirst($status_pembayaran);

                    $jenis_pembayaran_list[] = [
                        'jenis_pembayaran' => $jenis_pembayaran,
                        'jumlah' => $detail['jumlah'],
                        'cashback' => $detail['cashback'],
                        'tanggal_pembayaran' => $tanggal_pembayaran,
                        'keterangan' => $keterangan,
                        'metode_pembayaran' => $metode_pembayaran,
                        'status_pembayaran' => $status,
                    ];
                }
            }
            foreach ($jenis_pembayaran_list as $jp) {
                echo '<tr>';
                echo '<td class="text-center">' . $no++ . '</td>';
                echo '<td>' . htmlspecialchars($siswa['no_formulir']) . '</td>';
                echo '<td>' . htmlspecialchars($siswa['nama']) . '</td>';
                echo '<td class="text-center">' .
                    htmlspecialchars($jp['jenis_pembayaran']) .
                    '</td>';
                echo '<td class="text-end">' .
                    ($jp['jumlah'] !== '-' && $jp['jumlah'] !== null
                        ? 'Rp. ' . number_format($jp['jumlah'], 0, ',', '.')
                        : '-') .
                    '</td>';
                echo '<td class="text-end">' .
                    (strtolower($jp['jenis_pembayaran']) === 'uang pangkal'
                        ? 'Rp. ' . number_format($jp['cashback'], 0, ',', '.')
                        : '-') .
                    '</td>';
                echo '<td class="text-center">' .
                    ($jp['tanggal_pembayaran'] !== '-' &&
                    !empty($jp['tanggal_pembayaran'])
                        ? date('d/m/Y', strtotime($jp['tanggal_pembayaran']))
                        : '-') .
                    '</td>';
                echo '<td>' . htmlspecialchars($jp['keterangan']) . '</td>';
                echo '<td class="text-center">' .
                    htmlspecialchars($jp['metode_pembayaran']) .
                    '</td>';
                echo '<td class="text-center">' .
                    htmlspecialchars($jp['status_pembayaran']) .
                    '</td>';
                echo '</tr>';
            }
        }
        ?>
        </tbody>
    </table>
</div>
<script>
    window.print();
</script>
</body>
</html>
