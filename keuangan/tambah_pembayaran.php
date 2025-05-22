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

$no_formulir = isset($_POST['no_formulir']) ? sanitize($_POST['no_formulir']) : '';
$nama = isset($_POST['nama']) ? sanitize($_POST['nama']) : '';
$tahun_pelajaran = isset($_POST['tahun_pelajaran']) ? sanitize($_POST['tahun_pelajaran']) : '';
$metode_pembayaran = isset($_POST['metode_pembayaran']) ? sanitize($_POST['metode_pembayaran']) : '';
$keterangan = isset($_POST['keterangan']) ? sanitize($_POST['keterangan']) : '';

$jenis_pembayaran = isset($_POST['jenis_pembayaran']) && is_array($_POST['jenis_pembayaran']) ? $_POST['jenis_pembayaran'] : [];
$jumlah_pembayaran = isset($_POST['jumlah']) && is_array($_POST['jumlah']) ? $_POST['jumlah'] : [];
$bulan_pembayaran = isset($_POST['bulan']) && is_array($_POST['bulan']) ? $_POST['bulan'] : [];
$cashback_pembayaran = isset($_POST['cashback']) && is_array($_POST['cashback']) ? $_POST['cashback'] : [];

$errors = [];

if ($no_formulir === '') $errors[] = 'No Formulir harus diisi.';
if ($nama === '') $errors[] = 'Nama siswa tidak valid.';
if ($tahun_pelajaran === '') $errors[] = 'Tahun Pelajaran harus diisi.';
if ($metode_pembayaran === '') $errors[] = 'Metode Pembayaran harus dipilih.';
if (count($jenis_pembayaran) === 0) $errors[] = 'Setidaknya satu Jenis Pembayaran harus dipilih.';

$total_jumlah = 0;

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

    // Cek jenis pembayaran dan unit
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

        // Validasi cashback minimal 0
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
        $errors[] = "Error preparing nominal statement: " . $conn->error;
        continue;
    }
    $stmt_nominal->bind_param('i', $jenis_id);
    $stmt_nominal->execute();
    $res_nominal = $stmt_nominal->get_result();
    if ($res_nominal->num_rows > 0) {
        $nominal_max = floatval($res_nominal->fetch_assoc()['nominal_max']);
        if ($jumlah > $nominal_max) {
            $errors[] = "Jumlah Pembayaran pada item " . ($i + 1) . " melebihi nominal maksimum untuk jenis pembayaran tersebut.";
        }
    } else {
        $errors[] = "Pengaturan nominal tidak ditemukan untuk Jenis Pembayaran pada item " . ($i + 1) . ".";
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

// Proses penyimpanan data
$conn->begin_transaction();

try {
    // Ambil siswa_id dan pastikan siswa sesuai dengan unit petugas
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
    $stmt_pembayaran = $conn->prepare("INSERT INTO pembayaran (siswa_id, no_formulir, jumlah, metode_pembayaran, tahun_pelajaran, tanggal_pembayaran, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_pembayaran) {
        throw new Exception("Error preparing pembayaran statement: " . $conn->error);
    }
    $stmt_pembayaran->bind_param('isdssss', $siswa_id, $no_formulir, $total_jumlah, $metode_pembayaran, $tahun_pelajaran, $tanggal_pembayaran, $keterangan);
    if (!$stmt_pembayaran->execute()) {
        throw new Exception('Gagal menambahkan pembayaran: ' . $stmt_pembayaran->error);
    }
    $pembayaran_id = $stmt_pembayaran->insert_id;
    $stmt_pembayaran->close();

    // Siapkan query insert pembayaran_detail, termasuk cashback
    $stmt_detail = $conn->prepare("INSERT INTO pembayaran_detail (pembayaran_id, jenis_pembayaran_id, jumlah, bulan, status_pembayaran, angsuran_ke, cashback) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt_detail) {
        throw new Exception("Error preparing pembayaran_detail statement: " . $conn->error);
    }

    for ($i = 0; $i < count($jenis_pembayaran); $i++) {
        $jenis_id = $jenis_pembayaran[$i];
        $jumlah = floatval(str_replace('.', '', $jumlah_pembayaran[$i]));
        $bulan_val = isset($bulan_pembayaran[$i]) && $bulan_pembayaran[$i] !== '' ? $bulan_pembayaran[$i] : null;
        $cashback = isset($cashback_pembayaran[$i]) && $cashback_pembayaran[$i] !== '' ? intval(str_replace('.', '', $cashback_pembayaran[$i])) : 0;

        // Ambil nominal_max
        $stmt_nom = $conn->prepare("SELECT nominal_max FROM pengaturan_nominal WHERE jenis_pembayaran_id = ?");
        if (!$stmt_nom) {
            throw new Exception("Error preparing nominal_max statement: " . $conn->error);
        }
        $stmt_nom->bind_param('i', $jenis_id);
        $stmt_nom->execute();
        $res_nom = $stmt_nom->get_result();
        $nominal_max = 0;
        if ($res_nom->num_rows > 0) {
            $nominal_max = floatval($res_nom->fetch_assoc()['nominal_max']);
        }
        $stmt_nom->close();

        // Hitung total_sebelum dan jumlah angsuran sebelumnya
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
                    ");
                    if (!$stmt_prev) {
                        throw new Exception("Error preparing previous bulan statement: " . $conn->error);
                    }
                    $stmt_prev->bind_param('iiss', $siswa_id, $jenis_id, $prev_bulan, $tahun_pelajaran);
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

            $stmt_sum = $conn->prepare("
                SELECT COALESCE(SUM(pd.jumlah), 0) AS total_sebelumnya, COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.siswa_id = ?
                  AND pd.jenis_pembayaran_id = ?
                  AND pd.bulan = ?
                  AND p.tahun_pelajaran = ?
            ");
            if (!$stmt_sum) {
                throw new Exception("Error preparing sum statement: " . $conn->error);
            }
            $stmt_sum->bind_param('iiss', $siswa_id, $jenis_id, $bulan_val, $tahun_pelajaran);
        } else {
            $stmt_sum = $conn->prepare("
                SELECT COALESCE(SUM(pd.jumlah), 0) AS total_sebelumnya, COUNT(*) AS angsuran_count
                FROM pembayaran_detail pd
                INNER JOIN pembayaran p ON p.id = pd.pembayaran_id
                WHERE p.siswa_id = ?
                  AND pd.jenis_pembayaran_id = ?
                  AND pd.bulan IS NULL
                  AND p.tahun_pelajaran = ?
            ");
            if (!$stmt_sum) {
                throw new Exception("Error preparing sum statement: " . $conn->error);
            }
            $stmt_sum->bind_param('iis', $siswa_id, $jenis_id, $tahun_pelajaran);
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
                "Pembayaran pada item " . ($i+1) . " melebihi sisa yang harus dibayar. ".
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
            $status_pembayaran = 'Angsuran ke-'.$angsuran_ke;
        }

        // Simpan detail dengan cashback
        $stmt_detail->bind_param('iidssii', $pembayaran_id, $jenis_id, $jumlah, $bulan_val, $status_pembayaran, $angsuran_ke, $cashback);
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
