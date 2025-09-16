<?php
// Legacy lightweight Database wrapper
// This file will load ONLY if the enhanced Database class (config/database.php) has not been included.
if (!class_exists('Database')) {
class Database {
    private static $instance = null;
    private $pdo;
    private $logger;

    private function __construct() {
        try {
            require_once __DIR__ . '/../config/config.php';
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Initialize logger if available
            if (function_exists('getLogger')) {
                $this->logger = getLogger();
            } else {
                $this->logger = null;
            }
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    // Prevent cloning of the instance
    private function __clone() {}

    /**
     * Get Database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Execute a query and return the statement
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Execution Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw new Exception("Database query failed. Please try again later.");
        }
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->execute($sql, $params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("FetchAll Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch a single row
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->execute($sql, $params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("FetchOne Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch a single row (alias for fetchOne) – kept for backward compatibility
     *
     * @param string $sql   SQL query with placeholders
     * @param array  $params Bind parameters for the query
     * @return array|null   Associative array of the row or null if none
     */
    public function fetchRow($sql, $params = []) {
        return $this->fetchOne($sql, $params);
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Quote a string for use in a query
     */
    public function quote($string) {
        return $this->pdo->quote($string);
    }

    /**
     * Get the PDO instance directly (use with caution)
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Alias for getPdo() to provide direct PDO access
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Execute query with error handling (convenience wrapper)
     * @param string $sql SQL query
     * @param array  $params Parameters to bind
     * @return PDOStatement|false
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log via PSR-3 logger if set; fallback to error_log
            if ($this->logger) {
                $this->logger->error('Database query error', [
                    'sql'    => $sql,
                    'params' => $params,
                    'error'  => $e->getMessage()
                ]);
            } else {
                error_log('Database query error: ' . $e->getMessage() . '\nSQL: ' . $sql . '\nParams: ' . print_r($params, true));
            }
            return false;
        }
    }

    /**
     * Check if a table exists in the current database
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        try {
            $result = $this->fetchRow(
                "SELECT COUNT(*) AS count FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
                [DB_NAME, $tableName]
            );
            return isset($result['count']) && $result['count'] > 0;
        } catch (Exception $e) {
            // Silent fail to false
            return false;
        }
    }
} 
}