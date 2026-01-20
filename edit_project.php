<?php
require_once 'includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id']) && !empty($data['nome'])) {
    $id = $data['id'];
    $nome = trim($data['nome']);

    $stmt = $pdo->prepare("UPDATE projects SET nome = :nome, data_modifica = NOW() WHERE id = :id");

    if ($stmt->execute(['nome' => $nome, 'id' => $id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
}
?>