<?php

/**
 * SB-Tech — thin database layer built on mysqli prepared statements.
 *
 * The single DB access point for the whole app (anti-pattern fix #1/#2:
 * no string-concatenated SQL, no dual mysqli/PDO APIs). All data access
 * must go through this class. Business queries live in services, not
 * in page scripts.
 *
 * Usage:
 *   Database::select($sql, [params])
 *   Database::selectOne($sql, [params])
 *   Database::execute($sql, [params])      // INSERT/UPDATE/DELETE/DDL
 *   Database::insert($table, [data])       // associative array
 *   Database::update($table, [data], $whereSql, [whereParams])
 *   Database::delete($table, $whereSql, [whereParams])
 *   Database::transaction(callable)
 *
 * Parameter placeholders use ? (positional), matching mysqli bind types.
 * Types are inferred: null → i? no — null → s (MySQL casts), int → i,
 * float → d, everything else → s.
 */

require_once __DIR__ . '/QueryException.php';

class Database
{
    /** @var mysqli */
    private $mysqli;

    private static $instance = null;

    private function __construct(array $cfg)
    {
        $host   = $cfg['db_host']     ?? 'localhost';
        $user   = $cfg['db_username'] ?? '';
        $pass   = $cfg['db_password'] ?? '';
        $name   = $cfg['db_name']     ?? '';
        $socket = $cfg['db_socket']   ?? null;
        $port   = isset($cfg['db_port']) ? (int) $cfg['db_port'] : 0;

        $useSocket = $socket && ($host === 'localhost' || $host === '127.0.0.1');

        mysqli_report(MYSQLI_REPORT_OFF);
        // PHP 8.1+: connection failures ALWAYS throw mysqli_sql_exception,
        // even with MYSQLI_REPORT_OFF (the @ suppresses the duplicate PHP
        // warning raised just before the exception). Convert it to our own
        // RuntimeException so callers can handle it (page bootstrap shows a
        // friendly error; test helpers skip cleanly).
        try {
            $this->mysqli = @new mysqli($host, $user, $pass, $name, $useSocket ? null : $port, $useSocket ? $socket : null);
        } catch (mysqli_sql_exception $e) {
            if (!empty(config('debug'))) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
            }
            throw new RuntimeException('Database connection failed. Contact the administrator.', 0, $e);
        }
        $this->mysqli->set_charset('utf8mb4');
    }

    /** Get the shared singleton instance (bootstrap creates it as $objQuery). */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self($GLOBALS['APP_CONFIG'] ?? []);
        }
        return self::$instance;
    }

    public function mysqli(): mysqli
    {
        return $this->mysqli;
    }

    /** Select multiple rows as associative arrays. Throws on failure. */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql, $params);
        if (!$stmt->execute()) {
            $this->failQuery($sql, $stmt);
        }
        $rows   = [];
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $rows;
    }

    /** Select a single row, or null when no row matches. Throws on failure. */
    public function selectOne(string $sql, array $params = [])
    {
        $stmt = $this->prepare($sql, $params);
        if (!$stmt->execute()) {
            $this->failQuery($sql, $stmt);
        }
        $result = $stmt->get_result();
        $row    = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        return $row;
    }

    /** Run INSERT/UPDATE/DELETE/DDL. Throws on failure (never silent). */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->prepare($sql, $params);
        $ok   = $stmt->execute();
        if (!$ok) {
            $this->failQuery($sql, $stmt);
        }
        $stmt->close();
        return true;
    }

    /** Insert an associative array into a table; returns the new id. Throws on failure. */
    public function insert(string $table, array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('insert() requires at least one column.');
        }
        $cols         = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $sql          = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . $placeholders . ')';
        $stmt         = $this->prepare($sql, array_values($data));
        $ok           = $stmt->execute();
        if (!$ok) {
            $this->failQuery($sql, $stmt);
        }
        $id = (int) $this->mysqli->insert_id;
        $stmt->close();
        return $id;
    }

    /** Update rows; returns affected rows. Throws on failure. */
    public function update(string $table, array $data, string $whereSql, array $whereParams = []): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('update() requires at least one column.');
        }
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = '`' . $col . '` = ?';
        }
        $sql  = 'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . $whereSql;
        $stmt = $this->prepare($sql, array_merge(array_values($data), $whereParams));
        $ok   = $stmt->execute();
        if (!$ok) {
            $this->failQuery($sql, $stmt);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /** Delete rows; returns affected rows. Throws on failure. */
    public function delete(string $table, string $whereSql, array $whereParams = []): int
    {
        $sql  = 'DELETE FROM `' . $table . '` WHERE ' . $whereSql;
        $stmt = $this->prepare($sql, $whereParams);
        $ok   = $stmt->execute();
        if (!$ok) {
            $this->failQuery($sql, $stmt);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    /** Run a callable inside a transaction; rolls back on exception. */
    public function transaction(callable $fn)
    {
        $this->mysqli->begin_transaction();
        try {
            $result = $fn($this);
            $this->mysqli->commit();
            return $result;
        } catch (Throwable $e) {
            $this->mysqli->rollback();
            throw $e;
        }
    }

    /** Count rows for a table (helper used by admin lists). */
    public function count(string $table, string $whereSql = '1=1', array $params = []): int
    {
        $row = $this->selectOne('SELECT COUNT(*) AS c FROM `' . $table . '` WHERE ' . $whereSql, $params);
        return (int) ($row['c'] ?? 0);
    }

    /** Escape a LIKE fragment safely (wildcards from user input). */
    public function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /** Last inserted id. */
    public function lastInsertId(): int
    {
        return (int) $this->mysqli->insert_id;
    }

    /** Last error message (for debugging/logging). */
    public function lastError(): string
    {
        return (string) $this->mysqli->error;
    }

    /**
     * Log the full failure detail and throw a QueryException whose message is
     * safe to display. Raw SQL stays out of getMessage() (handlers flash it).
     */
    private function failQuery(string $sql, mysqli_stmt $stmt): never
    {
        $err = $stmt->error !== '' ? $stmt->error : $this->mysqli->error;
        $stmt->close();
        error_log('Database query error: ' . $err . ' | SQL: ' . $sql);
        throw new QueryException('Database query failed: ' . $err, $sql);
    }

    private function failPrepare(string $sql): never
    {
        $err = $this->mysqli->error;
        error_log('Database prepare error: ' . $err . ' | SQL: ' . $sql);
        throw new QueryException('Database query failed: ' . $err, $sql);
    }

    private function prepare(string $sql, array $params = []): mysqli_stmt
    {
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            $this->failPrepare($sql);
        }
        if ($params !== []) {
            $types = '';
            foreach ($params as $p) {
                if (is_int($p)) {
                    $types .= 'i';
                } elseif (is_float($p)) {
                    $types .= 'd';
                } elseif (is_null($p)) {
                    $types .= 's'; // MySQL casts NULL via string type
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }
        return $stmt;
    }
}
