document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('navToggle');
    const sidebar = document.getElementById('appSidebar');
    if (!toggle || !sidebar) return;

    const setOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        toggle.querySelector('i').className = open ? 'bi bi-x-lg' : 'bi bi-list';
    };
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a') && window.matchMedia('(max-width: 900px)').matches) setOpen(false);
    });
    window.matchMedia('(max-width: 900px)').addEventListener('change', () => setOpen(false));
});
