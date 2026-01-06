<?php
require_once __DIR__ . '/../includes/auth.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

global $pdo;

// Get filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$serviceType = $_GET['service_type'] ?? 'all';

// Simple query using only the user_appointments table
$query = "SELECT 
            id,
            user_id,
            appointment_id,
            service_type,
            status,
            priority_number,
            invoice_number,
            health_concerns,
            notes,
            rejection_reason,
            cancel_reason,
            reschedule_count,
            created_at,
            processed_at,
            completed_at,
            cancelled_at,
            missed_at
          FROM user_appointments 
          WHERE DATE(created_at) BETWEEN ? AND ?";

$params = [$startDate, $endDate];

if ($serviceType !== 'all') {
    $query .= " AND service_type = ?";
    $params[] = $serviceType;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="appointments_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Create Excel content
echo "<table border='1'>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>Appointment ID</th>";
echo "<th>User ID</th>";
echo "<th>Service Type</th>";
echo "<th>Status</th>";
echo "<th>Priority No.</th>";
echo "<th>Invoice No.</th>";
echo "<th>Health Concerns</th>";
echo "<th>Notes</th>";
echo "<th>Rejection Reason</th>";
echo "<th>Cancellation Reason</th>";
echo "<th>Reschedule Count</th>";
echo "<th>Date Created</th>";
echo "<th>Date Processed</th>";
echo "<th>Date Completed</th>";
echo "<th>Cancelled Date</th>";
echo "<th>Missed Date</th>";
echo "</tr>";

foreach ($appointments as $appointment) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($appointment['id']) . "</td>";
    echo "<td>" . htmlspecialchars($appointment['appointment_id'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($appointment['user_id']) . "</td>";
    echo "<td>" . htmlspecialchars($appointment['service_type']) . "</td>";
    echo "<td>" . htmlspecialchars($appointment['status']) . "</td>";
    echo "<td>" . htmlspecialchars($appointment['priority_number'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($appointment['invoice_number'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars(substr($appointment['health_concerns'] ?? '', 0, 100)) . "</td>";
    echo "<td>" . htmlspecialchars(substr($appointment['notes'] ?? '', 0, 100)) . "</td>";
    echo "<td>" . htmlspecialchars(substr($appointment['rejection_reason'] ?? '', 0, 50)) . "</td>";
    echo "<td>" . htmlspecialchars(substr($appointment['cancel_reason'] ?? '', 0, 50)) . "</td>";
    echo "<td>" . htmlspecialchars($appointment['reschedule_count'] ?? '0') . "</td>";
    echo "<td>" . date('Y-m-d H:i', strtotime($appointment['created_at'])) . "</td>";
    echo "<td>" . ($appointment['processed_at'] ? date('Y-m-d H:i', strtotime($appointment['processed_at'])) : '') . "</td>";
    echo "<td>" . ($appointment['completed_at'] ? date('Y-m-d H:i', strtotime($appointment['completed_at'])) : '') . "</td>";
    echo "<td>" . ($appointment['cancelled_at'] ? date('Y-m-d H:i', strtotime($appointment['cancelled_at'])) : '') . "</td>";
    echo "<td>" . ($appointment['missed_at'] ? date('Y-m-d H:i', strtotime($appointment['missed_at'])) : '') . "</td>";
    echo "</tr>";
}

echo "</table>";