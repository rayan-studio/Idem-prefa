<?php
// Enregistre la réception d'un bon de livraison : une quantité reçue par ligne,
// un commentaire global, et l'état du bon déduit des lignes.
require_once __DIR__ . '/../includes/prefa_context.php';
require_once __DIR__ . '/../includes/approvisionnement.php';
prefaPost();
if (!$canManageWorkshop) prefaError(403, 'La réception matériel est réservée au chef d’atelier.');

$idBon = filter_var($_POST['bon'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$revision = filter_var($_POST['revision'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if (!$idBon || $revision === false) prefaError(400, 'Bon de livraison invalide.');

$quantites = $_POST['quantite'] ?? null;
$commentairesLigne = $_POST['ligne_commentaire'] ?? [];
if (!is_array($quantites)) prefaError(400, 'Renseignez les quantités reçues.');
if (!is_array($commentairesLigne)) $commentairesLigne = [];

$commentaire = trim((string) ($_POST['commentaire'] ?? ''));
if (mb_strlen($commentaire) > 1000) prefaError(400, 'Commentaire trop long (1 000 caractères maximum).');

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT * FROM bon_livraison WHERE id = ? FOR UPDATE');
    $stmt->execute([$idBon]);
    $bon = $stmt->fetch();
    if (!$bon) { $db->rollBack(); prefaError(404, 'Bon de livraison introuvable.'); }
    if ((int) $bon['revision'] !== $revision) {
        $db->rollBack();
        prefaError(409, 'Ce bon a été modifié entre-temps. Actualisez avant de réessayer.');
    }

    $stmt = $db->prepare('SELECT * FROM bon_livraison_ligne WHERE id_bon = ? ORDER BY ordre, id');
    $stmt->execute([$idBon]);
    $lignes = $stmt->fetchAll();
    if (!$lignes) { $db->rollBack(); prefaError(400, 'Ce bon ne comporte aucune ligne.'); }

    $majLigne = $db->prepare('UPDATE bon_livraison_ligne SET quantite_recue = ?, commentaire = ? WHERE id = ? AND id_bon = ?');
    $apres = [];

    foreach ($lignes as $ligne) {
        $idLigne = (int) $ligne['id'];
        // Une case laissée vide veut dire « pas encore vérifiée », pas « rien reçu ».
        $brut = trim((string) ($quantites[$idLigne] ?? ''));
        if ($brut === '') {
            $recue = null;
        } else {
            $recue = filter_var(str_replace(',', '.', $brut), FILTER_VALIDATE_FLOAT);
            if ($recue === false || $recue < 0) { $db->rollBack(); prefaError(400, 'Quantité reçue invalide sur « ' . $ligne['designation'] . ' ».'); }
            if ($recue > (float) $ligne['quantite_attendue'] * 10 + 1000) { $db->rollBack(); prefaError(400, 'Quantité reçue hors de proportion sur « ' . $ligne['designation'] . ' ».'); }
        }

        $note = trim((string) ($commentairesLigne[$idLigne] ?? ''));
        if (mb_strlen($note) > 500) { $db->rollBack(); prefaError(400, 'Commentaire de ligne trop long (500 caractères maximum).'); }

        $majLigne->execute([$recue, $note ?: null, $idLigne, $idBon]);
        $apres[] = ['quantite_recue' => $recue, 'quantite_attendue' => $ligne['quantite_attendue']];
    }

    $etat = appoEtatDepuisLignes($apres);
    // Tant que tout n'est pas pointé, la réception n'est pas datée ni signée.
    $acheve = $etat !== 'attendu';
    $db->prepare('UPDATE bon_livraison SET statut = ?, commentaire = ?, receptionne_par = ?, date_reception = ?, revision = revision + 1 WHERE id = ?')
        ->execute([$etat, $commentaire ?: null, $acheve ? $actor['id'] : null, $acheve ? date('Y-m-d H:i:s') : null, $idBon]);

    $db->commit();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'statut' => $etat, 'message' => 'Réception enregistrée : ' . mb_strtolower(appoEtatsBon()[$etat])]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    prefaError(500, 'Impossible d’enregistrer la réception. Réessayez.');
}
