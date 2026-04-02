<?php
session_start();
require_once __DIR__ . '/config.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_name'], $_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

$allowed_pages = ['home', 'Profil', 'Reservation', 'Map', 'Parametre'];

$page = $_GET['page'] ?? 'home';

if (!in_array($page, $allowed_pages, true)) {
    $page = '404';
}

require 'doctype.php';
require 'navbar.php';

// on charge la page voulue
require $page . '.php';

// require footer
require 'footer.php';
?>
