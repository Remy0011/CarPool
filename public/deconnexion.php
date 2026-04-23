<?php
session_start();
require_once __DIR__ . '/config.php';
applySecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit('Forbidden');
}

session_unset();
session_destroy();
header('Location: login.php');
exit;
?>
