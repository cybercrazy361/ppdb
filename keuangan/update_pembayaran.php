<?php
// update_pembayaran.php

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

// Mendapatkan input JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

include '../database_connection.php';

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Unit petugas
$unit_petugas = $_SESSION['unit'];

// Urutan bulan
$bulan_order = ["Juli","Agustus","September","Oktober","November","Desember",
                "Januari","Februari","Maret","April","Mei","Juni"];

// Ambil dan sanitize input
$pembayaran_id       = isset($input['pembayaran_id'])       ? intval($input['pembayaran_id']) : 0;
$metode_pembayaran   = isset($input['metode_pembayaran'])   ? sanitize($input['metode_pembayaran']) : '';
$keterangan          = isset($input['keterangan'])          ? sanitize($input['keterangan']) : '';
$tahun_pelajaran     = isset($input['tahun_pelajaran'])     ? sanitize($input['tahun_pelajaran']) : '';
$jenis_pembayaran    = isset($input['jenis_pembayaran'])    && is_array($input['jenis_pembayaran'])    ? $input['jenis_pembayaran']    : [];
$jumlah_pembayaran   = isset($input['jumlah_pembayaran'])   && is_array($input['jumlah_pembayaran'])   ? $input['jumlah_pembayaran']   : [];
$bulan_pembayaran    = isset($input['bulan_pembayaran'])    && is_array($input['bulan_pembayaran'])    ? $input['bulan_pembayaran']    : [];
$cashback_pembayaran = isset($input['cashback'])            && is_array($input['cashback'])            ? $input['cashback']            : [];

$errors = [];

// Validasi dasar
if ($pembayaran_id <= 0)              $errors[] = 'ID Pembayaran tidak valid.';
if ($metode_pembayaran === '')        $errors[] = 'Metode Pembayaran harus dipilih.';
if ($tahun_pelajaran === '')         $errors[] = 'Tahun Pelajaran harus diisi.';
if (count($jenis_pembayaran) === 0)   $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

$total_jumlah = 0;
$total_effective = 0;

// Validasi dan hitung total
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id  = $jenis_pembayaran[$i];
    $j_str     = $jumlah_pembayaran[$i] ?? '0';
    $jumlah    = floatval(str_replace('.', '', $j_str));
    $cb_val    = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== ''
                 ? intval(str_replace('.', '', $cashback_pembayaran[$i]))
                 : 0;
    $bulan_val = isset($bulan_pembayaran[$i]) ? $bulan_pembayaran[$i] : '';

    // effective = jumlah + cashback
    $effective = $jumlah + $cb_val;

    if ($jenis_id === '') {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran harus dipilih.";
        continue;
    }
    if ($jumlah <= 0 && $effective <= 0) {
        $errors[] = "Item ".($i+1).": Jumlah Pembayaran harus lebih dari 0.";
    }

    // Validasi jenis & unit
    $stmt_jp = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    $stmt_jp->bind_param('is', $jenis_id, $unit_petugas);
    $stmt_jp->execute();
    $res_jp = $stmt_jp->get_result();
    if ($res_jp->num_rows === 0) {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran tidak ditemukan atau tidak untuk unit Anda.";
        $stmt_jp->close();
        continue;
    }
    $jenis_nama = strtolower($res_jp->fetch_assoc()['nama']);
    $stmt_jp->close();

    // Validasi bulan & cashback
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

    // Ambil nominal_max
    $stmt_nom = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
    $stmt_nom->bind_param('i', $jenis_id);
    $stmt_nom->execute();
    $res_nom = $stmt_nom->get_result();
    if ($res_nom->num_rows === 0) {
        $errors[] = "Item ".($i+1).": Pengaturan nominal tidak ditemukan.";
        $stmt_nom->close();
        continue;
    }
    $nominal_max = floatval($res_nom->fetch_assoc()['nominal_max']);
    $stmt_nom->close();

    $total_jumlah   += $jumlah;
    $total_effective += $effective;
}

if ($total_effective <= 0) {
    $errors[] = 'Total (jumlah+cashback) harus lebih dari 0.';
}

if ($errors) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

$conn->begin_transaction();

try {
    // Verifikasi pembayaran
    $stmt_v = $conn->prepare(
        "SELECT p.siswa_id, s.no_formulir 
         FROM pembayaran p 
         JOIN siswa s ON p.siswa_id=s.id 
         WHERE p.id=? AND s.unit=?"
    );
    $stmt_v->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmt_v->execute();
    $res_v = $stmt_v->get_result();
    if ($res_v->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $row_v = $res_v->fetch_assoc();
    $siswa_id = $row_v['siswa_id'];
    $stmt_v->close();

    // Update tabel pembayaran
    $stmt_up = $conn->prepare(
        "UPDATE pembayaran 
         SET jumlah=?, metode_pembayaran=?, tahun_pelajaran=?, keterangan=? 
         WHERE id=?"
    );
    $stmt_up->bind_param('dsssi', $total_effective, $metode_pembayaran, $tahun_pelajaran, $keterangan, $pembayaran_id);
    if (!$stmt_up->execute()) {
        throw new Exception('Gagal update pembayaran: '.$stmt_up->error);
    }
    $stmt_up->close();

    // Hapus detail lama
    $stmt_del = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id=?");
    $stmt_del->bind_param('i', $pembayaran_id);
    $stmt_del->execute();
    $stmt_del->close();

    // Siapkan insert detail
    $stmt_ins = $conn->prepare(
        "INSERT INTO pembayaran_detail
         (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    // Loop insert detail baru
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

        // Hitung total sebelumnya untuk jenis & bulan
        if ($bulan_val) {
            $sql_sum = "
                SELECT 
                  COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS total_prev,
                  COUNT(*) AS cnt 
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=? AND pd.bulan=? AND p.tahun_pelajaran=? AND p.id<>?
            ";
            $stmt_sum = $conn->prepare($sql_sum);
            $stmt_sum->bind_param('iissi', $siswa_id, $jenis_id, $bulan_val, $tahun_pelajaran, $pembayaran_id);
        } else {
            $sql_sum = "
                SELECT 
                  COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS total_prev,
                  COUNT(*) AS cnt 
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=? AND pd.bulan IS NULL AND p.tahun_pelajaran=? AND p.id<>?
            ";
            $stmt_sum = $conn->prepare($sql_sum);
            $stmt_sum->bind_param('iisi', $siswa_id, $jenis_id, $tahun_pelajaran, $pembayaran_id);
        }
        $stmt_sum->execute();
        $rs = $stmt_sum->get_result()->fetch_assoc();
        $prev_total = floatval($rs['total_prev']);
        $prev_cnt   = intval($rs['cnt']);
        $stmt_sum->close();

        // Tentukan status & angsuran
        $sisa = $nominal_max - $prev_total;
        $kumulatif = $prev_total + $effective;
        if ($kumulatif >= $nominal_max) {
            $status = 'Lunas';
            $angs = null;
        } else {
            $angs = $prev_cnt + 1;
            $status = 'Angsuran ke-'.$angs;
        }

        $stmt_ins->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $jumlah,
            $bulan_val,
            $status,
            $angs,
            $cb_val
        );
        if (!$stmt_ins->execute()) {
            throw new Exception('Gagal tambah detail: '.$stmt_ins->error);
        }
    }

    $stmt_ins->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diperbarui.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
