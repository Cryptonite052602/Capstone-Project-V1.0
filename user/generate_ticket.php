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
        // Fetch appointment
        $stmt = $pdo->prepare("
            SELECT 
                ua.*, 
                a.date, 
                a.start_time, 
                a.end_time, 
                s.full_name AS staff_name, 
                s.specialization,
                u.full_name AS patient_name,
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

        /* ================== PDF SETUP ================== */

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Community Health Tracker');
        $pdf->SetAuthor('Community Health Tracker');
        $pdf->SetTitle('Appointment Ticket');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        /* ================== FONT LOADING (Poppins) ================== */

        $fontRegular = 'dejavusans';
        $fontBold    = 'dejavusans';

        $tcpdfFontPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/fonts/';

        // If Poppins font exists in TCPDF fonts folder, use it
        if (
            file_exists($tcpdfFontPath . 'poppins.php') &&
            file_exists($tcpdfFontPath . 'poppinsb.php')
        ) {
            $pdf->AddFont('poppins', '', 'poppins.php');
            $pdf->AddFont('poppinsb', '', 'poppinsb.php');

            $fontRegular = 'poppins';
            $fontBold    = 'poppinsb';
        }

        /* ================== COLORS ================== */
        $blue = [37, 99, 235];
        $gray = [100, 116, 139];

        /* ================== HEADER ================== */
        $pdf->SetFont($fontBold, '', 22);
        $pdf->SetTextColor(...$blue);
        $pdf->Cell(0, 12, 'Barangay Luz Health Appointment', 0, 1, 'C');

        $pdf->SetFont($fontRegular, '', 12);
        $pdf->SetTextColor(...$gray);
        $pdf->Cell(0, 7, 'Official Appointment Ticket', 0, 1, 'C');

        $pdf->Ln(6);

        /* ================== PRIORITY NUMBER ================== */
        $pdf->SetFont($fontBold, '', 60);
        $pdf->SetTextColor(...$blue);
        $pdf->Cell(0, 26, $appointment['priority_number'], 0, 1, 'C');

        $pdf->SetFont($fontBold, '', 13);
        $pdf->Cell(0, 8, 'Priority Number', 0, 1, 'C');

        $pdf->Ln(6);

        /* ================== DETAILS ================== */
        $pdf->SetTextColor(0, 0, 0);

        $details = [
            'Patient Name'     => $appointment['patient_name'],
            'Health Worker'    => $appointment['staff_name'],
            'Specialization'   => $appointment['specialization'] ?: 'General Checkup',
            'Appointment Date' => date('F j, Y', strtotime($appointment['date'])),
            'Time Schedule'    => date('h:i A', strtotime($appointment['start_time'])) . ' - ' .
                                  date('h:i A', strtotime($appointment['end_time'])),
        ];

        if (!empty($appointment['age'])) {
            $details['Age'] = $appointment['age'];
        }

        if (!empty($appointment['contact'])) {
            $details['Contact'] = $appointment['contact'];
        }

        foreach ($details as $label => $value) {
            $pdf->SetFont($fontBold, '', 12);
            $pdf->Cell(55, 9, $label . ':', 0, 0);
            $pdf->SetFont($fontRegular, '', 12);
            $pdf->Cell(0, 9, $value, 0, 1);
        }

        /* ================== HEALTH CONCERNS ================== */
        if (!empty($appointment['health_concerns'])) {
            $pdf->Ln(4);
            $pdf->SetFont($fontBold, '', 12);
            $pdf->Cell(0, 8, 'Health Concerns', 0, 1);
            $pdf->SetFont($fontRegular, '', 11);
            $pdf->MultiCell(0, 7, $appointment['health_concerns']);
        }

        /* ================== QR CODE ================== */
        $pdf->Ln(6);
        $pdf->SetFont($fontBold, '', 12);
        $pdf->Cell(0, 8, 'Verification QR Code', 0, 1, 'C');

        $qrData = 'CHT-APPT-' . $appointmentId . '-' . $appointment['priority_number'];

        $style = [
            'border' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        ];

        $pdf->write2DBarcode($qrData, 'QRCODE,L', 85, $pdf->GetY(), 40, 40, $style, 'N');

        $pdf->Ln(45);

        /* ================== FOOTER ================== */
        $pdf->SetFont($fontRegular, 'I', 10);
        $pdf->SetTextColor(...$gray);
        $pdf->MultiCell(
            0,
            8,
            "Please arrive at least 15 minutes before your scheduled time.\nBring this ticket and a valid ID.",
            0,
            'C'
        );

        $pdf->Ln(4);
        $pdf->SetFont($fontRegular, '', 9);
        $pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y g:i A'), 0, 1, 'C');

        /* ================== OUTPUT ================== */
        $pdf->Output(
            'appointment_ticket_' . $appointment['priority_number'] . '.pdf',
            'D'
        );

    } catch (PDOException $e) {
        die('Error generating ticket: ' . $e->getMessage());
    }
} else {
    die('Invalid request');
}
?>
