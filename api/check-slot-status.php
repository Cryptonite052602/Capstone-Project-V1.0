<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isStaff()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

if (!isset($_GET['slot_id']) || !is_numeric($_GET['slot_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid slot ID']);
    exit();
}

$slotId = intval($_GET['slot_id']);
$staffId = $_SESSION['user']['id'];

try {
    // Verify the slot belongs to the staff member
    $stmt = $pdo->prepare("SELECT id FROM sitio1_appointments WHERE id = ? AND staff_id = ?");
    $stmt->execute([$slotId, $staffId]);
    $slot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$slot) {
        echo json_encode(['hasAppointments' => true]); // Assume it has appointments if slot not found
        exit();
    }
    
    // Check if there are any appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as appointment_count FROM user_appointments WHERE appointment_id = ?");
    $stmt->execute([$slotId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['hasAppointments' => $result['appointment_count'] > 0]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>