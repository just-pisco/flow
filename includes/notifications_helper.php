<?php
require_once 'includes/db.php';

class NotificationManager
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function addNotification($userId, $type, $message, $link = null)
    {
        try {
            // Verifica se la tabella esiste (fallback soft)
            // In produzione si assume esista grazie a setup_prod.php

            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, message, link) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $type, $message, $link]);
            return true;
        } catch (Exception $e) {
            // Log error but don't crash main flow
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }

    public function markAsRead($notificationId, $userId)
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    }

    public function markAllAsRead($userId)
    {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    public function getUnread($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>