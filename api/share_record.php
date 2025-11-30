<?php
/**
 * Share Health Record API
 * 
 * This API endpoint handles sharing of health records via email.
 * It should be created at: api/share_record.php
 * 
 * Usage:
 * POST /api/share_record.php
 * Parameters:
 *   - record_id (int): The health record ID to share
 *   - email (string): Email address to share with
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

// Check if user is logged in
redirectIfNotLoggedIn();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate input
$recordId = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Input validation
if (empty($recordId) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

global $pdo;

try {
    $userId = $_SESSION['user']['id'];
    
    // Verify that the record belongs to the current user
    $stmt = $pdo->prepare("
        SELECT v.*, p.user_id, s.full_name as doctor_name
        FROM patient_visits v
        JOIN sitio1_patients p ON v.patient_id = p.id
        JOIN sitio1_staff s ON v.staff_id = s.id
        WHERE v.id = ? AND p.user_id = ?
    ");
    $stmt->execute([$recordId, $userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }
    
    // Prepare email content
    $userFullName = $_SESSION['user']['full_name'] ?? 'A Patient';
    $visitDate = date('F d, Y', strtotime($record['visit_date']));
    $visitType = ucfirst($record['visit_type'] ?? 'General');
    
    $emailSubject = "Health Record - {$visitType} Visit ({$visitDate})";
    
    $emailBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #3b82f6; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
            .section { background-color: #f9fafb; padding: 15px; border-left: 4px solid #3b82f6; margin: 15px 0; }
            .section-title { font-weight: bold; color: #1f2937; margin-bottom: 10px; }
            .footer { color: #6b7280; font-size: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$visitType} Visit Record</h2>
                <p>Shared by: {$userFullName}</p>
                <p>Date: {$visitDate}</p>
            </div>
            
            <div class='section'>
                <div class='section-title'>👨‍⚕️ Healthcare Provider</div>
                <p>Dr. " . htmlspecialchars($record['doctor_name']) . "</p>
            </div>
            
            " . (!empty($record['diagnosis']) ? "
            <div class='section'>
                <div class='section-title'>🔍 Diagnosis</div>
                <p>" . nl2br(htmlspecialchars($record['diagnosis'])) . "</p>
            </div>
            " : "") . "
            
            " . (!empty($record['treatment']) ? "
            <div class='section'>
                <div class='section-title'>🏥 Treatment</div>
                <p>" . nl2br(htmlspecialchars($record['treatment'])) . "</p>
            </div>
            " : "") . "
            
            " . (!empty($record['prescription']) ? "
            <div class='section'>
                <div class='section-title'>💊 Prescription</div>
                <p>" . nl2br(htmlspecialchars($record['prescription'])) . "</p>
            </div>
            " : "") . "
            
            " . (!empty($record['notes']) ? "
            <div class='section'>
                <div class='section-title'>📝 Notes</div>
                <p>" . nl2br(htmlspecialchars($record['notes'])) . "</p>
            </div>
            " : "") . "
            
            " . (!empty($record['next_visit_date']) ? "
            <div class='section'>
                <div class='section-title'>📅 Next Appointment</div>
                <p>" . date('F d, Y', strtotime($record['next_visit_date'])) . "</p>
            </div>
            " : "") . "
            
            <div class='footer'>
                <p>This health record has been shared securely through the Community Health Tracker System.</p>
                <p>If you have any questions, please contact the sender or your healthcare provider.</p>
                <p style='color: #999; font-size: 11px;'>Sent on: " . date('F d, Y \a\t g:i A') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Set email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . (getenv('MAIL_FROM') ?? 'noreply@communityhealthtracker.local') . "\r\n";
    $headers .= "Reply-To: " . htmlspecialchars($_SESSION['user']['email']) . "\r\n";
    
    // Send email
    $mailSent = mail($email, $emailSubject, $emailBody, $headers);
    
    if (!$mailSent) {
        throw new Exception('Failed to send email');
    }
    
    // Log the share action
    logAction('share_health_record', [
        'record_id' => $recordId,
        'shared_with' => $email,
        'visit_date' => $record['visit_date'],
        'visit_type' => $record['visit_type']
    ]);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Record shared successfully with ' . htmlspecialchars($email)
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Error sharing health record: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error sharing record: ' . $e->getMessage()
    ]);
}
?>
