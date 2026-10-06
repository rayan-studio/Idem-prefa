<?php

session_start();
header('Cache-Control: no-store');

if (!isset($_SESSION['identifiant'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = $_SESSION['role_name'] ?? '';
if ($role === "Demandeur : Chargé d'affaires") {
    $role = $_SESSION['role_name'] = 'Demandeure';
}
$isWorkshopChief = in_array($role, ["Chef d'atelier", 'Gestionaire', 'Gestionnaire'], true);
if ($isWorkshopChief) $role = "Chef d'atelier";
$name = $_SESSION['name'] ?? '';
$prenom = $_SESSION['prenom'] ?? '';
$isWorkshopPersonnel = in_array($role, ['Utilisateur', 'Personnel atelier'], true);
$isRequester = in_array($role, ['Demandeur', 'Demandeure', "Chargé d'affaires"], true);
$initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
$urgentPending = 0;
if ($role === 'Adminstrateure') {
    require_once __DIR__ . '/../db.php';
    $db = new MyPDO(__DIR__ . '/../my_setting.ini');
    $urgentPending = (int) $db->query('SELECT COUNT(*) FROM demande_prefabrication WHERE urgent = 1 AND id_statut = 1')->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" href="data:,">
    <script>
        (function() {
            var savedTheme = localStorage.getItem('app_theme');
            if (savedTheme === 'pink') {
                document.documentElement.setAttribute('data-theme', 'pink');
                document.addEventListener('DOMContentLoaded', function() {
                    if (document.body) document.body.setAttribute('data-theme', 'pink');
                });
            }
        })();
    </script>
    <link href="../css/dashboard/settings.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/settings.css') ?>" rel="stylesheet" />
    <link href="../assets/vendor/vis-timeline/vis-timeline.min.css" rel="stylesheet" />
    <link href="../css/dashboard/dashboard-base.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/dashboard-base.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/dashboard-forms.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/dashboard-forms.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/prefa-form.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/prefa-form.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/prefa-list.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/prefa-list.css') ?>" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.11/dist/htmx.js" integrity="sha384-gmJEF2eAKY4e+FDN+qtKIivWyb6ANwDB7JUdUybKgQspPKyEX/pIRZG/0uaRoW2C" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>
    <link href="../css/dashboard/planning.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/planning.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/atelier.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/atelier.css') ?>" rel="stylesheet" />
    <link href="../css/floating-fields.css?v=<?= filemtime(__DIR__ . '/../css/floating-fields.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/pink-theme.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/pink-theme.css') ?>" rel="stylesheet" />
    <link href="../css/dashboard/planning-timeline.css?v=<?= filemtime(__DIR__ . '/../css/dashboard/planning-timeline.css') ?>" rel="stylesheet" />
    <script src="../js/floating-fields.js?v=<?= filemtime(__DIR__ . '/../js/floating-fields.js') ?>" defer></script>
</head>

<body>
    <div id="contenaire">
        <nav id="left-panel">
            <div class="sidebar-mobile-header">
                <strong>Navigation</strong>
                <button id="close-sidebar" type="button" aria-label="Fermer le menu">×</button>
            </div>
            <div class="nav-header">
                <?php if ($role == "Adminstrateure"): ?>
                    <button id="btn-create-user" class="nav-btn" type="button">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M19 8v6M22 11h-6" />
                        </svg>
                        <span>Créer un utilisateur</span>
                    </button>

                    <button id="btn-list-users" class="nav-btn" type="button">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M19 8v6M22 11h-6" />
                        </svg>
                        <span>Liste des utilisateurs</span>
                    </button>
                    <button id="btn-settings" class="nav-btn" type="button">
                        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" />
                            <circle cx="12" cy="12" r="3.5" />
                        </svg>
                        <span>Paramètres</span>
                    </button>
                <?php endif ?>

                <?php if ($isRequester || $isWorkshopChief): ?>
                    <button id="btn-prefa" class="nav-btn" type="button">
                        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m10 3 7 4-7 4-7-4 7-4Z" />
                            <path d="M3 7v9l7 4v-9M17 7v5M10 20l3-1.7" />
                            <path d="M18 15v6M15 18h6" />
                        </svg>
                        <span>Créer une demande</span>
                    </button>
                <?php endif; ?>
                <button id="btn-list-prefa" class="nav-btn" type="button">
                    <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="4" width="14" height="17" rx="2" />
                        <path d="M9 4V2h6v2M9 10l2 2 4-4M9 16h6" />
                    </svg>
                    <span><?= $role === 'Adminstrateure' ? 'Vérifier les demandes' : ($isWorkshopChief ? 'Suivi des demandes' : ($isWorkshopPersonnel ? 'Mes affectations' : 'Mes demandes')) ?></span>
                    <?php if ($role === 'Adminstrateure'): ?>
                        <span id="urgent-pending-count" class="nav-urgent-badge" aria-live="polite" aria-label="<?= $urgentPending ?> demandes urgentes en attente" title="Demandes urgentes en attente" <?= $urgentPending === 0 ? 'hidden' : '' ?>><?= $urgentPending ?></span>
                    <?php endif; ?>
                </button>
                <?php if ($role === 'Adminstrateure' || $isWorkshopChief): ?>
                    <button id="btn-planning" class="nav-btn" type="button">
                        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18M7 15h3M14 15h3M7 18h3" /></svg>
                        <span>Planning</span>
                    </button>
                    <?php endif; ?>
                <?php if ($role === 'Adminstrateure'): ?>
                    <button id="btn-urgent-prefa" class="nav-btn" type="button">
                        <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3 2.8 19h18.4L12 3Z" />
                            <path d="M12 9v4M12 16h.01" />
                        </svg>
                        <span>Demandes urgentes</span>
                        <span class="nav-urgent-badge" aria-hidden="true" <?= $urgentPending === 0 ? 'hidden' : '' ?>><?= $urgentPending ?></span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="nav-footer">
                <div class="user-avatar"><?= htmlspecialchars($initial) ?></div>

                <div class="nav-subfooter">
                    <p class="title"><?= htmlspecialchars($name . ' ' . $prenom) ?></p>
                    <span class="subtitle"><?= htmlspecialchars($role) ?></span>
                </div>

                <a class="nav-logout" href="../auth/logout.php" title="Se déconnecter" aria-label="Se déconnecter">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="M16 17l5-5-5-5M21 12H9" />
                    </svg>
                </a>
            </div>
        </nav>

        <button id="sidebar-backdrop" type="button" aria-label="Fermer le menu" tabindex="-1" hidden></button>
        <div id="drag"></div>

        <div id="right-panel">
            <div class="dashboard-toolbar">
                <button id="btn-hide" type="button" aria-label="Afficher ou masquer le menu" aria-controls="left-panel" aria-expanded="true">
                    <svg class="icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <path d="M9 3v18" />
                    </svg>
                    <span>Menu</span>
                </button>
            </div>

            <div id="content">
                <section class="dashboard-welcome" aria-labelledby="welcome-title">
                    <div class="welcome-card">
                        <svg class="welcome-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <path d="M9 3v18M9 9h12" />
                        </svg>
                        <h1 id="welcome-title">Bienvenue dans votre espace</h1>
                        <?php if ($role == "Adminstrateure"): ?>
                            <p>Sélectionnez une rubrique dans le menu pour commencer.</p>
                        <?php else: ?>
                            <p>Vous êtes connecté à votre tableau de bord.</p>
                        <?php endif ?>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="../js/dashboard-navigation.js?v=<?= hash_file('sha256', __DIR__ . '/../js/dashboard-navigation.js') ?>"></script>
    <script src="../js/dashboard-forms.js?v=<?= hash_file('sha256', __DIR__ . '/../js/dashboard-forms.js') ?>"></script>
    <script src="../js/prefa-interactions.js?v=<?= hash_file('sha256', __DIR__ . '/../js/prefa-interactions.js') ?>"></script>
    <script src="../assets/vendor/vis-timeline/vis-timeline.min.js"></script>
    <script src="../js/planning-timeline.js?v=<?= hash_file('sha256', __DIR__ . '/../js/planning-timeline.js') ?>"></script>
    <script src="../js/planning.js?v=<?= hash_file('sha256', __DIR__ . '/../js/planning.js') ?>"></script>
    <script src="../js/atelier.js?v=<?= hash_file('sha256', __DIR__ . '/../js/atelier.js') ?>"></script>
    <script src="../js/dashboard-sidebar.js?v=<?= hash_file('sha256', __DIR__ . '/../js/dashboard-sidebar.js') ?>"></script>
</body>

</html>
