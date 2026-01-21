<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['order']) && is_array($data['order'])) {
        try {
            $pdo->beginTransaction();

            $sql = "UPDATE tasks SET ordinamento = :ordinamento WHERE id = :id";
            $stmt = $pdo->prepare($sql);

            foreach ($data['order'] as $index => $id) {
                $stmt->execute([
                    ':ordinamento' => $index,
                    ':id' => $id
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>