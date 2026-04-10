<?php
$currentPage = $_GET['page'] ?? 'home';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="index.php?page=home">
            <span class="brand-mark">
                <img class="brand-logo" src="img/CarPool détouré.png" alt="CarPool logo">
                <span>CarPool</span>
            </span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>" href="index.php?page=home">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'Reservation' ? 'active' : '' ?>" href="index.php?page=Reservation">Reservations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'Map' ? 'active' : '' ?>" href="index.php?page=Map">Carte</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-with-icon <?= $currentPage === 'Profil' ? 'active' : '' ?>" href="index.php?page=Profil">
                        <span class="nav-icon-badge" aria-hidden="true">
                            <img class="nav-icon" src="img/img_icons/user.png" alt="">
                        </span>
                        <span>Profil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-with-icon <?= $currentPage === 'Parametre' ? 'active' : '' ?>" href="index.php?page=Parametre">
                        <span class="nav-icon-badge" aria-hidden="true">
                            <img class="nav-icon" src="img/img_icons/settings.png" alt="">
                        </span>
                        <span>Parametres</span>
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="navbar-text text-white-50"><?= $userName ?></span>
                <a class="btn btn-outline-light btn-sm" href="deconnexion.php">Deconnexion</a>
            </div>
        </div>
    </div>
</nav>
