<?php
$allowed_pages = ['Home'];

$page = $_GET['page'] ?? 'Home';

//die (var_dump($page));

if (!in_array($page, $allowed_pages, true)) {
    $page = 'Home';
}


require "doctype.php";
require "Navbar.php";

// on charge la page voulu
require $page . '.php';

// require footer
require "footer.php";
?>
