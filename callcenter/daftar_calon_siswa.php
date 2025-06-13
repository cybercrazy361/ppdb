<?php
// callcenter/daftar_calon_siswa.php
session_start();
include '../database_connection.php';

// 1) Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'callcenter') {
    header('Location: login_callcenter.php');
    exit();
}

// --- FILTER & Pencarian ---
$unit = $_SESSION['unit'];
$search = trim($_GET['q'] ?? '');
$pj_filter = trim($_GET['pj'] ?? '');

$where = [];
$params = [];
$types = '';

if ($unit !== 'Yayasan') {
    $where[] = "cp.pilihan = ?";
    $params[] = $unit;
    $types   .= 's';
}
if ($pj_filter) {
    $where[] = "cp.pj_username = ?";
    $params[] = $pj_filter;
    $types   .= 's';
}
if ($search) {
    $where[] = "(cp.nama LIKE ? OR cp.no_hp LIKE ? OR cp.asal_sekolah LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types   .= 'sss';
}

// 2) Query utama data calon_pendaftar JOIN callcenter
$sql = "SELECT cp.id, cp.nama, cp.jenis_kelamin, cp.asal_sekolah, cp.no_hp, cp.alamat,
               cp.pendidikan_ortu, cp.pekerjaan_ortu, cp.no_hp_ortu, cp.pilihan, cp.tanggal_daftar,
               cp.status, cp.notes, cp.pj_username, cc.nama as pj_nama
        FROM calon_pendaftar cp
        LEFT JOIN callcenter cc ON cp.pj_username = cc.username";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY cp.id DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 3) Ambil daftar PJ sesuai unit login saja
$pj_list = [];
$sql_pj = "SELECT username, nama FROM callcenter WHERE unit = ? ORDER BY nama ASC";
$stmt_pj = $conn->prepare($sql_pj);
$stmt_pj->bind_param("s", $unit);
$stmt_pj->execute();
$res_pj = $stmt_pj->get_result();
while($pj = $res_pj->fetch_assoc()) $pj_list[] = $pj;
$stmt_pj->close();

$calon = [];
while ($row = $result->fetch_assoc()) $calon[] = $row;
$stmt->close();

// 4) Daftar status dinamis & rekap
$status_list = [
    'PPDB Bersama'        => 'PPDB Bersama',
    'Sudah Bayar'         => 'Sudah Bayar',
    'Uang Titipan'        => 'Uang titipan sudah masuk',
    'Akan Bayar'          => 'Akan melakukan pembayaran',
    'Menunggu Negeri'     => 'Menunggu sekolah negeri',
    'Menunggu Progres'     => 'Menunggu progres',
    'Tidak Ada Konfirmasi'=> 'Tidak ada konfirmasi',
    'Tidak Jadi'          => 'Tidak jadi daftar'
];
$rekap = array_fill_keys(array_keys($status_list), 0);
foreach ($calon as $row) {
    $st = $row['status'];
    if (isset($rekap[$st])) $rekap[$st]++;
}

// Fungsi cek sudah terkirim
function sudah_terkirim($conn, $nama, $tanggal_daftar) {
    $stmt = $conn->prepare("SELECT COUNT(*) as jml FROM siswa WHERE nama=? AND tanggal_pendaftaran=?");
    $stmt->bind_param("ss", $nama, $tanggal_daftar);
    $stmt->execute();
    $result = $stmt->get_result();
    $jml = $result->fetch_assoc()['jml'];
    $stmt->close();
    return $jml > 0;
}
function tanggal_indo($tgl) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $tgl)) {
        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        $exp = explode('-', substr($tgl, 0, 10));
        return intval($exp[2]) . ' ' . $bulan[intval($exp[1])] . ' ' . $exp[0];
    }
    return $tgl;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Progres Pendaftaran - PPDB</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/sidebar_callcenter_styles.css">
  <link rel="stylesheet" href="../assets/css/progres_pendaftaran_styles.css">
