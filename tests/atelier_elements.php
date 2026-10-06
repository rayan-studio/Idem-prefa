<?php
chdir(is_dir('/var/www/html') ? '/var/www/html' : __DIR__ . '/../src');
require_once 'db.php';
require_once 'dashboard/includes/prefa_attachments.php';
require_once 'dashboard/includes/atelier.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
check(atelierLocalDateTime('2026-10-06 06:06:00', 'H:i') === '08:06', 'Summer UTC timestamps must display in Paris time.');
check(atelierLocalDateTime('2026-12-06 06:06:00', 'H:i') === '07:06', 'Winter UTC timestamps must display in Paris time.');
check(atelierLocalDateTime('2026-10-06 23:30:00', 'd/m/Y') === '07/10/2026', 'Paris conversion must handle midnight correctly.');
function callEndpoint(string $path, string $identity, array $post = [], array $get = []): array
{
    $script = 'chdir("/var/www/html"); session_id(' . var_export('atelier' . bin2hex(random_bytes(12)), true) . '); session_start();'
        . '$_SESSION = ' . var_export(['identifiant' => $identity, 'prefa_csrf' => 'atelier-test-token'], true) . '; session_write_close();'
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($post ? 'POST' : 'GET', true) . ';'
        . '$_POST = ' . var_export($post, true) . '; $_GET = ' . var_export($get, true) . ';'
        . 'register_shutdown_function(function () { echo "\nHTTP_CODE:", http_response_code() ?: 200; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });'
        . 'require ' . var_export($path, true) . ';';
    $process = proc_open(['php', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $body = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
    check($exit === 0, 'Endpoint error: ' . $errors . ' ' . $body);
    check((bool) preg_match('/\nHTTP_CODE:(\d+)$/', $body, $match), 'Missing endpoint status.');
    return ['status' => (int) $match[1], 'body' => substr($body, 0, -strlen($match[0]))];
}
$db = new MyPDO();
$admin = $db->query('SELECT identifiant FROM Utilisateur WHERE id_role = 1 ORDER BY id LIMIT 1')->fetchColumn();
check((bool) $admin, 'Administrator required.');
$prefix = 'atelier.test.' . bin2hex(random_bytes(8));
$ids = []; $requests = []; $paths = [];
$save = 'dashboard/actions/save_atelier.php'; $update = 'dashboard/actions/update_atelier.php';
try {
    $insert = $db->prepare('INSERT INTO Utilisateur (name, prenom, identifiant, id_role, password) VALUES (?, ?, ?, ?, ?)');
    foreach (['chef' => 2, 'chef2' => 2, 'dem' => 5, 'w1' => 3, 'w2' => 3] as $name => $role) {
        $insert->execute([$name, 'AtelierTest', $prefix . '.' . $name, $role, password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT)]);
        $ids[$name] = (int) $db->lastInsertId();
    }
    $rolePage = callEndpoint('api/users/get.php', $admin, [], ['q' => $prefix, 'role' => '2']);
    check(str_contains($rolePage['body'], $prefix . '.chef') && !str_contains($rolePage['body'], $prefix . '.w1') && !str_contains($rolePage['body'], $prefix . '.dem'), 'Role and search filters must work together.');
    check(str_contains($rolePage['body'], 'role=2'), 'Pagination must retain the role filter.');
    $emptyRolePage = callEndpoint('api/users/get.php', $admin, [], ['q' => $prefix . '.w1', 'role' => '2']);
    check(str_contains($emptyRolePage['body'], 'Aucun utilisateur trouvé.'), 'Search matches outside the selected role must be excluded.');
    $allRolesPage = callEndpoint('api/users/get.php', $admin, [], ['q' => $prefix, 'role' => '']);
    check(str_contains($allRolesPage['body'], $prefix . '.w1') && str_contains($allRolesPage['body'], $prefix . '.chef'), 'All roles must preserve text search.');
    $insert = $db->prepare('INSERT INTO demande_prefabrication (idUsers, DateUpdate, date_creation, pouces_total_iso, heures_chiffrees, CDN, ` QMOS`, urgent, id_statut) VALUES (?, NOW(), NOW(), 10, 48, 0, "", 0, ?)');
    foreach ([2, 1, 2, 2] as $status) { $insert->execute([$ids['dem'], $status]); $requests[] = (int) $db->lastInsertId(); }
    [$request, $pending, $other, $adminReq] = $requests;
    $directory = prefaAttachmentDirectory($request);
    check(mkdir($directory, 0777, true), 'Cannot create fixture document folder.');
    $files = [];
    foreach (['ISO-01', 'ISO-02'] as $reference) {
        $key = bin2hex(random_bytes(16)) . '-' . $reference . '.pdf';
        $path = $directory . '/' . $key; $paths[] = $path;
        file_put_contents($path, '%PDF-1.4 ' . $reference); $files[] = $key;
    }
    $documentIds = [];
    foreach (['qmos', 'dmos'] as $kind) {
        $content = '%PDF-1.4 ' . $kind;
        $db->prepare('INSERT INTO demande_' . $kind . '_documents (id_demande, nom, contenu, sha256) VALUES (?, ?, ?, ?)')->execute([$request, $kind . '.pdf', $content, hash('sha256', $content, true)]);
        $documentIds[$kind] = (int) $db->lastInsertId();
    }
    $base = ['csrf' => 'atelier-test-token', 'id' => $request];
    foreach (['w1', 'dem'] as $who) check(callEndpoint($save, $prefix . '.' . $who, $base + ['operation' => 'take'])['status'] === 403, 'Unauthorized users cannot manage workshop.');
    check(callEndpoint($save, $admin, array_replace($base, ['id' => $adminReq, 'operation' => 'take']))['status'] === 400, 'Admin must select a workshop chief.');
    check(callEndpoint($save, $admin, array_replace($base, ['id' => $adminReq, 'operation' => 'take', 'chef' => $ids['w1']]))['status'] === 400, 'Admin cannot choose workshop personnel as chief.');
    $adminBeforeTake = callEndpoint('dashboard/pages/lists_prefa.php', $admin, [], ['results' => '1']);
    check(str_contains($adminBeforeTake['body'], 'name="chef"') && str_contains($adminBeforeTake['body'], 'Affecter le chef d’atelier'), 'Admin must see the chief picker.');
    check(callEndpoint($save, $admin, array_replace($base, ['id' => $adminReq, 'operation' => 'take', 'chef' => $ids['chef2']]))['status'] === 200, 'Admin can assign a workshop chief.');
    $adminTaker = (int) $db->query('SELECT pris_en_charge_par FROM demande_prefabrication WHERE id = ' . $adminReq)->fetchColumn();
    $adminId = (int) $db->query('SELECT id FROM Utilisateur WHERE identifiant = ' . $db->quote($admin))->fetchColumn();
    check($adminTaker === $ids['chef2'], 'Selected chief must be recorded instead of the administrator.');
    check(callEndpoint($save, $admin, array_replace($base, ['id' => $adminReq, 'operation' => 'take', 'chef' => $ids['chef']]))['status'] === 409, 'Another take must not overwrite the assigned chief.');
    check(callEndpoint($save, $admin, array_replace($base, ['id' => $pending, 'operation' => 'take']))['status'] === 404, 'Admin cannot take pending request.');
    check(callEndpoint($save, $prefix . '.chef', array_replace($base, ['csrf' => 'bad', 'operation' => 'take']))['status'] === 403, 'CSRF required.');
    check(callEndpoint($save, $prefix . '.chef', array_replace($base, ['id' => $pending, 'operation' => 'take']))['status'] === 404, 'Cannot take a pending request.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'element', 'reference' => 'NO', 'libelle' => 'Not taken'])['status'] === 409, 'Take charge before creating elements.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'take'])['status'] === 200, 'Take charge independent of staff assignment.');
    $before = $db->query('SELECT pris_en_charge_par, date_prise_en_charge FROM demande_prefabrication WHERE id = ' . $request)->fetch();
    check((int) $before['pris_en_charge_par'] === $ids['chef'], 'Taker recorded.');
    foreach ([0, 1] as $index) {
        $docs = ['plan:' . $files[$index]];
        if ($index === 0) foreach ($documentIds as $kind => $docId) $docs[] = $kind . ':' . $docId;
        check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'element', 'reference' => 'ISO-0' . ($index + 1), 'libelle' => 'Tuyauterie <test>', 'documents' => $docs])['status'] === 200, 'Element creation failed.');
    }
    $elements = $db->query('SELECT id FROM element_atelier WHERE id_demande = ' . $request . ' ORDER BY reference')->fetchAll(PDO::FETCH_COLUMN);
    [$e1, $e2] = array_map('intval', $elements);
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'element', 'reference' => 'ISO-01', 'libelle' => 'Duplicate', 'documents' => ['plan:' . $files[0]]])['status'] === 409, 'Duplicate reference rejected.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'element', 'reference' => 'NO-FILE', 'libelle' => 'No file'])['status'] === 400, 'A new plan needs a file.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'documents', 'element' => $e2, 'documents' => []])['status'] === 400, 'Cannot remove every plan file.');
    check(callEndpoint($save, $prefix . '.w1', $base + ['operation' => 'documents', 'element' => $e2, 'documents' => ['plan:' . $files[1]]])['status'] === 403, 'Only chief may change document access.');
    $db->prepare('DELETE FROM element_atelier_document WHERE id_element = ?')->execute([$e2]);
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'assign', 'element' => $e2, 'type' => 'plan', 'utilisateur' => $ids['w2']])['status'] === 400, 'Legacy plan without a file cannot be assigned.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'documents', 'element' => $e2, 'documents' => ['plan:' . $files[1]]])['status'] === 200, 'Chief can repair a legacy plan missing its file.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'element', 'reference' => 'BAD', 'libelle' => 'Bad document', 'documents' => ['plan:' . bin2hex(random_bytes(16)) . '-missing.pdf']])['status'] === 400, 'Unrelated document rejected.');
    check(callEndpoint($save, $prefix . '.chef', array_replace($base, ['id' => $other, 'operation' => 'take']))['status'] === 200, 'Other take failed.');
    check(callEndpoint($save, $prefix . '.chef', array_replace($base, ['id' => $other, 'operation' => 'assign', 'element' => $e1, 'type' => 'plan', 'utilisateur' => $ids['w1']]))['status'] === 400, 'Cross-request element rejected.');
    check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'assign', 'element' => $e1, 'type' => 'plan', 'utilisateur' => $ids['dem']])['status'] === 400, 'Requester cannot be assigned as worker.');
    foreach ([[$e1, 'plan', 'w1'], [$e2, 'montage', 'w2'], [$e1, 'soudage', 'w1']] as [$element, $type, $who]) {
        check(callEndpoint($save, $prefix . '.chef', $base + ['operation' => 'assign', 'element' => $element, 'type' => $type, 'utilisateur' => $ids[$who]])['status'] === 200, 'Assign failed.');
    }
    $assignments = $db->query('SELECT type_affectation, id FROM affectation_atelier WHERE id_element = ' . $e1 . ' AND actif = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
    $plan = (int) $assignments['plan']; $weld = (int) $assignments['soudage'];
    $page = callEndpoint('dashboard/pages/lists_prefa.php', $prefix . '.w1');
    check($page['status'] === 200 && str_contains($page['body'], 'ISO-01') && !str_contains($page['body'], 'ISO-02'), 'Worker must see only assigned elements.');
    check(str_contains($page['body'], 'Tuyauterie &lt;test&gt;') && str_contains($page['body'], 'Compte rendu du suivi soudage'), 'Escape labels and render weld report.');
    check(!str_contains($page['body'], 'Créer l’élément') && !str_contains($page['body'], 'prefa-review'), 'Worker must not manage requests.');
    if (getenv('ATELIER_PREVIEW')) {
        $css = '';
        foreach (['dashboard-base', 'dashboard-forms', 'prefa-form', 'prefa-list', 'atelier'] as $sheet) $css .= file_get_contents('css/dashboard/' . $sheet . '.css');
        $css .= file_get_contents('css/floating-fields.css');
        file_put_contents('/tmp/atelier-preview.html', '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Affectations atelier</title><style>' . $css . 'body{height:auto;overflow:auto}</style><body>' . $page['body'] . '<script>' . file_get_contents('js/floating-fields.js') . '</script></body></html>');
    }
    foreach (['w1' => 200, 'w2' => 404] as $who => $status) {
        check(callEndpoint('dashboard/actions/download_prefa_attachment.php', $prefix . '.' . $who, [], ['id' => $request, 'file' => $files[0]])['status'] === $status, 'Per-plan access failed.');
        foreach ($documentIds as $kind => $docId) check(callEndpoint('dashboard/actions/download_' . $kind . '.php', $prefix . '.' . $who, [], ['id' => $request, 'file' => $docId])['status'] === $status, 'Related QMOS/DMOS access failed.');
    }
    check(callEndpoint('dashboard/actions/download_prefa_attachment.php', $prefix . '.w1', [], ['id' => $request, 'file' => $files[1]])['status'] === 404, 'No access to the other plan of the same request.');
    foreach (['dashboard/pages/create_prefa.php', 'dashboard/actions/create_prefa.php', 'dashboard/actions/delete_prefa.php', 'dashboard/actions/save_planning.php', 'dashboard/actions/review_prefa.php'] as $endpoint) {
        check(callEndpoint($endpoint, $prefix . '.w1', $base)['status'] === 403, 'Worker cannot create, edit, delete, plan or validate.');
    }
    check(callEndpoint('dashboard/pages/create_prefa.php', $prefix . '.chef')['status'] === 200, 'Chief keeps creation form.');
    $steps = ['csrf' => 'atelier-test-token', 'affectation' => $plan, 'operation' => 'steps', 'etapes_revision' => 0,
        'etapes' => ['decoupage' => 'OK', 'pliage' => 'Non OK', 'pointage' => '', 'soudage' => 'Non OK', 'passivation' => 'RAS', 'autre' => 'RAS']];
    check(callEndpoint($update, $prefix . '.w2', $steps)['status'] === 404, 'Other workers cannot edit element steps.');
    check(callEndpoint($update, $prefix . '.chef', $steps)['status'] === 403, 'Only assigned personnel may edit steps.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($steps, ['etapes' => array_replace($steps['etapes'], ['soudage' => 'Invalid'])]))['status'] === 400, 'Invalid step status must be rejected.');
    check(callEndpoint($update, $prefix . '.w1', $steps)['status'] === 200, 'Step save failed.');
    check(callEndpoint($update, $prefix . '.w1', $steps)['status'] === 409, 'Stale element steps must not overwrite another save.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($steps, ['affectation' => $weld]))['status'] === 409, 'All tasks on one element must share the step revision.');
    $stored = json_decode($db->query('SELECT statuts FROM element_atelier_etapes WHERE id_element = ' . $e1)->fetchColumn(), true);
    check($stored == $steps['etapes'], 'Step statuses must be persisted exactly.');
    check((int) $db->query('SELECT COUNT(*) FROM element_atelier_etapes_historique WHERE id_element = ' . $e1)->fetchColumn() === 1, 'Only successful step saves should enter history.');
    $staffSteps = callEndpoint('dashboard/pages/atelier.php', $prefix . '.w1');
    check(str_contains($staffSteps['body'], 'name="etapes[decoupage]"') && str_contains($staffSteps['body'], 'value="Non OK" selected'), 'Staff form must show saved step statuses.');
    $planningSteps = callEndpoint('dashboard/pages/planning.php', $admin);
    check(preg_match('/<script id="planning-data"[^>]*>(.*?)<\/script>/s', $planningSteps['body'], $dataMatch) === 1, 'Planning data missing.');
    $planningData = json_decode($dataMatch[1], true);
    $planningRequest = array_values(array_filter($planningData, fn($r) => (int) $r['id'] === $request))[0];
    check($planningRequest['elements'][0]['statuts'] == $steps['etapes'] && $planningRequest['elements'][1]['statuts'] === [], 'Planning must use actual statuses and leave unsaved elements empty.');
    $progress = ['csrf' => 'atelier-test-token', 'affectation' => $plan, 'operation' => 'progress', 'avancement' => 60, 'revision' => 0, 'commentaire' => 'Montage en cours'];
    check(callEndpoint($update, $prefix . '.w2', $progress)['status'] === 404, 'Cannot update another worker’s task.');
    check(callEndpoint($update, $prefix . '.chef', $progress)['status'] === 403, 'Only assignee updates progress.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($progress, ['avancement' => 101]))['status'] === 400, 'Invalid progress rejected.');
    check(callEndpoint($update, $prefix . '.w1', $progress)['status'] === 200, 'Progress update failed.');
    check(callEndpoint($update, $prefix . '.w1', $progress)['status'] === 409, 'Stale revision rejected.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($progress, ['affectation' => $weld, 'avancement' => 100, 'commentaire' => 'Soudage terminé']))['status'] === 200, 'Separate weld progress failed.');
    $time = ['csrf' => 'atelier-test-token', 'affectation' => $plan, 'operation' => 'time', 'date' => date('Y-m-d'), 'minutes' => 90, 'cle_saisie' => bin2hex(random_bytes(16))];
    check(callEndpoint($update, $prefix . '.w1', $time)['status'] === 200, 'Pointage failed.');
    check(callEndpoint($update, $prefix . '.w1', $time)['status'] === 200, 'Retry should be idempotent.');
    check((int) $db->query('SELECT SUM(duree_minutes) FROM pointage_atelier WHERE id_affectation = ' . $plan)->fetchColumn() === 90, 'Retry must not duplicate time.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($time, ['cle_saisie' => bin2hex(random_bytes(16)), 'affectation' => $weld, 'minutes' => 1400]))['status'] === 400, 'Daily total across tasks must not exceed 24h.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($time, ['date' => '2026-02-30']))['status'] === 400, 'Invalid pointage date rejected.');
    $historyPage = callEndpoint('dashboard/pages/atelier.php', $prefix . '.w1');
    check(str_contains($historyPage['body'], 'Historique de l’avancement (1)') && str_contains($historyPage['body'], 'Historique des pointages (1)') && str_contains($historyPage['body'], 'Historique des étapes (1)'), 'Assignment detail must show all three histories.');
    check(str_contains($historyPage['body'], 'Montage en cours') && str_contains($historyPage['body'], '90 min'), 'Saved progress and time entries must appear in history.');
    check(!str_contains($historyPage['body'], 'Historique des anciennes affectations') && !str_contains($historyPage['body'], 'Mes derniers avancements'), 'Active assignments must not have duplicate global histories.');
    check(callEndpoint($save, $prefix . '.chef2', $base + ['operation' => 'assign', 'element' => $e1, 'type' => 'plan', 'utilisateur' => $ids['w2']])['status'] === 200, 'Reassign failed.');
    check(callEndpoint($save, $prefix . '.chef2', $base + ['operation' => 'revoke', 'element' => $e1, 'type' => 'soudage'])['status'] === 200, 'Revoke failed.');
    check(callEndpoint($save, $admin, $base + ['operation' => 'assign', 'element' => $e1, 'type' => 'soudage', 'utilisateur' => $ids['w1']])['status'] === 200, 'Admin can assign worker.');
    check(callEndpoint($save, $admin, $base + ['operation' => 'revoke', 'element' => $e1, 'type' => 'soudage'])['status'] === 200, 'Admin can revoke assignment.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($progress, ['revision' => 1]))['status'] === 404, 'Reassignment must block old writer.');
    check(callEndpoint($update, $prefix . '.w1', array_replace($steps, ['etapes_revision' => 1]))['status'] === 404, 'Reassignment must revoke step editing rights.');
    check(callEndpoint('dashboard/actions/download_prefa_attachment.php', $prefix . '.w1', [], ['id' => $request, 'file' => $files[0]])['status'] === 404, 'Reassignment must revoke all old document access.');
    check((int) $db->query('SELECT avancement FROM affectation_atelier WHERE id = ' . $plan)->fetchColumn() === 60, 'Historical progress preserved.');
    check((int) $db->query('SELECT SUM(duree_minutes) FROM pointage_atelier WHERE id_affectation = ' . $plan)->fetchColumn() === 90, 'Historical time preserved.');
    $after = $db->query('SELECT pris_en_charge_par, date_prise_en_charge FROM demande_prefabrication WHERE id = ' . $request)->fetch();
    check($before === $after, 'Reassignment preserves first taker and date.');
    $page = callEndpoint('dashboard/pages/lists_prefa.php', $prefix . '.chef', [], ['results' => '1']);
    check($page['status'] === 200 && str_contains($page['body'], 'ancienne affectation') && str_contains($page['body'], 'Ajouter un plan'), 'Chief must see elements and history.');
    $adminPage = callEndpoint('dashboard/pages/lists_prefa.php', $admin, [], ['results' => '1']);
    check($adminPage['status'] === 200 && str_contains($adminPage['body'], 'Ajouter un plan') && str_contains($adminPage['body'], '+ Affecter'), 'Admin must see workshop management and assignment controls.');
    if (getenv('ATELIER_PREVIEW')) {
        check((bool) preg_match('/<section class="prefa-workshop-planning atelier-section" aria-labelledby="atelier-title-' . $request . '".*?<\/section>/s', $page['body'], $section), 'Chief section preview missing.');
        file_put_contents('/tmp/atelier-chief-preview.html', '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Plans et tâches</title><style>' . $css . 'body{height:auto;overflow:auto;padding:20px}.atelier-section{max-width:1100px;margin:0 auto}</style><body>' . $section[0] . '<script>' . file_get_contents('js/floating-fields.js') . '</script></body></html>');
    }
    $page = callEndpoint('dashboard/pages/atelier.php', $prefix . '.w1');
    check($page['status'] === 200 && str_contains($page['body'], '90 min') && str_contains($page['body'], 'Aucun élément'), 'Worker retains own time history after revocation.');
    check(str_contains($page['body'], 'Historique des anciennes affectations') && str_contains($page['body'], 'Montage en cours'), 'Worker retains saved progress history after revocation.');
    $db->prepare('UPDATE demande_prefabrication SET id_personnel_atelier = ? WHERE id = ?')->execute([$ids['w1'], $request]);
    check(callEndpoint('dashboard/actions/download_prefa_attachment.php', $prefix . '.w1', [], ['id' => $request, 'file' => $files[1]])['status'] === 404, 'Legacy global assignment must not grant all-plan access.');
    $db->prepare('UPDATE demande_prefabrication SET id_statut = 1 WHERE id = ?')->execute([$request]);
    check(callEndpoint('dashboard/actions/download_prefa_attachment.php', $prefix . '.w2', [], ['id' => $request, 'file' => $files[0]])['status'] === 404, 'Unvalidated requests must block documents.');
    echo "ALL ELEMENT ASSIGNMENT, DOCUMENT ACCESS, PROGRESS AND POINTAGE TESTS PASSED\n";
} finally {
    foreach ($paths as $path) if (is_file($path)) unlink($path);
    if (isset($directory) && is_dir($directory)) rmdir($directory);
    if ($requests) $db->exec('DELETE FROM demande_prefabrication WHERE id IN (' . implode(',', $requests) . ')');
    if ($ids) $db->exec('DELETE FROM Utilisateur WHERE id IN (' . implode(',', $ids) . ')');
}
