<?php
define('DB_NAME',    getenv('DB_NAME')    ?: 'mydatabase');
define('DB_USER',    getenv('DB_USER')    ?: 'user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'password');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

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
