<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'if_homecare');

// Classe de Conexão
class Database {
private static $instance = null;
private $conn;

private function __construct() {
try {
$this->conn = new PDO(
"mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
DB_USER,
DB_PASS,
[
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false
]
);
} catch(PDOException $e) {
die("Erro na conexão: " . $e->getMessage());
}
}

public static function getInstance() {
if (self::$instance === null) {
self::$instance = new self();
}
return self::$instance;
}

public function getConnection() {
return $this->conn;
}

// Prevenir clonagem
private function __clone() {}

// Prevenir deserialização
public function __wakeup() {
throw new Exception("Cannot unserialize singleton");
}
}

// Função auxiliar para obter a conexão
function getDB() {
return Database::getInstance()->getConnection();
}
