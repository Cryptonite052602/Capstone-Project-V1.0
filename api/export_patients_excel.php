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
$sitio = $_GET['sitio'] ?? 'all';

// Build query
$query = "SELECT 
            id,
            full_name,
            date_of_birth,
            age,
            gender,
            civil_status,
            address,
            sitio,
            contact,
            disease,
            last_checkup,
            consultation_type,
            occupation,
            created_at
          FROM sitio1_patients 
          WHERE DATE(created_at) BETWEEN ? AND ?
          AND deleted_at IS NULL";

$params = [$startDate, $endDate];

if ($sitio !== 'all') {
    $query .= " AND sitio = ?";
    $params[] = $sitio;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="patient_records_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Create Excel content
echo "<table border='1'>";
echo "<tr>";
echo "<th>ID</th>";
echo "<th>Full Name</th>";
echo "<th>Date of Birth</th>";
echo "<th>Age</th>";
echo "<th>Gender</th>";
echo "<th>Civil Status</th>";
echo "<th>Address</th>";
echo "<th>Sitio</th>";
echo "<th>Contact</th>";
echo "<th>Disease/Condition</th>";
echo "<th>Last Checkup</th>";
echo "<th>Consultation Type</th>";
echo "<th>Occupation</th>";
echo "<th>Date Registered</th>";
echo "</tr>";

foreach ($patients as $patient) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($patient['id']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['full_name']) . "</td>";
    echo "<td>" . ($patient['date_of_birth'] ? date('Y-m-d', strtotime($patient['date_of_birth'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($patient['age']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['gender']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['civil_status']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['address']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['sitio']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['contact']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['disease']) . "</td>";
    echo "<td>" . ($patient['last_checkup'] ? date('Y-m-d', strtotime($patient['last_checkup'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($patient['consultation_type']) . "</td>";
    echo "<td>" . htmlspecialchars($patient['occupation']) . "</td>";
    echo "<td>" . date('Y-m-d', strtotime($patient['created_at'])) . "</td>";
    echo "</tr>";
}

echo "</table>";