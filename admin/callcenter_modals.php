<!-- Modal Tambah Call Center -->
<div class="modal fade" id="addCallCenterModal" tabindex="-1" aria-labelledby="addCallCenterModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="add_callcenter.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCallCenterModalLabel">Tambah Petugas Call Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="nama" class="form-label">Nama</label>
          <input type="text" class="form-control" id="nama" name="nama" required>
        </div>
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
          <label for="unit" class="form-label">Unit</label>
          <select class="form-select" id="unit" name="unit" required>
            <option value="">-- Pilih Unit --</option>
            <option value="Yayasan">Yayasan</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-success">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Call Center -->
<div class="modal fade" id="editCallCenterModal" tabindex="-1" aria-labelledby="editCallCenterModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="edit_callcenter.php" method="POST" class="modal-content">
      <input type="hidden" id="edit_id" name="id">
      <div class="modal-header">
        <h5 class="modal-title" id="editCallCenterModalLabel">Edit Petugas Call Center</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="edit_nama" class="form-label">Nama</label>
          <input type="text" class="form-control" id="edit_nama" name="nama" required>
        </div>
        <div class="mb-3">
          <label for="edit_username" class="form-label">Username</label>
          <input type="text" class="form-control" id="edit_username" name="username" required>
        </div>
        <div class="mb-3">
          <label for="edit_password" class="form-label">Password <small>(Kosongkan jika tidak diganti)</small></label>
          <input type="password" class="form-control" id="edit_password" name="password">
        </div>
        <div class="mb-3">
          <label for="edit_unit" class="form-label">Unit</label>
          <select class="form-select" id="edit_unit" name="unit" required>
            <option value="">-- Pilih Unit --</option>
            <option value="Yayasan">Yayasan</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-warning">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Hapus Call Center -->
<div class="modal fade" id="deleteCallCenterModal" tabindex="-1" aria-labelledby="deleteCallCenterModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="delete_callcenter.php" method="POST" class="modal-content">
      <input type="hidden" id="delete_id" name="id">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteCallCenterModalLabel">Konfirmasi Hapus</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Yakin ingin menghapus petugas call center <strong id="delete_nama"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Hapus</button>
      </div>
    </form>
  </div>
</div>
