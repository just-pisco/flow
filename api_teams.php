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
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Helper to check Global Role
function getGlobalRole($pdo, $userId)
{
    $stmt = $pdo->prepare("SELECT global_role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: 'user';
}

// Helper to check Team Role
function getTeamRole($pdo, $teamId, $userId)
{
    $stmt = $pdo->prepare("SELECT role FROM team_members WHERE team_id = ? AND user_id = ?");
    $stmt->execute([$teamId, $userId]);
    return $stmt->fetchColumn();
}

try {
    $globalRole = getGlobalRole($pdo, $currentUserId);

    if ($method === 'GET') {
        if ($action === 'list_teams') {
            if ($globalRole !== 'superadmin') {
                throw new Exception("Accesso negato.");
            }
            $stmt = $pdo->query("SELECT t.*, u.username as owner_name, 
                                (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id) as member_count 
                                FROM teams t 
                                JOIN users u ON t.created_by = u.id");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'my_team') {
            // Get the team(s) the user belongs to. For now assuming single primary team or list all.
            $stmt = $pdo->prepare("
                SELECT t.*, tm.role as my_role 
                FROM teams t 
                JOIN team_members tm ON t.id = tm.team_id 
                WHERE tm.user_id = ?
            ");
            $stmt->execute([$currentUserId]);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // For each team, get members if I am admin? Or always?
            // Let's return just team info here.
            echo json_encode(['success' => true, 'data' => $teams]);

        } elseif ($action === 'team_members') {
            $teamId = $_GET['team_id'];
            // Check if I am member of this team
            $myRole = getTeamRole($pdo, $teamId, $currentUserId);
            if (!$myRole && $globalRole !== 'superadmin') {
                throw new Exception("Non fai parte di questo team.");
            }

            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, u.nome, u.cognome, tm.role, tm.joined_at 
                FROM team_members tm 
                JOIN users u ON tm.user_id = u.id 
                WHERE tm.team_id = ?
            ");
            $stmt->execute([$teamId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'create_team') {
            if ($globalRole !== 'superadmin') {
                throw new Exception("Solo i Superadmin possono creare team.");
            }
            $name = trim($input['name']);
            if (empty($name))
                throw new Exception("Nome team obbligatorio.");

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO teams (name, created_by) VALUES (?, ?)");
            $stmt->execute([$name, $currentUserId]);
            $teamId = $pdo->lastInsertId();

            // Add Creator as Admin
            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$teamId, $currentUserId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'data' => ['id' => $teamId]]);

        } elseif ($action === 'create_user_in_team') {
            // Team Admin creates a user and adds them to their team
            $teamId = $input['team_id'];
            $username = trim($input['username']);
            $email = trim($input['email']);
            $email = empty($email) ? null : $email; // Convert empty string to null for DB uniqueness
            $nome = isset($input['nome']) ? trim($input['nome']) : null;
            $cognome = isset($input['cognome']) ? trim($input['cognome']) : null;
            $password = $input['password'];

            // Check permissions
            $myRole = getTeamRole($pdo, $teamId, $currentUserId);
            if ($myRole !== 'admin' && $globalRole !== 'superadmin') {
                throw new Exception("Non hai i permessi di amministrazione per questo team.");
            }

            if (empty($username) || empty($password)) {
                throw new Exception("Username e password obbligatori.");
            }

            $role = isset($input['role']) && in_array($input['role'], ['membr', 'admin']) ? $input['role'] : 'member';

            // Validate Role Assignment Permission
            // If I am Team Admin, I can assign 'admin' or 'member'. (User said "pari o al di sotto").
            // Since there is no role higher than admin in team context (owner is just creator, logicly admin), this is fine.

            $pdo->beginTransaction();

            // Create User
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                throw new Exception("Username già esistente.");
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, nome, cognome) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed, $nome, $cognome]);
            $newUserId = $pdo->lastInsertId();

            // Add to Team
            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, ?)");
            $stmt->execute([$teamId, $newUserId, $role]);

            $pdo->commit();
            echo json_encode(['success' => true]);

        } elseif ($action === 'remove_team_member') {
            $teamId = $input['team_id'];
            $targetUserId = $input['user_id'];

            // Permissions
            $myRole = getTeamRole($pdo, $teamId, $currentUserId);
            if ($myRole !== 'admin' && $globalRole !== 'superadmin') {
                throw new Exception("Non hai i permessi.");
            }

            // Cannot remove yourself? Or warn? Let's allow but maybe frontend warns.

            $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$teamId, $targetUserId]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'update_team_member') {
            $teamId = $input['team_id'];
            $targetUserId = $input['user_id'];
            $role = in_array($input['role'], ['member', 'admin']) ? $input['role'] : 'member';

            // Permissions
            $myRole = getTeamRole($pdo, $teamId, $currentUserId);
            if ($myRole !== 'admin' && $globalRole !== 'superadmin') {
                throw new Exception("Non hai i permessi.");
            }

            $nome = isset($input['nome']) ? trim($input['nome']) : null;
            $cognome = isset($input['cognome']) ? trim($input['cognome']) : null;
            $email = trim($input['email']);
            $email = empty($email) ? null : $email;

            $pdo->beginTransaction();

            // Update User Profile
            $sql = "UPDATE users SET nome = ?, cognome = ?, email = ? WHERE id = ?";
            $params = [$nome, $cognome, $email, $targetUserId];

            // Password update if provided
            if (!empty($input['password'])) {
                $sql = "UPDATE users SET nome = ?, cognome = ?, email = ?, password = ? WHERE id = ?";
                $params = [$nome, $cognome, $email, password_hash($input['password'], PASSWORD_DEFAULT), $targetUserId];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Update Role
            $stmt = $pdo->prepare("UPDATE team_members SET role = ? WHERE team_id = ? AND user_id = ?");
            $stmt->execute([$role, $teamId, $targetUserId]);

            $pdo->commit();
            echo json_encode(['success' => true]);

        } elseif ($action === 'add_existing_user') {
            // Superadmin adding user to team
            if ($globalRole !== 'superadmin') {
                throw new Exception("Solo i Superadmin possono mappare utenti esistenti.");
            }
            $teamId = $input['team_id'];
            $username = trim($input['username']);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $userId = $stmt->fetchColumn();

            if (!$userId)
                throw new Exception("Utente non trovato.");

            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role) VALUES (?, ?, 'member')");
            $stmt->execute([$teamId, $userId]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'delete_team') {
            if ($globalRole !== 'superadmin') {
                throw new Exception("Solo i Superadmin possono eliminare team.");
            }
            $id = $input['id'];

            $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>