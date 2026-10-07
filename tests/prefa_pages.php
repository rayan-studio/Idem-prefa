<?php
// Read-only rendering checks in the local PHP container.
chdir('/var/www/html');
require_once 'db.php';
$connection = new MyPDO();
$page = $argv[1] ?? 'plans_iso';
$admin = in_array($page, ['atelier', 'pointage'], true)
    ? $connection->query("SELECT u.identifiant FROM Utilisateur u JOIN role r ON r.id = u.id_role WHERE r.name IN ('Utilisateur', 'Personnel atelier') LIMIT 1")->fetchColumn()
    : $connection->query('SELECT identifiant FROM Utilisateur WHERE id_role = 1 LIMIT 1')->fetchColumn();
if (($argv[2] ?? '') === 'chief') {
    $admin = $connection->query('SELECT identifiant FROM Utilisateur WHERE id_role = 2 LIMIT 1')->fetchColumn();
}
if (!$admin) throw new RuntimeException('No administrator available for the rendering check');
session_start();
$_SESSION['identifiant'] = $admin;
session_write_close();
if (!in_array($page, ['plans_iso', 'lists_prefa', 'planning', 'create_prefa', 'atelier', 'pointage'], true)) exit(1);
$_SERVER['REQUEST_METHOD'] = 'GET';
if ($page === 'create_prefa') {
    $_GET['id'] = $connection->query('SELECT id FROM demande_prefabrication ORDER BY id LIMIT 1')->fetchColumn();
}
ob_start();
require 'dashboard/pages/' . $page . '.php';
$html = ob_get_clean();
if (preg_match('/(?:Warning|Fatal error|Parse error):/', $html)) throw new RuntimeException('Rendering error');
if (!in_array($page, ['atelier', 'pointage'], true) && !preg_match('/\d{2}-DP-\d{3,}/', $html)) throw new RuntimeException('Request reference missing');
if ($page === 'plans_iso' && str_contains($html, 'Demande #')) throw new RuntimeException('Old fallback still visible');
if ($page === 'plans_iso' && (!str_contains($html, 'class="prefa-detail-row"') || !str_contains($html, 'aria-controls="prefa-detail-'))) throw new RuntimeException('Request details unavailable');
if ($page === 'plans_iso') {
    $document = new DOMDocument();
    @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($document);
    $plans = $xpath->query('//article[@class="atelier-plan-block"]');
    foreach ($plans as $plan) {
        if ($xpath->query('.//article', $plan)->length !== 0) throw new RuntimeException('Nested plan blocks');
        if ($xpath->query('./div[@class="atelier-plan-work-grid"]/section', $plan)->length !== 3) throw new RuntimeException('Plan assignment sections missing');
        if ($xpath->query('./div[@class="atelier-editor-row"]/div[contains(@class,"atelier-manage-panel")]', $plan)->length !== 1) throw new RuntimeException('Assignment editor incorrectly nested');
    }
    if (str_contains($html, 'class="atelier-plans-table"')) throw new RuntimeException('Old plan table still present');
}
if ($page === 'create_prefa' && !str_contains($html, 'name="nom_affaire"')) throw new RuntimeException('Request name input missing');
echo $page . ": OK\n";
