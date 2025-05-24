<?php
// File: form_pendaftaran.php

session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil unit dari sesi login (SMA/SMK)
$unit = $_SESSION['unit'];

// Fungsi menampilkan error message
function display_errors() {
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . htmlspecialchars($_SESSION['error_message']) .
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
    <title>Form Pendaftaran Siswa Baru</title>
    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/form_pendaftaran_styles.css">
    <style>
        /* CARD CONTAINER LAYOUT SAMA DENGAN DASHBOARD */
        .card.form-card {
            max-width: 900px;
            margin: 2.5rem auto 0 auto;
            border-radius: 1.2rem;
            box-shadow: 0 8px 40px 0 rgba(46, 89, 217, 0.12), 0 1.5px 4px 0 rgba(133, 135, 150, 0.06);
            border: none;
            background: #fff;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
        }
        @media (max-width: 800px) {
            .card.form-card {
                padding: 1.3rem 0.5rem;
                max-width: 99vw;
            }
        }
        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2e59d9;
            text-align: center;
            margin-bottom: .2rem;
            letter-spacing: 0.01em;
        }
        .form-desc {
            font-size: 1.07rem;
            color: #6c757d;
            margin-bottom: 2rem;
            text-align: center;
        }
        .input-group-text {
            background: linear-gradient(135deg, #2e59d9 80%, #13afe0 100%);
            color: #fff;
            border: none;
            border-top-left-radius: 10px !important;
            border-bottom-left-radius: 10px !important;
            font-size: 1.15rem;
            min-width: 44px;
            display: flex;
            align-items: center;
        }
        .btn-gradient {
            background: linear-gradient(45deg, #2e59d9 70%, #13afe0 100%);
            color: #fff;
            border: none;
            padding: .7rem 2rem;
            border-radius: 1.2rem;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.01em;
            transition: transform .18s, box-shadow .18s, background .22s;
            box-shadow: 0 3px 14px rgba(46, 89, 217, 0.07);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-gradient:hover,
        .btn-gradient:focus {
            transform: translateY(-3px) scale(1.032);
            box-shadow: 0 6px 18px rgba(46, 89, 217, 0.19);
            background: linear-gradient(45deg, #1e388c 50%, #13afe0 100%);
        }
        .btn-outline-secondary {
            border-radius: 1.2rem;
            padding: .65rem 1.5rem;
            font-weight: 500;
            border: 1.6px solid #858796;
            color: #858796;
            background: #fff;
            transition: color .17s, border-color .17s, background .17s, box-shadow .17s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-outline-secondary:hover,
        .btn-outline-secondary:focus {
            border-color: #2e59d9;
            color: #2e59d9;
            background: #f4f7fc;
            box-shadow: 0 2px 8px rgba(46, 89, 217, 0.08);
        }
        .d-grid .btn,
        .d-flex .btn {
            min-width: 150px;
        }
        @media (max-width: 600px) {
            .form-title { font-size: 1.25rem;}
            .form-desc { font-size: 0.97rem;}
            .card.form-card { padding: 1.1rem 0.3rem; }
            .d-grid .btn, .d-flex .btn { width: 100%; min-width: unset; font-size: .97rem;}
        }
    </style>
</head>
<body style="background: linear-gradient(110deg, #f0f4ff 0%, #f5f8ff 30%, #e8eeff 100%);">

<div class="container py-4">
    <div class="card shadow-sm form-card">
        <div class="mb-4">
            <div class="form-title">Form Pendaftaran Siswa Baru</div>
            <div class="form-desc">Silakan isi data calon siswa dengan lengkap dan benar.</div>
            <?php display_errors(); ?>
        </div>
        <div class="mt-4 text-center">
            <a href="dashboard_pendaftaran.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
        <form action="proses_pendaftaran.php" method="POST" novalidate>
            <!-- CSRF Token & Unit -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="unit" value="<?= htmlspecialchars($unit) ?>">

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label for="no_formulir" class="form-label">No Formulir</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" id="no_formulir" name="no_formulir" class="form-control" placeholder="Contoh: 2025-SMA-001" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" id="nama" name="nama" class="form-control" placeholder="Nama calon siswa" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                        <select id="jenis_kelamin" name="jenis_kelamin" class="form-select" required>
                            <option value="">-- Pilih Jenis Kelamin --</option>
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" class="form-control" placeholder="Kota/Tempat Lahir" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-school"></i></span>
                        <input type="text" id="asal_sekolah" name="asal_sekolah" class="form-control" placeholder="Nama sekolah asal" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="no_hp" class="form-label">No HP Siswa</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" id="no_hp" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="no_hp_ortu" class="form-label">No HP Orang Tua</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
                        <input type="tel" id="no_hp_ortu" name="no_hp_ortu" class="form-control" placeholder="08xxxxxxxxxx" required>
                    </div>
                </div>
                <div class="col-12">
                    <label for="alamat" class="form-label">Alamat Lengkap</label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap siswa" required></textarea>
                </div>
            </div>

            <div class="row g-2 mt-4">
                <div class="col-12 col-md-6 d-grid">
                    <button type="reset" class="btn btn-outline-secondary py-3"><i class="fas fa-sync-alt"></i> Reset</button>
                </div>
                <div class="col-12 col-md-6 d-grid">
                    <button type="submit" class="btn btn-gradient py-3"><i class="fas fa-paper-plane"></i> Daftar Sekarang</button>
                </div>
            </div>
        </form>
        
    </div>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
