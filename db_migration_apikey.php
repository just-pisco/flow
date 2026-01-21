<?php
require_once 'includes/db.php';

try {
    echo "Aggiunta colonna 'gemini_api_key' alla tabella 'users'...\n";

    // Controlla se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gemini_api_key'");
    if ($stmt->fetch()) {
        echo "La colonna 'gemini_api_key' esiste già.\n";
    } else {
        // Aggiungi colonna (VARCHAR 255, NULLABLE)
        $sql = "ALTER TABLE users ADD COLUMN gemini_api_key VARCHAR(255) NULL";
        $pdo->exec($sql);
        echo "Colonna aggiunta con successo.\n";
    }

} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>