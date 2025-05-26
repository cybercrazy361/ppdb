<!-- sidebar_pendaftaran.php -->
<nav class="sidebar">
  <div class="brand">PPDB <?=htmlspecialchars($unit)?></div>
  <a href="dashboard_pendaftaran.php" class="nav-link <?= ($active=='dashboard'?'active':'') ?>">
    <i class="fas fa-tachometer-alt"></i><span> Dashboard</span>
  </a>
  <a href="form_pendaftaran.php" class="nav-link <?= ($active=='form'?'active':'') ?>">
    <i class="fas fa-user-plus"></i><span> Input</span>
  </a>
  <a href="daftar_siswa.php" class="nav-link <?= ($active=='progres'?'active':'') ?>">
    <i class="fas fa-users"></i><span> Progres Pembayaran</span>
  </a>
  <a href="cetak_laporan_pendaftaran.php" class="nav-link <?= ($active=='cetak'?'active':'') ?>">
    <i class="fas fa-file-alt"></i><span> Cetak</span>
  </a>
  <a href="review_calon_pendaftar.php" class="nav-link <?= ($active=='review'?'active':'') ?>">
    <i class="fas fa-check-circle"></i><span> Review</span>
  </a>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
