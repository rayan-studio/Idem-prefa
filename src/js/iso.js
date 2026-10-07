(() => {
    $(document).on('click', '.plans-iso-toggle', function () {
        const row = document.getElementById(this.getAttribute('aria-controls'));
        if (!row) return;
        row.hidden = !row.hidden;
        this.setAttribute('aria-expanded', String(!row.hidden));
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
