(() => {
    const trigger = document.getElementById('account-menu-button');
    const menu = document.getElementById('account-menu');
    const dialog = document.getElementById('account-dialog');
    if (!trigger || !menu || !dialog) return;
    const form = dialog.querySelector('form');
    const message = form.querySelector('.account-message');
    const marge = 8;
    const popovers = [];

    // Les menus sont en position fixe : la barre latérale défile et les découperait.
    function popover(declencheur, panneau, premierFocus) {
        function placer() {
            const repere = declencheur.getBoundingClientRect();
            const largeur = panneau.offsetWidth;
            const hauteur = panneau.offsetHeight;
            let gauche = repere.right + marge;
            let haut = repere.bottom - hauteur;
            if (gauche + largeur > window.innerWidth - marge) {
                // Pas la place à droite : le menu s’ouvre au-dessus du déclencheur.
                gauche = repere.left;
                haut = repere.top - hauteur - marge;
            }
            panneau.style.left = Math.max(marge, Math.min(gauche, window.innerWidth - largeur - marge)) + 'px';
            panneau.style.top = Math.max(marge, Math.min(haut, window.innerHeight - hauteur - marge)) + 'px';
        }

        function ouvrir(ouvert) {
            if (ouvert) {
                popovers.forEach(autre => { if (autre !== controle) autre.ouvrir(false); });
                panneau.style.visibility = 'hidden';
                panneau.hidden = false;
                placer();
                panneau.style.visibility = '';
                panneau.querySelector(premierFocus)?.focus();
            } else {
                const rendreFocus = panneau.contains(document.activeElement);
                panneau.hidden = true;
                panneau.classList.remove('is-managing');
                panneau.querySelector('.account-manage')?.setAttribute('aria-pressed', 'false');
                if (rendreFocus) declencheur.focus();
            }
            declencheur.setAttribute('aria-expanded', String(ouvert));
        }

        const controle = { declencheur, panneau, ouvrir, placer, estOuvert: () => !panneau.hidden };
        declencheur.addEventListener('click', () => ouvrir(panneau.hidden));
        popovers.push(controle);
        return controle;
    }

    const menuComptes = popover(trigger, menu, '.account-item:not(.is-current), .account-add');

    document.addEventListener('click', event => {
        popovers.forEach(p => {
            if (p.estOuvert() && !p.panneau.contains(event.target) && !p.declencheur.contains(event.target)) p.ouvrir(false);
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') popovers.forEach(p => { if (p.estOuvert()) p.ouvrir(false); });
    });

    window.addEventListener('resize', () => popovers.forEach(p => { if (p.estOuvert()) p.placer(); }));

    const ouvrirMenu = ouvert => menuComptes.ouvrir(ouvert);

    async function envoyer(donnees, bouton) {
        bouton.disabled = true;
        try {
            const reponse = await fetch('actions/comptes.php', { method: 'POST', body: donnees, credentials: 'same-origin' });
            const resultat = await reponse.json().catch(() => ({}));
            if (!reponse.ok || !resultat.success) return resultat.message || 'Action impossible. Réessayez.';
            window.location.href = resultat.redirect || 'dashboard.php';
            return null;
        } catch (error) {
            return 'Le serveur ne répond pas. Réessayez.';
        } finally {
            bouton.disabled = false;
        }
    }

    menu.addEventListener('click', async event => {
        if (event.target.closest('.account-add')) {
            ouvrirMenu(false);
            form.reset();
            message.textContent = '';
            message.classList.remove('is-error');
            dialog.showModal();
            form.elements.login.focus();
            return;
        }
        const gestion = event.target.closest('.account-manage');
        if (gestion) {
            // Comme Discord : un mode dédié révèle le retrait, plutôt qu'une croix permanente.
            const actif = menu.classList.toggle('is-managing');
            gestion.setAttribute('aria-pressed', String(actif));
            gestion.querySelector('.account-manage-label').textContent = actif ? 'Terminer' : 'Gérer les comptes';
            return;
        }
        const retrait = event.target.closest('.account-remove');
        const bascule = event.target.closest('.account-item');
        if (!retrait && !bascule) return;
        if (bascule && menu.classList.contains('is-managing')) return;
        if (bascule && bascule.classList.contains('is-current')) { ouvrirMenu(false); return; }
        if (retrait && !window.confirm(
            'Retirer « ' + retrait.dataset.nom + ' » de cet appareil ? Son mot de passe sera redemandé pour y revenir.'
        )) return;
        const bouton = retrait || bascule;
        const donnees = new FormData();
        donnees.set('csrf', menu.dataset.csrf);
        donnees.set('operation', retrait ? 'remove' : 'switch');
        donnees.set('compte', bouton.dataset.compte);
        const erreur = await envoyer(donnees, bouton);
        if (erreur) window.alert(erreur);
    });

    dialog.addEventListener('click', event => {
        if (event.target.closest('.account-dialog-close')) dialog.close();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        message.textContent = 'Connexion…';
        message.classList.remove('is-error');
        const erreur = await envoyer(new FormData(form), form.querySelector('.account-submit'));
        if (erreur) {
            message.textContent = erreur;
            message.classList.add('is-error');
        }
    });

    // ===== THÈME =====
    // Même stockage que la page Paramètres : 'light', 'pink', ou rien pour le thème sombre.
    const declencheurTheme = document.getElementById('theme-menu-button');
    const menuTheme = document.getElementById('theme-menu');
    if (declencheurTheme && menuTheme) {
        const menuThemes = popover(declencheurTheme, menuTheme, '.theme-option[aria-checked="true"], .theme-option');

        const themeActif = () => {
            try {
                return localStorage.getItem('app_theme') || 'default';
            } catch (error) {
                return document.documentElement.dataset.theme || 'default';
            }
        };

        function marquerActif() {
            const actuel = themeActif();
            menuTheme.querySelectorAll('.theme-option').forEach(option => {
                const choisi = option.dataset.themeVal === actuel;
                option.classList.toggle('is-current', choisi);
                option.setAttribute('aria-checked', String(choisi));
                // toggleAttribute et pas .hidden : la coche est un <svg>, qui n'a pas cette propriété.
                option.querySelector('.account-check').toggleAttribute('hidden', !choisi);
            });
        }

        function appliquerTheme(nom) {
            const racine = document.documentElement;
            if (nom === 'light' || nom === 'pink') {
                racine.setAttribute('data-theme', nom);
                document.body.setAttribute('data-theme', nom);
            } else {
                racine.removeAttribute('data-theme');
                document.body.removeAttribute('data-theme');
            }
            // Le stockage peut être refusé (navigation privée) : le thème s'applique quand même.
            try {
                if (nom === 'light' || nom === 'pink') localStorage.setItem('app_theme', nom);
                else localStorage.removeItem('app_theme');
            } catch (error) { /* le choix ne sera pas mémorisé */ }
            marquerActif();
        }

        menuTheme.addEventListener('click', event => {
            const option = event.target.closest('.theme-option');
            if (!option) return;
            appliquerTheme(option.dataset.themeVal);
            menuThemes.ouvrir(false);
        });

        marquerActif();
    }
})();
