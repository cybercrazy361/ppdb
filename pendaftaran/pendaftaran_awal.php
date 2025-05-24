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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(120deg, #18c2ef 0%, #1879dc 100%);
            font-family: 'Poppins', sans-serif;
        }
        .auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 10px;}
        .auth-box {
            background: rgba(255,255,255,0.96); border-radius: 22px; max-width: 820px; width: 100%;
            box-shadow: 0 8px 40px 0 rgba(46, 89, 217, 0.13), 0 1.5px 4px 0 rgba(133, 135, 150, 0.05);
            display: flex; flex-direction: row; overflow: hidden; animation: fadeInUp 0.8s;
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(26px);} to { opacity: 1; transform: none;}}
        .info-panel {
            background: linear-gradient(120deg, #18c2ef 60%, #1879dc 100%); color: #fff; min-width: 270px; max-width: 330px;
            padding: 2.6rem 2rem 2.4rem 2rem; display: flex; flex-direction: column; justify-content: center; align-items: center;
        }
        .info-panel img { width: 82px; height: 82px; object-fit: contain; margin-bottom: 1.2rem; border-radius: 14px; box-shadow: 0 2px 15px rgba(24,121,220,0.11);}
        .info-panel h2 { font-size: 1.37rem; font-weight: 700; margin-bottom: 0.55rem; letter-spacing: 1.1px;}
        .info-panel p { font-size: 1.04rem; font-weight: 400; margin-bottom: 1.2rem;}
        .info-panel .btn-daftar {
            margin-top: 8px; font-size: 1.1rem; font-weight: 700; padding: 12px 32px;
            background: #fff; color: #1976d2; border-radius: 9px; box-shadow: 0 1px 8px rgba(30,98,170,0.11);
            text-decoration: none; border: none; transition: .18s;
        }
        .info-panel .btn-daftar:hover { background: #1879dc; color: #fff; }
        .form-panel { flex: 1; padding: 2.8rem 2.2rem 2.2rem 2.2rem;}
        .form-panel h3 { color: #1879dc; font-weight: 700; margin-bottom: .65rem; font-size: 1.29rem;}
        .form-panel p { font-size: 1.05rem; color: #425174; font-weight: 500; margin-bottom: 1.7rem;}
        .form-label { color: #24578a; font-weight: 600; font-size: 1.04rem; margin-bottom: 3px;}
        .form-control, .form-select { font-size: 1.03rem; border-radius: 9px; box-shadow: none; margin-bottom: 10px; transition: border-color 0.19s;}
        .form-control:focus, .form-select:focus { border-color: #18c2ef; box-shadow: 0 0 0 1.2px #b7e2f8;}
        .input-group-text.icon-field { background: #eaf5fd; color: #137cd8; border: none; border-radius: 9px 0 0 9px; font-size: 1.16rem;}
        .btn-submit {
            background: linear-gradient(90deg,#18c2ef 0%,#1879dc 100%); border: none; color: #fff;
            border-radius: 9px; font-weight: 700; font-size: 1.09rem; padding: 11px 0; margin-right: 8px;
            width: 49%; box-shadow: 0 2px 10px rgba(24,121,220,0.10); transition: .2s;
        }
        .btn-submit:hover { background: linear-gradient(90deg,#1879dc 0%, #12deae 100%); color: #fff; transform: translateY(-2px) scale(1.03);}
        .btn-reset {
            background: #e9f4fd; color: #2173ad; border: none; border-radius: 9px;
            font-weight: 700; font-size: 1.09rem; width: 49%; transition: .2s;
        }
        .btn-reset:hover { background: #c9e4fa; color: #074b7b; transform: translateY(-2px) scale(1.03);}
        .text-muted { font-size: .97rem; color: #687a92 !important;}
        @media (max-width: 950px) {
            .auth-box { flex-direction: column; max-width: 97vw;}
            .info-panel { max-width: 100vw; border-radius: 22px 22px 0 0; padding: 2.2rem 1.2rem 1.1rem 1.2rem;}
            .form-panel { border-radius: 0 0 22px 22px; padding: 2.2rem 1.2rem 1.2rem 1.2rem;}
        }
        @media (max-width: 500px) {
            .form-panel, .info-panel { padding: 1.15rem 2vw 1rem 2vw; }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-box">
            <!-- LEFT PANEL: Info & CTA -->
            <div class="info-panel text-center">
                <img src="../assets/images/logo_trans.png" alt="Logo YPDK" onerror="this.style.display='none'">
                <h2>SMA/SMK Dharma Karya</h2>
                <p>Sekolah Menengah berbasis Islami & berprestasi di Jakarta Selatan.<br>Terakreditasi "A" & fasilitas lengkap.</p>
                <div style="margin-top:18px; font-size:0.99rem; color:#eaf5fd99;">
                    <i class="fa fa-phone me-1"></i>
                    Hotline-Bu Puji: <b>0815-1151-9271</b>
                </div>
            </div>
            <!-- RIGHT PANEL: Formulir -->
            <div class="form-panel">
                <h3 id="form-daftar">Formulir Pendaftaran</h3>
                <p>Lengkapi data di bawah untuk mendaftar secara online.<br><span class="text-muted">Gratis biaya pendaftaran, kuota terbatas!</span></p>
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
                        <div class="col-12 col-md-6">
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
                        <div class="col-12 col-md-6">
                            <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
                            <div class="input-group">
                                <span class="input-group-text icon-field"><i class="fa-solid fa-school"></i></span>
                                <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" required placeholder="Masukkan asal sekolah">
                            </div>
                        </div>
                        <!-- EMAIL DIHAPUS -->
                        <div class="col-12 col-md-6">
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
                        <div class="col-12 col-md-6">
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
                        <div class="col-12 col-md-6">
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
    </div>
</body>
</html>
