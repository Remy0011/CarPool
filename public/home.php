<?php
$name     = htmlspecialchars($_SESSION['user_name']);
$email    = htmlspecialchars($_SESSION['user_email']);
$initiale = mb_strtoupper(mb_substr($name, 0, 1));
?>

<div class="container d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="card shadow-sm p-4 text-center" style="max-width: 420px; width: 100%;">
        <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary fw-bold fs-4"
             style="width: 64px; height: 64px;">
            <?= $initiale ?>
        </div>
        <h2 class="fw-semibold mb-1">Bienvenue, <?= $name ?> !</h2>
        <p class="text-muted small mb-3"><?= $email ?></p>
        <div class="bg-light rounded p-3 text-start mb-3">
            <p class="small text-secondary mb-0">Vous êtes connecté au portail. Le contenu de votre application s'affichera ici.</p>
        </div>
        <a href="deconnexion.php" class="btn btn-outline-dark btn-sm">Se déconnecter</a>
    </div>
</div>
