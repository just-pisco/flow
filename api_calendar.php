<?php
require_once 'includes/db.php';
require_once 'includes/notifications_helper.php';
require_once 'includes/GoogleCalendarHelper.php';

// Prevent HTML errors breaking JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_api_error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$helper = new GoogleCalendarHelper($pdo);

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

    } elseif ($action === 'auth_code') {
        // Exchange Auth Code for Tokens
        $data = json_decode(file_get_contents('php://input'), true);
        $code = $data['code'] ?? null;

        if (!$code) {
            throw new Exception("Auth code missing");
        }

        $tokens = $helper->exchangeCodeForToken($currentUserId, $code);

        // Se otteniamo i token, proviamo a creare/trovare il calendario
        // Nota: exchangeCodeForToken salva già nel DB i token.
        // Possiamo chiamare getAccessToken per sicurezza o usare $tokens['access_token']

        echo json_encode(['success' => true]);

    } elseif ($action === 'get_token') {
        // Return valid access token if available
        $token = $helper->getAccessToken($currentUserId);

        if ($token) {
            echo json_encode(['success' => true, 'access_token' => $token]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No token found']);
        }

    } elseif ($action === 'get_google_config') {
        // KEEPING FOR COMPATIBILITY (Used by JS to check ID presence)
        $stmt = $pdo->prepare("SELECT google_calendar_id FROM users WHERE id = ?");
        $stmt->execute([$currentUserId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'google_calendar_id' => $result['google_calendar_id'] ?? null]);

    } elseif ($action === 'save_google_config') {
        // Can be used to manually clear calendar ID or legacy purposes
        $data = json_decode(file_get_contents('php://input'), true);
        if (array_key_exists('google_calendar_id', $data)) {
            $calendarId = $data['google_calendar_id'];
            $stmt = $pdo->prepare("UPDATE users SET google_calendar_id = ? WHERE id = ?");
            $stmt->execute([$calendarId, $currentUserId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Missing google_calendar_id field']);
        }

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