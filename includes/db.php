<?php
require_once __DIR__ . '/env_loader.php';

// Carica le variabili d'ambiente dal file .env nella root (../.env rispetto a includes, ma qui siamo in public quindi .env è in public)
// Se .env è nella stessa cartella di index.php (parent di includes):
loadEnv(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'local';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Aggiunto per error handling migliore
} catch (\PDOException $e) {
    // In produzione non mostrare dettagli errore database!
    // throw new \PDOException($e->getMessage(), (int) $e->getCode());
    // die("Errore di connessione al database. Controlla il file di log.");
    die("Errore DB: " . $e->getMessage());
}
?>