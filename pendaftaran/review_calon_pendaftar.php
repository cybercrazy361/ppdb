<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Ambil username dari sesi
$username = $_SESSION['username'];

// Ambil unit dari petugas yang sedang login
$stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($unit);
$stmt->fetch();
$stmt->close();

// Tentukan kondisi filter berdasarkan unit
if ($unit == 'Yayasan') {
    // Yayasan dapat melihat semua calon pendaftar
    $query = "SELECT * FROM calon_pendaftar ORDER BY id DESC";
    $stmt = $conn->prepare($query);
} else {
    // Petugas SMA atau SMK hanya melihat pendaftar dengan pilihan yang sesuai
    $query = "SELECT * FROM calon_pendaftar WHERE pilihan = ? ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $unit);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Calon Pendaftar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #007bff, #6c757d);
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            color: #333;
        }

        h4 {
            text-align: center;
            color: #007bff;
            font-weight: bold;
        }

        .table {
            margin-top: 20px;
        }

        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table td {
            text-align: center;
        }

        .btn-back {
            background-color: #6c757d;
            color: white;
            border-radius: 8px;
            padding: 10px 20px;
            text-decoration: none;
            transition: 0.3s ease;
        }

        .btn-back:hover {
            background-color: #5a6268;
        }
    </style>
</head>

<body>
    <div class="container">
        <h4>Review Calon Pendaftar</h4>
        <a href="dashboard_pendaftaran.php" class="btn-back mb-3 d-inline-block">Kembali ke Dashboard</a>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Asal Sekolah</th>
                    <th>Email</th>
                    <th>No HP</th>
                    <th>Alamat</th>
                    <th>Pilihan Sekolah</th>
                    <th>Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($no++) ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['no_hp']) ?></td>
                            <td><?= htmlspecialchars($row['alamat']) ?></td>
                            <td><?= htmlspecialchars($row['pilihan']) ?></td>
                            <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Belum ada data calon pendaftar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
