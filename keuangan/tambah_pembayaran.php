<?php
// tambah_pembayaran.php

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

$unit_petugas = $_SESSION['unit'];
$bulan_order = ["Juli","Agustus","September","Oktober","November","Desember",
                "Januari","Februari","Maret","April","Mei","Juni"];

$no_formulir         = isset($_POST['no_formulir'])        ? sanitize($_POST['no_formulir'])        : '';
$nama                = isset($_POST['nama'])               ? sanitize($_POST['nama'])               : '';
$tahun_pelajaran     = isset($_POST['tahun_pelajaran'])    ? sanitize($_POST['tahun_pelajaran'])    : '';
$metode_pembayaran   = isset($_POST['metode_pembayaran'])  ? sanitize($_POST['metode_pembayaran'])  : '';
$keterangan          = isset($_POST['keterangan'])         ? sanitize($_POST['keterangan'])         : '';
$jenis_pembayaran    = isset($_POST['jenis_pembayaran'])   && is_array($_POST['jenis_pembayaran'])   ? $_POST['jenis_pembayaran']   : [];
$jumlah_pembayaran   = isset($_POST['jumlah'])             && is_array($_POST['jumlah'])             ? $_POST['jumlah']             : [];
$bulan_pembayaran    = isset($_POST['bulan'])              && is_array($_POST['bulan'])              ? $_POST['bulan']              : [];
$cashback_pembayaran = isset($_POST['cashback'])           && is_array($_POST['cashback'])           ? $_POST['cashback']           : [];

$errors = [];
$total_effective = 0;

// Basic validation
if ($no_formulir === '')        $errors[] = 'No Formulir harus diisi.';
if ($nama === '')               $errors[] = 'Nama siswa tidak valid.';
if ($tahun_pelajaran === '')    $errors[] = 'Tahun Pelajaran harus diisi.';
if ($metode_pembayaran === '')  $errors[] = 'Metode Pembayaran harus dipilih.';
if (count($jenis_pembayaran) === 0) $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

// Validate each payment item and accumulate total_effective
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id = $jenis_pembayaran[$i];
    $j_str    = $jumlah_pembayaran[$i] ?? '0';
    $jumlah   = floatval(str_replace('.', '', $j_str));
    $cb_val   = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
                ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
                : 0;
    $bulan_val = $bulan_pembayaran[$i] ?? '';

    $effective = $jumlah + $cb_val;
    $total_effective += $effective;

    if ($jenis_id === '') {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran harus dipilih.";
        continue;
    }
    if ($effective <= 0) {
        $errors[] = "Item ".($i+1).": (jumlah + cashback) harus lebih dari 0.";
    }

    // Check jenis_pembayaran ownership & get its name
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id=? AND unit=?");
    $stmt->bind_param('is', $jenis_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran tidak ditemukan atau bukan milik unit Anda.";
        $stmt->close();
        continue;
    }
    $jenis_nama = strtolower($res->fetch_assoc()['nama']);
    $stmt->close();

    // Validate month & cashback rules
    if ($jenis_nama === 'spp') {
        if ($bulan_val === '' || array_search($bulan_val, $bulan_order) === false) {
            $errors[] = "Item ".($i+1).": Bulan harus dipilih dan valid untuk SPP.";
        }
    } else {
        if ($bulan_val !== '') {
            $errors[] = "Item ".($i+1).": Bulan hanya boleh diisi untuk SPP.";
        }
        if ($jenis_nama !== 'uang pangkal' && $cb_val > 0) {
            $errors[] = "Item ".($i+1).": Cashback hanya boleh untuk Uang Pangkal.";
        }
        if ($jenis_nama === 'uang pangkal' && $cb_val < 0) {
            $errors[] = "Item ".($i+1).": Cashback minimal 0.";
        }
    }

    // Fetch nominal_max
    $stmt = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=?");
    $stmt->bind_param('i', $jenis_id);
    $stmt->execute();
    $res2 = $stmt->get_result();
    if ($res2->num_rows === 0) {
        $errors[] = "Item ".($i+1).": Pengaturan nominal tidak ditemukan.";
        $stmt->close();
        continue;
    }
    $nominal_max = floatval($res2->fetch_assoc()['nominal_max']);
    $stmt->close();

    // Fetch previous total including cashback
    if ($bulan_val !== '') {
        $sql = "
            SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
            FROM pembayaran_detail pd
            JOIN pembayaran p ON p.id=pd.pembayaran_id
            WHERE p.no_formulir=? AND pd.jenis_pembayaran_id=? AND pd.bulan=? AND p.tahun_pelajaran=?
        ";
        $stm = $conn->prepare($sql);
        $stm->bind_param('siis', $no_formulir, $jenis_id, $bulan_val, $tahun_pelajaran);
    } else {
        $sql = "
            SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
            FROM pembayaran_detail pd
            JOIN pembayaran p ON p.id=pd.pembayaran_id
            WHERE p.no_formulir=? AND pd.jenis_pembayaran_id=? AND pd.bulan IS NULL AND p.tahun_pelajaran=?
        ";
        $stm = $conn->prepare($sql);
        $stm->bind_param('sis', $no_formulir, $jenis_id, $tahun_pelajaran);
    }
    $stm->execute();
    $prev = floatval($stm->get_result()->fetch_assoc()['prev']);
    $stm->close();

    // Validate remaining balance
    $sisa = $nominal_max - $prev;
    if ($effective > $sisa) {
        $errors[] = "Item ".($i+1).": (jumlah+cashback) melebihi sisa ".number_format($sisa,0,',','.').".";
    }
}

