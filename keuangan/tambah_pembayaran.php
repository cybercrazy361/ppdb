<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

include '../database_connection.php';

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

$unit_petugas = $_SESSION['unit']; // Mendapatkan unit petugas dari sesi

// Urutan bulan (sesuaikan dengan kebutuhan)
$bulan_order = ["Juli", "Agustus", "September", "Oktober", "November", "Desember", 
                "Januari", "Februari", "Maret", "April", "Mei", "Juni"];

$no_formulir         = isset($_POST['no_formulir'])        ? sanitize($_POST['no_formulir'])        : '';
$nama                = isset($_POST['nama'])               ? sanitize($_POST['nama'])               : '';
$tahun_pelajaran     = isset($_POST['tahun_pelajaran'])    ? sanitize($_POST['tahun_pelajaran'])    : '';
$metode_pembayaran   = isset($_POST['metode_pembayaran'])  ? sanitize($_POST['metode_pembayaran'])  : '';
$keterangan          = isset($_POST['keterangan'])         ? sanitize($_POST['keterangan'])         : '';

$jenis_pembayaran    = isset($_POST['jenis_pembayaran'])    && is_array($_POST['jenis_pembayaran'])    ? $_POST['jenis_pembayaran']    : [];
$jumlah_pembayaran   = isset($_POST['jumlah'])              && is_array($_POST['jumlah'])              ? $_POST['jumlah']              : [];
$bulan_pembayaran    = isset($_POST['bulan'])               && is_array($_POST['bulan'])               ? $_POST['bulan']               : [];
$cashback_pembayaran = isset($_POST['cashback'])            && is_array($_POST['cashback'])            ? $_POST['cashback']            : [];

$errors = [];

if ($no_formulir === '')        $errors[] = 'No Formulir harus diisi.';
if ($nama === '')               $errors[] = 'Nama siswa tidak valid.';
if ($tahun_pelajaran === '')    $errors[] = 'Tahun Pelajaran harus diisi.';
if ($metode_pembayaran === '')  $errors[] = 'Metode Pembayaran harus dipilih.';
if (count($jenis_pembayaran) === 0) $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

$total_jumlah = 0;

