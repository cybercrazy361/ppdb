// assets/js/setting_jenis_pembayaran.js

document.addEventListener('DOMContentLoaded', function () {
    // ========================
    // 1. Tambah Jenis Pembayaran (AJAX)
    // ========================
    const tambahJenisPembayaranForm = document.getElementById('tambahJenisPembayaranForm');
    if (tambahJenisPembayaranForm) {
        tambahJenisPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('tambah-jenis-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const nama_jenis_pembayaran = document.getElementById('tambah_nama_jenis_pembayaran').value.trim();
            const csrf_token = tambahJenisPembayaranForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = tambahJenisPembayaranForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (nama_jenis_pembayaran === '') {
                errorMessages.push('Nama Jenis Pembayaran harus diisi.');
                isValid = false;
            }

            if (!isValid) {
                if (errorContainer) {
                    errorContainer.innerHTML = errorMessages.join('<br>');
                    errorContainer.style.display = 'block';
                } else {
                    alert(errorMessages.join('\n'));
                }
                return;
            } else {
                if (errorContainer) {
                    errorContainer.style.display = 'none';
                }
            }

            // Persiapkan data untuk dikirim via AJAX
            const formData = new FormData(tambahJenisPembayaranForm);

            fetch('update_jenis_pembayaran.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json(); // Mengubah respons menjadi objek JavaScript
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        alert(data.message);
                        // Reload halaman atau tambahkan jenis pembayaran baru ke tabel tanpa reload
                        window.location.reload();
                    } else {
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menambahkan jenis pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menambahkan jenis pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 2. Edit Jenis Pembayaran (AJAX)
    // ========================
    const editJenisPembayaranForm = document.getElementById('editJenisPembayaranForm');
    if (editJenisPembayaranForm) {
        editJenisPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('edit-jenis-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const jenis_id = document.getElementById('edit_jenis_id').value.trim();
            const nama_jenis_pembayaran = document.getElementById('edit_nama_jenis_pembayaran').value.trim();
            const csrf_token = editJenisPembayaranForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = editJenisPembayaranForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (jenis_id === '' || jenis_id === null) {
                errorMessages.push('Jenis Pembayaran ID tidak valid.');
                isValid = false;
            }

            if (nama_jenis_pembayaran === '') {
                errorMessages.push('Nama Jenis Pembayaran harus diisi.');
                isValid = false;
            }

            if (!isValid) {
                if (errorContainer) {
                    errorContainer.innerHTML = errorMessages.join('<br>');
                    errorContainer.style.display = 'block';
                } else {
                    alert(errorMessages.join('\n'));
                }
                return;
            } else {
                if (errorContainer) {
                    errorContainer.style.display = 'none';
                }
            }

            // Persiapkan data untuk dikirim via AJAX
            const formData = new FormData(editJenisPembayaranForm);

            fetch('update_jenis_pembayaran.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json(); // Mengubah respons menjadi objek JavaScript
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        alert(data.message);
                        // Reload halaman atau perbarui tabel secara dinamis
                        window.location.reload();
                    } else {
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat memperbarui jenis pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat memperbarui jenis pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 3. Hapus Jenis Pembayaran (AJAX)
    // ========================
    const deleteJenisPembayaranForm = document.getElementById('deleteJenisPembayaranForm');
    if (deleteJenisPembayaranForm) {
        deleteJenisPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('delete-jenis-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const jenis_id = document.getElementById('delete_jenis_id').value.trim();
            const csrf_token = deleteJenisPembayaranForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = deleteJenisPembayaranForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (jenis_id === '' || jenis_id === null) {
                errorMessages.push('Jenis Pembayaran ID tidak valid.');
                isValid = false;
            }

            if (!isValid) {
                if (errorContainer) {
                    errorContainer.innerHTML = errorMessages.join('<br>');
                    errorContainer.style.display = 'block';
                } else {
                    alert(errorMessages.join('\n'));
                }
                return;
            } else {
                if (errorContainer) {
                    errorContainer.style.display = 'none';
                }
            }

            // Persiapkan data untuk dikirim via AJAX
            const formData = new FormData(deleteJenisPembayaranForm);

            fetch('delete_jenis_pembayaran.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json(); // Mengubah respons menjadi objek JavaScript
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        alert(data.message);
                        // Reload halaman atau hapus baris dari tabel tanpa reload
                        window.location.reload();
                    } else {
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menghapus jenis pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menghapus jenis pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 4. Edit Button Click Handler
    // ========================
    const editButtons = document.querySelectorAll('.edit-jenis-pembayaran-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const jenis_id = this.getAttribute('data-id');
            const nama_jenis_pembayaran = this.getAttribute('data-nama');

            // Isi data ke dalam form edit
            document.getElementById('edit_jenis_id').value = jenis_id;
            document.getElementById('edit_nama_jenis_pembayaran').value = nama_jenis_pembayaran;

            // Tampilkan modal edit
            const modalEdit = new bootstrap.Modal(document.getElementById('modalEditJenisPembayaran'));
            modalEdit.show();
        });
    });

    // ========================
    // 5. Delete Button Click Handler
    // ========================
    const deleteButtons = document.querySelectorAll('.delete-jenis-pembayaran-btn');
    const modalDeleteJenisPembayaran = new bootstrap.Modal(document.getElementById('modalDeleteJenisPembayaran'));
    const deleteJenisPembayaranFormElement = document.getElementById('deleteJenisPembayaranForm');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const jenis_id = this.getAttribute('data-id');
            const nama_jenis_pembayaran = this.getAttribute('data-nama');

            // Isi data ke dalam form hapus
            document.getElementById('delete_jenis_id').value = jenis_id;
            document.getElementById('delete_jenis_nama').textContent = nama_jenis_pembayaran;

            // Tampilkan modal hapus
            modalDeleteJenisPembayaran.show();
        });
    });

    // ========================
    // 6. Fungsi Format Number (Jika Diperlukan)
    // ========================
    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }
});
