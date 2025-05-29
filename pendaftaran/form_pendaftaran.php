<?php
session_start();
include '../database_connection.php';

// Pastikan pengguna sudah login sebagai petugas pendaftaran
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi menampilkan error dari session
function display_errors() {
    if (!empty($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
            . htmlspecialchars($_SESSION['error_message']) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
        unset($_SESSION['error_message']);
    }
}

$unit = $_SESSION['unit']; // 'SMA' atau 'SMK'

// ==== Generate NO INVOICE AUTO ====
// Format: INV[MM][DD][YY][SEQ]
$bulan = date('m');    // 05
$tanggal = date('d');  // 29
$tahun = date('y');    // 25
$prefix = "REG{$bulan}{$tanggal}{$tahun}";

// Hitung urutan hari ini di tabel siswa
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM siswa WHERE DATE(created_at) = ?");
$stmt->bind_param('s', $today);
$stmt->execute();
$res = $stmt->get_result();
$urut = 1;
if ($row = $res->fetch_assoc()) {
    $urut = intval($row['total']) + 1;
}
$stmt->close();

// Hasil akhir: INV052925001 (tanpa spasi)
$no_invoice = $prefix . str_pad($urut, 3, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Form Pendaftaran Siswa Baru</title>
  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/sidebar_pendaftaran_styles.css"/>
  <link rel="stylesheet" href="../assets/css/form_pendaftaran_styles.css"/>
</head>
<body style="background: linear-gradient(110deg,#f0f4ff 0%,#f5f8ff 30%,#e8eeff 100%);">

<?php $active = 'form'; ?>
<?php include 'sidebar_pendaftaran.php'; ?>

<div class="main">
  <!-- Navbar Header -->
  <header class="navbar">
    <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
    <div class="title">Progres Pendaftaran Murid Baru <?= htmlspecialchars($unit) ?></div>
    <div class="user-menu">
      <small>Halo, <?= htmlspecialchars($_SESSION['nama']) ?></small>
      <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
    </div>
  </header>

  <div class="container py-1">
    <?php display_errors(); ?>
    <div class="card shadow-sm form-card">
      <form action="proses_pendaftaran.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="unit" value="<?= htmlspecialchars($unit) ?>">

        <div class="row g-4">
          <div class="col-12 col-md-6">
            <label for="no_invoice" class="form-label">No Registrasi</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-receipt"></i></span>
              <input type="text" id="no_invoice" name="no_invoice"
                     class="form-control" value="<?= htmlspecialchars($no_invoice) ?>"
                     readonly>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="nama" class="form-label">Nama Lengkap</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" id="nama" name="nama"
                     class="form-control" placeholder="Nama calon siswa"
                     required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
              <select id="jenis_kelamin" name="jenis_kelamin"
                      class="form-select" required>
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
              <input type="text" id="tempat_lahir" name="tempat_lahir"
                     class="form-control" placeholder="Kota/Tempat Lahir"
                     required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
              <input type="date" id="tanggal_lahir" name="tanggal_lahir"
                     class="form-control" required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-school"></i></span>
              <input type="text" id="asal_sekolah" name="asal_sekolah"
                     class="form-control" placeholder="Nama sekolah asal"
                     required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="no_hp" class="form-label">No HP Siswa</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-phone"></i></span>
              <input type="tel" id="no_hp" name="no_hp"
                     class="form-control" placeholder="08xxxxxxxxxx"
                     required>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label for="no_hp_ortu" class="form-label">No HP Orang Tua</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
              <input type="tel" id="no_hp_ortu" name="no_hp_ortu"
                     class="form-control" placeholder="08xxxxxxxxxx"
                     required>
            </div>
          </div>

          <div class="col-12">
            <label for="alamat" class="form-label">Alamat Lengkap</label>
            <textarea id="alamat" name="alamat"
                      class="form-control" rows="3"
                      placeholder="Alamat lengkap siswa"
                      required></textarea>
          </div>
        </div>

        <div class="row g-2 mt-4">
          <div class="col-12 col-md-6 d-grid">
            <button type="reset"
                    class="btn btn-outline-secondary py-3">
              <i class="fas fa-sync-alt"></i> Reset
            </button>
          </div>
          <div class="col-12 col-md-6 d-grid">
            <button type="submit"
                    class="btn btn-gradient py-3">
              <i class="fas fa-paper-plane"></i> Daftar Sekarang
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS & sidebar toggle script -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar_pendaftaran.js"></script>
</body>
</html>
