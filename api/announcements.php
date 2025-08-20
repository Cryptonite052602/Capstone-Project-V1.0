<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $pdo;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get announcements for user
        if (isUser()) {
            $userId = $_SESSION['user']['id'];
            
            // Get unread announcements
            $stmt = $pdo->prepare("
                SELECT a.* 
                FROM sitio1_announcements a
                LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ?
                WHERE ua.id IS NULL
                ORDER BY a.post_date DESC
            ");
            $stmt->execute([$userId]);
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['announcements' => $announcements]);
        } 
        // Get announcement stats for staff/admin
        elseif (isStaff() || isAdmin()) {
            $announcementId = $_GET['id'] ?? null;
            
            if ($announcementId) {
                // Get detailed responses for a specific announcement
                $stmt = $pdo->prepare("
                    SELECT u.full_name, ua.status, ua.updated_at
                    FROM user_announcements ua
                    JOIN sitio1_users u ON ua.user_id = u.id
                    WHERE ua.announcement_id = ?
                    ORDER BY ua.updated_at DESC
                ");
                $stmt->execute([$announcementId]);
                $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['responses' => $responses]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Announcement ID is required']);
            }
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // User responding to announcement
        if (isUser()) {
            $data = json_decode(file_get_contents('php://input'), true);
            $announcementId = $data['announcement_id'] ?? null;
            $status = $data['status'] ?? null;
            
            if ($announcementId && in_array($status, ['accepted', 'dismissed'])) {
                $userId = $_SESSION['user']['id'];
                
                // Check if response already exists
                $stmt = $pdo->prepare("SELECT id FROM user_announcements WHERE user_id = ? AND announcement_id = ?");
                $stmt->execute([$userId, $announcementId]);
                
                if ($stmt->fetch()) {
                    // Update existing response
                    $stmt = $pdo->prepare("UPDATE user_announcements SET status = ? WHERE user_id = ? AND announcement_id = ?");
                    $stmt->execute([$status, $userId, $announcementId]);
                } else {
                    // Insert new response
                    $stmt = $pdo->prepare("INSERT INTO user_announcements (user_id, announcement_id, status) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $announcementId, $status]);
                }
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid data']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>