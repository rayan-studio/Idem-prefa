<?php

/** Allocate inside the transaction that creates the request. */
function prefaAssignReference(PDO $db, int $id, int $year): string
{
    if (!$db->inTransaction()) throw new LogicException('La numérotation nécessite une transaction.');
    $counter = $db->prepare('INSERT INTO prefa_reference_compteur (annee, dernier_numero) VALUES (?, 1)
        ON DUPLICATE KEY UPDATE dernier_numero = dernier_numero + 1');
    $counter->execute([$year]);
    $find = $db->prepare('SELECT dernier_numero FROM prefa_reference_compteur WHERE annee = ? FOR UPDATE');
    $find->execute([$year]);
    $number = (int) $find->fetchColumn();
    $reference = sprintf('%02d-DP-%03d', $year % 100, $number);
    $save = $db->prepare('INSERT INTO prefa_reference (id_demande, annee, numero, reference) VALUES (?, ?, ?, ?)');
    $save->execute([$id, $year, $number, $reference]);
    return $reference;
}

/** One lookup for the entire page, including workshop history. */
function prefaReference(int $id): string
{
    global $db;
    static $references = null;
    $references ??= $db->query('SELECT id_demande, reference FROM prefa_reference')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!isset($references[$id])) throw new RuntimeException('Référence de demande manquante. Appliquez la migration des références.');
    return (string) $references[$id];
}
