<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../dashboard/includes/user_display.php';

$q = trim((string) ($_GET['q'] ?? ''));
$roleFilter = filter_var($_GET['role'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$db = new MyPDO();
$where = '';
$params = [];
$conditions = [];
if ($q !== '') {
    $conditions[] = '(u.identifiant LIKE :identifiant OR u.name LIKE :name OR u.prenom LIKE :prenom)';
    $params = array_fill_keys(['identifiant', 'name', 'prenom'], '%' . $q . '%');
}
if ($roleFilter) {
    $conditions[] = 'u.id_role = :role';
    $params['role'] = $roleFilter;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$count = $db->prepare('SELECT COUNT(*) FROM Utilisateur u' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$stmt = $db->prepare('SELECT u.*, r.name AS role_name FROM Utilisateur u LEFT JOIN role r ON r.id = u.id_role' . $where . ' ORDER BY u.id ASC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value, $key === 'role' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageUrl = static function ($number) use ($q, $roleFilter) {
    return htmlspecialchars('/api/users/get.php?' . http_build_query(['page' => $number, 'q' => $q, 'role' => $roleFilter]), ENT_QUOTES, 'UTF-8');
};
?>
<div id="users-results"
     hx-get="<?= $pageUrl($page) ?>"
     hx-trigger="usersChanged from:body"
     hx-target="this" hx-swap="outerHTML">
    <table>
        <thead>
            <tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Identifiant</th><th>Rôle</th><th>Date de création</th><th>Actions</th></tr>
        </thead>
        <tbody id="search-results">
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= (int) $user['id'] ?></td>
                <td><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['identifiant'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(userRoleLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(userCreationLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <button hx-get="/api/users/edit.php?id=<?= (int) $user['id'] ?>"
                            hx-target="closest tr" hx-swap="outerHTML">Modifier</button>
                    <button hx-post="/dashboard/actions/delete_user.php?id=<?= (int) $user['id'] ?>"
                            hx-confirm="Supprimer cet utilisateur ?"
                            hx-target="closest tr" hx-swap="outerHTML">Supprimer</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($total === 0): ?>
            <tr><td colspan="7">Aucun utilisateur trouvé.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <nav class="users-pagination" aria-label="Pagination des utilisateurs">
        <button hx-get="<?= $pageUrl($page - 1) ?>" hx-target="#users-results" hx-swap="outerHTML"
                <?= $page <= 1 ? 'disabled' : '' ?>>Précédent</button>
        <span aria-live="polite">Page <?= $page ?> sur <?= $totalPages ?> — <?= $total ?> utilisateur(s)</span>
        <button hx-get="<?= $pageUrl($page + 1) ?>" hx-target="#users-results" hx-swap="outerHTML"
                <?= $page >= $totalPages ? 'disabled' : '' ?>>Suivant</button>
    </nav>
</div>
