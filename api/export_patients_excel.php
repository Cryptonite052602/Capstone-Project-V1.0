<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

global $pdo;

// Get and validate parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$searchQuery = $_GET['search'] ?? '';

// Validate date format
if (!strtotime($startDate) || !strtotime($endDate)) {
    header('HTTP/1.0 400 Bad Request');
    exit('Invalid date format');
}

try {
    // Build query
    $query = "SELECT 
                id, 
                full_name, 
                age, 
                address, 
                disease, 
                contact as contact_number, 
                last_checkup, 
                medical_history, 
                created_at 
              FROM sitio1_patients 
              WHERE created_at BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate . ' 23:59:59'];
    
    if (!empty($searchQuery)) {
        $query .= " AND (full_name LIKE ? OR address LIKE ? OR contact LIKE ? OR disease LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="patient_records_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Start Excel output
    echo '<table border="1">';
    
    // Table headers
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Full Name</th>';
    echo '<th>Age</th>';
    echo '<th>Address</th>';
    echo '<th>Condition</th>';
    echo '<th>Contact</th>';
    echo '<th>Last Checkup</th>';
    echo '<th>Date Added</th>';
    echo '</tr>';
    
    // Table data
    foreach ($patients as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
        echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['age']) . '</td>';
        echo '<td>' . htmlspecialchars($row['address']) . '</td>';
        echo '<td>' . htmlspecialchars($row['disease']) . '</td>';
        echo '<td>' . htmlspecialchars($row['contact_number']) . '</td>';
        echo '<td>' . ($row['last_checkup'] ? date('M d, Y', strtotime($row['last_checkup'])) : 'N/A') . '</td>';
        echo '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit();

} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
}