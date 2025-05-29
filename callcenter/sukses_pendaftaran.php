<?php
// sukses_pendaftaran.php
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pendaftaran Berhasil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #28a745 30%, #6c757d 100%);
            font-family: 'Poppins', sans-serif;
            color: white;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sukses-container {
            background: rgba(255, 255, 255, 0.2);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .sukses-container h1 {
            font-size: 2rem;
            color: #fff;
        }

        .sukses-container p {
            font-size: 1rem;
            color: #d4edda;
        }

        .btn-kembali {
            background-color: #fff;
            color: #28a745;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            transition: 0.3s ease;
            margin-top: 20px;
        }

        .btn-kembali:hover {
            background-color: #d4edda;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="sukses-container">
        <h1>Pendaftaran Berhasil!</h1>
        <p>Terima kasih telah mendaftar. Kami akan segera menghubungi Anda.</p>
        <a href="pendaftaran_awal.php" class="btn-kembali">Kembali ke Form Pendaftaran</a>
    </div>
</body>

</html>
