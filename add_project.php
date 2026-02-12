<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nome_progetto'])) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $nome = $_POST['nome_progetto'];
    $colore = $_POST['colore'] ?? '#6366f1'; // Default Indigo
    $user_id = $_SESSION['user_id'];

    // Get max order to append at the end
    $stmtOrder = $pdo->prepare("SELECT MAX(ordinamento) FROM projects WHERE id IN (SELECT project_id FROM project_members WHERE user_id = ?)");
    $stmtOrder->execute([$user_id]); // Simplified logic: order among accessible projects? Or just global?
    // Actually, order is likely per user if we had a specific "user_projects" sorting table, but here it's on the project table itself.
    // If it's on the project table, it's shared order. That might be tricky for multi-user.
    // But for now, let's just append.
    $maxOrder = $stmtOrder->fetchColumn();
    $newOrder = $maxOrder !== false ? $maxOrder + 1 : 0;

    $sql = "INSERT INTO projects (nome, user_id, colore, ordinamento) VALUES (:nome, :user_id, :colore, :ordinamento)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([
        'nome' => $nome, 
        'user_id' => $user_id,
        'colore' => $colore,
        'ordinamento' => $newOrder
    ])) {
        // Redirect to new project
        $newId = $pdo->lastInsertId();

        // Add owner to project_members
        $stmtMember = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'owner')");
        $stmtMember->execute([$newId, $user_id]);

        header("Location: index.php?project_id=" . $newId);
        exit();
    }
}
?>