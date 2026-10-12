<?php
/**
 * One PDO connection per request. Every query in the app goes through here,
 * and every query uses prepared statements — no string-built SQL anywhere.
 */

/** Connection options, shared by the app and the installer. */
function pdo_options(): array
{
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (DB_SSL) {                       // cloud MySQL refuses plaintext connections
        // PHP 8.5 renamed this constant; look it up by name so both work.
        $sslCa = defined('Pdo\\Mysql::ATTR_SSL_CA') ? constant('Pdo\\Mysql::ATTR_SSL_CA') : PDO::MYSQL_ATTR_SSL_CA;
        $options[$sslCa] = DB_SSL_CA;
    }
    return $options;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, pdo_options());
    } catch (PDOException $e) {
        http_response_code(500);
        $install = base_url('install.php');
        exit(
            '<div style="font:15px/1.6 system-ui;max-width:640px;margin:14vh auto;padding:28px;'
            . 'background:#1a1612;color:#f7efe5;border-radius:18px;border:1px solid #ff5a1f55">'
            . '<h2 style="margin:0 0 10px">Cannot reach the database</h2>'
            . '<p style="color:#c9bdae">Start <b>MySQL</b> in the XAMPP manager, then run the installer.</p>'
            . '<p style="font-family:ui-monospace,monospace;font-size:13px;color:#8e8375">'
            . htmlspecialchars($e->getMessage()) . '</p>'
            . '<p><a href="' . $install . '" style="color:#ff5a1f">→ Run the installer</a></p></div>'
        );
    }
    return $pdo;
}

/** Run a query and return every row. */
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Run a query and return the first row (or null). */
function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Run a statement that changes data; returns the number of affected rows. */
function exec_sql(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Insert and return the new id. */
function insert(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}
