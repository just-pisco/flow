<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];

// Check Superadmin Role
$stmt = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$globalRole = $stmt->fetchColumn();

if ($globalRole !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accesso negato. Solo Superadmin.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        if ($action === 'list_users') {
            // List all users with some metadata
            $stmt = $pdo->query("
                SELECT id, username, email, nome, cognome, global_role, data_creazione as created_at,
                (SELECT COUNT(*) FROM team_members WHERE user_id = users.id) as team_count
                FROM users 
                ORDER BY data_creazione DESC
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'get_user_details') {
            $userId = $_GET['user_id'];

            // Get User Info
            $stmt = $pdo->prepare("SELECT id, username, email, nome, cognome, global_role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user)
                throw new Exception("Utente non trovato.");

            // Get All Teams and User's Status in them
            // We want a list of ALL teams, and a flag if user is member, plus their role
            $stmtTeams = $pdo->query("SELECT id, name FROM teams ORDER BY name");
            $allTeams = $stmtTeams->fetchAll(PDO::FETCH_ASSOC);

            $stmtMember = $pdo->prepare("SELECT team_id, role FROM team_members WHERE user_id = ?");
            $stmtMember->execute([$userId]);
            $memberships = $stmtMember->fetchAll(PDO::FETCH_KEY_PAIR); // team_id => role

            foreach ($allTeams as &$team) {
                if (isset($memberships[$team['id']])) {
                    $team['is_member'] = true;
                    $team['role'] = $memberships[$team['id']];
                } else {
                    $team['is_member'] = false;
                    $team['role'] = null;
                }
            }

            echo json_encode(['success' => true, 'data' => ['user' => $user, 'teams' => $allTeams]]);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'update_user') {
            $userId = $input['user_id'];
            $nome = $input['nome'];
            $cognome = $input['cognome'];
            $email = empty($input['email']) ? null : $input['email'];
            $globalRoleInput = $input['global_role']; // 'user' or 'superadmin'

            // Avoid revoking own superadmin if only one exists? Not strict requirement but careful.

            $pdo->beginTransaction();

            $sql = "UPDATE users SET nome = ?, cognome = ?, email = ?, global_role = ? WHERE id = ?";
            $params = [$nome, $cognome, $email, $globalRoleInput, $userId];

            if (!empty($input['password'])) {
                $sql = "UPDATE users SET nome = ?, cognome = ?, email = ?, global_role = ?, password = ? WHERE id = ?";
                $params = [$nome, $cognome, $email, $globalRoleInput, password_hash($input['password'], PASSWORD_DEFAULT), $userId];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Handle Team Assignments
            // Expecting 'teams' array: [{team_id: 1, is_member: true, role: 'admin'}, ...]
            if (isset($input['teams']) && is_array($input['teams'])) {
                foreach ($input['teams'] as $t) {
                    if ($t['is_member']) {
                        // Insert or Update
                        // Check if exists
                        $stmtCheck = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?");
                        $stmtCheck->execute([$t['team_id'], $userId]);
                        if ($stmtCheck->fetch()) {
                            // Update role
                            $stmtUpd = $pdo->prepare("UPDATE team_members SET role = ? WHERE team_id = ? AND user_id = ?");
                            $stmtUpd->execute([$t['role'], $t['team_id'], $userId]);
                        } else {
                            // Insert
                            $stmtIns = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, ?)");
                            $stmtIns->execute([$t['team_id'], $userId, $t['role']]);
                        }
                    } else {
                        // Remove
                        $stmtDel = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
                        $stmtDel->execute([$t['team_id'], $userId]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);

        } elseif ($action === 'delete_user') {
            $userId = $input['user_id'];
            if ($userId == $currentUserId)
                throw new Exception("Non puoi eliminare te stesso.");

            // 1. Get profile image to delete file
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $img = $stmt->fetchColumn();

            if ($img) {
                $filePath = __DIR__ . '/uploads/avatars/' . $img;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // 2. Delete User (Cascade handles relationships)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true]);
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>