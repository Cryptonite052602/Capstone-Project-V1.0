<?php
/**
 * Patient Visit Edit Mode Handler
 * This file handles the display and editing of patient visit information
 * with toggle functionality between view and edit modes
 */

require_once __DIR__ . '/../includes/auth.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

// Get patient ID and visit ID from request
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$visitId = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

if ($patientId <= 0 || $visitId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient or visit ID']);
    exit();
}

try {
    // Get visit details
    $stmt = $pdo->prepare("SELECT pv.*, p.full_name as patient_name
                          FROM patient_visits pv 
                          LEFT JOIN sitio1_patients p ON pv.patient_id = p.id
                          WHERE pv.id = ? AND pv.patient_id = ?");
    $stmt->execute([$visitId, $patientId]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        echo json_encode(['success' => false, 'message' => 'Visit record not found']);
        exit();
    }
    
    // Return visit data in editable format
    echo json_encode([
        'success' => true,
        'visit' => $visit
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving visit data: ' . $e->getMessage()
    ]);
}
?>
