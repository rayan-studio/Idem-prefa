<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../api/User.php';

$db = new MyPDO();
$id = $_GET['id'];

$stmt = $db->prepare("DELETE FROM Utilisateur WHERE id = :id");
$stmt->execute(['id' => $id]);
header('HX-Trigger-After-Swap: usersChanged');
?>
