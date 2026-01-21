<?php
require_once 'includes/db.php';

try {
    echo "Aggiunta colonna 'ordinamento' alla tabella 'tasks'...\n";

    // Controlla se la colonna esiste già
    $stmt = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'ordinamento'");
    if ($stmt->fetch()) {
        echo "La colonna 'ordinamento' esiste già.\n";
    } else {
        // Aggiungi colonna
        $sql = "ALTER TABLE tasks ADD COLUMN ordinamento INT NOT NULL DEFAULT 0";
        $pdo->exec($sql);
        echo "Colonna aggiunta con successo.\n";

        // Inizializza l'ordinamento basato sull'ordine di creazione (o ID)
        echo "Inizializzazione ordinamento...\n";
        $sql = "SET @rank = 0; UPDATE tasks SET ordinamento = (@rank := @rank + 1) ORDER BY data_creazione DESC"; // O ORDER BY id
        // Nota: L'ordinamento di visualizzazione attuale è data_creazione DESC.
        // Se vogliamo mantenere quell'ordine come iniziale per il drag & drop custom, dovremmo assegnare gli indici in quell'ordine.
        // Tuttavia, SortableJS di solito lavora con indici crescenti dall'alto in basso.
        // La query SELECT attuale è ORDER BY data_creazione DESC.
        // Quindi il task più nuovo è in alto (indice visuale 0).

        // Eseguiamo un update intelligente?
        // Per ora lasciamo 0 o facciamo un update semplice.
        // Facciamo che i nuovi task hanno ordinamento 0 (in cima) o MAX+1 (in fondo)?
        // Dipende dalla query di select.
        // Se cambiamo la query in ORDER BY ordinamento ASC, allora dobbiamo popolare 'ordinamento'.
    }

} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
?>