<?php
session_start();
include '../database_connection.php';

// =========================
// SETUP PHPSpreadsheet
// =========================
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Set output JSON
header('Content-Type: application/json');

// =========================
// Fungsi bantu
// =========================
function formatHp($hp) {
    $hp = trim($hp);
    $hp = preg_replace('/[^0-9]/', '', ltrim($hp, '+')); // hilangkan + dan non-digit
    if (substr($hp, 0, 2) === '62') {
        return $hp;
    } elseif (substr($hp, 0, 1) === '0') {
        return '62' . substr($hp, 1);
    } elseif (substr($hp, 0, 1) === '8') {
        return '62' . $hp;
    }
    return $hp;
}

function validJenisKelamin($jk) {
    $jk = strtolower(trim($jk));
    if ($jk === 'laki-laki' || $jk === 'l') return 'Laki-laki';
    if ($jk === 'perempuan' || $jk === 'p') return 'Perempuan';
    return null;
}

// Mapping pendidikan ortu agar konsisten sesuai di sistem
function normalisasiPendidikan($input) {
    $str = strtolower(str_replace(['-', '.', ','], ['', '', '/'], $input));
    $str = preg_replace('/\s+/', '', $str); // hilangkan spasi

    if (strpos($str, 'sd') !== false) return 'SD/Sederajat';
    if (strpos($str, 'smp') !== false || strpos($str, 'mts') !== false) return 'SMP/Sederajat';
    if (strpos($str, 'sma') !== false || strpos($str, 'smk') !== false || strpos($str, 'ma') !== false) return 'SMA/Sederajat';
    if (strpos($str, 'd3') !== false) return 'D3';
    if (strpos($str, 'd1') !== false || strpos($str, 'd2') !== false) return 'D3'; // semua D1/D2 ke D3
    if (strpos($str, 's1') !== false) return 'S1';
    if (strpos($str, 's2') !== false) return 'S2';
    if (strpos($str, 's3') !== false) return 'S3';
    return 'Lainnya';
}

// Parse tanggal dari excel, apapun format, ke YYYY-MM-DD
function parseTanggalDaftar($tgl) {
    $tgl = trim($tgl);
    // Jika excel number format
    if (is_numeric($tgl) && strlen($tgl) < 8) {
        // Format excel serial date
        $UNIX_DATE = ($tgl - 25569) * 86400;
        return date('Y-m-d', $UNIX_DATE);
    }
    // Cek format: 4/12/2025 19.28.30 atau 5/8/2025 7.23.53 dst
    $tgl = preg_replace('/ (\d{1,2})\.(\d{2})\.(\d{2})$/', ' $1:$2:$3', $tgl);
    $time = strtotime($tgl);
    if (!$time) return null;
    return date('Y-m-d', $time);
}

// =========================
// 1. Validasi session & role
// =========================
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak!']);
    exit;
}

// =========================
// 2. Ambil unit petugas
// =========================
$unit = null;
if (isset($_SESSION['unit'])) {
    $unit = $_SESSION['unit'];
} else {
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($unit);
    $stmt->fetch();
    $stmt->close();
}
if (!$unit) {
    echo json_encode(['success' => false, 'message' => 'Unit tidak ditemukan!']);
    exit;
}

// =========================
// 3. Proses file upload
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Validasi header
        $header = $rows[0];
        $expected = [
            'Nama', 'Jenis Kelamin', 'Asal Sekolah', 'No HP',
            'Alamat', 'Pendidikan Ortu', 'No HP Ortu', 'Tanggal Daftar'
        ];
        foreach ($expected as $i => $exp) {
            if (strtolower(trim($header[$i] ?? '')) !== strtolower($exp)) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Header Excel tidak sesuai!<br>Header yang benar:<br><b>'.implode(' | ', $expected).'</b>'
                ]);
                exit;
            }
        }

        // Import data
        $count = 0; $rowError = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!array_filter($row)) continue; // skip baris kosong

            $nama             = trim($row[0] ?? '');
            $jenis_kelamin    = validJenisKelamin($row[1] ?? '');
            $asal_sekolah     = trim($row[2] ?? '');
            $no_hp            = formatHp(trim($row[3] ?? ''));
            $alamat           = trim($row[4] ?? '');
            $pendidikan_ortu  = normalisasiPendidikan(trim($row[5] ?? ''));
            $no_hp_ortu       = formatHp(trim($row[6] ?? ''));
            $tanggal_daftar   = parseTanggalDaftar(trim($row[7] ?? ''));

            // Validasi
            if ($nama === '' || !$tanggal_daftar) {
                $rowError[] = $i+1; // Excel row (1-based)
                continue;
            }
            if (!$jenis_kelamin) {
                $rowError[] = $i+1; continue;
            }
            if (!preg_match('/^628[0-9]{7,13}$/', $no_hp) || !preg_match('/^628[0-9]{7,13}$/', $no_hp_ortu)) {
                $rowError[] = $i+1; continue;
            }

            $stmt = $conn->prepare("INSERT INTO calon_pendaftar
                (nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, tanggal_daftar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                "sssssssss",
                $nama,
                $jenis_kelamin,
                $asal_sekolah,
                $no_hp,
                $alamat,
                $pendidikan_ortu,
                $no_hp_ortu,
                $unit,
                $tanggal_daftar
            );
            if ($stmt->execute()) $count++;
            $stmt->close();
        }

        $msg = "Berhasil mengimpor <b>$count</b> data.";
        if (!empty($rowError)) {
            $msg .= "<br>Baris <b>".implode(', ', $rowError)."</b> gagal diimpor (data tidak valid atau kosong).";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'File error: '.$e->getMessage()]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload!']);
    exit;
}
