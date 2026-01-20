<?php
require_once 'includes/db.php';

try {
    echo "Aggiunta colonna 'scadenza' alla tabella 'tasks'...\n";

    // Controlla se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'scadenza'");
    if ($stmt->fetch()) {
        echo "La colonna 'scadenza' esiste già.\n";
    } else {
        // Aggiungi colonna
        $sql = "ALTER TABLE tasks ADD COLUMN scadenza DATE NULL DEFAULT NULL";
        $pdo->exec($sql);
        echo "Colonna aggiunta con successo.\n";
    }

} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>