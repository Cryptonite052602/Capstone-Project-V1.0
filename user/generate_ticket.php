<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

use TCPDF as TCPDF;

if (isset($_GET['appointment_id'])) {
    $appointmentId = intval($_GET['appointment_id']);
    $userId = $_SESSION['user']['id'];
    
    global $pdo;
    
    try {
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT 
                ua.*, 
                a.date, 
                a.start_time, 
                a.end_time, 
                s.full_name as staff_name, 
                s.specialization,
                u.full_name as patient_name,
                u.age,
                u.contact
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            JOIN sitio1_staff s ON a.staff_id = s.id
            JOIN sitio1_users u ON ua.user_id = u.id
            WHERE ua.id = ? AND ua.user_id = ?
        ");
        $stmt->execute([$appointmentId, $userId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            die('Appointment not found');
        }
        
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Community Health Tracker');
        $pdf->SetAuthor('Community Health Tracker');
        $pdf->SetTitle('Appointment Ticket');
        $pdf->SetSubject('Appointment Ticket');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', 'B', 20);
        
        // Title
        $pdf->SetTextColor(59, 130, 246); // Blue color
        $pdf->Cell(0, 10, 'HEALTH APPOINTMENT TICKET', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Border for ticket
        $pdf->SetDrawColor(59, 130, 246);
        $pdf->SetLineWidth(1);
        $pdf->Rect(10, 10, 190, 120);
        
        // Priority Number - Large and prominent
        $pdf->SetFont('helvetica', 'B', 48);
        $pdf->SetTextColor(220, 38, 38); // Red color for priority number
        $pdf->Cell(0, 20, 'PRIORITY #' . $appointment['priority_number'], 0, 1, 'C');
        $pdf->Ln(10);
        
        // Reset font and color for details
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0); // Black color
        
        // Appointment details in a table-like format
        $details = [
            'Patient Name' => $appointment['patient_name'],
            'Health Worker' => $appointment['staff_name'],
            'Specialization' => $appointment['specialization'] ?: 'General Checkup',
            'Date' => date('F j, Y', strtotime($appointment['date'])),
            'Time' => date('h:i A', strtotime($appointment['start_time'])) . ' - ' . date('h:i A', strtotime($appointment['end_time'])),
            'Ticket Generated' => date('F j, Y g:i A')
        ];
        
        if ($appointment['age']) {
            $details['Age'] = $appointment['age'];
        }
        
        if ($appointment['contact']) {
            $details['Contact'] = $appointment['contact'];
        }
        
        // Health concerns
        if (!empty($appointment['health_concerns'])) {
            $details['Health Concerns'] = $appointment['health_concerns'];
        }
        
        $y = $pdf->GetY();
        foreach ($details as $label => $value) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(40, 8, $label . ':', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, $value, 0, 1, 'L');
        }
        
        // QR Code for quick scanning (optional)
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, 'Scan this QR code for verification:', 0, 1, 'C');
        
        // Generate QR code data
        $qrData = "CHT-APPT-" . $appointmentId . "-" . $appointment['priority_number'];
        
        // Generate QR code
        $style = array(
            'border' => 0,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0,0,0),
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        );
        
        $pdf->write2DBarcode($qrData, 'QRCODE,L', 80, $pdf->GetY(), 50, 50, $style, 'N');
        
        // Important notes
        $pdf->SetY(140);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 8, "Important: Please arrive 15 minutes before your scheduled time. Bring this ticket and valid ID. Late arrivals may be rescheduled.", 0, 'C');
        
        // Output the PDF
        $pdf->Output('appointment_ticket_' . $appointment['priority_number'] . '.pdf', 'D');
        
    } catch (PDOException $e) {
        die('Error generating ticket: ' . $e->getMessage());
    }
} else {
    die('Invalid request');
}
?>