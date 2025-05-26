// Tidak perlu edit, cukup copy-paste di bawah ini (setelah include Bootstrap dan sebelum include sidebar_pendaftaran.js jika JS eksternal)
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    function showSidebar() {
        sidebar.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
    }
    function hideSidebar() {
        sidebar.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            showSidebar();
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            hideSidebar();
        });
    }
    // Hide sidebar if resize >992px
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) hideSidebar();
    });
});
