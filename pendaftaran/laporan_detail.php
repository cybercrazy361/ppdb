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

<!-- Modal -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-labelledby="modalDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDetailLabel">Detail Pendaftaran Harian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <table class="table table-bordered table-sm">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Status PPDB</th>
              <th>Status Pembayaran</th>
            </tr>
          </thead>
          <tbody id="detailTableBody">
            <tr><td colspan="3" class="text-center">Memuat...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

    <h5>Rekap Total (Semua Data)</h5>
    <div class="row g-2 mb-4">
      <div class="col">
        <div class="report-card report-blue">
          <b>Total Siswa</b><br>
          <span class="count"><?= $rekap['total'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-green">
          <b>Lunas</b><br>
          <span class="count"><?= $rekap['lunas'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-yellow">
          <b>Angsuran</b><br>
          <span class="count"><?= $rekap['angsuran'] ?></span>
        </div>
      </div>
      <div class="col">
        <div class="report-card report-red">
          <b>Belum Bayar</b><br>
          <span class="count"><?= $rekap['belum'] ?></span>
        </div>
      </div>
      <?php if ($rekap['ppdb'] > 0): ?>
      <div class="col">
        <div class="report-card ppdb">
          <b>PPDB Bersama</b><br>
          <span class="count"><?= $rekap['ppdb'] ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>
<div class="text-end mb-3">
  <button class="btn btn-outline-primary btn-sm" onclick="showDetail()">Lihat Detail Harian</button>
</div>

    <h5>Rekap Harian</h5>
    <form id="formCariTanggal" class="d-flex align-items-center mb-2" style="gap:12px;">
      <label for="tanggalCari" class="form-label mb-0">Pilih Tanggal:</label>
      <input type="date" id="tanggalCari" class="form-control" style="max-width:170px" value="<?= date(
          'Y-m-d'
      ) ?>">
    </form>
    <div id="rekapHarian">
      <!-- Akan diisi via JS/AJAX -->
      <div class="row g-2 mb-4">
        <div class="col"><div class="card p-3"><b>Daftar Hari Ini</b><br><span class="count">…</span></div></div>
        <div class="col"><div class="card p-3 text-success"><b>Lunas</b><br><span class="count">…</span></div></div>
        <div class="col"><div class="card p-3 text-warning"><b>Angsuran</b><br><span class="count">…</span></div></div>
        <div class="col"><div class="card p-3 text-danger"><b>Belum Bayar</b><br><span class="count">…</span></div></div>
        <div class="col"><div class="card p-3 ppdb"><b>PPDB Bersama</b><br><span class="count">…</span></div></div>
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
      <div class="col"><div class="card p-3 text-success"><b>Lunas</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 text-warning"><b>Angsuran</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 text-danger"><b>Belum Bayar</b><br><span class="count text-muted">Memuat...</span></div></div>
      <div class="col"><div class="card p-3 ppdb"><b>PPDB Bersama</b><br><span class="count text-muted">Memuat...</span></div></div>
    </div>`;
  fetch('rekap_harian.php?tanggal='+encodeURIComponent(tgl))
    .then(r=>r.json()).then(d=>{
      rekapDiv.innerHTML = `
      <div class="row g-2 mb-4">
        <div class="col"><div class="card p-3"><b>Daftar</b><br><span class="count">${d.total}</span></div></div>
        <div class="col"><div class="card p-3 text-success"><b>Lunas</b><br><span class="count">${d.lunas}</span></div></div>
        <div class="col"><div class="card p-3 text-warning"><b>Angsuran</b><br><span class="count">${d.angsuran}</span></div></div>
        <div class="col"><div class="card p-3 text-danger"><b>Belum Bayar</b><br><span class="count">${d.belum}</span></div></div>
        <div class="col"><div class="card p-3 ppdb"><b>PPDB Bersama</b><br><span class="count">${d.ppdb}</span></div></div>
      </div>`;
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

function showDetail() {
  const tgl = document.getElementById('tanggalCari').value;
  const tbody = document.getElementById('detailTableBody');
  tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">Memuat data...</td></tr>`;
  
  fetch('rekap_harian_detail.php?tanggal=' + encodeURIComponent(tgl))
    .then(r => r.json())
    .then(data => {
      if (Array.isArray(data) && data.length > 0) {
        tbody.innerHTML = data.map(row => `
          <tr>
            <td>${row.nama}</td>
            <td>${row.status_ppdb ?? '-'}</td>
            <td>${row.status_pembayaran}</td>
          </tr>
        `).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted">Tidak ada data</td></tr>`;
      }
      const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
      modal.show();
    })
    .catch(_ => {
      tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center">Gagal memuat data</td></tr>`;
    });
}

</script>
</body>
</html>
