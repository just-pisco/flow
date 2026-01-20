<?php
require_once 'includes/db.php';
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    // Recupera project_id prima di cancellare
    $stmt = $pdo->prepare("SELECT project_id FROM tasks WHERE id = ?");
    $stmt->execute([$data['id']]);
    $task = $stmt->fetch();
    $project_id = $task['project_id'] ?? null;

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    if ($stmt->execute([$data['id']])) {
        // Aggiorna progetto
        if ($project_id) {
            $upd = $pdo->prepare("UPDATE projects SET data_modifica = NOW() WHERE id = ?");
            $upd->execute([$project_id]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>