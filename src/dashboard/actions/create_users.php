<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../api/User.php';

$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$role = $_POST['role'] ?? '';
$prenom = trim($_POST['prenom'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $prenom === '' || $role === '' || $password === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs obligatoires.'
    ]);

    exit;
}

$db = new MyPDO(__DIR__ . '/../../my_setting.ini');
$user = new User($db);

try {
    $user->register($name, $prenom, $role, $password, $email);

    echo json_encode([
        'success' => true,
        'message' => 'Utilisateur créé avec succès.'
    ]);

} catch (DomainException $e) {
    http_response_code(409);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => "Impossible de créer l'utilisateur."
    ]);
}
