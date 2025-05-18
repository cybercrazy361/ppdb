<?php
// sidebar.php

// Pastikan session sudah dimulai dan user sudah login
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nama halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <!-- Brand & Toggle -->
    <div class="sidebar-header d-flex align-items-center justify-content-between px-3 py-2">
        <a href="dashboard_keuangan.php" class="sidebar-brand d-flex align-items-center">
            <img src="assets/img/logo.png" alt="Logo" class="sidebar-logo me-2">
            <span class="sidebar-brand-text">Nama Sekolah</span>
        </a>
        <button id="sidebarToggle" class="btn btn-sm">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- User Info -->
    <div class="user-info text-center py-3">
        <img src="<?php echo htmlspecialchars($_SESSION['profile_pic'] ?? 'assets/img/default-avatar.png'); ?>" alt="User" class="rounded-circle mb-2 sidebar-avatar">
        <h5 class="mb-1"><?php echo htmlspecialchars($_SESSION['nama']); ?></h5>
        <small><?php echo htmlspecialchars($_SESSION['unit']); ?></small>
    </div>

    <!-- Navigation -->
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'dashboard_keuangan.php') ? 'active' : ''; ?>" href="dashboard_keuangan.php" title="Dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'kelola_pembayaran.php') ? 'active' : ''; ?>" href="kelola_pembayaran.php" title="Kelola Keuangan">
                <i class="fas fa-wallet"></i>
                <span>Kelola Keuangan</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'daftar_siswa_keuangan.php') ? 'active' : ''; ?>" href="daftar_siswa_keuangan.php" title="Daftar Siswa">
                <i class="fas fa-users"></i>
                <span>Daftar Siswa</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex justify-content-between <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'active' : ''; ?>"
               href="#submenuSetting" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'true' : 'false'; ?>"
               title="Setting Tagihan">
                <span>
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    <span>Setting</span>
                </span>
                <i class="fas fa-chevron-down"></i>
            </a>
            <div class="collapse <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'show' : ''; ?>" id="submenuSetting">
                <ul class="nav flex-column ms-3">
                    <li class="nav-item">
                        <a href="setting_nominal.php" class="nav-link <?php echo ($current_page == 'setting_nominal.php') ? 'active' : ''; ?>" title="Setting Nominal">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            <span>Setting Nominal</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="setting_jenis_pembayaran.php" class="nav-link <?php echo ($current_page == 'setting_jenis_pembayaran.php') ? 'active' : ''; ?>" title="Setting Jenis Pembayaran">
                            <i class="fas fa-credit-card me-2"></i>
                            <span>Setting Jenis Pembayaran</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'admin_receipt_layout.php') ? 'active' : ''; ?>" href="admin_receipt_layout.php" title="Pengaturan Layout Kuitansi">
                <i class="fas fa-th-large"></i>
                <span>Layout Kuitansi</span>
            </a>
        </li>
        <li class="nav-item mt-auto">
            <a class="nav-link logout-link" href="../logout/logout_keuangan.php" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
    // Toggle collapse sidebar
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
</script>
