<?php
require_once 'includes/db.php';
require_once 'includes/GoogleDriveHelper.php';

// Configurazione Error Log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_drive_error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$helper = new GoogleDriveHelper($pdo);

try {
    if ($action === 'upload_file') {
        // Handle Multipart Upload
        $taskId = !empty($_POST['task_id']) ? $_POST['task_id'] : null;
        $projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;

        if ((!$taskId && !$projectId) || !isset($_FILES['file'])) {
            throw new Exception("Missing parameters (task_id/project_id) or file");
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code " . $file['error']);
        }

        $tmpPath = $file['tmp_name'];
        $fileName = $file['name'];
        $mimeType = $file['type'];

        // 1. Find/Create Folder
        $folderId = $helper->findOrCreateFolder($currentUserId, 'Flow.');

        // 2. Upload to Drive
        $driveFile = $helper->uploadFile($currentUserId, $tmpPath, $fileName, $mimeType, $folderId);
        $fileId = $driveFile['id'];
        $webViewLink = $driveFile['webViewLink'];
        $iconLink = $driveFile['iconLink'] ?? '';

        // 3. Set Permissions (Anyone Reader)
        try {
            $helper->setPublicPermission($currentUserId, $fileId);
        } catch (Exception $e) {
            error_log("Permission Warning: " . $e->getMessage());
        }

        // 4. Save to DB
        $stmt = $pdo->prepare("INSERT INTO attachments 
            (task_id, project_id, user_id, file_provider, external_file_id, file_name, file_url, mime_type, icon_url)
            VALUES (?, ?, ?, 'google_drive', ?, ?, ?, ?, ?)");

        $stmt->execute([
            $taskId,
            $projectId,
            $currentUserId,
            $fileId,
            $fileName,
            $webViewLink,
            $mimeType,
            $iconLink
        ]);

        echo json_encode([
            'success' => true,
            'file' => [
                'name' => $fileName,
                'url' => $webViewLink
            ]
        ]);

    } elseif ($action === 'get_attachments') {
        $taskId = $_GET['task_id'] ?? null;
        $projectId = $_GET['project_id'] ?? null;

        if ($taskId) {
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE task_id = ? ORDER BY created_at DESC");
            $stmt->execute([$taskId]);
        } elseif ($projectId) {
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE project_id = ? ORDER BY created_at DESC");
            $stmt->execute([$projectId]);
        } else {
            throw new Exception("Missing context ID");
        }

        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'files' => $files]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
