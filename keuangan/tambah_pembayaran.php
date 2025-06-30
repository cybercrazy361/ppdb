<?php
// tambah_pembayaran.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method',
    ]);
    exit();
}

if (
    !isset($_POST['csrf_token']) ||
    $_POST['csrf_token'] !== $_SESSION['csrf_token']
) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

include '../database_connection.php';

function sanitize($data)
{
    return htmlspecialchars(trim($data));
}

$unit_petugas = $_SESSION['unit'];
$bulan_order = [
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
];

// Tangkap data dari form
$no_formulir = isset($_POST['no_formulir'])
    ? sanitize($_POST['no_formulir'])
    : '';
$nama = isset($_POST['nama']) ? sanitize($_POST['nama']) : '';
$tahun_pelajaran = isset($_POST['tahun_pelajaran'])
    ? sanitize($_POST['tahun_pelajaran'])
    : '';
$metode_pembayaran = isset($_POST['metode_pembayaran'])
    ? sanitize($_POST['metode_pembayaran'])
    : '';
$keterangan = isset($_POST['keterangan']) ? sanitize($_POST['keterangan']) : '';
$jenis_pembayaran =
    isset($_POST['jenis_pembayaran']) && is_array($_POST['jenis_pembayaran'])
        ? $_POST['jenis_pembayaran']
        : [];
$jumlah_pembayaran =
    isset($_POST['jumlah']) && is_array($_POST['jumlah'])
        ? $_POST['jumlah']
        : [];
$bulan_pembayaran =
    isset($_POST['bulan']) && is_array($_POST['bulan']) ? $_POST['bulan'] : [];
$cashback_pembayaran =
    isset($_POST['cashback']) && is_array($_POST['cashback'])
        ? $_POST['cashback']
        : [];

$errors = [];
$total_effective = 0; // jumlah + cashback (untuk validasi lunas)
$total_dibayar = 0; // hanya jumlah pembayaran (uang yang masuk sekolah)

// Validasi dasar
if ($no_formulir === '') {
    $errors[] = 'No Formulir harus diisi.';
}
if ($nama === '') {
    $errors[] = 'Nama siswa tidak valid.';
}
if ($tahun_pelajaran === '') {
    $errors[] = 'Tahun Pelajaran harus diisi.';
}
if ($metode_pembayaran === '') {
    $errors[] = 'Metode Pembayaran harus dipilih.';
}
if (count($jenis_pembayaran) === 0) {
    $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';
}

// ========== Ambil bulan SPP yang sudah lunas ==========
$paid_months = [];
if ($no_formulir && $tahun_pelajaran) {
    $sql_paid = "
        SELECT pd.bulan
        FROM pembayaran_detail pd
        JOIN pembayaran p ON p.id=pd.pembayaran_id
        JOIN jenis_pembayaran jp ON jp.id=pd.jenis_pembayaran_id
        WHERE p.no_formulir=? AND p.tahun_pelajaran=? AND jp.nama='SPP' AND pd.status_pembayaran='Lunas'
    ";
    $stmt_paid = $conn->prepare($sql_paid);
    $stmt_paid->bind_param('ss', $no_formulir, $tahun_pelajaran);
    $stmt_paid->execute();
    $res_paid = $stmt_paid->get_result();
    while ($row_paid = $res_paid->fetch_assoc()) {
        if ($row_paid['bulan']) {
            $paid_months[] = $row_paid['bulan'];
        }
    }
    $stmt_paid->close();
}

