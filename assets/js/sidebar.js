// sidebar.js
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');

    // Load state
    const collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    sidebar.classList.toggle('collapsed', collapsed);

    // Toggle sidebar
    toggleBtn.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    });

    // Auto-close on small screens when clicking outside
    document.addEventListener('click', e => {
        const width = window.innerWidth;
        if (width < 768 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.add('collapsed');
            localStorage.setItem('sidebar-collapsed', true);
        }
    });

    // Submenu rotate
    const submenuToggles = document.querySelectorAll('.has-submenu > .nav-link');
    submenuToggles.forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const collapseEl = document.querySelector(link.getAttribute('href'));
            const bsCollapse = new bootstrap.Collapse(collapseEl, { toggle: true });
        });
    });
});
