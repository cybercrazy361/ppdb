<?php
session_start();
// Opsional: Validasi agar hanya diakses setelah submit form
if (!isset($_SESSION['success_pendaftaran'])) {
    header('Location: input_progres_pendaftaran.php');
    exit();
}
unset($_SESSION['success_pendaftaran']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Sukses - SPMB Call Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar_callcenter_styles.css">
    <style>
        .success-container {
            max-width: 430px;
            margin: 60px auto 0 auto;
            padding: 36px 22px 29px 22px;
            background: #fff;
            border-radius: 1.15rem;
            box-shadow: 0 2px 14px 0 rgba(32,90,170,0.12);
            text-align: center;
        }
        .success-container .icon {
            font-size: 3.7rem;
            color: #36bb7a;
            margin-bottom: 18px;
        }
        .success-container .btn {
            margin-top: 18px;
            border-radius: 0.9rem;
            font-weight: 600;
            padding: 8px 23px;
        }
        @media (max-width:600px){
            .success-container { margin: 35px 2vw 0 2vw; padding: 17px 4vw 14px 4vw; }
        }
    </style>
</head>
<body>
    <?php $active = ''; ?>
    <?php include 'sidebar_callcenter.php'; ?>

    <div class="main d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="success-container">
            <div class="icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3 class="mb-3">Pendaftaran Berhasil!</h3>
            <p class="mb-2">
                Data calon siswa berhasil disimpan.<br>
                Silakan lanjutkan proses follow-up atau tambahkan pendaftar lainnya.
            </p>
            <a href="input_progres_pendaftaran.php" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i>Tambah Lagi</a>
            <a href="dashboard_callcenter.php" class="btn btn-outline-success ms-2"><i class="fa-solid fa-house me-1"></i>Dashboard</a>
        </div>
    </div>
</body>
</html>
