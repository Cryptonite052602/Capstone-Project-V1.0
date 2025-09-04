<?php
function createAnnouncementNotification($announcementId, $title) {
    global $pdo;
    
    try {
        // Get all approved users
        $stmt = $pdo->prepare("SELECT id FROM sitio1_users WHERE approved = TRUE");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare notification insert
        $notificationStmt = $pdo->prepare("INSERT INTO notifications 
                                         (user_id, type, title, message, related_id, is_read, created_at) 
                                         VALUES (?, 'announcement', ?, ?, ?, 0, NOW())");
        
        // Create notification for each user
        foreach ($users as $user) {
            $notificationStmt->execute([
                $user['id'],
                "New Announcement: " . substr($title, 0, 50) . (strlen($title) > 50 ? "..." : ""),
                "A new community announcement has been posted. Please check your announcements.",
                $announcementId
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function markAnnouncementNotificationsAsRead($announcementId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 
                              WHERE related_id = ? AND type = 'announcement'");
        $stmt->execute([$announcementId]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification update error: " . $e->getMessage());
        return false;
    }
}

function createTargetedAnnouncementNotification($announcementId, $title, $userIds) {
    global $pdo;
    
    try {
        foreach ($userIds as $userId) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id, is_read) 
                                  VALUES (?, 'announcement', ?, ?, ?, 0)");
            $stmt->execute([$userId, 'New Announcement', $title, $announcementId]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error creating targeted announcement notification: " . $e->getMessage());
        return false;
    }
}
?>