<?php
// sidebar.php

// Mendapatkan nama halaman saat ini
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="user-info text-center mb-4">
        <h4><?php echo htmlspecialchars($_SESSION['nama']); ?></h4>
        <p><?php echo htmlspecialchars($_SESSION['unit']); ?></p>
    </div>
    <ul class="nav flex-column">
        <!-- Dashboard Menu -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'dashboard_keuangan.php') ? 'active' : ''; ?>"
               href="dashboard_keuangan.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Kelola Keuangan Menu -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'kelola_pembayaran.php') ? 'active' : ''; ?>"
               href="kelola_pembayaran.php">
                <i class="fas fa-wallet"></i>
                <span>Kelola Keuangan</span>
            </a>
        </li>

        <!-- Daftar Siswa Keuangan Menu -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'daftar_siswa_keuangan.php') ? 'active' : ''; ?>"
               href="daftar_siswa_keuangan.php">
                <i class="fas fa-users"></i>
                <span>Daftar Siswa</span>
            </a>
        </li>

        <!-- Setting Tagihan Menu dengan Submenu -->
        <li class="nav-item has-submenu">
            <a class="nav-link <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'active' : ''; ?>"
               href="#submenuSettingTagihan"
               data-bs-toggle="collapse"
               aria-expanded="<?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'true' : 'false'; ?>"
               aria-controls="submenuSettingTagihan">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Setting Tagihan</span>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </a>
            <div class="collapse <?php echo in_array($current_page, ['setting_nominal.php','setting_jenis_pembayaran.php']) ? 'show' : ''; ?>"
                 id="submenuSettingTagihan">
                <ul class="btn-toggle-nav list-unstyled fw-normal pb-1 small">
                    <li>
                        <a href="setting_nominal.php"
                           class="nav-link ms-4 <?php echo ($current_page == 'setting_nominal.php') ? 'active' : ''; ?>">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Setting Nominal</span>
                        </a>
                    </li>
                    <li>
                        <a href="setting_jenis_pembayaran.php"
                           class="nav-link ms-4 <?php echo ($current_page == 'setting_jenis_pembayaran.php') ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Setting Jenis Pembayaran</span>
                        </a>
                    </li>
                </ul>
            </div>
        </li>
        <!-- Rekap Pembayaran Menu -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'rekap_pembayaran.php') ? 'active' : ''; ?>"
            href="rekap_pembayaran.php">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Pembayaran</span>
            </a>
        </li>

        <!-- Pengaturan Layout Kuitansi Menu -->
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'admin_receipt_layout.php') ? 'active' : ''; ?>"
               href="admin_receipt_layout.php">
                <i class="fas fa-th-large"></i>
                <span>Layout Kuitansi</span>
            </a>
        </li>

        <!-- Logout Link -->
        <li class="nav-item logout-link mt-auto">
            <a class="nav-link"
               href="../logout/logout_keuangan.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
