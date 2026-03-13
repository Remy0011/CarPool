<?php
$allowed_pages = ['Home','Profil','Reservation','Map'];

$page = $_GET['page'] ?? 'Home';

//die (var_dump($page));

if (!in_array($page, $allowed_pages, true)) {
    $page = 'Home';
}

// Vérifier si le fichier existe
if (file_exists($page)) {
    include $page;
} else {
    include '404.php'; // Page d'erreur 404
}

require "doctype.php";
require "navbar.php";

// on charge la page voulu
require $page . '.php';

// require footer
require "footer.php";
?>
