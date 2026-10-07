<?php

/**
 * Réception matériel et demandes d'approvisionnement : libellés partagés et
 * calcul de l'état d'un bon de livraison.
 */

/** Motifs d'un manque de matériel, dans l'ordre où ils sont proposés. */
function appoMotifs(): array
{
    return [
        'oubli' => 'Oubli',
        'erreur' => 'Erreur',
        'fabrication' => 'Besoin de fabrication',
        'autre' => 'Autre',
    ];
}

/** Étapes de traitement d'une demande d'approvisionnement. */
function appoStatuts(): array
{
    return [
        'nouvelle' => 'Nouvelle',
        'prise_en_compte' => 'Prise en compte',
        'commandee' => 'Commandée',
        'livree' => 'Livrée',
        'refusee' => 'Refusée',
    ];
}

/** États d'un bon de livraison. */
function appoEtatsBon(): array
{
    return [
        'attendu' => 'En attente de réception',
        'partielle' => 'Réception partielle',
        'complete' => 'Réception complète',
        'refusee' => 'Réception refusée',
    ];
}

/**
 * Déduit l'état d'un bon des quantités pointées sur ses lignes.
 *
 * Une ligne non pointée (quantite_recue à NULL) laisse le bon « en attente » :
 * tant qu'il reste une ligne à voir, la réception n'est pas tranchée.
 *
 * @param array<int, array<string, mixed>> $lignes
 */
function appoEtatDepuisLignes(array $lignes): string
{
    if (!$lignes) return 'attendu';

    $pointees = 0;
    $conformes = 0;
    $recu = 0.0;

    foreach ($lignes as $ligne) {
        if ($ligne['quantite_recue'] === null) continue;
        $pointees++;
        $quantite = (float) $ligne['quantite_recue'];
        $recu += $quantite;
        if ($quantite + 0.0005 >= (float) $ligne['quantite_attendue']) $conformes++;
    }

    if ($pointees < count($lignes)) return 'attendu';
    if ($conformes === count($lignes)) return 'complete';
    return $recu > 0 ? 'partielle' : 'refusee';
}

/** Quantité manquante sur une ligne, 0 si elle est servie ou pas encore pointée. */
function appoManquant(array $ligne): float
{
    if ($ligne['quantite_recue'] === null) return 0.0;
    return max(0.0, (float) $ligne['quantite_attendue'] - (float) $ligne['quantite_recue']);
}

/** Affiche une quantité sans les zéros décimaux inutiles : 12,000 devient 12. */
function appoQuantite($valeur): string
{
    $nombre = (float) $valeur;
    return rtrim(rtrim(number_format($nombre, 3, ',', ' '), '0'), ',');
}
