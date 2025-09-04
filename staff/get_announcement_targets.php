<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if (isset($_GET['id'])) {
    $announcementId = intval($_GET['id']);
    $staffId = $_SESSION['user']['id'];
    
    try {
        // Verify the announcement belongs to the current staff
        $stmt = $pdo->prepare("SELECT id FROM sitio1_announcements WHERE id = ? AND staff_id = ?");
        $stmt->execute([$announcementId, $staffId]);
        
        if ($stmt->rowCount() > 0) {
            // Get target users
            $stmt = $pdo->prepare("SELECT user_id FROM announcement_targets WHERE announcement_id = ?");
            $stmt->execute([$announcementId]);
            $targets = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['success' => true, 'targets' => $targets]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}