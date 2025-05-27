<!-- sidebar_pendaftaran.php -->
<nav class="sidebar">
  <div class="brand">SPMB <?= htmlspecialchars($unit) ?></div>
  <div class="nav flex-column">
    <a href="dashboard_pendaftaran.php"
      class="nav-link <?= ($active=='dashboard'?'active':'') ?>">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
    <a href="form_pendaftaran.php"
      class="nav-link <?= ($active=='form'?'active':'') ?>">
      <i class="fas fa-user-plus"></i>
      <span>Input Progress</span>
    </a>
    <a href="daftar_siswa.php"
      class="nav-link <?= ($active=='progres'?'active':'') ?>">
      <i class="fas fa-users"></i>
      <span>Progres Pembayaran</span>
    </a>
    <!-- Input Progres Pendaftaran -->
    <a href="input_progres_pendaftaran.php"
      class="nav-link <?= ($active=='inputpendaftaran'?'active':'') ?>">
      <i class="fas fa-user-edit"></i>
      <span>Input Progres Pendaftaran</span>
    </a>
    <!-- Progres Pendaftaran -->
    <a href="progres_pendaftaran.php"
      class="nav-link <?= ($active=='progrespendaftaran'?'active':'') ?>">
      <i class="fas fa-clipboard-list"></i>
      <span>Progres Pendaftaran</span>
    </a>
    <!--
<a href="cetak_laporan_pendaftaran.php"
  class="nav-link <?= ($active=='cetak'?'active':'') ?>">
  <i class="fas fa-file-alt"></i>
  <span>Cetak</span>
</a>
<a href="review_calon_pendaftar.php"
  class="nav-link <?= ($active=='review'?'active':'') ?>">
  <i class="fas fa-check-circle"></i>
  <span>Review</span>
</a>
-->

  </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
