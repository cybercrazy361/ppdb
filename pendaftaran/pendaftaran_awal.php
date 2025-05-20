<?php
// pendaftaran_awal.php
session_start();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Awal - Yayasan Pendidikan Dharma Karya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(120deg, #18c2ef 0%, #1879dc 100%);
            font-family: 'Poppins', sans-serif;
        }
        .header-glass {
            background: rgba(255,255,255,0.27);
            border-radius: 22px 22px 0 0;
            backdrop-filter: blur(6px);
            box-shadow: 0 4px 16px rgba(20,56,110,0.07);
            text-align: center;
            padding: 2.5rem 2rem 1.2rem 2rem;
            margin-bottom: 0;
            max-width: 610px;
            margin-left: auto;
            margin-right: auto;
        }
        .header-glass h1 {
            color: #1879dc;
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 0.6rem;
            letter-spacing: 1.5px;
        }
        .header-glass p {
            font-size: 1.07rem;
            color: #2d4263;
            font-weight: 500;
            margin-bottom: 0;
        }
        .form-card {
            background: rgba(255,255,255,0.95);
            border-radius: 0 0 20px 20px;
            padding: 2.7rem 2.1rem 2.1rem 2.1rem;
            max-width: 610px;
            margin: -16px auto 0 auto;
            box-shadow: 0 8px 28px rgba(24, 121, 220, 0.13);
            border: 1px solid #f1f5fa;
            animation: fadeInUp 0.8s;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(25px);}
            to   { opacity: 1; transform: none;}
        }
        .form-label {
            color: #2d4263;
            font-weight: 600;
            font-size: 1.06rem;
            margin-bottom: 2px;
        }
        .form-control, .form-select {
            font-size: 1.03rem;
            border-radius: 9px;
            box-shadow: none;
            margin-bottom: 10px;
            transition: border-color 0.19s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #18c2ef;
            box-shadow: 0 0 0 1.5px #b7e2f8;
        }
        .input-group-text.icon-field {
            background: #eaf5fd;
            color: #137cd8;
            border: none;
            border-radius: 9px 0 0 9px;
            font-size: 1.18rem;
        }
        .btn-submit {
            background: linear-gradient(90deg,#18c2ef 0%,#1879dc 100%);
            border: none;
            color: #fff;
            border-radius: 9px;
            font-weight: 700;
            font-size: 1.07rem;
            padding: 11px 0;
            transition: .2s;
            margin-right: 8px;
            width: 49%;
            box-shadow: 0 2px 10px rgba(24,121,220,0.10);
        }
        .btn-submit:hover {
            background: linear-gradient(90deg,#1879dc 0%, #12deae 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }
        .btn-reset {
            background: #e9f4fd;
            color: #2173ad;
            border: none;
            border-radius: 9px;
            font-weight: 700;
            font-size: 1.07rem;
            width: 49%;
            transition: .2s;
        }
        .btn-reset:hover {
            background: #c9e4fa;
            color: #074b7b;
            transform: translateY(-2px) scale(1.03);
        }
        .text-muted {
            font-size: .98rem;
            color: #687a92 !important;
        }
        @media (max-width: 720px) {
            .header-glass, .form-card { max-width: 98vw; padding: 1.6rem 5vw 1.1rem 5vw; }
            .form-card { margin: -14px auto 0 auto; }
            .header-glass h1 { font-size: 1.35rem;}
        }
        @media (max-width: 440px) {
            .header-glass, .form-card { padding: 1.1rem 3vw 1rem 3vw; }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- HEADER -->
        <div class="header-glass mb-0">
            <h1><i class="fa fa-user-plus me-2"></i>Selamat Datang Di Sistem Pendaftaran Online</h1>
            <p class="mt-3">Lengkapi data berikut untuk mendaftar di Yayasan Pendidikan Dharma Karya.</p>
        </div>
        <!-- FORM -->
        <div class="form-card">
            <form action="proses_calon_pendaftar.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-user"></i></span>
                            <input type="text" class="form-control" id="nama" name="nama" required placeholder="Masukkan nama lengkap">
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa-solid fa-venus-mars"></i></span>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="" selected disabled>-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa-solid fa-school"></i></span>
                            <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" required placeholder="Masukkan asal sekolah">
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="Masukkan email aktif">
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="no_hp" class="form-label">No HP Calon Peserta Didik</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-phone"></i></span>
                            <input type="tel" class="form-control" id="no_hp" name="no_hp" required pattern="[0-9]{10,13}" placeholder="0812xxxxxx">
                        </div>
                        <small class="text-muted ms-1">Format: Hanya angka (10-13 digit)</small>
                    </div>
                    <div class="col-12">
                        <label for="alamat" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="2" required placeholder="Masukkan alamat lengkap"></textarea>
                    </div>
                    <div class="col-12">
                        <label for="pendidikan_ortu" class="form-label">Pendidikan Orang Tua/Wali</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa-solid fa-graduation-cap"></i></span>
                            <select class="form-select" id="pendidikan_ortu" name="pendidikan_ortu" required>
                                <option value="" disabled selected>-- Pilih Pendidikan Terakhir --</option>
                                <option value="SD">SD/Sederajat</option>
                                <option value="SMP">SMP/Sederajat</option>
                                <option value="SMA">SMA/Sederajat</option>
                                <option value="D3">D3</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="no_hp_ortu" class="form-label">No HP Orang Tua/Wali</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-phone-volume"></i></span>
                            <input type="tel" class="form-control" id="no_hp_ortu" name="no_hp_ortu" required pattern="[0-9]{10,13}" placeholder="0812xxxxxx">
                        </div>
                        <small class="text-muted ms-1">Format: Hanya angka (10-13 digit)</small>
                    </div>
                    <div class="col-12">
                        <label for="pilihan" class="form-label">Pilih Sekolah</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-building-columns"></i></span>
                            <select class="form-select" id="pilihan" name="pilihan" required>
                                <option value="" disabled selected>-- Pilih Sekolah --</option>
                                <option value="SMA">SMA Dharma Karya</option>
                                <option value="SMK">SMK Dharma Karya</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4 gap-2">
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane me-1"></i> Kirim</button>
                    <button type="reset" class="btn-reset"><i class="fa-solid fa-rotate-left me-1"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
