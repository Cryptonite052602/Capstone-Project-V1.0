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
        // Get consultations for user
        if (isUser()) {
            $userId = $_SESSION['user']['id'];
            $status = $_GET['status'] ?? null;
            
            $query = "SELECT * FROM sitio1_consultations WHERE user_id = ?";
            $params = [$userId];
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
            }
            
            $query .= " ORDER BY created_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['consultations' => $consultations]);
        } 
        // Get consultations for staff/admin
        elseif (isStaff() || isAdmin()) {
            $status = $_GET['status'] ?? 'pending';
            $limit = $_GET['limit'] ?? null;
            
            $query = "
                SELECT c.*, u.full_name as user_name 
                FROM sitio1_consultations c
                JOIN sitio1_users u ON c.user_id = u.id
                WHERE c.status = ?
                ORDER BY c.created_at DESC
            ";
            
            if ($limit) {
                $query .= " LIMIT ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$status, $limit]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute([$status]);
            }
            
            $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['consultations' => $consultations]);
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // User submitting consultation
        if (isUser() && isset($data['question'])) {
            $userId = $_SESSION['user']['id'];
            $question = trim($data['question']);
            $isCustom = $data['is_custom'] ?? false;
            
            if (empty($question)) {
                http_response_code(400);
                echo json_encode(['error' => 'Question cannot be empty']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sitio1_consultations (user_id, question, is_custom)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $question, $isCustom]);
            
            echo json_encode(['success' => true, 'consultation_id' => $pdo->lastInsertId()]);
        } 
        // Staff responding to consultation
        elseif ((isStaff() || isAdmin()) && isset($data['consultation_id']) && isset($data['response'])) {
            $consultationId = $data['consultation_id'];
            $response = trim($data['response']);
            $staffId = $_SESSION['user']['id'];
            
            if (empty($response)) {
                http_response_code(400);
                echo json_encode(['error' => 'Response cannot be empty']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                UPDATE sitio1_consultations 
                SET response = ?, responded_by = ?, status = 'responded', responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$response, $staffId, $consultationId]);
            
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