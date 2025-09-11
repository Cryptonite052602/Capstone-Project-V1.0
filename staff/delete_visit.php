<?php
// staff/delete_visit.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
redirectIfNotLoggedIn();

if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $_DELETE);
    $visitId = isset($_DELETE['id']) ? intval($_DELETE['id']) : 0;
    
    if ($visitId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
        exit();
    }
    
    try {
        // Check if the visit belongs to a patient that the staff member has access to
        $stmt = $pdo->prepare("SELECT pv.id 
                              FROM patient_visits pv 
                              JOIN sitio1_patients p ON pv.patient_id = p.id 
                              WHERE pv.id = ? AND p.added_by = ?");
        $stmt->execute([$visitId, $_SESSION['user']['id']]);
        $visit = $stmt->fetch();
        
        if (!$visit) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this visit']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM patient_visits WHERE id = ?");
        $stmt->execute([$visitId]);
        
        echo json_encode(['success' => true, 'message' => 'Visit record deleted successfully']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error deleting visit: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>