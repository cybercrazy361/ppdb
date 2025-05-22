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

// Unit petugas dari sesi
$unit_petugas = $_SESSION['unit'];

// Urutan bulan
$bulan_order = ["Juli","Agustus","September","Oktober","November","Desember",
                "Januari","Februari","Maret","April","Mei","Juni"];

// Ambil dan sanitize input
$pembayaran_id       = isset($input['pembayaran_id']) ? intval($input['pembayaran_id']) : 0;
$metode_pembayaran   = isset($input['metode_pembayaran']) ? sanitize($input['metode_pembayaran']) : '';
$keterangan          = isset($input['keterangan']) ? sanitize($input['keterangan']) : '';
$tahun_pelajaran     = isset($input['tahun_pelajaran']) ? sanitize($input['tahun_pelajaran']) : '';

$jenis_pembayaran    = $input['jenis_pembayaran']    ?? [];
$jumlah_pembayaran   = $input['jumlah_pembayaran']   ?? [];
$bulan_pembayaran    = $input['bulan_pembayaran']    ?? [];
$cashback_pembayaran = $input['cashback']            ?? [];

$errors = [];
$total_jumlah = 0;

// 1) Validasi dasar dan hitung total efektif
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id  = $jenis_pembayaran[$i];
    // parse jumlah dan cashback
    $jumlah   = floatval(str_replace('.', '', $jumlah_pembayaran[$i] ?? '0'));
    $cashback = intval(str_replace('.', '', $cashback_pembayaran[$i] ?? '0'));
    $bulan    = $bulan_pembayaran[$i] ?? '';

    // Validasi keutuhan
    if (!$jenis_id) {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran harus dipilih.";
    }
    if ($jumlah <= 0) {
        $errors[] = "Item ".($i+1).": Jumlah harus > 0.";
    }

    // Validasi unit & nama jenis
    $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    $stmt->bind_param('is', $jenis_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $errors[] = "Item ".($i+1).": Jenis Pembayaran tidak valid untuk unit Anda.";
        continue;
    }
    $nama_jenis = strtolower($res->fetch_assoc()['nama']);
    $stmt->close();

    // Bulan hanya untuk SPP
    if ($nama_jenis === 'spp') {
        if (!$bulan) {
            $errors[] = "Item ".($i+1).": Pilih bulan untuk SPP.";
        } elseif (array_search($bulan, $bulan_order) === false) {
            $errors[] = "Item ".($i+1).": Bulan '$bulan' tidak valid.";
        }
    } else {
        if ($bulan) {
            $errors[] = "Item ".($i+1).": Bulan hanya untuk jenis SPP.";
        }
        // cashback hanya untuk uang pangkal
        if ($nama_jenis !== 'uang pangkal' && $cashback>0) {
            $errors[] = "Item ".($i+1).": Cashback hanya untuk Uang Pangkal.";
        }
        if ($nama_jenis==='uang pangkal' && $cashback<0) {
            $errors[] = "Item ".($i+1).": Cashback minimal 0.";
        }
    }

    // cek nominal_max
    $stmt2 = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
    $stmt2->bind_param('i', $jenis_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2->num_rows===0) {
        $errors[] = "Item ".($i+1).": Tidak ada pengaturan nominal.";
        continue;
    }
    $nominal_max = floatval($res2->fetch_assoc()['nominal_max']);
    $stmt2->close();

    // Hitung total efektif: jumlah + cashback
    $effective = $jumlah + $cashback;
    if ($effective > $nominal_max) {
        $errors[] = "Item ".($i+1).": ($jumlah + $cashback) melebihi maksimum $nominal_max.";
    }

    $total_jumlah += $effective;
}

if ($pembayaran_id<=0) {
    $errors[] = "ID Pembayaran tidak valid.";
}
if (!$metode_pembayaran) {
    $errors[] = "Pilih Metode Pembayaran.";
}
if (!$tahun_pelajaran) {
    $errors[] = "Isi Tahun Pelajaran.";
}
if ($total_jumlah<=0) {
    $errors[] = "Total efektif harus > 0.";
}

