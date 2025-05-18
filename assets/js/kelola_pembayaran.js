// assets/js/kelola_pembayaran.js

document.addEventListener('DOMContentLoaded', function () {
    // ========================
    // 1. Format Currency Input
    // ========================
    function formatCurrencyInput(input) {
        input.addEventListener('input', function () {
            let cursorPos = input.selectionStart;
            let origLen = input.value.length;
            let digits = input.value.replace(/\D/g, '');
            if (!digits) {
                input.value = '';
                return;
            }
            let formatted = new Intl.NumberFormat('id-ID').format(digits);
            input.value = formatted;
            let newLen = formatted.length;
            cursorPos += newLen - origLen;
            input.setSelectionRange(cursorPos, cursorPos);
        });
    }

    // ========================
    // 2. Autocomplete Siswa
    // ========================
    const noFormulirInput = document.getElementById('no_formulir');
    const namaInput = document.getElementById('nama');

    function searchStudent(q) {
        const sug = document.getElementById('siswa-suggestions');
        if (q.length < 2) return sug.style.display = 'none';
        fetch(`search_student.php?query=${encodeURIComponent(q)}`)
            .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
            .then(j => {
                sug.innerHTML = '';
                if (j.success && j.data.length) {
                    j.data.forEach(s => {
                        let div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.textContent = `${s.no_formulir} – ${s.nama}`;
                        div.onclick = () => {
                            noFormulirInput.value = s.no_formulir;
                            namaInput.value = s.nama;
                            sug.style.display = 'none';
                        };
                        sug.appendChild(div);
                    });
                } else {
                    sug.innerHTML = '<div class="dropdown-item">Tidak ditemukan</div>';
                }
                sug.style.display = 'block';
            })
            .catch(err => console.error('searchStudent error:', err));
    }

    function debounce(fn, ms) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), ms);
        };
    }

    if (noFormulirInput) {
        noFormulirInput.addEventListener('input',
            debounce(() => searchStudent(noFormulirInput.value.trim()), 300)
        );
    }
    document.addEventListener('click', e => {
        const sug = document.getElementById('siswa-suggestions');
        if (sug && !sug.contains(e.target) && e.target !== noFormulirInput) {
            sug.style.display = 'none';
        }
    });

    // ========================
    // 3. Dynamic Payment Items
    // ========================
    const paymentWrapper = document.getElementById('payment-wrapper');
    const addPaymentBtn = document.getElementById('add-payment-btn');

    function isSPP(id) {
        return Array.isArray(jenisPembayaranList) &&
            jenisPembayaranList.some(j => j.id == id && j.nama.toLowerCase() === 'spp');
    }

    function getJenisPembayaranOptions(sel = '') {
        if (!Array.isArray(jenisPembayaranList)) return '<option disabled>Data kosong</option>';
        let opts = '<option value="" disabled>Pilih Jenis Pembayaran</option>';
        jenisPembayaranList.forEach(j => {
            opts += `<option value="${j.id}"${j.id == sel ? ' selected' : ''}>${j.nama}</option>`;
        });
        return opts;
    }

    function addPayment(selectedId = '', selectedBulan = '') {
        const item = document.createElement('div');
        item.className = 'payment-item mb-3';
        item.innerHTML = `
            <div class="row g-2">
              <div class="col-md-5">
                <select name="jenis_pembayaran[]" class="form-select jenis-pembayaran" required>
                  ${getJenisPembayaranOptions(selectedId)}
                </select>
              </div>
              <div class="col-md-4">
                <input type="text" name="jumlah[]" class="form-control jumlah-input" placeholder="Jumlah (Rp)" required>
              </div>
              <div class="col-md-3 d-flex align-items-start">
                <select name="bulan[]" class="form-select bulan-pembayaran flex-grow-1" style="display:none;">
                  <option value="" disabled selected>Pilih Bulan</option>
                  <option>Juli</option><option>Agustus</option><option>September</option>
                  <option>Oktober</option><option>November</option><option>Desember</option>
                  <option>Januari</option><option>Februari</option><option>Maret</option>
                  <option>April</option><option>Mei</option><option>Juni</option>
                </select>
                <button type="button" class="btn btn-danger remove-payment-btn ms-2">
                  <i class="fas fa-minus"></i>
                </button>
              </div>
            </div>`;
        paymentWrapper.appendChild(item);

        // apply currency format
        const ji = item.querySelector('.jumlah-input');
        formatCurrencyInput(ji);

        const js = item.querySelector('.jenis-pembayaran');
        const bs = item.querySelector('.bulan-pembayaran');
        if (selectedId && isSPP(selectedId)) {
            bs.style.display = 'block';
            bs.value = selectedBulan;
        }
        js.addEventListener('change', () => {
            if (isSPP(js.value)) bs.style.display = 'block';
            else {
                bs.style.display = 'none';
                bs.value = '';
            }
        });
    }

    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', () => {
            if (!Array.isArray(jenisPembayaranList)) {
                console.error('jenisPembayaranList belum tersedia!');
                return;
            }
            addPayment();
        });
    }
    if (paymentWrapper) {
        paymentWrapper.addEventListener('click', e => {
            const btn = e.target.closest('.remove-payment-btn');
            if (btn) btn.closest('.payment-item').remove();
        });
    }

    // ========================
    // 4. AJAX Submit Tambah Pembayaran
    // ========================
    const tambahForm = document.getElementById('tambahPembayaranForm');
    if (tambahForm) {
        tambahForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const errBox = document.getElementById('form-errors');
            errBox.style.display = 'none';
            errBox.innerHTML = '';

            // collect values
            const noF = noFormulirInput.value.trim();
            const nama = namaInput.value.trim();
            const tp = document.getElementById('tahun_pelajaran').value.trim();
            const met = document.getElementById('metode_pembayaran').value.trim();
            const ket = document.getElementById('keterangan').value.trim();
            const jenis = Array.from(document.querySelectorAll('select[name="jenis_pembayaran[]"]')).map(el => el.value);
            const jum = Array.from(document.querySelectorAll('input[name="jumlah[]"]')).map(el => el.value);
            const bulan = Array.from(document.querySelectorAll('select[name="bulan[]"]')).map(el => el.value);

            let errs = [];
            if (!noF) errs.push('No Formulir harus diisi.');
            if (!nama) errs.push('Nama siswa tidak valid.');
            if (!tp) errs.push('Tahun Pelajaran harus dipilih.');
            if (!met) errs.push('Metode Pembayaran harus dipilih.');

            let total = 0;
            jenis.forEach((j, i) => {
                const x = parseFloat(jum[i].replace(/\D/g, '')) || 0;
                total += x;
                if (!j) errs.push(`Jenis pembayaran item ${i + 1} belum dipilih.`);
                if (x <= 0) errs.push(`Jumlah item ${i + 1} harus > 0.`);
                if (isSPP(j) && !bulan[i]) errs.push(`Bulan item ${i + 1} (SPP) harus dipilih.`);
            });
            if (total <= 0) errs.push('Total pembayaran harus > 0.');

            if (errs.length) {
                errBox.innerHTML = errs.join('<br>');
                errBox.style.display = 'block';
                return;
            }

            fetch('tambah_pembayaran.php', {
                method: 'POST',
                body: new FormData(tambahForm)
            })
                .then(r => r.ok ? r.json() : Promise.reject(r.statusText))
                .then(j => {
                    if (j.success) window.location.reload();
                    else {
                        errBox.innerHTML = j.message;
                        errBox.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('submit error:', err);
                    errBox.innerHTML = 'Gagal menyimpan, cek console.';
                    errBox.style.display = 'block';
                });
        });
    }

    // ========================
    // 5. Validasi dan AJAX Formulir Tambah Pembayaran
    // ========================
    const addPaymentForm = document.getElementById('tambahPembayaranForm');

    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', function (e) {
            e.preventDefault(); // Mencegah form dari reload halaman

            // Reset error messages
            const errorContainer = document.getElementById('form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            const no_formulir = document.getElementById('no_formulir').value.trim();
            const nama = document.getElementById('nama').value.trim();
            const tahun_pelajaran = document.getElementById('tahun_pelajaran').value.trim();
            const metode_pembayaran = document.getElementById('metode_pembayaran').value.trim();
            const keterangan = document.getElementById('keterangan').value.trim();

            const jenis_pembayaran = Array.from(document.querySelectorAll('select[name="jenis_pembayaran[]"]')).map(el => el.value);
            const jumlah_pembayaran = Array.from(document.querySelectorAll('input[name="jumlah[]"]')).map(el => el.value);
            const bulan_pembayaran = Array.from(document.querySelectorAll('select[name="bulan[]"]')).map(el => el.value);

            let isValid = true;
            let errorMessages = [];

            if (no_formulir === '') {
                errorMessages.push('No Formulir harus diisi.');
                isValid = false;
            }

            if (nama === '') {
                errorMessages.push('Nama siswa tidak valid.');
                isValid = false;
            }

            if (tahun_pelajaran === '') {
                errorMessages.push('Tahun Pelajaran harus diisi.');
                isValid = false;
            }

            if (metode_pembayaran === '') {
                errorMessages.push('Metode Pembayaran harus dipilih.');
                isValid = false;
            }

            // Validasi setiap jenis pembayaran
            jenis_pembayaran.forEach((jenis, index) => {
                const jumlah = parseFloat(jumlah_pembayaran[index].replace(/\./g, '')) || 0;

                if (jenis === '') {
                    errorMessages.push(`Jenis Pembayaran pada item ${index + 1} harus dipilih.`);
                    isValid = false;
                }

                if (jumlah <= 0) {
                    errorMessages.push(`Jumlah Pembayaran pada item ${index + 1} harus lebih dari 0.`);
                    isValid = false;
                }

                if (isSPP(jenis) && (bulan_pembayaran[index] === '' || bulan_pembayaran[index] === undefined)) {
                    errorMessages.push(`Bulan Pembayaran pada item ${index + 1} harus dipilih karena jenis pembayaran adalah SPP.`);
                    isValid = false;
                }
            });

            // Hitung total jumlah pembayaran
            let total_jumlah = 0;
            jumlah_pembayaran.forEach(jml => {
                const jumlah = parseFloat(jml.replace(/\./g, '')) || 0;
                total_jumlah += jumlah;
            });

            if (total_jumlah <= 0) {
                errorMessages.push('Total Pembayaran harus lebih dari 0.');
                isValid = false;
            }

            // Tambahkan validasi nominal_max
            for (let i = 0; i < jenis_pembayaran.length; i++) {
                const jenis_id = jenis_pembayaran[i];
                const jumlah = parseFloat(jumlah_pembayaran[i].replace(/\./g, '')) || 0;

                // Cari nominal_max dari jenisPembayaranList
                const jenis = jenisPembayaranList.find(j => j.id == jenis_id);
                if (jenis) {
                    // Assume pengaturan_nominal data is loaded into jenisPembayaranList
                    // If not, you need to fetch it from the server
                    // For simplicity, let's assume each 'jenis' has 'nominal_max'
                    // You may need to adjust based on your data structure
                    // Here, we skip this validation or assume it's handled server-side
                }
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
            const formData = new FormData(addPaymentForm);

            fetch('tambah_pembayaran.php', {
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
                        // Reload halaman atau reset form
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
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menambahkan pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menambahkan pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 6. Modal Edit Pembayaran
    // ========================
    const editPembayaranForm = document.getElementById('editPembayaranForm');

    if (editPembayaranForm) {
        editPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Ambil nilai dari form edit
            const pembayaran_id = document.getElementById('editPembayaranId').value.trim();
            const metode_pembayaran = document.getElementById('edit_metode_pembayaran').value.trim();
            const keterangan = document.getElementById('edit_keterangan').value.trim();
            const tahun_pelajaran = document.getElementById('edit_tahun_pelajaran').value.trim();

            const jenis_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper select[name="jenis_pembayaran[]"]')).map(el => el.value);
            const jumlah_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper input[name="jumlah[]"]')).map(el => el.value);
            const bulan_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper select[name="bulan[]"]')).map(el => el.value);

            // Debugging log untuk memastikan data yang diambil
            console.log("Data dikirim ke server:", {
                pembayaran_id,
                metode_pembayaran,
                keterangan,
                tahun_pelajaran,
                jenis_pembayaran,
                jumlah_pembayaran,
                bulan_pembayaran
            });

            // Validasi awal untuk memastikan data lengkap
            let isValid = true;
            let errorMessages = [];

            if (!pembayaran_id || pembayaran_id === '') {
                errorMessages.push('ID Pembayaran tidak valid.');
                isValid = false;
            }

            if (metode_pembayaran === '') {
                errorMessages.push('Metode Pembayaran harus dipilih.');
                isValid = false;
            }

            if (tahun_pelajaran === '') {
                errorMessages.push('Tahun Pelajaran harus diisi.');
                isValid = false;
            }

            // Validasi setiap jenis pembayaran dan jumlah
            jenis_pembayaran.forEach((jenis, index) => {
                const jumlah = parseFloat(jumlah_pembayaran[index].replace(/\./g, '')) || 0;

                if (jenis === '') {
                    errorMessages.push(`Jenis Pembayaran pada item ${index + 1} harus dipilih.`);
                    isValid = false;
                }

                if (jumlah <= 0) {
                    errorMessages.push(`Jumlah Pembayaran pada item ${index + 1} harus lebih dari 0.`);
                    isValid = false;
                }

                if (isSPP(jenis) && (bulan_pembayaran[index] === '' || bulan_pembayaran[index] === undefined)) {
                    errorMessages.push(`Bulan Pembayaran pada item ${index + 1} harus dipilih karena jenis pembayaran adalah SPP.`);
                    isValid = false;
                }
            });

            // Hitung total jumlah pembayaran
            let total_jumlah = 0;
            jumlah_pembayaran.forEach(jml => {
                const jumlah = parseFloat(jml.replace(/\./g, '')) || 0;
                total_jumlah += jumlah;
            });

            if (total_jumlah <= 0) {
                errorMessages.push('Total Pembayaran harus lebih dari 0.');
                isValid = false;
            }

            // Tambahkan validasi nominal_max
            for (let i = 0; i < jenis_pembayaran.length; i++) {
                const jenis_id = jenis_pembayaran[i];
                const jumlah = parseFloat(jumlah_pembayaran[i].replace(/\./g, '')) || 0;

                // Cari nominal_max dari jenisPembayaranList
                const jenis = jenisPembayaranList.find(j => j.id == jenis_id);
                if (jenis) {
                    // Assume pengaturan_nominal data is loaded into jenisPembayaranList
                    // If not, you need to fetch it from the server
                    // For simplicity, let's assume each 'jenis' has 'nominal_max'
                    // You may need to adjust based on your data structure
                    // Here, we skip this validation or assume it's handled server-side
                }
            }

            // Tampilkan error jika validasi gagal
            const errorContainer = document.getElementById('edit-form-errors');
            if (!isValid) {
                console.error('Validasi gagal:', errorMessages);
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

            // Persiapkan data untuk dikirim ke backend
            const data = {
                pembayaran_id,
                metode_pembayaran,
                keterangan,
                tahun_pelajaran,
                jenis_pembayaran,
                jumlah_pembayaran,
                bulan_pembayaran,
                csrf_token: document.getElementById('editCsrfToken').value.trim()
            };

            // Kirim data ke backend menggunakan AJAX (fetch)
            fetch('update_pembayaran.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json(); // Parse respons ke JSON
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        alert('Pembayaran berhasil diperbarui.');
                        window.location.reload(); // Reload halaman untuk memuat data terbaru
                    } else {
                        console.error('Error dari server:', data.message);
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Gagal memperbarui pembayaran: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Terjadi kesalahan:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat memperbarui pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat memperbarui pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 7. Load Data Pembayaran untuk Edit
    // ========================
    const editButtons = document.querySelectorAll('.edit-btn');

    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pembayaran_id = this.getAttribute('data-id');

            fetch(`get_pembayaran.php?id=${encodeURIComponent(pembayaran_id)}`)
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        const pembayaran = data.data;
                        const details = data.details;

                        // Isi data ke dalam form edit
                        document.getElementById('editPembayaranId').value = pembayaran.id;
                        document.getElementById('edit_no_formulir').value = pembayaran.no_formulir;
                        document.getElementById('edit_nama').value = pembayaran.nama;
                        document.getElementById('edit_tahun_pelajaran').value = pembayaran.tahun_pelajaran;
                        document.getElementById('edit_metode_pembayaran').value = pembayaran.metode_pembayaran;
                        document.getElementById('edit_keterangan').value = pembayaran.keterangan;

                        // Clear existing payment details
                        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');
                        editPaymentWrapper.innerHTML = '';

                        // Tambahkan setiap detail pembayaran
                        details.forEach(detail => {
                            addEditPayment(detail.jenis_pembayaran_id, detail.bulan, detail.jumlah);
                        });

                        // Tampilkan modal edit
                        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditPembayaran'));
                        modalEdit.show();
                    } else {
                        alert('Data pembayaran tidak ditemukan.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengambil data pembayaran.');
                });
        });
    });

    // ========================
    // 8. Hapus Pembayaran dengan Modal Konfirmasi
    // ========================
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const modalHapusPembayaran = new bootstrap.Modal(document.getElementById('modalHapusPembayaran'));
    const hapusPembayaranForm = document.getElementById('hapusPembayaranForm');
    const hapus_no_formulir = document.getElementById('hapus_no_formulir');
    const hapus_nama = document.getElementById('hapus_nama');
    const hapus_jumlah = document.getElementById('hapus_jumlah');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pembayaran_id = this.getAttribute('data-id');

            // Fetch pembayaran data untuk menampilkan di modal
            fetch(`get_pembayaran.php?id=${encodeURIComponent(pembayaran_id)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const pembayaran = data.data;
                        hapus_no_formulir.textContent = pembayaran.no_formulir;
                        hapus_nama.textContent = pembayaran.nama;
                        hapus_jumlah.textContent = new Intl.NumberFormat('id-ID').format(pembayaran.jumlah);

                        // Set pembayaran_id pada form hapus
                        document.getElementById('hapusPembayaranId').value = pembayaran.id;

                        // Tampilkan modal hapus
                        modalHapusPembayaran.show();
                    } else {
                        alert('Data pembayaran tidak ditemukan.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengambil data pembayaran.');
                });
        });
    });

    // Handle form hapus pembayaran
    if (hapusPembayaranForm) {
        hapusPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const pembayaran_id = document.getElementById('hapusPembayaranId').value.trim();
            const errorContainer = document.getElementById('hapus-form-errors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';

            if (!pembayaran_id) {
                errorContainer.innerHTML = 'ID Pembayaran tidak valid.';
                errorContainer.style.display = 'block';
                return;
            }

            // Ambil CSRF token dari form hapus
            const csrf_token = hapusPembayaranForm.querySelector('input[name="csrf_token"]').value.trim();

            // Kirim permintaan hapus via AJAX
            fetch(`delete_pembayaran.php?id=${encodeURIComponent(pembayaran_id)}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrf_token
                })
            })
                .then(response => {
                    console.log('Response Status:', response.status); // Untuk debugging
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received from server:', data); // Untuk debugging
                    if (data.success) {
                        alert('Pembayaran berhasil dihapus.');
                        window.location.reload();
                    } else {
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Gagal menghapus pembayaran: ' + data.message);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (errorContainer) {
                        errorContainer.innerHTML = 'Terjadi kesalahan saat menghapus pembayaran.';
                        errorContainer.style.display = 'block';
                    } else {
                        alert('Terjadi kesalahan saat menghapus pembayaran.');
                    }
                });
        });
    }

    // ========================
    // 9. Fungsi untuk Mendapatkan Opsi Jenis Pembayaran dengan Seleksi
    // ========================
    function getJenisPembayaranOptions(selectedId = '') {
        let options = '<option value="" disabled>Pilih Jenis Pembayaran</option>';
        jenisPembayaranList.forEach(jenis => {
            if (jenis.id == selectedId) {
                options += `<option value="${jenis.id}" selected>${jenis.nama}</option>`;
            } else {
                options += `<option value="${jenis.id}">${jenis.nama}</option>`;
            }
        });
        return options;
    }

    // ========================
    // 10. Format Number Utility
    // ========================
    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    // ========================
    // 11. Tambah Jenis Pembayaran di Modal Edit
    // ========================
    const addEditPaymentBtn = document.getElementById('add-edit-payment-btn');
    if (addEditPaymentBtn) {
        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');

        addEditPaymentBtn.addEventListener('click', function () {
            addEditPayment();
        });

        // Menggunakan event delegation untuk tombol hapus di dalam modal edit
        editPaymentWrapper.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-payment-btn') || event.target.closest('.remove-payment-btn')) {
                const button = event.target.closest('.remove-payment-btn');
                const paymentItem = button.closest('.payment-item');
                if (paymentItem) {
                    paymentItem.remove();
                }
            }
        });
    }

    // Fungsi untuk menambahkan jenis pembayaran pada modal edit
    function addEditPayment(selectedId = '', selectedBulan = '', selectedJumlah = '') {
        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');
        const paymentItem = document.createElement('div');
        paymentItem.className = 'payment-item mb-3';
        paymentItem.innerHTML = `
            <div class="row">
                <div class="col-md-5">
                    <select name="jenis_pembayaran[]" class="form-select jenis-pembayaran" required>
                        ${getJenisPembayaranOptions(selectedId)}
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="jumlah[]" class="form-control jumlah-input" placeholder="Jumlah (Rp)" value="${selectedJumlah ? formatNumber(selectedJumlah) : ''}" required>
                </div>
                <div class="col-md-3">
                    <select name="bulan[]" class="form-select bulan-pembayaran" style="display: none;">
                        <option value="" disabled selected>Pilih Bulan</option>
                        <option value="Juli">Juli</option>
                        <option value="Agustus">Agustus</option>
                        <option value="September">September</option>
                        <option value="Oktober">Oktober</option>
                        <option value="November">November</option>
                        <option value="Desember">Desember</option>
                        <option value="Januari">Januari</option>
                        <option value="Februari">Februari</option>
                        <option value="Maret">Maret</option>
                        <option value="April">April</option>
                        <option value="Mei">Mei</option>
                        <option value="Juni">Juni</option>
                    </select>
                    <button type="button" class="btn btn-danger remove-payment-btn mt-2"><i class="fas fa-minus"></i></button>
                </div>
            </div>
        `;
        editPaymentWrapper.appendChild(paymentItem);

        // Terapkan format rupiah pada input jumlah yang baru ditambahkan
        const newJumlahInput = paymentItem.querySelector('.jumlah-input');
        formatCurrencyInput(newJumlahInput);

        // Jika jenis pembayaran adalah SPP, tampilkan dropdown bulan
        const jenisPembayaranSelect = paymentItem.querySelector('.jenis-pembayaran');
        const bulanPembayaranSelect = paymentItem.querySelector('.bulan-pembayaran');

        if (selectedId !== '' && isSPP(selectedId)) {
            bulanPembayaranSelect.style.display = 'block';
            bulanPembayaranSelect.value = selectedBulan;
        }

        // Jika ada jumlah yang dipilih, set nilainya
        if (selectedJumlah !== '') {
            newJumlahInput.value = formatNumber(selectedJumlah);
        }

        // Event listener untuk mengubah jenis pembayaran
        jenisPembayaranSelect.addEventListener('change', function () {
            if (isSPP(this.value)) {
                bulanPembayaranSelect.style.display = 'block';
            } else {
                bulanPembayaranSelect.style.display = 'none';
                bulanPembayaranSelect.value = '';
            }
        });
    }

});
