<?php
define('DB_NAME',    getenv('DB_NAME')    ?: 'mydatabase');
define('DB_USER',    getenv('DB_USER')    ?: 'user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'password');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

function applySecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https://nominatim.openstreetmap.org https://router.project-osrm.org https://tile.openstreetmap.org;");
}

function csrfToken(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token): void
{
    $sessionToken = $_SESSION['_csrf_token'] ?? null;

    if (!is_string($token) || !is_string($sessionToken) || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('Jeton CSRF invalide.');
    }
}

function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $candidates = [];

    $envHost = getenv('DB_HOST');
    $envPort = getenv('DB_PORT');
    if ($envHost !== false && $envHost !== '') {
        $candidates[] = [$envHost, $envPort !== false && $envPort !== '' ? $envPort : '3306'];
    }

    $candidates[] = ['mysql', '3306'];
    $candidates[] = ['127.0.0.1', '3307'];
    $candidates[] = ['127.0.0.1', '10012'];

    $lastException = null;

    foreach ($candidates as [$host, $port]) {
        try {
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    throw $lastException ?? new PDOException('Database connection failed.');
}