</head>
<body>
    <?php $active = 'calonsiswa'; ?>
    <?php include 'sidebar_callcenter.php'; ?>
    <div class="main">
        <header class="navbar">
            <button class="toggle-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="title">Progres Pendaftaran Calon Siswa</div>
            <div class="user-menu">
                <small>Halo, <?= htmlspecialchars($_SESSION['nama'] ?? '') ?></small>
                <a href="../callcenter/logout_callcenter.php" class="btn-logout">Logout</a>
            </div>
        </header>
        <div class="main-container">
            <div class="card-wrapper shadow-sm">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-2 gap-2">
                    <h4 class="mb-0 fw-bold">Progres Pendaftaran</h4>
                </div>
                <!-- Box Rekap Status -->
                <div class="rekap-box mb-3">
                    <?php foreach($rekap as $key => $count):
                        $cls = 'status-'.strtolower(str_replace(' ', '-', $key));
                    ?>
                    <span class="badge <?= $cls ?> filter-status-badge" data-status="<?= $key ?>"><?= $key ?>: <?= $count ?></span>
                    <?php endforeach; ?>
                    <span class="badge bg-dark filter-status-badge" data-status="">Semua</span>
                </div>
                
                <!-- Tombol Download Template Excel -->
                <a href="../assets/template/template_calon_pendaftar.xlsx" class="btn btn-success btn-sm mb-2" download>
                  <i class="fas fa-download"></i> Download Template Excel
                </a>
                <div class="mb-3 d-flex gap-2 flex-wrap">
                  <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" class="form-control form-control-sm" placeholder="Cari Nama / No HP / Asal Sekolah">
                    <select name="pj" class="form-select form-select-sm">
                      <option value="">-- Semua PJ --</option>
                      <?php foreach($pj_list as $pj): ?>
                        <option value="<?= htmlspecialchars($pj['username']) ?>" <?= (isset($_GET['pj']) && $_GET['pj'] == $pj['username'] ? 'selected' : '') ?>>
                          <?= htmlspecialchars($pj['nama']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i> Cari</button>
                    <?php if(!empty($_GET['q']) || !empty($_GET['pj'])): ?>
                      <a href="<?= strtok($_SERVER["REQUEST_URI"],'?') ?>" class="btn btn-sm btn-outline-danger" title="Reset"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                  </form>
                </div>

                <!-- =========================== -->
                <!--     Form Upload Excel       -->
                <!-- =========================== -->
                <div class="mb-3">
                  <form id="formUploadExcel" enctype="multipart/form-data" method="post" action="upload_excel.php" class="d-flex align-items-center gap-2">
                    <input type="file" name="excel_file" accept=".xlsx,.xls" class="form-control form-control-sm" required>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Upload Excel</button>
                  </form>
                  <div id="uploadResult" class="mt-2"></div>
                </div>
                
                <div class="table-responsive">
                    <table id="calonTable" class="table table-hover table-bordered align-middle">
    <thead>
    <tr>
        <th class="no-col">No</th>
        <th class="nama-col">Nama</th>
        <th class="jk-col">Jenis Kelamin</th>
        <th class="asal-col">Asal Sekolah</th>
        <th class="hp-col">No HP</th>
        <th class="alamat-col d-none">Alamat</th>
        <th class="pendidikan-col d-none">Pendidikan Ortu/Wali</th>
        <th class="pekerjaan-col d-none">Pekerjaan Ortu/Wali</th>
        <th class="hp-ortu-col">No HP Ortu/Wali</th>
        <th class="tgl-col">Tanggal Daftar</th>
        <th class="status-col">Status</th>
        <th class="pj-col">Penanggung Jawab</th>
        <th class="ket-col">Keterangan</th>
        <th class="kirim-col">Kirim</th>
    </tr>
    </thead>
<tbody>
<?php $no=1; foreach ($calon as $row):
    $current = $row['status'];
    $notes   = $row['notes'];
    $terkirim = sudah_terkirim($conn, $row['nama'], $row['tanggal_daftar']);
?>
<tr data-id="<?= $row['id'] ?>">
    <td><?= $no++ ?></td>
    <td><?= htmlspecialchars($row['nama']) ?></td>
    <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
    <td><?= htmlspecialchars($row['asal_sekolah']) ?></td>
    <td>
      <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['no_hp'])) ?>" target="_blank">
        <?= htmlspecialchars($row['no_hp']) ?>
      </a>
    </td>
    <td class="alamat-col d-none"><?= htmlspecialchars($row['alamat']) ?></td>
    <td class="pendidikan-col d-none"><?= htmlspecialchars($row['pendidikan_ortu']) ?></td>
    <td class="pekerjaan-col d-none"><?= htmlspecialchars($row['pekerjaan_ortu'] ?? '-') ?></td>
    <td>
      <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['no_hp_ortu'])) ?>" target="_blank">
        <?= htmlspecialchars($row['no_hp_ortu']) ?>
      </a>
    </td>
    <td><?= tanggal_indo($row['tanggal_daftar']) ?></td>
    <td class="status-col text-center">
        <span class="d-none status-search-text"><?= htmlspecialchars($current) ?></span>
