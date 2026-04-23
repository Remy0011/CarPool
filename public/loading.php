<?php
session_start();
require_once __DIR__ . '/config.php';
applySecurityHeaders();

if (!isset($_SESSION['user_name'], $_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

if (!($_SESSION['show_loading_screen'] ?? false)) {
    header('Location: index.php?page=home');
    exit;
}

unset($_SESSION['show_loading_screen']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chargement</title>
    <meta http-equiv="refresh" content="2.2;url=index.php?page=home">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="carpool.css">
</head>
<body class="loading-page">
<main class="loading-wrap">
    <img class="loading-logo" src="img/CarPool détouré.png" alt="CarPool logo">
    <div class="wheel-loader" aria-hidden="true">
        <div class="wheel-tire">
            <div class="wheel-rim">
                <span class="spoke"></span>
                <span class="spoke spoke-2"></span>
                <span class="spoke spoke-3"></span>
                <span class="spoke spoke-4"></span>
                <span class="wheel-core"></span>
            </div>
        </div>
    </div>
    <h1>Bienvenue <?= htmlspecialchars($_SESSION['user_name']) ?></h1>
    <p>Connexion en cours. Redirection vers votre espace CarPool...</p>
</main>
</body>
</html>
