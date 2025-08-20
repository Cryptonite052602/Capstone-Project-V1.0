<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Make sure TCPDF is installed via composer

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
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Community Health Tracker');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Patient Records');
    $pdf->SetSubject('Patient Data Export');
    
    // Set margins
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font for title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Patient Records Report', 0, 1, 'C');
    
    // Set font for subtitle
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'From ' . date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate)), 0, 1, 'C');
    
    if (!empty($searchQuery)) {
        $pdf->Cell(0, 10, 'Search Filter: ' . htmlspecialchars($searchQuery), 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    
    // Set font for table headers
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Table headers
    $headers = [
        'ID' => 10,
        'Full Name' => 40,
        'Age' => 15,
        'Address' => 50,
        'Condition' => 40,
        'Contact' => 30,
        'Last Checkup' => 30,
        'Date Added' => 30
    ];
    
    // Header colors
    $pdf->SetFillColor(64, 115, 158);
    $pdf->SetTextColor(255);
    
    // Print headers
    foreach ($headers as $header => $width) {
        $pdf->Cell($width, 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Set font for data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(0);
    $fill = false;
    $pdf->SetFillColor(224, 235, 255);
    
    // Print data
    foreach ($patients as $row) {
        $pdf->Cell($headers['ID'], 6, $row['id'], 'LR', 0, 'C', $fill);
        $pdf->Cell($headers['Full Name'], 6, $row['full_name'], 'LR', 0, 'L', $fill);
        $pdf->Cell($headers['Age'], 6, $row['age'], 'LR', 0, 'C', $fill);
        $pdf->Cell($headers['Address'], 6, $row['address'], 'LR', 0, 'L', $fill);
        $pdf->Cell($headers['Condition'], 6, $row['disease'], 'LR', 0, 'L', $fill);
        $pdf->Cell($headers['Contact'], 6, $row['contact_number'], 'LR', 0, 'L', $fill);
        $pdf->Cell($headers['Last Checkup'], 6, $row['last_checkup'] ? date('M d, Y', strtotime($row['last_checkup'])) : 'N/A', 'LR', 0, 'C', $fill);
        $pdf->Cell($headers['Date Added'], 6, date('M d, Y', strtotime($row['created_at'])), 'LR', 1, 'C', $fill);
        $fill = !$fill;
    }
    
    // Closing line
    $pdf->Cell(array_sum($headers), 0, '', 'T');
    
    // Output PDF
    $pdf->Output('patient_records_' . date('Y-m-d') . '.pdf', 'D');
    exit();

} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit('PDF generation error: ' . $e->getMessage());
}