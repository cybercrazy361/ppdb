<!-- sidebar_pendaftaran.php -->
<!-- Pastikan sudah include FontAwesome dan CSS sidebar ini -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="sidebar_pendaftaran_styles.css">

<!-- Toggle button (mobile) -->
<button class="toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
  <i class="fas fa-bars"></i>
</button>

<nav class="sidebar" id="sidebarNav">
  <div class="brand">SPMB <?= htmlspecialchars($unit) ?></div>
  <div class="nav flex-column">
    <a href="dashboard_pendaftaran.php"
      class="nav-link <?= ($active=='dashboard'?'active':'') ?>">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
    <!-- LABEL PEMBAYARAN -->
    <div class="sidebar-section-label">PROGRES PEMBAYARAN</div>
    <a href="form_pendaftaran.php"
      class="nav-link <?= ($active=='form'?'active':'') ?>">
      <i class="fas fa-user-plus"></i>
      <span>Input Progres Pembayaran</span>
    </a>
    <a href="daftar_siswa.php"
      class="nav-link <?= ($active=='progres'?'active':'') ?>">
      <i class="fas fa-users"></i>
      <span>Progres Pembayaran</span>
    </a>
    
    <!-- Untuk kebutuhan lain tinggal tambahkan menu di sini -->
  </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
