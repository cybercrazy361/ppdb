$(document).ready(function () {
    // Add Siswa
    $('#addSiswaForm').submit(function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: 'add_siswa.php',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    $('#add-form-errors').removeClass('d-none').html(response.message);
                }
            },
            error: function () {
                $('#add-form-errors').removeClass('d-none').html('Terjadi kesalahan saat menambah siswa.');
            }
        });
    });

    // Edit Siswa - Populate Modal
    $('.edit-siswa-btn').click(function () {
        const siswaId = $(this).data('id');

        $.ajax({
            type: 'GET',
            url: 'get_siswa.php',
            data: { id: siswaId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const siswa = response.data;
                    $('#edit_siswa_id').val(siswa.id);
                    $('#edit_no_formulir').val(siswa.no_formulir);
                    $('#edit_nama').val(siswa.nama);
                    $('#edit_unit').val(siswa.unit);
                    $('#edit_jenis_kelamin').val(siswa.jenis_kelamin);
                    $('#edit_tahun_pelajaran').val(siswa.tahun_pelajaran);
                    $('#edit_tempat_lahir').val(siswa.tempat_lahir);
                    $('#edit_tanggal_lahir').val(siswa.tanggal_lahir);
                    $('#edit_asal_sekolah').val(siswa.asal_sekolah);
                    $('#edit_alamat').val(siswa.alamat);
                    $('#edit_no_hp').val(siswa.no_hp);
                    $('#edit_status_pembayaran').val(siswa.status_pembayaran);
                    $('#edit_metode_pembayaran').val(siswa.metode_pembayaran);
                    $('#edit_keterangan').val(siswa.keterangan);

                    $('#editSiswaModal').modal('show');
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Terjadi kesalahan saat mengambil data siswa.');
            }
        });
    });

    // Edit Siswa - Submit Form
    $('#editSiswaForm').submit(function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: 'update_siswa.php',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    $('#edit-form-errors').removeClass('d-none').html(response.message);
                }
            },
            error: function () {
                $('#edit-form-errors').removeClass('d-none').html('Terjadi kesalahan saat memperbarui siswa.');
            }
        });
    });

    // Delete Siswa - Populate Modal
    $('.delete-siswa-btn').click(function () {
        const siswaId = $(this).data('id');
        const siswaNama = $(this).closest('tr').find('td:nth-child(3)').text();

        $('#delete_siswa_id').val(siswaId);
        $('#delete_siswa_nama').text(siswaNama);
        $('#deleteSiswaModal').modal('show');
    });

    // Delete Siswa - Submit Form
    $('#deleteSiswaForm').submit(function (e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: 'delete_siswa.php',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    $('#delete-form-errors').removeClass('d-none').html(response.message);
                }
            },
            error: function () {
                $('#delete-form-errors').removeClass('d-none').html('Terjadi kesalahan saat menghapus siswa.');
            }
        });
    });

    // View Pembayaran
    $('.view-pembayaran-btn').click(function () {
        const siswaId = $(this).data('id');

        $.ajax({
            type: 'GET',
            url: 'get_pembayaran_siswa.php',
            data: { id: siswaId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    const pembayaran = response.data;
                    let html = '<h5>Pembayaran:</h5>';
                    html += '<table class="table table-bordered">';
                    html += '<thead><tr><th>No</th><th>Jenis Pembayaran</th><th>Jumlah</th><th>Bulan</th><th>Status</th></tr></thead><tbody>';

                    pembayaran.forEach((item, index) => {
                        html += `<tr>
                                            <td>${index + 1}</td>
                                            <td>${item.jenis_pembayaran}</td>
                                            <td>${new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(item.jumlah)}</td>
                                            <td>${item.bulan || '-'}</td>
                                            <td>${item.status_pembayaran}</td>
                                        </tr>`;
                    });

                    html += '</tbody></table>';
                    $('#pembayaran-details').html(html);
                    $('#viewPembayaranModal').modal('show');
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Terjadi kesalahan saat mengambil detail pembayaran.');
            }
        });
    });
});
