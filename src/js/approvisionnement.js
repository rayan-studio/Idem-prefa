(() => {
    // Les deux pages du module partagent le même endpoint de rechargement : on recharge
    // celle qui est ouverte.
    const page = () => document.getElementById('signalements-page') ? 'pages/signalements.php' : 'pages/materiel.php';

    function recharger() {
        $('#content').load(page(), function (_, statut) {
            if (statut === 'error') $(this).text('Impossible de charger la page. Actualisez.');
        });
    }

    $(document).on('click', '.appro-refresh', recharger);

    function envoyer(formulaire, soumetteur) {
        const $form = $(formulaire);
        const message = $form.find('.atelier-message').length
            ? $form.find('.atelier-message')
            : $('[data-message-for="' + formulaire.id + '"]');
        // form.elements rassemble aussi les champs rattachés par l'attribut form.
        const boutons = $(Array.from(formulaire.elements).filter(champ => champ.tagName === 'BUTTON'));
        boutons.prop('disabled', true);
        message.text('Enregistrement…');
        const donnees = new FormData(formulaire);
        // FormData ignore le bouton d'envoi. Or c'est lui qui porte l'etat vise, chaque
        // bouton proposant une transition differente : sans cet ajout, « statut » part
        // vide et le serveur refuse la requete.
        if (soumetteur && soumetteur.name && !donnees.has(soumetteur.name)) {
            donnees.append(soumetteur.name, soumetteur.value);
        }
        $.ajax({ url: 'actions/approvisionnement.php', method: 'POST', data: donnees, processData: false, contentType: false, dataType: 'json' })
            .done(reponse => { message.text(reponse.message || 'Enregistré.'); recharger(); })
            .fail(xhr => message.text(xhr.responseJSON?.message || 'Enregistrement impossible. Réessayez.'))
            .always(() => boutons.prop('disabled', false));
    }

    $(document).on('submit', '.appro-statut-form, .appro-create-form', function (event) {
        event.preventDefault();
        envoyer(this, event.originalEvent && event.originalEvent.submitter);
    });
})();
