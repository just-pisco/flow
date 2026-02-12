<?php
require_once 'includes/db.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list_sidebar') {
        // Same logic as in sidebar.php template
        $stmt = $pdo->prepare("
            SELECT p.id, p.nome, p.colore, pm.role 
            FROM projects p 
            JOIN project_members pm ON p.id = pm.project_id 
            WHERE pm.user_id = ? 
            ORDER BY p.ordinamento ASC, p.data_modifica DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $projects]);

    } elseif ($action === 'get_details') {
        $projectId = $_GET['project_id'] ?? 0;

        // Check access
        $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $_SESSION['user_id']]);
        $role = $stmt->fetchColumn();

        if (!$role) {
            throw new Exception("Accesso negato.");
        }

        // Get details (include colore)
        $stmt = $pdo->prepare("SELECT id, nome, descrizione, colore, data_creazione as created_at FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get members (re-use logic or fetch here?)
        // Client might verify access via members list API call, but let's include basic count or rely on members API.
        // Let's stick to project core data.

        // Get Attachments
        $stmtParams = $pdo->prepare("SELECT * FROM project_attachments WHERE project_id = ? ORDER BY created_at DESC");
        $stmtParams->execute([$projectId]);
        $attachments = $stmtParams->fetchAll(PDO::FETCH_ASSOC);

        $project['role'] = $role; // Return user's role too
        $project['attachments'] = $attachments;

        echo json_encode(['success' => true, 'data' => $project]);

    } elseif ($action === 'update_details') {
        $input = json_decode(file_get_contents('php://input'), true);
        $projectId = $input['project_id'];
        $nome = trim($input['nome']);
        $descrizione = trim($input['descrizione']);
        $colore = isset($input['colore']) ? trim($input['colore']) : '#6366f1';

        // Check Owner/Admin role?
        // Let's allow editors too? Usually only admins change project settings.
        $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $_SESSION['user_id']]);
        $role = $stmt->fetchColumn();

        if ($role !== 'owner') { // Only owner for now
            throw new Exception("Solo il proprietario può modificare i dettagli.");
        }

        $stmt = $pdo->prepare("UPDATE projects SET nome = ?, descrizione = ?, colore = ?, data_modifica = NOW() WHERE id = ?");
        $stmt->execute([$nome, $descrizione, $colore, $projectId]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'add_attachment') {
        $input = json_decode(file_get_contents('php://input'), true);
        $projectId = $input['project_id'];
        $type = $input['type']; // 'link' or 'drive_file'
        $name = trim($input['name']);
        $url = trim($input['url']);

        // Check access (any member can add attachments?) Yes, typically.
        $stmt = $pdo->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $_SESSION['user_id']]);
        if (!$stmt->fetch())
            throw new Exception("Accesso negato.");

        $stmt = $pdo->prepare("INSERT INTO project_attachments (project_id, user_id, type, name, url) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$projectId, $_SESSION['user_id'], $type, $name, $url]);

        echo json_encode(['success' => true]);

    } elseif ($action === 'delete_attachment') {
        $input = json_decode(file_get_contents('php://input'), true);
        $attachId = $input['id'];

        // Get attachment info to verify permissions
        $stmt = $pdo->prepare("SELECT project_id, user_id FROM project_attachments WHERE id = ?");
        $stmt->execute([$attachId]);
        $attach = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attach)
            throw new Exception("Allegato non trovato.");

        // Check if user is owner of attachment OR owner of project
        $stmtRole = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmtRole->execute([$attach['project_id'], $_SESSION['user_id']]);
        $role = $stmtRole->fetchColumn();

        if ($attach['user_id'] != $_SESSION['user_id'] && $role !== 'owner') {
            throw new Exception("Non puoi eliminare questo allegato.");
        }

        $stmt = $pdo->prepare("DELETE FROM project_attachments WHERE id = ?");
        $stmt->execute([$attachId]);

        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>