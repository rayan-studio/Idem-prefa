<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../api/User.php';
require_once __DIR__ . '/../includes/user_display.php';

$db = new MyPDO();

$prenom = $_POST['prenom'];
$name = $_POST['name'];

$initial_name = mb_strtoupper(mb_substr(trim($name), 0, 1));
$identifiant = strtolower(str_replace(' ', '', $initial_name . "." . $prenom));

$stmt = $db->prepare("
    UPDATE Utilisateur
    SET name= :name,
        prenom = :prenom,
        identifiant = :identifiant
    WHERE id = :id
");

$stmt->execute([
    'name' => $name,
    'prenom' => $prenom,
    'identifiant' => $identifiant,
    'id' => $_POST['id']
]);
header('HX-Trigger-After-Swap: usersChanged');
$details = $db->prepare('SELECT u.*, r.name AS role_name FROM Utilisateur u LEFT JOIN role r ON r.id = u.id_role WHERE u.id = ?');
$details->execute([$_POST['id']]);
$user = $details->fetch();
?>
<tr>
    <td><?= (int) $_POST['id'] ?></td>
    <td><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars($identifiant, ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars(userRoleLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars(userCreationLabel($user), ENT_QUOTES, 'UTF-8') ?></td>
    <td>
        <button
            hx-get="/api/users/edit.php?id=<?= (int) $_POST['id'] ?>"
            hx-target="closest tr"
            hx-swap="outerHTML">
            Modifier
        </button>

        <button
            hx-post="/dashboard/actions/delete_user.php?id=<?= (int) $_POST['id'] ?>"
            hx-confirm="Supprimer cet utilisateur ?"
            hx-target="closest tr"
            hx-swap="outerHTML">
            Supprimer
        </button>
    </td>
</tr>
