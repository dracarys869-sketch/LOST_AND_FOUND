<?php
/**
 * Database Connection — PDO Singleton
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('<div role="alert" style="background:var(--danger-bg,#fbeeea);color:var(--danger,#a32f2b);padding:var(--space-4,1rem);font-family:var(--font-sans,system-ui,sans-serif);border-radius:var(--radius,.5rem);">
                    <strong>Database Connection Failed:</strong><br>' . htmlspecialchars($e->getMessage()) . '
                </div>');
            }
            die('A database error occurred. Please try again later.');
        }
    }

    /** Get the singleton instance */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Get the raw PDO connection */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prepare and execute a query with bound parameters.
     *
     * @param string $sql    The SQL query with placeholders
     * @param array  $params An associative or indexed array of parameters
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /** Fetch all matching rows */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Execute an INSERT and return the last insert ID */
    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /** Execute an UPDATE/DELETE and return affected row count */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /** Begin a transaction */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /** Commit a transaction */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /** Roll back a transaction */
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

    /** Prevent cloning of the singleton */
    private function __clone() {}
}

/**
 * Helper: Get the global DB instance
 */
function db(): Database
{
    return Database::getInstance();
}
