<?php
require_once 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id'])) {
    $id = $data['id'];

    try {
        $pdo->beginTransaction();

        // 1. Elimina i task associati (Cascata manuale se non impostata su DB)
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE project_id = ?");
        $stmt->execute([$id]);

        // 2. Elimina il progetto
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}
?>