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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
            font-family: 'Poppins', sans-serif;
            color: #333;
        }
        .header-glass {
            background: rgba(255,255,255,0.2);
            border-radius: 20px 20px 0 0;
            backdrop-filter: blur(4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            text-align: center;
            padding: 2.5rem 2rem 1rem 2rem;
        }
        .header-glass h1 {
            color: #0d6efd;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .form-card {
            background: rgba(255,255,255,0.85);
            border-radius: 0 0 18px 18px;
            padding: 2.5rem 2rem;
            max-width: 600px;
            margin: -40px auto 0 auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.09);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            border-radius: 10px;
            box-shadow: none;
            font-size: 1rem;
        }
        .input-group-text {
            background: #e9ecef;
            border: none;
            border-radius: 10px 0 0 10px;
        }
        .btn-submit {
            background: linear-gradient(90deg, #0072ff 0%, #00c6ff 100%);
            border: none;
            padding: 12px 0;
            color: white;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: 0.2s;
            width: 48%;
            box-shadow: 0 2px 10px rgba(0,123,255,0.09);
        }
        .btn-submit:hover {
            background: linear-gradient(90deg, #0056b3 0%, #43e97b 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }
        .btn-reset {
            background: #adb5bd;
            color: #fff;
            border: none;
            padding: 12px 0;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 600;
            width: 48%;
            transition: 0.2s;
        }
        .btn-reset:hover {
            background: #495057;
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }
        @media (max-width: 650px) {
            .header-glass, .form-card { padding: 1.5rem 0.7rem; }
            .form-card { margin: -30px auto 0 auto; }
            .btn-submit, .btn-reset { font-size: 15px; }
        }
        .icon-field {
            width: 42px; display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="header-glass mb-0">
            <h1><i class="fa-solid fa-user-plus"></i> Pendaftaran Awal</h1>
            <p>Lengkapi data berikut untuk mendaftar di Yayasan Pendidikan Dharma Karya.</p>
        </div>
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
                    <div class="col-md-12">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="Masukkan email aktif">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label for="no_hp" class="form-label">No HP Calon Peserta Didik</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-phone"></i></span>
                            <input type="tel" class="form-control" id="no_hp" name="no_hp" required pattern="[0-9]{10,13}" placeholder="0812xxxxxx">
                        </div>
                        <small class="text-muted ms-1">Format: Hanya angka (10-13 digit)</small>
                    </div>
                    <div class="col-md-12">
                        <label for="alamat" class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="2" required placeholder="Masukkan alamat lengkap"></textarea>
                    </div>
                    <div class="col-md-12">
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
                    <div class="col-md-12">
                        <label for="no_hp_ortu" class="form-label">No HP Orang Tua/Wali</label>
                        <div class="input-group">
                            <span class="input-group-text icon-field"><i class="fa fa-phone-volume"></i></span>
                            <input type="tel" class="form-control" id="no_hp_ortu" name="no_hp_ortu" required pattern="[0-9]{10,13}" placeholder="0812xxxxxx">
                        </div>
                        <small class="text-muted ms-1">Format: Hanya angka (10-13 digit)</small>
                    </div>
                    <div class="col-md-12">
                        <label for="pilihan" class="form-label">Pilih Sekolah</label>
                        <select class="form-select" id="pilihan" name="pilihan" required>
                            <option value="" disabled selected>-- Pilih Sekolah --</option>
                            <option value="SMA">SMA Dharma Karya</option>
                            <option value="SMK">SMK Dharma Karya</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4 gap-2">
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Kirim</button>
                    <button type="reset" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
