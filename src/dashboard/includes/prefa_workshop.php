<?php

function prefaWorkshopPersonnel(PDO $db): array
{
    return $db->query("
        SELECT u.id, u.name, u.prenom, TRIM(CONCAT(u.prenom, ' ', u.name)) AS nom
        FROM Utilisateur u
        JOIN role r ON r.id = u.id_role
        WHERE r.name IN ('Utilisateur', 'Personnel atelier')
        ORDER BY u.prenom, u.name, u.id
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function prefaWorkshopPersonnelId(PDO $db, mixed $value): ?int
{
    if ($value === '' || $value === null) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) prefaError(400, 'Sélectionnez un membre du personnel atelier valide.');
    $check = $db->prepare("
        SELECT u.id
        FROM Utilisateur u
        JOIN role r ON r.id = u.id_role
        WHERE u.id = ?
          AND r.name IN ('Utilisateur', 'Personnel atelier')
    ");
    $check->execute([$id]);
    if (!$check->fetchColumn()) prefaError(400, 'Ce compte ne possède pas le rôle Utilisateur.');
    return (int) $id;
}
