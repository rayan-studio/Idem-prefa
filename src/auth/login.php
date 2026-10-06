<?php

session_start();

require_once __DIR__ . './../db.php';

if (isset($_SESSION['identifiant'])) {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <!-- Métadonnées -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Login</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.20.0/axios.min.js" integrity="sha512-OVq+Gfa9PIjbTC0jWOccSFSp/vzkBQ5pMcbHQrNZLl+izmdFmusmAMlX4LT550ivvSnMXMEeG7CxEtjIz6Dn2Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
    <link href="../css/index.css?v=<?= filemtime(__DIR__ . '/../css/index.css') ?>" rel="stylesheet" />
    <link href="../css/floating-fields.css?v=<?= filemtime(__DIR__ . '/../css/floating-fields.css') ?>" rel="stylesheet" />
    
    <script src="../js/floating-fields.js?v=<?= filemtime(__DIR__ . '/../js/floating-fields.js') ?>" defer></script>
    <script src="../js/login.js?v=<?= filemtime(__DIR__ . '/../js/login.js') ?>" defer></script>
</head>

<body>
    <div class="contenaire">
        <form id="login-form" method="post">
            <h1>Connexion</h1>

            <label for="login">identifiant :</label>
            <input
                name="login"
                id="login"
                type="text"
                required
                autocomplete="username">

            <label for="password">Votre mot de passe :</label>
            <input
                name="password"
                id="password"
                type="password"
                required
                autocomplete="current-password">

            <p id="form-message" ></p>
            <div class="box-submit">
                <button type="submit">Se connecter</button>
            </div>
        </form>
    </div>
</body>

</html>