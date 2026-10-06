<?php
chdir('/var/www/html');
require_once 'db.php';
require_once 'dashboard/includes/prefa_attachments.php';
$db = new MyPDO();
$identity = 'upload.check.' . bin2hex(random_bytes(8));
$userId = null;
$sessionId = 'uploadcheck' . bin2hex(random_bytes(12));
try {
    $db->prepare('INSERT INTO Utilisateur (name, prenom, identifiant, id_role, password) VALUES (?, ?, ?, 5, ?)')
        ->execute(['UploadCheck', 'Fixture', $identity, password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT)]);
    $userId = (int) $db->lastInsertId();
    session_id($sessionId);
    session_start();
    $_SESSION = ['identifiant' => $identity, 'prefa_csrf' => 'upload-check-token'];
    session_write_close();
    $boundary = 'UploadCheck' . bin2hex(random_bytes(12));
    $body = '';
    foreach (['csrf' => 'upload-check-token', 'date_fin_prevue' => '2026-10-16', 'date_livraison_prevue' => '2026-10-24', 'pouces_total_iso' => '50'] as $key => $value) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
    }
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"plan_documents[]\"; filename=\"upload-check.pdf\"\r\nContent-Type: application/pdf\r\n\r\n$pdf\r\n--$boundary--\r\n";
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: multipart/form-data; boundary=$boundary\r\nCookie: PHPSESSID=$sessionId\r\n", 'content' => $body, 'ignore_errors' => true, 'timeout' => 20]]);
    $response = file_get_contents('http://localhost/dashboard/actions/create_prefa.php', false, $context);
    $result = json_decode($response, true);
    if (!($result['success'] ?? false)) throw new RuntimeException('Real HTTP upload failed: ' . $response);
    $stmt = $db->prepare('SELECT id FROM demande_prefabrication WHERE idUsers = ?');
    $stmt->execute([$userId]);
    $requestId = (int) $stmt->fetchColumn();
    $attachments = prefaAttachments($requestId);
    if (count($attachments) !== 1 || $attachments[0]['name'] !== 'upload-check.pdf') throw new RuntimeException('Uploaded attachment missing.');
} finally {
    if ($userId) {
        $stmt = $db->prepare('SELECT id FROM demande_prefabrication WHERE idUsers = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $requestId) {
            foreach (prefaAttachments((int) $requestId) as $file) unlink(prefaAttachmentDirectory((int) $requestId) . '/' . $file['key']);
            $directory = prefaAttachmentDirectory((int) $requestId);
            if (is_dir($directory)) rmdir($directory);
        }
        $db->prepare('DELETE FROM demande_prefabrication WHERE idUsers = ?')->execute([$userId]);
        $db->prepare('DELETE FROM Utilisateur WHERE id = ?')->execute([$userId]);
    }
    session_id($sessionId);
    session_start();
    session_destroy();
}
echo "OK: request creation over HTTP with a PDF attachment.\n";