<select class="status-select form-select form-select-sm status-<?= strtolower(str_replace(' ', '-', $current)) ?>">
    <option value="">- Pilih Status -</option> <!-- Tambahkan ini sebagai opsi default -->
    <?php foreach ($status_list as $st => $desc): ?>
        <option value="<?= $st ?>" <?= $st === $current ? 'selected' : '' ?>><?= $desc ?></option>
    <?php endforeach; ?>
</select>

    </td>
    <td>
<select class="pj-select form-select form-select-lg" data-id="<?= $row['id'] ?>">
    <option value="">- Pilih PJ -</option>
    <?php foreach($pj_list as $pj): ?>
      <option value="<?= $pj['username'] ?>" <?= ($row['pj_username'] ?? '') == $pj['username'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($pj['nama']) ?>
      </option>
    <?php endforeach; ?>
</select>

</td>

    <td class="text-center">
        <button type="button"
            class="btn btn-sm btn-outline-primary btn-notes"
            data-id="<?= $row['id'] ?>"
            data-nama="<?= htmlspecialchars($row['nama']) ?>"
            data-notes="<?= htmlspecialchars($notes) ?>">
            <i class="fas fa-sticky-note"></i> Lihat/Edit
        </button>
    </td>
    <td class="text-center">
        <?php if ($terkirim): ?>
        <span class="badge bg-success">Terkirim</span>
        <?php else: ?>
        <button class="btn btn-sm btn-success btn-kirim"
                data-id="<?= $row['id'] ?>"
                data-nama="<?= htmlspecialchars($row['nama']) ?>"
                data-jenis_kelamin="<?= htmlspecialchars($row['jenis_kelamin']) ?>"
                data-asal_sekolah="<?= htmlspecialchars($row['asal_sekolah']) ?>"
                data-no_hp="<?= htmlspecialchars($row['no_hp']) ?>"
                data-alamat="<?= htmlspecialchars($row['alamat']) ?>"
                data-pendidikan_ortu="<?= htmlspecialchars($row['pendidikan_ortu']) ?>"
                data-no_hp_ortu="<?= htmlspecialchars($row['no_hp_ortu']) ?>"
                data-tanggal_daftar="<?= htmlspecialchars($row['tanggal_daftar']) ?>"
                data-unit="<?= htmlspecialchars($unit) ?>"
        ><i class="fas fa-paper-plane"></i> Kirim</button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>

</table>
                </div>
            </div>
        </div>
    </div>
<!-- Modal Notes -->
<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formNotesModal">
      <div class="modal-header">
        <h5 class="modal-title" id="notesModalLabel">Edit Keterangan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="notes_id">
        <div class="mb-2">
          <label class="form-label">Nama Siswa:</label>
          <div class="fw-bold" id="notes_nama"></div>
        </div>
        <div>
          <label for="notes_text" class="form-label">Keterangan</label>
          <textarea id="notes_text" class="form-control" rows="5" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-gradient">Simpan</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Notifikasi -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notifModalLabel">Informasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="notifModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- JS: jQuery, Bootstrap, DataTables -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function(){
  // Global status filter
  let statusFilter = '';
  const table = $('#calonTable').DataTable({
    pageLength: 10,
    lengthMenu: [5,10,25,50],
    order: [[0,'asc']],
    language:{
      search:     "Cari:",
      lengthMenu: "_MENU_ entri per halaman",
      info:       "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
      paginate:{ previous:"Sebelumnya", next:"Berikutnya" }
    }
  });

  // Custom filter: Badge filter status berfungsi walau kolom status berupa <select>
  $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
    if(settings.nTable.id !== 'calonTable') return true;
    if(!statusFilter) return true;
    var row = table.row(dataIndex).node();
    var val = $(row).find('select.status-select').val();
    return val === statusFilter;
  });

  // Klik badge filter status
