<?php
// Aktifkan error reporting untuk debug (bisa hapus di produksi)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// Jika sudah login, redirect sesuai role
if (isset($_SESSION['pimpinan'])) {
    header('Location: dashboard_pimpinan.php');
    exit();
} elseif (isset($_SESSION['yayasan'])) {
    header('Location: dashboard_yayasan.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Akun PPDB</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow p-4">
                    <h3 class="mb-3 text-center" style="color:var(--clr-primary);font-weight:700;">Login Akun</h3>
                    <?php if (!empty($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                    <?php endif; ?>
                    <form method="post" action="proses_login_umum.php" autocomplete="off">
                        <div class="mb-3">
                            <label for="role" class="form-label">Login Sebagai</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Pilih...</option>
                                <option value="pimpinan">Pimpinan SMA/SMK</option>
                                <option value="yayasan">Yayasan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="unit" class="form-label">Unit (jika Pimpinan)</label>
                            <select class="form-select" id="unit" name="unit">
                                <option value="">Pilih Unit</option>
                                <option value="SMA">SMA</option>
                                <option value="SMK">SMK</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required autofocus autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-2">Masuk</button>
                        <a href="../index.php" class="btn btn-secondary w-100">Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Sembunyikan unit jika role yayasan
    document.getElementById('role').addEventListener('change', function() {
        var unitField = document.getElementById('unit');
        if (this.value === 'yayasan') {
            unitField.disabled = true;
            unitField.value = '';
        } else {
            unitField.disabled = false;
        }
    });
    </script>
</body>
</html>
