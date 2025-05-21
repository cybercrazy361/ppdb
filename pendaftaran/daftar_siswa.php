<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil unit dari sesi login
$unit = $_SESSION['unit']; // Misalnya SMA atau SMK

// Pagination settings
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page > 1) ? ($page * $limit) - $limit : 0;

// Total data siswa untuk unit yang sesuai
$totalQuery = "SELECT COUNT(*) AS total FROM siswa WHERE unit = ?";
$stmtTotal = $conn->prepare($totalQuery);
$stmtTotal->bind_param('s', $unit);
$stmtTotal->execute();
$totalResult = $stmtTotal->get_result();
$totalSiswa = $totalResult->fetch_assoc()['total'];
$stmtTotal->close();

// Query untuk mengambil data siswa dengan status pembayaran Uang Pangkal dan SPP Juli
$query = "
    SELECT 
        s.*, 
        COALESCE(
            (SELECT p.metode_pembayaran 
             FROM pembayaran p 
             WHERE p.siswa_id = s.id 
             ORDER BY p.tanggal_pembayaran DESC 
             LIMIT 1),
            'Belum Ada'
        ) AS metode_pembayaran,
        CASE
            WHEN 
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = s.id 
                    AND pd1.jenis_pembayaran = 'Uang Pangkal' 
                    AND pd1.status_pembayaran = 'Lunas'
                ) > 0
            AND
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = s.id 
                    AND pd2.jenis_pembayaran = 'SPP Juli' 
                    AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            THEN 'Lunas'
            WHEN 
                (
                    (SELECT COUNT(*) FROM pembayaran_detail pd1 
                        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                        WHERE p1.siswa_id = s.id 
                        AND pd1.jenis_pembayaran = 'Uang Pangkal' 
                        AND pd1.status_pembayaran = 'Lunas'
                    ) > 0
                    OR
                    (SELECT COUNT(*) FROM pembayaran_detail pd2 
                        JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                        WHERE p2.siswa_id = s.id 
                        AND pd2.jenis_pembayaran = 'SPP Juli' 
                        AND pd2.status_pembayaran = 'Lunas'
                    ) > 0
                )
            THEN 'Angsuran'
            ELSE 'Belum Bayar'
        END AS status_pembayaran
    FROM siswa s
    WHERE s.unit = ?
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('sii', $unit, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Hitung total halaman
$totalPages = ceil($totalSiswa / $limit);

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if ($tanggal == '0000-00-00' || $tanggal == null) {
        return '-';
    }
    $bulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $date = date('d', strtotime($tanggal));
    $month = $bulan[date('F', strtotime($tanggal))];
    $year = date('Y', strtotime($tanggal));

    return "$date $month $year";
}

