<?php
// Run in the PHP container with PLANNING_APP_ROOT=/var/www/html.
chdir(getenv('PLANNING_APP_ROOT') ?: __DIR__ . '/../src');
require_once 'db.php';
require_once 'dashboard/includes/planning.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function callPlanning(string $path, string $identity, array $post = [], array $get = []): array
{
    $script = 'chdir("/var/www/html"); session_id(' . var_export('planningcheck' . bin2hex(random_bytes(12)), true) . '); session_start();'
        . '$_SESSION = ' . var_export(['identifiant' => $identity, 'prefa_csrf' => 'planning-check-token'], true) . '; session_write_close();'
        . '$_SERVER["REQUEST_METHOD"] = ' . var_export($post ? 'POST' : 'GET', true) . ';'
        . '$_POST = ' . var_export($post, true) . '; $_GET = ' . var_export($get, true) . ';'
        . 'register_shutdown_function(function () { echo "\nHTTP_CODE:", http_response_code() ?: 200; if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); });'
        . 'require ' . var_export($path, true) . ';';
    $process = proc_open(['php', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start endpoint check.');
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    check($exit === 0 && $errors === '', 'Endpoint PHP error: ' . $errors);
    check((bool) preg_match('/\nHTTP_CODE:(\d+)$/', $body, $matches), 'Missing response status.');
    return ['status' => (int) $matches[1], 'body' => substr($body, 0, -strlen($matches[0]))];
}

check(planningDate('2026-02-30') === null, 'Invalid dates must be rejected.');
$friday = planningDate('2026-10-09');
$period = planningPeriod($friday, 48, true);
check($period['start'] === '2026-10-09' && $period['end'] === '2026-10-12', 'Two working days from Friday must end on Monday.');
check(planningPeriod(planningDate('2026-10-10'), 1, true)['start'] === '2026-10-12', 'Weekend start must move to Monday.');
check(planningPeriod($friday, 24.1, true)['end'] === '2026-10-12', 'Fractional days must round up.');
$request = ['id' => 1, 'date_debut_planifiee' => '2026-10-09', 'date_fin_planifiee' => '2026-10-12', 'planning_jours_ouvres' => 1];
check(planningOccupiedDays($request, $friday, planningDate('2026-10-12')) === ['2026-10-09', '2026-10-12'], 'Weekends must not count as occupied.');
$other = array_replace($request, ['id' => 2, 'date_debut_planifiee' => '2026-10-12', 'date_fin_planifiee' => '2026-10-13']);
$layout = planningLanes([$request, $other], $friday, 7);
check($layout['conflicts'] === 1 && $layout['occupied'] === 3 && $layout['lanes'] === 2, 'Overlapping jobs must remain visible and count occupied days once.');
check($layout['blocks'][0]['conflict'] && $layout['blocks'][1]['conflict'], 'Both conflicting jobs must be flagged.');
$clipped = planningLanes([$request], planningDate('2026-10-12'), 1);
check($clipped['blocks'][0]['column'] === 1 && $clipped['blocks'][0]['span'] === 1, 'Jobs crossing the visible range must be clipped.');
$weekdayLayout = planningLanes([$request], $friday, 7, true);
check($weekdayLayout['blocks'][0]['column'] === 1 && $weekdayLayout['blocks'][0]['span'] === 2, 'Timetable must omit weekend columns without stretching the duration.');
try { planningPeriod($friday, 0, true); throw new RuntimeException('Zero duration accepted.'); } catch (InvalidArgumentException) {}

$db = new MyPDO();
$admin = $db->query('SELECT identifiant FROM Utilisateur WHERE id_role = 1 ORDER BY id LIMIT 1')->fetchColumn();
check((bool) $admin, 'An administrator account is required for endpoint checks.');
$prefix = 'planning.check.' . bin2hex(random_bytes(8));
$userIds = [];
$jobIds = [];
try {
    $insertUser = $db->prepare('INSERT INTO Utilisateur (name, prenom, identifiant, id_role, password) VALUES (?, ?, ?, ?, ?)');
    foreach ([3, 5] as $role) {
        $insertUser->execute(['Planning <check>', 'Fixture', $prefix . '.' . $role, $role, password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT)]);
        $userIds[$role] = (int) $db->lastInsertId();
    }
    $insertJob = $db->prepare('INSERT INTO demande_prefabrication (idUsers, DateUpdate, date_creation, date_fin_prevue, date_livraison_prevue, pouces_total_iso, heures_chiffrees, CDN, ` QMOS`, urgent, id_statut) VALUES (?, NOW(), NOW(), ?, ?, 1, 48, 0, ?, 0, ?)');
    foreach ([2, 2, 1] as $status) {
        $insertJob->execute([$userIds[3], '2026-10-15', '2026-10-16', '', $status]);
        $jobIds[] = (int) $db->lastInsertId();
    }
    $payload = ['csrf' => 'planning-check-token', 'id' => $jobIds[0], 'revision' => 0, 'start' => '2026-10-09', 'end' => '2026-10-14', 'operation' => 'save'];
    $endpoint = 'dashboard/actions/save_planning.php';
    check(callPlanning($endpoint, $prefix . '.3', $payload)['status'] === 403, 'Non-admin must not plan requests.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['csrf' => 'bad']))['status'] === 403, 'Invalid CSRF must be rejected.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['id' => $jobIds[2]]))['status'] === 409, 'Pending requests must not occupy the planning.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['start' => '2026-02-30']))['status'] === 400, 'Invalid start dates must be rejected.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['end' => '']))['status'] === 400, 'Missing end date must be rejected.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['end' => '2026-10-08']))['status'] === 400, 'End before start must be rejected.');
    $saved = callPlanning($endpoint, $admin, $payload);
    check($saved['status'] === 200 && json_decode($saved['body'], true)['success'], 'Plan save failed.');
    $savedFocus = json_decode($saved['body'], true)['planning'];
    check($savedFocus['start'] === '2026-10-09' && $savedFocus['creator'] === $userIds[3], 'Saved response must identify the period and original requester.');
    $row = $db->query('SELECT * FROM demande_prefabrication WHERE id = ' . $jobIds[0])->fetch();
    check($row['date_fin_planifiee'] === '2026-10-14' && $row['id_charger_affaire'] === null && (int) $row['idUsers'] === $userIds[3], 'Planning must save dates without assigning a charge d’affaires or changing the creator.');
    check(callPlanning($endpoint, $admin, $payload)['status'] === 409, 'Stale planning revision must be rejected.');
    check(callPlanning($endpoint, $admin, array_replace($payload, ['id' => $jobIds[1], 'start' => '2026-10-12']))['status'] === 200, 'Second overlapping request failed.');
    $page = callPlanning('dashboard/pages/planning.php', $admin, [], ['start' => '2026-10-09', 'period' => '7', 'creator' => $userIds[3]]);
    check($page['status'] === 200 && !str_contains($page['body'], 'has-overlap') && str_contains($page['body'], 'Planning &lt;check&gt;'), 'Planning must render overlap and escape account names.');
    check(!str_contains($page['body'], 'data-plan-id="' . $jobIds[2] . '"'), 'Pending request leaked into planning.');
    check(!str_contains($page['body'], 'planning-assignee'), 'Planning must not offer charge d’affaires assignment.');
    $numberPage = callPlanning('dashboard/pages/planning.php', $admin, [], ['start' => '2026-10-09', 'period' => '7', 'request' => '#00' . $jobIds[0]]);
    check($numberPage['status'] === 200 && str_contains($numberPage['body'], 'data-plan-id="' . $jobIds[0] . '"'), 'Request number search must accept a hash and leading zeros.');
    check(!str_contains($numberPage['body'], 'data-plan-id="' . $jobIds[1] . '"'), 'Request number search must exclude other requests.');
    $combinedPage = callPlanning('dashboard/pages/planning.php', $admin, [], ['start' => '2026-10-09', 'request' => (string) $jobIds[0], 'creator' => $userIds[3]]);
    check(str_contains($combinedPage['body'], 'data-plan-id="' . $jobIds[0] . '"') && !str_contains($combinedPage['body'], 'data-plan-id="' . $jobIds[1] . '"'), 'Request number and creator filters must apply together.');
    $outsidePage = callPlanning('dashboard/pages/planning.php', $admin, [], ['start' => '2026-11-02', 'period' => '7', 'creator' => $userIds[3]]);
    check(str_contains($outsidePage['body'], 'data-planning-go-to="2026-10-12"'), 'Empty period must provide a shortcut to the closest planned request.');
    check(callPlanning('dashboard/pages/planning.php', $prefix . '.3')['status'] === 403, 'Planning page must require administrator access.');
    $removed = callPlanning($endpoint, $admin, array_replace($payload, ['revision' => 1, 'operation' => 'remove']));
    check($removed['status'] === 200, 'Removing a planned period failed.');
    $row = $db->query('SELECT date_debut_planifiee, id_charger_affaire FROM demande_prefabrication WHERE id = ' . $jobIds[0])->fetch();
    check($row['date_debut_planifiee'] === null && $row['id_charger_affaire'] === null, 'Removal must clear dates without assigning an account.');
    $review = callPlanning('dashboard/actions/review_prefa.php', $admin, ['csrf' => 'planning-check-token', 'id' => $jobIds[2], 'statut' => '2']);
    check($review['status'] === 200, 'Admin must validate without choosing a charge d’affaires.');
    $reviewed = $db->query('SELECT id_statut, idUsers, id_charger_affaire, date_debut_planifiee, date_fin_planifiee FROM demande_prefabrication WHERE id = ' . $jobIds[2])->fetch();
    check((int) $reviewed['id_statut'] === 2 && (int) $reviewed['idUsers'] === $userIds[3] && $reviewed['id_charger_affaire'] === null, 'Validation must preserve creator without assigning an account.');
    $expectedValidationPeriod = planningPeriod(new DateTimeImmutable('today', new DateTimeZone('Europe/Paris')), 48, true);
    check($reviewed['date_debut_planifiee'] === $expectedValidationPeriod['start'], 'Validation must initialize planning start to reception/validation date.');
    check($reviewed['date_fin_planifiee'] === $expectedValidationPeriod['end'], 'Validation must initialize planning end to start plus chiffrage duration.');
    $list = callPlanning('dashboard/pages/lists_prefa.php', $admin, [], ['results' => '1']);
    check($list['status'] === 200 && !str_contains($list['body'], 'reassign-charger-') && !str_contains($list['body'], 'review-charger-'), 'Admin details must not offer charge d’affaires assignment.');
    $edit = callPlanning('dashboard/pages/create_prefa.php', $admin, [], ['id' => $jobIds[0]]);
    check($edit['status'] === 200 && !str_contains($edit['body'], 'edit-id-charger-affaire'), 'Admin must keep editing access without charge d’affaires selection.');
    echo 'OK: working days, clipping, overlap, permissions, CSRF, revision, save, remove, rendering, validation auto-planning without assignment.', PHP_EOL;
} finally {
    if ($jobIds) $db->exec('DELETE FROM demande_prefabrication WHERE id IN (' . implode(',', $jobIds) . ')');
    if ($userIds) $db->exec('DELETE FROM Utilisateur WHERE id IN (' . implode(',', $userIds) . ')');
}
