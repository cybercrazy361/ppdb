<?php
include '../database_connection.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['pembayaran_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID pembayaran tidak ditemukan.']);
    exit;
}

$pembayaran_id = intval($data['pembayaran_id']);
$jumlah_total = 0;

// Validasi dan sanitasi input
$jenis_pembayaran = $data['jenis_pembayaran'] ?? [];
$jumlah = $data['jumlah'] ?? [];
$metode_pembayaran = trim($data['metode_pembayaran'] ?? '');
$keterangan = trim($data['keterangan'] ?? '');

// Hitung total jumlah
foreach ($jumlah as $jml) {
    $jumlah_total += floatval($jml);
}

// Validasi
if ($jumlah_total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total pembayaran harus lebih dari 0.']);
    exit;
}

if (empty($metode_pembayaran)) {
    echo json_encode(['success' => false, 'message' => 'Metode pembayaran harus dipilih.']);
    exit;
}

if (empty($jenis_pembayaran) || empty($jumlah)) {
    echo json_encode(['success' => false, 'message' => 'Setidaknya satu jenis pembayaran harus ditambahkan.']);
    exit;
}

// Validasi setiap detail pembayaran
foreach ($jenis_pembayaran as $index => $jenis) {
    $jml = floatval($jumlah[$index]);
    if (empty($jenis)) {
        echo json_encode(['success' => false, 'message' => "Jenis pembayaran pada item ".($index + 1)." harus diisi."]);
        exit;
    }
    if ($jml <= 0) {
        echo json_encode(['success' => false, 'message' => "Jumlah pembayaran pada item ".($index + 1)." harus lebih dari 0."]);
        exit;
    }
}

// Mulai transaksi
$conn->begin_transaction();
try {
    // Update tabel pembayaran
    $update_pembayaran = "UPDATE pembayaran 
                           SET jumlah = ?, metode_pembayaran = ?, keterangan = ?
                           WHERE id = ?";
    $stmt_update_pembayaran = $conn->prepare($update_pembayaran);
    if (!$stmt_update_pembayaran) {
        throw new Exception('Gagal mempersiapkan query pembayaran.');
    }

    $stmt_update_pembayaran->bind_param(
        'dssi',
        $jumlah_total,
        $metode_pembayaran,
        $keterangan,
        $pembayaran_id
    );

    if (!$stmt_update_pembayaran->execute()) {
        throw new Exception('Gagal memperbarui pembayaran.');
    }

    // Hapus detail pembayaran lama
    $delete_detail = "DELETE FROM pembayaran_detail WHERE pembayaran_id = ?";
    $stmt_delete_detail = $conn->prepare($delete_detail);
    if (!$stmt_delete_detail) {
        throw new Exception('Gagal mempersiapkan query penghapusan detail pembayaran.');
    }
    $stmt_delete_detail->bind_param('i', $pembayaran_id);
    if (!$stmt_delete_detail->execute()) {
        throw new Exception('Gagal menghapus detail pembayaran lama.');
    }

    // Insert detail pembayaran baru
    $insert_detail = "INSERT INTO pembayaran_detail 
                      (pembayaran_id, jenis_pembayaran, jumlah) 
                      VALUES (?, ?, ?)";
    $stmt_insert_detail = $conn->prepare($insert_detail);
    if (!$stmt_insert_detail) {
        throw new Exception('Gagal mempersiapkan query pembayaran detail.');
    }

    foreach ($jenis_pembayaran as $index => $jenis) {
        $jml = floatval($jumlah[$index]);
        $stmt_insert_detail->bind_param(
            'isd',
            $pembayaran_id,
            $jenis,
            $jml
        );

        if (!$stmt_insert_detail->execute()) {
            throw new Exception('Gagal menambahkan pembayaran detail.');
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil diperbarui.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Tutup semua statement untuk membebaskan sumber daya
$stmt_update_pembayaran->close();
$stmt_delete_detail->close();
$stmt_insert_detail->close();
$conn->close();
?>