if ($errors) {
    echo json_encode(['success'=>false, 'message'=> implode('<br>',$errors)]);
    exit();
}

// 2) Proses update
$conn->begin_transaction();
try {
    // Verifikasi kepemilikan
    $stmtV = $conn->prepare("
        SELECT p.siswa_id
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id=s.id
        WHERE p.id=? AND s.unit=?
    ");
    $stmtV->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmtV->execute();
    $resV = $stmtV->get_result();
    if ($resV->num_rows===0) {
        throw new Exception("Pembayaran tidak ditemukan/unit mismatch.");
    }
    $siswa_id = $resV->fetch_assoc()['siswa_id'];
    $stmtV->close();

    // Update header
    $stmtU = $conn->prepare("
        UPDATE pembayaran
        SET jumlah=?, metode_pembayaran=?, tahun_pelajaran=?, keterangan=?
        WHERE id=?
    ");
    $stmtU->bind_param('dsssi', $total_jumlah, $metode_pembayaran, $tahun_pelajaran, $keterangan, $pembayaran_id);
    $stmtU->execute();
    $stmtU->close();

    // Hapus detail lama
    $stmtD = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id=?");
    $stmtD->bind_param('i', $pembayaran_id);
    $stmtD->execute();
    $stmtD->close();

    // Siapkan insert detail
    $stmtI = $conn->prepare("
        INSERT INTO pembayaran_detail
        (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
        VALUES (?,?,?,?,?,?,?)
    ");

    // Loop insert ulang detail
    for ($i=0; $i<count($jenis_pembayaran); $i++) {
        $jenis_id  = $jenis_pembayaran[$i];
        $jumlah    = floatval(str_replace('.', '', $jumlah_pembayaran[$i]));
        $cashback  = intval(str_replace('.', '', $cashback_pembayaran[$i] ?? '0'));
        $bulan     = $bulan_pembayaran[$i] ?: null;

        // Hitung total_sebelum (jumlah+cashback)
        if ($bulan) {
            $sql = "
              SELECT COALESCE(SUM(jumlah+COALESCE(cashback,0)),0) AS total_prev,
                     COUNT(*) AS count_prev
              FROM pembayaran_detail pd
              JOIN pembayaran p ON pd.pembayaran_id=p.id
              WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=?
                AND pd.bulan=? AND p.tahun_pelajaran=? AND p.id<>?
            ";
            $stmtS = $conn->prepare($sql);
            $stmtS->bind_param('iissi', $siswa_id, $jenis_id, $bulan, $tahun_pelajaran, $pembayaran_id);
        } else {
            $sql = "
              SELECT COALESCE(SUM(jumlah+COALESCE(cashback,0)),0) AS total_prev,
                     COUNT(*) AS count_prev
              FROM pembayaran_detail pd
              JOIN pembayaran p ON pd.pembayaran_id=p.id
              WHERE p.siswa_id=? AND pd.jenis_pembayaran_id=?
                AND pd.bulan IS NULL AND p.tahun_pelajaran=? AND p.id<>?
            ";
            $stmtS = $conn->prepare($sql);
            $stmtS->bind_param('iisi', $siswa_id, $jenis_id, $tahun_pelajaran, $pembayaran_id);
        }
        $stmtS->execute();
        $rs = $stmtS->get_result()->fetch_assoc();
        $total_prev    = floatval($rs['total_prev']);
        $count_prev    = intval($rs['count_prev']);
        $stmtS->close();

        // Tentukan status
        $effective = $jumlah + $cashback;
        $angs_ke   = $count_prev + 1;
        if ($total_prev + $effective >= $nominal_max) {
            $status = 'Lunas';
            $angs_ke = null;
        } else {
            $status = 'Angsuran ke-'.$angs_ke;
        }

        // Insert record
        $stmtI->bind_param(
          'iidssii',
          $pembayaran_id,
          $jenis_id,
          $jumlah,
          $bulan,
          $status,
          $angs_ke,
          $cashback
        );
        if (!$stmtI->execute()) {
            throw new Exception("Gagal insert detail: ".$stmtI->error);
        }
    }
    $stmtI->close();

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'Pembayaran berhasil diperbarui.']);

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

$conn->close();
