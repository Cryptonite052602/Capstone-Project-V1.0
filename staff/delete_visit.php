<?php
// delete_visit.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $visitId = $_GET['id'] ?? 0;
    
    if (empty($visitId)) {
        echo json_encode(['success' => false, 'message' => 'Invalid visit ID']);
        exit();
    }
    
    try {
        // Verify the visit belongs to a patient owned by the current user
        $stmt = $pdo->prepare("SELECT pv.id 
                              FROM patient_visits pv 
                              JOIN sitio1_patients p ON pv.patient_id = p.id 
                              WHERE pv.id = ? AND p.added_by = ?");
        $stmt->execute([$visitId, $_SESSION['user']['id']]);
        $visit = $stmt->fetch();
        
        if (!$visit) {
            echo json_encode(['success' => false, 'message' => 'Visit not found or access denied']);
            exit();
        }
        
        // Delete the visit from patient_visits table
        $stmt = $pdo->prepare("DELETE FROM patient_visits WHERE id = ?");
        $stmt->execute([$visitId]);
        
        echo json_encode(['success' => true, 'message' => 'Visit record deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting visit: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>