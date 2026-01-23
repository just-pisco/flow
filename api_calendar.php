<?php
require_once 'includes/db.php';
require_once 'includes/notifications_helper.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_events') {
        $view = $_GET['view'] ?? 'mine'; // 'mine' or 'all'

        if ($view === 'all') {
            // Fetch ALL tasks from projects where user is a member
            $stmt = $pdo->prepare("
                SELECT t.id, t.titolo, t.scadenza, t.stato, t.project_id
                FROM tasks t
                JOIN project_members pm ON t.project_id = pm.project_id
                WHERE pm.user_id = ? 
                AND t.scadenza IS NOT NULL 
                GROUP BY t.id
            ");
        } else {
            // Fetch only tasks assigned to user
            $stmt = $pdo->prepare("
                SELECT t.id, t.titolo, t.scadenza, t.stato, t.project_id
                FROM tasks t
                JOIN task_assignments ta ON t.id = ta.task_id
                WHERE ta.user_id = ? 
                AND t.scadenza IS NOT NULL 
            ");
        }

        $stmt->execute([$currentUserId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($tasks as $t) {
            $color = '#64748b'; // default slate-500
            if ($t['stato'] === 'completato')
                $color = '#16a34a'; // green-600
            elseif ($t['stato'] === 'in_corso')
                $color = '#2563eb'; // blue-600
            elseif ($t['stato'] === 'revisione')
                $color = '#9333ea'; // purple-600

            // Check if date is valid YYYY-MM-DD
            if (strtotime($t['scadenza'])) {
                $events[] = [
                    'id' => $t['id'],
                    'title' => $t['titolo'],
                    'start' => $t['scadenza'], // FullCalendar expects YYYY-MM-DD
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'url' => "index.php?project_id={$t['project_id']}&highlight_task={$t['id']}",
                    'allDay' => true // Tasks usually due on a day, not specific time unless we add time
                ];
            }
        }

        echo json_encode($events); // FullCalendar expects direct array
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    file_put_contents('debug_api_error.log', date('Y-m-d H:i:s') . ": " . $errorMsg . "\n", FILE_APPEND);
    echo json_encode(['error' => $errorMsg]);
}
?>