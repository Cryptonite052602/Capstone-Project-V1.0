<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'] ?? 0;
    $visit_date = $_POST['visit_date'] ?? '';
    $visit_type = $_POST['visit_type'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $prescription = $_POST['prescription'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $next_visit_date = $_POST['next_visit_date'] ?? null;
    
    // Validate required fields
    if (empty($patient_id) || empty($visit_date) || empty($visit_type)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    try {
        // Check if patient exists and belongs to the current user
        $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
        $stmt->execute([$patient_id, $_SESSION['user']['id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            echo json_encode(['success' => false, 'message' => 'Patient not found']);
            exit();
        }
        
        // Insert the visit record into patient_visits table
        $stmt = $pdo->prepare("INSERT INTO patient_visits 
                              (patient_id, staff_id, visit_date, visit_type, diagnosis, treatment, prescription, notes, next_visit_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $patient_id,
            $_SESSION['user']['id'], // staff_id from session
            $visit_date,
            $visit_type,
            $diagnosis,
            $treatment,
            $prescription,
            $notes,
            $next_visit_date ?: null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Visit record added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving visit: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>