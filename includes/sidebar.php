<?php
// sidebar.php

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nama halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <div class="brand">
    <img src="assets/logo-white.png" alt="Logo" />
    <button id="sidebarToggle" aria-expanded="true">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <div class="user-info">
    <h4><?php echo htmlspecialchars($_SESSION['nama']); ?></h4>
    <p><?php echo htmlspecialchars($_SESSION['unit']); ?></p>
  </div>

  <ul class="nav">
    <li class="nav-item">
      <a href="dashboard_keuangan.php"
         class="nav-link <?php echo ($current_page=='dashboard_keuangan.php')?'active':''?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="kelola_pembayaran.php"
         class="nav-link <?php echo ($current_page=='kelola_pembayaran.php')?'active':''?>">
        <i class="fas fa-wallet"></i>
        <span>Kelola Keuangan</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="daftar_siswa_keuangan.php"
         class="nav-link <?php echo ($current_page=='daftar_siswa_keuangan.php')?'active':''?>">
        <i class="fas fa-users"></i>
        <span>Daftar Siswa</span>
      </a>
    </li>
    <li class="nav-item has-submenu">
      <a href="#submenuSettingTagihan" data-bs-toggle="collapse"
         aria-expanded="<?php echo in_array($current_page,['setting_nominal.php','setting_jenis_pembayaran.php'])?'true':'false';?>"
         class="nav-link <?php echo in_array($current_page,['setting_nominal.php','setting_jenis_pembayaran.php'])?'active':''?>">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Setting Tagihan</span>
        <i class="fas fa-chevron-down submenu-icon"></i>
      </a>
      <ul class="collapse submenu <?php echo in_array($current_page,['setting_nominal.php','setting_jenis_pembayaran.php'])?'show':''?>" id="submenuSettingTagihan">
        <li>
          <a href="setting_nominal.php"
             class="nav-link <?php echo ($current_page=='setting_nominal.php')?'active':''?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Nominal</span>
          </a>
        </li>
        <li>
          <a href="setting_jenis_pembayaran.php"
             class="nav-link <?php echo ($current_page=='setting_jenis_pembayaran.php')?'active':''?>">
            <i class="fas fa-credit-card"></i>
            <span>Jenis Pembayaran</span>
          </a>
        </li>
      </ul>
    </li>
    <li class="nav-item">
      <a href="admin_receipt_layout.php"
         class="nav-link <?php echo ($current_page=='admin_receipt_layout.php')?'active':''?>">
        <i class="fas fa-th-large"></i>
        <span>Layout Kuitansi</span>
      </a>
    </li>
  </ul>

  <div class="sidebar-footer">
    <a href="../logout/logout_keuangan.php" class="nav-link logout">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </div>
</div>
