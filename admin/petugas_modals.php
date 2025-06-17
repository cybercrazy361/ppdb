<!-- Modal Tambah Petugas -->
<div class="modal fade" id="addPetugasModal" tabindex="-1" aria-labelledby="addPetugasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addPetugasModalLabel">Tambah Petugas Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_petugas.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Petugas</label>
                        <input type="text" class="form-control" id="nama" name="nama" placeholder="Masukkan nama petugas" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Unit</label>
                        <select class="form-select" id="unit" name="unit" required>
                            <option value="" disabled selected>Pilih unit</option>
                            <option value="Yayasan">Yayasan</option>
                            <option value="SMA">SMA</option>
                            <option value="SMK">SMK</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Petugas -->
<div class="modal fade" id="editPetugasModal" tabindex="-1" aria-labelledby="editPetugasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="editPetugasModalLabel">Edit Petugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="edit_petugas.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_nama" class="form-label">Nama Petugas</label>
                        <input type="text" class="form-control" id="edit_nama" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit" class="form-label">Unit</label>
                        <select class="form-select" id="edit_unit" name="unit" required>
                            <option value="Yayasan">Yayasan</option>
                            <option value="SMA">SMA</option>
                            <option value="SMK">SMK</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Delete -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteConfirmationModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="delete_petugas.php" method="POST">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus data <strong id="delete_nama"></strong>?</p>
                    <input type="hidden" name="id" id="delete_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal Hapus Akses (Akses Lain) -->
<div class="modal fade" id="hapusAksesModal" tabindex="-1" aria-labelledby="hapusAksesModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="hapus_akses_petugas.php" method="POST" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="hapusAksesModalLabel">Konfirmasi Hapus Akses</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="username" id="hapus_akses_username">
        <input type="hidden" name="role" id="hapus_akses_role">
        <input type="hidden" name="unit" id="hapus_akses_unit">
        <p>Yakin ingin menghapus akses <strong id="hapus_akses_label"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Hapus Akses</button>
      </div>
    </form>
  </div>
</div>
