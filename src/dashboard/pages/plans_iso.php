<?php
require_once __DIR__ . '/../includes/prefa_context.php';
require_once __DIR__ . '/../includes/prefa_attachments.php';
require_once __DIR__ . '/../includes/prefa_workshop.php';
require_once __DIR__ . '/../includes/atelier.php';

if (!$canManageWorkshop) {
    prefaError(403, 'Cette page est réservée au chef d’atelier et à l’administrateur.');
}

$workshopUsers = prefaWorkshopPersonnel($db);

// Récupérer toutes les demandes validées (statut = 2)
$stmt = $db->query("
    SELECT d.*, s.libelle, u.name, u.prenom,
           TRIM(CONCAT(taker.prenom, ' ', taker.name)) AS prise_en_charge_nom,
           p.libelle AS passivation_libelle, m.libelle AS matiere_libelle
    FROM demande_prefabrication d
    JOIN statut_demande s ON s.id = d.id_statut
    LEFT JOIN Utilisateur u ON u.id = d.idUsers
    LEFT JOIN Utilisateur taker ON taker.id = d.pris_en_charge_par
    LEFT JOIN type_passivation p ON p.id = d.id_passivation
    LEFT JOIN type_matiere m ON m.id = d.id_matiere
    WHERE d.id_statut = 2
    ORDER BY d.urgent DESC, d.date_livraison_prevue ASC, d.id DESC
");
$validatedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparer les pièces jointes, QMOS et DMOS en bloc
$attachmentsByRequest = [];
$qmosByRequest = [];
$dmosByRequest = [];

if ($validatedRequests) {
    $requestIds = array_column($validatedRequests, 'id');
    foreach ($requestIds as $rId) {
        $attachmentsByRequest[$rId] = prefaAttachments((int) $rId);
    }

    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));

    $qmosQuery = $db->prepare("SELECT id, id_demande, nom FROM demande_qmos_documents WHERE id_demande IN ($placeholders) ORDER BY id");
    $qmosQuery->execute($requestIds);
    foreach ($qmosQuery->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $qmosByRequest[$doc['id_demande']][] = $doc;
    }

    $dmosQuery = $db->prepare("SELECT id, id_demande, nom FROM demande_dmos_documents WHERE id_demande IN ($placeholders) ORDER BY id");
    $dmosQuery->execute($requestIds);
    foreach ($dmosQuery->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $dmosByRequest[$doc['id_demande']][] = $doc;
    }
}
// Nombre de plans par demande
$planCounts = [];
if ($validatedRequests) {
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
    $countStmt = $db->prepare("
        SELECT id_demande, COUNT(*) AS nb_plans
        FROM element_atelier
        WHERE id_demande IN ($placeholders)
        GROUP BY id_demande
    ");
    $countStmt->execute($requestIds);
    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $planCounts[(int) $c['id_demande']] = (int) $c['nb_plans'];
    }
}

$totalValidated = count($validatedRequests);
$toTakeCharge = count(array_filter($validatedRequests, fn($r) => empty($r['prise_en_charge_atelier'])));
$totalPlans = array_sum($planCounts);
?>

<section class="page prefa-page prefa-list plans-iso-page" id="plans-iso-page" aria-labelledby="plans-iso-title">
    <div class="prefa-shell">
        <header class="prefa-heading prefa-list-heading">
            <div>
                <h1 id="plans-iso-title">Plans / ISO & Affectations</h1>
                <p>Préparez les plans et ISO, rattachez les documents techniques et organisez les affectations.</p>
            </div>
            <button type="button" id="refresh-plans-iso" class="prefa-toggle">Actualiser</button>
        </header>

        <p class="prefa-list-summary">
            <strong><?= $totalValidated ?></strong> demande<?= $totalValidated > 1 ? 's' : '' ?> validée<?= $totalValidated > 1 ? 's' : '' ?>
            · <strong><?= $toTakeCharge ?></strong> à prendre en charge
            · <strong><?= $totalPlans ?></strong> plan<?= $totalPlans > 1 ? 's' : '' ?>
        </p>

        <?php if (!$validatedRequests): ?>
            <p class="prefa-table-empty plans-iso-empty">Aucune demande validée pour le moment.</p>
        <?php else: ?>
            <div class="plans-iso-list" id="plans-iso-list">
                <?php foreach ($validatedRequests as $index => $req):
                    $row = $req;
                    $requestId = (int) $row['id'];
                    $isTaken = !empty($row['prise_en_charge_atelier']);
                    $nbPlans = $planCounts[$requestId] ?? 0;
                    $ownerName = trim(($row['prenom'] ?? '') . ' ' . ($row['name'] ?? ''));
                    $filterData = mb_strtolower('#' . $requestId . ' ' . ($row['nom_affaire'] ?? '') . ' ' . $ownerName);
                ?>
                    <details class="plans-iso-card<?= !empty($row['urgent']) ? ' is-urgent' : '' ?>"
                        id="plans-iso-card-<?= $requestId ?>"
                        data-search="<?= prefaEscape($filterData) ?>"
                        <?= !$isTaken ? 'open' : '' ?>>

                        <summary class="plans-iso-summary">
                            <span class="plans-iso-id">#<?= $requestId ?></span>
                            <strong class="plans-iso-title"><?= prefaEscape($row['nom_affaire'] ?: 'Demande #' . $requestId) ?></strong>
                            <span class="plans-iso-owner"><?= prefaEscape($ownerName) ?></span>
                            <?php if (!empty($row['urgent'])): ?><span class="prefa-urgent">Urgente</span><?php endif; ?>

                            <span class="plans-iso-meta">
                                <?php if (!empty($row['date_livraison_prevue'])): ?>
                                    <span><?= prefaEscape(prefaFormatDate($row['date_livraison_prevue'])) ?></span>
                                <?php endif; ?>
                                <span><?= $nbPlans ?> plan<?= $nbPlans > 1 ? 's' : '' ?></span>
                            </span>
                        </summary>
                        <div class="plans-iso-body">
                            <?php require __DIR__ . '/../partials/atelier_request.php'; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>