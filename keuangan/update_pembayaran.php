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

$unit_petugas = $_SESSION['unit'];
$bulan_order  = ["Juli","Agustus","September","Oktober","November","Desember",
                 "Januari","Februari","Maret","April","Mei","Juni"];

$pembayaran_id     = isset($input['pembayaran_id'])     ? intval($input['pembayaran_id'])     : 0;
$metode_pembayaran = isset($input['metode_pembayaran']) ? sanitize($input['metode_pembayaran']) : '';
$tahun_pelajaran   = isset($input['tahun_pelajaran'])   ? sanitize($input['tahun_pelajaran'])   : '';
$keterangan        = isset($input['keterangan'])        ? sanitize($input['keterangan'])        : '';

$jenis_list  = isset($input['jenis_pembayaran'])   && is_array($input['jenis_pembayaran'])   ? $input['jenis_pembayaran']   : [];
$jumlah_list = isset($input['jumlah_pembayaran']) && is_array($input['jumlah_pembayaran']) ? $input['jumlah_pembayaran'] : [];
$bulan_list  = isset($input['bulan_pembayaran'])  && is_array($input['bulan_pembayaran'])  ? $input['bulan_pembayaran']  : [];
$cb_list     = isset($input['cashback'])          && is_array($input['cashback'])          ? $input['cashback']          : [];

$errors = [];
$total_effective = 0;

// Validasi dasar
if ($pembayaran_id <= 0)        $errors[] = 'ID Pembayaran tidak valid.';
if ($metode_pembayaran === '')  $errors[] = 'Metode Pembayaran harus dipilih.';
if ($tahun_pelajaran === '')    $errors[] = 'Tahun Pelajaran harus diisi.';
if (count($jenis_list) === 0)   $errors[] = 'Setidaknya satu jenis pembayaran harus dipilih.';

