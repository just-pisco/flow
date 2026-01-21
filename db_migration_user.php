<?php
require_once 'includes/db.php';

try {
    echo "Aggiunta colonna 'user_id' alla tabella 'projects'...\n";

    // Controlla se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'user_id'");
    if ($stmt->fetch()) {
        echo "La colonna 'user_id' esiste già.\n";
    } else {
        // Aggiungi colonna (INT, può essere NULL inizialmente per i vecchi poi li fissiamo)
        $sql = "ALTER TABLE projects ADD COLUMN user_id INT NOT NULL DEFAULT 1";
        // DEFAULT 1 assume che l'admin/primo utente possieda tutto il pregresso.
        // Se non c'è user con ID 1 potrebbe dare problemi con foreign keys se le mettessimo, 
        // ma per ora è un link logico.

        $pdo->exec($sql);
        echo "Colonna aggiunta con successo. Default assegnato a user_id=1.\n";

        // Aggiungi l'indice per performance
        $pdo->exec("CREATE INDEX idx_user_id ON projects(user_id)");
        echo "Indice creato.\n";
    }

} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>