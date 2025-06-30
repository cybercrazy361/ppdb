<?php
session_start();
include '../database_connection.php';

// Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}
$unit = $_SESSION['unit'];
$uang_pangkal_id = 1;
$spp_id = 2;

// Fungsi rekap total
function rekapTotal($conn, $unit, $uang_pangkal_id, $spp_id)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM siswa WHERE unit = ?'
    );
    $stmt->bind_param('s', $unit);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt->close();

    // Hitung PPDB Bersama (total)
    $stmt_ppdb = $conn->prepare("
        SELECT COUNT(*) AS total_ppdb
        FROM siswa s
        LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
        WHERE s.unit = ? AND LOWER(cp.status) = 'ppdb bersama'
    ");
    $stmt_ppdb->bind_param('s', $unit);
    $stmt_ppdb->execute();
    $total_ppdb = $stmt_ppdb->get_result()->fetch_assoc()['total_ppdb'] ?? 0;
    $stmt_ppdb->close();

    $sql = "
SELECT s.id,
  CASE
    WHEN 
      (SELECT COUNT(*) FROM pembayaran_detail pd1 
        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
        WHERE p1.siswa_id = s.id 
          AND pd1.jenis_pembayaran_id = $uang_pangkal_id
          AND pd1.status_pembayaran = 'Lunas'
      ) > 0
    THEN 'Lunas'
    WHEN 
      (SELECT COUNT(*) FROM pembayaran_detail pd1 
        JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
        WHERE p1.siswa_id = s.id 
          AND pd1.jenis_pembayaran_id = $uang_pangkal_id
      ) > 0
    THEN 'Angsuran'
    ELSE 'Belum Bayar'
  END AS status_pembayaran
FROM siswa s
LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id = cp.id
WHERE s.unit = ? AND (cp.status IS NULL OR LOWER(cp.status) != 'ppdb bersama')
";

    // hanya siswa NON ppdb bersama dihitung statusnya
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $unit);
    $stmt->execute();
    $result = $stmt->get_result();

    $lunas = $angsuran = $belum = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['status_pembayaran'] === 'Lunas') {
            $lunas++;
        } elseif ($row['status_pembayaran'] === 'Angsuran') {
            $angsuran++;
        } else {
            $belum++;
        }
    }
    $stmt->close();

    return [
        'total' => $total,
        'lunas' => $lunas,
        'angsuran' => $angsuran,
        'belum' => $belum,
        'ppdb' => $total_ppdb,
    ];
}

// Fungsi rekap hari ini (dan PPDB Bersama harian)
function rekapHariIni($conn, $unit, $uang_pangkal_id, $spp_id, $tanggal = null)
{
    $today = $tanggal ?: date('Y-m-d');

    // Ambil siswa hari ini
    $stmt = $conn->prepare(
        'SELECT s.id, cp.status AS status_ppdb FROM siswa s LEFT JOIN calon_pendaftar cp ON s.calon_pendaftar_id=cp.id WHERE s.unit=? AND s.tanggal_pendaftaran=?'
    );
    $stmt->bind_param('ss', $unit, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $lunas = $angsuran = $belum = $total = $ppdb = 0;

    while ($row = $result->fetch_assoc()) {
        $id = $row['id'];
        $is_ppdb =
            strtolower(trim($row['status_ppdb'] ?? '')) === 'ppdb bersama';
        if ($is_ppdb) {
            $ppdb++;
            $total++;
            continue; // Tidak hitung status bayar, langsung kategori ppdb
        }
        // Non-ppdb, cek status bayar:
        $cek = "
        SELECT
        CASE
            WHEN 
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = $id 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                      AND pd1.status_pembayaran = 'Lunas'
                ) > 0
            AND
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = $id 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            THEN 'Lunas'
            WHEN (
                (SELECT COUNT(*) FROM pembayaran_detail pd1 
                    JOIN pembayaran p1 ON pd1.pembayaran_id = p1.id
                    WHERE p1.siswa_id = $id 
                      AND pd1.jenis_pembayaran_id = $uang_pangkal_id
                ) > 0
                OR
                (SELECT COUNT(*) FROM pembayaran_detail pd2 
                    JOIN pembayaran p2 ON pd2.pembayaran_id = p2.id
                    WHERE p2.siswa_id = $id 
                      AND pd2.jenis_pembayaran_id = $spp_id
                      AND pd2.bulan = 'Juli'
                      AND pd2.status_pembayaran = 'Lunas'
                ) > 0
            )
            THEN 'Angsuran'
            ELSE 'Belum Bayar'
        END AS status_pembayaran
        ";
        $q = $conn->query($cek);
        $stat = $q->fetch_assoc()['status_pembayaran'] ?? '';
        if ($stat === 'Lunas') {
            $lunas++;
        } elseif ($stat === 'Angsuran') {
            $angsuran++;
        } else {
            $belum++;
        }
        $total++;
    }
    $stmt->close();
    return [
        'total' => $total,
        'lunas' => $lunas,
        'angsuran' => $angsuran,
        'belum' => $belum,
        'ppdb' => $ppdb,
    ];
}

