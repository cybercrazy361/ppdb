// sidebar.js

document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer = document.querySelector('.footer');

    /**
     * Fungsi untuk mengatur status sidebar berdasarkan parameter
     * @param {boolean} collapsed - Apakah sidebar dalam keadaan collapsed
     */
    function setSidebarState(collapsed) {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('sidebar-collapsed');
            footer.classList.add('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', 'false');
        } else {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('sidebar-collapsed');
            footer.classList.remove('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }
    }

    /**
     * Memuat status sidebar dari localStorage
     */
    function loadSidebarState() {
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        setSidebarState(isCollapsed);
    }

    /**
     * Menyimpan status sidebar ke localStorage
     * @param {boolean} collapsed - Apakah sidebar dalam keadaan collapsed
     */
    function saveSidebarState(collapsed) {
        localStorage.setItem('sidebar-collapsed', collapsed);
    }

    // Inisialisasi status sidebar saat halaman dimuat
    loadSidebarState();

    /**
     * Event Listener untuk tombol toggle sidebar
     */
    sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('sidebar-collapsed');
        footer.classList.toggle('sidebar-collapsed');

        // Menyimpan status sidebar setelah toggle
        const isCollapsed = sidebar.classList.contains('collapsed');
        saveSidebarState(isCollapsed);

        // Mengupdate atribut aria-expanded untuk aksesibilitas
        sidebarToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
    });

    /**
     * Menangani klik di luar sidebar untuk menutupnya (hanya pada perangkat mobile)
     */
    document.addEventListener('click', function (e) {
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        if (viewportWidth < 768) { // Perangkat mobile
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                if (!sidebar.classList.contains('collapsed')) {
                    setSidebarState(true); // Menutup sidebar
                    saveSidebarState(true);
                }
            }
        }
    });

    /**
     * Fungsi untuk menutup semua submenu kecuali yang sedang dibuka
     */
    function closeOtherSubmenus(currentLink) {
        const submenuLinks = document.querySelectorAll('.nav-link[data-bs-toggle="collapse"]');
        submenuLinks.forEach(function (link) {
            if (link !== currentLink) {
                const target = link.getAttribute('href');
                const collapseElement = document.querySelector(target);
                if (collapseElement.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(collapseElement, {
                        toggle: false
                    });
                    bsCollapse.hide();
                }
                link.classList.remove('active');
            }
        });
    }

    /**
     * Event Listener untuk setiap link yang memiliki submenu
     */
    const submenuLinks = document.querySelectorAll('.nav-link[data-bs-toggle="collapse"]');
    submenuLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault(); // Mencegah perilaku default link

            // Menutup submenu lain
            closeOtherSubmenus(link);

            const target = this.getAttribute('href');
            const collapseElement = document.querySelector(target);
            const bsCollapse = new bootstrap.Collapse(collapseElement, {
                toggle: true
            });

            // Menambahkan kelas active saat submenu dibuka
            collapseElement.addEventListener('shown.bs.collapse', function () {
                link.classList.add('active');
            }, { once: true });

            // Menghapus kelas active saat submenu ditutup
            collapseElement.addEventListener('hidden.bs.collapse', function () {
                link.classList.remove('active');
            }, { once: true });
        });
    });

    /**
     * Menambahkan kelas active pada menu utama jika salah satu submenu aktif
     */
    function setActiveMainMenu() {
        submenuLinks.forEach(function (link) {
            const target = link.getAttribute('href');
            const collapseElement = document.querySelector(target);
            if (collapseElement.classList.contains('show')) {
                link.classList.add('active');
            }
        });
    }

    // Inisialisasi status submenu saat halaman dimuat
    setActiveMainMenu();
});
