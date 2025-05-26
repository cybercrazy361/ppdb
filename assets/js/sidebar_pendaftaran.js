// assets/js/sidebar_pendaftaran.js

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
    // Hide sidebar if resize >576px
    window.addEventListener('resize', function () {
        if (window.innerWidth > 576) hideSidebar();
    });
});
