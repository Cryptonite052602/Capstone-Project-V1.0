<?php
/**
 * save_visit.php - Save Patient Visit Records
 * Handles saving patient visit data to the database
 * Stores visit information in patient_visits table
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

redirectIfNotLoggedIn();

if (!isStaff()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $visit_id = intval($_POST['visit_id'] ?? 0);
    $action = $_POST['action'] ?? 'add'; // 'add' or 'edit'
    
    // Get all form data according to your table structure
    $visit_date = $_POST['visit_date'] ?? '';
    $visit_type = $_POST['visit_type'] ?? '';
    $symptoms = $_POST['symptoms'] ?? '';
    $vital_signs = $_POST['vital_signs'] ?? '';
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment = $_POST['treatment'] ?? '';
    $prescription = $_POST['prescription'] ?? '';
    $referral_info = $_POST['referral_info'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $next_visit_date = !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null;
    
    // Validate required fields
    if (empty($patient_id) || empty($visit_date) || empty($visit_type)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields (Date, Type)',
            'data' => null
        ]);
        exit();
    }
    
    try {
        // Check if patient exists and belongs to current staff
        $stmt = $pdo->prepare("SELECT id, full_name FROM sitio1_patients WHERE id = ? AND added_by = ?");
        $stmt->execute([$patient_id, $_SESSION['user']['id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Patient not found or you do not have access',
                'data' => null
            ]);
            exit();
        }
        
        $staff_id = $_SESSION['user']['id'];
        $response = ['success' => false, 'message' => '', 'data' => null];
        
        if ($action === 'add') {
            // Insert new visit record - matching your table structure exactly
            $stmt = $pdo->prepare("INSERT INTO patient_visits 
                (patient_id, staff_id, visit_date, visit_type, diagnosis, treatment, prescription, notes, next_visit_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $patient_id,
                $staff_id,
                $visit_date,
                $visit_type,
                !empty($diagnosis) ? $diagnosis : null,
                !empty($treatment) ? $treatment : null,
                !empty($prescription) ? $prescription : null,
                $this->combineVisitNotes($symptoms, $vital_signs, $referral_info, $notes),
                $next_visit_date
            ]);
            
            $visit_id = $pdo->lastInsertId();
            $response['success'] = true;
            $response['message'] = '✅ Visit record added successfully!';
            
            // Log the successful creation
            try {
                log_action('visit_add', $_SESSION['user']['id'], $patient_id, [
                    'visit_id' => $visit_id,
                    'visit_date' => $visit_date,
                    'visit_type' => $visit_type
                ]);
            } catch (Exception $e) {
                // Logging should not break the response
            }
            
        } elseif ($action === 'edit' && $visit_id > 0) {
            // Update existing visit record
            // First check if visit belongs to a patient added by current staff
            $stmt = $pdo->prepare("SELECT pv.* FROM patient_visits pv 
                                  JOIN sitio1_patients p ON pv.patient_id = p.id 
                                  WHERE pv.id = ? AND p.added_by = ?");
            $stmt->execute([$visit_id, $_SESSION['user']['id']]);
            $existingVisit = $stmt->fetch();
            
            if (!$existingVisit) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'message' => 'Visit record not found or you do not have access',
                    'data' => null
                ]);
                exit();
            }
            
            // Update visit record - matching your table structure
            $stmt = $pdo->prepare("UPDATE patient_visits SET 
                visit_date = ?, visit_type = ?, diagnosis = ?, treatment = ?, 
                prescription = ?, notes = ?, next_visit_date = ?, updated_at = NOW()
                WHERE id = ?");
            
            $stmt->execute([
                $visit_date,
                $visit_type,
                !empty($diagnosis) ? $diagnosis : null,
                !empty($treatment) ? $treatment : null,
                !empty($prescription) ? $prescription : null,
                $this->combineVisitNotes($symptoms, $vital_signs, $referral_info, $notes),
                $next_visit_date,
                $visit_id
            ]);
            
            $response['success'] = true;
            $response['message'] = '✅ Visit record updated successfully!';
            
            // Log the update
            try {
                log_action('visit_update', $_SESSION['user']['id'], $patient_id, [
                    'visit_id' => $visit_id,
                    'visit_date' => $visit_date,
                    'visit_type' => $visit_type
                ]);
            } catch (Exception $e) {
                // ignore logging errors
            }
        }
        
        // Fetch the saved/updated visit for display
        if ($response['success']) {
            $stmt = $pdo->prepare("SELECT pv.*, u.full_name as staff_name 
                                  FROM patient_visits pv 
                                  LEFT JOIN sitio1_users u ON pv.staff_id = u.id 
                                  WHERE pv.id = ?");
            $stmt->execute([$visit_id]);
            $savedVisit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Parse the combined notes back into separate fields for display
            $parsedNotes = $this->parseVisitNotes($savedVisit['notes'] ?? '');
            
            $response['data'] = [
                'id' => $savedVisit['id'],
                'patient_id' => $savedVisit['patient_id'],
                'patient_name' => $patient['full_name'],
                'visit_date' => date('M j, Y g:i A', strtotime($savedVisit['visit_date'])),
                'visit_date_raw' => $savedVisit['visit_date'],
                'visit_type' => ucfirst($savedVisit['visit_type']),
                'visit_type_raw' => $savedVisit['visit_type'],
                'symptoms' => $parsedNotes['symptoms'] ?? '',
                'vital_signs' => $parsedNotes['vital_signs'] ?? '',
                'diagnosis' => $savedVisit['diagnosis'] ?? '',
                'treatment' => $savedVisit['treatment'] ?? '',
                'prescription' => $savedVisit['prescription'] ?? '',
                'referral_info' => $parsedNotes['referral_info'] ?? '',
                'notes' => $parsedNotes['notes'] ?? '',
                'next_visit_date' => $savedVisit['next_visit_date'] ? date('M j, Y', strtotime($savedVisit['next_visit_date'])) : 'Not scheduled',
                'next_visit_date_raw' => $savedVisit['next_visit_date'],
                'staff_name' => $savedVisit['staff_name'],
                'created_at' => date('M j, Y g:i A', strtotime($savedVisit['created_at']))
            ];
        }
        
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = '❌ Error saving visit: ' . $e->getMessage();
        $response['data'] = null;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
    
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method',
        'data' => null
    ]);
    exit();
}

/**
 * Combine multiple visit fields into a single notes field with structured format
 */
