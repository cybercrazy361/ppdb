<?php
session_start();
// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Awal - Yayasan Pendidikan Dharma Karya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/sidebar_pendaftaran_styles.css">
  <link rel="stylesheet" href="../assets/css/input_progres_pendaftaran_styles.css">
</head>
<body>
    <?php $active = 'inputpendaftaran'; ?>
    <?php include 'sidebar_pendaftaran.php'; ?>
  
    <div class="main">
        <header class="navbar">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="title">Progres Pendaftaran Murid baru SMA/SMK</div>
            <div class="user-menu">
                <small>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? '') ?></small>
                <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
            </div>
        </header>
        <div class="container-form">
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
                        <button type="submit" class="btn btn-gradient flex-fill"><i class="fa-solid fa-paper-plane me-1"></i> Kirim</button>
                        <button type="reset" class="btn btn-outline-secondary flex-fill"><i class="fa-solid fa-rotate-left me-1"></i> Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
      // Hamburger sidebar toggle (optional, follow pattern sidebar_pendaftaran.js)
      document.addEventListener("DOMContentLoaded", function () {
          const sidebarToggle = document.getElementById('sidebarToggle');
          if (sidebarToggle) {
              sidebarToggle.addEventListener('click', function () {
                  document.querySelector('.sidebar').classList.toggle('active');
              });
          }
      });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar_pendaftaran.js"></script>
</body>
</html>
