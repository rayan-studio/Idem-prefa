const btn_hide_sidebar = document.getElementById('btn-hide');
const sidebarLayout = window.matchMedia('(max-width: 900px)');
function setSidebar(open) {
    document.getElementById('contenaire').classList.toggle('sidebar-closed', !open);
    btn_hide_sidebar.setAttribute('aria-expanded', String(open));
    const overlay = open && sidebarLayout.matches;
    document.getElementById('sidebar-backdrop').hidden = !overlay;
    document.getElementById('right-panel').inert = overlay;
    if (overlay) {
        document.getElementById('close-sidebar').focus();
    } else if (document.getElementById('left-panel').contains(document.activeElement)) {
        btn_hide_sidebar.focus();
    }
}
document.getElementById('close-sidebar').addEventListener('click', () => setSidebar(false));
document.getElementById('sidebar-backdrop').addEventListener('click', () => setSidebar(false));
document.addEventListener('keydown', function (event) {
    if (!sidebarLayout.matches || btn_hide_sidebar.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') setSidebar(false);
    if (event.key === 'Tab') {
        const items = document.getElementById('left-panel').querySelectorAll('button:not(:disabled), a[href]');
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
});
btn_hide_sidebar.addEventListener('click', function () {
    setSidebar(this.getAttribute('aria-expanded') !== 'true');
});
sidebarLayout.addEventListener('change', function (event) { setSidebar(!event.matches); });
setSidebar(!sidebarLayout.matches);
