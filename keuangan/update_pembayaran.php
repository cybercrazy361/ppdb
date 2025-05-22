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

// Ambil input JSON
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

// Urutan bulan
$bulan_order = ["Juli","Agustus","September","Oktober","November","Desember",
                "Januari","Februari","Maret","April","Mei","Juni"];

// Ambil sesi & input
$unit_petugas       = $_SESSION['unit'];
$pembayaran_id      = intval($input['pembayaran_id'] ?? 0);
$metode_pembayaran  = sanitize($input['metode_pembayaran'] ?? '');
$tahun_pelajaran    = sanitize($input['tahun_pelajaran'] ?? '');
$keterangan         = sanitize($input['keterangan'] ?? '');

$jenis_pembayaran   = $input['jenis_pembayaran']    ?? [];
$jumlah_pembayaran  = $input['jumlah_pembayaran']   ?? [];
$bulan_pembayaran   = $input['bulan_pembayaran']    ?? [];
$cashback_pembayaran= $input['cashback']            ?? [];

$errors = [];

// Validasi dasar
if ($pembayaran_id <= 0)               $errors[] = 'ID Pembayaran tidak valid.';
if (empty($metode_pembayaran))        $errors[] = 'Metode Pembayaran harus dipilih.';
if (empty($tahun_pelajaran))          $errors[] = 'Tahun Pelajaran harus diisi.';
if (count($jenis_pembayaran) === 0)    $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

// Hitung total netto
$total_netto = 0;
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id   = intval($jenis_pembayaran[$i]);
    $j_raw      = floatval(str_replace('.', '', $jumlah_pembayaran[$i] ?? '0'));
    $cb_raw     = floatval(str_replace('.', '', $cashback_pembayaran[$i] ?? '0'));
    $netto      = $j_raw - $cb_raw;
    $bulan_val  = $bulan_pembayaran[$i] ?? null;

    if ($jenis_id <= 0)            $errors[] = "Item ".($i+1)." : Jenis wajib dipilih.";
    if ($j_raw <= 0)               $errors[] = "Item ".($i+1)." : Jumlah harus >0.";
    if ($cb_raw < 0)               $errors[] = "Item ".($i+1)." : Cashback minimal 0.";

    // Cek jenis & unit
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id=? AND unit=?");
    $stmt->bind_param('is', $jenis_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $errors[] = "Item ".($i+1)." : Jenis tidak ditemukan/unit mismatch.";
    } else {
        $nama = strtolower($res->fetch_assoc()['nama']);
        if ($nama === 'spp' && !$bulan_val) {
            $errors[] = "Item ".($i+1)." : Bulan wajib untuk SPP.";
        }
        if ($nama !== 'spp' && $bulan_val) {
            $errors[] = "Item ".($i+1)." : Bulan hanya untuk SPP.";
        }
        if ($nama !== 'uang pangkal' && $cb_raw > 0) {
            $errors[] = "Item ".($i+1)." : Cashback hanya untuk Uang Pangkal.";
        }
    }
    $stmt->close();

    // Cek nominal_max
    $stmt2 = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=?");
    $stmt2->bind_param('i', $jenis_id);
    $stmt2->execute();
    $r2 = $stmt2->get_result();
    if ($r2->num_rows === 0) {
        $errors[] = "Item ".($i+1)." : Pengaturan nominal tidak ada.";
    } else {
        $max = floatval($r2->fetch_assoc()['nominal_max']);
        if ($j_raw > $max) {
            $errors[] = "Item ".($i+1)." : Jumlah melebihi nominal_max.";
        }
    }
    $stmt2->close();

    $total_netto += $netto;
}

if ($total_netto <= 0) $errors[] = 'Total (jumlah–cashback) harus >0.';
if ($errors) {
    echo json_encode(['success'=>false,'message'=>implode('<br>',$errors)]);
    exit();
}

