<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // SAVE KEY
    if (isset($data['action']) && $data['action'] === 'save' && !empty($data['key'])) {
        $key = trim($data['key']);
        // Basic validation length?

        try {
            $stmt = $pdo->prepare("UPDATE users SET gemini_api_key = :key WHERE id = :id");
            $stmt->execute(['key' => $key, 'id' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }
    // DELETE KEY
    elseif (isset($data['action']) && $data['action'] === 'delete') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET gemini_api_key = NULL WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>