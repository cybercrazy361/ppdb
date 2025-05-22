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

// Ambil JSON input
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

$unit_petugas     = $_SESSION['unit'];
$bulan_order      = ["Juli","Agustus","September","Oktober","November","Desember",
                     "Januari","Februari","Maret","April","Mei","Juni"];

// Ambil input
$pembayaran_id     = intval($input['pembayaran_id'] ?? 0);
$metode_pembayaran = sanitize($input['metode_pembayaran'] ?? '');
$tahun_pelajaran   = sanitize($input['tahun_pelajaran'] ?? '');
$keterangan        = sanitize($input['keterangan'] ?? '');

$jenis_pembayaran    = $input['jenis_pembayaran']    ?? [];
$jumlah_pembayaran   = $input['jumlah_pembayaran']   ?? [];
$bulan_pembayaran    = $input['bulan_pembayaran']    ?? [];
$cashback_pembayaran = $input['cashback']            ?? [];

$errors = [];
if ($pembayaran_id <= 0)    $errors[] = 'ID Pembayaran tidak valid.';
if (empty($metode_pembayaran)) $errors[] = 'Metode Pembayaran harus dipilih.';
if (empty($tahun_pelajaran))   $errors[] = 'Tahun Pelajaran harus diisi.';
if (count($jenis_pembayaran)===0) $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

// hitung total netto
$total_jumlah = 0;
for ($i=0; $i<count($jenis_pembayaran); $i++) {
    $jenis_id = intval($jenis_pembayaran[$i]);
    $jumlah_raw = floatval(str_replace('.','',$jumlah_pembayaran[$i]??'0'));
    $cb_raw     = floatval(str_replace('.','',$cashback_pembayaran[$i]??'0'));
    $netto      = $jumlah_raw - $cb_raw;
    $bulan_val  = $bulan_pembayaran[$i] ?? null;

    if ($jenis_id<=0)        $errors[] = "Item ".($i+1)." : Jenis harus dipilih.";
    if ($jumlah_raw<=0)      $errors[] = "Item ".($i+1)." : Jumlah harus >0.";
    if ($cb_raw<0)           $errors[] = "Item ".($i+1)." : Cashback minimal 0.";

    // cek unit & jenis
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id=? AND unit=?");
    $stmt->bind_param('is',$jenis_id,$unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows===0) {
        $errors[] = "Item ".($i+1)." : Jenis tidak ditemukan/unit salah.";
    } else {
        $nama = strtolower($res->fetch_assoc()['nama']);
        if ($nama==='spp' && !$bulan_val) {
            $errors[] = "Item ".($i+1)." : Bulan wajib untuk SPP.";
        }
        if ($nama!=='spp' && $bulan_val) {
            $errors[] = "Item ".($i+1)." : Bulan hanya untuk SPP.";
        }
        if ($nama!=='uang pangkal' && $cb_raw>0) {
            $errors[] = "Item ".($i+1)." : Cashback hanya untuk Uang Pangkal.";
        }
    }
    $stmt->close();

    // cek nominal_max
    $stmt2 = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id=?");
    $stmt2->bind_param('i',$jenis_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2->num_rows===0) {
        $errors[] = "Item ".($i+1)." : Tidak ada nominal_max.";
    } else {
        $max = floatval($res2->fetch_assoc()['nominal_max']);
        if ($jumlah_raw > $max) {
            $errors[] = "Item ".($i+1)." : Melebihi nominal_max.";
        }
    }
    $stmt2->close();

    $total_jumlah += $netto;
}

if ($total_jumlah <= 0) {
    $errors[] = 'Total netto pembayaran harus >0.';
}
if ($errors) {
    echo json_encode(['success'=>false,'message'=>implode('<br>',$errors)]);
    exit();
}

$conn->begin_transaction();
try {
    // verifikasi pemilik
    $stmtV = $conn->prepare("
        SELECT p.siswa_id,s.no_formulir
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id=s.id
        WHERE p.id=? AND s.unit=?
    ");
    $stmtV->bind_param('is',$pembayaran_id,$unit_petugas);
    $stmtV->execute();
    $rv = $stmtV->get_result();
    if ($rv->num_rows===0) throw new Exception('Pembayaran tidak ditemukan/unit mismatch.');
    $rowV = $rv->fetch_assoc();
    $siswa_id = $rowV['siswa_id'];
    $stmtV->close();

    // update utama (netto)
    $upd = $conn->prepare("
        UPDATE pembayaran
        SET jumlah=?, metode_pembayaran=?, tahun_pelajaran=?, keterangan=?
        WHERE id=?
    ");
    $upd->bind_param('dsssi',$total_jumlah,$metode_pembayaran,$tahun_pelajaran,$keterangan,$pembayaran_id);
    $upd->execute();
    $upd->close();

    // hapus detail lama
    $del = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id=?");
    $del->bind_param('i',$pembayaran_id);
    $del->execute();
    $del->close();

    // insert detail baru
    $ins = $conn->prepare("
        INSERT INTO pembayaran_detail
          (pembayaran_id, jenis_pembayaran_id,
           jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
        VALUES (?,?,?,?,?,?,?)
    ");

    for ($i=0; $i<count($jenis_pembayaran); $i++) {
        $jenis_id = intval($jenis_pembayaran[$i]);
        $jumlah_raw = floatval(str_replace('.','',$jumlah_pembayaran[$i]));
        $cb_raw     = floatval(str_replace('.','',$cashback_pembayaran[$i]??0));
        $netto      = $jumlah_raw - $cb_raw;
        $bulan_val  = $bulan_pembayaran[$i] ?? null;

        // cek sisa & angsuran
        if ($bulan_val) {
            // sum previous netto
            $stmtSum = $conn->prepare("
                SELECT
                  COALESCE(SUM(pd.jumlah - COALESCE(pd.cashback,0)),0) AS total_prev,
                  COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON pd.pembayaran_id=p.id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=?
                  AND pd.bulan=? AND p.tahun_pelajaran=?
                  AND p.id<>?
            ");
            $stmtSum->bind_param('iissi',
                $siswa_id,$jenis_id,$bulan_val,
                $tahun_pelajaran,$pembayaran_id
            );
        } else {
            $stmtSum = $conn->prepare("
                SELECT
                  COALESCE(SUM(pd.jumlah - COALESCE(pd.cashback,0)),0) AS total_prev,
                  COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON pd.pembayaran_id=p.id
                WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=?
                  AND pd.bulan IS NULL AND p.tahun_pelajaran=?
                  AND p.id<>?
            ");
            $stmtSum->bind_param('iisi',
                $siswa_id,$jenis_id,
                $tahun_pelajaran,$pembayaran_id
            );
        }
        $stmtSum->execute();
        $rSum = $stmtSum->get_result()->fetch_assoc();
        $total_prev = floatval($rSum['total_prev']);
        $cnt_prev   = intval($rSum['cnt']);
        $stmtSum->close();

        $sisa = $nominal_max - $total_prev;
        if ($netto > $sisa) {
            throw new Exception("Item ".($i+1)." : Bayar lebih dari sisa ({$sisa}).");
        }

        $total_kum = $total_prev + $netto;
        if ($total_kum >= $nominal_max) {
            $status = 'Lunas';
            $angs_ke = null;
        } else {
            $angs_ke = $cnt_prev + 1;
            $status = 'Angsuran ke-'.$angs_ke;
        }

        // execute insert
        $ins->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $jumlah_raw,
            $bulan_val,
            $status,
            $angs_ke,
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
