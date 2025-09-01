<?php
// Create the API endpoint file: /community-health-tracker/api/get_invoice.php
// This should be a separate file, but here's the code for it:

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();

if (!isset($_GET['appointment_id'])) {
    http_response_code(400);
    echo 'Appointment ID required';
    exit();
}

$appointmentId = intval($_GET['appointment_id']);
$userId = $_SESSION['user']['id'];

try {
    $stmt = $pdo->prepare("
        SELECT ua.invoice_data 
        FROM user_appointments ua
        WHERE ua.id = ? AND ua.user_id = ? AND ua.invoice_generated = 1
    ");
    $stmt->execute([$appointmentId, $userId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invoice && !empty($invoice['invoice_data'])) {
        header('Content-Type: text/html');
        echo $invoice['invoice_data'];
    } else {
        http_response_code(404);
        echo 'Invoice not found';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Error retrieving invoice';
}

?>