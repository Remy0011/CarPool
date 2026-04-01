<?php
require_once 'config.php';

try {
    $pdo = getDB();
    echo "<p style='color:green;font-family:sans-serif;'>✅ Connexion réussie à la base <strong>mysql_db</strong> sur mysql:3306</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;font-family:sans-serif;'>❌ Échec de connexion : " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
