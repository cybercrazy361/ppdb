// Sidebar toggle: mobile/desktop, ESC key, resize
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    function showSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
        // Trap focus in sidebar (accessibility, optional)
        document.body.style.overflow = "hidden";
    }
    function hideSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = "";
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

    // ESC keyboard close
    document.addEventListener('keydown', function (e) {
        if ((e.key === "Escape" || e.keyCode === 27) && sidebar && sidebar.classList.contains('active')) {
            hideSidebar();
        }
    });

    // Hide sidebar if resize >992px (with debounce)
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 992) hideSidebar();
        }, 80);
    });
});
