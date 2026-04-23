<?php
session_start();
require_once __DIR__ . '/config.php';
applySecurityHeaders();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_name'], $_SESSION['user_email'])) {
    header('Location: login.php');
    exit;
}

$allowed_pages = ['home', 'Profil', 'Reservation', 'Map', 'Parametre'];

$page = $_GET['page'] ?? 'home';
$isNotFound = false;

if (!in_array($page, $allowed_pages, true)) {
    $page = '404';
    $isNotFound = true;
}

if ($isNotFound) {
    http_response_code(404);
}

$baseDir = __DIR__;

require $baseDir . '/doctype.php';

if (!$isNotFound) {
    require $baseDir . '/navbar.php';
}

// on charge la page voulue
require $baseDir . '/' . $page . '.php';

// require footer
if (!$isNotFound) {
    require $baseDir . '/footer.php';
} else {
    echo '</body></html>';
}
?>
