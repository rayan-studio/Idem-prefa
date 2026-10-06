<?php
require_once __DIR__ . '/../../db.php';
session_start();
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Paris');

function prefaError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['identifiant'])) prefaError(401, 'Veuillez vous reconnecter.');

$db = new MyPDO(__DIR__ . '/../../my_setting.ini');
$stmt = $db->prepare('SELECT u.id, u.id_role, r.name AS role_name FROM Utilisateur u JOIN role r ON r.id = u.id_role WHERE u.identifiant = ?');
$stmt->execute([$_SESSION['identifiant']]);
$actor = $stmt->fetch();

if (!$actor) prefaError(401, 'Veuillez vous reconnecter.');

$isAdmin = (int) $actor['id_role'] === 1;
$isWorkshopChief = (int) $actor['id_role'] === 2;
$canViewAllPrefa = $isAdmin || $isWorkshopChief;
$isWorkshopPersonnel = in_array($actor['role_name'], ['Utilisateur', 'Personnel atelier'], true);
$isRequester = in_array((int) $actor['id_role'], [2, 4, 5], true);
$canEditPrefa = $isAdmin || $isRequester;
$canManageWorkshop = $isAdmin || $isWorkshopChief;
$_SESSION['prefa_csrf'] ??= bin2hex(random_bytes(32));

function prefaPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') prefaError(405, 'Méthode non autorisée.');
    if (!hash_equals($_SESSION['prefa_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        prefaError(403, 'Rechargez la page puis réessayez.');
    }
}

function prefaEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function prefaFormatDate($value, bool $withTime = false): string
{
    if (!$value) return 'Non renseignée';
    $date = new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone('Europe/Paris'))
        ->format($withTime ? 'd/m/Y à H:i' : 'd/m/Y');
}
