<?php
// Execute via CLI in the PHP container; safe to rerun without duplicates.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
chdir('/var/www/html');
require_once '/var/www/html/db.php';
$db = new MyPDO();
$role = $db->query("SELECT id FROM role WHERE name = 'Utilisateur' LIMIT 1")->fetchColumn();
if ($role === false) {
    throw new RuntimeException('Role Utilisateur introuvable.');
}
$names = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau'];
$firstNames = ['Emma', 'Louis', 'Jade', 'Gabriel', 'Louise'];
$exists = $db->prepare('SELECT COUNT(*) FROM Utilisateur WHERE identifiant = ? OR email = ?');
$insert = $db->prepare('INSERT INTO Utilisateur (name, prenom, identifiant, email, id_role, password) VALUES (?, ?, ?, ?, ?, ?)');
$created = 0;
$db->beginTransaction();
try {
    for ($i = 1; $i <= 50; $i++) {
        $identifier = sprintf('test.pagination.%03d', $i);
        $email = $identifier . '@example.com';
        $exists->execute([$identifier, $email]);
        if ((int) $exists->fetchColumn() > 0) {
            continue;
        }
        $insert->execute([
            $names[($i - 1) % 10],
            $firstNames[intdiv($i - 1, 10)],
            $identifier,
            $email,
            (int) $role,
            password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT),
        ]);
        $created++;
    }
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
echo 'Utilisateurs de test ajoutes : ' . $created . PHP_EOL;
echo 'Total utilisateurs : ' . $db->query('SELECT COUNT(*) FROM Utilisateur')->fetchColumn() . PHP_EOL;
