<?php
// partials/header.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Sistem Keuangan'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootswatch Theme (Cosmo) -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/cosmo/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/kelola_pembayaran.css">
    
    <!-- Meta Tag Responsif -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-content">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm">
            <div class="container-fluid">
                <!-- Sidebar Toggle Button -->
                <button class="btn btn-primary me-2" id="toggle-sidebar" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Brand Title -->
                <a class="navbar-brand fw-bold" href="#">Kelola Pembayaran</a>

                <!-- Right Navigation Items -->
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <!-- Notifikasi -->
                        <li class="nav-item dropdown me-3">
                            <a class="nav-link" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell fa-lg"></i>
                                <span class="badge bg-danger rounded-pill" id="notification-count">3</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><a class="dropdown-item" href="#">Notifikasi 1</a></li>
                                <li><a class="dropdown-item" href="#">Notifikasi 2</a></li>
                                <li><a class="dropdown-item" href="#">Notifikasi 3</a></li>
                            </ul>
                        </li>

                        <!-- Profil Pengguna -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle fa-lg"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item" href="#">Profil</a></li>
                                <li><a class="dropdown-item text-danger" href="../keuangan/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
