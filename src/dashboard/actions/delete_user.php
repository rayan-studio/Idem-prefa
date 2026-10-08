<?php
require_once __DIR__ . '/../includes/prefa_context.php';

prefaPost();
if (!$isAdmin) prefaError(403, 'Seul un administrateur peut supprimer un compte.');

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) prefaError(400, 'Compte invalide.');

// Garde-fou : un administrateur seul qui supprime son propre compte se ferme la porte,
// et plus personne ne peut créer de compte.
if ($id === (int) $actor['id']) prefaError(409, 'Vous ne pouvez pas supprimer votre propre compte.');

try {
    $stmt = $db->prepare('DELETE FROM Utilisateur WHERE id = :id');
    $stmt->execute(['id' => $id]);
} catch (PDOException $e) {
    // Le compte est référencé par ses demandes, ses affectations ou ses pointages.
    prefaError(409, 'Ce compte a déjà travaillé sur des demandes : il ne peut plus être supprimé.');
}

header('HX-Trigger-After-Swap: usersChanged');
