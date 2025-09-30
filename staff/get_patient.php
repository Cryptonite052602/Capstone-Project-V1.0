<?php
// staff/get_patient.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
redirectIfNotLoggedIn();

if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT p.*, 
                          COALESCE(e.gender, p.gender) as display_gender,
                          u.unique_number, u.email as user_email, u.id as user_id
                          FROM sitio1_patients p 
                          LEFT JOIN sitio1_users u ON p.user_id = u.id
                          LEFT JOIN existing_info_patients e ON p.id = e.patient_id
                          WHERE p.id = ? AND p.added_by = ?");
    $stmt->execute([$patientId, $_SESSION['user']['id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Get health info
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$healthInfo) {
        $healthInfo = [
            'height' => '',
            'weight' => '',
            'blood_type' => '',
            'allergies' => '',
            'medical_history' => '',
            'current_medications' => '',
            'family_history' => ''
        ];
    }
    
    $response = [
        'success' => true,
        'patient' => $patient,
        'healthInfo' => $healthInfo
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading patient data: ' . $e->getMessage()]);
}
?>