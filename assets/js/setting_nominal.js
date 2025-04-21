// assets/js/setting_nominal.js

document.addEventListener('DOMContentLoaded', function () {
    // ========================
    // 1. Format Currency Input
    // ========================
    function formatCurrencyInput(input) {
        input.addEventListener('input', function () {
            let cursorPosition = input.selectionStart;
            let originalLength = input.value.length;

            // Hapus semua karakter non-digit
            let value = input.value.replace(/\D/g, '');
            if (value === '') {
                input.value = '';
                return;
            }

            // Format ke dalam format rupiah
            let formattedValue = new Intl.NumberFormat('id-ID').format(value);
            input.value = formattedValue;

            // Hitung perbedaan panjang setelah format
            let newLength = formattedValue.length;
            cursorPosition += newLength - originalLength;

            // Set kembali posisi kursor
            input.setSelectionRange(cursorPosition, cursorPosition);
        });
    }

    // Terapkan format rupiah pada semua input dengan kelas 'nominal-input'
    const nominalInputs = document.querySelectorAll('.nominal-input');
    nominalInputs.forEach(input => {
        formatCurrencyInput(input);
    });

    // ========================
    // 2. Tampilkan/Menyembunyikan Field Bulan
    // ========================
    function toggleBulanField(selectElement, bulanContainerId) {
        const jenisPembayaranName = selectElement.options[selectElement.selectedIndex].text.toLowerCase();
        const bulanContainer = document.getElementById(bulanContainerId);

        if (jenisPembayaranName === 'spp') {
            bulanContainer.style.display = 'block';
            bulanContainer.querySelector('select').setAttribute('required', 'required');
        } else {
            bulanContainer.style.display = 'none';
            bulanContainer.querySelector('select').removeAttribute('required');
            bulanContainer.querySelector('select').value = ''; // Reset nilai bulan
        }
    }

    // Tambahkan event listener pada dropdown jenis_pembayaran di tambah dan edit form
    const tambahJenisPembayaranSelect = document.getElementById('tambah_jenis_pembayaran');
    if (tambahJenisPembayaranSelect) {
        tambahJenisPembayaranSelect.addEventListener('change', function () {
            toggleBulanField(this, 'tambah_bulan_container');
        });
    }

    const editJenisPembayaranSelect = document.getElementById('edit_jenis_pembayaran');
    if (editJenisPembayaranSelect) {
        editJenisPembayaranSelect.addEventListener('change', function () {
            toggleBulanField(this, 'edit_bulan_container');
        });
    }

    // ========================
    // 3. Tambah Pengaturan Nominal (AJAX)
    // ========================
    const tambahNominalForm = document.getElementById('tambahNominalForm');
    if (tambahNominalForm) {
        tambahNominalForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('tambah-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const jenis_pembayaran = document.getElementById('tambah_jenis_pembayaran').value;
            const nominal_max = document.getElementById('tambah_nominal_max').value.trim();
            const bulan = document.getElementById('tambah_bulan').value;
            const csrf_token = tambahNominalForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = tambahNominalForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (jenis_pembayaran === '' || jenis_pembayaran === null) {
                errorMessages.push('Jenis Pembayaran harus dipilih.');
                isValid = false;
            }

            if (nominal_max === '') {
                errorMessages.push('Nominal Maksimum harus diisi.');
                isValid = false;
            } else {
                // Cek apakah nominal_max adalah angka dan lebih besar dari 0
                const numeric_nominal = parseFloat(nominal_max.replace(/\./g, ''));
                if (isNaN(numeric_nominal) || numeric_nominal <= 0) {
                    errorMessages.push('Nominal Maksimum harus berupa angka dan lebih besar dari 0.');
                    isValid = false;
                }
            }

            // Cek apakah jenis_pembayaran adalah SPP untuk mewajibkan bulan
            const jenisPembayaranName = tambahJenisPembayaranSelect.options[tambahJenisPembayaranSelect.selectedIndex].text.toLowerCase();
            if (jenisPembayaranName === 'spp' && (bulan === '' || bulan === null)) {
                errorMessages.push('Bulan harus dipilih untuk jenis pembayaran SPP.');
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
            const formData = new FormData(tambahNominalForm);

            fetch('update_nominal.php', { // Sesuaikan path sesuai lokasi file
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    return response.text(); // Mengubah respons menjadi teks terlebih dahulu
                })
                .then(text => {
                    console.log('Response Text:', text); // Log teks respons
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            alert(data.message);
                            // Reload halaman atau tambahkan pengaturan baru ke tabel tanpa reload
                            window.location.reload();
                        } else {
                            if (errorContainer) {
                                errorContainer.innerHTML = data.message;
                                errorContainer.style.display = 'block';
                            } else {
                                alert('Error: ' + data.message);
                            }
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response Text:', text);
                        if (errorContainer) {
                            errorContainer.innerHTML = 'Terjadi kesalahan saat memproses respons dari server.';
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Terjadi kesalahan saat memproses respons dari server.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menambahkan pengaturan nominal.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menambahkan pengaturan nominal.');
                    }
                });
        });
    }

    // ========================
    // 4. Edit Pengaturan Nominal (AJAX)
    // ========================
    const editNominalForm = document.getElementById('editNominalForm');
    if (editNominalForm) {
        editNominalForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('edit-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const pengaturan_id = document.getElementById('edit_pengaturan_id').value.trim();
            const jenis_pembayaran = document.getElementById('edit_jenis_pembayaran').value.trim();
            const nominal_max = document.getElementById('edit_nominal_max').value.trim();
            const bulan = document.getElementById('edit_bulan').value;
            const csrf_token = editNominalForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = editNominalForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (pengaturan_id === '' || pengaturan_id === null) {
                errorMessages.push('Pengaturan ID tidak valid.');
                isValid = false;
            }

            if (jenis_pembayaran === '' || jenis_pembayaran === null) {
                errorMessages.push('Jenis Pembayaran harus dipilih.');
                isValid = false;
            }

            if (nominal_max === '') {
                errorMessages.push('Nominal Maksimum harus diisi.');
                isValid = false;
            } else {
                // Cek apakah nominal_max adalah angka dan lebih besar dari 0
                const numeric_nominal = parseFloat(nominal_max.replace(/\./g, ''));
                if (isNaN(numeric_nominal) || numeric_nominal <= 0) {
                    errorMessages.push('Nominal Maksimum harus berupa angka dan lebih besar dari 0.');
                    isValid = false;
                }
            }

            // Cek apakah jenis_pembayaran adalah SPP untuk mewajibkan bulan
            const jenisPembayaranName = editJenisPembayaranSelect.options[editJenisPembayaranSelect.selectedIndex].text.toLowerCase();
            if (jenisPembayaranName === 'spp' && (bulan === '' || bulan === null)) {
                errorMessages.push('Bulan harus dipilih untuk jenis pembayaran SPP.');
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
            const formData = new FormData(editNominalForm);

            fetch('update_nominal.php', { // Sesuaikan path sesuai lokasi file
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    return response.text(); // Mengubah respons menjadi teks terlebih dahulu
                })
                .then(text => {
                    console.log('Response Text:', text); // Log teks respons
                    try {
                        const data = JSON.parse(text);
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
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response Text:', text);
                        if (errorContainer) {
                            errorContainer.innerHTML = 'Terjadi kesalahan saat memproses respons dari server.';
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Terjadi kesalahan saat memproses respons dari server.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat memperbarui pengaturan nominal.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat memperbarui pengaturan nominal.');
                    }
                });
        });
    }

    // ========================
    // 5. Hapus Pengaturan Nominal (AJAX)
    // ========================
    const deleteNominalForm = document.getElementById('deleteNominalForm');
    if (deleteNominalForm) {
        deleteNominalForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Reset error messages
            const errorContainer = document.getElementById('delete-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const pengaturan_id = document.getElementById('delete_pengaturan_id').value.trim();
            const csrf_token = deleteNominalForm.querySelector('input[name="csrf_token"]').value.trim();
            const action = deleteNominalForm.querySelector('input[name="action"]').value.trim();

            let isValid = true;
            let errorMessages = [];

            if (pengaturan_id === '' || pengaturan_id === null) {
                errorMessages.push('Pengaturan ID tidak valid.');
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
            const formData = new FormData(deleteNominalForm);

            fetch('delete_nominal.php', { // Sesuaikan path sesuai lokasi file
                method: 'POST',
                body: formData
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    return response.text(); // Mengubah respons menjadi teks terlebih dahulu
                })
                .then(text => {
                    console.log('Response Text:', text); // Log teks respons
                    try {
                        const data = JSON.parse(text);
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
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        console.error('Response Text:', text);
                        if (errorContainer) {
                            errorContainer.innerHTML = 'Terjadi kesalahan saat memproses respons dari server.';
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Terjadi kesalahan saat memproses respons dari server.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menghapus pengaturan nominal.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menghapus pengaturan nominal.');
                    }
                });
        });
    }

    // ========================
    // 6. Edit Button Click Handler
    // ========================
    const editButtons = document.querySelectorAll('.edit-nominal-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pengaturan_id = this.getAttribute('data-id');
            const jenis_pembayaran_id = this.getAttribute('data-jenis_pembayaran_id');
            const nominal_max = this.getAttribute('data-nominal');
            const bulan = this.getAttribute('data-bulan');

            // Isi data ke dalam form edit
            document.getElementById('edit_pengaturan_id').value = pengaturan_id;
            document.getElementById('edit_jenis_pembayaran').value = jenis_pembayaran_id;
            document.getElementById('edit_nominal_max').value = formatNumber(nominal_max);
            document.getElementById('edit_bulan').value = bulan !== 'null' && bulan !== '' ? bulan : '';

            // Tampilkan atau sembunyikan field bulan berdasarkan jenis_pembayaran
            toggleBulanField(editJenisPembayaranSelect, 'edit_bulan_container');

            // Tampilkan modal edit
            const modalEdit = new bootstrap.Modal(document.getElementById('modalEditNominal'));
            modalEdit.show();
        });
    });

    // ========================
    // 7. Delete Button Click Handler
    // ========================
    const deleteButtons = document.querySelectorAll('.delete-nominal-btn');
    const modalDeleteNominal = new bootstrap.Modal(document.getElementById('modalDeleteNominal'));

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pengaturan_id = this.getAttribute('data-id');
            const jenis_pembayaran = this.getAttribute('data-jenis');
            const nominal_max = this.getAttribute('data-nominal');
            const bulan = this.getAttribute('data-bulan');

            // Isi data ke dalam form hapus
            document.getElementById('delete_pengaturan_id').value = pengaturan_id;
            document.getElementById('delete_pengaturan_jenis').textContent = jenis_pembayaran;
            document.getElementById('delete_pengaturan_nominal').textContent = new Intl.NumberFormat('id-ID').format(nominal_max);
            document.getElementById('delete_pengaturan_bulan').textContent = bulan !== 'null' && bulan !== '' ? bulan : '-';

            // Tampilkan modal hapus
            modalDeleteNominal.show();
        });
    });

    // ========================
    // 8. Fungsi Format Number
    // ========================
    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }
});