$conn->begin_transaction();
try {
    // Verifikasi ownership & ambil siswa_id + no_formulir
    $stmt = $conn->prepare("
        SELECT p.siswa_id, s.no_formulir
        FROM pembayaran p
        JOIN siswa s ON p.siswa_id = s.id
        WHERE p.id = ? AND s.unit = ?
    ");
    $stmt->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $row = $res->fetch_assoc();
    $siswa_id = $row['siswa_id'];
    $no_formulir = $row['no_formulir'];

    $stmt->close();

    // === BLOKIR LONCAT SPP (ambil paid_months SPP selain pembayaran ini) ===
    $paid_months = [];
    $sql_paid = "
        SELECT pd.bulan
        FROM pembayaran_detail pd
        JOIN pembayaran p ON p.id = pd.pembayaran_id
        JOIN jenis_pembayaran jp ON jp.id = pd.jenis_pembayaran_id
        WHERE p.no_formulir = ? 
          AND p.tahun_pelajaran = ? 
          AND jp.nama = 'SPP' 
          AND pd.status_pembayaran = 'Lunas'
          AND p.id != ?
    ";
    $stmt_paid = $conn->prepare($sql_paid);
    $stmt_paid->bind_param('ssi', $no_formulir, $tahun_pelajaran, $pembayaran_id);
    $stmt_paid->execute();
    $res_paid = $stmt_paid->get_result();
    while ($row_paid = $res_paid->fetch_assoc()) {
        if ($row_paid['bulan']) $paid_months[] = $row_paid['bulan'];
    }
    $stmt_paid->close();

    // Validasi tiap item pembayaran (termasuk urutan SPP)
    for ($i = 0; $i < count($jenis_list); $i++) {
        $jenis_id = $jenis_list[$i];
        $j_str    = $jumlah_list[$i] ?? '0';
        $jumlah   = floatval(str_replace('.', '', $j_str));
        $cb       = isset($cb_list[$i]) && $cb_list[$i] !== '' 
                    ? intval(str_replace('.', '', $cb_list[$i])) 
                    : 0;
        $bulan    = $bulan_list[$i] ?? '';
        $effective = $jumlah + $cb;
        $total_effective += $effective;

        if ($jenis_id === '') {
            $errors[] = "Item ".($i+1).": Jenis pembayaran harus dipilih.";
            continue;
        }
        if ($effective <= 0) {
            $errors[] = "Item ".($i+1).": (jumlah+cashback) harus lebih dari 0.";
        }

        // Ambil nama jenis & cek unit
        $stmt = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
        $stmt->bind_param('is', $jenis_id, $unit_petugas);
        $stmt->execute();
        $res_jenis = $stmt->get_result();
        if ($res_jenis->num_rows === 0) {
            $errors[] = "Item ".($i+1).": Jenis pembayaran tidak ditemukan untuk unit Anda.";
            $stmt->close();
            continue;
        }
        $nama_jenis = strtolower($res_jenis->fetch_assoc()['nama']);
        $stmt->close();

        // Validasi bulan & cashback
        if ($nama_jenis === 'spp') {
            if ($bulan === '' || array_search($bulan, $bulan_order) === false) {
                $errors[] = "Item ".($i+1).": Bulan harus dipilih dan valid untuk SPP.";
            }
            // Cek SPP harus urut
            $bulan_progress = [];
            foreach ($bulan_order as $b) if (in_array($b, $paid_months)) $bulan_progress[] = $b;
            // Cari bulan pertama yang belum lunas
            $first_unpaid = '';
            foreach ($bulan_order as $b) {
                if (!in_array($b, $paid_months)) {
                    $first_unpaid = $b;
                    break;
                }
            }
            // Cek apakah di input ada SPP bulan yang loncat
            // Ambil semua bulan SPP yang diinputkan (hanya bulan pada proses update ini)
            $bulan_spp_in_update = [];
            for ($k = 0; $k < count($jenis_list); $k++) {
                if ($jenis_list[$k] == $jenis_id) {
                    $stmt_spp = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id=?");
                    $stmt_spp->bind_param('i', $jenis_list[$k]);
                    $stmt_spp->execute();
                    $res_spp = $stmt_spp->get_result();
                    $nm_spp = strtolower($res_spp->fetch_assoc()['nama']);
                    $stmt_spp->close();
                    if ($nm_spp === 'spp') {
                        $b_spp = $bulan_list[$k] ?? '';
                        if ($b_spp) $bulan_spp_in_update[] = $b_spp;
                    }
                }
            }
            // Lakukan validasi urutan hanya pada item SPP pertama pada form
            // (agar error tidak muncul banyak2)
            if ($bulan !== $first_unpaid && !in_array($first_unpaid, $bulan_spp_in_update)) {
                $errors[] = "Item ".($i+1).": Pembayaran SPP harus urut. Bulan berikutnya hanya boleh dibayar jika bulan sebelumnya lunas. Silakan bayar bulan <b>$first_unpaid</b> terlebih dahulu.";
            }
        } else {
            if ($bulan !== '') {
                $errors[] = "Item ".($i+1).": Bulan hanya boleh diisi untuk SPP.";
            }
            if ($nama_jenis !== 'uang pangkal' && $cb > 0) {
                $errors[] = "Item ".($i+1).": Cashback hanya untuk Uang Pangkal.";
            }
            if ($nama_jenis === 'uang pangkal' && $cb < 0) {
                $errors[] = "Item ".($i+1).": Cashback minimal 0.";
            }
        }

        // Ambil nominal_max berdasar bulan & unit
        if ($nama_jenis === 'spp') {
            $stmt = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan = ?
                  AND unit = ?
            ");
            $stmt->bind_param('iss', $jenis_id, $bulan, $unit_petugas);
        } else {
            $stmt = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan IS NULL
                  AND unit = ?
            ");
            $stmt->bind_param('is', $jenis_id, $unit_petugas);
        }
        $stmt->execute();
        $res2 = $stmt->get_result();
        if ($res2->num_rows === 0) {
            $errors[] = "Item ".($i+1).": Pengaturan nominal tidak ditemukan.";
            $stmt->close();
            continue;
        }
        $nominal_max = floatval($res2->fetch_assoc()['nominal_max']);
        $stmt->close();

        // Hitung prev (total sebelum)
        if ($nama_jenis === 'spp') {
            $sql = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.no_formulir=? 
                  AND pd.jenis_pembayaran_id=? 
                  AND pd.bulan=? 
                  AND p.tahun_pelajaran=?
                  AND p.id != ?
            ";
            $stm = $conn->prepare($sql);
            $stm->bind_param('siisi', $no_formulir, $jenis_id, $bulan, $tahun_pelajaran, $pembayaran_id);
        } else {
            $sql = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.no_formulir=? 
                  AND pd.jenis_pembayaran_id=? 
                  AND pd.bulan IS NULL 
                  AND p.tahun_pelajaran=?
                  AND p.id != ?
            ";
            $stm = $conn->prepare($sql);
            $stm->bind_param('sisi', $no_formulir, $jenis_id, $tahun_pelajaran, $pembayaran_id);
        }
        $stm->execute();
        $prev = floatval($stm->get_result()->fetch_assoc()['prev']);
        $stm->close();

        $sisa = $nominal_max - $prev;

        // Blokir input ulang & cek sisa untuk SPP
        if ($nama_jenis === 'spp') {
            if ($prev >= $nominal_max) {
                $errors[] = "Item ".($i+1).": SPP bulan $bulan sudah lunas, tidak bisa bayar lagi.";
                continue;
            }
            if ($effective > $sisa) {
                $errors[] = "Item ".($i+1).": (jumlah+cashback) melebihi sisa ".number_format($sisa,0,',','.').".";
                continue;
            }
        }
    }

    if ($total_effective <= 0) {
        $errors[] = 'Total (jumlah+cashback) harus lebih dari 0.';
    }

    if ($errors) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
        exit();
    }

    // Update header pembayaran
    $stmt = $conn->prepare("
        UPDATE pembayaran
        SET jumlah = ?, metode_pembayaran = ?, tahun_pelajaran = ?, keterangan = ?
        WHERE id = ?
    ");
    $stmt->bind_param('dsssi', $total_effective, $metode_pembayaran, $tahun_pelajaran, $keterangan, $pembayaran_id);
    if (!$stmt->execute()) {
        throw new Exception('Gagal update pembayaran: ' . $stmt->error);
    }
    $stmt->close();

    // Hapus detail lama
    $stmt = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id = ?");
    $stmt->bind_param('i', $pembayaran_id);
    $stmt->execute();
    $stmt->close();

    // Siapkan insert detail
    $stmt = $conn->prepare("
        INSERT INTO pembayaran_detail
          (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Loop insert detail baru
    for ($i = 0; $i < count($jenis_list); $i++) {
        $jenis_id = $jenis_list[$i];
        $j_str    = $jumlah_list[$i] ?? '0';
        $jumlah   = floatval(str_replace('.', '', $j_str));
        $cb       = isset($cb_list[$i]) && $cb_list[$i] !== '' 
                    ? intval(str_replace('.', '', $cb_list[$i])) 
                    : 0;
        $bulan    = isset($bulan_list[$i]) && $bulan_list[$i] !== '' 
                    ? $bulan_list[$i] 
                    : null;
        $effective = $jumlah + $cb;

        // Ambil nama jenis ulang
        $stm2 = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ?");
        $stm2->bind_param('i', $jenis_id);
        $stm2->execute();
        $nm = strtolower($stm2->get_result()->fetch_assoc()['nama']);
        $stm2->close();

        // Hitung prev & cnt
        if ($nm === 'spp' && $bulan) {
            $sql2 = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev,
                       COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id = ? 
                  AND pd.jenis_pembayaran_id = ? 
                  AND pd.bulan = ? 
                  AND p.tahun_pelajaran = ?
                  AND p.id != ?
            ";
            $stm3 = $conn->prepare($sql2);
            $stm3->bind_param('iissi', $siswa_id, $jenis_id, $bulan, $tahun_pelajaran, $pembayaran_id);
        } else {
            $sql2 = "
                SELECT COALESCE(SUM(pd.jumlah+COALESCE(pd.cashback,0)),0) AS prev,
                       COUNT(*) AS cnt
                FROM pembayaran_detail pd
                JOIN pembayaran p ON p.id=pd.pembayaran_id
                WHERE p.siswa_id = ? 
                  AND pd.jenis_pembayaran_id = ? 
                  AND pd.bulan IS NULL 
                  AND p.tahun_pelajaran = ?
                  AND p.id != ?
            ";
            $stm3 = $conn->prepare($sql2);
            $stm3->bind_param('iisi', $siswa_id, $jenis_id, $tahun_pelajaran, $pembayaran_id);
        }
        $stm3->execute();
        $row2 = $stm3->get_result()->fetch_assoc();
        $prev = floatval($row2['prev']);
        $cnt  = intval($row2['cnt']);
        $stm3->close();

        // Ambil nominal_max lagi
        if ($nm === 'spp') {
            $stm4 = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan = ?
                  AND unit = ?
            ");
            $stm4->bind_param('iss', $jenis_id, $bulan, $unit_petugas);
        } else {
            $stm4 = $conn->prepare("
                SELECT nominal_max
                FROM pengaturan_nominal
                WHERE jenis_pembayaran_id = ?
                  AND bulan IS NULL
                  AND unit = ?
            ");
            $stm4->bind_param('is', $jenis_id, $unit_petugas);
        }
        $stm4->execute();
        $nom = floatval($stm4->get_result()->fetch_assoc()['nominal_max']);
        $stm4->close();

        // Tentukan status & angsuran
        if ($nm === 'uang pangkal') {
            if ($prev + $effective >= $nom) {
                $status = 'Lunas';
                $angs   = null;
            } else {
                $angs   = $cnt + 1;
                $status = 'Angsuran ke-'.$angs;
            }
        } elseif ($nm === 'spp') {
            if ($prev + $effective >= $nom) {
                $status = 'Lunas';
            } else {
                $status = 'Angsuran ke-'.($cnt + 1);
            }
            $angs = null;
        } else {
            $status = ($effective >= $nom) ? 'Lunas' : 'Belum Lunas';
            $angs   = null;
        }

        $stmt->bind_param(
            'iidssii',
            $pembayaran_id,
            $jenis_id,
            $jumlah,
            $bulan,
            $status,
            $angs,
            $cb
        );
        if (!$stmt->execute()) {
            throw new Exception('Gagal menambahkan detail: '.$stmt->error);
        }
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diperbarui.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
