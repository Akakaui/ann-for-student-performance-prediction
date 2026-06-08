<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;

    public function __construct() {
        try {
            $host = DB_HOST;
            $port = DB_PORT;
            $dbname = DB_NAME;
            $user = DB_USER;
            $pass = DB_PASS;
            $sslmode = DB_SSLMODE;

            // Force IPv4 - Render doesn't support IPv6
            $ipv4_host = gethostbyname($host);
            if ($ipv4_host !== $host) {
                $host = $ipv4_host;
                error_log("Resolved $host to IPv4: $ipv4_host");
            }

            error_log("DB Connection: host=$host port=$port dbname=$dbname user=$user sslmode=$sslmode");

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

            $this->connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            error_log("DB Connection successful");
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            error_log("Database connection code: " . $e->getCode());
            error_log("Database DSN: host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " sslmode=" . DB_SSLMODE);
            die("We're having trouble connecting. Please try again later.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction() {
        $this->connection->beginTransaction();
    }

    public function commit() {
        $this->connection->commit();
    }

    public function rollback() {
        $this->connection->rollBack();
    }
}
