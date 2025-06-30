<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
include '../database_connection.php';

function safe($str)
{
    return htmlspecialchars($str ?? '-');
}

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'pendaftaran') {
    die('Akses tidak diizinkan.');
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('ID siswa tidak valid.');
}

$stmt = $conn->prepare('SELECT * FROM siswa WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    die('Data siswa tidak ditemukan.');
}

$petugas = '-';
$username_petugas = $_SESSION['username'] ?? '';
if ($username_petugas) {
    $stmt_petugas = $conn->prepare(
        'SELECT nama FROM petugas WHERE username = ?'
    );
    $stmt_petugas->bind_param('s', $username_petugas);
    $stmt_petugas->execute();
    $result_petugas = $stmt_petugas->get_result();
    $data_petugas = $result_petugas->fetch_assoc();
    $petugas =
        $data_petugas && !empty($data_petugas['nama'])
            ? $data_petugas['nama']
            : $username_petugas;
    $stmt_petugas->close();
}

// Ambil status & notes dari calon_pendaftar
$status_pendaftaran = '-';
$keterangan_pendaftaran = '-';
if (!empty($row['calon_pendaftar_id'])) {
    $stmtStatus = $conn->prepare(
        'SELECT status, notes FROM calon_pendaftar WHERE id = ?'
    );
    $stmtStatus->bind_param('i', $row['calon_pendaftar_id']);
    $stmtStatus->execute();
    $rsStatus = $stmtStatus->get_result()->fetch_assoc();
    $status_pendaftaran = $rsStatus['status'] ?? '-';
    $keterangan_pendaftaran = $rsStatus['notes'] ?? '-';
    $stmtStatus->close();
}

function tanggal_id($tgl)
{
    if (!$tgl || $tgl == '0000-00-00') {
        return '-';
    }
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember',
    ];
    $date = date('d', strtotime($tgl));
    $month = $bulan[date('F', strtotime($tgl))];
    $year = date('Y', strtotime($tgl));
    return "$date $month $year";
}

// --- Status progres UANG PANGKAL: Berdasarkan total pembayaran (jumlah-cashback) vs tagihan --- //
$uang_pangkal_id = 1;

