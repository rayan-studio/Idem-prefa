<?php

function prefaChargers(PDO $db): array
{
    return $db->query("SELECT id, TRIM(CONCAT(prenom, ' ', name)) AS nom FROM Utilisateur WHERE id_role = 5 ORDER BY prenom, name, id")->fetchAll();
}

function prefaChargerId(PDO $db, mixed $value): ?int
{
    if ($value === '' || $value === null) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) prefaError(400, 'Sélectionnez un compte Demandeure valide.');
    $check = $db->prepare('SELECT id FROM Utilisateur WHERE id = ? AND id_role = 5');
    $check->execute([$id]);
    if (!$check->fetchColumn()) prefaError(400, 'Ce compte ne possède pas le rôle Demandeure.');
    return (int) $id;
}
