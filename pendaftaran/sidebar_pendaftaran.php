<!-- sidebar_pendaftaran.php -->

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
    <div class="sidebar-section-label">PROGRES PEMBAYARAN</div>
    <!-- HILANGKAN INPUT PEMBAYARAN -->
    <!--
    <a href="form_pendaftaran.php"
      class="nav-link <?= ($active=='form'?'active':'') ?>">
      <i class="fas fa-user-plus"></i>
      <span>Input Progres Pembayaran</span>
    </a>
    -->
    <a href="daftar_siswa.php"
      class="nav-link <?= ($active=='progres'?'active':'') ?>">
      <i class="fas fa-users"></i>
      <span>Progres Pembayaran</span>
    </a>
    <a href="laporan_detail.php"
      class="nav-link <?= ($active=='laporan'?'active':'') ?>">
      <i class="fas fa-file-alt"></i>
      <span>Detail Laporan</span>
    </a>
  </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
