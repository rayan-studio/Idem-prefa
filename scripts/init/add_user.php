<?php
$dsn = "mysql:host=db;dbname=myapp;charset=utf8";
$user = "myuser"; // ou "root" si tu veux les droits complets
$pass = "mypassword"; // ou "rootpassword" si root

$name = "Alan";
$prenom = "Admin";
$email = "admin@example.com";
$motDePasse = "QOSIDJqsjdoipQJSDOPqjsopdjQOSJDPOQSjd89Q45S6Dq1s23d46748937249";
$initial_name = mb_strtoupper(mb_substr(trim($name), 0, 1));
$identifiant = $initial_name . "." . $prenom; // premiére lettre du nom + prenom avec .
$hash = password_hash($motDePasse, PASSWORD_BCRYPT);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Utilisateur WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO Utilisateur (name, id_role, password, email, prenom, identifiant) VALUES (?,?, ?, ?, ?, ?)");
        $stmt->execute([$name, 1, $hash, $email, $prenom, $identifiant]);
        echo "Utilisateur ajouté avec mot de passe hashé.\n";
    } else {
        echo "Utilisateur déjà existant.\n";
    }
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
