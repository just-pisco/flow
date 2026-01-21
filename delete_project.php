<?php
session_start();
require_once 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    $id = $data['id'];

    try {
        $pdo->beginTransaction();

        // 1. Elimina i task associati (Cascata manuale se non impostata su DB)
        // This should only delete tasks for projects owned by the user, or rely on project deletion cascade
        // For now, we assume tasks are deleted if the project is deleted.
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE project_id = ?");
        $stmt->execute([$id]);

        // 2. Elimina il progetto con controllo utente
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $id, 'user_id' => $user_id]);

        // Check if a project was actually deleted
        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode(['success' => true]);
        } else {
            // No project deleted, likely due to incorrect ID or user_id mismatch
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Delete failed or access denied']);
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}
?>