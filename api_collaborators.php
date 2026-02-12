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

try {
    if ($method === 'GET') {
        if ($action === 'search_global') {
            // Search ANY user to add as friend
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 3) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            // Exclude myself and already friends/pending?
            // For now just partial match username
            $stmt = $pdo->prepare("SELECT id, username, nome, cognome, profile_image FROM users WHERE username LIKE ? AND id != ? LIMIT 10");
            $stmt->execute(["%$query%", $currentUserId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrich with status (collaborator, pending, none)
            foreach ($results as &$user) {
                $stmtStatus = $pdo->prepare("
                    SELECT status, requester_id FROM collaborations 
                    WHERE (requester_id = ? AND receiver_id = ?) 
                       OR (requester_id = ? AND receiver_id = ?)
                ");
                $stmtStatus->execute([$currentUserId, $user['id'], $user['id'], $currentUserId]);
                $collaboration = $stmtStatus->fetch(PDO::FETCH_ASSOC);

                if ($collaboration) {
                    $user['collaboration_status'] = $collaboration['status'];
                    $user['is_requester'] = ($collaboration['requester_id'] == $currentUserId);
                } else {
                    $user['collaboration_status'] = 'none';
                }
            }

            echo json_encode(['success' => true, 'data' => $results]);

        } elseif ($action === 'list_collaborators') {
            // Get accepted collaborators
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.nome, u.cognome, u.profile_image
                FROM collaborations c
                JOIN users u ON (c.requester_id = u.id OR c.receiver_id = u.id)
                WHERE (c.requester_id = ? OR c.receiver_id = ?) 
                  AND c.status = 'accepted'
                  AND u.id != ?
            ");
            $stmt->execute([$currentUserId, $currentUserId, $currentUserId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'list_requests') {
            // Incoming pending requests
            $stmt = $pdo->prepare("
                SELECT c.id as collaboration_id, u.id as user_id, u.username, u.nome, u.cognome, u.profile_image, c.created_at
                FROM collaborations c
                JOIN users u ON c.requester_id = u.id
                WHERE c.receiver_id = ? AND c.status = 'pending'
            ");
            $stmt->execute([$currentUserId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'pending_count') {
            // Count pending incoming requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM collaborations WHERE receiver_id = ? AND status = 'pending'");
            $stmt->execute([$currentUserId]);
            echo json_encode(['success' => true, 'count' => $stmt->fetchColumn()]);
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'send_request') {
            $targetUserId = $input['user_id'];
            if ($targetUserId == $currentUserId)
                throw new Exception("Non puoi aggiungerti da solo.");

            // Check existence
            $stmt = $pdo->prepare("
                SELECT id FROM collaborations 
                WHERE (requester_id = ? AND receiver_id = ?) 
                   OR (requester_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$currentUserId, $targetUserId, $targetUserId, $currentUserId]);
            if ($stmt->fetch()) {
                throw new Exception("Richiesta già esistente o siete già collaboratori.");
            }

            $stmt = $pdo->prepare("INSERT INTO collaborations (requester_id, receiver_id) VALUES (?, ?)");
            $stmt->execute([$currentUserId, $targetUserId]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'respond_request') {
            $collaborationId = $input['collaboration_id'];
            $decision = $input['decision']; // 'accept' or 'decline'

            // Verify I am the receiver
            $stmt = $pdo->prepare("SELECT receiver_id FROM collaborations WHERE id = ? AND status = 'pending'");
            $stmt->execute([$collaborationId]);
            $receiver = $stmt->fetchColumn();

            if ($receiver != $currentUserId) {
                throw new Exception("Richiesta non trovata o non valida.");
            }

            if ($decision === 'accept') {
                $stmt = $pdo->prepare("UPDATE collaborations SET status = 'accepted' WHERE id = ?");
                $stmt->execute([$collaborationId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM collaborations WHERE id = ?");
                $stmt->execute([$collaborationId]);
            }

            echo json_encode(['success' => true]);

        } elseif ($action === 'remove_collaborator') {
            $friendUserId = $input['user_id']; // renamed in js call

            // Delete collaboration where I am requester OR receiver, and other is $friendUserId
            $stmt = $pdo->prepare("
                DELETE FROM collaborations 
                WHERE (requester_id = ? AND receiver_id = ?) 
                   OR (requester_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$currentUserId, $friendUserId, $friendUserId, $currentUserId]);

            echo json_encode(['success' => true]);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>