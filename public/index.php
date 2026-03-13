<?php
session_start();

$allowed_pages = ['Home','Profil','Reservation','Map'];

$page = $_GET['page'] ?? 'Home';

//die (var_dump($page));

if (!in_array($page, $allowed_pages, true)) {
    $page = '404';
}


require "doctype.php";
require "navbar.php";

// on charge la page voulu
require $page . '.php';

// require footer
require "footer.php";
?>
