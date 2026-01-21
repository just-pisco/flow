<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'search_users') {
            // Search users by partial username to invite
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 3) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username LIKE ? AND id != ? LIMIT 10");
            $stmt->execute(["%$query%", $currentUserId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'list_members') {
            $projectId = $_GET['project_id'] ?? 0;

            // Verify access (must be owner or member)
            // Actually, any member should see other members
            $stmt = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $currentUserId]);
            if (!$stmt->fetch()) {
                // Check if actually owner (though migration added owners to members, let's be safe)
                // Or maybe just check if user is associated.
                // Let's assume strict check:
                // But wait, if migration ran, owner IS a member.
                // If row not found, unauthorized.
                throw new Exception("Accesso negato al progetto o progetto inesistente.");
            }

            // Fetch members with roles
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, pm.role 
                FROM project_members pm
                JOIN users u ON pm.user_id = u.id
                WHERE pm.project_id = ?
                ORDER BY pm.role='owner' DESC, u.username ASC
            ");
            $stmt->execute([$projectId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'add_member') {
            $projectId = $input['project_id'];
            $username = trim($input['username']);

            // 1. Verify Requestor is Owner (Only owner can add members?) 
            // Or editors? Let's restrict to Owner for now for simplicity/security.
            $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $currentUserId]);
            $role = $stmt->fetchColumn();

            if ($role !== 'owner') {
                throw new Exception("Solo il proprietario può aggiungere membri.");
            }

            // 2. Find User ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $newUserId = $stmt->fetchColumn();

            if (!$newUserId) {
                throw new Exception("Utente non trovato.");
            }

            // 3. Add to Project
            // Check if already member
            $stmt = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $newUserId]);
            if ($stmt->fetch()) {
                throw new Exception("L'utente è già membro del progetto.");
            }

            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'editor')");
            $stmt->execute([$projectId, $newUserId]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'remove_member') {
            $projectId = $input['project_id'];
            $targetUserId = $input['user_id'];

            // 1. Verify Requestor is Owner
            $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $currentUserId]);
            $role = $stmt->fetchColumn();

            if ($role !== 'owner') {
                // Allow user to leave themselves?
                if ($targetUserId != $currentUserId) {
                    throw new Exception("Solo il proprietario può rimuovere membri.");
                }
            }

            // Cannot remove owner
            // Check target role
            $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $targetUserId]);
            $targetRole = $stmt->fetchColumn();

            if ($targetRole === 'owner') {
                throw new Exception("Non puoi rimuovere il proprietario.");
            }

            // Remove
            $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?");
            $stmt->execute([$projectId, $targetUserId]);

            // Also remove from task assignments in this project?
            // Technically yes, or integrity constraints will cascade if we structured it that way.
            // Our Schema: task_assignments -> task -> delete cascade.
            // But we are not deleting the task, just the member relation.
            // If we assume a user must be a member to be assigned, we should probably cleanup assignments.
            // But strict FK is on users(id), not project_members. So assignments technically stay valid DB-wise
            // but semantically invalid.
            // Let's cleanup assignments for tasks in this project.
            $sqlCleanup = "DELETE ta FROM task_assignments ta 
                           JOIN tasks t ON ta.task_id = t.id 
                           WHERE t.project_id = ? AND ta.user_id = ?";
            $stmt = $pdo->prepare($sqlCleanup);
            $stmt->execute([$projectId, $targetUserId]);

            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>