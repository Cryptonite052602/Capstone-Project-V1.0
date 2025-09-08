<?php
// save_patient_data.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
    $patientId = intval($_POST['patient_id']);
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update patient basic info
        $stmt = $pdo->prepare("UPDATE sitio1_patients SET last_checkup = ? WHERE id = ? AND added_by = ?");
        $stmt->execute([
            !empty($_POST['last_checkup']) ? $_POST['last_checkup'] : null,
            $patientId,
            $_SESSION['user']['id']
        ]);
        
        // For non-registered users, update personal info
        if (empty($_POST['user_id'])) {
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET full_name = ?, age = ?, gender = ?, address = ?, contact = ? WHERE id = ? AND added_by = ?");
            $stmt->execute([
                $_POST['full_name'],
                $_POST['age'],
                $_POST['gender'],
                $_POST['address'],
                $_POST['contact'],
                $patientId,
                $_SESSION['user']['id']
            ]);
        }
        
        // Save or update health information
        $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $existingInfo = $stmt->fetch();
        
        if ($existingInfo) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                gender = ?, height = ?, weight = ?, blood_type = ?, allergies = ?, 
                medical_history = ?, current_medications = ?, family_history = ?
                WHERE patient_id = ?");
            $stmt->execute([
                $_POST['gender'],
                $_POST['height'],
                $_POST['weight'],
                $_POST['blood_type'],
                $_POST['allergies'],
                $_POST['medical_history'],
                $_POST['current_medications'],
                $_POST['family_history'],
                $patientId
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, blood_type, allergies, 
                 medical_history, current_medications, family_history) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId,
                $_POST['gender'],
                $_POST['height'],
                $_POST['weight'],
                $_POST['blood_type'],
                $_POST['allergies'],
                $_POST['medical_history'],
                $_POST['current_medications'],
                $_POST['family_history']
            ]);
        }
        
        // Save visit data if provided
        if (!empty($_POST['visit_date']) && !empty($_POST['visit_type'])) {
            $stmt = $pdo->prepare("INSERT INTO patient_visits 
                (patient_id, staff_id, visit_date, visit_type, diagnosis, 
                 treatment, prescription, notes, next_visit_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId,
                $_SESSION['user']['id'],
                $_POST['visit_date'],
                $_POST['visit_type'],
                $_POST['diagnosis'] ?? '',
                $_POST['treatment'] ?? '',
                $_POST['prescription'] ?? '',
                $_POST['notes'] ?? '',
                !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null
            ]);
        }
        
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Patient information saved successfully!';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error saving patient information: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>