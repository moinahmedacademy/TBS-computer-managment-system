<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die(json_encode(['error' => 'Database connection failed. Please contact admin.']));
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConn() {
        return $this->conn;
    }

    public function query($sql, $params = [], $types = '') {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log('Query prepare failed: ' . $this->conn->error . ' SQL: ' . $sql);
            return false;
        }
        if ($params) {
            if (!$types) {
                $types = str_repeat('s', count($params));
            }
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public function fetchAll($sql, $params = [], $types = '') {
        $stmt = $this->query($sql, $params, $types);
        if (!$stmt) return [];
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function fetchOne($sql, $params = [], $types = '') {
        $stmt = $this->query($sql, $params, $types);
        if (!$stmt) return null;
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function insert($sql, $params = [], $types = '') {
        $stmt = $this->query($sql, $params, $types);
        if (!$stmt) return false;
        return $this->conn->insert_id;
    }

    public function execute($sql, $params = [], $types = '') {
        $stmt = $this->query($sql, $params, $types);
        if (!$stmt) return false;
        return $stmt->affected_rows;
    }

    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
}

function db() {
    return Database::getInstance();
}
