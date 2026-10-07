<?php

/** Comptes mémorisés dans la session, hors compte actif. */
function comptesStored(): array
{
    $comptes = $_SESSION['comptes'] ?? [];
    return is_array($comptes) ? array_values(array_filter($comptes, 'is_string')) : [];
}

/** Identifiants des comptes connectés sur cet appareil, compte actif compris. */
function comptesSession(): array
{
    $comptes = comptesStored();
    $actif = $_SESSION['identifiant'] ?? null;
    if (is_string($actif) && !in_array($actif, $comptes, true)) $comptes[] = $actif;
    return array_values(array_unique($comptes));
}

function comptesRemember(string $identifiant): void
{
    if ($identifiant === '') return;
    $comptes = comptesSession();
    if (!in_array($identifiant, $comptes, true)) $comptes[] = $identifiant;
    $_SESSION['comptes'] = $comptes;
}

function comptesForget(string $identifiant): void
{
    $_SESSION['comptes'] = array_values(array_diff(comptesSession(), [$identifiant]));
}

/**
 * Comptes connectés avec leurs informations à jour, dans leur ordre d’ajout.
 * Un compte supprimé entre-temps est oublié plutôt qu’affiché.
 */
function comptesList(PDO $db): array
{
    $comptes = comptesSession();
    if (!$comptes) return [];
    $placeholders = implode(',', array_fill(0, count($comptes), '?'));
    $stmt = $db->prepare("
        SELECT u.identifiant, u.name, u.prenom, r.name AS role_name
        FROM Utilisateur u
        JOIN role r ON r.id = u.id_role
        WHERE u.identifiant IN ($placeholders)
    ");
    $stmt->execute($comptes);
    $connus = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $connus[$row['identifiant']] = $row;
    $_SESSION['comptes'] = array_values(array_intersect($comptes, array_keys($connus)));
    return array_map(fn($identifiant) => $connus[$identifiant], $_SESSION['comptes']);
}
