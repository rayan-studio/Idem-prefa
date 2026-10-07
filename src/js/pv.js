(() => {
    // Le volet ouvert est rouvert après rechargement : on enregistre PV par PV et il
    // serait pénible de rouvrir la demande à chaque fois.
    const ouverts = () => Array.from(document.querySelectorAll('#pv-page .prefa-detail-row:not([hidden])')).map(ligne => ligne.id);

    function recharger(deplies = []) {
        $('#content').load('pages/pv.php', function (_, statut) {
            if (statut === 'error') {
                $(this).text('Impossible de charger les PV. Actualisez.');
                return;
            }
            deplies.forEach(id => {
                const ligne = document.getElementById(id);
                if (ligne) ligne.hidden = false;
                document.querySelectorAll('.prefa-toggle[aria-controls="' + id + '"]').forEach(bouton => bouton.setAttribute('aria-expanded', 'true'));
            });
        });
    }

    $(document).on('click', '.pv-refresh', () => recharger(ouverts()));

    // Filtrage sans aller-retour serveur : les quatre PV de chaque demande sont deja
    // dans la page, il n'y a qu'a masquer les lignes qui ne correspondent pas.
    function filtrer() {
        const texte = (document.getElementById('pv-filtre-recherche')?.value || '').trim().toLowerCase();
        const avancement = document.getElementById('pv-filtre-avancement')?.value || '';
        const demandeur = document.getElementById('pv-filtre-demandeur')?.value || '';
        let visibles = 0;
        document.querySelectorAll('#pv-page .pv-ligne').forEach(ligne => {
            const restants = Number(ligne.dataset.restants || 0);
            const garder = (!texte || (ligne.dataset.recherche || '').includes(texte))
                && (!demandeur || ligne.dataset.demandeur === demandeur)
                && (avancement !== 'restants' || restants > 0)
                && (avancement !== 'complets' || restants === 0);
            ligne.hidden = !garder;
            // Un volet laisse ouvert sur une ligne masquee resterait affiche tout seul.
            if (!garder) {
                ligne.querySelectorAll('.prefa-detail-row').forEach(volet => { volet.hidden = true; });
                ligne.querySelectorAll('.prefa-toggle[aria-expanded="true"]').forEach(bouton => bouton.setAttribute('aria-expanded', 'false'));
            }
            if (garder) visibles++;
        });
        const compte = document.getElementById('pv-compte');
        if (compte) compte.textContent = visibles;
        const libelle = document.getElementById('pv-compte-libelle');
        if (libelle) libelle.textContent = visibles === 1 ? 'demande affichée' : 'demandes affichées';
    }

    $(document).on('input', '#pv-filtre-recherche', filtrer);
    $(document).on('change', '#pv-filtre-avancement, #pv-filtre-demandeur', filtrer);

    function envoyer(donnees, message, boutons, deplies) {
        boutons.prop('disabled', true);
        message.text('Enregistrement…');
        $.ajax({ url: 'actions/pv.php', method: 'POST', data: donnees, processData: false, contentType: false, dataType: 'json' })
            .done(reponse => { message.text(reponse.message || 'Enregistré.'); recharger(deplies); })
            .fail(xhr => message.text(xhr.responseJSON?.message || 'Enregistrement impossible. Réessayez.'))
            .always(() => boutons.prop('disabled', false));
    }

    $(document).on('submit', '.pv-form', function (event) {
        event.preventDefault();
        envoyer(new FormData(this), $(this).find('.atelier-message'), $(this).find('button'), ouverts());
    });

    // La zone de dépôt remplace l'input natif : c'est à elle d'annoncer ce qui est sélectionné.
    $(document).on('change', '.pv-fichiers-input', function () {
        const texte = this.closest('.pv-fichiers').querySelector('.pv-fichiers-texte');
        const fichiers = Array.from(this.files || []);
        if (!fichiers.length) {
            texte.textContent = 'Ajouter des documents';
        } else if (fichiers.length === 1) {
            texte.textContent = fichiers[0].name;
        } else {
            texte.textContent = fichiers.length + ' fichiers sélectionnés';
        }
    });

    $(document).on('click', '.pv-retirer-document', function () {
        if (!window.confirm('Retirer « ' + this.dataset.nom + ' » ? Le fichier sera supprimé.')) return;
        const form = this.closest('.pv-form');
        const donnees = new FormData();
        donnees.set('csrf', form.elements.csrf.value);
        donnees.set('operation', 'delete_document');
        donnees.set('demande', this.dataset.demande);
        donnees.set('type', this.dataset.type);
        donnees.set('document', this.dataset.document);
        envoyer(donnees, $(form).find('.atelier-message'), $(this), ouverts());
    });
})();