// Validasi setiap item dan hitung total_jumlah
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id = $jenis_pembayaran[$i];
    $jumlah_str = $jumlah_pembayaran[$i] ?? '0';
    $jumlah = floatval(str_replace('.', '', $jumlah_str));
    $bulan_val = $bulan_pembayaran[$i] ?? '';
    $cashback = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
                ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
                : 0;

    // effective = jumlah + cashback (cashback sebagai diskon)
    $effective = $jumlah + $cashback;

    if ($jenis_id === '') {
        $errors[] = "Item ".($i + 1).": Jenis Pembayaran harus dipilih.";
    }
    if ($jumlah <= 0) {
        $errors[] = "Item ".($i + 1).": Jumlah Pembayaran harus lebih dari 0.";
    }

    // Cek unit dan nama jenis pembayaran
    $stmt_jp = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    if (!$stmt_jp) {
        $errors[] = "Error preparing statement: " . $conn->error;
        continue;
    }
    $stmt_jp->bind_param('is', $jenis_id, $unit_petugas);
    $stmt_jp->execute();
    $res_jp = $stmt_jp->get_result();
    if ($res_jp->num_rows > 0) {
        $jenis_nama = strtolower($res_jp->fetch_assoc()['nama']);

        // Validasi bulan untuk SPP
        if ($jenis_nama === 'spp') {
            if ($bulan_val === '') {
                $errors[] = "Item ".($i + 1).": Bulan harus dipilih untuk SPP.";
            } elseif (array_search($bulan_val, $bulan_order) === false) {
                $errors[] = "Item ".($i + 1).": Bulan tidak valid.";
            }
        } else {
            if ($bulan_val !== '') {
                $errors[] = "Item ".($i + 1).": Bulan hanya boleh diisi untuk SPP.";
            }
            if ($jenis_nama !== 'uang pangkal' && $cashback > 0) {
                $errors[] = "Item ".($i + 1).": Cashback hanya boleh diisi untuk Uang Pangkal.";
            }
        }

        // Validasi cashback minimal 0 untuk uang pangkal
        if ($jenis_nama === 'uang pangkal' && $cashback < 0) {
            $errors[] = "Item ".($i + 1).": Cashback harus 0 atau lebih.";
        }
    } else {
        $errors[] = "Item ".($i + 1).": Jenis Pembayaran tidak ditemukan atau tidak untuk unit Anda.";
    }
    $stmt_jp->close();

    // Ambil nominal_max
    $stmt_nominal = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
    if (!$stmt_nominal) {
        $errors[] = "Error preparing nominal statement: " . $conn->error;
        continue;
    }
    $stmt_nominal->bind_param('i', $jenis_id);
    $stmt_nominal->execute();
    $res_nominal = $stmt_nominal->get_result();
    if ($res_nominal->num_rows > 0) {
        $nominal_max = floatval($res_nominal->fetch_assoc()['nominal_max']);
    } else {
        $errors[] = "Item ".($i + 1).": Pengaturan nominal tidak ditemukan.";
    }
    $stmt_nominal->close();

    // Hitung total sebelumnya termasuk cashback
    if ($bulan_val !== '') {
        $sql = "
            SELECT 
                COALESCE(SUM(pd.jumlah + COALESCE(pd.cashback,0)),0) AS total_prev,
                COUNT(*) AS angsuran_count
            FROM pembayaran_detail pd
            INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
            WHERE p.no_formulir = ?
              AND pd.jenis_pembayaran_id = ?
              AND pd.bulan = ?
              AND p.tahun_pelajaran = ?
        ";
        $stmt_sum = $conn->prepare($sql);
        $stmt_sum->bind_param('siis', $no_formulir, $jenis_id, $bulan_val, $tahun_pelajaran);
    } else {
        $sql = "
            SELECT 
                COALESCE(SUM(pd.jumlah + COALESCE(pd.cashback,0)),0) AS total_prev,
                COUNT(*) AS angsuran_count
            FROM pembayaran_detail pd
            INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
            WHERE p.no_formulir = ?
              AND pd.jenis_pembayaran_id = ?
              AND pd.bulan IS NULL
              AND p.tahun_pelajaran = ?
        ";
        $stmt_sum = $conn->prepare($sql);
        $stmt_sum->bind_param('sis', $no_formulir, $jenis_id, $tahun_pelajaran);
    }
    $stmt_sum->execute();
    $row_sum = $stmt_sum->get_result()->fetch_assoc();
    $total_prev     = floatval($row_sum['total_prev']);
    $angsuran_count = intval($row_sum['angsuran_count']);
    $stmt_sum->close();

    // Validasi sisa bayar
    $sisa = $nominal_max - $total_prev;
    if ($effective > $sisa) {
        $errors[] = "Item ".($i + 1).": (jumlah + cashback) melebihi sisa ".number_format($sisa,0,',','.').".";
    }

    $total_jumlah += $jumlah;
}

if ($total_jumlah <= 0) {
    $errors[] = 'Total Pembayaran harus lebih dari 0.';
}

if (count($errors) > 0) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

// Proses penyimpanan data
$conn->begin_transaction();

