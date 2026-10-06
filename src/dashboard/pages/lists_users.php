<?php
require_once __DIR__ . '/../../db.php';
$db = new MyPDO(__DIR__ . '/../../my_setting.ini');
$roles = $db->query('SELECT id AS id_role, name AS role_name FROM role ORDER BY id')->fetchAll();
?>

<section class="users-page">
<div class="header-list">
    <h1 class="list-h1">Liste des utilisateurs</h1>
    <form id="users-filters" class="users-filters"
        hx-get="/api/users/get.php"
        hx-trigger="load, submit, input changed delay:500ms from:input, change from:select"
        hx-target="#users-results" hx-swap="outerHTML" hx-sync="this:replace">
    <input
        type="text"
        name="q"
        placeholder="Rechercher..."
        aria-label="Rechercher un utilisateur"
    >
    <select name="role" aria-label="Filtrer les utilisateurs par rôle">
        <option value="">Tous les rôles</option>
        <?php foreach ($roles as $role): ?><option value="<?= (int) $role['id_role'] ?>"><?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
    </select>
    </form>
</div>

<div id="users-results"></div>
</section>
