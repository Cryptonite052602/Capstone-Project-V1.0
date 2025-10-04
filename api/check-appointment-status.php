<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isUser()) {
    echo json_encode(['canCancel' => false, 'message' => 'Authentication required']);
    exit;
}

if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    echo json_encode(['canCancel' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$appointmentId = intval($_GET['appointment_id']);
$userId = $_SESSION['user']['id'];

try {
    $stmt = $pdo->prepare("
        SELECT ua.status, a.date 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.id = ? AND ua.user_id = ?
    ");
    $stmt->execute([$appointmentId, $userId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['canCancel' => false, 'message' => 'Appointment not found']);
        exit;
    }
    
    if ($appointment['status'] === 'pending' && $appointment['date'] >= date('Y-m-d')) {
        echo json_encode(['canCancel' => true, 'message' => 'Appointment can be cancelled']);
    } elseif ($appointment['status'] === 'approved') {
        echo json_encode(['canCancel' => false, 'message' => 'Approved appointments cannot be cancelled online. Please contact support.']);
    } elseif ($appointment['status'] === 'completed') {
        echo json_encode(['canCancel' => false, 'message' => 'Completed appointments cannot be cancelled.']);
    } elseif ($appointment['status'] === 'cancelled') {
        echo json_encode(['canCancel' => false, 'message' => 'This appointment has already been cancelled.']);
    } elseif ($appointment['date'] < date('Y-m-d')) {
        echo json_encode(['canCancel' => false, 'message' => 'Past appointments cannot be cancelled.']);
    } else {
        echo json_encode(['canCancel' => false, 'message' => 'Appointment cannot be cancelled at this time.']);
    }
} catch (PDOException $e) {
    echo json_encode(['canCancel' => false, 'message' => 'Error checking appointment status']);
}
?>