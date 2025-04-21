<?php
// form_pendaftaran.php

session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil unit dari sesi login
$unit = $_SESSION['unit']; // Misalnya 'SMA' atau 'SMK'

// Fungsi untuk menampilkan pesan error jika ada
function display_errors() {
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . $_SESSION['error_message'] .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
        unset($_SESSION['error_message']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Pendaftaran Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/form_pendaftaran_styles.css">
</head>

<body>
    <div class="container mt-3">
        <div class="form-container shadow-lg p-4 rounded bg-white">
            <div class="form-header text-center mb-4">
                <h2>Form Pendaftaran Siswa Baru</h2>
                <p>Masukkan data lengkap calon siswa baru.</p>
            </div>

            <?php display_errors(); ?>

            <form action="proses_pendaftaran.php" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <!-- Unit (hidden input) -->
                <input type="hidden" name="unit" value="<?= htmlspecialchars($unit); ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="no_formulir" class="form-label">No Formulir</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="no_formulir" name="no_formulir" placeholder="Masukkan No Formulir" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan Nama Lengkap" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" placeholder="Masukkan Tempat Lahir" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-school"></i></span>
                            <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" placeholder="Masukkan Asal Sekolah" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="no_hp" class="form-label">No HP</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" placeholder="Masukkan Nomor HP" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Masukkan Alamat" required></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-primary btn-gradient"><i class="fas fa-paper-plane"></i> Daftar</button>
                    <button type="reset" class="btn btn-secondary btn-gradient"><i class="fas fa-sync-alt"></i> Reset</button>
                </div>
            </form>

            <!-- Tombol Kembali -->
            <div class="mt-4 text-center">
                <a href="dashboard_pendaftaran.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
