<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $pdo;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get available appointment slots
        if (isset($_GET['available'])) {
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT a.id, a.date, a.start_time, a.end_time, a.max_slots,
                       COUNT(ua.id) as booked_slots
                FROM sitio1_appointments a
                LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status != 'rejected'
                WHERE a.date = ? AND a.date >= CURDATE()
                GROUP BY a.id
                HAVING booked_slots < a.max_slots
                ORDER BY a.start_time
            ");
            $stmt->execute([$date]);
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['slots' => $slots]);
        } 
        // Get user's appointments
        elseif (isUser()) {
            $userId = $_SESSION['user']['id'];
            $status = $_GET['status'] ?? null;
            
            $query = "
                SELECT ua.*, a.date, a.start_time, a.end_time, s.full_name as staff_name
                FROM user_appointments ua
                JOIN sitio1_appointments a ON ua.appointment_id = a.id
                JOIN sitio1_staff s ON a.staff_id = s.id
                WHERE ua.user_id = ?
            ";
            
            $params = [$userId];
            
            if ($status) {
                $query .= " AND ua.status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY a.date DESC, a.start_time DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['appointments' => $appointments]);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // User requesting appointment
        if (isUser() && isset($data['appointment_id'])) {
            $userId = $_SESSION['user']['id'];
            $appointmentId = $data['appointment_id'];
            $notes = $data['notes'] ?? '';
            
            // Check if slot is still available
            $stmt = $pdo->prepare("
                SELECT a.max_slots, COUNT(ua.id) as booked_slots
                FROM sitio1_appointments a
                LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status != 'rejected'
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$appointmentId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot || $slot['booked_slots'] >= $slot['max_slots']) {
                http_response_code(400);
                echo json_encode(['error' => 'Slot no longer available']);
                exit();
            }
            
            // Check if user already has a pending/approved appointment for this slot
            $stmt = $pdo->prepare("
                SELECT id FROM user_appointments 
                WHERE user_id = ? AND appointment_id = ? AND status IN ('pending', 'approved')
            ");
            $stmt->execute([$userId, $appointmentId]);
            
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'You already have a request for this slot']);
                exit();
            }
            
            // Create appointment request
            $stmt = $pdo->prepare("
                INSERT INTO user_appointments (user_id, appointment_id, notes)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $appointmentId, $notes]);
            
            echo json_encode(['success' => true, 'appointment_id' => $pdo->lastInsertId()]);
        } 
        // Staff/Admin managing appointments
        elseif ((isStaff() || isAdmin()) && isset($data['action'])) {
            $appointmentId = $data['appointment_id'] ?? null;
            $action = $data['action'];
            
            if (!$appointmentId || !in_array($action, ['approve', 'reject', 'complete'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid data']);
                exit();
            }
            
            $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'completed');
            
            $stmt = $pdo->prepare("
                UPDATE user_appointments 
                SET status = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $appointmentId]);
            
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>