// Ambil tagihan uang pangkal
$tagihan_uang_pangkal = 0;
$stmt_tagihan_up = $conn->prepare("
    SELECT nominal FROM siswa_tagihan_awal WHERE siswa_id = ? AND jenis_pembayaran_id = ?
");
$stmt_tagihan_up->bind_param('ii', $id, $uang_pangkal_id);
$stmt_tagihan_up->execute();
$res_tagihan_up = $stmt_tagihan_up->get_result();
if ($row_up = $res_tagihan_up->fetch_assoc()) {
    $tagihan_uang_pangkal = (int) $row_up['nominal'];
}
$stmt_tagihan_up->close();

// Hitung total pembayaran uang pangkal (jumlah - cashback)
$total_bayar_uang_pangkal = 0;
$stmt_bayar_up = $conn->prepare("
    SELECT SUM(pd.jumlah - IFNULL(pd.cashback,0)) AS total_bayar
    FROM pembayaran_detail pd
    JOIN pembayaran p ON pd.pembayaran_id = p.id
    WHERE p.siswa_id = ? AND pd.jenis_pembayaran_id = ?
");
$stmt_bayar_up->bind_param('ii', $id, $uang_pangkal_id);
$stmt_bayar_up->execute();
$res_bayar_up = $stmt_bayar_up->get_result();
if ($row_bayar_up = $res_bayar_up->fetch_assoc()) {
    $total_bayar_uang_pangkal = (int) $row_bayar_up['total_bayar'];
}
$stmt_bayar_up->close();

// Tentukan status
if (
    $total_bayar_uang_pangkal >= $tagihan_uang_pangkal &&
    $tagihan_uang_pangkal > 0
) {
    $status_pembayaran = 'Lunas';
} elseif ($total_bayar_uang_pangkal > 0) {
    $status_pembayaran = 'Angsuran';
} else {
    $status_pembayaran = 'Belum Bayar';
}

// Tagihan awal
$tagihan = [];
$stmtTagihan = $conn->prepare("
    SELECT jp.nama AS jenis, sta.nominal
    FROM siswa_tagihan_awal sta
    JOIN jenis_pembayaran jp ON sta.jenis_pembayaran_id = jp.id
    WHERE sta.siswa_id = ?
    ORDER BY jp.id ASC
");
$stmtTagihan->bind_param('i', $id);
$stmtTagihan->execute();
$res = $stmtTagihan->get_result();
while ($t = $res->fetch_assoc()) {
    $tagihan[] = $t;
}
$stmtTagihan->close();

// Riwayat pembayaran terakhir + cashback
$pembayaran_terakhir = [];
if ($status_pembayaran !== 'Belum Bayar') {
    $stmtBayar = $conn->prepare("
        SELECT jp.nama AS jenis, pd.jumlah, pd.status_pembayaran, pd.bulan, p.tanggal_pembayaran, pd.cashback
        FROM pembayaran_detail pd
        JOIN pembayaran p ON pd.pembayaran_id = p.id
        JOIN jenis_pembayaran jp ON pd.jenis_pembayaran_id = jp.id
        WHERE p.siswa_id = ?
        ORDER BY p.tanggal_pembayaran DESC, pd.id DESC
        LIMIT 6
    ");
    $stmtBayar->bind_param('i', $id);
    $stmtBayar->execute();
    $resBayar = $stmtBayar->get_result();
    while ($b = $resBayar->fetch_assoc()) {
        $pembayaran_terakhir[] = $b;
    }
    $stmtBayar->close();
}

function getStatusBadge($status)
{
    $status = strtolower($status);
    if ($status === 'lunas') {
        return '<span style="color:#1cc88a;font-weight:bold"><i class="fas fa-check-circle"></i> Lunas</span>';
    }
    if ($status === 'angsuran') {
        return '<span style="color:#f6c23e;font-weight:bold"><i class="fas fa-hourglass-half"></i> Angsuran</span>';
    }
    return '<span style="color:#e74a3b;font-weight:bold"><i class="fas fa-times-circle"></i> Belum Bayar</span>';
}

$note_class = '';
if ($status_pembayaran === 'Belum Bayar') {
    $note_class = 'belum-bayar';
} elseif ($status_pembayaran === 'Angsuran') {
    $note_class = 'angsuran';
} elseif ($status_pembayaran === 'Lunas') {
    $note_class = 'lunas';
}

$no_invoice = $row['no_invoice'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/print_bukti_pendaftaran.css" />
</head>
<body>
  <div style="margin-bottom:16px;">
    <button id="btnPrint" type="button" style="padding:7px 18px;font-size:14px;background:#213b82;color:#fff;border:none;border-radius:6px;cursor:pointer;">
      <i class="fas fa-print"></i> Cetak / Simpan PDF
    </button>
    <button id="btnGenerateOnly" type="button" style="margin-left:8px;padding:7px 18px;font-size:14px;background:#1cc88a;color:#fff;border:none;border-radius:6px;cursor:pointer;">
      <i class="fas fa-file-pdf"></i> Generate jika cetak gagal
    </button>
    <button id="btnKirimWA" type="button" style="margin-left:8px;padding:7px 18px;font-size:14px;background:green;color:#fff;border:none;border-radius:6px;cursor:pointer;">
      <i class="fab fa-whatsapp"></i> Kirim PDF ke WA Ortu
    </button>
  </div>

  <!-- MODAL SUKSES GENERATE PDF/WA -->
  <div id="modalSuccess" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.30);">
    <div style="background:#fff;max-width:350px;margin:110px auto;padding:28px 22px;border-radius:12px;box-shadow:0 5px 16px #213b8230;text-align:center;">
      <div id="modalMessage" style="font-size:16px;font-weight:700;color:#1cc88a;margin-bottom:13px;"></div>
      <div id="pdfLinkBox" style="margin-bottom:18px"></div>
      <button onclick="document.getElementById('modalSuccess').style.display='none'" style="background:#213b82;color:#fff;padding:7px 22px;border-radius:7px;border:none;cursor:pointer;font-size:14px">Tutup</button>
    </div>
  </div>

  <div class="container">
    <div class="kop-surat-rel">
      <img src="../assets/images/logo_trans.png" alt="Logo" class="kop-logo-abs" />
      <div class="kop-info-center">
        <div class="kop-title1">YAYASAN PENDIDIKAN DHARMA KARYA</div>
        <div class="kop-title2">SMA/SMK DHARMA KARYA</div>
        <div class="kop-akreditasi"><b>Terakreditasi “A”</b></div>
        <div class="kop-alamat">Jalan Melawai XII No.2 Kav. 207A Kebayoran Baru Jakarta Selatan</div>
        <div class="kop-alamat">Telp. 021-7398578 / 7250224</div>
      </div>
    </div>
    <div class="kop-garis"></div>

   <div class="header-content">
  <div class="sub-title"><b>BUKTI PENDAFTARAN MURID BARU</b></div>
  <div class="tahun-ajaran"><b>SISTEM PENERIMAAN MURID BARU (SPMB)</b></div>
  <div class="tahun-ajaran"><b>SMA DHARMA KARYA JAKARTA</b></div>
  <div class="tahun-ajaran" style="font-size:12px;"><b>TAHUN AJARAN 2025/2026</b></div>
</div>


    <div class="no-reg-bar">
      <div class="no-reg-row" style="margin-bottom:0;">
        <div class="no-reg-label"><b>No. Registrasi </b></div>
        <div class="no-reg-sep">:</div>
        <div class="no-reg-val"><b><i><?= safe(
            $row['no_formulir']
        ) ?></i></b></div>
      </div>
      <?php if (!empty($row['reviewed_by'])): ?>
        <span class="callcenter-badge">
          <i class="fas fa-headset"></i>
          <b>Call Center:</b> <?= safe($row['reviewed_by']) ?>
        </span>
      <?php endif; ?>
    </div>
<?php
$is_ppdb_bersama = strtoupper($status_pendaftaran) === 'PPDB BERSAMA';
if (
    ($status_pembayaran !== 'Belum Bayar' || $is_ppdb_bersama) &&
    !empty($no_invoice)
): ?>
  <div class="no-reg-row">
    <div class="no-reg-label"><b>No. Formulir</b></div>
    <div class="no-reg-sep">:</div>
    <div class="no-reg-val"><b><i><?= safe($no_invoice) ?></i></b></div>
  </div>
<?php endif;
?>


    <table class="data-table">
      <caption>DATA CALON PESERTA MURID BARU</caption>
      <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id(
          $row['tanggal_pendaftaran']
      ) ?></td></tr>
      <tr><th>Nama Murid Baru</th><td><?= safe($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe(
          $row['asal_sekolah']
      ) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= safe(
          $row['no_hp_ortu']
      ) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe(
          $row['unit']
      ) ?></td></tr>
    </table>

    <div class="status-keterangan-wrap">
      <table class="status-keterangan-table">
        <tr>
          <td class="status-ket-label">Status Pendaftaran</td>
          <td class="status-ket-sep">:</td>
          <td class="status-ket-value"><?= htmlspecialchars(
              $status_pendaftaran
          ) ?></td>
        </tr>
        <tr>
          <td class="status-ket-label">Keterangan</td>
          <td class="status-ket-sep">:</td>
          <td class="status-ket-value"><?= !empty($keterangan_pendaftaran)
              ? htmlspecialchars($keterangan_pendaftaran)
              : '-' ?></td>
        </tr>
      </table>
    </div>

<?php if (strtoupper($status_pendaftaran) !== 'PPDB BERSAMA'): ?>
  <table class="tagihan-table" style="margin-top:9px;">
    <tr>
      <th colspan="2" style="background:#e3eaf7;font-size:13.5px;text-align:center">
        <i class="fas fa-coins"></i> Keterangan Pembayaran
      </th>
    </tr>
    <?php if (count($tagihan)):
        foreach ($tagihan as $tg): ?>
    <tr>
      <td><?= safe($tg['jenis']) ?></td>
      <td style="text-align:right;font-weight:600">
        Rp <?= number_format($tg['nominal'], 0, ',', '.') ?>
      </td>
    </tr>
    <?php endforeach;
    else:
         ?>
    <tr>
      <td colspan="2" style="text-align:center;color:#bb2222;">Belum ada tagihan yang diverifikasi.</td>
    </tr>
    <?php
    endif; ?>
  </table>

  <?php if (
      $status_pembayaran !== 'Belum Bayar' &&
      count($pembayaran_terakhir)
  ): ?>
    <div style="margin:9px 0 2px 0;font-size:12.5px;font-weight:500;">Riwayat Pembayaran:</div>
    <table class="tagihan-table riwayat-bayar" style="margin-bottom:9px;">
      <colgroup>
        <col style="width:18%">
        <col style="width:18%">
        <col style="width:18%">
        <col style="width:14%">
        <col style="width:10%">
        <col style="width:22%">
      </colgroup>
      <tr>
        <th>Jenis</th>
        <th>Nominal</th>
        <th>Cashback</th>
        <th>Status</th>
        <th>Bulan</th>
        <th>Tanggal</th>
      </tr>
      <?php foreach ($pembayaran_terakhir as $b): ?>
      <tr>
        <td><?= safe($b['jenis']) ?></td>
<td style="text-align:right;">Rp <?= number_format(
    $b['jumlah'] - ($b['cashback'] ?? 0),
    0,
    ',',
    '.'
) ?></td>

        <td style="text-align:right;">
          <?= ($b['cashback'] ?? 0) > 0
              ? 'Rp ' . number_format($b['cashback'], 0, ',', '.')
              : '-' ?>
        </td>
        <td><?= safe($b['status_pembayaran']) ?></td>
        <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
        <td class="tgl-lebar"><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>

<?php if (strtoupper($status_pendaftaran) !== 'PPDB BERSAMA'): ?>
  <div class="status-row">
    Status Pembayaran: <?= getStatusBadge($status_pembayaran) ?>
  </div>
<?php endif; ?>

    <div class="row-btm">
      <div class="info-contact">
        Informasi lebih lanjut hubungi:<br>
        Hotline SMA : <b>081511519271</b> (Bu Puji)
      </div>
    </div>

    <div class="note <?= $note_class ?>">
      <?php if ($status_pembayaran === 'Belum Bayar'): ?>
        <b>Catatan:</b><br>
        1. Apabila telah menyelesaikan administrasi, serahkan kembali form pendaftaran ini ke bagian pendaftaran untuk mendapatkan nomor Formulir.<br>
        2. Form Registrasi ini bukan menjadi bukti siswa tersebut diterima di SMA Dharma Karya. Siswa dinyatakan diterima apabila telah menyelesaikan administrasi dan mendapatkan nomor Formulir.
      <?php elseif ($status_pembayaran === 'Angsuran'): ?>
        <b>Catatan:</b><br>
        Simpan Form ini sebagai bukti telah melakukan pembayaran Administrasi.
      <?php elseif ($status_pembayaran === 'Lunas'): ?>
        <b>Catatan:</b><br>
        Simpan Form ini sebagai bukti telah melakukan pembayaran Administrasi.
      <?php else: ?>
        <b>Catatan:</b><br>
        Status pembayaran tidak diketahui.
      <?php endif; ?>
    </div>

    <div class="footer-ttd-kanan">
      <div class="ttd-block-kanan">
        <div class="ttd-tanggal-kanan">Jakarta, <?= tanggal_id(
            date('Y-m-d')
        ) ?></div>
        <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
        <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>

  <script>
  function showModalSuccess(judul, html) {
    document.getElementById('modalMessage').innerHTML = judul;
    document.getElementById('pdfLinkBox').innerHTML = html;
    document.getElementById('modalSuccess').style.display = '';
  }

  // Tombol Cetak/Generate PDF
  document.getElementById('btnPrint').onclick = function() {
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membuat PDF...';
    fetch('generate_bukti_pendaftaran.php?id=<?= $row[
        'id'
    ] ?>', { method:'GET' })
      .then(res => res.text())
      .then(html => {
        let pdfLink = '';
        const regex = /href=['"]([^'"]+\.pdf)['"]/i;
        const m = html.match(regex);
        if(m && m[1]) pdfLink = m[1];
        showModalSuccess('PDF Bukti Pendaftaran Berhasil Dibuat!', pdfLink
          ? '<a href="'+pdfLink+'" target="_blank" style="font-size:15px;font-weight:600;color:#213b82;">Download PDF</a>'
          : 'PDF berhasil dibuat. Silakan cek folder Bukti Pendaftaran.');
      })
      .catch(()=>{ alert('Gagal membuat PDF!'); })
      .finally(()=>{
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-print"></i> Cetak / Simpan PDF';
      });
  };

  // Tombol Generate jika cetak gagal
  document.getElementById('btnGenerateOnly').onclick = function() {
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Meng-generate PDF...';
    fetch('generate_bukti_pendaftaran.php?id=<?= $row[
        'id'
    ] ?>', { method:'GET' })
      .then(res => res.text())
      .then(html => {
        let pdfLink = '';
        const regex = /href=['"]([^'"]+\.pdf)['"]/i;
        const m = html.match(regex);
        if(m && m[1]) pdfLink = m[1];
        showModalSuccess('PDF Bukti Pendaftaran Berhasil Dibuat!', pdfLink
          ? '<a href="'+pdfLink+'" target="_blank" style="font-size:15px;font-weight:600;color:#213b82;">Download PDF</a>'
          : 'PDF berhasil dibuat. Silakan cek folder Bukti Pendaftaran.');
      })
      .catch(()=>{ alert('Gagal generate PDF!'); })
      .finally(()=>{
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-file-pdf"></i> Generate jika cetak gagal';
      });
  };

  // Tombol Kirim ke WA Ortu
  document.getElementById('btnKirimWA').onclick = function() {
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim ke WA...';
    fetch('kirim_wa_bukti_pendaftaran.php?id=<?= $row[
        'id'
    ] ?>', { method:'GET' })
      .then(res => res.text())
      .then(html => {
        showModalSuccess('Kirim PDF ke WhatsApp Ortu', html);
      })
      .catch(()=>{ alert('Gagal mengirim ke WhatsApp!'); })
      .finally(()=>{
        this.disabled = false;
        this.innerHTML = '<i class="fab fa-whatsapp"></i> Kirim PDF ke WA Ortu';
      });
  };
  </script>
</body>
</html>
