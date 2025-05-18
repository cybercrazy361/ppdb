// sidebar.js

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const footer = document.querySelector('.footer');
    const COLLAPSED_KEY = 'sidebar-collapsed';

    /**
     * Toggle a class on multiple elements.
     * @param {string} className
     * @param {HTMLElement[]} elements
     */
    const toggleClassOn = (className, elements) => {
        elements.forEach(el => el.classList.toggle(className));
    };

    /**
     * Set sidebar state (collapsed / expanded).
     * @param {boolean} collapsed
     */
    const setSidebarState = (collapsed) => {
        const action = collapsed ? 'add' : 'remove';

        sidebar.classList[action]('collapsed');
        mainContent.classList[action]('sidebar-collapsed');
        footer.classList[action]('sidebar-collapsed');

        sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
        localStorage.setItem(COLLAPSED_KEY, String(collapsed));
    };

    /**
     * Load and apply persisted sidebar state.
     */
    const loadSidebarState = () => {
        const isCollapsed = localStorage.getItem(COLLAPSED_KEY) === 'true';
        setSidebarState(isCollapsed);
    };

    /**
     * Close sidebar when clicking outside on mobile.
     * @param {MouseEvent} e
     */
    const handleOutsideClick = (e) => {
        if (window.innerWidth < 768 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            if (!sidebar.classList.contains('collapsed')) {
                setSidebarState(true);
            }
        }
    };

    /**
     * Debounce utility to limit function calls.
     * @param {Function} fn
     * @param {number} wait
     */
    const debounce = (fn, wait = 100) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), wait);
        };
    };

    /**
     * Close all submenus except the one related to current link.
     * @param {HTMLElement} currentLink
     */
    const closeOtherSubmenus = (currentLink) => {
        document.querySelectorAll('.nav-link[data-target]').forEach(link => {
            if (link !== currentLink) {
                const targetEl = document.querySelector(link.dataset.target);
                if (targetEl.classList.contains('show')) {
                    new bootstrap.Collapse(targetEl, { toggle: false }).hide();
                }
                link.classList.remove('active');
            }
        });
    };

    /**
     * Initialize submenu toggles.
     */
    const initSubmenus = () => {
        document.querySelectorAll('.nav-link[data-target]').forEach(link => {
            const targetSelector = link.dataset.target;
            const collapseEl = document.querySelector(targetSelector);

            // Sync initial active state
            if (collapseEl.classList.contains('show')) {
                link.classList.add('active');
            }

            link.addEventListener('click', (e) => {
                e.preventDefault();
                closeOtherSubmenus(link);

                const bsCollapse = new bootstrap.Collapse(collapseEl, { toggle: true });
                collapseEl.addEventListener('shown.bs.collapse', () => link.classList.add('active'), { once: true });
                collapseEl.addEventListener('hidden.bs.collapse', () => link.classList.remove('active'), { once: true });
            });
        });
    };

    // ——— Initialization ———
    loadSidebarState();
    initSubmenus();

    // Toggle sidebar on button click
    sidebarToggle.addEventListener('click', () => {
        setSidebarState(!sidebar.classList.contains('collapsed'));
    });

    // Click outside to close (mobile)
    document.addEventListener('click', handleOutsideClick);

    // Ensure sidebar auto-closes on window resize < 768px
    window.addEventListener('resize', debounce(() => {
        if (window.innerWidth < 768 && !sidebar.classList.contains('collapsed')) {
            setSidebarState(true);
        }
    }));
});
