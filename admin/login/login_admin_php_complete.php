<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/admin_login_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="login-container d-flex justify-content-center align-items-center">
        <div class="login-card p-4">
            <div class="text-center mb-4">
                <h2 class="login-title"><i class="fas fa-user-shield"></i> Login Admin</h2>
            </div>
            <form action="login_process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Masukkan username">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Masukkan password">
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-login"
                        style="background: linear-gradient(45deg, #007bff, #00c6ff); border: none; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); transition: transform 0.3s, box-shadow 0.3s;">Login
                        <i class="fas fa-sign-in-alt"></i></button>
                </div>

                <div class="text-center mt-3">
                    <a href="../index.html" class="btn btn-link"
                        style="text-decoration: none; color: #007bff; font-weight: bold; transition: color 0.3s;">Kembali
                        ke Halaman Utama</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
