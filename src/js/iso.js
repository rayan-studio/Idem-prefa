(() => {
    $(document).on('click', '.plans-iso-toggle', function () {
        const row = document.getElementById(this.getAttribute('aria-controls'));
        if (!row) return;
        const ouvrir = row.hidden;
        if (ouvrir) {
            // Un seul volet ouvert à la fois, comme partout ailleurs.
            document.querySelectorAll('.prefa-detail-row:not([hidden])').forEach(autre => { autre.hidden = true; });
            document.querySelectorAll('.prefa-toggle[aria-expanded="true"]').forEach(bouton => bouton.setAttribute('aria-expanded', 'false'));
        }
        row.hidden = !ouvrir;
        this.setAttribute('aria-expanded', String(ouvrir));
    });

    $(document).on('input', '#plans-iso-filter-search', function () {
        const query = this.value.trim().toLowerCase();
        document.querySelectorAll('.plans-iso-request').forEach(row => {
            row.hidden = !!query && !(row.dataset.search || '').includes(query);
        });
    });

    $(document).on('click', '#refresh-plans-iso', function () {
        $('#content').load('pages/plans_iso.php');
    });
})();
