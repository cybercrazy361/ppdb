// sidebar.js

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const main = document.querySelector('.main-content');
    const footer = document.querySelector('.sidebar-footer');

    // Load state
    const collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    sidebar.classList.toggle('collapsed', collapsed);

    toggleBtn.addEventListener('click', () => {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', isCollapsed);
    });

    // Close submenu when opening another
    document.querySelectorAll('.has-submenu > .nav-link').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const target = document.querySelector(link.getAttribute('href'));
            const bs = new bootstrap.Collapse(target, { toggle: true });
            // close others
            document.querySelectorAll('.has-submenu .collapse').forEach(c => {
                if (c !== target) new bootstrap.Collapse(c, { toggle: false }).hide();
            });
        });
    });
});
