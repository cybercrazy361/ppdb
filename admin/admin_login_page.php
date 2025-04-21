<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - PPDB Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_login_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="login-container d-flex justify-content-center align-items-center">
        <div class="login-card p-4">
            <div class="text-center mb-4">
                <h2 class="login-title"><i class="fas fa-user-shield"></i> Login Admin</h2>
            </div>
            <form action="login/login_process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Masukkan username" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Masukkan password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i
                                class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-login">Login
                        <i class="fas fa-sign-in-alt"></i></button>
                </div>

                <div class="text-center mt-3">
                    <a href="../index.html" class="btn btn-link">Kembali
                        ke Halaman Utama</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye icon
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>
