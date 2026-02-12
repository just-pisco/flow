<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order']) || !is_array($input['order'])) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "UPDATE projects SET ordinamento = :ordinamento WHERE id = :id";
    $stmt = $pdo->prepare($sql);

    foreach ($input['order'] as $position => $projectId) {
        $stmt->execute([
            ':ordinamento' => $position,
            ':id' => $projectId
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
