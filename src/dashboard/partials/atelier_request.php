<?php

/** @var PDO $db */
/** @var int $requestId */
/** @var array<string, mixed> $row */
/** @var array<int, array<int, array<string, mixed>>> $attachmentsByRequest */
/** @var array<int, array<int, array<string, mixed>>> $qmosByRequest */
/** @var array<int, array<int, array<string, mixed>>> $dmosByRequest */
/** @var bool $canManageWorkshop */
/** @var array<int, array<string, mixed>> $workshopUsers */

require_once __DIR__ . '/../includes/atelier.php';
$elements = atelierElements($db, $requestId);
$types = ['plan' => 'Plan / ISO', 'montage' => 'Montage', 'soudage' => 'Suivi soudage'];
$taken = !empty($row['prise_en_charge_atelier']);
$documentOptions = [];
foreach ($attachmentsByRequest[$requestId] ?? [] as $file) $documentOptions['plan:' . $file['key']] = $file['name'];
foreach (['qmos' => $qmosByRequest[$requestId] ?? [], 'dmos' => $dmosByRequest[$requestId] ?? []] as $kind => $files) {
    foreach ($files as $file) $documentOptions[$kind . ':' . $file['id']] = strtoupper($kind) . ' — ' . $file['nom'];
}
$activeCount = 0;
foreach ($elements as $element) foreach ($element['affectations'] as $assignment) if ($assignment['actif']) $activeCount++;
?>
<section class="prefa-workshop-planning atelier-section" aria-labelledby="atelier-title-<?= $requestId ?>">
    <header class="atelier-overview">
        <div>
            <h3 id="atelier-title-<?= $requestId ?>">Travaux à affecter</h3>
            <p class="atelier-muted">Plan / ISO, montage et soudage sont les travaux à réaliser. Les fichiers de plan, QMOS et DMOS sont les documents utilisés.</p>
        </div>
        <span class="atelier-state <?= $taken ? 'is-taken' : '' ?>"><?= $taken ? 'Pris en charge' : 'À prendre en charge' ?></span>
    </header>
    <div class="atelier-overview-meta">
        <span>Responsable atelier : <strong><?= prefaEscape($row['prise_en_charge_nom'] ?: ($taken ? 'Non renseigné' : 'En attente')) ?></strong></span>
        <?php if ($elements): ?><span><?= count($elements) ?> <?= count($elements) === 1 ? 'plan' : 'plans' ?> · <?= $activeCount ?> <?= $activeCount === 1 ? 'travail affecté' : 'travaux affectés' ?></span><?php endif; ?>
    </div>
    <?php if ($canManageWorkshop && !$taken): ?>
        <div class="atelier-start">
            <p><?= $isAdmin ? 'Choisissez le chef d’atelier responsable de cette demande.' : 'Prenez en charge cette demande pour préparer les plans et répartir le travail.' ?></p>
            <form class="atelier-action prefa-form" data-endpoint="save_atelier.php">
                <input type="hidden" name="csrf" value="<?= prefaEscape($_SESSION['prefa_csrf']) ?>"><input type="hidden" name="id" value="<?= $requestId ?>"><input type="hidden" name="operation" value="take">
                <?php if ($isAdmin): $chiefs = $db->query("SELECT id, TRIM(CONCAT(prenom, ' ', name)) AS nom FROM Utilisateur WHERE id_role = 2 ORDER BY prenom, name, id")->fetchAll(); ?>
                    <div class="atelier-chief-picker"><label for="atelier-chief-<?= $requestId ?>">Chef d’atelier responsable</label><select id="atelier-chief-<?= $requestId ?>" name="chef" required>
                            <option value="">Sélectionner un chef d’atelier</option>
                            <?php foreach ($chiefs as $chief): ?><option value="<?= (int) $chief['id'] ?>"><?= prefaEscape($chief['nom']) ?></option><?php endforeach; ?>
                        </select></div>
                    <?php if (!$chiefs): ?><p class="atelier-muted">Aucun chef d’atelier disponible. Créez un compte avec ce rôle pour pouvoir l’affecter.</p><?php endif; ?>
                <?php endif; ?>
                <div class="atelier-form-actions"><button type="submit" <?= $isAdmin && !$chiefs ? 'disabled' : '' ?>><?= $isAdmin ? 'Affecter le chef d’atelier' : 'Prendre en charge' ?></button><span class="atelier-message" role="status" aria-live="polite"></span></div>
            </form>
        </div>
    <?php endif; ?>
    <?php if ($canManageWorkshop && $taken): ?>
        <details class="atelier-new-element" <?= !$elements ? 'open' : '' ?>>
            <summary>Ajouter un plan</summary>
            <?php if (!$elements): ?><p class="atelier-muted">Commencez par un plan, par exemple ISO-01, et sélectionnez ses documents.</p><?php endif; ?>
            <form class="atelier-action prefa-form atelier-grid" data-endpoint="save_atelier.php">
                <input type="hidden" name="csrf" value="<?= prefaEscape($_SESSION['prefa_csrf']) ?>"><input type="hidden" name="id" value="<?= $requestId ?>"><input type="hidden" name="operation" value="element">
                <div><label for="atelier-ref-<?= $requestId ?>">Libele</label><input id="atelier-ref-<?= $requestId ?>" name="reference" maxlength="80" required></div>
                <div><label for="atelier-label-<?= $requestId ?>">Description</label><input id="atelier-label-<?= $requestId ?>" name="libelle" maxlength="180" required></div>
                <?php if ($documentOptions): ?>
                    <fieldset class="atelier-document-picker">
                        <legend>Documents à associer</legend>
                        <p class="atelier-muted">Sélectionnez au moins un fichier de plan / ISO. Les personnes affectées auront accès aux documents sélectionnés.</p>
                        <?php foreach ($documentOptions as $token => $label): ?><label class="atelier-checkbox"><input type="checkbox" name="documents[]" value="<?= prefaEscape($token) ?>" <?= str_starts_with($token, 'plan:') && count($attachmentsByRequest[$requestId] ?? []) === 1 ? 'checked' : '' ?>> <span><?= prefaEscape($label) ?></span></label><?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>
                <div class="atelier-form-actions"><button type="submit">Ajouter</button><span class="atelier-message" role="status" aria-live="polite"></span></div>
            </form>
        </details>
    <?php elseif (!$elements && !$canManageWorkshop): ?>
        <p class="atelier-muted atelier-no-plans">Le chef d’atelier n’a pas encore préparé les plans et les tâches.</p>
    <?php endif; ?>
    <?php if ($elements): ?>
        <div class="atelier-plans-scroll">
            <table class="atelier-plans-table">
                <thead>
                    <tr>
                        <th>Plan et documents</th>
                        <th>Travail sur le plan / ISO</th>
                        <th>Montage</th>
                        <th>Soudage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($elements as $element):
                        $active = [];
                        $history = [];
                        foreach ($element['affectations'] as $assignment) {
                            if ($assignment['actif']) $active[$assignment['type_affectation']] = $assignment;
                            else $history[] = $assignment;
                        }
                        $formId = 'atelier-manage-' . (int) $element['id'];
                        $availableDocuments = [];
                        foreach ($element['documents'] as $doc) {
                            $name = atelierDocumentName($db, $requestId, $doc['type_document'], $doc['cle_document']);
                            if ($name !== null) {
                                $endpoint = $doc['type_document'] === 'plan' ? 'download_prefa_attachment.php' : 'download_' . $doc['type_document'] . '.php';
                                $availableDocuments[] = ['name' => $name, 'url' => 'actions/' . $endpoint . '?id=' . $requestId . '&file=' . rawurlencode($doc['cle_document'])];
                            }
                        }
                    ?>
                        <tr class="atelier-plan-summary">
                            <td><strong><?= prefaEscape($element['reference']) ?></strong><span class="atelier-row-description"><?= prefaEscape($element['libelle']) ?></span>
                                <?php if (!$availableDocuments): ?><span class="atelier-missing-file"><?= !empty($attachmentsByRequest[$requestId]) ? 'Le fichier de la demande reste à rattacher à ce plan.' : 'Aucun fichier rattaché à ce plan.' ?></span><?php endif; ?>
                                <?php if ($canManageWorkshop && $taken): ?>
                                    <details class="atelier-plan-information">
                                        <summary><?= $availableDocuments ? 'Modifier les documents' : 'Associer un fichier' ?></summary>
                                        <form class="atelier-action prefa-form atelier-document-edit" data-endpoint="save_atelier.php">
                                            <input type="hidden" name="csrf" value="<?= prefaEscape($_SESSION['prefa_csrf']) ?>"><input type="hidden" name="id" value="<?= $requestId ?>"><input type="hidden" name="operation" value="documents"><input type="hidden" name="element" value="<?= (int) $element['id'] ?>">
                                            <p class="atelier-muted">Sélectionnez au moins un fichier de plan / ISO.</p>
                                            <?php $selectedDocuments = array_map(fn($doc) => $doc['type_document'] . ':' . $doc['cle_document'], $element['documents']); ?>
                                            <?php foreach ($documentOptions as $token => $label): ?><label class="atelier-checkbox"><input type="checkbox" name="documents[]" value="<?= prefaEscape($token) ?>" <?= in_array($token, $selectedDocuments, true) ? 'checked' : '' ?>><span><?= prefaEscape($label) ?></span></label><?php endforeach; ?>
                                            <?php if (!$documentOptions): ?><p class="atelier-muted">Ajoutez d’abord un fichier à la demande.</p><?php endif; ?>
                                            <div class="atelier-form-actions"><button type="submit" <?= !$documentOptions ? 'disabled' : '' ?>>Enregistrer les documents</button><span class="atelier-message" role="status" aria-live="polite"></span></div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <?php if ($availableDocuments): ?>
                                    <details class="atelier-plan-information">
                                        <summary>Documents (<?= count($availableDocuments) ?>)</summary>
                                        <div class="atelier-plan-files"><?php foreach ($availableDocuments as $doc): ?><a class="prefa-document-link<?= strtolower(pathinfo($doc['name'], PATHINFO_EXTENSION)) === 'pdf' ? ' prefa-pdf-link' : '' ?>" data-name="<?= prefaEscape($doc['name']) ?>" href="<?= prefaEscape($doc['url']) ?>"><?= prefaEscape($doc['name']) ?></a><?php endforeach; ?></div>
                                    </details>
                                <?php endif; ?>
                                <?php if ($history): ?>
                                    <details class="atelier-plan-information atelier-assignment-history">
                                        <summary>Historique des affectations (<?= count($history) ?>)</summary>
                                        <ol class="atelier-history-timeline">
                                            <?php foreach ($history as $assignment): ?>
                                                <li>
                                                    <div class="atelier-history-entry"><strong><?= prefaEscape($assignment['utilisateur_nom']) ?></strong><span class="atelier-history-kind"><?= $types[$assignment['type_affectation']] ?></span><span class="atelier-history-period">Du <?= date('d/m/Y à H:i', strtotime($assignment['date_affectation'])) ?><?php if ($assignment['date_fin_affectation']): ?> au <?= date('d/m/Y à H:i', strtotime($assignment['date_fin_affectation'])) ?><?php endif; ?></span><span class="atelier-history-ended">Affectation terminée <span class="atelier-history-accessible">(ancienne affectation)</span></span><?php if ($assignment['commentaire']): ?><p class="atelier-note"><?= prefaEscape($assignment['commentaire']) ?></p><?php endif; ?></div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($types as $type => $label): $assignment = $active[$type] ?? null; ?>
                                <td class="atelier-work-cell">
                                    <?php if ($canManageWorkshop && $taken): ?>
                                        <button type="button" class="atelier-edit-assignment atelier-person-button <?= $assignment ? 'is-assigned' : '' ?>" data-form="<?= $formId ?>" data-type="<?= $type ?>" data-label="<?= prefaEscape($element['reference'] . ' · ' . $label) ?>" data-user="<?= $assignment ? (int) $assignment['id_utilisateur'] : '' ?>" aria-controls="<?= $formId ?>" aria-expanded="false">
                                            <?= $assignment ? prefaEscape($assignment['utilisateur_nom']) : '+ Affecter' ?>
                                        </button>
                                    <?php else: ?><span><?= $assignment ? prefaEscape($assignment['utilisateur_nom']) : 'Non affecté' ?></span><?php endif; ?>
                                    <?php if ($assignment): ?><span class="atelier-work-status"><?= (int) $assignment['avancement'] === 100 ? 'Terminé' : ((int) $assignment['avancement'] > 0 ? 'En cours' : 'À commencer') ?></span><?php endif; ?>
                                    <?php if ($assignment && $assignment['commentaire']): ?><details class="atelier-work-report">
                                            <summary>Compte rendu</summary>
                                            <p class="atelier-note"><?= prefaEscape($assignment['commentaire']) ?></p>
                                        </details><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php if ($canManageWorkshop && $taken): ?>
                            <tr class="atelier-editor-row" hidden>
                                <td colspan="4">
                                    <?php if ($canManageWorkshop && $taken): ?>
                                        <div id="<?= $formId ?>" class="atelier-manage-panel" hidden>
                                            <h5 class="atelier-manage-title">Affecter une personne</h5>
                                            <form class="atelier-action prefa-form atelier-manage-form" data-endpoint="save_atelier.php">
                                                <input type="hidden" name="csrf" value="<?= prefaEscape($_SESSION['prefa_csrf']) ?>"><input type="hidden" name="id" value="<?= $requestId ?>"><input type="hidden" name="operation" value="assign"><input type="hidden" name="element" value="<?= (int) $element['id'] ?>"><input type="hidden" name="type" value="plan">
                                                <div><label for="atelier-user-<?= $element['id'] ?>">Personnel atelier</label><select id="atelier-user-<?= $element['id'] ?>" name="utilisateur" required>
                                                        <option value="">Sélectionner une personne</option><?php foreach ($workshopUsers as $user): ?><option value="<?= (int) $user['id'] ?>"><?= prefaEscape($user['nom']) ?></option><?php endforeach; ?>
                                                    </select></div>
                                                <div class="atelier-form-actions">
                                                    <button type="submit" <?= !$workshopUsers ? 'disabled' : '' ?>>Enregistrer</button>
                                                    <button type="submit" value="revoke" formnovalidate class="atelier-remove-assignment" hidden>Retirer l’affectation</button>
                                                    <button type="button" class="atelier-cancel-assignment">Annuler</button>
                                                    <span class="atelier-message" role="status" aria-live="polite"></span>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php if (!empty($row['id_personnel_atelier'])): ?>
        <details class="atelier-legacy">
            <summary>Ancienne affectation de la demande</summary>
            <p class="atelier-muted"><?= prefaEscape($row['personnel_atelier_nom'] ?: 'Non renseignée') ?> était affecté à la demande entière. Cette information est conservée ; les accès actuels dépendent des plans et des tâches affectés ci-dessus.</p>
        </details>
    <?php endif; ?>
</section>