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

            // Enrich with status (friend, pending, none)
            foreach ($results as &$user) {
                $stmtStatus = $pdo->prepare("
                    SELECT status, requester_id FROM friendships 
                    WHERE (requester_id = ? AND receiver_id = ?) 
                       OR (requester_id = ? AND receiver_id = ?)
                ");
                $stmtStatus->execute([$currentUserId, $user['id'], $user['id'], $currentUserId]);
                $friendship = $stmtStatus->fetch(PDO::FETCH_ASSOC);

                if ($friendship) {
                    $user['friendship_status'] = $friendship['status'];
                    $user['is_requester'] = ($friendship['requester_id'] == $currentUserId);
                } else {
                    $user['friendship_status'] = 'none';
                }
            }

            echo json_encode(['success' => true, 'data' => $results]);

        } elseif ($action === 'list_friends') {
            // Get accepted friends
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.nome, u.cognome, u.profile_image
                FROM friendships f
                JOIN users u ON (f.requester_id = u.id OR f.receiver_id = u.id)
                WHERE (f.requester_id = ? OR f.receiver_id = ?) 
                  AND f.status = 'accepted'
                  AND u.id != ?
            ");
            $stmt->execute([$currentUserId, $currentUserId, $currentUserId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'list_requests') {
            // Incoming pending requests
            $stmt = $pdo->prepare("
                SELECT f.id as friendship_id, u.id as user_id, u.username, u.nome, u.cognome, u.profile_image, f.created_at
                FROM friendships f
                JOIN users u ON f.requester_id = u.id
                WHERE f.receiver_id = ? AND f.status = 'pending'
            ");
            $stmt->execute([$currentUserId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($action === 'pending_count') {
            // Count pending incoming requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM friendships WHERE receiver_id = ? AND status = 'pending'");
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
                SELECT id FROM friendships 
                WHERE (requester_id = ? AND receiver_id = ?) 
                   OR (requester_id = ? AND receiver_id = ?)
            ");
            $stmt->execute([$currentUserId, $targetUserId, $targetUserId, $currentUserId]);
            if ($stmt->fetch()) {
                throw new Exception("Richiesta già esistente o siete già amici.");
            }

            $stmt = $pdo->prepare("INSERT INTO friendships (requester_id, receiver_id) VALUES (?, ?)");
            $stmt->execute([$currentUserId, $targetUserId]);

            echo json_encode(['success' => true]);

        } elseif ($action === 'respond_request') {
            $friendshipId = $input['friendship_id'];
            $decision = $input['decision']; // 'accept' or 'decline'

            // Verify I am the receiver
            $stmt = $pdo->prepare("SELECT receiver_id FROM friendships WHERE id = ? AND status = 'pending'");
            $stmt->execute([$friendshipId]);
            $receiver = $stmt->fetchColumn();

            if ($receiver != $currentUserId) {
                throw new Exception("Richiesta non trovata o non valida.");
            }

            if ($decision === 'accept') {
                $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ?");
                $stmt->execute([$friendshipId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM friendships WHERE id = ?");
                $stmt->execute([$friendshipId]);
            }

            echo json_encode(['success' => true]);

        } elseif ($action === 'remove_friend') {
            $friendUserId = $input['friend_user_id'];

            // Delete friendship where I am requester OR receiver, and other is $friendUserId
            $stmt = $pdo->prepare("
                DELETE FROM friendships 
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