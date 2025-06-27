<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private $host;
    private $username;
    private $password;
    private $database;
    
    private function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'mysql';
        $this->username = $_ENV['DB_USER'] ?? 'mail_user';
        $this->password = $_ENV['DB_PASS'] ?? 'mail_password';
        $this->database = $_ENV['DB_NAME'] ?? 'mail_service';
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            $this->connection = new PDO($dsn, $this->username, $this->password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
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
    
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
?>
