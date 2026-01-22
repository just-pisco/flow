<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nome_progetto'])) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $nome = $_POST['nome_progetto'];
    $user_id = $_SESSION['user_id'];

    $sql = "INSERT INTO projects (nome, user_id) VALUES (:nome, :user_id)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute(['nome' => $nome, 'user_id' => $user_id])) {
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