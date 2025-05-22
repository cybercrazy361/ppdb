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

// Mendapatkan unit_petugas dari sesi
$unit_petugas = $_SESSION['unit']; // Pastikan sesi memiliki 'unit'

// Definisikan urutan bulan sesuai tahun pelajaran
$bulan_order = ["Juli", "Agustus", "September", "Oktober", "November", "Desember", 
                "Januari", "Februari", "Maret", "April", "Mei", "Juni"];

// Mengambil dan menyanitasi input
$pembayaran_id = isset($input['pembayaran_id']) ? intval($input['pembayaran_id']) : 0;
$metode_pembayaran = isset($input['metode_pembayaran']) ? sanitize($input['metode_pembayaran']) : '';
$keterangan = isset($input['keterangan']) ? sanitize($input['keterangan']) : '';
$tahun_pelajaran = isset($input['tahun_pelajaran']) ? sanitize($input['tahun_pelajaran']) : '';

$jenis_pembayaran = isset($input['jenis_pembayaran']) && is_array($input['jenis_pembayaran']) ? $input['jenis_pembayaran'] : [];
$jumlah_pembayaran = isset($input['jumlah_pembayaran']) && is_array($input['jumlah_pembayaran']) ? $input['jumlah_pembayaran'] : [];
$bulan_pembayaran = isset($input['bulan_pembayaran']) && is_array($input['bulan_pembayaran']) ? $input['bulan_pembayaran'] : [];
$cashback_pembayaran = isset($input['cashback']) && is_array($input['cashback']) ? $input['cashback'] : [];

$errors = [];

// Validasi input dasar
if ($pembayaran_id <= 0) {
    $errors[] = 'ID Pembayaran tidak valid.';
}
if ($metode_pembayaran === '') {
    $errors[] = 'Metode Pembayaran harus dipilih.';
}
if ($tahun_pelajaran === '') {
    $errors[] = 'Tahun Pelajaran harus diisi.';
}
if (count($jenis_pembayaran) === 0) {
    $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';
}

$total_jumlah = 0;

// Validasi setiap item jenis pembayaran
for ($i = 0; $i < count($jenis_pembayaran); $i++) {
    $jenis_id = $jenis_pembayaran[$i];
    $jumlah_str = $jumlah_pembayaran[$i];
    $jumlah = floatval(str_replace('.', '', $jumlah_str));
    $bulan_val = isset($bulan_pembayaran[$i]) ? $bulan_pembayaran[$i] : '';
    $cashback = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== '' ? intval(str_replace('.', '', $cashback_pembayaran[$i])) : 0;

    if ($jenis_id === '') {
        $errors[] = "Jenis Pembayaran pada item " . ($i + 1) . " harus dipilih.";
    }
    if ($jumlah <= 0) {
        $errors[] = "Jumlah Pembayaran pada item " . ($i + 1) . " harus lebih dari 0.";
    }

    // Cek jenis pembayaran dan unit_petugas
    $stmt_jp = $conn->prepare("SELECT nama FROM jenis_pembayaran WHERE id = ? AND unit = ?");
    if (!$stmt_jp) {
        $errors[] = "Error preparing jenis_pembayaran statement: " . $conn->error;
        continue;
    }
    $stmt_jp->bind_param('is', $jenis_id, $unit_petugas);
    $stmt_jp->execute();
    $res_jp = $stmt_jp->get_result();
    if ($res_jp->num_rows > 0) {
        $jenis_nama = strtolower($res_jp->fetch_assoc()['nama']);

        // Validasi bulan sesuai jenis pembayaran
        if ($jenis_nama === 'spp') {
            if ($bulan_val === '') {
                $errors[] = "Bulan Pembayaran pada item " . ($i + 1) . " harus dipilih karena jenis pembayaran adalah SPP.";
            } else {
                $current_month_index = array_search($bulan_val, $bulan_order);
                if ($current_month_index === false) {
                    $errors[] = "Bulan pada item " . ($i + 1) . " tidak valid.";
                }
            }
        } else {
            if ($bulan_val !== '') {
                $errors[] = "Bulan hanya boleh diisi untuk pembayaran jenis SPP pada item " . ($i + 1) . ".";
            }
            // Validasi: cashback hanya untuk Uang Pangkal (opsional)
            if ($jenis_nama !== 'uang pangkal' && $cashback > 0) {
                $errors[] = "Cashback hanya boleh diisi untuk Uang Pangkal pada item " . ($i + 1) . ".";
            }
        }
        if ($jenis_nama === 'uang pangkal' && $cashback < 0) {
            $errors[] = "Cashback untuk Uang Pangkal harus 0 atau lebih.";
        }
    } else {
        $errors[] = "Jenis Pembayaran pada item " . ($i + 1) . " tidak ditemukan atau tidak sesuai unit.";
    }
    $stmt_jp->close();

    // Cek nominal_max
    $stmt_nominal = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
    if (!$stmt_nominal) {
        $errors[] = "Error preparing nominal_max statement: " . $conn->error;
        continue;
    }
    $stmt_nominal->bind_param('i', $jenis_id);
    $stmt_nominal->execute();
    $res_nominal = $stmt_nominal->get_result();
    if ($res_nominal->num_rows > 0) {
        $nominal_max = floatval($res_nominal->fetch_assoc()['nominal_max']);
        if ($jumlah > $nominal_max) {
            $errors[] = "Jumlah Pembayaran pada item " . ($i + 1) . " melebihi nominal maksimum.";
        }
    } else {
        $errors[] = "Pengaturan nominal tidak ditemukan untuk item " . ($i + 1) . ".";
    }
    $stmt_nominal->close();

    $total_jumlah += $jumlah;
}

