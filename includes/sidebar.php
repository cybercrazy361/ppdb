<?php
// sidebar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nama halaman saat ini untuk penandaan active
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-header">
        <div class="user-panel">
      <?php if (!empty($_SESSION['profile_pic'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['profile_pic']); ?>" alt="Avatar" class="avatar">
      <?php else: ?>
        <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
      <?php endif; ?>
      <div class="user-info">
        <h4><?= htmlspecialchars($_SESSION['nama']); ?></h4>
        <p><?= htmlspecialchars($_SESSION['unit']); ?></p>
      </div>
    </div>
  </div>

  <nav class="menu">
    <ul>
      <li>
        <a href="dashboard_keuangan.php" class="<?= ($current_page == 'dashboard_keuangan.php') ? 'active' : '' ?>">
          <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
      </li>
      <li>
        <a href="kelola_pembayaran.php" class="<?= ($current_page == 'kelola_pembayaran.php') ? 'active' : '' ?>">
          <i class="fas fa-wallet"></i><span>Kelola Keuangan</span>
        </a>
      </li>
      <li>
        <a href="daftar_siswa_keuangan.php" class="<?= ($current_page == 'daftar_siswa_keuangan.php') ? 'active' : '' ?>">
          <i class="fas fa-users"></i><span>Daftar Siswa</span>
        </a>
      </li>
      <li>
        <a href="#submenuSettingTagihan" 
           data-bs-toggle="collapse" 
           aria-expanded="<?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'true' : 'false' ?>" 
           class="<?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'active' : '' ?>">
          <i class="fas fa-file-invoice-dollar"></i><span>Setting</span>
          <i class="fas fa-chevron-down ms-auto"></i>
        </a>
        <div id="submenuSettingTagihan" class="collapse <?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'show' : '' ?>">
          <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
            <li>
              <a href="setting_nominal.php" class="nav-link <?= ($current_page == 'setting_nominal.php') ? 'active' : '' ?>">
                <i class="fas fa-money-bill-wave"></i><span>Setting Nominal</span>
              </a>
            </li>
            <li>
              <a href="setting_jenis_pembayaran.php" class="nav-link <?= ($current_page == 'setting_jenis_pembayaran.php') ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i><span>Setting Jenis Pembayaran</span>
              </a>
            </li>
          </ul>
        </div>
      </li>
      <li>
        <a href="admin_receipt_layout.php" class="<?= ($current_page == 'admin_receipt_layout.php') ? 'active' : '' ?>">
          <i class="fas fa-th-large"></i><span>Layout Kuitansi</span>
        </a>
      </li>
      <li class="logout">
        <a href="../logout/logout_keuangan.php">
          <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
