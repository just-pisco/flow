<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET' && $action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM task_statuses WHERE user_id = ? ORDER BY ordine ASC, id ASC");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'create') {
            if (empty($input['nome']) || empty($input['colore'])) {
                throw new Exception("Nome e colore sono obbligatori");
            }

            // Get current max order
            $stmt = $pdo->prepare("SELECT MAX(ordine) FROM task_statuses WHERE user_id = ?");
            $stmt->execute([$userId]);
            $maxOrder = $stmt->fetchColumn() ?: 0;

            $stmt = $pdo->prepare("INSERT INTO task_statuses (user_id, nome, colore, ordine) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, trim($input['nome']), $input['colore'], $maxOrder + 1]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } elseif ($action === 'update') {
            if (empty($input['id']) || empty($input['nome']) || empty($input['colore'])) {
                throw new Exception("Dati incompleti");
            }

            $stmt = $pdo->prepare("UPDATE task_statuses SET nome = ?, colore = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([trim($input['nome']), $input['colore'], $input['id'], $userId]);

            echo json_encode(['success' => true]);
        } elseif ($action === 'delete') {
            if (empty($input['id'])) {
                throw new Exception("ID mancante");
            }

            // Optional: Check if used by tasks? 
            // For now, simpler: Allow delete. Tasks with this status string will just lose the color association 
            // or we could update them to 'da_fare'.
            // Let's migrate orphaned tasks to 'da_fare' just in case, but 'da_fare' might be deleted too!
            // Safest: Do nothing to tasks, they keep the string. But rendering might break if we rely solely on DB join.
            // But we stored 'stato' as string in tasks table. So they keep the string.

            $stmt = $pdo->prepare("DELETE FROM task_statuses WHERE id = ? AND user_id = ?");
            $stmt->execute([$input['id'], $userId]);

            echo json_encode(['success' => true]);
        } elseif ($action === 'reorder') {
            // Expect input['order'] = [id1, id2, id3]
            if (empty($input['order']) || !is_array($input['order'])) {
                throw new Exception("Ordine non valido");
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE task_statuses SET ordine = ? WHERE id = ? AND user_id = ?");
            foreach ($input['order'] as $index => $id) {
                $stmt->execute([$index, $id, $userId]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Azione non valida");
        }
    } else {
        throw new Exception("Metodo non supportato");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>