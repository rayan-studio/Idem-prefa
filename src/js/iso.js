(() => {
const searchInput = document.getElementById('plans-iso-filter-search');
const cards = document.querySelectorAll('.plans-iso-card');

if (searchInput) {
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        cards.forEach(card => {
            card.hidden = !!query && !(card.dataset.search || '').includes(query);
        });
    });
}

const refreshBtn = document.getElementById('refresh-plans-iso');
if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
        $('#content').load('pages/plans_iso.php');
    });
}
})();