private function combineVisitNotes($symptoms, $vital_signs, $referral_info, $notes) {
    $combined = [];
    
    if (!empty($symptoms)) {
        $combined[] = "SYMPTOMS: " . trim($symptoms);
    }
    
    if (!empty($vital_signs)) {
        $combined[] = "VITAL_SIGNS: " . trim($vital_signs);
    }
    
    if (!empty($referral_info)) {
        $combined[] = "REFERRAL: " . trim($referral_info);
    }
    
    if (!empty($notes)) {
        $combined[] = "NOTES: " . trim($notes);
    }
    
    return implode("\n\n", $combined);
}

/**
 * Parse combined notes back into separate fields
 */
private function parseVisitNotes($combinedNotes) {
    $parsed = [
        'symptoms' => '',
        'vital_signs' => '',
        'referral_info' => '',
        'notes' => ''
    ];
    
    if (empty($combinedNotes)) {
        return $parsed;
    }
    
    $sections = explode("\n\n", $combinedNotes);
    
    foreach ($sections as $section) {
        if (strpos($section, 'SYMPTOMS:') === 0) {
            $parsed['symptoms'] = trim(substr($section, 9));
        } elseif (strpos($section, 'VITAL_SIGNS:') === 0) {
            $parsed['vital_signs'] = trim(substr($section, 12));
        } elseif (strpos($section, 'REFERRAL:') === 0) {
            $parsed['referral_info'] = trim(substr($section, 9));
        } elseif (strpos($section, 'NOTES:') === 0) {
            $parsed['notes'] = trim(substr($section, 6));
        }
    }
    
    return $parsed;
}
?>