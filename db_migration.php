<?php
require_once 'includes/db.php';

try {
    // 1. Aggiungi la colonna se non esiste
    // Nota: MySQL non ha "IF NOT EXISTS" per ADD COLUMN in tutte le versioni facilmente, 
    // ma possiamo provare catchare l'errore o controllare prima.
    // Metodo semplice: Try/Catch

    echo "Aggiunta colonna 'data_modifica' alla tabella 'projects'...\n";

    // Controlla se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'data_modifica'");
    if ($stmt->fetch()) {
        echo "La colonna 'data_modifica' esiste già.\n";
    } else {
        // Aggiungi colonna
        $sql = "ALTER TABLE projects ADD COLUMN data_modifica TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP";
        $pdo->exec($sql);
        echo "Colonna aggiunta con successo.\n";

        // 2. Popola con data_creazione per i vecchi record
        echo "Aggiornamento vecchi record...\n";
        // Usa IF per evitare date non valide o null
        $sql = "UPDATE projects SET data_modifica = IF(data_creazione IS NOT NULL AND data_creazione > '1970-01-01', data_creazione, CURRENT_TIMESTAMP)";
        $pdo->exec($sql);
        echo "Record aggiornati.\n";
    }

} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>