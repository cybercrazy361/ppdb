// assets/js/sidebar.js

document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer = document.querySelector('.footer');

    function setSidebarState(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        mainContent.classList.toggle('sidebar-collapsed', collapsed);
        footer.classList.toggle('sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function loadSidebarState() {
        setSidebarState(localStorage.getItem('sidebar-collapsed') === 'true');
    }

    function saveSidebarState(collapsed) {
        localStorage.setItem('sidebar-collapsed', collapsed);
    }

    loadSidebarState();

    sidebarToggle.addEventListener('click', function () {
        const isCollapsed = !sidebar.classList.contains('collapsed');
        setSidebarState(isCollapsed);
        saveSidebarState(isCollapsed);
    });

    document.addEventListener('click', function (e) {
        if (window.innerWidth < 768 &&
            !sidebar.contains(e.target) &&
            !sidebarToggle.contains(e.target) &&
            !sidebar.classList.contains('collapsed')) {
            setSidebarState(true);
            saveSidebarState(true);
        }
    });

    const submenuLinks = document.querySelectorAll('.nav-link[data-bs-toggle="collapse"]');
    submenuLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            new bootstrap.Collapse(target, { toggle: true });
        });
    });
});
