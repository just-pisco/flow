<?php
require_once 'includes/db.php';
session_start();
$user_id = $_SESSION['user_id'] ?? 0;

echo "User ID: $user_id<br>";

// 1. Check Assignments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM task_assignments WHERE user_id = ?");
$stmt->execute([$user_id]);
echo "Assignments Count: " . $stmt->fetchColumn() . "<br>";

// 2. Check Tasks with Deadlines assigned to user
$stmt = $pdo->prepare("
    SELECT t.id, t.titolo, t.scadenza, t.stato 
    FROM tasks t
    JOIN task_assignments ta ON t.id = ta.task_id
    WHERE ta.user_id = ?
");
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($tasks);
echo "</pre>";

// 3. Check Tasks created in projects where user is member (Potential Alternative View)
echo "<h3>All Tasks in My Projects (assigned or not):</h3>";
$stmt = $pdo->prepare("
    SELECT t.id, t.titolo, t.scadenza 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN project_members pm ON p.id = pm.project_id
    WHERE pm.user_id = ? AND t.scadenza IS NOT NULL
    LIMIT 5
");
$stmt->execute([$user_id]);
$allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($allTasks);
echo "</pre>";
?>