<?php
session_start();
include '../database_connection.php';

// 1) Validasi login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pendaftaran') {
    header('Location: login_pendaftaran.php');
    exit();
}

// 2) Ambil unit petugas
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT unit FROM petugas WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($unit);
$stmt->fetch();
$stmt->close();

// 3) Query data calon_pendaftar (TANPA EMAIL)
$sql = "SELECT id, nama, jenis_kelamin, asal_sekolah, no_hp, alamat, pendidikan_ortu, no_hp_ortu, pilihan, tanggal_daftar, status, notes
        FROM calon_pendaftar " .
       ($unit !== 'Yayasan' ? "WHERE pilihan = ? " : "") .
       "ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($unit !== 'Yayasan') {
    $stmt->bind_param("s", $unit);
}
$stmt->execute();
$result = $stmt->get_result();
$calon = [];
while ($row = $result->fetch_assoc()) $calon[] = $row;
$stmt->close();

// 4) Daftar status dinamis & inisialisasi rekap
$status_list = [
    'PPDB Bersama'        => 'Sudah melakukan pembayaran/PPDB Bersama',
    'Sudah Bayar'         => 'Sudah Bayar',              // <-- Tambahkan ini
    'Uang Titipan'        => 'Uang titipan sudah masuk',
    'Akan Bayar'          => 'Akan melakukan pembayaran',
    'Menunggu Negeri'     => 'Menunggu sekolah negeri',
    'Tidak Ada Konfirmasi'=> 'Tidak ada konfirmasi',
    'Tidak Jadi'          => 'Tidak jadi daftar'
];

$rekap = array_fill_keys(array_keys($status_list), 0);
// Hitung jumlah tiap status
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Review Calon Pendaftar</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    .status-ppdb-bersama { background:#198754 !important; color:#fff !important; }
    .status-uang-titipan { background:#6f42c1 !important; color:#fff !important; }
    .status-akan-bayar { background:#fd7e14 !important; color:#fff !important; }
    .status-menunggu-negeri { background:#ffc107 !important; color:#333 !important; }
    .status-tidak-ada-konfirmasi { background:#6c757d !important; color:#fff !important; }
    .status-tidak-jadi { background:#dc3545 !important; color:#fff !important; }
    .rekap-box { margin-bottom:16px; }
    .rekap-box .badge { font-size:1rem; padding:.5em 1em; cursor:pointer;}
    .rekap-box .badge.bg-dark { background: #343a40 !important; }
    .btn-back { float:right; }
    @media (max-width:600px){
      .rekap-box{ font-size:.9rem;}
      .table-responsive { font-size: 0.92rem;}
    }
  </style>
  <link rel="stylesheet" href="../assets/css/review_calon_pendaftar_styles.css">
</head>
<body>
  <div class="main-container">
    <div class="card-wrapper">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Review Calon Pendaftar</h4>
        <a href="dashboard_pendaftaran.php" class="btn btn-secondary btn-sm btn-back">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
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

      <div class="table-responsive">
        <table id="calonTable" class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama</th>
              <th>Jenis Kelamin</th>
              <th>Asal Sekolah</th>
              <th>No HP</th>
              <th>Alamat</th>
              <th>Pendidikan Ortu/Wali</th>
              <th>No HP Ortu/Wali</th>
              <th>Tanggal Daftar</th>
              <th>Status</th>
              <th>Keterangan</th>
              <th>Kirim</th>
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
              <td><?= htmlspecialchars($row['no_hp']) ?></td>
              <td><?= htmlspecialchars($row['alamat']) ?></td>
              <td><?= htmlspecialchars($row['pendidikan_ortu']) ?></td>
              <td><?= htmlspecialchars($row['no_hp_ortu']) ?></td>
              <td><?= htmlspecialchars($row['tanggal_daftar']) ?></td>
              <td class="text-center">
                <span class="d-none status-search-text"><?= htmlspecialchars($current) ?></span>
                <select class="status-select form-select form-select-sm status-<?= strtolower(str_replace(' ', '-', $current)) ?>">
                  <?php foreach ($status_list as $st => $desc): ?>
                  <option value="<?= $st ?>" <?= $st === $current ? 'selected' : '' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
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

  <!-- Modal untuk Notes -->
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
  // DataTable dan event badge filter tetap
  $.fn.dataTable.ext.search.push(
    function(settings, data, dataIndex) {
      if(settings.nTable.id !== 'calonTable') return true;
      var statusFilter = settings.aoPreSearchCols[9]?.sSearch || "";
      if(!statusFilter) return true;
      var td = settings.aoData[dataIndex].anCells[9];
      let value = $(td).find('.status-search-text').text().trim();
      if(!value) value = $(td).find('select').val();
      return value === statusFilter;
    }
  );
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
  table.on('order.dt search.dt draw.dt', function() {
    table.column(0, { search: 'applied', order: 'applied', page: 'current' })
      .nodes()
      .each(function(cell, i) { cell.innerHTML = i + 1; });
  });
  $('.filter-status-badge').on('click', function(){
    const status = $(this).data('status');
    table.column(9).search(status).draw();
  });

  // Notes modal
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
    'Uang Titipan':'status-uang-titipan',
    'Akan Bayar':'status-akan-bayar',
    'Menunggu Negeri':'status-menunggu-negeri',
    'Tidak Ada Konfirmasi':'status-tidak-ada-konfirmasi',
    'Tidak Jadi':'status-tidak-jadi'
  };
  $('#calonTable').on('change', '.status-select', function(){
    const $sel = $(this),
          $row = $sel.closest('tr'),
          id   = $row.data('id'),
          status = $sel.val();
    $.post('update_status.php', {id, status}, function(res){
      if(res.success){
        $sel.removeClass().addClass('status-select form-select form-select-sm ' + classes[status]);
        location.reload();
      } else {
        alert('Error menyimpan status');
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
});
</script>
</body>
</html>
