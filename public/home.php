<?php
$name     = htmlspecialchars($_SESSION['user_name']);
$email    = htmlspecialchars($_SESSION['user_email']);
$initiale = mb_strtoupper(mb_substr($name, 0, 1));
?>

<main class="container home-shell d-flex justify-content-center align-items-center">
    <div class="welcome-card text-center">
        <div class="welcome-avatar">
            <?= $initiale ?>
        </div>
        <h2 class="fw-semibold mb-1">Bienvenue, <?= $name ?> !</h2>
        <p class="text-muted small mb-3"><?= $email ?></p>
        <div class="welcome-panel mb-3">
            <p class="small text-secondary mb-0">Vous êtes connecté au portail. Vos réservations s'afficheront ici.</p>
        </div>
    </div>
</main>