$('.filter-status-badge').on('click', function(){
    statusFilter = $(this).data('status') || '';
    table.draw();
});

  // Nomor urut update setiap sorting, search, pagination
  table.on('order.dt search.dt draw.dt', function() {
    table.column(0, { search: 'applied', order: 'applied', page: 'current' })
      .nodes()
      .each(function(cell, i) { cell.innerHTML = i + 1; });
  });

  // Modal notes
  let notesModal = new bootstrap.Modal(document.getElementById('notesModal'));
  $('#calonTable').on('click', '.btn-notes', function(){
    const id   = $(this).data('id');
    const nama = $(this).data('nama');
    const notes= $(this).data('notes');
    $('#notes_id').val(id);
    $('#notes_nama').text(nama);
    $('#notes_text').val(notes);
    notesModal.show();
  });

  $('#formNotesModal').on('submit', function(e){
    e.preventDefault();
    const id = $('#notes_id').val();
    const notes = $('#notes_text').val();
    $.post('update_status.php', {id, notes}, function(res){
      if(!res.success){
        alert('Gagal menyimpan keterangan');
      } else {
        $(`tr[data-id="${id}"] .btn-notes`).data('notes', notes);
        notesModal.hide();
      }
    }, 'json');
  });

  // Status select update
const classes = {
    'PPDB Bersama':'status-ppdb-bersama',
    'Sudah Bayar':'status-sudah-bayar',
    'Uang Titipan':'status-uang-titipan',
    'Akan Bayar':'status-akan-bayar',
    'Menunggu Negeri':'status-menunggu-negeri',
    'Menunggu Proses':'status-menunggu-proses',
    'Tidak Ada Konfirmasi':'status-tidak-ada-konfirmasi',
    'Tidak Jadi':'status-tidak-jadi'
};

$('#calonTable').on('change', '.status-select', function(){
    const $sel = $(this),
          $row = $sel.closest('tr'),
          id   = $row.data('id'),
          status = $sel.val();

    // Jika kosong, reset class saja, jangan kirim ke backend
    if(!status) {
        $sel.removeClass().addClass('status-select form-select form-select-sm');
        return; // Stop
    }

    // Kalau tidak kosong, kirim ke backend
    $.post('update_status.php', {id, status}, function(res){
      if(res.success){
        $sel.removeClass().addClass('status-select form-select form-select-sm ' + classes[status]);
        location.reload();
      } else {
        alert('Error menyimpan status');
      }
    }, 'json');
});

  // PJ select update
  $('#calonTable').on('change', '.pj-select', function(){
    var $sel = $(this);
    var id = $sel.data('id');
    var pj_username = $sel.val();
    $.post('update_pj.php', {id: id, pj_username: pj_username}, function(res){
        if(res.success){
            $sel.removeClass('is-invalid').addClass('is-valid');
        } else {
            $sel.addClass('is-invalid');
            alert('Gagal update PJ!');
        }
    }, 'json');
  });

  // Kirim ke siswa (AJAX)
  $('#calonTable').on('click', '.btn-kirim', function(){
    const btn = $(this);
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Proses...');
    $.post('kirim_calon_ke_siswa.php', {
      id: btn.data('id'),
      nama: btn.data('nama'),
      jenis_kelamin: btn.data('jenis_kelamin'),
      asal_sekolah: btn.data('asal_sekolah'),
      no_hp: btn.data('no_hp'),
      alamat: btn.data('alamat'),
      pendidikan_ortu: btn.data('pendidikan_ortu'),
      no_hp_ortu: btn.data('no_hp_ortu'),
      tanggal_daftar: btn.data('tanggal_daftar'),
      unit: btn.data('unit')
    }, function(res){
      if(res.success){
        btn.parent().html('<span class="badge bg-success">Terkirim</span>');
        $('#notifModalLabel').text('Berhasil');
        $('#notifModalBody').html('Data calon pendaftar berhasil dikirim ke tabel siswa.<br><b>No Formulir:</b> ' + res.no_formulir);
      } else {
        btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Kirim');
        $('#notifModalLabel').text('Gagal Mengirim');
        $('#notifModalBody').html(res.message || 'Gagal mengirim data. Silakan coba lagi.');
      }
      $('#notifModal').modal('show');
    }, 'json');
  });

  // Upload Excel
  $('#formUploadExcel').on('submit', function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $('#uploadResult').html('<i class="fas fa-spinner fa-spin"></i> Upload...');
    $.ajax({
      url: 'upload_excel.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(res){
        if(res.success){
          $('#uploadResult').html('<span class="text-success">'+res.message+'</span>');
          setTimeout(() => location.reload(), 1500);
        } else {
          $('#uploadResult').html('<span class="text-danger">'+res.message+'</span>');
        }
      },
      error: function(){
        $('#uploadResult').html('<span class="text-danger">Terjadi kesalahan server!</span>');
      }
    });
  });
  
});
</script>
</body>
</html>
