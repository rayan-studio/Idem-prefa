(() => {
    function refreshStaff(expanded = []) {
        if (!Array.isArray(expanded)) expanded = [];
        $('#content').load('pages/atelier.php', function (_, status) {
            if (status === 'error') {
                $(this).text('Impossible de charger vos affectations. Actualisez la page.');
                return;
            }
            expanded.forEach(id => {
                const row = document.getElementById(id);
                if (row) row.hidden = false;
                document.querySelectorAll('.prefa-toggle').forEach(button => {
                    if (button.getAttribute('aria-controls') === id) button.setAttribute('aria-expanded', 'true');
                });
            });
        });
    }
    $(document).on('click', '.atelier-refresh', refreshStaff);
    $(document).on('click', '.atelier-edit-assignment', function () {
        const panel = document.getElementById(this.dataset.form);
        if (!panel) return;
        const form = panel.querySelector('form');
        const user = this.dataset.user || '';
        form.elements.type.value = this.dataset.type;
        form.elements.utilisateur.value = user;
        panel.querySelector('.atelier-manage-title').textContent = (user ? 'Modifier : ' : 'Affecter : ') + this.dataset.label;
        panel.querySelector('.atelier-remove-assignment').hidden = !user;
        panel.querySelector('.atelier-message').textContent = '';
        document.querySelectorAll('.atelier-edit-assignment').forEach(button => {
            if (button.dataset.form === this.dataset.form) button.setAttribute('aria-expanded', String(button === this));
        });
        panel.hidden = false;
        const editorRow = panel.closest('.atelier-editor-row');
        if (editorRow) editorRow.hidden = false;
        form.elements.utilisateur.focus();
    });
    $(document).on('click', '.atelier-cancel-assignment', function () {
        const panel = this.closest('.atelier-manage-panel');
        panel.hidden = true;
        const editorRow = panel.closest('.atelier-editor-row');
        if (editorRow) editorRow.hidden = true;
        document.querySelectorAll('.atelier-edit-assignment').forEach(button => {
            if (button.dataset.form === panel.id) button.setAttribute('aria-expanded', 'false');
        });
    });
    $(document).on('submit', '.atelier-action', function (event) {
        event.preventDefault();
        const form = $(this);
        const endpoint = this.dataset.endpoint;
        if (!['save_atelier.php', 'update_atelier.php'].includes(endpoint)) return;
        const data = new URLSearchParams(form.serialize());
        if (event.originalEvent?.submitter?.value === 'revoke') data.set('operation', 'revoke');
        const message = form.find('.atelier-message');
        const buttons = form.find('button');
        buttons.prop('disabled', true);
        message.text('Enregistrement…');
        const expanded = Array.from(document.querySelectorAll('.prefa-toggle[aria-expanded="true"]')).map(button => button.getAttribute('aria-controls'));
        $.ajax({ url: 'actions/' + endpoint, method: 'POST', data: data.toString(), dataType: 'json' })
            .done(function () {
                if (endpoint === 'update_atelier.php') { refreshStaff(expanded); return; }
                const prefaFilters = document.getElementById('prefa-filters');
                if (prefaFilters) {
                    const params = new URLSearchParams(new FormData(prefaFilters));
                    params.set('results', '1');
                    $.get('pages/lists_prefa.php?' + params.toString()).done(function (html) {
                        $('#prefa-results').replaceWith(html);
                        expanded.forEach(id => {
                            const row = document.getElementById(id);
                            if (row) row.hidden = false;
                            document.querySelectorAll('.prefa-toggle').forEach(button => {
                                if (button.getAttribute('aria-controls') === id) button.setAttribute('aria-expanded', 'true');
                            });
                        });
                    }).fail(function () { message.text('Enregistrement effectué. Actualisez pour voir le résultat.'); });
                } else if (document.getElementById('plans-iso-page')) {
                    const openCardIds = Array.from(document.querySelectorAll('.plans-iso-card[open]')).map(c => c.id);
                    $('#content').load('pages/plans_iso.php', function () {
                        openCardIds.forEach(id => {
                            const card = document.getElementById(id);
                            if (card) card.open = true;
                        });
                    });
                }
            })
            .fail(xhr => message.text(xhr.responseJSON?.message || 'Enregistrement impossible. Réessayez.'))
            .always(() => buttons.prop('disabled', false));
    });
})();
