<?php
// admin_receipt_layout.php


/**
 * Mengubah angka menjadi kata (bahasa Indonesia)
 */
function terbilang($angka) {
    $abil = ["","satu","dua","tiga","empat","lima","enam","tujuh","delapan","sembilan","sepuluh","sebelas"];
    $angka = (int) $angka;
    if ($angka < 12) {
        return " " . $abil[$angka];
    } elseif ($angka < 20) {
        return terbilang($angka - 10) . " belas";
    } elseif ($angka < 100) {
        return terbilang($angka / 10) . " puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        return " seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        return terbilang($angka / 100) . " ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        return " seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        return terbilang($angka / 1000) . " ribu" . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        return terbilang($angka / 1000000) . " juta" . terbilang($angka % 1000000);
    } else {
        return "";
    }
}


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../database_connection.php';

// Pastikan pengguna sudah login dan memiliki peran 'keuangan' atau 'admin'
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['keuangan', 'admin'])) {
    header('Location: login_keuangan.php');
    exit();
}

// Ambil unit petugas dari sesi
$user_unit = $_SESSION['unit']; // 'Yayasan', 'SMA', atau 'SMK'

// Fetch current layout settings berdasarkan unit
$stmt = $conn->prepare("SELECT id, element_name, x_position_mm, y_position_mm, visible, font_size, font_family, paper_width_mm, paper_height_mm, image_path, watermark_text FROM receipt_layout WHERE unit = ? ORDER BY id ASC");
if ($stmt) {
    $stmt->bind_param('s', $user_unit);
    $stmt->execute();
    $result = $stmt->get_result();
    $layouts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Fetch current paper size (asumsi semua elemen memiliki ukuran kertas yang sama)
$paper = [
    'paper_width_mm' => 80.0,
    'paper_height_mm' => 120.0
];
if (!empty($layouts)) {
    $paper['paper_width_mm'] = $layouts[0]['paper_width_mm'];
    $paper['paper_height_mm'] = $layouts[0]['paper_height_mm'];
}

// Handle form submission
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF tidak valid.");
    }

    // Ambil dan sanitasi input ukuran kertas
    $paper_width_mm = isset($_POST['paper_width_mm']) ? floatval($_POST['paper_width_mm']) : $paper['paper_width_mm'];
    $paper_height_mm = isset($_POST['paper_height_mm']) ? floatval($_POST['paper_height_mm']) : $paper['paper_height_mm'];

    // Validasi input ukuran kertas
    if ($paper_width_mm <= 0) $errors[] = "Lebar kertas harus lebih besar dari 0 mm.";
    if ($paper_height_mm <= 0) $errors[] = "Tinggi kertas harus lebih besar dari 0 mm.";

    // Proses upload logo jika ada
    foreach ($layouts as $layout) {
        $element = $layout['element_name'];

        if ($element === 'logo') {
            if (isset($_FILES["image_path_$element"]) && $_FILES["image_path_$element"]['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES["image_path_$element"]['tmp_name'];
                $fileName = $_FILES["image_path_$element"]['name'];
                $fileSize = $_FILES["image_path_$element"]['size'];
                $fileType = $_FILES["image_path_$element"]['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // Daftar ekstensi yang diperbolehkan
                $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (in_array($fileExtension, $allowedfileExtensions)) {
                    // Buat nama file unik
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

                    // Direktori tempat menyimpan logo
                    $uploadFileDir = '../uploads/logos/';
                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }
                    $dest_path = $uploadFileDir . $newFileName;

                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Simpan path ke database (relative path)
                        $image_path_db = $dest_path;

                        // Update layout dengan path gambar
                        $stmt_update_logo = $conn->prepare("UPDATE receipt_layout SET image_path = ? WHERE element_name = ? AND unit = ?");
                        if ($stmt_update_logo) {
                            $stmt_update_logo->bind_param('sss', $image_path_db, $element, $user_unit);
                            if (!$stmt_update_logo->execute()) {
                                $errors[] = "Gagal memperbarui path logo: " . $stmt_update_logo->error;
                            }
                            $stmt_update_logo->close();
                        } else {
                            $errors[] = "Error preparing statement untuk logo: " . $conn->error;
                        }
                    } else {
                        $errors[] = "Gagal mengupload logo.";
                    }
                } else {
                    $errors[] = "Ekstensi file logo tidak diperbolehkan. Hanya JPG, JPEG, PNG, dan GIF yang diizinkan.";
                }
            }
        }
    }

    // Ambil teks watermark jika disediakan
    $watermark_text = '';
    foreach ($layouts as $layout) {
        if ($layout['element_name'] === 'watermark') {
            $watermark_text = isset($_POST['watermark_text']) ? trim($_POST['watermark_text']) : $layout['watermark_text'];
            break;
        }
    }

    // Loop melalui setiap elemen layout dan update posisi, visibilitas, font size, font family
    foreach ($layouts as $layout) {
        $element = $layout['element_name'];
        $x_position_mm = isset($_POST["x_{$element}"]) ? floatval($_POST["x_{$element}"]) : $layout['x_position_mm'];
        $y_position_mm = isset($_POST["y_{$element}"]) ? floatval($_POST["y_{$element}"]) : $layout['y_position_mm'];
        $visible = isset($_POST["visible_{$element}"]) ? 1 : 0;

        // Ambil font_size dan font_family
        $font_size = isset($_POST["font_size_{$element}"]) ? floatval($_POST["font_size_{$element}"]) : $layout['font_size'];
        $font_family = isset($_POST["font_family_{$element}"]) ? trim($_POST["font_family_{$element}"]) : $layout['font_family'];

        // Update database
        $stmt_update = $conn->prepare("UPDATE receipt_layout SET x_position_mm = ?, y_position_mm = ?, visible = ?, paper_width_mm = ?, paper_height_mm = ?, font_size = ?, font_family = ? WHERE element_name = ? AND unit = ?");
        if ($stmt_update) {
            $stmt_update->bind_param('ddiiddsss', $x_position_mm, $y_position_mm, $visible, $paper_width_mm, $paper_height_mm, $font_size, $font_family, $element, $user_unit);
            if (!$stmt_update->execute()) {
                $errors[] = "Gagal memperbarui $element: " . $stmt_update->error;
            }
            $stmt_update->close();

            // Jika elemen adalah watermark, update teks watermark
            if ($element === 'watermark') {
                $stmt_update_watermark = $conn->prepare("UPDATE receipt_layout SET watermark_text = ? WHERE element_name = ? AND unit = ?");
                if ($stmt_update_watermark) {
                    $stmt_update_watermark->bind_param('sss', $watermark_text, $element, $user_unit);
                    if (!$stmt_update_watermark->execute()) {
                        $errors[] = "Gagal memperbarui teks watermark: " . $stmt_update_watermark->error;
                    }
                    $stmt_update_watermark->close();
                } else {
                    $errors[] = "Error preparing statement untuk teks watermark: " . $conn->error;
                }
            }
        } else {
            $errors[] = "Error preparing statement: " . $conn->error;
        }
    }

    if (empty($errors)) {
        $success = "Pengaturan layout dan ukuran kertas berhasil diperbarui.";
        // Refresh layout dan paper size
        $stmt = $conn->prepare("SELECT id, element_name, x_position_mm, y_position_mm, visible, font_size, font_family, paper_width_mm, paper_height_mm, image_path, watermark_text FROM receipt_layout WHERE unit = ? ORDER BY id ASC");
        if ($stmt) {
            $stmt->bind_param('s', $user_unit);
            $stmt->execute();
            $result = $stmt->get_result();
            $layouts = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        if (!empty($layouts)) {
            $paper['paper_width_mm'] = $layouts[0]['paper_width_mm'];
            $paper['paper_height_mm'] = $layouts[0]['paper_height_mm'];
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fungsi untuk mendapatkan nilai elemen untuk preview (opsional)
function getElementValue($elementName) {
    // Implementasikan sesuai kebutuhan Anda, misalnya mengambil data dari database atau variabel global
    // Contoh:
    return ''; // Ganti dengan nilai sebenarnya atau kosongkan
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Layout Kuitansi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Custom Sidebar CSS -->
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <!-- Custom Admin Receipt Layout CSS -->
    <link rel="stylesheet" href="../assets/css/admin_receipt_layout.css">
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <button id="sidebarToggle" class="btn btn-link d-md-inline rounded-circle me-3">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link" href="#">
                        <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['nama']); ?></span>
                        <i class="fas fa-user-circle fa-lg"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="container-fluid">
            <h1 class="mt-4 dashboard-title">Pengaturan Layout Kuitansi</h1>
            <?php if (!empty($success)) : ?>
                <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error) : ?>
                        <p><?= htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="admin_receipt_layout.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
                <div class="row">
                    <!-- Form Input Ukuran Kertas -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Ukuran Kertas (mm)</h5>
                                <div class="mb-3">
                                    <label for="paper_width_mm" class="form-label">Lebar Kertas (mm)</label>
                                    <input type="number" name="paper_width_mm" id="paper_width_mm" class="form-control" value="<?= htmlspecialchars($paper['paper_width_mm']); ?>" min="50" step="0.1" required>
                                </div>
                                <div class="mb-3">
                                    <label for="paper_height_mm" class="form-label">Tinggi Kertas (mm)</label>
                                    <input type="number" name="paper_height_mm" id="paper_height_mm" class="form-control" value="<?= htmlspecialchars($paper['paper_height_mm']); ?>" min="100" step="0.1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Form Input Posisi, Visibilitas, Font Size, Font Family, Upload Logo, dan Teks Watermark -->
                    <div class="col-md-12 mt-4">
<table class="table table-bordered">
  <thead class="table-primary">
    <tr>
      <th>Elemen</th>
      <th>Posisi X (mm)</th>
      <th>Posisi Y (mm)</th>
      <th>Visible</th>
      <th>Font Size (pt)</th>
      <th>Font Family</th>
      <th>Upload Logo / Teks Watermark</th>
    </tr>
  </thead>
  <tbody>
    <!-- Baris khusus Terbilang -->
    <tr>
      <td>Terbilang</td>
      <td><input type="number" name="x_terbilang" class="form-control position-input"
                 value="<?= htmlspecialchars(($layouts[array_search('terbilang', array_column($layouts,'element_name'))]['x_position_mm'] ?? 10.0)); ?>"
                 min="0" step="0.1" required></td>
      <td><input type="number" name="y_terbilang" class="form-control position-input"
                 value="<?= htmlspecialchars(($layouts[array_search('terbilang', array_column($layouts,'element_name'))]['y_position_mm'] ?? 110.0)); ?>"
                 min="0" step="0.1" required></td>
      <td class="text-center">
        <input type="checkbox" name="visible_terbilang"
               <?= ($layouts[array_search('terbilang', array_column($layouts,'element_name'))]['visible'] ?? 1) ? 'checked' : ''; ?>>
      </td>
      <td><input type="number" name="font_size_terbilang" class="form-control"
                 value="<?= htmlspecialchars(($layouts[array_search('terbilang', array_column($layouts,'element_name'))]['font_size'] ?? 12.0)); ?>"
                 min="1" step="0.1" required></td>
      <td><input type="text" name="font_family_terbilang" class="form-control"
                 value="<?= htmlspecialchars(($layouts[array_search('terbilang', array_column($layouts,'element_name'))]['font_family'] ?? 'Nunito, sans-serif')); ?>"
                 required></td>
      <td>-</td>
    </tr>

    <!-- Loop untuk elemen lain -->
    <?php foreach ($layouts as $layout) : ?>
      <?php if ($layout['element_name'] === 'terbilang') continue; ?>
      <tr>
        <td><?= htmlspecialchars(ucfirst(str_replace('_',' ',$layout['element_name']))); ?></td>
        <td><input type="number" name="x_<?= htmlspecialchars($layout['element_name']); ?>" class="form-control position-input"
                   value="<?= htmlspecialchars($layout['x_position_mm']); ?>" min="0" step="0.1" required></td>
        <td><input type="number" name="y_<?= htmlspecialchars($layout['element_name']); ?>" class="form-control position-input"
                   value="<?= htmlspecialchars($layout['y_position_mm']); ?>" min="0" step="0.1" required></td>
        <td class="text-center">
          <input type="checkbox" name="visible_<?= htmlspecialchars($layout['element_name']); ?>"
                 <?= $layout['visible'] ? 'checked' : ''; ?>>
        </td>
        <td>
          <?php if (!in_array($layout['element_name'], ['logo','watermark'])): ?>
            <input type="number" name="font_size_<?= htmlspecialchars($layout['element_name']); ?>" class="form-control"
                   value="<?= htmlspecialchars($layout['font_size']); ?>" min="1" step="0.1" required>
          <?php else: ?>
            <input type="number" class="form-control" value="<?= htmlspecialchars($layout['font_size']); ?>" disabled>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!in_array($layout['element_name'], ['logo','watermark'])): ?>
            <input type="text" name="font_family_<?= htmlspecialchars($layout['element_name']); ?>" class="form-control"
                   value="<?= htmlspecialchars($layout['font_family']); ?>" required>
          <?php else: ?>
            <input type="text" class="form-control" value="<?= htmlspecialchars($layout['font_family']); ?>" disabled>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($layout['element_name']==='logo'): ?>
            <label class="form-label">Upload Logo</label>
            <input type="file" name="image_path_logo" class="form-control">
            <?php if (!empty($layout['image_path'])): ?>
              <img src="<?= htmlspecialchars($layout['image_path']); ?>" style="max-width:100px;margin-top:10px;">
            <?php endif; ?>
          <?php elseif ($layout['element_name']==='watermark'): ?>
            <label class="form-label">Teks Watermark</label>
            <input type="text" name="watermark_text" class="form-control"
                   value="<?= htmlspecialchars($layout['watermark_text']); ?>" required>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

                        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                    </div>
                </div>
                <div class="row mt-4">
<!-- Preview Layout -->
<div class="col-md-12">
    <h4>Preview Layout</h4>
    <div class="layout-grid" id="layoutGrid"
         style="width: <?= htmlspecialchars($paper['paper_width_mm']); ?>mm;
                height: <?= htmlspecialchars($paper['paper_height_mm']); ?>mm;">
        <?php foreach ($layouts as $layout) : ?>
            <?php if (!$layout['visible']) continue; ?>

            <?php
            $element      = $layout['element_name'];
            $x            = $layout['x_position_mm'];
            $y            = $layout['y_position_mm'];
            $fs           = $layout['font_size'];
            $ff           = $layout['font_family'];
            $style_inline = "left: {$x}mm; top: {$y}mm; font-size: {$fs}pt; font-family:'{$ff}'; cursor:move;";
            ?>

            <?php if ($element === 'logo' && !empty($layout['image_path'])) : ?>
                <div class="layout-element logo-element"
                     id="element-logo"
                     data-element="logo"
                     style="<?= $style_inline ?>">
                    <img src="<?= htmlspecialchars($layout['image_path']) ?>"
                         alt="Logo"
                         style="max-width:100px; max-height:100px; pointer-events:none;">
                </div>

            <?php elseif ($element === 'watermark') : ?>
                <div class="layout-element watermark-element"
                     id="element-watermark"
                     data-element="watermark"
                     style="<?= $style_inline ?>">
                    <?= htmlspecialchars($layout['watermark_text']) ?>
                </div>

            <?php elseif ($element === 'terbilang') : ?>
                <?php
                    $total = getElementValue('jumlah') ?: 0;
                    $kata  = ucfirst(trim(terbilang($total))) . " rupiah";
                ?>
                <div class="layout-element"
                     id="element-terbilang"
                     data-element="terbilang"
                     style="<?= $style_inline ?>">
                    <?= htmlspecialchars($kata) ?>
                </div>

            <?php else : ?>
                <div class="layout-element"
                     id="element-<?= htmlspecialchars($element) ?>"
                     data-element="<?= htmlspecialchars($element) ?>"
                     style="<?= $style_inline ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_',' ',$element))) ?>:
                    <?= htmlspecialchars(getElementValue($element)) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <p class="mt-2">Anda dapat menyeret elemen pada preview untuk menyesuaikan posisi.</p>
</div>

                </div>
            </form>
        </div>
    </div>
    <footer class="footer bg-white text-center py-3">
        &copy; <?php echo date('Y'); ?> Sistem Keuangan PPDB
    </footer>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom Sidebar JS -->
    <script src="../assets/js/sidebar.js"></script>
    <!-- Custom Admin Receipt Layout JS -->
    <script src="../assets/js/admin_receipt_layout.js"></script>

    <script>
        // Menyesuaikan ukuran grid secara dinamis berdasarkan input ukuran kertas
        document.getElementById('paper_width_mm').addEventListener('input', updateGridSize);
        document.getElementById('paper_height_mm').addEventListener('input', updateGridSize);

        function updateGridSize() {
            const width = document.getElementById('paper_width_mm').value;
            const height = document.getElementById('paper_height_mm').value;
            const grid = document.getElementById('layoutGrid');
            grid.style.width = `${width}mm`;
            grid.style.height = `${height}mm`;

            // Mengupdate posisi elemen sesuai dengan ukuran baru
            const elements = document.querySelectorAll('.layout-element');
            elements.forEach(element => {
                const elemName = element.getAttribute('data-element');
                const xInput = document.querySelector(`input[name="x_${elemName}"]`);
                const yInput = document.querySelector(`input[name="y_${elemName}"]`);
                const fontSizeInput = document.querySelector(`input[name="font_size_${elemName}"]`);
                const fontFamilyInput = document.querySelector(`input[name="font_family_${elemName}"]`);

                const newX = parseFloat(xInput.value);
                const newY = parseFloat(yInput.value);
                const newFontSize = fontSizeInput.value;
                const newFontFamily = fontFamilyInput.value;

                if (elemName === 'logo' && element.querySelector('img')) {
                    // Logo sebagai gambar
                    element.style.left = `${newX}mm`;
                    element.style.top = `${newY}mm`;
                } else if (elemName === 'watermark') {
                    // Watermark sebagai teks
                    element.style.left = `${newX}mm`;
                    element.style.top = `${newY}mm`;
                    element.style.fontSize = `${newFontSize}pt`;
                    element.style.fontFamily = `${newFontFamily}`;
                } else {
                    // Elemen lainnya
                    element.style.left = `${newX}mm`;
                    element.style.top = `${newY}mm`;
                    element.style.fontSize = `${newFontSize}pt`;
                    element.style.fontFamily = `${newFontFamily}`;
                }
            });
        }
    </script>
</body>
</html>