$rekap = rekapTotal($conn, $unit, $uang_pangkal_id, $spp_id);
$hariini = rekapHariIni($conn, $unit, $uang_pangkal_id, $spp_id);
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Laporan Detail <?= htmlspecialchars($unit) ?> – SPMB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/sidebar_pendaftaran_styles.css" />
  <link rel="stylesheet" href="../assets/css/laporan_detail.css" />
  <style>
    .report-card.ppdb {background: linear-gradient(135deg, #17c7e7 0%, #2cb5e8 100%); color: #fff;}
    .report-card.ppdb .count {color:#fff;}
    .card.ppdb {background: linear-gradient(135deg, #17c7e7 0%, #2cb5e8 100%) !important; color: #fff !important;}
    .card.ppdb .count {color:#fff;}
    .report-card {
      cursor: pointer;
    }
  </style>
</head>
<body>
<?php
$active = 'laporan';
include 'sidebar_pendaftaran.php';
?>
<div class="main">
  <header class="navbar">
    <div class="title">Laporan Detail – <?= htmlspecialchars($unit) ?></div>
    <div class="user-menu">
      <small>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? '') ?></small>
      <a href="../logout/logout_pendaftaran.php" class="btn-logout">Logout</a>
    </div>
  </header>
  <div class="container mt-4">

    <h5>Rekap Total (Semua Data)</h5>
    <div class="row g-2 mb-4">
      <div class="col">
        <div class="report-card report-blue">
          <b>Total Siswa</b><br>
          <span class="count"><?= $rekap['total'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-green" onclick="showDetail('lunas')">
          <b>Lunas</b><br>
          <span class="count"><?= $rekap['lunas'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-yellow" onclick="showDetail('angsuran')">
          <b>Angsuran</b><br>
          <span class="count"><?= $rekap['angsuran'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-red" onclick="showDetail('belum')">
          <b>Belum Bayar</b><br>
          <span class="count"><?= $rekap['belum'] ?></span>
        </div>
      </div>
      <?php if ($rekap['ppdb'] > 0): ?>
      <div class="col">
        <div class="report-card ppdb" onclick="showDetail('ppdb')">
          <b>PPDB Bersama</b><br>
          <span class="count"><?= $rekap['ppdb'] ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <h5>Rekap Harian</h5>
    <form id="formCariTanggal" class="d-flex align-items-center mb-2" style="gap:12px;">
      <label for="tanggalCari" class="form-label mb-0">Pilih Tanggal:</label>
      <input type="date" id="tanggalCari" class="form-control" style="max-width:170px" value="<?= date(
          'Y-m-d'
      ) ?>">
    </form>
    <div id="rekapHarian">
      <div class="row g-2 mb-4">
        <div class="col"><div class="card p-3"><b>Daftar Hari Ini</b><br><span class="count text-muted">…</span></div></div>
        <div class="col"><div class="card p-3 text-success" onclick="showDetail('lunas')"><b>Lunas</b><br><span class="count text-muted">…</span></div></div>
        <div class="col"><div class="card p-3 text-warning" onclick="showDetail('angsuran')"><b>Angsuran</b><br><span class="count text-muted">…</span></div></div>
        <div class="col"><div class="card p-3 text-danger" onclick="showDetail('belum')"><b>Belum Bayar</b><br><span class="count text-muted">…</span></div></div>
        <div class="col"><div class="card p-3 ppdb" onclick="showDetail('ppdb')"><b>PPDB Bersama</b><br><span class="count text-muted">…</span></div></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detail List -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailModalLabel">Detail Daftar Siswa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p><strong>Status:</strong> <span id="detailStatus"></span></p>
        <table class="table table-striped table-bordered">
          <thead>
            <tr><th>No</th><th>Nama Siswa</th></tr>
          </thead>
          <tbody id="detailBody">
            <tr><td colspan="2" class="text-center">Memuat...</td></tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/sidebar_pendaftaran.js"></script>
<script>
function loadRekapHarian(tgl) {
  const rekapDiv = document.getElementById('rekapHarian');
  rekapDiv.innerHTML = `
    <div class="row g-2 mb-4">
      <div class="col"><div class="card p-3"><b>Daftar Hari Ini</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 text-success" onclick="showDetail('lunas')"><b>Lunas</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 text-warning" onclick="showDetail('angsuran')"><b>Angsuran</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 text-danger" onclick="showDetail('belum')"><b>Belum Bayar</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 ppdb" onclick="showDetail('ppdb')"><b>PPDB Bersama</b><br><span class="count text-muted">Memuat...</span></div></div>
    </div>`;
  fetch('rekap_harian.php?tanggal='+encodeURIComponent(tgl))
    .then(r=>r.json()).then(d=>{
      rekapDiv.innerHTML = `
      <div class="row g-2 mb-4">
        <div class="col"><div class="card p-3"><b>Daftar</b><br><span class="count">${d.total}</span></div></div>
        <div class="col"><div class="card p-3 text-success" onclick="showDetail('lunas')"><b>Lunas</b><br><span class="count">${d.lunas}</span></div></div>
        <div class="col"><div class="card p-3 text-warning" onclick="showDetail('angsuran')"><b>Angsuran</b><br><span class="count">${d.angsuran}</span></div></div>
        <div class="col"><div class="card p-3 text-danger" onclick="showDetail('belum')"><b>Belum Bayar</b><br><span class="count">${d.belum}</span></div></div>
        <div class="col"><div class="card p-3 ppdb" onclick="showDetail('ppdb')"><b>PPDB Bersama</b><br><span class="count">${d.ppdb}</span></div></div>
      </div>`;
      window.rekapData = d; // Simpan data di global untuk modal
    }).catch(_=>{
      rekapDiv.innerHTML = `<div class="alert alert-danger">Gagal memuat data!</div>`;
    });
}

document.getElementById('tanggalCari').addEventListener('change',function(){
  loadRekapHarian(this.value);
});

window.addEventListener('DOMContentLoaded',function(){
  loadRekapHarian(document.getElementById('tanggalCari').value);
});

// Fungsi tampilkan modal detail nama siswa
function showDetail(status) {
  const modalTitle = document.getElementById('detailModalLabel');
  const statusSpan = document.getElementById('detailStatus');
  const tbody = document.getElementById('detailBody');

  if (!window.rekapData) {
    tbody.innerHTML = '<tr><td colspan="2" class="text-center">Data tidak tersedia</td></tr>';
    return;
  }

  let list = [];
  switch(status) {
    case 'lunas': list = window.rekapData.list_lunas || []; break;
    case 'angsuran': list = window.rekapData.list_angsuran || []; break;
    case 'belum': list = window.rekapData.list_belum || []; break;
    case 'ppdb': list = window.rekapData.list_ppdb || []; break;
    default: list = [];
  }

  statusSpan.textContent = status.charAt(0).toUpperCase() + status.slice(1);

  if (list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="2" class="text-center">Tidak ada data.</td></tr>';
  } else {
    tbody.innerHTML = list.map((nama, i) =>
      `<tr><td>${i + 1}</td><td>${nama}</td></tr>`
    ).join('');
  }

  const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
  detailModal.show();
}
</script>
</body>
</html>
