<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../dashboard/includes/user_display.php';
$id = $_GET['id'];
$db = new MyPDO();
$sql = "SELECT u.*, r.name AS role_name FROM Utilisateur u LEFT JOIN role r ON r.id = u.id_role WHERE u.id = :id";
$stmt = $db->prepare($sql);
$stmt->execute([
    ':id' => $_GET['id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<tr>
    <td>
        <?= (int) $user['id'] ?>
        <input type="hidden" name="id"
            value="<?= (int) $user['id'] ?>">
    </td>
    <td>
        <input
            name="name"
            value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>">
    </td>
    <td>
        <input
            name="prenom"
            value="<?= htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8') ?>">

    </td>
    <td>
        <?= $user['identifiant'] ?>
        <input type="hidden" name="identifiant"
            value="<?= $user['identifiant'] ?>">
    </td>
    <td><?= htmlspecialchars(userRoleLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars(userCreationLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
    <td>
        <button
            hx-post="/dashboard/actions/edit_user.php"
            hx-include="closest tr"
            hx-target="closest tr"
            hx-swap="outerHTML">
            Enregistrer
        </button>

        <button
            hx-post="/dashboard/actions/delete_user.php"
            hx-vals=\'{"id": ' . (int) $user[' id'] . '}\'
            hx-confirm="Supprimer cet utilisateur ?"
            hx-target="closest tr"
            hx-swap="outerHTML">
            Supprimer
        </button>
    </td>
</tr>
