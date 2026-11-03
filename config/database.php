<?php

require_once __DIR__ . '/app.php';

/**
 * Return one shared PDO connection for the application.
 * The connection uses prepared statements and utf8mb4 encoding.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Keep CURRENT_TIMESTAMP in stored procedures aligned with PHP local time.
    $pdo->exec("SET time_zone = '-05:00'");

    return $pdo;
}
