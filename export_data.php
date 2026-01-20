<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="flow_backup_' . date('Y-m-d') . '.json"');

try {
    $projects = [];

    // Fetch all projects
    $stmt = $pdo->query("SELECT * FROM projects");
    while ($project = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fetch tasks for each project
        $taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ?");
        $taskStmt->execute([$project['id']]);
        $project['tasks'] = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

        $projects[] = $project;
    }

    echo json_encode(['version' => '1.0', 'exported_at' => date('c'), 'data' => $projects], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
}
?>