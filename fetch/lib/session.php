<?php
/**
 * Sessions in MySQL.
 *
 * On XAMPP, PHP keeps sessions as files in a temp folder and everything works.
 * On a serverless host every request may land on a different machine with its
 * own temp folder, so a login would be forgotten immediately. Storing the
 * session rows in the database we already have fixes that without adding any
 * new service.
 */

final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        // The table is created by install.php; make sure it exists anyway.
        db()->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
               id         VARCHAR(128) PRIMARY KEY,
               payload    MEDIUMTEXT   NOT NULL,
               updated_at DATETIME     NOT NULL
             )'
        );
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $row = q1('SELECT payload FROM sessions WHERE id = ?', [$id]);
        return $row['payload'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        exec_sql(
            'INSERT INTO sessions (id, payload, updated_at) VALUES (?,?,NOW())
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = NOW()',
            [$id, $data]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        exec_sql('DELETE FROM sessions WHERE id = ?', [$id]);
        return true;
    }

    /** Housekeeping: PHP calls this now and then. */
    public function gc(int $maxLifetime): int|false
    {
        return exec_sql('DELETE FROM sessions WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)', [$maxLifetime]);
    }
}

function use_database_sessions(): void
{
    session_set_save_handler(new DbSessionHandler(), true);
}
