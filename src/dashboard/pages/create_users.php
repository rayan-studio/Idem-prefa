<?php

require_once __DIR__ . '/../includes/prefa_context.php';

if (!$isAdmin) prefaError(403, 'Seul un administrateur peut créer un compte.');

?>
<section class="page">
    <div class="form-card">

        <div class="form-header">
            <h1>Créer un utilisateur</h1>
            <p>Ajoutez un nouvel utilisateur à votre application.</p>
        </div>

        <form id="create-user-form">
            <input type="hidden" name="csrf" value="<?= prefaEscape($_SESSION['prefa_csrf']) ?>">
            <div class="form-group">
                <label for="name">Nom</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Dupont"
                    autocomplete="family-name"
                    required>
            </div>

            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input
                    type="text"
                    id="prenom"
                    name="prenom"
                    placeholder="Marie"
                    autocomplete="given-name"
                    aria-describedby="identifiant-message"
                    required>
            </div>

            <div id="identifiant-message" class="form-message" role="status" aria-live="polite">Saisissez le nom et le prénom.</div>

            <div class="form-group">
                <label for="email">Adresse email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="exemple@email.com"
                    autocomplete="email">
            </div>

            <div class="form-group role-group">
                <label for="role-search">Rôle</label>

                <div class="role-select">
                    <input
                        type="text"
                        id="role-search"
                        placeholder="Rechercher un rôle..."
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="role-list"
                        aria-expanded="false">

                    <input type="hidden" id="role" name="role" required>

                    <div class="role-list" id="role-list">
                        <div class="role-option" data-value="1">
                            Administrateur
                        </div>

                        <div class="role-option" data-value="2">
                            Gestionnaire
                        </div>

                        <div class="role-option" data-value="3">
                            Utilisateur
                        </div>

                        <div class="role-option" data-value="5">
                            Demandeur
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    autocomplete="new-password"
                    required>
            </div>

            <div id="form-message" class="form-message"></div>

            <button type="submit" disabled>
                Créer l'utilisateur
            </button>

        </form>
    </div>
</section>

<script>
    // =============================
// SELECTEUR DE ROLE
// =============================

// Ouvrir la liste
$(document).on('focus', '#role-search', function () {
    $('#role-list').addClass('active');
    $(this).attr('aria-expanded', 'true');
});


// Recherche des rôles
$(document).on('input', '#role-search', function () {

    const search = $(this).val().toLowerCase().trim();

    $('.role-option').each(function () {

        const text = $(this).text().toLowerCase();

        $(this).toggleClass(
            'hidden',
            !text.includes(search)
        );
    });

    $('#role-list').addClass('active');
    $(this).attr('aria-expanded', 'true');

    // On stocke l'index directement sur l'input
    $(this).data('selected-index', -1);
});


// Sélection à la souris
$(document).on('click', '.role-option', function () {
    selectRole($(this));
});


function selectRole(option) {

    $('#role-search')
        .val(option.text().trim())
        .attr('aria-expanded', 'false');

    $('#role').val(option.data('value'));

    $('#role-list').removeClass('active');

    $('.role-option').removeClass('selected');

    option.addClass('selected');
}


// Navigation clavier
$(document).on('keydown', '#role-search', function (event) {

    const roleSearch = $(this);

    const visibleOptions = $('.role-option:not(.hidden)');

    let selectedIndex =
        roleSearch.data('selected-index') ?? -1;


    if (event.key === 'ArrowDown') {

        event.preventDefault();

        selectedIndex++;

        if (selectedIndex >= visibleOptions.length) {
            selectedIndex = 0;
        }

        updateKeyboardSelection(
            visibleOptions,
            selectedIndex
        );
    }


    if (event.key === 'ArrowUp') {

        event.preventDefault();

        selectedIndex--;

        if (selectedIndex < 0) {
            selectedIndex = visibleOptions.length - 1;
        }

        updateKeyboardSelection(
            visibleOptions,
            selectedIndex
        );
    }


    if (event.key === 'Enter') {

        if (
            selectedIndex >= 0 &&
            visibleOptions.eq(selectedIndex).length
        ) {
            event.preventDefault();

            selectRole(
                visibleOptions.eq(selectedIndex)
            );
        }
    }


    if (event.key === 'Escape') {

        $('#role-list').removeClass('active');

        roleSearch.attr(
            'aria-expanded',
            'false'
        );
    }


    roleSearch.data(
        'selected-index',
        selectedIndex
    );
});


function updateKeyboardSelection(options, index) {

    $('.role-option').removeClass('selected');

    const option = options.eq(index);

    if (option.length) {

        option.addClass('selected');

        option[0].scrollIntoView({
            block: 'nearest'
        });
    }
}


// Fermer la liste en cliquant ailleurs
$(document).on('click', function (event) {

    if (!$(event.target).closest('.role-select').length) {

        $('#role-list').removeClass('active');

        $('#role-search').attr(
            'aria-expanded',
            'false'
        );
    }
});
</script>