if ($total_jumlah <= 0) {
    $errors[] = 'Total Pembayaran harus lebih dari 0.';
}

if (count($errors) > 0) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

$conn->begin_transaction();

try {
    // Verifikasi pembayaran_id dan pastikan pembayaran milik unit_petugas
    $stmt_verifikasi = $conn->prepare("SELECT p.siswa_id, s.no_formulir FROM pembayaran p INNER JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ? AND s.unit = ?");
    if (!$stmt_verifikasi) {
        throw new Exception("Error preparing verifikasi pembayaran statement: " . $conn->error);
    }
    $stmt_verifikasi->bind_param('is', $pembayaran_id, $unit_petugas);
    $stmt_verifikasi->execute();
    $res_verifikasi = $stmt_verifikasi->get_result();
    if ($res_verifikasi->num_rows === 0) {
        throw new Exception('Pembayaran tidak ditemukan atau tidak sesuai unit.');
    }
    $pembayaran = $res_verifikasi->fetch_assoc();
    $siswa_id = $pembayaran['siswa_id'];
    $no_formulir_pembayaran = $pembayaran['no_formulir'];
    $stmt_verifikasi->close();

    // Update pembayaran utama
    $stmt_update = $conn->prepare("UPDATE pembayaran SET jumlah = ?, metode_pembayaran = ?, tahun_pelajaran = ?, keterangan = ? WHERE id = ?");
    if (!$stmt_update) {
        throw new Exception("Error preparing update pembayaran statement: " . $conn->error);
    }
    $stmt_update->bind_param('dsssi', $total_jumlah, $metode_pembayaran, $tahun_pelajaran, $keterangan, $pembayaran_id);
    if (!$stmt_update->execute()) {
        throw new Exception('Gagal memperbarui pembayaran: ' . $stmt_update->error);
    }
    $stmt_update->close();

    // Hapus detail pembayaran lama
    $stmt_hapus_detail = $conn->prepare("DELETE FROM pembayaran_detail WHERE pembayaran_id = ?");
    if (!$stmt_hapus_detail) {
        throw new Exception("Error preparing hapus_detail statement: " . $conn->error);
    }
    $stmt_hapus_detail->bind_param('i', $pembayaran_id);
    if (!$stmt_hapus_detail->execute()) {
        throw new Exception('Gagal menghapus detail pembayaran lama: ' . $stmt_hapus_detail->error);
    }
    $stmt_hapus_detail->close();

    // Insert detail pembayaran baru, tambahkan kolom cashback
    $stmt_insert_detail = $conn->prepare("INSERT INTO pembayaran_detail (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_insert_detail) {
        throw new Exception("Error preparing insert_detail statement: " . $conn->error);
    }

    for ($i = 0; $i < count($jenis_pembayaran); $i++) {
        $jenis_id = $jenis_pembayaran[$i];
        $jumlah = floatval(str_replace('.', '', $jumlah_pembayaran[$i]));
        $bulan_val = isset($bulan_pembayaran[$i]) && $bulan_pembayaran[$i] !== '' ? $bulan_pembayaran[$i] : null;
        $cashback = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== '' ? intval(str_replace('.', '', $cashback_pembayaran[$i])) : 0;

        // Ambil nominal_max (sudah diverifikasi sebelumnya)
        $stmt_nom = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
        if (!$stmt_nom) {
            throw new Exception("Error preparing nominal_max statement: " . $conn->error);
        }
        $stmt_nom->bind_param('i', $jenis_id);
        $stmt_nom->execute();
        $res_nom = $stmt_nom->get_result();
        if ($res_nom->num_rows > 0) {
            $nominal_max = floatval($res_nom->fetch_assoc()['nominal_max']);
        } else {
            throw new Exception("Pengaturan nominal tidak ditemukan untuk jenis pembayaran ID: $jenis_id.");
        }
        $stmt_nom->close();

        // Jika SPP, cek kelunasan bulan sebelumnya
        if ($bulan_val !== null) {
            $current_month_index = array_search($bulan_val, $bulan_order);
            if ($current_month_index === false) {
                throw new Exception("Bulan pada item " . ($i + 1) . " tidak valid.");
            }
            if ($current_month_index > 0) {
                for ($m = 0; $m < $current_month_index; $m++) {
                    $prev_bulan = $bulan_order[$m];
                    $stmt_prev = $conn->prepare("
                        SELECT COALESCE(SUM(pd.jumlah),0) AS total_prev
                        FROM pembayaran_detail pd
                        INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                        WHERE p.siswa_id = ?
                          AND pd.jenis_pembayaran_id = ?
                          AND pd.bulan = ?
                          AND p.tahun_pelajaran = ?
                          AND p.id != ?
                    ");
                    if (!$stmt_prev) {
                        throw new Exception("Error preparing previous bulan statement: " . $conn->error);
                    }
                    $stmt_prev->bind_param('iissi', $siswa_id, $jenis_id, $prev_bulan, $tahun_pelajaran, $pembayaran_id);
                    $stmt_prev->execute();
                    $res_prev = $stmt_prev->get_result();
                    $total_prev = 0;
                    if ($res_prev->num_rows > 0) {
                        $total_prev = floatval($res_prev->fetch_assoc()['total_prev']);
                    }
                    $stmt_prev->close();

                    if ($total_prev < $nominal_max) {
                        throw new Exception("SPP untuk bulan $prev_bulan tahun pelajaran $tahun_pelajaran belum lunas. Tidak dapat membayar $bulan_val sebelum $prev_bulan lunas.");
                    }
                }
            }

            // Hitung total_sebelum untuk bulan ini
            $stmt_sum_query = "
                SELECT COALESCE(SUM(pd.jumlah), 0) AS total_sebelumnya, COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.siswa_id = ?
                  AND pd.jenis_pembayaran_id = ?
                  AND pd.bulan = ?
                  AND p.tahun_pelajaran = ?
                  AND p.id != ?
            ";
            $stmt_sum = $conn->prepare($stmt_sum_query);
            if (!$stmt_sum) {
                throw new Exception("Error preparing sum statement: " . $conn->error);
            }
            $stmt_sum->bind_param('iissi', $siswa_id, $jenis_id, $bulan_val, $tahun_pelajaran, $pembayaran_id);
        } else {
            // Non-SPP
            $stmt_sum_query = "
                SELECT COALESCE(SUM(pd.jumlah), 0) AS total_sebelumnya, COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.siswa_id = ?
                  AND pd.jenis_pembayaran_id = ?
                  AND pd.bulan IS NULL
                  AND p.tahun_pelajaran = ?
                  AND p.id != ?
            ";
            $stmt_sum = $conn->prepare($stmt_sum_query);
            if (!$stmt_sum) {
                throw new Exception("Error preparing sum statement: " . $conn->error);
            }
            $stmt_sum->bind_param('iisi', $siswa_id, $jenis_id, $tahun_pelajaran, $pembayaran_id);
        }

        $stmt_sum->execute();
        $res_sum = $stmt_sum->get_result();
        $total_sebelum = 0;
        $angsuran_count = 0;
        if ($res_sum->num_rows > 0) {
            $row_sum = $res_sum->fetch_assoc();
            $total_sebelum = floatval($row_sum['total_sebelumnya']);
            $angsuran_count = intval($row_sum['angsuran_count']);
        }
        $stmt_sum->close();

        if ($total_sebelum >= $nominal_max) {
            throw new Exception("Pembayaran untuk item " . ($i+1) . " pada tahun pelajaran $tahun_pelajaran sudah lunas. Tidak dapat menambah pembayaran lagi.");
        }

        $sisa_harus_bayar = $nominal_max - $total_sebelum;
        if ($jumlah > $sisa_harus_bayar) {
            throw new Exception(
                "Pembayaran pada item " . ($i+1) . " melebihi sisa yang harus dibayar. " .
                "Sisa: " . number_format($sisa_harus_bayar, 0, ',', '.') .
                ", Anda membayar: " . number_format($jumlah, 0, ',', '.')
            );
        }

        $total_kumulatif = $total_sebelum + $jumlah;
        $angsuran_ke = $angsuran_count + 1;
        if ($total_kumulatif >= $nominal_max) {
            $status_pembayaran = 'Lunas';
            $angsuran_ke = null;
        } else {
            $status_pembayaran = 'Angsuran ke-' . $angsuran_ke;
        }

        // Bind parameter dan eksekusi insert_detail (tambahkan cashback)
        $stmt_insert_detail->bind_param('iidssii', $pembayaran_id, $jenis_id, $jumlah, $bulan_val, $status_pembayaran, $angsuran_ke, $cashback);
        if (!$stmt_insert_detail->execute()) {
            throw new Exception('Gagal menambahkan pembayaran detail: ' . $stmt_insert_detail->error);
        }
    }

    $stmt_insert_detail->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diperbarui.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
