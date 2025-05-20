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
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #13bef1 0%, #0781e4 100%);
    font-family: 'Poppins', sans-serif;
}

.header-banner {
    width: 100%;
    background: linear-gradient(135deg, #3ac5fc 70%, #0b64c0 100%);
    border-radius: 24px 24px 0 0;
    margin: 24px auto 0 auto;
    padding: 32px 0 18px 0;
    text-align: center;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
    color: #0054a3;
    position: relative;
    max-width: 900px;
}

.header-banner h1 {
    font-weight: 800;
    font-size: 2.6rem;
    margin-bottom: 10px;
    color: #1879dc;
    letter-spacing: 2px;
}

.header-banner p {
    font-size: 1.14rem;
    color: #24292f;
    font-weight: 500;
    margin-bottom: 0;
}

.form-wrapper {
    margin: 0 auto;
    margin-top: 0;
    max-width: 900px;
    padding-bottom: 40px;
}

.form-container {
    background: #fff;
    border-radius: 0 0 18px 18px;
    padding: 36px 32px 32px 32px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.07);
    margin: 0;
    max-width: 100%;
}

@media (max-width: 768px) {
    .header-banner, .form-wrapper {
        max-width: 100%;
        border-radius: 0;
        margin: 0;
    }
    .form-container {
        padding: 22px 8px 18px 8px;
        border-radius: 0 0 14px 14px;
    }
    .header-banner h1 {
        font-size: 1.4rem;
    }
}

.form-label {
    color: #1a3a63;
    font-weight: 600;
    font-size: 1.04rem;
    margin-bottom: 2px;
}
.form-control, .form-select {
    font-size: 1.03rem;
    border-radius: 8px;
    margin-bottom: 18px;
}
.btn-submit {
    background: linear-gradient(90deg,#0cb6fa 0%,#1879dc 100%);
    border: none;
    color: #fff;
    border-radius: 8px;
    font-weight: 600;
    transition: .2s;
    margin-right: 8px;
}
.btn-submit:hover {
    background: #1879dc;
    color: #fff;
}
.btn-reset {
    background: #e1e7ee;
    border: none;
    color: #3b3b3b;
    border-radius: 8px;
    font-weight: 600;
    transition: .2s;
}
.btn-reset:hover {
    background: #b2c3d9;
    color: #222;
}

    </style>
</head>
<body>
    <div class="container py-5">
        <!-- HEADER -->
        <div class="header-glass mb-0">
            <h1><i class="fa fa-user-plus me-2"></i>Pendaftaran Awal</h1>
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