if ($total_effective <= 0) {
    $errors[] = 'Total (jumlah+cashback) harus lebih dari 0.';
}

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

// Begin transaction
$conn->begin_transaction();
try {
    // Fetch siswa_id
    $stmt = $conn->prepare("SELECT id FROM siswa WHERE no_formulir=? AND unit=?");
    $stmt->bind_param('ss', $no_formulir, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Siswa tidak ditemukan atau unit tidak sesuai.');
    }
    $siswa_id = $res->fetch_assoc()['id'];
    $stmt->close();

    // Insert into pembayaran
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
        $total_effective,
        $metode_pembayaran,
        $tahun_pelajaran,
        $tgl,
        $keterangan
    );
    if (!$stmt->execute()) {
        throw new Exception('Gagal menambahkan pembayaran: '.$stmt->error);
    }
    $pembayaran_id = $stmt->insert_id;
    $stmt->close();

    // Prepare detail insert
    $stmt = $conn->prepare("
        INSERT INTO pembayaran_detail
        (pembayaran_id,jenis_pembayaran_id,jumlah,bulan,status_pembayaran,angsuran_ke,cashback)
        VALUES(?,?,?,?,?,?,?)
    ");

    // Loop and insert each detail
    for ($i = 0; $i < count($jenis_pembayaran); $i++) {
        $jenis_id  = $jenis_pembayaran[$i];
        $j_str     = $jumlah_pembayaran[$i] ?? '0';
        $jumlah    = floatval(str_replace('.', '', $j_str));
        $cb_val    = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
                     ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
                     : 0;
        $bulan_val = isset($bulan_pembayaran[$i]) && $bulan_pembayaran[$i] !== ''
                     ? $bulan_pembayaran[$i] 
                     : null;
        $effective = $jumlah + $cb_val;

        // Get jenis_nama again
        $stm2 = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id=?");
        $stm2->bind_param('i',$jenis_id);
        $stm2->execute();
        $jn = strtolower($stm2->get_result()->fetch_assoc()['nama']);
        $stm2->close();

        // Fetch prev total and count
        if ($bulan_val) {
            $sql2 = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev,
                       COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=? AND pd.bulan=? AND p.tahun_pelajaran=?
            ";
            $stm3 = $conn->prepare($sql2);
            $stm3->bind_param('iiss', $siswa_id, $jenis_id, $bulan_val, $tahun_pelajaran);
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
        $cnt  = intval($row['cnt']);
        $stm3->close();

        // Fetch nominal_max
        $stm4 = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=?");
        $stm4->bind_param('i',$jenis_id);
        $stm4->execute();
        $nom = floatval($stm4->get_result()->fetch_assoc()['nominal_max']);
        $stm4->close();

        // Determine status & angsuran only for Uang Pangkal
        if ($jn === 'uang pangkal') {
            if ($prev + $effective >= $nom) {
                $status = 'Lunas';
                $angs = null;
            } else {
                $angs = $cnt + 1;
                $status = 'Angsuran ke-'.$angs;
            }
        } else {
            if ($effective >= $nom) {
                $status = 'Lunas';
            } else {
                $status = 'Belum Lunas';
            }
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
            throw new Exception('Gagal menambahkan detail: '.$stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil ditambahkan.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
