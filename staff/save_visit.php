<?php
// staff/save_visit.php
require_once __DIR__ . '/../includes/auth.php';

redirectIfNotLoggedIn();

if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $action = $_POST['action'] ?? 'add';
        $patientId = intval($_POST['patient_id']);
        $staffId = $_SESSION['user']['id'];
        
        // Debug: Log received data
        error_log("Received visit data: " . print_r($_POST, true));
        
        // Validate required fields
        if (empty($_POST['visit_date']) || empty($_POST['visit_type'])) {
            throw new Exception("Visit date and type are required. Received: " . print_r($_POST, true));
        }
        
        // Validate visit type
        $allowedVisitTypes = ['checkup', 'consultation', 'emergency', 'followup'];
        $visitType = $_POST['visit_type'];
        if (!in_array($visitType, $allowedVisitTypes)) {
            throw new Exception("Invalid visit type: " . $visitType);
        }
        
        $pdo->beginTransaction();
        
        // Prepare visit data - map all fields exactly as they appear in the database
        $visitData = [
            'patient_id' => $patientId,
            'staff_id' => $staffId,
            'visit_date' => $_POST['visit_date'],
            'visit_type' => $visitType,
            'symptoms' => $_POST['symptoms'] ?? null,
            'vital_signs' => $_POST['vital_signs'] ?? null,
            'diagnosis' => $_POST['diagnosis'] ?? null,
            'treatment' => $_POST['treatment'] ?? null,
            'prescription' => $_POST['prescription'] ?? null,
            'referral_info' => $_POST['referral_info'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'next_visit_date' => !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null,
            'visit_purpose' => $_POST['visit_purpose'] ?? null // Add this if you have it in form
        ];
        
        if ($action === 'add') {
            // Insert new visit with ALL fields that match the database table
            $stmt = $pdo->prepare("INSERT INTO patient_visits 
                (patient_id, staff_id, visit_date, visit_type, symptoms, vital_signs,
                 diagnosis, treatment, prescription, referral_info, notes, next_visit_date, visit_purpose) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $result = $stmt->execute([
                $visitData['patient_id'],
                $visitData['staff_id'],
                $visitData['visit_date'],
                $visitData['visit_type'],
                $visitData['symptoms'],
                $visitData['vital_signs'],
                $visitData['diagnosis'],
                $visitData['treatment'],
                $visitData['prescription'],
                $visitData['referral_info'],
                $visitData['notes'],
                $visitData['next_visit_date'],
                $visitData['visit_purpose']
            ]);
            
            if (!$result) {
                throw new Exception("Failed to execute INSERT query");
            }
            
            $visitId = $pdo->lastInsertId();
            error_log("Successfully inserted visit with ID: " . $visitId);
            
        } elseif ($action === 'edit' && !empty($_POST['visit_id'])) {
            // Update existing visit
            $visitId = intval($_POST['visit_id']);
            
            $stmt = $pdo->prepare("UPDATE patient_visits SET 
                visit_date = ?, visit_type = ?, symptoms = ?, vital_signs = ?,
                diagnosis = ?, treatment = ?, prescription = ?, referral_info = ?,
                notes = ?, next_visit_date = ?, visit_purpose = ?, updated_at = NOW()
                WHERE id = ? AND staff_id = ?");
                
            $result = $stmt->execute([
                $visitData['visit_date'],
                $visitData['visit_type'],
                $visitData['symptoms'],
                $visitData['vital_signs'],
                $visitData['diagnosis'],
                $visitData['treatment'],
                $visitData['prescription'],
                $visitData['referral_info'],
                $visitData['notes'],
                $visitData['next_visit_date'],
                $visitData['visit_purpose'],
                $visitId,
                $staffId
            ]);
            
            if (!$result) {
                throw new Exception("Failed to execute UPDATE query");
            }
            
            error_log("Successfully updated visit with ID: " . $visitId);
        } else {
            throw new Exception("Invalid action or missing visit ID");
        }
        
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Visit ' . ($action === 'add' ? 'recorded' : 'updated') . ' successfully!';
        
        // Prepare data for success modal
        $response['data'] = [
            'visit_id' => $visitId,
            'visit_date' => date('M j, Y g:i A', strtotime($visitData['visit_date'])),
            'visit_type' => ucfirst($visitData['visit_type']),
            'diagnosis' => $visitData['diagnosis'] ?? 'Not specified',
            'next_visit_date' => $visitData['next_visit_date'] ? date('M j, Y', strtotime($visitData['next_visit_date'])) : 'Not scheduled'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = 'Error saving visit: ' . $e->getMessage();
        $response['message'] = $errorMessage;
        error_log($errorMessage);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>