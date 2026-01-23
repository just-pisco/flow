<?php
require_once 'includes/db.php';
require_once 'includes/notifications_helper.php';

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

    if (isset($data['stato'])) { // Direct status update
        $updates[] = "stato = :stato_direct";
        $params['stato_direct'] = $data['stato'];
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

    // Handle Assignees (Separate from main UPDATE)
    $updateAssignees = false;
    if (isset($data['assignees']) && is_array($data['assignees'])) {
        $updateAssignees = true;
    }

    if (empty($updates) && !$updateAssignees) {
        echo json_encode(['success' => false, 'error' => 'No updates provided']);
        exit;
    }

    // Execute Main Update if needed
    if (!empty($updates)) {
        $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute($params)) {
            echo json_encode(['success' => false, 'error' => 'Main update failed']);
            exit;
        }
    }

    // Execute Assignees Update
    if ($updateAssignees) {
        $notifManager = new NotificationManager($pdo);

        // Clear existing keys for this task (Simple approach: delete all, insert new)
        $del = $pdo->prepare("DELETE FROM task_assignments WHERE task_id = ?");
        $del->execute([$id]);

        $ins = $pdo->prepare("INSERT IGNORE INTO task_assignments (task_id, user_id) VALUES (?, ?)");

        // Get Task Title for Notification
        $tStmt = $pdo->prepare("SELECT titolo, project_id FROM tasks WHERE id = ?");
        $tStmt->execute([$id]);
        $taskInfo = $tStmt->fetch(); // title, project_id

        foreach ($data['assignees'] as $userId) {
            $ins->execute([$id, $userId]);

            // Avoid notifying self if I assigned myself? Maybe.
            // But let's notify everyone for now or check current user?
            // User ID is not readily available in this script via session? 
            // session_start() IS NOT CALLED HERE! We should add it.
            // But waiting: update_task.php might rely on session cookie being present but not started?
            // Actually it reads input. If session is not started, we don't know who is doing the action.
            // Let's safe check session.
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            $currentUserId = $_SESSION['user_id'] ?? 0;

            if ($userId != $currentUserId && $taskInfo) {
                $notifManager->addNotification(
                    $userId,
                    'task_assign',
                    "Ti è stato assegnato il task '{$taskInfo['titolo']}'",
                    "index.php?project_id={$taskInfo['project_id']}" // Ideally scroll to task?
                );
            }
        }
    }

    // Update Project timestamp
    $updProject = $pdo->prepare("UPDATE projects SET data_modifica = NOW() WHERE id = (SELECT project_id FROM tasks WHERE id = ?)");
    $updProject->execute([$id]);

    echo json_encode(['success' => true]);
    exit; // Stop further execution since we handled logic manually above

}
?>