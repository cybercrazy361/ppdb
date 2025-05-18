<!-- File: sidebar.php -->
<?php
// Mendapatkan nama halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
  <div class="sidebar-header">
    <a href="dashboard_keuangan.php" class="brand">
      <img src="../assets/img/logo.png" alt="Logo" class="brand-logo">
      <span class="brand-name">Sistem Keuangan</span>
    </a>
    <button id="sidebarToggle" class="toggle-btn">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <div class="user-panel">
    <div class="image">
      <img src="../assets/img/avatar.png" alt="User Image" class="user-img">
    </div>
    <div class="info">
      <p><?php echo htmlspecialchars($_SESSION['nama']); ?></p>
      <small><?php echo htmlspecialchars($_SESSION['unit']); ?></small>
    </div>
  </div>

  <ul class="nav-menu">
    <li class="nav-item">
      <a href="dashboard_keuangan.php" class="nav-link <?php echo $current_page=='dashboard_keuangan.php'?'active':'' ?>">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="kelola_pembayaran.php" class="nav-link <?php echo $current_page=='kelola_pembayaran.php'?'active':'' ?>">
        <i class="fas fa-wallet"></i><span>Kelola Keuangan</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="daftar_siswa_keuangan.php" class="nav-link <?php echo $current_page=='daftar_siswa_keuangan.php'?'active':'' ?>">
        <i class="fas fa-users"></i><span>Daftar Siswa</span>
      </a>
    </li>
    <li class="nav-item has-submenu <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php'])?'open':'' ?>">
      <a href="#submenuTagihan" data-bs-toggle="collapse" class="nav-link">
        <i class="fas fa-file-invoice-dollar"></i><span>Setting Tagihan</span><i class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <ul id="submenuTagihan" class="collapse submenu-list <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php'])?'show':'' ?>">
        <li><a href="setting_nominal.php" class="nav-link <?php echo $current_page=='setting_nominal.php'?'active':'' ?>"><i class="fas fa-money-bill-wave"></i><span>Nominal</span></a></li>
        <li><a href="setting_jenis_pembayaran.php" class="nav-link <?php echo $current_page=='setting_jenis_pembayaran.php'?'active':'' ?>"><i class="fas fa-credit-card"></i><span>Jenis Pembayaran</span></a></li>
      </ul>
    </li>
    <li class="nav-item">
      <a href="admin_receipt_layout.php" class="nav-link <?php echo $current_page=='admin_receipt_layout.php'?'active':'' ?>">
        <i class="fas fa-th-large"></i><span>Layout Kuitansi</span>
      </a>
    </li>
    <li class="nav-item logout">
      <a href="../logout/logout_keuangan.php" class="nav-link">
        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
      </a>
    </li>
  </ul>
</nav>
