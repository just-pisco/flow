<?php
require_once 'includes/db.php';

// Leggiamo i dati inviati via JavaScript (JSON)
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    $id = $data['id'];
    $updates = [];
    $params = ['id' => $id];

    if (isset($data['completato'])) {
        $updates[] = "stato = :stato";
        $params['stato'] = $data['completato'] ? 'completato' : 'da_fare';
    }

    if (isset($data['titolo'])) {
        $updates[] = "titolo = :titolo";
        $params['titolo'] = trim($data['titolo']);
    }

    if (array_key_exists('scadenza', $data)) { // Usa array_key_exists per permettere null
        $updates[] = "scadenza = :scadenza";
        $rawDate = !empty($data['scadenza']) ? $data['scadenza'] : null;

        // Convert dd/mm/yyyy to yyyy-mm-dd if necessary
        if ($rawDate) {
            $dateObj = DateTime::createFromFormat('d/m/Y', $rawDate);
            if ($dateObj) {
                $rawDate = $dateObj->format('Y-m-d');
            }
        }
        $params['scadenza'] = $rawDate;
    }

    if (array_key_exists('descrizione', $data)) {
        $updates[] = "descrizione = :descrizione";
        $params['descrizione'] = $data['descrizione'];
    }

    if (empty($updates)) {
        echo json_encode(['success' => false, 'error' => 'No updates provided']);
        exit;
    }

    $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute($params)) {
        // Aggiorna data_modifica del progetto
        $updProject = $pdo->prepare("UPDATE projects SET data_modifica = NOW() WHERE id = (SELECT project_id FROM tasks WHERE id = ?)");
        $updProject->execute([$id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
}
?>