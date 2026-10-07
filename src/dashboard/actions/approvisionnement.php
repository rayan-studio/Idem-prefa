<?php
// Demandes d'approvisionnement : le personnel atelier signale un manque sur une
// demande à laquelle il est affecté ; le chef d'atelier suit le traitement.
require_once __DIR__ . '/../includes/prefa_context.php';
require_once __DIR__ . '/../includes/approvisionnement.php';
prefaPost();

$operation = (string) ($_POST['operation'] ?? '');
if (!in_array($operation, ['create', 'statut'], true)) prefaError(400, 'Action invalide.');

try {
    if ($operation === 'create') {
        if (!$isWorkshopPersonnel && !$canManageWorkshop) {
            prefaError(403, 'Seul le personnel atelier ou le chef d’atelier peut signaler un manque.');
        }

        $idDemande = filter_var($_POST['demande'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $designation = trim((string) ($_POST['designation'] ?? ''));
        $quantite = filter_var(str_replace(',', '.', trim((string) ($_POST['quantite'] ?? ''))), FILTER_VALIDATE_FLOAT);
        $unite = trim((string) ($_POST['unite'] ?? 'u'));
        $motif = (string) ($_POST['motif'] ?? '');
        $commentaire = trim((string) ($_POST['commentaire'] ?? ''));

        if (!$idDemande) prefaError(400, 'Choisissez la demande concernée.');
        if ($designation === '' || mb_strlen($designation) > 180) prefaError(400, 'Indiquez le matériel manquant (180 caractères maximum).');
        if ($quantite === false || $quantite <= 0 || $quantite > 999999) prefaError(400, 'Indiquez une quantité supérieure à zéro.');
        if (!array_key_exists($unite, appoUnites())) prefaError(400, 'Choisissez une unité dans la liste.');
        if (!array_key_exists($motif, appoMotifs())) prefaError(400, 'Choisissez un motif.');
        if (mb_strlen($commentaire) > 1000) prefaError(400, 'Commentaire trop long (1 000 caractères maximum).');

        $db->beginTransaction();

        // Le personnel atelier ne signale que sur les demandes où il a une affectation active.
        if ($isWorkshopPersonnel) {
            $stmt = $db->prepare('SELECT 1 FROM affectation_atelier a
                JOIN element_atelier e ON e.id = a.id_element
                JOIN demande_prefabrication d ON d.id = e.id_demande
                WHERE a.id_utilisateur = ? AND a.actif = 1 AND d.id = ? AND d.id_statut = 2 LIMIT 1');
            $stmt->execute([$actor['id'], $idDemande]);
            if (!$stmt->fetchColumn()) { $db->rollBack(); prefaError(403, 'Vous n’êtes pas affecté à cette demande.'); }
        } else {
            $stmt = $db->prepare('SELECT 1 FROM demande_prefabrication WHERE id = ? AND id_statut = 2');
            $stmt->execute([$idDemande]);
            if (!$stmt->fetchColumn()) { $db->rollBack(); prefaError(404, 'Demande introuvable ou non validée.'); }
        }

        $db->prepare('INSERT INTO demande_approvisionnement (id_demande, designation, quantite, unite, motif, commentaire, demande_par) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$idDemande, $designation, $quantite, $unite, $motif, $commentaire ?: null, $actor['id']]);

        $db->commit();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Manque signalé au chef d’atelier.']);
        return;
    }

    // ===== Le chef d'atelier marque un signalement traité, ou le rouvre =====
    if (!$canManageWorkshop) prefaError(403, 'Action réservée au chef d’atelier.');

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $statut = (string) ($_POST['statut'] ?? '');
    if (!$id || !array_key_exists($statut, appoStatuts())) prefaError(400, 'État invalide.');

    $db->beginTransaction();
    $stmt = $db->prepare('SELECT id FROM demande_approvisionnement WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) { $db->rollBack(); prefaError(404, 'Signalement introuvable.'); }

    // « Vue » et « Traité » portent tous deux une signature : qui s'en est saisi, et
    // quand. Rouvrir l'efface, le signalement redevient à faire.
    $signe = $statut !== 'nouvelle';
    $db->prepare('UPDATE demande_approvisionnement SET statut = ?, traite_par = ?, date_traitement = ? WHERE id = ?')
        ->execute([$statut, $signe ? $actor['id'] : null, $signe ? date('Y-m-d H:i:s') : null, $id]);

    $db->commit();
    $messages = [
        'nouvelle' => 'Signalement rouvert.',
        'vue' => 'Signalement marqué vue.',
        'traitee' => 'Signalement marqué traité.',
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => $messages[$statut]]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    prefaError(500, 'Impossible d’enregistrer. Réessayez.');
}