// Validasi tiap item dan akumulasi total
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id = $jenis_pembayaran[$i];
    $j_str = $jumlah_pembayaran[$i] ?? '0';
    $jumlah = floatval(str_replace('.', '', $j_str));
    $cb_val =
        isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
            ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
            : 0;
    $bulan_val = $bulan_pembayaran[$i] ?? '';

    $effective = $jumlah + $cb_val;
    $total_dibayar += $jumlah; // <--- hanya jumlah uang masuk
    $total_effective += $effective; // untuk validasi status lunas

    if ($jenis_id === '') {
        $errors[] = 'Item ' . ($i + 1) . ': Jenis Pembayaran harus dipilih.';
        continue;
    }
    if ($effective <= 0) {
        $errors[] =
            'Item ' . ($i + 1) . ': (jumlah + cashback) harus lebih dari 0.';
    }

    // Ambil nama jenis & cek unit
    $stmt = $conn->prepare(
        'SELECT nama FROM jenis_pembayaran WHERE id=? AND unit=?'
    );
    $stmt->bind_param('is', $jenis_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $errors[] =
            'Item ' .
            ($i + 1) .
            ': Jenis Pembayaran tidak ditemukan atau bukan milik unit Anda.';
        $stmt->close();
        continue;
    }
    $jenis_nama = strtolower($res->fetch_assoc()['nama']);
    $stmt->close();

    // Validasi bulan & cashback
    if ($jenis_nama === 'spp') {
        if (
            $bulan_val === '' ||
            array_search($bulan_val, $bulan_order) === false
        ) {
            $errors[] =
                'Item ' .
                ($i + 1) .
                ': Bulan harus dipilih dan valid untuk SPP.';
        }
        // ================= BLOKIR LONCAT BULAN SPP ==============
        // Urutkan paid_months sesuai $bulan_order
        $bulan_progress = [];
        foreach ($bulan_order as $b) {
            if (in_array($b, $paid_months)) {
                $bulan_progress[] = $b;
            }
        }
        // Cari bulan pertama yang belum lunas
        $first_unpaid = '';
        foreach ($bulan_order as $b) {
            if (!in_array($b, $paid_months)) {
                $first_unpaid = $b;
                break;
            }
        }
        // Validasi: user harus pilih bulan pertama yang belum lunas, tidak boleh loncat
        if ($bulan_val !== $first_unpaid) {
            $errors[] =
                'Item ' .
                ($i + 1) .
                ": Pembayaran SPP harus urut, bulan berikutnya hanya boleh dibayar jika bulan sebelumnya lunas. Silakan bayar bulan <b>$first_unpaid</b> terlebih dahulu.";
        }
    } else {
        if ($bulan_val !== '') {
            $errors[] =
                'Item ' . ($i + 1) . ': Bulan hanya boleh diisi untuk SPP.';
        }
        if ($jenis_nama !== 'uang pangkal' && $cb_val > 0) {
            $errors[] =
                'Item ' .
                ($i + 1) .
                ': Cashback hanya boleh untuk Uang Pangkal.';
        }
        if ($jenis_nama === 'uang pangkal' && $cb_val < 0) {
            $errors[] = 'Item ' . ($i + 1) . ': Cashback minimal 0.';
        }
    }

    // Ambil nominal_max berdasar bulan & unit
    if ($jenis_nama === 'spp') {
        $stmt = $conn->prepare("
          SELECT nominal_max
          FROM pengaturan_nominal
          WHERE jenis_pembayaran_id = ?
            AND bulan              = ?
            AND unit               = ?
        ");
        $stmt->bind_param('iss', $jenis_id, $bulan_val, $unit_petugas);
    } else {
        $stmt = $conn->prepare("
          SELECT nominal_max
          FROM pengaturan_nominal
          WHERE jenis_pembayaran_id = ?
            AND bulan              IS NULL
            AND unit               = ?
        ");
        $stmt->bind_param('is', $jenis_id, $unit_petugas);
    }
    $stmt->execute();
    $res2 = $stmt->get_result();
    if ($res2->num_rows === 0) {
        $errors[] =
            'Item ' . ($i + 1) . ': Pengaturan nominal tidak ditemukan.';
        $stmt->close();
        continue;
    }
    $nominal_max = floatval($res2->fetch_assoc()['nominal_max']);
    $stmt->close();

    // Hitung prev (total sebelumnya)
    if ($jenis_nama === 'spp') {
        $sql = "
          SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
          FROM pembayaran_detail pd
          JOIN pembayaran p ON p.id=pd.pembayaran_id
          WHERE p.no_formulir=? 
            AND pd.jenis_pembayaran_id=? 
            AND pd.bulan=? 
            AND p.tahun_pelajaran=?
        ";
        $stm = $conn->prepare($sql);
        $stm->bind_param(
            'siis',
            $no_formulir,
            $jenis_id,
            $bulan_val,
            $tahun_pelajaran
        );
    } else {
        $sql = "
          SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
          FROM pembayaran_detail pd
          JOIN pembayaran p ON p.id=pd.pembayaran_id
          WHERE p.no_formulir=? 
            AND pd.jenis_pembayaran_id=? 
            AND pd.bulan IS NULL 
            AND p.tahun_pelajaran=?
        ";
        $stm = $conn->prepare($sql);
        $stm->bind_param('sis', $no_formulir, $jenis_id, $tahun_pelajaran);
    }
    $stm->execute();
    $prev = floatval($stm->get_result()->fetch_assoc()['prev']);
    $stm->close();

    $sisa = $nominal_max - $prev;

    // Blokir insert jika SPP sudah lunas atau melebihi sisa
    if ($jenis_nama === 'spp') {
        if ($prev >= $nominal_max) {
            $errors[] =
                'Item ' .
                ($i + 1) .
                ": SPP bulan $bulan_val sudah lunas, tidak bisa bayar lagi.";
            continue;
        }
        if ($effective > $sisa) {
            $errors[] =
                'Item ' .
                ($i + 1) .
                ': Pembayaran (' .
                number_format($effective, 0, ',', '.') .
                ') melebihi sisa ' .
                number_format($sisa, 0, ',', '.') .
                '.';
            continue;
        }
    }
}

if ($total_effective <= 0) {
    $errors[] = 'Total (jumlah+cashback) harus lebih dari 0.';
}

if ($errors) {
    echo json_encode([
        'success' => false,
        'message' => implode('<br>', $errors),
    ]);
    exit();
}

// Begin transaction
$conn->begin_transaction();

try {
    // Ambil siswa_id
    $stmt = $conn->prepare(
        'SELECT id FROM siswa WHERE no_formulir=? AND unit=?'
    );
    $stmt->bind_param('ss', $no_formulir, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Siswa tidak ditemukan atau unit tidak sesuai.');
    }
    $siswa_id = $res->fetch_assoc()['id'];
    $stmt->close();

    // Insert ke tabel pembayaran
    $tgl = date('Y-m-d');
    $stmt = $conn->prepare("
        INSERT INTO pembayaran
        (siswa_id,no_formulir,jumlah,metode_pembayaran,tahun_pelajaran,tanggal_pembayaran,keterangan)
        VALUES(?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        'isdssss',
        $siswa_id,
        $no_formulir,
        $total_dibayar, // <-- hanya jumlah uang yang diterima
        $metode_pembayaran,
        $tahun_pelajaran,
        $tgl,
        $keterangan
    );
    if (!$stmt->execute()) {
        throw new Exception('Gagal menambahkan pembayaran: ' . $stmt->error);
    }
    $pembayaran_id = $stmt->insert_id;
    $stmt->close();

    // Siapkan insert detail
    $stmt = $conn->prepare("
        INSERT INTO pembayaran_detail
        (pembayaran_id,jenis_pembayaran_id,jumlah,bulan,status_pembayaran,angsuran_ke,cashback)
        VALUES(?,?,?,?,?,?,?)
    ");

    // Loop insert detail
    for ($i = 0; $i < count($jenis_pembayaran); $i++) {
        $jenis_id = $jenis_pembayaran[$i];
        $j_str = $jumlah_pembayaran[$i] ?? '0';
        $jumlah = floatval(str_replace('.', '', $j_str));
        $cb_val =
            isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
                ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
                : 0;
        $bulan_val =
            isset($bulan_pembayaran[$i]) && $bulan_pembayaran[$i] !== ''
                ? $bulan_pembayaran[$i]
                : null;
        $effective = $jumlah + $cb_val;

        // Ambil nama jenis
        $stm2 = $conn->prepare('SELECT nama FROM jenis_pembayaran WHERE id=?');
        $stm2->bind_param('i', $jenis_id);
        $stm2->execute();
        $jn = strtolower($stm2->get_result()->fetch_assoc()['nama']);
        $stm2->close();

        // Hitung prev & count untuk menentukan status/angsuran
        if ($jn === 'spp' && $bulan_val) {
            $sql2 = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev,
                       COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=? AND pd.bulan=? AND p.tahun_pelajaran=?
            ";
            $stm3 = $conn->prepare($sql2);
            $stm3->bind_param(
                'iiss',
                $siswa_id,
                $jenis_id,
                $bulan_val,
                $tahun_pelajaran
            );
        } else {
            $sql2 = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev,
                       COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=? AND pd.bulan IS NULL AND p.tahun_pelajaran=?
            ";
            $stm3 = $conn->prepare($sql2);
            $stm3->bind_param('iis', $siswa_id, $jenis_id, $tahun_pelajaran);
        }
        $stm3->execute();
        $row = $stm3->get_result()->fetch_assoc();
        $prev = floatval($row['prev']);
        $cnt = intval($row['cnt']);
        $stm3->close();

        // Ambil nominal_max lagi
        if ($jn === 'spp') {
            $stm4 = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan               = ?
                  AND unit                = ?
            ");
            $stm4->bind_param('iss', $jenis_id, $bulan_val, $unit_petugas);
        } else {
            $stm4 = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan               IS NULL
                  AND unit                = ?
            ");
            $stm4->bind_param('is', $jenis_id, $unit_petugas);
        }
        $stm4->execute();
        $nom = floatval($stm4->get_result()->fetch_assoc()['nominal_max']);
        $stm4->close();

        // Tentukan status & angsuran
        if ($jn === 'uang pangkal') {
            if ($prev + $effective >= $nom) {
                $status = 'Lunas';
                $angs = null;
            } else {
                $angs = $cnt + 1;
                $status = 'Angsuran ke-' . $angs;
            }
        } elseif ($jn === 'spp') {
            if ($prev + $effective >= $nom) {
                $status = 'Lunas';
            } else {
                $status = 'Angsuran ke-' . ($cnt + 1);
            }
            $angs = null;
        } else {
            $status = $effective >= $nom ? 'Lunas' : 'Belum Lunas';
            $angs = null;
        }

        $stmt->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $jumlah,
            $bulan_val,
            $status,
            $angs,
            $cb_val
        );
        if (!$stmt->execute()) {
            throw new Exception('Gagal menambahkan detail: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Pembayaran berhasil ditambahkan.',
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
