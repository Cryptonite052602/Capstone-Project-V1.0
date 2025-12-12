<?php
// staff/save_patient_data.php (COMPLETE UPDATED VERSION)
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
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if patient exists and get user_id and full_name
        $stmt = $pdo->prepare("SELECT p.*, 
            COALESCE(p.full_name, u.full_name) as display_full_name,
            COALESCE(p.date_of_birth, u.date_of_birth) as display_date_of_birth,
            COALESCE(p.age, u.age) as display_age,
            COALESCE(p.gender, u.gender) as display_gender,
            u.full_name as user_full_name
            FROM sitio1_patients p 
            LEFT JOIN sitio1_users u ON p.user_id = u.id
            WHERE p.id = ? AND p.added_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            throw new Exception("Patient not found or access denied");
        }
        
        $isRegisteredUser = !empty($patient['user_id']);
        
        // Update patient basic information in sitio1_patients
        if ($isRegisteredUser) {
            // For registered users, only update specific fields that aren't pulled from user table
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET 
                last_checkup = ?
                WHERE id = ? AND added_by = ?");
            $stmt->execute([
                !empty($_POST['last_checkup']) ? $_POST['last_checkup'] : null,
                $patientId,
                $_SESSION['user']['id']
            ]);
        } else {
            // For non-registered users, update all personal information including date_of_birth
            $stmt = $pdo->prepare("UPDATE sitio1_patients SET 
                full_name = ?, 
                date_of_birth = ?,
                age = ?, 
                gender = ?, 
                civil_status = ?, 
                occupation = ?, 
                address = ?, 
                sitio = ?, 
                contact = ?, 
                last_checkup = ?
                WHERE id = ? AND added_by = ?");
            $stmt->execute([
                $_POST['full_name'] ?? '',
                !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                !empty($_POST['age']) ? intval($_POST['age']) : null,
                $_POST['gender'] ?? '',
                $_POST['civil_status'] ?? null,
                $_POST['occupation'] ?? null,
                $_POST['address'] ?? '',
                $_POST['sitio'] ?? null,
                $_POST['contact'] ?? '',
                !empty($_POST['last_checkup']) ? $_POST['last_checkup'] : null,
                $patientId,
                $_SESSION['user']['id']
            ]);
        }
        
        // Prepare health information data for existing_info_patients
        $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
        
        $healthData = [
            'gender' => $isRegisteredUser ? ($_POST['gender'] ?? '') : $_POST['gender'],
            'height' => $height,
            'weight' => $weight,
            'temperature' => $temperature,
            'blood_pressure' => $_POST['blood_pressure'] ?? null,
            'blood_type' => $_POST['blood_type'] ?? '',
            'allergies' => $_POST['allergies'] ?? null,
            'medical_history' => $_POST['medical_history'] ?? null,
            'current_medications' => $_POST['current_medications'] ?? null,
            'family_history' => $_POST['family_history'] ?? null,
            'immunization_record' => $_POST['immunization_record'] ?? null,
            'chronic_conditions' => $_POST['chronic_conditions'] ?? null
        ];
        
        // Check if health info already exists
        $stmt = $pdo->prepare("SELECT id FROM existing_info_patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $existingInfo = $stmt->fetch();
        
        if ($existingInfo) {
            // Update existing health record
            $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                gender = ?, 
                height = ?, 
                weight = ?, 
                temperature = ?, 
                blood_pressure = ?,
                blood_type = ?, 
                allergies = ?, 
                medical_history = ?, 
                current_medications = ?, 
                family_history = ?, 
                immunization_record = ?,
                chronic_conditions = ?, 
                updated_at = NOW()
                WHERE patient_id = ?");
            $stmt->execute([
                $healthData['gender'],
                $healthData['height'],
                $healthData['weight'],
                $healthData['temperature'],
                $healthData['blood_pressure'],
                $healthData['blood_type'],
                $healthData['allergies'],
                $healthData['medical_history'],
                $healthData['current_medications'],
                $healthData['family_history'],
                $healthData['immunization_record'],
                $healthData['chronic_conditions'],
                $patientId
            ]);
        } else {
            // Insert new health record
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, temperature, blood_pressure,
                 blood_type, allergies, medical_history, current_medications, 
                 family_history, immunization_record, chronic_conditions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId,
                $healthData['gender'],
                $healthData['height'],
                $healthData['weight'],
                $healthData['temperature'],
                $healthData['blood_pressure'],
                $healthData['blood_type'],
                $healthData['allergies'],
                $healthData['medical_history'],
                $healthData['current_medications'],
                $healthData['family_history'],
                $healthData['immunization_record'],
                $healthData['chronic_conditions']
            ]);
        }
        
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Patient information saved successfully!';
        
        // Return beautifully formatted data for success modal
        $response['formatted_data'] = [
            'patient_id' => $patientId,
            'patient_name' => htmlspecialchars($patient['display_full_name']),
            'date_of_birth' => !empty($patient['display_date_of_birth']) ? 
                date('F j, Y', strtotime($patient['display_date_of_birth'])) : 'Not specified',
            'age' => $patient['display_age'] ? $patient['display_age'] . ' years old' : 'Not specified',
            'gender' => $patient['display_gender'] ? htmlspecialchars($patient['display_gender']) : 'Not specified',
            'height' => $height ? number_format($height, 1) . ' cm' : 'Not provided',
            'weight' => $weight ? number_format($weight, 1) . ' kg' : 'Not provided',
            'blood_type' => $healthData['blood_type'] ? '<span class="font-bold text-primary">' . htmlspecialchars($healthData['blood_type']) . '</span>' : 'Not provided',
            'temperature' => $temperature ? number_format($temperature, 1) . '°C' : 'Not provided',
            'blood_pressure' => $healthData['blood_pressure'] ? htmlspecialchars($healthData['blood_pressure']) : 'Not provided',
            'last_checkup' => !empty($_POST['last_checkup']) ? 
                date('F j, Y', strtotime($_POST['last_checkup'])) : 'Not scheduled',
            'patient_type' => $isRegisteredUser ? 
                '<span class="text-success font-bold">Registered Barangay Resident</span>' : 
                '<span class="text-info font-bold">Regular Patient</span>',
            'timestamp' => date('F j, Y g:i A'),
            'saved_by' => htmlspecialchars($_SESSION['user']['full_name'] ?? 'Medical Staff')
        ];
        
    } catch (Exception $e) {
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