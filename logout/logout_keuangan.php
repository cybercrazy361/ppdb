<?php
session_start();

// Hapus semua sesi
session_unset();
session_destroy();

// Pastikan tidak dapat kembali ke dashboard dengan tombol back
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Redirect ke halaman login
header('Location: ../keuangan/login_keuangan.php');
exit();
?>
