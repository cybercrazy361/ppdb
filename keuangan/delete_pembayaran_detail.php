<?php
include '../database_connection.php';

header('Content-Type: application/json');

// Cek metode request
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
    exit;
}

// Mendapatkan ID pembayaran_detail dari parameter URL atau body
parse_str(file_get_contents("php://input"), $params);

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
    exit;
}

$pembayaran_detail_id = intval($_GET['id']);

// Mendapatkan pembayaran_id terkait untuk memperbarui jumlah_total di tabel pembayaran
$query_pembayaran_id = "SELECT pembayaran_id, jumlah FROM pembayaran_detail WHERE id = ?";
$stmt_pembayaran_id = $conn->prepare($query_pembayaran_id);
$stmt_pembayaran_id->bind_param('i', $pembayaran_detail_id);
$stmt_pembayaran_id->execute();
$result_pembayaran_id = $stmt_pembayaran_id->get_result();

if ($result_pembayaran_id->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Detail pembayaran tidak ditemukan.']);
    exit;
}

$row_pembayaran_id = $result_pembayaran_id->fetch_assoc();
$pembayaran_id = $row_pembayaran_id['pembayaran_id'];
$jumlah_detail = $row_pembayaran_id['jumlah'];

// Mulai transaksi
$conn->begin_transaction();
try {
    // Hapus pembayaran_detail
    $delete_query = "DELETE FROM pembayaran_detail WHERE id = ?";
    $stmt_delete = $conn->prepare($delete_query);
    $stmt_delete->bind_param('i', $pembayaran_detail_id);
    if (!$stmt_delete->execute()) {
        throw new Exception('Gagal menghapus detail pembayaran.');
    }

    // Kurangi jumlah_total di tabel pembayaran
    $update_pembayaran = "UPDATE pembayaran SET jumlah_total = jumlah_total - ? WHERE id = ?";
    $stmt_update_pembayaran = $conn->prepare($update_pembayaran);
    $stmt_update_pembayaran->bind_param('di', $jumlah_detail, $pembayaran_id);
    if (!$stmt_update_pembayaran->execute()) {
        throw new Exception('Gagal memperbarui jumlah_total pembayaran.');
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Detail pembayaran berhasil dihapus.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
