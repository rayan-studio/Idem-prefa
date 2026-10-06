<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['identifiant'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Veuillez vous reconnecter.']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../api/User.php';

$name = trim($_POST['name'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');

if ($name === '' || $prenom === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Saisissez le nom et le prénom.']);
    exit;
}

try {
    $user = new User(new MyPDO(__DIR__ . '/../../my_setting.ini'));
    $identifiant = User::buildIdentifiant($name, $prenom);
    $available = !$user->identifiantExists($identifiant);
    echo json_encode([
        'available' => $available,
        'message' => $available
            ? $identifiant . ' — disponible'
            : $identifiant . ' — déjà utilisé'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Vérification impossible. Modifiez le nom ou le prénom pour réessayer.']);
}