try {
    // Ambil siswa_id
    $stmt_siswa = $conn->prepare("SELECT id FROM siswa WHERE no_formulir = ? AND unit = ?");
    if (!$stmt_siswa) {
        throw new Exception("Error preparing siswa statement: " . $conn->error);
    }
    $stmt_siswa->bind_param('ss', $no_formulir, $unit_petugas);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();
    if ($res_siswa->num_rows === 0) {
        throw new Exception('Siswa dengan No Formulir tersebut tidak ditemukan atau tidak sesuai unit.');
    }
    $siswa_id = $res_siswa->fetch_assoc()['id'];
    $stmt_siswa->close();

    $tanggal_pembayaran = date('Y-m-d');
    $stmt_pembayaran = $conn->prepare("
        INSERT INTO pembayaran 
            (siswa_id, no_formulir, jumlah, metode_pembayaran, tahun_pelajaran, tanggal_pembayaran, keterangan)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_pembayaran) {
        throw new Exception("Error preparing pembayaran statement: " . $conn->error);
    }
    $stmt_pembayaran->bind_param(
        'isdssss',
        $siswa_id,
        $no_formulir,
        $total_jumlah,
        $metode_pembayaran,
        $tahun_pelajaran,
        $tanggal_pembayaran,
        $keterangan
    );
    if (!$stmt_pembayaran->execute()) {
        throw new Exception('Gagal menambahkan pembayaran: ' . $stmt_pembayaran->error);
    }
    $pembayaran_id = $stmt_pembayaran->insert_id;
    $stmt_pembayaran->close();

    // Siapkan insert detail
    $stmt_detail = $conn->prepare("
        INSERT INTO pembayaran_detail 
            (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_detail) {
        throw new Exception("Error preparing pembayaran_detail statement: " . $conn->error);
    }

    // Loop untuk insert setiap detail
    for ($i = 0; $i < count($jenis_pembayaran); $i++) {
        $jenis_id = $jenis_pembayaran[$i];
        $jumlah   = floatval(str_replace('.', '', $jumlah_pembayaran[$i]));
        $bulan_val = $bulan_pembayaran[$i] !== '' ? $bulan_pembayaran[$i] : null;
        $cashback  = isset($cashback_pembayaran[$i]) 
                     ? intval(str_replace('.', '', $cashback_pembayaran[$i])) 
                     : 0;
        $effective = $jumlah + $cashback;

        // Hitung ulang total_prev & angsuran_count
        if ($bulan_val !== null) {
            $sql = "
                SELECT 
                    COALESCE(SUM(pd.jumlah + COALESCE(pd.cashback,0)),0) AS total_prev,
                    COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.no_formulir = ? 
                  AND pd.jenis_pembayaran_id = ? 
                  AND pd.bulan = ? 
                  AND p.tahun_pelajaran = ?
            ";
            $stmtSum = $conn->prepare($sql);
            $stmtSum->bind_param('siis', $no_formulir, $jenis_id, $bulan_val, $tahun_pelajaran);
        } else {
            $sql = "
                SELECT 
                    COALESCE(SUM(pd.jumlah + COALESCE(pd.cashback,0)),0) AS total_prev,
                    COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.no_formulir = ? 
                  AND pd.jenis_pembayaran_id = ? 
                  AND pd.bulan IS NULL 
                  AND p.tahun_pelajaran = ?
            ";
            $stmtSum = $conn->prepare($sql);
            $stmtSum->bind_param('sis', $no_formulir, $jenis_id, $tahun_pelajaran);
        }
        $stmtSum->execute();
        $row = $stmtSum->get_result()->fetch_assoc();
        $total_prev     = floatval($row['total_prev']);
        $angsuran_count = intval($row['angsuran_count']);
        $stmtSum->close();

        // Cek status
        $sisa = floatval($conn->query(
            "SELECT nominal_max 
             FROM pengaturan_nominal 
             WHERE jenis_pembayaran_id = $jenis_id"
        )->fetch_assoc()['nominal_max']) - $total_prev;

        if ($total_prev + $effective >= $total_prev + $sisa) {
            $status = 'Lunas';
            $angsuran = null;
        } else {
            $angsuran = $angsuran_count + 1;
            $status = "Angsuran ke-$angsuran";
        }

        // Bind dan execute
        $stmt_detail->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $jumlah,
            $bulan_val,
            $status,
            $angsuran,
            $cashback
        );
        if (!$stmt_detail->execute()) {
            throw new Exception('Gagal menambahkan pembayaran detail: ' . $stmt_detail->error);
        }
    }

    $stmt_detail->close();
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil ditambahkan.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
