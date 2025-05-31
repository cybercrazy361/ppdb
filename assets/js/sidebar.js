// assets/js/sidebar.js

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer = document.querySelector('.footer');

    // ===== Load & apply saved collapsed state =====
    const wasCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    sidebar.classList.toggle('collapsed', wasCollapsed);
    mainContent?.classList.toggle('sidebar-collapsed', wasCollapsed);
    footer?.classList.toggle('sidebar-collapsed', wasCollapsed);
    toggleBtn.setAttribute('aria-expanded', (!wasCollapsed).toString());

    // ===== Toggle sidebar open/close =====
    toggleBtn.addEventListener('click', () => {
        const isNowCollapsed = !sidebar.classList.contains('collapsed');
        sidebar.classList.toggle('collapsed', isNowCollapsed);
        mainContent?.classList.toggle('sidebar-collapsed', isNowCollapsed);
        footer?.classList.toggle('sidebar-collapsed', isNowCollapsed);
        localStorage.setItem('sidebar-collapsed', isNowCollapsed);
        toggleBtn.setAttribute('aria-expanded', (!isNowCollapsed).toString());
    });

    // ===== Auto‐close on mobile when clicking outside =====
    document.addEventListener('click', e => {
        if (
            window.innerWidth < 768 &&
            !sidebar.contains(e.target) &&
            !toggleBtn.contains(e.target) &&
            !sidebar.classList.contains('collapsed')
        ) {
            sidebar.classList.add('collapsed');
            mainContent?.classList.add('sidebar-collapsed');
            footer?.classList.add('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', true);
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    });

    // ===== Submenu toggle handlers =====
    document.querySelectorAll('.nav-link[data-bs-toggle="collapse"]').forEach(link => {
        const selector = link.getAttribute('href');
        const targetEl = document.querySelector(selector);
        if (!targetEl) return;

        // Buat satu instance Collapse tanpa toggle otomatis
        const bsCollapse = new bootstrap.Collapse(targetEl, { toggle: false });

        // Klik link → toggle submenu
        link.addEventListener('click', e => {
            e.preventDefault();
            bsCollapse.toggle();
        });

        // Update class active & rotasi arrow
        targetEl.addEventListener('shown.bs.collapse', () => link.classList.add('active'));
        targetEl.addEventListener('hidden.bs.collapse', () => link.classList.remove('active'));
    });
});
