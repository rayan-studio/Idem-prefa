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

/**
 * Unités de comptage, en liste fermée.
 *
 * Un champ libre laissait passer « U », « unite », « Un. » pour la même chose, alors
 * que les lignes de bon de livraison, elles, comptent en mètres ou en kilos.
 */
function appoUnites(): array
{
    return [
        'u' => 'u — unité',
        'm' => 'm — mètre',
        'kg' => 'kg — kilogramme',
        'l' => 'l — litre',
        'm2' => 'm² — mètre carré',
        'lot' => 'lot',
    ];
}

/**
 * État d'un signalement. Deux valeurs suffisent : le chef d'atelier a besoin de
 * voir ce qui lui est remonté et de retirer de sa liste ce qu'il a traité, pas de
 * suivre un circuit d'achat.
 */
function appoStatuts(): array
{
    return [
        'nouvelle' => 'À faire',
        'vue' => 'Vue',
        'traitee' => 'Traité',
    ];
}

/** Affiche une quantité sans les zéros décimaux inutiles : 12,000 devient 12. */
function appoQuantite($valeur): string
{
    $nombre = (float) $valeur;
    return rtrim(rtrim(number_format($nombre, 3, ',', ' '), '0'), ',');
}
