<?php
// staff/save_patient_data.php (simplified version without logging)
require_once __DIR__ . '/../includes/auth.php';
redirectIfNotLoggedIn();

if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
    $patientId = intval($_POST['patient_id']);
    $response = ['success' => false, 'message' => ''];
    
    try {
        $pdo->beginTransaction();
        
        // Update patient basic info with new fields
        $stmt = $pdo->prepare("UPDATE sitio1_patients SET 
            last_checkup = ?, civil_status = ?, occupation = ?, sitio = ? 
            WHERE id = ? AND added_by = ?");
        $stmt->execute([
            !empty($_POST['last_checkup']) ? $_POST['last_checkup'] : null,
            $_POST['civil_status'] ?? null,
            $_POST['occupation'] ?? null,
            $_POST['sitio'] ?? null,
            $patientId,
            $_SESSION['user']['id']
        ]);
        
        // For non-registered users, update personal info
        $stmt = $pdo->prepare("SELECT user_id FROM sitio1_patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        
        if (empty($patient['user_id'])) {
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET 
                full_name = ?, age = ?, gender = ?, address = ?, contact = ? 
                WHERE id = ? AND added_by = ?");
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
        
        // Save or update health information with new fields
        $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $existingInfo = $stmt->fetch();
        
        $gender = !empty($patient['user_id']) ? $_POST['gender'] : $_POST['gender'];
        
        if ($existingInfo) {
            $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                gender = ?, height = ?, weight = ?, temperature = ?, blood_pressure = ?,
                blood_type = ?, allergies = ?, medical_history = ?, 
                current_medications = ?, family_history = ?, immunization_record = ?,
                chronic_conditions = ?, updated_at = NOW()
                WHERE patient_id = ?");
            $stmt->execute([
                $gender,
                $_POST['height'],
                $_POST['weight'],
                $_POST['temperature'] ?? null,
                $_POST['blood_pressure'] ?? null,
                $_POST['blood_type'],
                $_POST['allergies'],
                $_POST['medical_history'],
                $_POST['current_medications'],
                $_POST['family_history'],
                $_POST['immunization_record'] ?? null,
                $_POST['chronic_conditions'] ?? null,
                $patientId
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, temperature, blood_pressure,
                 blood_type, allergies, medical_history, current_medications, 
                 family_history, immunization_record, chronic_conditions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId,
                $gender,
                $_POST['height'],
                $_POST['weight'],
                $_POST['temperature'] ?? null,
                $_POST['blood_pressure'] ?? null,
                $_POST['blood_type'],
                $_POST['allergies'],
                $_POST['medical_history'],
                $_POST['current_medications'],
                $_POST['family_history'],
                $_POST['immunization_record'] ?? null,
                $_POST['chronic_conditions'] ?? null
            ]);
        }
        
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Patient information saved successfully!';
        
        // Fetch saved health info to return to client
        try {
            $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patientId]);
            $saved = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch patient name
            $stmt = $pdo->prepare("SELECT full_name FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            $response['data'] = [
                'patient_id' => $patientId,
                'patient_name' => $p['full_name'] ?? null,
                'gender' => $saved['gender'] ?? null,
                'height' => $saved['height'] ?? null,
                'weight' => $saved['weight'] ?? null,
                'temperature' => $saved['temperature'] ?? null,
                'blood_pressure' => $saved['blood_pressure'] ?? null,
                'blood_type' => $saved['blood_type'] ?? null,
                'allergies' => $saved['allergies'] ?? null,
                'medical_history' => $saved['medical_history'] ?? null,
                'current_medications' => $saved['current_medications'] ?? null,
                'family_history' => $saved['family_history'] ?? null,
                'chronic_conditions' => $saved['chronic_conditions'] ?? null,
                'updated_at' => $saved['updated_at'] ?? null
            ];
        } catch (Exception $e) {
            // ignore fetch errors
            $response['data'] = null;
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $response['message'] = 'Error saving patient information: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>