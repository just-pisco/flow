<?php
session_start();
require_once 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

if (isset($data['id']) && !empty($data['nome'])) {
    $id = $data['id'];
    $nome = trim($data['nome']);
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE projects SET nome = :nome, data_modifica = NOW() WHERE id = :id AND user_id = :user_id");

    if ($stmt->execute(['nome' => $nome, 'id' => $id, 'user_id' => $user_id])) {
        // Check if any row was actually updated (i.e., the project belonged to the user)
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed or access denied']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}
?>