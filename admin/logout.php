<?php
// logout.php
session_start();

// Hancurkan semua data sesi
$_SESSION = array();
session_destroy();

// Set header untuk mencegah caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Redirect ke halaman login
header("Location: admin_login_page.php");
exit();
?>
