<?php
// pendaftaran_awal.php
session_start();

// Menghasilkan token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Awal - Yayasan Pendidikan Dharma Karya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #007bff 30%, #6c757d 100%);
            font-family: 'Poppins', sans-serif;
            color: white;
        }

        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .form-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-header h1 {
            font-size: 2rem;
            color: #007bff;
        }

        .form-header p {
            font-size: 1rem;
            color: #6c757d;
        }

        .btn-submit {
            background-color: #007bff;
            border: none;
            padding: 10px 15px;
            color: white;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .btn-reset {
            background-color: #6c757d;
            border: none;
            padding: 10px 15px;
            color: white;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s ease;
            width: 100%;
        }

        .btn-reset:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                width: 90%;
            }

            .form-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="form-container">
            <div class="form-header">
                <h1>Pendaftaran Awal</h1>
                <p>Silakan isi data berikut untuk pendaftaran awal.</p>
            </div>
            <form action="proses_calon_pendaftar.php" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama lengkap" required>
                </div>
                <div class="mb-3">
                    <label for="asal_sekolah" class="form-label">Asal Sekolah</label>
                    <input type="text" class="form-control" id="asal_sekolah" name="asal_sekolah" placeholder="Masukkan asal sekolah" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Masukkan email" required>
                </div>
                <div class="mb-3">
                    <label for="no_hp" class="form-label">No Telepon</label>
                    <input type="tel" class="form-control" id="no_hp" name="no_hp" placeholder="Masukkan nomor telepon" pattern="[0-9]{10,13}" required>
                    <small class="text-muted">Format: Hanya angka (10-13 digit).</small>
                </div>
                <div class="mb-3">
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3" placeholder="Masukkan alamat lengkap" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="pilihan" class="form-label">Pilih Sekolah</label>
                    <select class="form-select" id="pilihan" name="pilihan" required>
                        <option value="" disabled selected>-- Pilih Sekolah --</option>
                        <option value="SMA">SMA Dharma Karya</option>
                        <option value="SMK">SMK Dharma Karya</option>
                    </select>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn-submit">Kirim Pendaftaran</button>
                    <button type="reset" class="btn-reset">Reset</button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
