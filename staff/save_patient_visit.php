<?php
// save_patient_data.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_data'])) {
    $patient_id = $_POST['patient_id'] ?? 0;
    
    // Validate required health fields
    $requiredHealthFields = ['height', 'weight', 'blood_type'];
    $missingHealthFields = [];
    
    foreach ($requiredHealthFields as $field) {
        if (empty($_POST[$field])) {
            $missingHealthFields[] = $field;
        }
    }
    
    // Validate required visit fields
    $requiredVisitFields = ['visit_date', 'visit_type'];
    $missingVisitFields = [];
    
    foreach ($requiredVisitFields as $field) {
        if (empty($_POST[$field])) {
            $missingVisitFields[] = $field;
        }
    }
    
    // Get patient gender from database if not provided in form
    $gender = $_POST['gender'] ?? '';
    if (empty($gender)) {
        try {
            $stmt = $pdo->prepare("SELECT gender FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient && !empty($patient['gender'])) {
                $gender = $patient['gender'];
            }
        } catch (PDOException $e) {
            // If we can't get gender from database, add it to missing fields
            $missingHealthFields[] = 'gender';
        }
    }
    
    if (!empty($missingHealthFields)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required health fields: ' . implode(', ', $missingHealthFields)]);
        exit();
    }
    
    if (!empty($missingVisitFields)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required visit fields: ' . implode(', ', $missingVisitFields)]);
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Process health information
        $height = $_POST['height'];
        $weight = $_POST['weight'];
        $blood_type = $_POST['blood_type'];
        $allergies = $_POST['allergies'] ?? null;
        $medical_history = $_POST['medical_history'] ?? null;
        $current_medications = $_POST['current_medications'] ?? null;
        $family_history = $_POST['family_history'] ?? null;
        $last_checkup = $_POST['last_checkup'] ?? null;

        // Check if health record exists
        $stmt = $pdo->prepare("SELECT id FROM existing_info_patients WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        
        if ($stmt->fetch()) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                gender = ?, height = ?, weight = ?, blood_type = ?, allergies = ?, 
                medical_history = ?, current_medications = ?, family_history = ?,
                updated_at = NOW()
                WHERE patient_id = ?");
            $stmt->execute([
                $gender, $height, $weight, $blood_type, $allergies,
                $medical_history, $current_medications, $family_history,
                $patient_id
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, blood_type, allergies, 
                medical_history, current_medications, family_history)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patient_id, $gender, $height, $weight, $blood_type, $allergies,
                $medical_history, $current_medications, $family_history
            ]);
        }
        
        // Also update the gender in the main patient table if needed
        if (!empty($gender)) {
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET gender = ? WHERE id = ?");
            $stmt->execute([$gender, $patient_id]);
        }
        
        // Update last checkup date if provided
        if (!empty($last_checkup)) {
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET last_checkup = ? WHERE id = ?");
            $stmt->execute([$last_checkup, $patient_id]);
        }
        
        // Process visit information
        $visit_date = $_POST['visit_date'];
        $visit_type = $_POST['visit_type'];
        $diagnosis = $_POST['diagnosis'] ?? '';
        $treatment = $_POST['treatment'] ?? '';
        $prescription = $_POST['prescription'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $next_visit_date = $_POST['next_visit_date'] ?? null;
        
        // Insert the visit record into patient_visits table
        $stmt = $pdo->prepare("INSERT INTO patient_visits 
                              (patient_id, staff_id, visit_date, visit_type, diagnosis, treatment, prescription, notes, next_visit_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $patient_id,
            $_SESSION['user']['id'], // staff_id from session
            $visit_date,
            $visit_type,
            $diagnosis,
            $treatment,
            $prescription,
            $notes,
            $next_visit_date && $next_visit_date !== '' ? $next_visit_date : null
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Patient health information and visit record saved successfully!']);
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error saving patient data: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error saving data: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>