<?php
// sidebar.php
session_start();

// Cegah akses tanpa login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'keuangan') {
    header('Location: login_keuangan.php');
    exit();
}

// Nama halaman saat ini untuk active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside id="sidebar" class="sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-between px-3 py-2">
        <div class="brand">
            <img src="../assets/img/logo.png" alt="Logo" class="brand-logo">
            <span class="brand-name">PPDB Keu.</span>
        </div>
        <button id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div class="user-info text-center py-3">
        <img src="../assets/img/avatar.png" alt="Avatar" class="avatar mb-2">
        <h5 class="mb-1"><?php echo htmlspecialchars($_SESSION['nama']); ?></h5>
        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['unit']); ?></small>
    </div>
    <nav class="nav flex-column">
        <a href="dashboard_keuangan.php" class="nav-link <?= $current_page=='dashboard_keuangan.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
        </a>
        <a href="kelola_pembayaran.php" class="nav-link <?= $current_page=='kelola_pembayaran.php' ? 'active' : '' ?>">
            <i class="fas fa-wallet"></i><span>Kelola Pembayaran</span>
        </a>
        <a href="daftar_siswa_keuangan.php" class="nav-link <?= $current_page=='daftar_siswa_keuangan.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i><span>Daftar Siswa</span>
        </a>
        <div class="nav-item has-submenu">
            <a href="#submenuTagihan" data-bs-toggle="collapse" aria-expanded="<?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php'])?'true':'false' ?>" class="nav-link <?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php'])?'active':'' ?>">
                <i class="fas fa-file-invoice-dollar"></i><span>Setting Tagihan</span><i class="fas fa-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?= in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php'])?'show':'' ?>" id="submenuTagihan">
                <a href="setting_nominal.php" class="nav-link ms-4 <?= $current_page=='setting_nominal.php'?'active':'' ?>">
                    <i class="fas fa-money-bill-wave"></i><span>Nominal</span>
                </a>
                <a href="setting_jenis_pembayaran.php" class="nav-link ms-4 <?= $current_page=='setting_jenis_pembayaran.php'?'active':'' ?>">
                    <i class="fas fa-credit-card"></i><span>Jenis Pembayaran</span>
                </a>
            </div>
        </div>
        <a href="admin_receipt_layout.php" class="nav-link <?= $current_page=='admin_receipt_layout.php'?'active':'' ?>">
            <i class="fas fa-th-large"></i><span>Layout Kuitansi</span>
        </a>
    </nav>
    <div class="mt-auto mb-3 px-3">
        <a href="../logout/logout_keuangan.php" class="nav-link logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>
