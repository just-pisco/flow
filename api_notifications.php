<?php
require_once 'includes/db.php';
require_once 'includes/notifications_helper.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$notifManager = new NotificationManager($pdo);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'get_unread') {
            $data = $notifManager->getUnread($user_id);
            echo json_encode(['success' => true, 'data' => $data]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'mark_read') {
            $id = $input['id'] ?? null;
            if ($id) {
                $notifManager->markAsRead($id, $user_id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ID']);
            }
        } elseif ($action === 'mark_all_read') {
            $notifManager->markAllAsRead($user_id);
            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>