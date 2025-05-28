// assets/js/kelola_pembayaran.js

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

    // =============================================
    // 2. Inisialisasi untuk mem-fetch bulan yang lunas
    // =============================================
    let paidMonths = [];  // array nama bulan yang sudah lunas
    const tahunSelect = document.getElementById('tahun_pelajaran');

    function fetchPaidMonths() {
        const no = document.getElementById('no_formulir').value.trim();
        const th = tahunSelect.value;
        if (!no || !th) {
            paidMonths = [];
            return;
        }
        fetch(`get_paid_months.php?no_formulir=${encodeURIComponent(no)}&tahun=${encodeURIComponent(th)}`)
            .then(r => r.json())
            .then(d => {
                if (d.success && Array.isArray(d.paid_months)) {
                    paidMonths = d.paid_months;
                } else {
                    paidMonths = [];
                }
            })
            .catch(() => {
                paidMonths = [];
            });
    }
    // Panggil setiap kali No Formulir atau Tahun berubah
    document.getElementById('no_formulir').addEventListener('change', fetchPaidMonths);
    tahunSelect.addEventListener('change', fetchPaidMonths);

    // ========================
    // 3. Pencarian No Formulir (Autocomplete)
    // ========================
    const noFormulirInput = document.getElementById('no_formulir');
    const namaInput = document.getElementById('nama');

    function searchStudent(query) {
        if (query.length < 2) {
            document.getElementById('siswa-suggestions').style.display = 'none';
            return;
        }

        fetch(`search_student.php?query=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                const suggestions = document.getElementById('siswa-suggestions');
                suggestions.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    data.data.forEach(student => {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.textContent = `${student.no_formulir} - ${student.nama}`;
                        div.style.cursor = 'pointer';
                        div.onclick = () => selectStudent(student);
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.innerHTML = '<div class="dropdown-item">Tidak ditemukan</div>';
                    suggestions.style.display = 'block';
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function selectStudent(student) {
        noFormulirInput.value = student.no_formulir;
        namaInput.value = student.nama;
        document.getElementById('siswa-suggestions').style.display = 'none';
        fetchPaidMonths(); // update bulan lunas
    }

    function debounce(func, delay) {
        let debounceTimer;
        return function () {
            const context = this;
            const args = arguments;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func.apply(context, args), delay);
        }
    }

    noFormulirInput.addEventListener('input', debounce(function () {
        searchStudent(noFormulirInput.value.trim());
    }, 300));

    // ========================
    // 4. Menutup Suggestion saat klik di luar
    // ========================
    document.addEventListener('click', function (e) {
        const sug = document.getElementById('siswa-suggestions');
        if (!noFormulirInput.contains(e.target) && sug && !sug.contains(e.target)) {
            sug.style.display = 'none';
        }
    });

    // ===============================================
    // 5. Menambah Jenis Pembayaran Dinamis dengan Blokir
    // ===============================================
    const paymentWrapper = document.getElementById('payment-wrapper');
    const addPaymentBtn = document.getElementById('add-payment-btn');

    if (typeof jenisPembayaranList === 'undefined' || !Array.isArray(jenisPembayaranList)) {
        console.error('jenisPembayaranList tidak didefinisikan atau bukan array.');
    } else {
        function getJenisPembayaranOptions(selectedId = '') {
            let options = '<option value="" disabled>Pilih Jenis Pembayaran</option>';
            jenisPembayaranList.forEach(jenis => {
                options += `<option value="${jenis.id}"${jenis.id == selectedId ? ' selected' : ''}>${jenis.nama}</option>`;
            });
            return options;
        }

        function addPayment(selectedId = '', selectedBulan = '', selectedCashback = '') {
            const paymentItem = document.createElement('div');
            paymentItem.className = 'payment-item mb-3';
            paymentItem.innerHTML = `
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="jenis_pembayaran[]" class="form-select jenis-pembayaran" required>
                            ${getJenisPembayaranOptions(selectedId)}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="jumlah[]" class="form-control jumlah-input" placeholder="Jumlah (Rp)" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label cashback-label" style="display:none;">Cashback (Rp)</label>
                        <input type="text" name="cashback[]" class="form-control cashback-input" placeholder="Cashback" style="display:none;">
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
                        <button type="button" class="btn btn-danger remove-payment-btn mt-2">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
            `;
            paymentWrapper.appendChild(paymentItem);

            // format currency pada input jumlah & cashback
            formatCurrencyInput(paymentItem.querySelector('.jumlah-input'));
            formatCurrencyInput(paymentItem.querySelector('.cashback-input'));

            const jenisSelect = paymentItem.querySelector('.jenis-pembayaran');
            const cashbackInput = paymentItem.querySelector('.cashback-input');
            const cashbackLabel = paymentItem.querySelector('.cashback-label');
            const bulanSelect = paymentItem.querySelector('.bulan-pembayaran');

            function toggleCashbackInput() {
                const nama = jenisSelect.options[jenisSelect.selectedIndex].text.toLowerCase();
                if (nama.includes('uang pangkal')) {
                    cashbackLabel.style.display = 'block';
                    cashbackInput.style.display = 'block';
                    cashbackInput.required = true;
                } else {
                    cashbackLabel.style.display = 'none';
                    cashbackInput.style.display = 'none';
                    cashbackInput.value = '';
                    cashbackInput.required = false;
                }
            }

            function toggleBulanInput() {
                const namaJenis = jenisPembayaranList.find(j => j.id == jenisSelect.value)?.nama.toLowerCase();
                const isSpp = namaJenis === 'spp';
                if (!isSpp) {
                    bulanSelect.style.display = 'none';
                    bulanSelect.value = '';
                    return;
                }
                bulanSelect.style.display = 'block';

                // Bulan urut dari Juli ke Juni
                const bulan_order = [
                    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
                    "Januari", "Februari", "Maret", "April", "Mei", "Juni"
                ];

                // Cari bulan pertama yang belum lunas
                let firstUnpaid = null;
                for (const bulan of bulan_order) {
                    if (!paidMonths.includes(bulan)) {
                        firstUnpaid = bulan;
                        break;
                    }
                }

                Array.from(bulanSelect.options).forEach(opt => {
                    if (!opt.value) return; // skip placeholder

                    // Hanya bulan pertama yang belum lunas yang enable
                    if (opt.value === firstUnpaid) {
                        opt.disabled = false;
                        opt.text = opt.text.replace(' (Terkunci)', '');
                    } else {
                        opt.disabled = true;
                        if (!opt.text.includes(' (Terkunci)')) opt.text += ' (Terkunci)';
                    }
                    // Kalau sudah lunas, tetap di-disable & tulis lunas
                    if (paidMonths.includes(opt.value)) {
                        opt.disabled = true;
                        if (!opt.text.includes(' (Lunas)')) opt.text += ' (Lunas)';
                    }
                });

                // Otomatis pilih bulan pertama yang belum lunas
                if (firstUnpaid) bulanSelect.value = firstUnpaid;
                else bulanSelect.value = '';
            }


            // Inisialisasi tampilan
            toggleCashbackInput();
            toggleBulanInput();

            jenisSelect.addEventListener('change', function () {
                toggleCashbackInput();
                toggleBulanInput();
            });

            // tombol hapus baris
            paymentItem.querySelector('.remove-payment-btn').addEventListener('click', function () {
                paymentItem.remove();
            });
        }

        // tombol tambah baris pembayaran
        addPaymentBtn.addEventListener('click', () => addPayment());
    }

    // ========================
    // 6. Modal Edit Pembayaran
    // ========================
    // 6. Modal Edit Pembayaran
    const editPembayaranForm = document.getElementById('editPembayaranForm');
    if (editPembayaranForm) {
        editPembayaranForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const pembayaran_id = document.getElementById('editPembayaranId').value.trim();
            const metode_pembayaran = document.getElementById('edit_metode_pembayaran').value.trim();
            const keterangan = document.getElementById('edit_keterangan').value.trim();
            const tahun_pelajaran = document.getElementById('edit_tahun_pelajaran').value.trim();

            const jenis_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper select[name="jenis_pembayaran[]"]')).map(el => el.value);
            const jumlah_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper input[name="jumlah[]"]')).map(el => el.value);
            const bulan_pembayaran = Array.from(document.querySelectorAll('#edit-payment-wrapper select[name="bulan[]"]')).map(el => el.value);
            const cashback = Array.from(document.querySelectorAll('#edit-payment-wrapper input[name="cashback[]"]')).map(el => el.value);

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

            let total_jumlah = 0;
            jumlah_pembayaran.forEach(jml => {
                const jumlah = parseFloat(jml.replace(/\./g, '')) || 0;
                total_jumlah += jumlah;
            });

            if (total_jumlah <= 0) {
                errorMessages.push('Total Pembayaran harus lebih dari 0.');
                isValid = false;
            }

            const errorContainer = document.getElementById('edit-form-errors');
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

            const data = {
                pembayaran_id,
                metode_pembayaran,
                keterangan,
                tahun_pelajaran,
                jenis_pembayaran,
                jumlah_pembayaran,
                bulan_pembayaran,
                cashback,
                csrf_token: document.getElementById('editCsrfToken').value.trim()
            };

            fetch('update_pembayaran.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pembayaran berhasil diperbarui.');
                        window.location.reload();
                    } else {
                        if (errorContainer) {
                            errorContainer.innerHTML = data.message;
                            errorContainer.style.display = 'block';
                        } else {
                            alert('Gagal memperbarui pembayaran: ' + data.message);
                        }
                    }
                })
                .catch(error => {
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
    // 7. Load Data Pembayaran untuk Edit
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function () {
            const pembayaran_id = this.getAttribute('data-id');

            fetch(`get_pembayaran.php?id=${encodeURIComponent(pembayaran_id)}`)
                .then(response => {
                    // Jika response bukan 200/OK, lempar error!
                    if (!response.ok) throw new Error('HTTP status ' + response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('DATA FETCH EDIT:', data); // DEBUG LOG
                    if (data.success) {
                        const pembayaran = data.data;
                        const details = data.details;
                        document.getElementById('editPembayaranId').value = pembayaran.id;
                        document.getElementById('edit_no_formulir').value = pembayaran.no_formulir;
                        document.getElementById('edit_nama').value = pembayaran.nama;
                        document.getElementById('edit_tahun_pelajaran').value = pembayaran.tahun_pelajaran;
                        document.getElementById('edit_metode_pembayaran').value = pembayaran.metode_pembayaran;
                        document.getElementById('edit_keterangan').value = pembayaran.keterangan;
                        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');
                        editPaymentWrapper.innerHTML = '';

                        // Tambahkan try/catch di dalam loop agar error tidak block modal tampil
                        details.forEach((detail, idx) => {
                            try {
                                console.log('DETAIL KE-', idx, detail); // DEBUG LOG
                                addEditPayment(
                                    detail.jenis_pembayaran_id,
                                    detail.bulan ?? '',
                                    detail.jumlah ?? '',
                                    detail.cashback ?? ''
                                );
                            } catch (err) {
                                console.error('Gagal addEditPayment di detail ke-', idx, detail, err);
                                // Bisa juga tampilkan error di layar/modal
                            }
                        });
                        const modalEdit = new bootstrap.Modal(document.getElementById('modalEditPembayaran'));
                        modalEdit.show();
                    } else {
                        alert('Data pembayaran tidak ditemukan.');
                    }
                })
                .catch(error => {
                    // DEBUG LOG
                    console.error('Fetch Edit Error:', error);
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
            // → Ubah menjadi:
            fetch('delete_pembayaran.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: pembayaran_id,
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
    // 11. Tambah Jenis Pembayaran di Modal Edit
    const addEditPaymentBtn = document.getElementById('add-edit-payment-btn');
    if (addEditPaymentBtn) {
        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');

        addEditPaymentBtn.addEventListener('click', function () {
            addEditPayment();
        });

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

    function addEditPayment(selectedId = '', selectedBulan = '', selectedJumlah = '', selectedCashback = '') {
        const editPaymentWrapper = document.getElementById('edit-payment-wrapper');
        const paymentItem = document.createElement('div');
        paymentItem.className = 'payment-item mb-3';
        paymentItem.innerHTML = `
    <div class="row g-2">
        <div class="col-md-3">
            <select name="jenis_pembayaran[]" class="form-select jenis-pembayaran" required>
                ${getJenisPembayaranOptions(selectedId)}
            </select>
        </div>
        <div class="col-md-2">
            <input type="text" name="jumlah[]" class="form-control jumlah-input" placeholder="Jumlah (Rp)" value="${selectedJumlah ? formatNumber(selectedJumlah) : ''}" required>
        </div>
        <div class="col-md-2">
    <label class="form-label cashback-label" style="display:none;">Cashback (Rp)</label>
    <input type="text"
           min="0" step="100"
           name="cashback[]"
           class="form-control cashback-input"
           placeholder="Masukkan Cashback"
           style="display:none;"
           value="${selectedCashback ? selectedCashback : ''}">
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
        </div>
        <div class="col-md-2 d-flex align-items-center">
            <button type="button" class="btn btn-danger remove-payment-btn mt-2"><i class="fas fa-minus"></i></button>
        </div>
    </div>
    `;
        editPaymentWrapper.appendChild(paymentItem);

        const newJumlahInput = paymentItem.querySelector('.jumlah-input');
        formatCurrencyInput(newJumlahInput);

        const newCashbackInput = paymentItem.querySelector('.cashback-input');
        formatCurrencyInput(newCashbackInput);
        if (selectedCashback) {
            // ambil hanya digit
            const raw = selectedCashback.toString().replace(/\D/g, '');
            // format dengan titik ribuan
            newCashbackInput.value = new Intl.NumberFormat('id-ID').format(raw);
        }

        const jenisPembayaranSelect = paymentItem.querySelector('.jenis-pembayaran');
        const cashbackInput = paymentItem.querySelector('.cashback-input');
        const cashbackLabel = paymentItem.querySelector('.cashback-label');
        const bulanPembayaranSelect = paymentItem.querySelector('.bulan-pembayaran');

        function toggleCashbackInput() {
            const selectedOption = jenisPembayaranSelect.options[jenisPembayaranSelect.selectedIndex];
            const jenisNama = selectedOption ? selectedOption.text.toLowerCase() : '';
            if (jenisNama.includes("uang pangkal")) {
                cashbackLabel.style.display = 'block';
                cashbackInput.style.display = 'block';
                cashbackInput.required = true;
            } else {
                cashbackLabel.style.display = 'none';
                cashbackInput.style.display = 'none';
                cashbackInput.value = '';
                cashbackInput.required = false;
            }

        }

        function toggleBulanInput() {
            if (isSPP(jenisPembayaranSelect.value)) {
                bulanPembayaranSelect.style.display = 'block';
                if (selectedBulan) bulanPembayaranSelect.value = selectedBulan;
            } else {
                bulanPembayaranSelect.style.display = 'none';
                bulanPembayaranSelect.value = '';
            }
        }

        toggleCashbackInput();
        toggleBulanInput();

        jenisPembayaranSelect.addEventListener('change', function () {
            toggleCashbackInput();
            toggleBulanInput();
        });

        if (selectedJumlah !== '') {
            newJumlahInput.value = formatNumber(selectedJumlah);
        }
        if (selectedCashback !== undefined && selectedCashback !== '') {
            cashbackInput.value = formatNumber(selectedCashback);
        }

        if (selectedId !== '' && isSPP(selectedId) && selectedBulan) {
            bulanPembayaranSelect.style.display = 'block';
            bulanPembayaranSelect.value = selectedBulan;
        }
    }
});

// ========================
// 12. Submit Tambah Pembayaran via AJAX
// ========================
const tambahPembayaranForm = document.getElementById('tambahPembayaranForm');
if (tambahPembayaranForm) {
    tambahPembayaranForm.addEventListener('submit', function (e) {
        e.preventDefault();
        let formData = new FormData(this);
        fetch('tambah_pembayaran.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    alert('Pembayaran berhasil!');
                    window.location.reload();
                } else {
                    document.getElementById('form-errors').innerHTML = d.message;
                    document.getElementById('form-errors').style.display = 'block';
                }
            })
            .catch(() => alert('Error server!'));
    });
}
function isSPP(jenis_id) {
    if (!window.jenisPembayaranList) return false;
    // Cari di list, samakan string 'SPP' (tidak case sensitive)
    const jenis = window.jenisPembayaranList.find(j => j.id == jenis_id);
    if (!jenis) return false;
    return (jenis.nama || '').trim().toLowerCase() === 'spp';
}