// Fungsi untuk mengganti status pembayaran menjadi label dan ikon
function getStatusPembayaranLabel($status) {
    switch (strtolower($status)) {
        case 'lunas':
            return '<i class="fas fa-check-circle text-success" title="Lunas"></i> Lunas';
        case 'angsuran':
            return '<i class="fas fa-exclamation-circle text-warning" title="Angsuran"></i> Angsuran';
        case 'belum bayar':
        default:
            return '<i class="fas fa-times-circle text-danger" title="Belum Membayar"></i> Belum Bayar';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/daftar_siswa_styles.css">
</head>

<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Daftar Siswa <?= htmlspecialchars($unit); ?></h2>

        <!-- Menampilkan Pesan Sukses atau Error -->
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . htmlspecialchars($_SESSION['success_message']) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            unset($_SESSION['success_message']);
        }

        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                . htmlspecialchars($_SESSION['error_message']) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            unset($_SESSION['error_message']);
        }

        if (isset($_SESSION['edit_errors'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                . implode('<br>', array_map('htmlspecialchars', $_SESSION['edit_errors'])) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            unset($_SESSION['edit_errors']);
        }

        if (isset($_SESSION['delete_errors'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                . implode('<br>', array_map('htmlspecialchars', $_SESSION['delete_errors'])) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
            unset($_SESSION['delete_errors']);
        }
        ?>

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>No Formulir</th>
                        <th>Nama</th>
                        <th>Jenis Kelamin</th>
                        <th>Tempat/Tanggal Lahir</th>
                        <th>Asal Sekolah</th>
                        <th>Alamat</th>
                        <th>No HP</th>
                        <th>Status Pembayaran</th>
                        <th>Metode Pembayaran</th>
                        <th>Tanggal Pendaftaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        $no = $offset + 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $no++ . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_formulir']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['jenis_kelamin']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tempat_lahir']) . ", " . formatTanggalIndonesia($row['tanggal_lahir']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['asal_sekolah']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['alamat']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_hp']) . "</td>";
                            echo "<td class='text-center'>" . getStatusPembayaranLabel($row['status_pembayaran']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
                            echo "<td>" . formatTanggalIndonesia($row['tanggal_pendaftaran']) . "</td>";
                            echo "<td class='text-center'>
                                    <button class='btn btn-warning btn-sm editBtn' 
                                            data-id='" . htmlspecialchars($row['id']) . "' 
                                            data-nama='" . htmlspecialchars($row['nama']) . "' 
                                            data-jenis_kelamin='" . htmlspecialchars($row['jenis_kelamin']) . "' 
                                            data-tempat_lahir='" . htmlspecialchars($row['tempat_lahir']) . "' 
                                            data-tanggal_lahir='" . htmlspecialchars($row['tanggal_lahir']) . "' 
                                            data-asal_sekolah='" . htmlspecialchars($row['asal_sekolah']) . "' 
                                            data-alamat='" . htmlspecialchars($row['alamat']) . "' 
                                            data-no_hp='" . htmlspecialchars($row['no_hp']) . "' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#editModal'>Edit</button>
                                    <button class='btn btn-danger btn-sm deleteBtn' 
                                            data-id='" . htmlspecialchars($row['id']) . "' 
                                            data-nama='" . htmlspecialchars($row['nama']) . "' 
                                            data-bs-toggle='modal' 
                                            data-bs-target='#deleteModal'>Delete</button>
                                </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='12' class='text-center'>Tidak ada data siswa.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1">Previous</a>
                </li>
                <?php
                // Tentukan rentang halaman yang ditampilkan
                $max_links = 5; // Maksimal jumlah link halaman yang ditampilkan
                $start_page = max(1, $page - floor($max_links / 2));
                $end_page = min($totalPages, $start_page + $max_links - 1);

                // Jika tidak cukup halaman di akhir, geser ke kiri
                if (($end_page - $start_page) < ($max_links - 1)) {
                    $start_page = max(1, $end_page - $max_links + 1);
                }

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                </li>
            </ul>
            <div class="mb-3">
                <a href="dashboard_pendaftaran.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
        </nav>
    </div>


    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="edit_siswa.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Data Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editId" name="id">
                        <div class="mb-3">
                            <label for="editNama" class="form-label">Nama</label>
                            <input type="text" class="form-control" id="editNama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="editJenisKelamin" class="form-label">Jenis Kelamin</label>
                            <select class="form-select" id="editJenisKelamin" name="jenis_kelamin" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editTempatLahir" class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" id="editTempatLahir" name="tempat_lahir" required>
                        </div>
                        <div class="mb-3">
                            <label for="editTanggalLahir" class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" id="editTanggalLahir" name="tanggal_lahir" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAsalSekolah" class="form-label">Asal Sekolah</label>
                            <input type="text" class="form-control" id="editAsalSekolah" name="asal_sekolah" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAlamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="editAlamat" name="alamat" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editNoHp" class="form-label">No HP</label>
                            <input type="text" class="form-control" id="editNoHp" name="no_hp" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="delete_siswa.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Hapus Data Siswa</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus data <b id="deleteNama"></b>?</p>
                        <input type="hidden" id="deleteId" name="id">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Edit button event listener
        document.querySelectorAll('.editBtn').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('editId').value = button.getAttribute('data-id');
                document.getElementById('editNama').value = button.getAttribute('data-nama');
                document.getElementById('editJenisKelamin').value = button.getAttribute('data-jenis_kelamin');
                document.getElementById('editTempatLahir').value = button.getAttribute('data-tempat_lahir');
                document.getElementById('editTanggalLahir').value = button.getAttribute('data-tanggal_lahir');
                document.getElementById('editAsalSekolah').value = button.getAttribute('data-asal_sekolah');
                document.getElementById('editAlamat').value = button.getAttribute('data-alamat');
                document.getElementById('editNoHp').value = button.getAttribute('data-no_hp');
            });
        });

        // Delete button event listener
        document.querySelectorAll('.deleteBtn').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('deleteId').value = button.getAttribute('data-id');
                document.getElementById('deleteNama').innerText = button.getAttribute('data-nama');
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
