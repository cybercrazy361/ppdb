<!-- sidebar_callcenter.php -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/sidebar_callcenter_styles.css">

<!-- Toggle button (mobile) -->
<button class="toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
  <i class="fas fa-bars"></i>
</button>

<nav class="sidebar" id="sidebarNav">
  <div class="brand">SPMB Call Center <?= htmlspecialchars($unit) ?></div>
  <div class="nav flex-column">
    <a href="dashboard_callcenter.php" class="nav-link <?= ($active=='dashboard'?'active':'') ?>">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
     <!-- LABEL CALON SISWA -->
   <!--  <div class="sidebar-section-label">Progres siswa</div>
   
<a href="input_progres_pendaftaran" class="nav-link <?= ($active=='progressiswa'?'active':'') ?>">
  <i class="fas fa-user-plus"></i>
  <span>Input Daftar Calon Siswa</span>
</a>
-->

    <!-- LABEL CALON SISWA -->
    <div class="sidebar-section-label">CALON SISWA</div>
    <a href="daftar_calon_siswa.php" class="nav-link <?= ($active=='calonsiswa'?'active':'') ?>">
      <i class="fas fa-user-graduate"></i>
      <span>Daftar Calon Siswa</span>
    </a>
    
  </div>
</nav>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebarNav');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  const sidebarToggle = document.getElementById('sidebarToggle');
  if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (sidebarBackdrop) sidebarBackdrop.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
    });
    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', function () {
        sidebar.classList.remove('open');
        sidebarBackdrop.style.display = 'none';
      });
    }
  }
});
</script>
