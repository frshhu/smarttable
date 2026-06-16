<?php
namespace SmartTable\Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $config = require __DIR__ . '/../../config/database.php';
        
        // DODANO: ;sslmode=require na końcu DSN dla pełnej kompatybilności z chmurą Neon
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};sslmode=require";
        
        try {
            $this->conn = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Błąd połączenia z chmurową bazą danych Neon: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}