$conn->begin_transaction();
try {
    // Verifikasi kepemilikan
    $v = $conn->prepare("
        SELECT p.siswa_id
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id=s.id
        WHERE p.id=? AND s.unit=?
    ");
    $v->bind_param('is',$pembayaran_id,$unit_petugas);
    $v->execute();
    $rv = $v->get_result();
    if ($rv->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau unit mismatch.');
    }
    $siswa_id = $rv->fetch_assoc()['siswa_id'];
    $v->close();

    // Update header
    $u = $conn->prepare("
        UPDATE pembayaran
        SET jumlah=?, metode_pembayaran=?, tahun_pelajaran=?, keterangan=?
        WHERE id=?
    ");
    $u->bind_param('dsssi',$total_netto,$metode_pembayaran,$tahun_pelajaran,$keterangan,$pembayaran_id);
    $u->execute();
    $u->close();

    // Hapus detail lama
    $d = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id=?");
    $d->bind_param('i',$pembayaran_id);
    $d->execute();
    $d->close();

    // Siapkan insert detail
    $ins = $conn->prepare("
        INSERT INTO pembayaran_detail
          (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
        VALUES (?,?,?,?,?,?,?)
    ");

    // Loop each item
    for ($i=0; $i<count($jenis_pembayaran); $i++) {
        $jenis_id  = intval($jenis_pembayaran[$i]);
        $j_raw     = floatval(str_replace('.', '', $jumlah_pembayaran[$i]));
        $cb_raw    = floatval(str_replace('.', '', $cashback_pembayaran[$i] ?? 0));
        $netto     = $j_raw - $cb_raw;
        $bulan_val = $bulan_pembayaran[$i] ?? null;

        // Ambil nominal_max lagi
        $n = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=?");
        $n->bind_param('i',$jenis_id);
        $n->execute();
        $rn = $n->get_result()->fetch_assoc();
        $max = floatval($rn['nominal_max']);
        $n->close();

        // Hitung total sebelumnya (netto)
        if ($bulan_val) {
            $s = $conn->prepare("
                SELECT 
                  COALESCE(SUM(pd.jumlah - pd.cashback), 0) AS total_prev,
                  COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON pd.pembayaran_id=p.id
                WHERE p.siswa_id=? 
                  AND pd.jenis_pembayaran_id=?
                  AND pd.bulan=?
                  AND p.tahun_pelajaran=?
                  AND p.id<>?
            ");
            $s->bind_param('iissi',$siswa_id,$jenis_id,$bulan_val,$tahun_pelajaran,$pembayaran_id);
        } else {
            $s = $conn->prepare("
                SELECT 
                  COALESCE(SUM(pd.jumlah - pd.cashback), 0) AS total_prev,
                  COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON pd.pembayaran_id=p.id
                WHERE p.siswa_id=?
                  AND pd.jenis_pembayaran_id=?
                  AND pd.bulan IS NULL
                  AND p.tahun_pelajaran=?
                  AND p.id<>?
            ");
            $s->bind_param('iisi',$siswa_id,$jenis_id,$tahun_pelajaran,$pembayaran_id);
        }
        $s->execute();
        $rs = $s->get_result()->fetch_assoc();
        $prev_total = floatval($rs['total_prev']);
        $prev_cnt   = intval($rs['cnt']);
        $s->close();

        $remaining = $max - $prev_total;
        if ($netto > $remaining) {
            throw new Exception("Item ".($i+1)." : Bayar melebihi sisa ({$remaining}).");
        }

        // Tentukan status & angsuran_ke
        $cum = $prev_total + $netto;
        if ($cum >= $max) {
            $status = 'Lunas';
            $angs = null;
        } else {
            $angs = $prev_cnt + 1;
            $status = 'Angsuran ke-'.$angs;
        }

        // Insert detail
        $ins->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $j_raw,
            $bulan_val,
            $status,
            $angs,
            $cb_raw
        );
        $ins->execute();
    }
    $ins->close();

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Pembayaran berhasil diperbarui.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

$conn->close();
