<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Bukti Pendaftaran Siswa Baru (<?= safe($row['no_formulir']) ?>)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/print_bukti_pendaftaran.css" />
</head>
<body>
<?php if (!isset($_GET['send_wa'])): // Tombol hanya muncul di tampilan web, bukan PDF ?>
  <button id="btnPrint" onclick="window.print()" style="display:inline-block;margin:10px 0 16px 0;padding:7px 18px;font-size:14px;background:#213b82;color:#fff;border:none;border-radius:6px;cursor:pointer;">
    <i class="fas fa-print"></i> Cetak / Simpan PDF
  </button>
  <button id="btnWA" onclick="window.location.href='<?= $_SERVER['PHP_SELF'] . '?id=' . $id . '&send_wa=1' ?>'" style="display:inline-block;margin:10px 0 16px 12px;padding:7px 18px;font-size:14px;background:#25d366;color:#fff;border:none;border-radius:6px;cursor:pointer;">
    <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp Orang Tua
  </button>
<?php endif; ?>

  <div class="container">
    <!-- KOP SURAT: Logo kiri, info tetap center -->
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
      <?php if ($status_pembayaran === 'Lunas' || $status_pembayaran === 'Angsuran'): ?>
        <div class="sub-title"><b>BUKTI PENDAFTARAN MURID BARU</b></div>
      <?php else: ?>
        <div class="sub-title"><b>BUKTI PENDAFTARAN CALON MURID BARU</b></div>
      <?php endif; ?>
      <div class="tahun-ajaran"><b>SISTEM PENERIMAAN MURID BARU (SPMB)</b></div>
      <div class="tahun-ajaran"><b>SMA DHARMA KARYA JAKARTA</b></div>
      <div class="tahun-ajaran" style="font-size:12px;"><b>TAHUN AJARAN 2025/2026</b></div>
    </div>

    <div class="no-reg-bar">
      <div class="no-reg-row" style="margin-bottom:0;">
        <div class="no-reg-label"><b>No. Registrasi Pendaftaran</b></div>
        <div class="no-reg-sep">:</div>
        <div class="no-reg-val"><b><i><?= safe($row['no_formulir']) ?></i></b></div>
      </div>
      <?php if (!empty($row['reviewed_by'])): ?>
        <span class="callcenter-badge">
          <i class="fas fa-headset"></i>
          <b>Call Center:</b> <?= safe($row['reviewed_by']) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if ($status_pembayaran !== 'Belum Bayar' && !empty($no_invoice)): ?>
      <div class="no-reg-row">
        <div class="no-reg-label"><b>No. Formulir Pendaftaran</b></div>
        <div class="no-reg-sep">:</div>
        <div class="no-reg-val"><b><i><?= safe($no_invoice) ?></i></b></div>
      </div>
    <?php endif; ?>

    <table class="data-table">
      <caption>DATA CALON PESERTA DIDIK BARU</caption>
      <tr><th>Tanggal Pendaftaran</th><td><?= tanggal_id($row['tanggal_pendaftaran']) ?></td></tr>
      <tr><th>Nama Calon Peserta Didik</th><td><?= safe($row['nama']) ?></td></tr>
      <tr><th>Jenis Kelamin</th><td><?= safe($row['jenis_kelamin']) ?></td></tr>
      <tr><th>Asal Sekolah SMP/MTs</th><td><?= safe($row['asal_sekolah']) ?></td></tr>
      <tr><th>Alamat Rumah</th><td><?= safe($row['alamat']) ?></td></tr>
      <tr><th>No. HP Siswa</th><td><?= safe($row['no_hp']) ?></td></tr>
      <tr><th>No. HP Orang Tua/Wali</th><td><?= safe($row['no_hp_ortu']) ?></td></tr>
      <tr><th>Pilihan Sekolah/Jurusan</th><td><?= safe($row['unit']) ?></td></tr>
    </table>

    <div class="status-keterangan-wrap">
      <table class="status-keterangan-table">
        <tr>
          <td class="status-ket-label">Status Pendaftaran</td>
          <td class="status-ket-sep">:</td>
          <td class="status-ket-value"><?= htmlspecialchars($status_pendaftaran) ?></td>
        </tr>
        <tr>
          <td class="status-ket-label">Keterangan</td>
          <td class="status-ket-sep">:</td>
          <td class="status-ket-value"><?= !empty($keterangan_pendaftaran) ? htmlspecialchars($keterangan_pendaftaran) : '-' ?></td>
        </tr>
      </table>
    </div>

    <table class="tagihan-table" style="margin-top:9px;">
      <tr>
        <th colspan="2" style="background:#e3eaf7;font-size:13.5px;text-align:center">
          <i class="fas fa-coins"></i> Keterangan Pembayaran
        </th>
      </tr>
      <?php if(count($tagihan)): foreach($tagihan as $tg): ?>
      <tr>
        <td><?= safe($tg['jenis']) ?></td>
        <td style="text-align:right;font-weight:600">
          Rp <?= number_format($tg['nominal'], 0, ',', '.') ?>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="2" style="text-align:center;color:#bb2222;">Belum ada tagihan yang diverifikasi.</td>
      </tr>
      <?php endif; ?>
    </table>

    <?php if ($status_pembayaran !== 'Belum Bayar' && count($pembayaran_terakhir)): ?>
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
        <?php foreach($pembayaran_terakhir as $b): ?>
        <tr>
          <td><?= safe($b['jenis']) ?></td>
          <td style="text-align:right;">Rp <?= number_format($b['jumlah'],0,',','.') ?></td>
          <td style="text-align:right;">
            <?= ($b['cashback'] ?? 0) > 0 ? 'Rp ' . number_format($b['cashback'],0,',','.') : '-' ?>
          </td>
          <td><?= safe($b['status_pembayaran']) ?></td>
          <td><?= $b['bulan'] ? safe($b['bulan']) : '-' ?></td>
          <td class="tgl-lebar"><?= tanggal_id($b['tanggal_pembayaran']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <div class="status-row">
      Status Pembayaran: <?= getStatusBadge($status_pembayaran) ?>
    </div>

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
        Siswa telah melakukan pembayaran sebagian (angsuran).<br>
        Simpan bukti ini sebagai tanda terima pembayaran.
      <?php elseif ($status_pembayaran === 'Lunas'): ?>
        <b>Catatan:</b><br>
        Siswa telah menyelesaikan seluruh pembayaran.<br>
        Simpan bukti ini sebagai tanda lunas dan konfirmasi pendaftaran.
      <?php else: ?>
        <b>Catatan:</b><br>
        Status pembayaran tidak diketahui.
      <?php endif; ?>
    </div>

    <div class="footer-ttd-kanan">
      <div class="ttd-block-kanan">
        <div class="ttd-tanggal-kanan">Jakarta, <?= tanggal_id(date('Y-m-d')) ?></div>
        <div class="ttd-petugas-kanan"><?= safe($petugas) ?></div>
        <div class="ttd-label-kanan">(Petugas Pendaftaran)</div>
      </div>
    </div>
  </div>
</body>
</html>
