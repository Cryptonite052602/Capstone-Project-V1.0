<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Enhanced function to check and add missing columns for appointment functionality
function checkAndAddAppointmentColumns($pdo) {
    $columns = [
        'rescheduled_from' => "INT NULL",
        'rescheduled_at' => "DATETIME NULL", 
        'rescheduled_count' => "INT DEFAULT 0",
        'invoice_number' => "VARCHAR(50) NULL",
        'priority_number' => "VARCHAR(50) NULL",
        'processed_at' => "DATETIME NULL",
        'invoice_generated_at' => "DATETIME NULL",
        'completed_at' => "DATETIME NULL",
        'rejection_reason' => "TEXT NULL",
        'cancel_reason' => "TEXT NULL",
        'cancelled_at' => "DATETIME NULL",
        'cancelled_by_user' => "BOOLEAN DEFAULT FALSE",
        'appointment_ticket' => "LONGTEXT NULL"
    ];
    
    foreach ($columns as $columnName => $columnDefinition) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM user_appointments LIKE '$columnName'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                $pdo->exec("ALTER TABLE user_appointments ADD COLUMN $columnName $columnDefinition");
                error_log("Added missing column: $columnName");
            }
        } catch (PDOException $e) {
            error_log("Error checking/adding column $columnName: " . $e->getMessage());
        }
    }
    
    // Also check if the status enum includes all required values
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM user_appointments LIKE 'status'");
        $statusColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($statusColumn) {
            $currentType = $statusColumn['Type'];
            $requiredValues = ['pending', 'approved', 'completed', 'cancelled', 'rejected', 'rescheduled', 'missed'];
            
            $missingValues = [];
            foreach ($requiredValues as $value) {
                if (stripos($currentType, $value) === false) {
                    $missingValues[] = $value;
                }
            }
            
            if (!empty($missingValues)) {
                // Update the enum to include missing values
                $newEnum = "ENUM('pending', 'approved', 'completed', 'cancelled', 'rejected', 'rescheduled', 'missed') NOT NULL DEFAULT 'pending'";
                $pdo->exec("ALTER TABLE user_appointments MODIFY status $newEnum");
                error_log("Updated status enum to include all required values");
            }
        }
    } catch (PDOException $e) {
        error_log("Error updating status enum: " . $e->getMessage());
    }
}

// Call this function to ensure columns exist
checkAndAddAppointmentColumns($pdo);

// Function to generate unique number
function generateUniqueNumber($pdo) {
    $prefix = 'CHT';
    $unique = false;
    $uniqueNumber = '';
    
    while (!$unique) {
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $uniqueNumber = $prefix . $randomNumber;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE unique_number = ?");
        $stmt->execute([$uniqueNumber]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $unique = true;
        }
    }
    
    return $uniqueNumber;
}

// Function to send email notification
function sendAccountStatusEmail($email, $status, $message = '', $uniqueNumber = '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabanagarchiel@gmail.com';
        $mail->Password   = 'qmdh ofnf bhfj wxsa';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('cabanagarchiel@gmail.com', 'Barangay Luz Health Center');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {

$mail->Subject = 'Barangay Luz Health Monitoring and Tracking System';
$mail->Body = '
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#ffffff; font-family: Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#ffffff;">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">

<!-- Header -->
<tr>
<td style="background-color:#2563eb; padding:30px; text-align:center;">
    <div style="font-size:26px; font-weight:bold; color:#ffffff;">
        <img src="/asssets/images/Luz.jpg" style="width: 100px; height: auto; margin-right: 10px;">
            Barangay Luz Health Monitoring and Tracking
    </div>
    <div style="margin-top:8px; font-size:16px; color:#dbeafe;">
        Account Approval Notice
    </div>
</td>
</tr>

<!-- Content -->
<tr>
<td style="padding:30px; color:#1f2937; font-size:15px; line-height:1.7;">

<p>Hello, </p>

<p>
We are happy to inform you that your registration has been
<strong style="color:#2563eb;">successfully approved</strong>.
Your account is now active and ready for use.
</p>

<!-- Unique Number -->
<div style="
    background-color:#eff6ff;
    border:1px solid #3b82f6;
    border-radius:8px;
    padding:16px;
    text-align:center;
    margin:25px 0;
">
    <div style="font-size:13px; color:#2563eb; margin-bottom:6px;">
        Your Unique Identification Number
    </div>
    <div style="font-size:22px; font-weight:bold; letter-spacing:1px; color:#1e3a8a;">
        ' . $uniqueNumber . '
    </div>
</div>

<p>
Please keep this number secure. It will be used for appointments,
medical records, and identity verification.
</p>

<ul style="padding-left:18px;">
    <li>Book healthcare appointments</li>
    <li>View medical history</li>
    <li>Receive health updates</li>
</ul>

<!-- Button -->
<div style="text-align:center; margin-top:30px;">
    <a href="https://your-health-portal.com/login"
       style="
        background-color:#3b82f6;
        color:#ffffff;
        text-decoration:none;
        padding:14px 28px;
        border-radius:6px;
        font-size:15px;
        display:inline-block;
       ">
        Access Your Account
    </a>
</div>

<p style="margin-top:30px;">
Thank you for trusting <strong>Barangay Luz Health Monitoring and Tracking Platform</strong> with your healthcare needs.
</p>

<p>
Warm regards,<br>
<strong>The Barangay Luz Health Center Team</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="
    background-color:#f8fafc;
    padding:20px;
    text-align:center;
    font-size:12px;
    color:#6b7280;
    border-top:1px solid #e5e7eb;
">
This is an automated message. Please do not reply.<br>
© ' . date('Y') . ' Barangay Luz Health Monitoring and Tracking System
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
';
}
 else {

$mail->Subject = 'Account Registration Update – Barangay Luz Health Monitoring and Tracking System';
$mail->Body = '
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#ffffff; font-family: Arial, Helvetica, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center">

<table width="600" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:10px;">

<!-- Header -->
<tr>
<td style="background-color:#3b82f6; padding:30px; text-align:center;">
    <div style="font-size:26px; font-weight:bold; color:#ffffff;">
        🏥 Barangay Luz Health Monitoring and Tracking System
    </div>
    <div style="margin-top:8px; font-size:16px; color:#dbeafe;">
        Registration Status Update
    </div>
</td>
</tr>

<!-- Content -->
<tr>
<td style="padding:30px; color:#1f2937; font-size:15px; line-height:1.7;">

<p>Hello,</p>

<p>
Thank you for submitting your registration. After careful review,
we are unable to approve your account at this time.
</p>

<!-- Reason -->
<div style="
    background-color:#f8fafc;
    border-left:4px solid #3b82f6;
    padding:15px;
    margin:20px 0;
">
    <strong>Reason Provided:</strong><br>
    ' . htmlspecialchars($message) . '
</div>

<p>
You may reapply or contact our support team if you believe this decision
requires further review.
</p>

<div style="
    background-color:#eff6ff;
    padding:15px;
    border-radius:8px;
    margin:25px 0;
">
    <strong>Support Contact</strong><br>
    📞 (02) 8-123-4567<br>
    ✉️ support@communityhealthtracker.ph
</div>

<p>
We appreciate your understanding and interest in our services.
</p>

<p>
Sincerely,<br>
<strong>The Barangay Luz Health Monitoring and Tracking System Team</strong>
</p>

</td>
</tr>

<!-- Footer -->
<tr>
<td style="
    background-color:#f8fafc;
    padding:20px;
    text-align:center;
    font-size:12px;
    color:#6b7280;
    border-top:1px solid #e5e7eb;
">
This is an automated message. Please do not reply.<br>
© ' . date('Y') . ' Barangay Luz Health Monitoring and Tracking System
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
';
}


        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// MODIFIED FUNCTION: Generate sequential priority number 01-05 per time slot
function generatePriorityNumber($pdo, $appointmentId, $staffId, $date, $timeSlotId) {
    // Get all approved appointments for this specific time slot on this date
    $stmt = $pdo->prepare("
        SELECT ua.id, ua.priority_number 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ? 
        AND a.date = ? 
        AND a.id = ?
        AND ua.status = 'approved'
        ORDER BY ua.processed_at ASC, ua.id ASC
    ");
    $stmt->execute([$staffId, $date, $timeSlotId]);
    $approvedAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count existing priority numbers in this time slot
    $existingPriorityNumbers = [];
    foreach ($approvedAppointments as $appointment) {
        if (!empty($appointment['priority_number'])) {
            $existingPriorityNumbers[] = $appointment['priority_number'];
        }
    }
    
    // Find the next available sequential number (01-05)
    $availableNumbers = ['01', '02', '03', '04', '05'];
    $nextNumber = null;
    
    foreach ($availableNumbers as $number) {
        if (!in_array($number, $existingPriorityNumbers)) {
            $nextNumber = $number;
            break;
        }
    }
    
    // If all numbers 01-05 are taken, use the next in sequence (shouldn't happen with max 5 slots)
    if ($nextNumber === null) {
        $nextNumber = '05'; // Fallback to 05 if all taken
    }
    
    return $nextNumber;
}

// Function to generate appointment ticket with time slot info
function generateAppointmentTicket($appointmentData, $priorityNumber) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <meta charset="utf-8">
        <title>Appointment Ticket - ' . $priorityNumber . '</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ticket-container {
                max-width: 400px;
                width: 100%;
            }
            .ticket { 
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                border: 3px solid #3b82f6;
                position: relative;
                overflow: hidden;
            }
            .ticket::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #3b82f6, #8b5cf6, #3b82f6);
            }
            .header { 
                text-align: center; 
                margin-bottom: 25px;
                border-bottom: 2px dashed #e5e7eb;
                padding-bottom: 20px;
            }
            .header h1 { 
                color: #3b82f6; 
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: bold;
            }
            .header p { 
                color: #6b7280; 
                margin: 5px 0;
                font-size: 14px;
            }
            .priority-number { 
                text-align: center; 
                font-size: 32px; 
                font-weight: bold; 
                color: #dc2626; 
                background: linear-gradient(135deg, #fef3f2, #fee2e2);
                padding: 15px;
                border-radius: 10px;
                margin: 20px 0;
                border: 2px dashed #dc2626;
                text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            }
            .section { 
                margin-bottom: 20px;
                padding: 15px;
                background: #f8fafc;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
            }
            .section h2 { 
                color: #374151; 
                border-bottom: 1px solid #e5e7eb; 
                padding-bottom: 8px; 
                font-size: 16px; 
                margin: 0 0 12px 0;
                display: flex;
                align-items: center;
            }
            .section h2 i {
                margin-right: 8px;
                color: #3b82f6;
            }
            .info-grid { 
                display: grid; 
                gap: 10px; 
            }
            .info-item { 
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px dashed #e5e7eb;
            }
            .info-item:last-child {
                border-bottom: none;
            }
            .label { 
                font-weight: bold; 
                color: #4b5563;
                font-size: 14px;
            }
            .value {
                color: #1f2937;
                font-size: 14px;
                text-align: right;
            }
            .barcode {
                text-align: center;
                margin: 25px 0 15px;
                padding: 15px;
                background: #f8fafc;
                border-radius: 8px;
                font-family: "Libre Barcode 128", cursive;
                font-size: 36px;
                letter-spacing: 2px;
            }
            .footer { 
                text-align: center; 
                margin-top: 25px; 
                color: #6b7280; 
                font-size: 12px;
                border-top: 2px dashed #e5e7eb;
                padding-top: 15px;
            }
            .watermark {
                position: absolute;
                bottom: 20px;
                right: 20px;
                opacity: 0.1;
                font-size: 72px;
                font-weight: bold;
                color: #3b82f6;
                transform: rotate(-15deg);
            }
            @media print {
                body {
                    background: white !important;
                    padding: 0;
                }
                .ticket {
                    box-shadow: none;
                    border: 2px solid #000;
                }
            }
            .time-slot-info {
                font-size: 14px;
                color: #6b7280;
                margin-top: 8px;
            }
            .priority-explanation {
                font-size: 12px;
                color: #4b5563;
                margin-top: 5px;
                font-style: italic;
            }
        </style>
        <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
    </head>
    <body>
        <div class="ticket-container">
            <div class="ticket">
                <div class="watermark">CHT</div>
                
                <div class="header">
                    <h1>Community Health Tracker</h1>
                    <p>Appointment Confirmation Ticket</p>
                    <div class="priority-number">' . $priorityNumber . '</div>
                    <div class="time-slot-info">
                        Time Slot: ' . date('g:i A', strtotime($appointmentData['start_time'])) . ' - ' . date('g:i A', strtotime($appointmentData['end_time'])) . '
                    </div>
                    <div class="priority-explanation">
                        Priority number is specific to this time slot (01-05 only)
                    </div>
                </div>
                
                <div class="section">
                    <h2><i class="fas fa-user"></i> Patient Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Full Name:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['full_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Patient ID:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['unique_number']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Contact:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['contact']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2><i class="fas fa-calendar-alt"></i> Appointment Details</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Date:</span>
                            <span class="value">' . date('F j, Y', strtotime($appointmentData['date'])) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Time:</span>
                            <span class="value">' . date('g:i A', strtotime($appointmentData['start_time'])) . ' - ' . date('g:i A', strtotime($appointmentData['end_time'])) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Healthcare Provider:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['staff_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Specialization:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['specialization']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2><i class="fas fa-file-invoice"></i> Invoice Details</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Invoice Number:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['invoice_number']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Generated:</span>
                            <span class="value">' . date('F j, Y g:i A', strtotime($appointmentData['invoice_generated_at'] ?? 'now')) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="barcode">
                    ' . strtoupper($appointmentData['invoice_number']) . '
                </div>
                
                <div class="footer">
                    <p>Please present this ticket at the clinic reception</p>
                    <p>Valid only for the scheduled appointment date and time</p>
                    <p>© ' . date('Y') . ' Community Health Tracker. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Function to send appointment approval email
function sendAppointmentApprovalEmail($appointment, $priorityNumber, $invoiceNumber) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabanagarchiel@gmail.com';
        $mail->Password   = 'qmdh ofnf bhfj wxsa';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('cabanagarchiel@gmail.com', 'Community Health Tracker');
        $mail->addAddress($appointment['email']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation - Priority #' . $priorityNumber;
        $mail->Body    = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        line-height: 1.6;
                        color: #333;
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        padding: 30px;
                        border-radius: 10px 10px 0 0;
                        color: white;
                    }
                    .logo {
                        font-size: 28px;
                        font-weight: bold;
                        margin-bottom: 10px;
                    }
                    .content {
                        background: #f9f9f9;
                        padding: 30px;
                        border-radius: 0 0 10px 10px;
                    }
                    .appointment-details {
                        background: #e8f5e8;
                        border: 2px solid #4CAF50;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    .priority-box {
                        background: #fff3cd;
                        border: 2px solid #ffc107;
                        padding: 15px;
                        border-radius: 8px;
                        text-align: center;
                        margin: 15px 0;
                        font-size: 18px;
                        font-weight: bold;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #ddd;
                        color: #666;
                        font-size: 12px;
                    }
                    .info-item {
                        margin: 10px 0;
                        padding: 8px 0;
                        border-bottom: 1px dashed #ddd;
                    }
                    .reminder {
                        background: #e3f2fd;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 15px 0;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="logo">🏥 Community Health Tracker</div>
                    <h1>Appointment Confirmed</h1>
                </div>
                <div class="content">
                    <p>Dear ' . htmlspecialchars($appointment['full_name']) . ',</p>
                    
                    <p>We are pleased to inform you that your appointment request has been approved. Below are the details of your scheduled appointment:</p>
                    
                    <div class="appointment-details">
                        <div class="info-item">
                            <strong>Date:</strong> ' . date('F j, Y', strtotime($appointment['date'])) . '
                        </div>
                        <div class="info-item">
                            <strong>Time:</strong> ' . date('g:i A', strtotime($appointment['start_time'])) . ' - ' . date('g:i A', strtotime($appointment['end_time'])) . '
                        </div>
                        <div class="info-item">
                            <strong>Healthcare Provider:</strong> ' . htmlspecialchars($appointment['staff_name']) . '
                        </div>
                        <div class="info-item">
                            <strong>Specialization:</strong> ' . htmlspecialchars($appointment['specialization']) . '
                        </div>
                        <div class="info-item">
                            <strong>Invoice Number:</strong> ' . $invoiceNumber . '
                        </div>
                    </div>
                    
                    <div class="priority-box">
                        Your Priority Number: <strong style="color: #dc2626; font-size: 24px;">' . $priorityNumber . '</strong>
                    </div>
                    
                    <div class="reminder">
                        <strong>Important Reminders:</strong>
                        <ul>
                            <li>Please arrive 15 minutes before your scheduled appointment time</li>
                            <li>Bring your appointment ticket (available in your dashboard)</li>
                            <li>Bring any relevant medical records or test results</li>
                            <li>Have your patient ID (' . htmlspecialchars($appointment['unique_number']) . ') ready</li>
                        </ul>
                    </div>
                    
                    <p>Your appointment ticket has been generated and is available for download from your patient dashboard. Please present this ticket upon arrival.</p>
                    
                    <p>If you need to reschedule or cancel your appointment, please do so at least 24 hours in advance through your patient portal or by contacting our office.</p>
                    
                    <p>We look forward to providing you with quality healthcare services.</p>
                    
                    <p>Best regards,<br>
                    <strong>The Community Health Tracker Team</strong></p>
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; ' . date('Y') . ' Community Health Tracker. All rights reserved.</p>
                </div>
            </body>
            </html>
        ';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to automatically check ID type
function checkIdType($imagePath) {
    // Common keywords for different ID types (lowercase for matching)
    $idKeywords = [
        'senior' => ['senior', 'sc', 'senior citizen', 'senior-citizen', 'osc', 'office of senior citizen', 'senior card', 's.c.', 'sc id', 'senior citizen id'],
        'national' => ['national', 'phil', 'philippine', 'phil id', 'phil-id', 'philid', 'national id', 'philippine id', 'phil sys', 'philsys', 'phil. id', 'philippine national id', 'pnid'],
        'driver' => ['driver', 'license', 'dl', 'lto', 'land transportation', 'driver\'s license', 'driving license', 'driver license'],
        'voter' => ['voter', 'comelec', 'voter id', 'voter-id', 'voter\'s id', 'voters id', 'voter certification'],
        'passport' => ['passport', 'dfa', 'department of foreign affairs', 'philippine passport', 'passport id'],
        'umid' => ['umid', 'unified', 'multi-purpose', 'unified multi-purpose', 'unified id', 'multi-purpose id'],
        'sss' => ['sss', 'social security', 'social security system', 'sss id', 'sss card', 'sss number'],
        'gsis' => ['gsis', 'government service insurance system', 'gsis id', 'gsis card'],
        'tin' => ['tin', 'tax', 'taxpayer', 'taxpayer identification', 'tin id', 'tin card', 'tax identification'],
        'postal' => ['postal', 'post office', 'philpost', 'postal id', 'post office id'],
        'prc' => ['prc', 'professional', 'professional regulation', 'prc id', 'prc license', 'professional id'],
        'nbi' => ['nbi', 'national bureau', 'national bureau of investigation', 'nbi clearance', 'nbi id'],
        'birth' => ['birth', 'certificate', 'birth certificate', 'psa', 'civil registry', 'certificate of live birth'],
        'company' => ['company', 'employee', 'employee id', 'company id', 'office', 'work', 'office id', 'work id', 'employment id'],
        'student' => ['student', 'school', 'university', 'college', 'school id', 'student id', 'student card', 'university id']
    ];
    
    // Extract filename without extension and path
    $filename = strtolower(pathinfo($imagePath, PATHINFO_FILENAME));
    
    // Check if image path contains any ID keywords
    $lowerPath = strtolower($imagePath);
    
    foreach ($idKeywords as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($filename, $keyword) !== false || strpos($lowerPath, $keyword) !== false) {
                return ucfirst($type) . ' ID';
            }
        }
    }
    
    // If no specific type found, check for general ID indicators
    $generalIdIndicators = ['id', 'identification', 'card', 'certificate', 'license'];
    foreach ($generalIdIndicators as $indicator) {
        if (strpos($filename, $indicator) !== false || strpos($lowerPath, $indicator) !== false) {
            return 'Valid ID';
        }
    }
    
    return 'Unknown ID Type';
}

// Function to check if ID is valid for verification (Senior Citizen or National ID)
function isIdValidForVerification($idType) {
    $validTypes = ['Senior ID', 'National ID'];
    
    foreach ($validTypes as $validType) {
        if (stripos($idType, $validType) !== false) {
            return true;
        }
    }
    
    return false;
}

// Export functionality
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $filterStatus = $_GET['status'] ?? 'all';
    $filterDate = $_GET['date'] ?? '';
    
    try {
        $query = "
            SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
                   s.full_name as staff_name, s.specialization
            FROM user_appointments ua 
            JOIN sitio1_users u ON ua.user_id = u.id 
            JOIN sitio1_appointments a ON ua.appointment_id = a.id 
            JOIN sitio1_users s ON a.staff_id = s.id 
            WHERE a.staff_id = ? 
        ";
        
        $params = [$staffId];
        
        if ($filterStatus !== 'all') {
            $query .= " AND ua.status = ?";
            $params[] = $filterStatus;
        }
        
        if (!empty($filterDate)) {
            $query .= " AND a.date = ?";
            $params[] = $filterDate;
        }
        
        $query .= " ORDER BY a.date DESC, a.start_time DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $exportAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($exportType === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.xls"');
            
            echo "Appointment ID\tPatient Name\tPatient ID\tContact Number\tDate\tTime\tStatus\tPriority Number\tInvoice Number\tHealth Concerns\n";
            
            foreach ($exportAppointments as $appointment) {
                $appointmentId = $appointment['id'];
                $patientName = $appointment['full_name'];
                $patientId = $appointment['unique_number'] ?? 'N/A';
                $contactNumber = $appointment['contact'];
                $date = date('M d, Y', strtotime($appointment['date']));
                $time = date('g:i A', strtotime($appointment['start_time'])) . ' - ' . date('g:i A', strtotime($appointment['end_time']));
                $status = ucfirst($appointment['status']);
                $priorityNumber = $appointment['priority_number'] ?? 'N/A';
                $invoiceNumber = $appointment['invoice_number'] || 'N/A';
                $healthConcerns = str_replace(["\t", "\n", "\r"], " ", $appointment['health_concerns'] ?? 'No concerns specified');
                
                echo "$appointmentId\t$patientName\t$patientId\t$contactNumber\t$date\t$time\t$status\t$priorityNumber\t$invoiceNumber\t$healthConcerns\n";
            }
            exit;
            
        } elseif ($exportType === 'pdf') {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            try {
                if (!class_exists('Mpdf\Mpdf')) {
                    throw new Exception('mPDF class not found. Library may not be installed.');
                }
                $mpdf = new \Mpdf\Mpdf();
            } catch (\Throwable $e) {
                header('Content-Type: text/plain');
                http_response_code(500);
                echo 'Error: mPDF library is not properly installed. Please contact your administrator. Details: ' . $e->getMessage();
                error_log('mPDF Error: ' . $e->getMessage());
                exit;
            }
            $mpdf->SetTitle('Appointments Report');
            
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family:sans-serif !important; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { color: #3b82f6; margin-bottom: 5px; }
                    .header p { color: #6b7280; margin: 0; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th { background-color: #3b82f6; color: white; padding: 12px; text-align: left; }
                    td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
                    .status-approved { color: #059669; }
                    .status-pending { color: #d97706; }
                    .status-rejected { color: #dc2626; }
                    .status-completed { color: #2563eb; }
                    .footer { margin-top: 30px; text-align: center; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Community Health Tracker</h1>
                    <p>Appointments Report - ' . date('F j, Y') . '</p>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Patient ID</th>
                            <th>Contact</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Priority No.</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($exportAppointments as $appointment) {
                $statusClass = 'status-' . $appointment['status'];
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($appointment['full_name']) . '</td>
                        <td>' . htmlspecialchars($appointment['unique_number'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($appointment['contact']) . '</td>
                        <td>' . date('M d, Y', strtotime($appointment['date'])) . '</td>
                        <td>' . date('g:i A', strtotime($appointment['start_time'])) . ' - ' . date('g:i A', strtotime($appointment['end_time'])) . '</td>
                        <td class="' . $statusClass . '">' . ucfirst($appointment['status']) . '</td>
                        <td>' . htmlspecialchars($appointment['priority_number'] ?? 'N/A') . '</td>
                    </tr>';
            }
            
            $html .= '
                    </tbody>
                </table>
                <div class="footer">
                    <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
                </div>
            </body>
            </html>';
            
            $mpdf->WriteHTML($html);
            $mpdf->Output('appointments_' . date('Y-m-d') . '.pdf', 'D');
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error exporting data: ' . $e->getMessage();
    }
}

// Get stats for dashboard
$stats = [
    'total_patients' => 0,
    'pending_consultations' => 0,
    'pending_appointments' => 0,
    'unapproved_users' => 0
];

// Staff ID
$staffId = $_SESSION['user']['id'];
$error = '';
$success = '';

// Define working hours
$morningSlots = [
    ['start' => '08:00', 'end' => '09:00'],
    ['start' => '09:00', 'end' => '10:00'],
    ['start' => '10:00', 'end' => '11:00'],
    ['start' => '11:00', 'end' => '12:00']
];

$afternoonSlots = [
    ['start' => '13:00', 'end' => '14:00'],
    ['start' => '14:00', 'end' => '15:00'],
    ['start' => '15:00', 'end' => '16:00'],
    ['start' => '16:00', 'end' => '17:00']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slot'])) {
        $date = $_POST['date'];
        $timeSlot = $_POST['time_slot'];
        $maxSlots = intval($_POST['max_slots']);
        
        // Parse the selected time slot
        $slotParts = explode(' - ', $timeSlot);
        $startTime = $slotParts[0];
        $endTime = $slotParts[1];
        
        if (!empty($date) && !empty($timeSlot)) {
            try {
                // Check if slot already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_appointments 
                                           WHERE staff_id = ? AND date = ? AND start_time = ? AND end_time = ?");
                $checkStmt->execute([$staffId, $date, $startTime, $endTime]);
                $slotExists = $checkStmt->fetchColumn();
                
                if ($slotExists > 0) {
                    $error = 'This time slot already exists for the selected date.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO sitio1_appointments (staff_id, date, start_time, end_time, max_slots) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$staffId, $date, $startTime, $endTime, $maxSlots]);
                    
                    // Store success message in session for modal display
                    $_SESSION['success_message'] = 'Appointment Slot Added Successfully!<br>' . 
                                                   date('F j, Y', strtotime($date)) . ' ' . 
                                                   date('g:i A', strtotime($startTime)) . ' - ' . 
                                                   date('g:i A', strtotime($endTime));
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=available-slots");
                    exit();
                }
            } catch (PDOException $e) {
                $error = 'Error adding appointment slot: ' . $e->getMessage();
            }
        } else {
            $error = 'Please fill in all fields with valid values.';
        }
    } elseif (isset($_POST['update_slot'])) {
        $slotId = intval($_POST['slot_id']);
        $date = $_POST['date'];
        $timeSlot = $_POST['time_slot'];
        $maxSlots = intval($_POST['max_slots']);
        
        $slotParts = explode(' - ', $timeSlot);
        $startTime = $slotParts[0];
        $endTime = $slotParts[1];
        
        if (!empty($date) && !empty($timeSlot)) {
            try {
                $stmt = $pdo->prepare("UPDATE sitio1_appointments SET date = ?, start_time = ?, end_time = ?, max_slots = ? WHERE id = ? AND staff_id = ?");
                $stmt->execute([$date, $startTime, $endTime, $maxSlots, $slotId, $staffId]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Appointment Slot Updated Successfully!<br>' . 
                                                   date('F j, Y', strtotime($date)) . ' ' . 
                                                   date('g:i A', strtotime($startTime)) . ' - ' . 
                                                   date('g:i A', strtotime($endTime));
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=available-slots");
                    exit();
                } else {
                    $error = 'No changes made or slot not found.';
                }
            } catch (PDOException $e) {
                $error = 'Error updating appointment slot: ' . $e->getMessage();
            }
        } else {
            $error = 'Please fill in all fields with valid values.';
        }
    } elseif (isset($_POST['delete_slot'])) {
        $slotId = intval($_POST['slot_id']);
        
        try {
            // First check if there are any appointments for this slot
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_appointments WHERE appointment_id = ?");
            $stmt->execute([$slotId]);
            $appointmentCount = $stmt->fetchColumn();
            
            if ($appointmentCount > 0) {
                $error = 'Cannot delete slot with existing appointments. Please reject or complete them first.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM sitio1_appointments WHERE id = ? AND staff_id = ?");
                $stmt->execute([$slotId, $staffId]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = 'Appointment Slot Deleted Successfully!';
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=available-slots");
                    exit();
                } else {
                    $error = 'Slot not found or already deleted.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error deleting appointment slot: ' . $e->getMessage();
        }
    } elseif (isset($_POST['approve_appointment'])) {
        $appointmentId = intval($_POST['appointment_id']);
        $action = $_POST['action'];
        $rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';
        
        if (in_array($action, ['approve', 'reject', 'complete'])) {
            try {
                // In the appointment approval section, update to use the new function
                if ($action === 'approve') {
                    // Get appointment details for priority number generation
                    $stmt = $pdo->prepare("
                        SELECT a.staff_id, a.date, a.id as slot_id
                        FROM user_appointments ua
                        JOIN sitio1_appointments a ON ua.appointment_id = a.id
                        WHERE ua.id = ?
                    ");
                    $stmt->execute([$appointmentId]);
                    $appointmentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$appointmentDetails) {
                        throw new Exception('Appointment not found');
                    }
                    
                    // Generate invoice and priority number using the MODIFIED function
                    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);
                    $priorityNumber = generatePriorityNumber($pdo, $appointmentId, $appointmentDetails['staff_id'], 
                                                            $appointmentDetails['date'], $appointmentDetails['slot_id']);
                    
                    // Update appointment with approved status and generated numbers
                    $stmt = $pdo->prepare("
                        UPDATE user_appointments 
                        SET status = 'approved', 
                            invoice_number = ?, 
                            priority_number = ?, 
                            processed_at = NOW(),
                            invoice_generated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$invoiceNumber, $priorityNumber, $appointmentId]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Generate and store appointment ticket
                        $stmt = $pdo->prepare("
                            SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number,
                                   a.date, a.start_time, a.end_time,
                                   s.full_name as staff_name, s.specialization
                            FROM user_appointments ua
                            JOIN sitio1_users u ON ua.user_id = u.id
                            JOIN sitio1_appointments a ON ua.appointment_id = a.id
                            JOIN sitio1_users s ON a.staff_id = s.id
                            WHERE ua.id = ?
                        ");
                        $stmt->execute([$appointmentId]);
                        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($appointment) {
                            // Generate appointment ticket HTML
                            $ticketHtml = generateAppointmentTicket($appointment, $priorityNumber);
                            
                            // Store the ticket in the database for later download
                            $stmt = $pdo->prepare("
                                UPDATE user_appointments 
                                SET appointment_ticket = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$ticketHtml, $appointmentId]);
                            
                            // Send notification email to user
                            if (filter_var($appointment['email'], FILTER_VALIDATE_EMAIL)) {
                                sendAppointmentApprovalEmail($appointment, $priorityNumber, $invoiceNumber);
                            }
                        }
                        
                        $_SESSION['success_message'] = 'Appointment Approved Successfully!<br>Priority Number: <strong>' . $priorityNumber . '</strong><br>Invoice: <strong>' . $invoiceNumber . '</strong>';
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=pending");
                        exit();
                    } else {
                        $error = 'Failed to update appointment status. Please try again.';
                    }
                    
                } elseif ($action === 'reject') {
                    if (empty($rejectionReason)) {
                        $error = 'Please provide a reason for rejecting this appointment.';
                    } else {
                        $stmt = $pdo->prepare("UPDATE user_appointments SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejectionReason, $appointmentId]);
                        
                        if ($stmt->rowCount() > 0) {
                            $_SESSION['success_message'] = 'Appointment Declined Successfully!';
                            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=pending");
                            exit();
                        } else {
                            $error = 'Failed to reject appointment. Please try again.';
                        }
                    }
                } elseif ($action === 'complete') {
                    $stmt = $pdo->prepare("
                        UPDATE user_appointments 
                        SET status = 'completed', 
                            completed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$appointmentId]);
                    
                    if ($stmt->rowCount() > 0) {
                        // Get appointment details for success message
                        $stmt = $pdo->prepare("
                            SELECT u.full_name, a.date, a.start_time, a.end_time, ua.priority_number
                            FROM user_appointments ua
                            JOIN sitio1_users u ON ua.user_id = u.id
                            JOIN sitio1_appointments a ON ua.appointment_id = a.id
                            WHERE ua.id = ?
                        ");
                        $stmt->execute([$appointmentId]);
                        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $_SESSION['success_message'] = 'Appointment Completed Successfully!<br>Patient: <strong>' . htmlspecialchars($appointment['full_name']) . '</strong><br>Priority Number: <strong>' . $appointment['priority_number'] . '</strong>';
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=appointment-management&appointment_tab=upcoming");
                        exit();
                    } else {
                        $error = 'Failed to mark appointment as completed. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Error updating appointment: ' . $e->getMessage();
                error_log("Appointment update error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['approve_user'])) {
        $userId = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        // Get user details first
        try {
            $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception("User not found.");
            }
            
            if (in_array($action, ['approve', 'decline'])) {
                if ($action === 'approve') {
                    // Generate unique number
                    $uniqueNumber = generateUniqueNumber($pdo);
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = TRUE, unique_number = ?, status = 'approved' WHERE id = ?");
                    $stmt->execute([$uniqueNumber, $userId]);
                    
                    // Send approval email
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'approved', '', $uniqueNumber);
                    }
                    
                    $_SESSION['success_message'] = 'Resident Account Approved Successfully!<br>Patient ID: <strong>' . $uniqueNumber . '</strong>';
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=account-management&success=true");
                    exit();
                } else {
                    $declineReason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = FALSE, status = 'declined' WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Send decline email with reason
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'declined', $declineReason);
                    }
                    
                    $_SESSION['success_message'] = 'Account Declined Successfully!';
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=account-management&success=true");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = 'Error processing user: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle deletion of cancelled appointments by staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cancelled_appointment'])) {
    $cancelledAppointmentId = intval($_POST['cancelled_appointment_id']);
    
    try {
        // Verify the appointment exists, is cancelled, and was originally pending (user-cancelled)
        $stmt = $pdo->prepare("
            SELECT ua.id, ua.priority_number, ua.invoice_number
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE ua.id = ? AND ua.status = 'cancelled' AND a.staff_id = ?
        ");
        $stmt->execute([$cancelledAppointmentId, $staffId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            throw new Exception('Cancelled appointment not found or you do not have permission to delete it.');
        }
        
        // Only allow deletion of appointments that were originally pending (no priority/invoice numbers)
        if (!empty($appointment['priority_number']) || !empty($appointment['invoice_number'])) {
            throw new Exception('Cannot delete approved appointments. These require manual processing.');
        }
        
        // Delete the cancelled appointment
        $stmt = $pdo->prepare("DELETE FROM user_appointments WHERE id = ?");
        $stmt->execute([$cancelledAppointmentId]);
        
        $_SESSION['success_message'] = 'Cancelled appointment deleted successfully.';
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=appointment-management&appointment_tab=cancelled');
        exit();
        
    } catch (Exception $e) {
        $error = 'Error deleting cancelled appointment: ' . $e->getMessage();
    }
}

// Check for success message from session (after redirect)
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterDate = $_GET['date'] ?? '';

// Get active tab from URL parameter
$activeTab = $_GET['tab'] ?? 'appointment-management';
$activeAppointmentTab = $_GET['appointment_tab'] ?? 'add-slot';

// Get data for dashboard and analytics
try {
    // Basic stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_patients WHERE added_by = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $stats['total_patients'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_consultations WHERE status = 'pending'");
    $stats['pending_consultations'] = $stmt->fetchColumn();

    // Check for missed appointments and update status
    $currentDateTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        UPDATE user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        SET ua.status = 'missed'
        WHERE a.staff_id = ? 
        AND ua.status IN ('pending', 'approved')
        AND CONCAT(a.date, ' ', a.end_time) < ?
        AND CONCAT(a.date, ' ', a.start_time) <= ?
    ");
    $stmt->execute([$staffId, $currentDateTime, $currentDateTime]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_appointments ua JOIN sitio1_appointments a ON ua.appointment_id = a.id WHERE a.staff_id = ? AND ua.status = 'pending'");
    $stmt->execute([$staffId]);
    $stats['pending_appointments'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $stats['unapproved_users'] = $stmt->fetchColumn();
    
    // Analytics data for charts
    $analytics = [];
    
    // Total registered patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient'");
    $analytics['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Approved patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient' AND approved = TRUE");
    $analytics['approved_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Regular patients (patients with more than 1 appointment)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM sitio1_users u 
        JOIN user_appointments ua ON u.id = ua.user_id 
        WHERE u.role = 'patient' 
        GROUP BY u.id 
        HAVING COUNT(ua.id) > 1
    ");
    $regularPatientsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analytics['regular_patients'] = count($regularPatientsResult);
    
    // Appointment status distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ?
        GROUP BY status
    ");
    $stmt->execute([$staffId]);
    $appointmentStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $analytics['appointment_status'] = $appointmentStatusData;
    
    // Monthly appointments trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(ua.created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ? AND ua.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(ua.created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$staffId]);
    $analytics['monthly_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Patient registration trend (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM sitio1_users 
        WHERE role = 'patient' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $analytics['patient_registration_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Available slots with accurate booking counts - ONLY FUTURE SLOTS
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            COUNT(ua.id) as booked_count,
            -- Check if the appointment time is in the past
            (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past,
            -- Check if current time is within the slot time
            (a.date = CURDATE() AND TIME(NOW()) >= a.start_time AND TIME(NOW()) <= a.end_time) as is_current_slot
        FROM sitio1_appointments a 
        LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.staff_id = ? 
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))
        GROUP BY a.id 
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending appointments (only future appointments)
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
               (CONCAT(a.date, ' ', a.end_time) < ?) as is_past
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'pending'
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$currentDateTime, $staffId]);
    $pendingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming appointments (approved but not completed)
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
               ua.priority_number, ua.invoice_number,
               (CONCAT(a.date, ' ', a.end_time) < ?) as is_past
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'approved'
        AND (a.date >= CURDATE())
        ORDER BY a.date, a.start_time, ua.priority_number
    ");
    $stmt->execute([$currentDateTime, $staffId]);
    $upcomingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cancelled appointments (including those cancelled by users)
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, 
               a.date, a.start_time, a.end_time, ua.cancel_reason, ua.cancelled_at,
               CASE 
                   WHEN ua.cancelled_by_user = 1 THEN 'Cancelled by Patient'
                   ELSE 'Cancelled by Staff'
               END as cancelled_by
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'cancelled'
        ORDER BY ua.cancelled_at DESC
    ");
    $stmt->execute([$staffId]);
    $cancelledAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get missed appointments
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
               s.full_name as staff_name, s.specialization,
               ua.created_at as appointment_created_at
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        JOIN sitio1_users s ON a.staff_id = s.id 
        WHERE a.staff_id = ? AND ua.status = 'missed'
        ORDER BY a.date DESC, a.start_time DESC
    ");
    $stmt->execute([$staffId]);
    $missedAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all appointments with filters - UPDATED: Include approved, completed, cancelled, rejected, missed
    $query = "
    SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
           s.full_name as staff_name, s.specialization,
           ua.created_at as appointment_created_at
    FROM user_appointments ua 
    JOIN sitio1_users u ON ua.user_id = u.id 
    JOIN sitio1_appointments a ON ua.appointment_id = a.id 
    JOIN sitio1_users s ON a.staff_id = s.id 
    WHERE a.staff_id = ? 
    AND ua.status IN ('approved', 'completed', 'cancelled', 'rejected', 'missed')
";

$params = [$staffId];

if ($filterStatus !== 'all') {
    $query .= " AND ua.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterDate)) {
    $query .= " AND a.date = ?";
    $params[] = $filterDate;
}

// Sort by date, time, and creation timestamp
$query .= " ORDER BY a.date DESC, a.start_time DESC, ua.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unapproved users with pagination and automatically check ID type
    $usersPerPage = 5;
    $currentPage = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;
    $offset = ($currentPage - 1) * $usersPerPage;
    
    // Get total count for pagination
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $totalUnapprovedUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUnapprovedUsers / $usersPerPage);
    
    // Get paginated unapproved users
    $stmt = $pdo->prepare("
        SELECT *, 
               CASE 
                   WHEN id_image_path IS NOT NULL AND id_image_path != '' THEN 
                       CASE 
                           WHEN id_image_path LIKE 'http%' THEN id_image_path
                           WHEN id_image_path LIKE '/%' THEN id_image_path
                           ELSE CONCAT('../', id_image_path)
                       END
                   ELSE NULL 
               END as display_image_path
        FROM sitio1_users 
        WHERE approved = FALSE AND (status IS NULL OR status != 'declined') 
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $usersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $unapprovedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Automatically check ID type for each user
    foreach ($unapprovedUsers as &$user) {
        if (!empty($user['id_image_path'])) {
            $user['id_type'] = checkIdType($user['id_image_path']);
            $user['is_valid_id'] = isIdValidForVerification($user['id_type']);
        } else {
            $user['id_type'] = 'No ID Uploaded';
            $user['is_valid_id'] = false;
        }
    }
    unset($user); // Break the reference
    
} catch (PDOException $e) {
    $error = 'Error fetching data: ' . $e->getMessage();
}

// Get Philippine holidays for the current year
function getPhilippineHolidays($year) {
    $holidays = array();
    
    // Fixed date holidays
    $fixedHolidays = array(
        $year . '-01-01' => 'New Year\'s Day',
        $year . '-04-09' => 'Day of Valor',
        $year . '-05-01' => 'Labor Day',
        $year . '-06-12' => 'Independence Day',
        $year . '-08-21' => 'Ninoy Aquino Day',
        $year . '-08-26' => 'National Heroes Day',
        $year . '-11-30' => 'Bonifacio Day',
        $year . '-12-25' => 'Christmas Day',
        $year . '-12-30' => 'Rizal Day'
    );
    
    // Variable date holidays (Easter-based)
    $easter = date('Y-m-d', easter_date($year));
    $goodFriday = date('Y-m-d', strtotime($easter . ' -2 days'));
    $holidays[$goodFriday] = 'Good Friday';
    
    // Add all fixed holidays
    foreach ($fixedHolidays as $date => $name) {
        $holidays[$date] = $name;
    }
    
    return $holidays;
}

// Get Philippine holidays
$currentYear = date('Y');
$phHolidays = getPhilippineHolidays($currentYear);

// Get available dates for calendar view - ONLY FUTURE DATES
$calendarDates = [];
$currentMonth = date('m');
$currentYear = date('Y');

// Get all available slots grouped by date - ONLY FUTURE SLOTS
$stmt = $pdo->prepare("
    SELECT 
        a.date, 
        a.start_time, 
        a.end_time,
        a.max_slots,
        COUNT(ua.id) as booked_count,
        GROUP_CONCAT(CONCAT(a.start_time, '-', a.end_time) SEPARATOR ',') as time_slots,
        (a.date = CURDATE() AND TIME(NOW()) >= a.start_time AND TIME(NOW()) <= a.end_time) as is_current_slot
    FROM sitio1_appointments a 
    LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status IN ('pending', 'approved', 'completed')
    WHERE a.staff_id = ? AND a.date >= CURDATE()
    GROUP BY a.date, a.start_time, a.end_time, a.max_slots
    ORDER BY a.date, a.start_time
");
$stmt->execute([$staffId]);
$availableDates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by date
$dateSlots = [];
foreach ($availableDates as $slot) {
    $date = $slot['date'];
    if (!isset($dateSlots[$date])) {
        $dateSlots[$date] = [];
    }
    $dateSlots[$date][] = $slot;
}

// Find the next available date with slots
$nextAvailableDate = null;
if (!empty($availableDates)) {
    $nextAvailableDate = $availableDates[0]['date'];
}

// Get all occupied time slots for the current staff
$stmt = $pdo->prepare("
    SELECT 
        a.date,
        a.start_time,
        a.end_time
    FROM sitio1_appointments a
    WHERE a.staff_id = ? AND a.date >= CURDATE()
    ORDER BY a.date, a.start_time
");
$stmt->execute([$staffId]);
$occupiedSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create an array of occupied time slots by date
$occupiedSlotsByDate = [];
foreach ($occupiedSlots as $slot) {
    $date = $slot['date'];
    if (!isset($occupiedSlotsByDate[$date])) {
        $occupiedSlotsByDate[$date] = [];
    }
    $occupiedSlotsByDate[$date][] = $slot['start_time'] . ' - ' . $slot['end_time'];
}

// Pagination configuration
$recordsPerPage = 5;

// Available Slots Pagination
$availableSlotsPage = isset($_GET['available_page']) ? max(1, intval($_GET['available_page'])) : 1;
$totalAvailableSlots = count($availableSlots);
$totalAvailablePages = ceil($totalAvailableSlots / $recordsPerPage);
$availableSlotsPaginated = array_slice($availableSlots, ($availableSlotsPage - 1) * $recordsPerPage, $recordsPerPage);

// Pending Appointments Pagination
$pendingPage = isset($_GET['pending_page']) ? max(1, intval($_GET['pending_page'])) : 1;
$totalPending = count($pendingAppointments);
$totalPendingPages = ceil($totalPending / $recordsPerPage);
$pendingAppointmentsPaginated = array_slice($pendingAppointments, ($pendingPage - 1) * $recordsPerPage, $recordsPerPage);

// Upcoming Appointments Pagination
$upcomingPage = isset($_GET['upcoming_page']) ? max(1, intval($_GET['upcoming_page'])) : 1;
$totalUpcoming = count($upcomingAppointments);
$totalUpcomingPages = ceil($totalUpcoming / $recordsPerPage);
$upcomingAppointmentsPaginated = array_slice($upcomingAppointments, ($upcomingPage - 1) * $recordsPerPage, $recordsPerPage);

// Cancelled Appointments Pagination
$cancelledPage = isset($_GET['cancelled_page']) ? max(1, intval($_GET['cancelled_page'])) : 1;
$totalCancelled = count($cancelledAppointments);
$totalCancelledPages = ceil($totalCancelled / $recordsPerPage);
$cancelledAppointmentsPaginated = array_slice($cancelledAppointments, ($cancelledPage - 1) * $recordsPerPage, $recordsPerPage);

// All Appointments Pagination
$allPage = isset($_GET['all_page']) ? max(1, intval($_GET['all_page'])) : 1;
$totalAll = count($allAppointments);
$totalAllPages = ceil($totalAll / $recordsPerPage);
$allAppointmentsPaginated = array_slice($allAppointments, ($allPage - 1) * $recordsPerPage, $recordsPerPage);

// Missed Appointments Pagination
$missedPage = isset($_GET['missed_page']) ? max(1, intval($_GET['missed_page'])) : 1;
$totalMissed = count($missedAppointments);
$totalMissedPages = ceil($totalMissed / $recordsPerPage);
$missedAppointmentsPaginated = array_slice($missedAppointments, ($missedPage - 1) * $recordsPerPage, $recordsPerPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Modal Styles - Centered and Consistent */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
            position: relative;
            margin: auto;
        }
        
        .modal-overlay.active .modal-container {
            transform: translateY(0);
            opacity: 1;
        }
        
        .modal-header {
            padding: 24px 24px 0 24px;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 0 24px 24px 24px;
        }
        
        /* Action Modal - Centered Success/Error Messages */
        .action-modal {
            max-width: 400px;
        }
        
        .action-modal .modal-body {
            text-align: center;
            padding: 40px 24px;
        }
        
        .action-modal-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }
        
        .action-modal-success .action-modal-icon {
            background: #d1fae5;
            color: #059669;
        }
        
        .action-modal-error .action-modal-icon {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .action-modal-info .action-modal-icon {
            background: #dbeafe;
            color: #3b82f6;
        }
        
        .action-modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }
        
        .action-modal-message {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        /* Other styles remain the same as before */
        .fixed {
            position: fixed;
        }
        .inset-0 {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
        }
        .hidden {
            display: none;
        }
        .z-50 {
            z-index: 50;
        }
        .tab-active {
            border-bottom: 2px solid #3C96E1;
            color: #3C96E1;
        }
        .count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.8rem;
            height: 1.8rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
            padding: 0 0.6rem;
            margin-left: 0.5rem;
        }
        .action-button {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }
        .action-button:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
        }
        .action-button:active {
            transform: translateY(-1px) !important;
        }
        .slot-past {
            background-color: #f3f4f6;
            color: #9ca3af;
        }
        .slot-full {
            background-color: #fef2f2;
            color: #ef4444;
        }
        .slot-available {
            background-color: #f0fdf4;
            color: #16a34a;
        }
        .slot-occupied {
            background-color: #ffedd5;
            color: #ea580c;
        }
        
        /* Enhanced Calendar Styles - Blue Theme */
        .calendar-day {
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.2s ease-in-out;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: white;
        }

        .calendar-day.selected {
            border-color: #3C96E1 !important;
            background: #3C96E1 !important;
            color: white !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3);
            transform: scale(1.02);
            z-index: 10;
        }

        .calendar-day.disabled {
            opacity: 0.4;
            cursor: not-allowed !important;
            background: #f8fafc !important;
            color: #9ca3af !important;
            border-color: #e5e7eb;
        }

        .calendar-day .font-semibold {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .calendar-day .day-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
        }

        .calendar-day.has-slots .day-indicator {
            background: #10b981;
        }

        .calendar-day.no-slots .day-indicator {
            background: #ef4444;
        }

        /* Time Slot Styles */
        .time-slot {
            transition: all 0.2s ease-in-out;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            background: white;
            position: relative;
        }

        .time-slot.disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
            background: #f8fafc !important;
            color: #9ca3af !important;
            border-color: #e5e7eb;
        }

        .time-slot.selected {
            border-color: #3C96E1 !important;
            background: #3C96E1 !important;
            color: white !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3);
            transform: scale(1.02);
            z-index: 5;
        }

        .time-slot.selected * {
            color: white !important;
            opacity: 1 !important;
        }

        /* Ensure text visibility in selected states */
        .calendar-day.selected *,
        .time-slot.selected * {
            color: white !important;
            opacity: 1 !important;
        }

        /* Holiday and weekend specific styles */
        .calendar-day.holiday:not(.selected) {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        .calendar-day.weekend:not(.selected) {
            background: #eff6ff;
            border-color: #dbeafe;
            color: #1e40af;
        }

        .calendar-day.today:not(.selected) {
            background: #dbeafe;
            border-color: #3C96E1;
            color: #1e40af;
            font-weight: bold;
        }
        
        /* Calendar date status indicator */
        .calendar-day.occupied {
            background: #fff3cd !important;
            border-color: #ffc107 !important;
            color: #856404 !important;
        }
        
        .calendar-day.occupied .date-status {
            font-size: 10px;
            font-weight: bold;
            color: #856404;
            background: rgba(255, 193, 7, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 4px;
        }
        
        /* Time slot status */
        .time-slot.occupied {
            background: #ffedd5 !important;
            border-color: #ea580c !important;
            color: #9a3412 !important;
            cursor: not-allowed !important;
        }
        
        .time-slot.occupied .slot-status {
            font-size: 12px;
            font-weight: bold;
            color: #9a3412;
            margin-top: 4px;
        }
        
        .time-slot.current-time {
            background: #fee2e2 !important;
            border-color: #dc2626 !important;
            color: #7f1d1d !important;
            cursor: not-allowed !important;
        }
        
        .time-slot.current-time .slot-status {
            font-size: 12px;
            font-weight: bold;
            color: #7f1d1d;
            margin-top: 4px;
        }
        
        /* Selected date status display */
        .date-status-display {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }
        
        .date-status-display.occupied {
            background: rgba(255, 193, 7, 0.2);
            color: #856404;
        }
        
        /* NEW: Set Date indicator - position at right side upper */
        .set-date-indicator {
            position: absolute;
            top: 4px;
            right: 4px;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 4px;
            border-radius: 3px;
            background: rgba(255, 193, 7, 0.9);
            color: #856404;
            z-index: 5;
            white-space: nowrap;
        }
        
        /* Modal buttons */
        .modal-button {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
        }

        /* Simple hover effect for available dates */
        .calendar-day:not(.disabled):not(.selected):not(.occupied):hover {
            border-color: #3C96E1;
            background: #eff6ff;
        }

        .time-slot:not(.disabled):not(.selected):not(.occupied):not(.current-time):hover {
            border-color: #3C96E1;
            background: #eff6ff;
        }
        
        /* Button disabled state */
        .button-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
            background-color: #9ca3af !important;
        }
        .button-disabled:hover {
            transform: none !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }

        /* Updated button styles for Edit and Delete */
        .btn-edit {
            background-color: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-edit:hover {
            background-color: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        .btn-delete {
            background-color: #ef4444 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 8px 16px !important;
            font-weight: 500 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3) !important;
        }

        .btn-delete:hover {
            background-color: #dc2626 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.4) !important;
        }

        /* Updated button style for View Details */
        .btn-view-details {
            background-color: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 8px 16px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-view-details:hover {
            background-color: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 8px;
        }

        .pagination-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            border-radius: 9999px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            text-decoration: none;
        }

        .pagination-button:hover {
            background: #3C96E1;
            color: white;
            border-color: #3C96E1;
            transform: translateY(-1px);
        }

        .pagination-button.active {
            background: #3C96E1;
            color: white;
            border-color: #3C96E1;
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f3f4f6;
            color: #9ca3af;
        }

        .pagination-button.disabled:hover {
            background: #f3f4f6;
            color: #9ca3af;
            border-color: #d1d5db;
            transform: none;
        }

        /* Export buttons with rounded corners */
        .btn-export {
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }

        .btn-export:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
        }

        /* Enhanced Tab Button Styles with Blue Theme */
        .nav-tab-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-right: 12px;
            margin-bottom: 8px;
            background: #3C96E1;
            color: white;
        }

        .nav-tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3);
            background: #2a7bc8;
        }

        .nav-tab-button.active {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
        }

        .nav-tab-button i {
            margin-right: 8px;
            font-size: 18px;
        }

        .nav-tab-button .count-badge {
            margin-left: 8px;
            font-size: 0.8rem;
            min-width: 1.6rem;
            height: 1.6rem;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .nav-tab-button.active .count-badge {
            background: #3C96E1;
            color: white;
        }

        /* Appointment Management Tab Button */
        .tab-appointment-management {
            background: #3C96E1;
            color: white;
        }

        .tab-appointment-management:hover {
            background: #2a7bc8;
        }

        .tab-appointment-management.active {
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
        }

        /* Account Approvals Tab Button */
        .tab-account-management {
            background: #3C96E1;
            color: white;
        }

        .tab-account-management:hover {
            background: #2a7bc8;
        }

        .tab-account-management.active {
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
        }

        /* Analytics Dashboard Tab Button */
        .tab-analytics {
            background: #3C96E1;
            color: white;
        }

        .tab-analytics:hover {
            background: #2a7bc8;
        }

        .tab-analytics.active {
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
        }

        /* Blue Theme for Appointment Management Tabs */
        .appointment-tab-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-right: 8px;
            margin-bottom: 8px;
            background: #3C96E1;
            color: white;
        }

        .appointment-tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3);
            background: #2a7bc8;
        }

        .appointment-tab-button.active {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 150, 225, 0.4);
            background: white;
            color: #3C96E1;
            border: 2px solid #3C96E1;
        }

        .appointment-tab-button i {
            margin-right: 8px;
            font-size: 16px;
        }

        .appointment-tab-button .count-badge {
            margin-left: 8px;
            font-size: 0.7rem;
            min-width: 1.4rem;
            height: 1.4rem;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .appointment-tab-button.active .count-badge {
            background: #3C96E1;
            color: white;
        }

        /* Blue action buttons */
        .btn-blue {
            background: #3C96E1 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-blue:hover {
            background: #2a7bc8 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.4) !important;
        }

        .btn-blue:active {
            transform: translateY(-1px) !important;
        }

        /* New Approve and Decline Button Styles with White Background */
        .btn-approve-white {
            background: white !important;
            color: #3C96E1 !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: 2px solid #3C96E1 !important;
            box-shadow: 0 2px 4px rgba(60, 150, 225, 0.1) !important;
        }

        .btn-approve-white:hover {
            background: #3C96E1 !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(60, 150, 225, 0.3) !important;
        }

        .btn-decline-white {
            background: white !important;
            color: #EF4444 !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: 2px solid #EF4444 !important;
            box-shadow: 0 2px 4px rgba(239, 68, 68, 0.1) !important;
        }

        .btn-decline-white:hover {
            background: #EF4444 !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3) !important;
        }

        /* Success button in blue theme */
        .btn-success-blue {
            background: #48BB78 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(72, 187, 120, 0.3) !important;
        }

        .btn-success-blue:hover {
            background: #38A169 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(72, 187, 120, 0.4) !important;
        }

        /* Warning button in blue theme */
        .btn-warning-blue {
            background: #ED8936 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(237, 137, 54, 0.3) !important;
        }

        .btn-warning-blue:hover {
            background: #DD6B20 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(237, 137, 54, 0.4) !important;
        }

        /* Danger button in blue theme */
        .btn-danger-blue {
            background: #F56565 !important;
            color: white !important;
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(245, 101, 101, 0.3) !important;
        }

        .btn-danger-blue:hover {
            background: #E53E3E !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(245, 101, 101, 0.4) !important;
        }

        /* Enhanced Chart Container Styles */
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            height: 400px;
            position: relative;
            overflow: hidden;
        }

        .chart-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }

        .chart-title i {
            margin-right: 8px;
            color: #3C96E1;
        }

        /* Analytics grid layout improvements */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Chart canvas responsive sizing */
        .chart-container canvas {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
        }

        /* Analytics grid layout */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .analytics-value {
            font-size: 32px;
            font-weight: 700;
            color: #3C96E1;
            margin: 8px 0;
        }

        .analytics-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .analytics-trend {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .trend-up {
            background: #dcfce7;
            color: #166534;
        }

        .trend-down {
            background: #fecaca;
            color: #991b1b;
        }

        .trend-neutral {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        /* Improved user details modal layout */
        .info-label {
            font-weight: 700 !important;
            color: #374151 !important;
            font-size: 14px !important;
        }
        
        .info-value {
            font-weight: 500 !important;
            color: #4b5563 !important;
            font-size: 14px !important;
        }
        
        .id-validation-status {
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
            text-align: center;
            display: inline-block;
        }
        
        .id-valid {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .id-invalid {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .id-unknown {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
        
        /* AJAX Loader */
        .ajax-loader {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3C96E1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* User details modal specific styles */
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .detail-section h4 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            flex: 1;
        }
        
        .detail-value {
            color: #2d3748;
            flex: 2;
            text-align: right;
        }
        
        .id-preview-container {
            max-height: 200px;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .id-preview-container img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        
        /* Missed appointment status */
        .status-missed {
            background: #fef3c7 !important;
            color: #92400e !important;
        }
        
        /* Desktop-sized modal styles */
        .modal-desktop {
            max-width: 800px !important;
            width: 95% !important;
        }
        
        .modal-wide-desktop {
            max-width: 900px !important;
            width: 95% !important;
        }
        
        /* Choose time slot modal desktop size */
        .modal-time-slot-desktop {
            max-width: 800px !important;
            width: 95% !important;
        }
        
        /* Time slot container for desktop modal */
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        @media (max-width: 768px) {
            .time-slots-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Disabled appointment actions */
        .disabled-action {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
        
        .disabled-action:hover {
            transform: none !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Horizontal layout for user details */
        .horizontal-user-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .horizontal-user-details .detail-section {
            margin-bottom: 0;
        }
        
        @media (max-width: 1024px) {
            .horizontal-user-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-100">

<!-- AJAX Loader -->
<div id="ajaxLoader" class="ajax-loader">
    <div class="spinner"></div>
    <p>Loading analytics...</p>
</div>

<div class="container mx-auto px-4 py-6">
    <!-- Dashboard Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            Admin Dashboard
        </h1>
        <!-- Help Button -->
        <button onclick="openHelpModal()" class="help-icon bg-gray-200 text-gray-600 p-2 rounded-full hover:bg-gray-300 transition">
            <i class="fas fa-question-circle text-xl"></i>
        </button>
    </div>

    <!-- Help/Guide Modal -->
    <div id="helpModal" class="modal-overlay hidden">
        <div class="modal-container max-w-4xl">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h3 class="text-2xl font-semibold text-gray-900">Staff Dashboard Guide</h3>
                    <button onclick="closeHelpModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="modal-body">
                <div class="bg-blue-50 p-4 rounded-lg mb-6">
                    <p class="text-blue-800"><strong>Welcome to the Community Health Tracker Staff Dashboard!</strong> This guide will help you understand how to use all the features available to you as a staff member.</p>
                </div>
                
                <!-- Guide content -->
                <div class="space-y-4">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h4 class="font-semibold text-lg text-gray-800">Appointment Management</h4>
                        <p class="text-gray-600">Manage your available time slots, view pending appointments, and handle appointment approvals.</p>
                    </div>
                    
                    <div class="border-l-4 border-green-500 pl-4">
                        <h4 class="font-semibold text-lg text-gray-800">Account Approvals</h4>
                        <p class="text-gray-600">Review and approve new patient registrations for system access.</p>
                    </div>

                    <div class="border-l-4 border-purple-500 pl-4">
                        <h4 class="font-semibold text-lg text-gray-800">Analytics Dashboard</h4>
                        <p class="text-gray-600">View comprehensive analytics and insights about patients, appointments, and system usage.</p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <div class="flex justify-end">
                    <button type="button" onclick="closeHelpModal()" class="px-6 py-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition font-medium">
                        Got it, thanks!
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-user-injured text-2xl text-blue-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Your Patients</h3>
                    <p class="text-3xl font-bold text-blue-600"><?= $stats['total_patients'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-file-medical text-2xl text-yellow-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Consultations</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending_consultations'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-calendar-check text-2xl text-purple-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Appointments</h3>
                    <p class="text-3xl font-bold text-purple-600"><?= $stats['pending_appointments'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-user-clock text-2xl text-red-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Unapproved Users</h3>
                    <p class="text-3xl font-bold text-red-600"><?= $stats['unapproved_users'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="mb-6">
        <div class="flex flex-wrap items-center" id="dashboardTabs" role="tablist">
            <button class="nav-tab-button tab-analytics <?= $activeTab === 'analytics' ? 'active' : '' ?>" 
                    id="analytics-tab" data-tabs-target="#analytics" type="button" role="tab" aria-controls="analytics" aria-selected="<?= $activeTab === 'analytics' ? 'true' : 'false' ?>">
                <i class="fas fa-chart-bar"></i>
                Analytics Dashboard
            </button>
            <button class="nav-tab-button tab-appointment-management <?= $activeTab === 'appointment-management' ? 'active' : '' ?>" 
                    id="appointment-tab" data-tabs-target="#appointment-management" type="button" role="tab" aria-controls="appointment-management" aria-selected="<?= $activeTab === 'appointment-management' ? 'true' : 'false' ?>">
                <i class="fas fa-calendar-alt"></i>
                Appointment Management
                <span class="count-badge"><?= $stats['pending_appointments'] ?></span>
            </button>
            
            <button class="nav-tab-button tab-account-management <?= $activeTab === 'account-management' ? 'active' : '' ?>" 
                    id="account-tab" data-tabs-target="#account-management" type="button" role="tab" aria-controls="account-management" aria-selected="<?= $activeTab === 'account-management' ? 'true' : 'false' ?>">
                <i class="fas fa-user-check"></i>
                Account Approvals
                <span class="count-badge"><?= $stats['unapproved_users'] ?></span>
            </button>
        </div>
    </div>
    
    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Appointment Management Section -->
        <div class="<?= $activeTab === 'appointment-management' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="appointment-management" role="tabpanel" aria-labelledby="appointment-tab">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-blue-700">Appointment Management</h2>
            </div>
            
            <!-- Navigation Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <div class="flex flex-wrap -mb-px" id="appointmentTabs" role="tablist">
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'add-slot' ? 'active' : '' ?>" 
                            id="add-slot-tab" data-tabs-target="#add-slot" type="button" role="tab" aria-controls="add-slot" aria-selected="<?= $activeAppointmentTab === 'add-slot' ? 'true' : 'false' ?>">
                        <i class="fas fa-plus-circle"></i>
                        Add Slot
                    </button>
                    
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'available-slots' ? 'active' : '' ?>" 
                            id="available-slots-tab" data-tabs-target="#available-slots" type="button" role="tab" aria-controls="available-slots" aria-selected="<?= $activeAppointmentTab === 'available-slots' ? 'true' : 'false' ?>">
                        <i class="fas fa-list-alt"></i>
                        Available Slots
                        <span class="count-badge"><?= count($availableSlots) ?></span>
                    </button>
                    
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'pending' ? 'active' : '' ?>" 
                            id="pending-tab" data-tabs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="<?= $activeAppointmentTab === 'pending' ? 'true' : 'false' ?>">
                        <i class="fas fa-clock"></i>
                        Pending
                        <span class="count-badge"><?= count($pendingAppointments) ?></span>
                    </button>
                    
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'upcoming' ? 'active' : '' ?>" 
                            id="upcoming-tab" data-tabs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="<?= $activeAppointmentTab === 'upcoming' ? 'true' : 'false' ?>">
                        <i class="fas fa-calendar-day"></i>
                        Upcoming
                        <span class="count-badge"><?= count($upcomingAppointments) ?></span>
                    </button>
                    
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'cancelled' ? 'active' : '' ?>" 
                            id="cancelled-tab" data-tabs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="<?= $activeAppointmentTab === 'cancelled' ? 'true' : 'false' ?>">
                        <i class="fas fa-times-circle"></i>
                        Cancelled
                        <span class="count-badge"><?= count($cancelledAppointments) ?></span>
                    </button>
                    
                    
                    
                    <button class="appointment-tab-button <?= $activeAppointmentTab === 'all' ? 'active' : '' ?>" 
                            id="all-tab" data-tabs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="<?= $activeAppointmentTab === 'all' ? 'true' : 'false' ?>">
                        <i class="fas fa-history"></i>
                        All Appointments
                        <span class="count-badge"><?= count($allAppointments) ?></span>
                    </button>
                </div>
            </div>
            
            <!-- Tab Contents -->
            <div class="tab-content">
                <!-- Add Available Slot -->
                <div class="<?= $activeAppointmentTab === 'add-slot' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="add-slot" role="tabpanel" aria-labelledby="add-slot-tab">
                    <h2 class="text-xl font-semibold mb-4 text-blue-700">Add Available Slot</h2>
                    
                    <!-- Calendar View for Date Selection -->
                    <div class="mb-8 bg-white p-4 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4 text-blue-700">Select a Date</h3>
                        
                        <div class="flex justify-between items-center mb-4">
                            <button id="prevMonth" class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h4 id="currentMonthYear" class="text-xl font-semibold">
                                <?= date('F Y') ?>
                            </h4>
                            <button id="nextMonth" class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <div class="grid grid-cols-7 gap-2 mb-2">
                            <div class="text-center font-semibold text-red-500">Sun</div>
                            <div class="text-center font-semibold">Mon</div>
                            <div class="text-center font-semibold">Tue</div>
                            <div class="text-center font-semibold">Wed</div>
                            <div class="text-center font-semibold">Thu</div>
                            <div class="text-center font-semibold">Fri</div>
                            <div class="text-center font-semibold text-blue-500">Sat</div>
                        </div>
                        
                        <div id="calendar" class="grid grid-cols-7 gap-2">
                            <!-- Calendar will be populated by JavaScript -->
                        </div>
                        
                        <div class="mt-4 flex items-center space-x-4 flex-wrap gap-2">
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-blue-500 rounded mr-2"></div>
                                <span class="text-sm">Selected Date</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-red-100 rounded mr-2"></div>
                                <span class="text-sm">Holiday</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-blue-100 rounded mr-2"></div>
                                <span class="text-sm">Today</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-blue-50 rounded mr-2"></div>
                                <span class="text-sm">Weekend</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-yellow-100 rounded mr-2"></div>
                                <span class="text-sm">Occupied Date</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-4 h-4 bg-gray-100 rounded mr-2"></div>
                                <span class="text-sm">Unavailable</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Date Display -->
                    <div id="selectedDateSection" class="hidden mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-blue-700">Selected Date</h3>
                                <p class="text-blue-600" id="selectedDateDisplay"></p>
                                <div class="text-sm mt-1" id="selectedDateStatus"></div>
                            </div>
                            <button type="button" onclick="openTimeSlotModal()" 
                                    class="bg-blue-600 text-white px-8 py-5 rounded-full hover:bg-blue-700 transition flex items-center justify-center font-medium text-base">
                                <i class="fas fa-clock mr-2"></i> Choose Time Slot
                            </button>
                        </div>
                    </div>
                    
                    <!-- Hidden form fields for submission -->
                    <input type="hidden" id="selected_date" name="date">
                    <input type="hidden" id="selected_time_slot" name="time_slot">
                    <input type="hidden" id="selected_max_slots" name="max_slots" value="1">
                </div>
                
                <!-- Available Slots -->
                <div class="<?= $activeAppointmentTab === 'available-slots' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="available-slots" role="tabpanel" aria-labelledby="available-slots-tab">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-blue-700">Your Available Slots</h2>
                        <?php
                        // Calculate total available slots across all time slots
                        $totalAvailableSlots = 0;
                        $totalBookedSlots = 0;
                        $totalMaxSlots = 0;
                        
                        if (!empty($availableSlotsPaginated)) {
                            foreach ($availableSlotsPaginated as $slot) {
                                $bookedCount = $slot['booked_count'] ?? 0;
                                $maxSlots = $slot['max_slots'];
                                $available = max(0, $maxSlots - $bookedCount);
                                $totalAvailableSlots += $available;
                                $totalBookedSlots += $bookedCount;
                                $totalMaxSlots += $maxSlots;
                            }
                        }
                        ?>
                        <span class="text-sm text-gray-600">
                            <?= $totalBookedSlots ?> booked / <?= $totalMaxSlots ?> total slots
                        </span>
                    </div>
                    
                    <?php if (empty($availableSlotsPaginated)): ?>
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-gray-600">No available slots found.</p>
                            <button onclick="switchAppointmentTab('add-slot')" class="btn-blue mt-2">
                                <i class="fas fa-plus-circle mr-2"></i> Add New Slot
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booked</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($availableSlotsPaginated as $slot): 
                                        $currentDate = date('Y-m-d');
                                        $currentTime = date('H:i:s');
                                        $isPast = $slot['date'] < $currentDate || 
                                                 ($slot['date'] == $currentDate && $slot['end_time'] < $currentTime);
                                        $isToday = $slot['date'] == $currentDate;
                                        $isCurrentSlot = $slot['is_current_slot'] ?? false;
                                        $bookedCount = $slot['booked_count'] ?? 0;
                                        $maxSlots = $slot['max_slots'];
                                        $availableSlotsCount = max(0, $maxSlots - $bookedCount);
                                        $percentage = $maxSlots > 0 ? min(100, ($bookedCount / $maxSlots) * 100) : 0;
                                        
                                        // Determine status class
                                        $statusClass = 'slot-available';
                                        $statusText = 'Available';
                                        
                                        if ($isCurrentSlot) {
                                            $statusClass = 'slot-occupied';
                                            $statusText = 'Ongoing';
                                        } elseif ($isPast) {
                                            $statusClass = 'slot-past';
                                            $statusText = 'Past';
                                        } elseif ($availableSlotsCount === 0) {
                                            $statusClass = 'slot-full';
                                            $statusText = 'Full';
                                        }
                                    ?>
                                        <tr class="<?= $isPast ? 'bg-gray-50' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= date('D, M d, Y', strtotime($slot['date'])) ?>
                                                </div>
                                                <?php if ($isToday): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Today</span>
                                                <?php elseif ($isPast): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Past</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('g:i A', strtotime($slot['start_time'])) ?> - <?= date('g:i A', strtotime($slot['end_time'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $maxSlots ?> slots
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $bookedCount ?> booked
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium 
                                                <?= $availableSlotsCount == 0 ? 'text-red-600' : ($availableSlotsCount <= 2 ? 'text-yellow-600' : 'text-green-600') ?>">
                                                <?= $availableSlotsCount ?> available
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $statusClass ?>">
                                                    <?= $statusText ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if (!$isPast && !$isCurrentSlot): ?>
                                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($slot)) ?>, '<?= $slot['start_time'] ?> - <?= $slot['end_time'] ?>')" 
                                                            class="btn-blue mr-2">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </button>
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                                                        <button type="submit" name="delete_slot" 
                                                                class="btn-danger-blue" 
                                                                onclick="return confirm('Are you sure you want to delete this slot?')">
                                                            <i class="fas fa-trash mr-1"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php elseif ($isCurrentSlot): ?>
                                                    <span class="text-orange-600 font-medium">Slot in progress</span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">No actions available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Available Slots -->
                        <?php if ($totalAvailablePages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($availableSlotsPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=available-slots&available_page=<?= $availableSlotsPage - 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalAvailablePages; $i++): ?>
                                    <?php if ($i == $availableSlotsPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=available-slots&available_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($availableSlotsPage < $totalAvailablePages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=available-slots&available_page=<?= $availableSlotsPage + 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pending Appointments -->
                <div class="<?= $activeAppointmentTab === 'pending' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-blue-700">Pending Appointments</h2>
                        <span class="text-sm text-gray-600"><?= count($pendingAppointments) ?> pending</span>
                    </div>
                    
                    <?php if (empty($pendingAppointmentsPaginated)): ?>
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-gray-600">No pending appointments.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Concerns</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pendingAppointmentsPaginated as $appointment): 
                                        $isPast = $appointment['is_past'] ?? false;
                                    ?>
                                        <tr class="<?= $isPast ? 'status-row-missed' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($appointment['unique_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['email']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('D, M d, Y', strtotime($appointment['date'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($isPast): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Missed Appointment
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No health concerns specified' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if (!$isPast): ?>
                                                    <button onclick="openAppointmentApprovalModal(<?= $appointment['id'] ?>)" 
                                                            class="btn-success-blue mr-2">
                                                        <i class="fas fa-check-circle mr-1"></i> Approve
                                                    </button>
                                                    <button onclick="openRejectionModal(<?= $appointment['id'] ?>)" 
                                                            class="btn-danger-blue">
                                                        <i class="fas fa-times-circle mr-1"></i> Reject
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-yellow-600 font-medium">Missed - No actions</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Pending Appointments -->
                        <?php if ($totalPendingPages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($pendingPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=pending&pending_page=<?= $pendingPage - 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalPendingPages; $i++): ?>
                                    <?php if ($i == $pendingPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=pending&pending_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($pendingPage < $totalPendingPages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=pending&pending_page=<?= $pendingPage + 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Upcoming Appointments -->
                <div class="<?= $activeAppointmentTab === 'upcoming' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-blue-700">Upcoming Appointments</h2>
                        <span class="text-sm text-gray-600"><?= count($upcomingAppointments) ?> upcoming</span>
                    </div>
                    
                    <?php if (empty($upcomingAppointmentsPaginated)): ?>
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-gray-600">No upcoming appointments.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority Number</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($upcomingAppointmentsPaginated as $appointment): 
                                        $isToday = $appointment['date'] == date('Y-m-d');
                                        $isPast = $appointment['is_past'] ?? false;
                                    ?>
                                        <tr class="<?= $isPast ? 'status-row-missed' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                <div class="flex items-center space-x-3">
    <!-- Label -->
    <span class="text-1xl text-gray-500 font-medium">
        Unique Number:
    </span>

    <!-- Value Container -->
    <div class="flex items-center bg-gray-500 px-4 py-2 rounded-lg text-white text-1xl">
        
        <!-- Copy Button -->
        <button
            type="button"
            onclick="copyToClipboard('<?= htmlspecialchars($appointment['unique_number']) ?>')"
            class="mr-2 hover:text-gray-200 focus:outline-none"
            title="Copy to clipboard"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16h8m-8-4h8m-2-6H6a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V8l-4-4z"/>
            </svg>
        </button>

        <!-- Unique Number Text -->
        <span class="text-center">
            <?= htmlspecialchars($appointment['unique_number']) ?>
        </span>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Optional feedback
        alert('Unique Number copied!');
    });
}
</script>

                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['email']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('D, M d, Y', strtotime($appointment['date'])) ?>
                                                    <?php if ($isToday): ?>
                                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Today</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-red-600">
                                                    <?= !empty($appointment['priority_number']) ? htmlspecialchars($appointment['priority_number']) : 'N/A' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= !empty($appointment['invoice_number']) ? htmlspecialchars($appointment['invoice_number']) : 'N/A' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($isPast): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Missed Consultation
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Approved
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if (!$isPast): ?>
                                                    <button onclick="openCompleteConfirmationModal(
                                                        <?= $appointment['id'] ?>, 
                                                        '<?= htmlspecialchars(addslashes($appointment['full_name'])) ?>',
                                                        '<?= $appointment['date'] ?>',
                                                        '<?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?>',
                                                        '<?= $appointment['priority_number'] ?>'
                                                    )" 
                                                            class="btn-success-blue">
                                                        <i class="fas fa-check-circle mr-1"></i> Mark Completed
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-yellow-600 font-medium">Missed - No actions</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Upcoming Appointments -->
                        <?php if ($totalUpcomingPages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($upcomingPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=upcoming&upcoming_page=<?= $upcomingPage - 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalUpcomingPages; $i++): ?>
                                    <?php if ($i == $upcomingPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=upcoming&upcoming_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($upcomingPage < $totalUpcomingPages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=upcoming&upcoming_page=<?= $upcomingPage + 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Cancelled Appointments -->
                <div class="<?= $activeAppointmentTab === 'cancelled' ? '' : 'hidden' ?> p-6 bg-white rounded-lg border border-gray-200" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-red-700">Cancelled Appointments</h2>
                        <span class="text-sm text-gray-600"><?= count($cancelledAppointments) ?> cancelled</span>
                    </div>
                    
                    <?php if (empty($cancelledAppointmentsPaginated)): ?>
                        <div class="bg-blue-50 p-6 rounded-lg text-center">
                            <p class="text-gray-600">No cancelled appointments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cancelled By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Original Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cancelled At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($cancelledAppointmentsPaginated as $appointment): 
                                        $wasPending = empty($appointment['priority_number']) && empty($appointment['invoice_number']);
                                        $cancelledBy = $appointment['cancelled_by'] ?? ($wasPending ? 'Cancelled by Patient' : 'Cancelled by Staff');
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($appointment['unique_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($appointment['date'])) ?></div>
                                                <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full <?= ($cancelledBy == 'Cancelled by Patient' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800') ?>">
                                                    <?= htmlspecialchars($cancelledBy) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 max-w-xs truncate"><?= !empty($appointment['cancel_reason']) ? htmlspecialchars($appointment['cancel_reason']) : 'No reason provided' ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $wasPending ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                                    <?= $wasPending ? 'Pending' : 'Approved' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M d, Y g:i A', strtotime($appointment['cancelled_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openCancelledDetailsModal(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                        class="btn-blue mr-2">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                                <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this cancelled appointment record? This action cannot be undone.');">
                                                    <input type="hidden" name="cancelled_appointment_id" value="<?= $appointment['id'] ?>">
                                                    <button type="submit" name="delete_cancelled_appointment" 
                                                            class="btn-delete">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Cancelled Appointments -->
                        <?php if ($totalCancelledPages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($cancelledPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=cancelled&cancelled_page=<?= $cancelledPage - 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalCancelledPages; $i++): ?>
                                    <?php if ($i == $cancelledPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=cancelled&cancelled_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($cancelledPage < $totalCancelledPages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=cancelled&cancelled_page=<?= $cancelledPage + 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Information Box -->
                        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-blue-800">Cancellation Information</h4>
                                    <ul class="text-sm text-blue-700 mt-2 space-y-1">
                                        <li>• <strong>Pending appointments</strong> can be cancelled directly by patients online</li>
                                        <li>• <strong>Approved appointments</strong> require patients to contact support for cancellation</li>
                                        <li>• Click "View" to see detailed cancellation information</li>
                                        <li>• Use "Delete" to remove cancelled appointment records (approved appointments cannot be deleted)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Missed Appointments -->
                <div class="<?= $activeAppointmentTab === 'missed' ? '' : 'hidden' ?> p-6 bg-white rounded-lg border border-gray-200" id="missed" role="tabpanel" aria-labelledby="missed-tab">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-semibold text-yellow-700">Missed Appointments</h2>
                        <span class="text-sm text-gray-600"><?= count($missedAppointments) ?> missed</span>
                    </div>
                    
                    <?php if (empty($missedAppointmentsPaginated)): ?>
                        <div class="bg-blue-50 p-6 rounded-lg text-center">
                            <p class="text-gray-600">No missed appointments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority Number</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Health Concerns</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($missedAppointmentsPaginated as $appointment): ?>
                                        <tr class="status-row-missed">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($appointment['unique_number']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($appointment['date'])) ?></div>
                                                <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Missed
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-red-600">
                                                    <?= !empty($appointment['priority_number']) ? htmlspecialchars($appointment['priority_number']) : 'N/A' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= !empty($appointment['invoice_number']) ? htmlspecialchars($appointment['invoice_number']) : 'N/A' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 max-w-xs truncate"><?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No concerns specified' ?></div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Missed Appointments -->
                        <?php if ($totalMissedPages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($missedPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=missed&missed_page=<?= $missedPage - 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalMissedPages; $i++): ?>
                                    <?php if ($i == $missedPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=missed&missed_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($missedPage < $totalMissedPages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=missed&missed_page=<?= $missedPage + 1 ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Information Box -->
                        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-yellow-800">Missed Appointments Information</h4>
                                    <ul class="text-sm text-yellow-700 mt-2 space-y-1">
                                        <li>• Appointments are automatically marked as "Missed" when the appointment time has passed</li>
                                        <li>• Pending appointments become "Missed Appointment"</li>
                                        <li>• Approved appointments become "Missed Consultation"</li>
                                        <li>• Missed appointments cannot be approved or completed</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- All Appointments -->
                <div class="<?= $activeAppointmentTab === 'all' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="all" role="tabpanel" aria-labelledby="all-tab">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-blue-700">All Appointments (Approved, Completed, Cancelled, Rejected, Missed)</h2>
                        <span class="text-sm text-gray-600"><?= count($allAppointments) ?> total</span>
                    </div>
                    
                    <!-- Filters and Export Buttons -->
                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                            <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                                <!-- Status Filter -->
                                <div>
                                    <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                                    <select id="statusFilter" class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
                                        <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        <option value="missed" <?= $filterStatus === 'missed' ? 'selected' : '' ?>>Missed</option>
                                    </select>
                                </div>
                                
                                <!-- Date Filter -->
                                <div>
                                    <label for="dateFilter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Date</label>
                                    <input type="date" id="dateFilter" value="<?= $filterDate ?>" class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <!-- Export Buttons -->
                            <div class="flex gap-2 w-full md:w-auto">
                                <button onclick="exportData('excel')" class="btn-success-blue flex items-center">
                                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                                </button>
                                <button onclick="exportData('pdf')" class="btn-danger-blue flex items-center">
                                    <i class="fas fa-file-pdf mr-2"></i> Export PDF
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($allAppointmentsPaginated)): ?>
                        <div class="bg-blue-50 p-4 rounded-lg text-center">
                            <p class="text-gray-600">No appointments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Number</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Appointed</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority Number</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $filteredAppointments = array_filter($allAppointmentsPaginated, function($appointment) {
                                        $allowedStatuses = ['approved', 'completed', 'cancelled', 'rejected', 'missed'];
                                        return in_array($appointment['status'], $allowedStatuses);
                                    });
                                    
                                    usort($filteredAppointments, function($a, $b) {
                                        $dateTimeA = strtotime($a['date'] . ' ' . $a['start_time']);
                                        $dateTimeB = strtotime($b['date'] . ' ' . $b['start_time']);
                                        return $dateTimeB - $dateTimeA;
                                    });
                                    
                                    foreach ($filteredAppointments as $appointment): 
                                        $isPast = strtotime($appointment['date']) < strtotime(date('Y-m-d'));
                                    ?>
                                        <tr class="<?= $isPast ? 'bg-gray-50' : '' ?> <?= $appointment['status'] === 'missed' ? 'status-row-missed' : '' ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['email']) ?></div>
                                                <?php if ($appointment['status'] === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                                                    <div class="mt-1 text-xs text-red-600">
                                                        <strong>Reason:</strong> <?= htmlspecialchars($appointment['rejection_reason']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-blue-600"><?= !empty($appointment['unique_number']) ? htmlspecialchars($appointment['unique_number']) : 'N/A' ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($appointment['contact']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?= date('D, M d, Y', strtotime($appointment['date'])) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $appointment['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                                       ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                       ($appointment['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 
                                                       ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 
                                                       ($appointment['status'] === 'missed' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')))) ?>">
                                                    <?= ucfirst($appointment['status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($appointment['priority_number'])): ?>
                                                    <div class="text-sm font-medium text-red-600"><?= $appointment['priority_number'] ?></div>
                                                <?php else: ?>
                                                    <span class="text-gray-400">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button onclick="openViewModal(<?= htmlspecialchars(json_encode($appointment)) ?>)" 
                                                        class="btn-blue">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for All Appointments -->
                        <?php if ($totalAllPages > 1): ?>
                            <div class="pagination mt-4">
                                <!-- Previous Button -->
                                <?php if ($allPage > 1): ?>
                                    <a href="?tab=appointment-management&appointment_tab=all&all_page=<?= $allPage - 1 ?>&status=<?= $filterStatus ?>&date=<?= $filterDate ?>" class="pagination-button">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $totalAllPages; $i++): ?>
                                    <?php if ($i == $allPage): ?>
                                        <span class="pagination-button active"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?tab=appointment-management&appointment_tab=all&all_page=<?= $i ?>&status=<?= $filterStatus ?>&date=<?= $filterDate ?>" class="pagination-button"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next Button -->
                                <?php if ($allPage < $totalAllPages): ?>
                                    <a href="?tab=appointment-management&appointment_tab=all&all_page=<?= $allPage + 1 ?>&status=<?= $filterStatus ?>&date=<?= $filterDate ?>" class="pagination-button">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-button disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Account Management Section -->
        <div class="<?= $activeTab === 'account-management' ? '' : 'hidden' ?> p-4 bg-white rounded-lg border border-gray-200" id="account-management" role="tabpanel" aria-labelledby="account-tab">
            <h2 class="text-xl font-semibold mb-4 text-blue-700">Patient Account Approvals</h2>
            
            <?php if (empty($unapprovedUsers)): ?>
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-gray-600">No pending patient approvals.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Full Name</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date Registered</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">ID Status</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unapprovedUsers as $user): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['username']) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <?php if (!empty($user['id_type'])): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $user['is_valid_id'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $user['is_valid_id'] ? 'Valid ID' : 'Invalid ID' ?>
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1"><?= $user['id_type'] ?></div>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                No ID Uploaded
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <button onclick="openUserDetailsModal(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                                class="btn-view-details">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination mt-6">
                        <!-- Previous Button -->
                        <?php if ($currentPage > 1): ?>
                            <a href="?tab=account-management&user_page=<?= $currentPage - 1 ?>" class="pagination-button">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-button disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="pagination-button active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?tab=account-management&user_page=<?= $i ?>" class="pagination-button"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?tab=account-management&user_page=<?= $currentPage + 1 ?>" class="pagination-button">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-button disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Analytics Dashboard Section -->
        <div class="<?= $activeTab === 'analytics' ? '' : 'hidden' ?> p-6 bg-white rounded-lg border border-gray-200" id="analytics" role="tabpanel" aria-labelledby="analytics-tab">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold mb-6 text-blue-700">Analytics Dashboard</h2>
                <button onclick="refreshAnalytics()" class="btn-blue flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Data
                </button>
            </div>
            
            <!-- Overview Cards -->
            <div id="analyticsCards" class="analytics-grid mb-8">
                <!-- Cards will be loaded via AJAX -->
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Appointment Status Distribution -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-pie"></i>
                        Appointment Status Distribution
                    </h3>
                    <canvas id="appointmentStatusChart" height="300"></canvas>
                </div>
                
                <!-- Monthly Appointments Trend -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-chart-line"></i>
                        Monthly Appointments Trend
                    </h3>
                    <canvas id="monthlyTrendChart" height="300"></canvas>
                </div>
            </div>

            <!-- Additional Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Patient Registration Trend -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-user-plus"></i>
                        Patient Registration Trend
                    </h3>
                    <canvas id="patientRegistrationChart" height="300"></canvas>
                </div>
                
                <!-- Appointment Completion Rate -->
                <div class="chart-container">
                    <h3 class="chart-title">
                        <i class="fas fa-tasks"></i>
                        Appointment Completion Rate
                    </h3>
                    <canvas id="completionRateChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Action Success/Error Modals -->
<div id="successModal" class="modal-overlay hidden">
    <div class="modal-container action-modal action-modal-success">
        <div class="modal-body">
            <div class="action-modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="action-modal-title" id="successModalTitle">Success</h3>
            <div class="action-modal-message" id="successModalMessage"></div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center">
                <button type="button" onclick="closeSuccessModal()" 
                        class="px-8 py-3 bg-green-600 text-white rounded-full hover:bg-green-700 transition font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<div id="errorModal" class="modal-overlay hidden">
    <div class="modal-container action-modal action-modal-error">
        <div class="modal-body">
            <div class="action-modal-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h3 class="action-modal-title" id="errorModalTitle">Error</h3>
            <div class="action-modal-message" id="errorModalMessage"></div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center">
                <button type="button" onclick="closeErrorModal()" 
                        class="px-8 py-3 bg-red-600 text-white rounded-full hover:bg-red-700 transition font-medium">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Time Slot Selection Modal - Desktop Size -->
<div id="timeSlotModal" class="modal-overlay hidden">
    <div class="modal-container modal-time-slot-desktop">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-semibold text-gray-900" id="timeSlotModalTitle">
                    Select Time Slot for <span id="modalSelectedDate" class="text-blue-600 font-semibold"></span>
                </h3>
                <button type="button" onclick="closeTimeSlotModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="modal-body">
            <!-- Date Status Display -->
            <div id="dateStatusDisplay" class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg hidden">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
                    <span id="dateStatusText" class="text-yellow-700 font-medium"></span>
                </div>
            </div>
            
            <!-- Time Slots Selection -->
            <div class="mb-6">
                <div class="mb-6">
                    <h4 class="font-medium mb-4 text-gray-700 text-lg">Morning Slots (8:00 AM - 12:00 PM)</h4>
                    <div class="time-slots-grid" id="morningSlotsContainer">
                        <?php foreach ($morningSlots as $index => $slot): ?>
                            <div class="time-slot border-2 border-gray-200 rounded-lg p-6 cursor-pointer transition-all duration-200 hover:border-blue-300 bg-white" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <span class="font-semibold text-gray-800 text-base mb-2">
                                        <?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?>
                                    </span>
                                    <span class="slot-status text-xs text-gray-500">
                                        Available
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h4 class="font-medium mb-4 text-gray-700 text-lg">Afternoon Slots (1:00 PM - 5:00 PM)</h4>
                    <div class="time-slots-grid" id="afternoonSlotsContainer">
                        <?php foreach ($afternoonSlots as $index => $slot): ?>
                            <div class="time-slot border-2 border-gray-200 rounded-lg p-6 cursor-pointer transition-all duration-200 hover:border-blue-300 bg-white" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <span class="font-semibold text-gray-800 text-base mb-2">
                                        <?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?>
                                    </span>
                                    <span class="slot-status text-xs text-gray-500">
                                        Available
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="max_slots" class="block text-gray-700 mb-3 font-medium">Maximum Appointments per Slot *</label>
                    <select id="max_slots" name="max_slots" class="w-full px-4 py-3 text-base border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeTimeSlotModal()" class="px-8 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <button type="button" id="confirmSlotBtn" onclick="confirmTimeSlotSelection()" class="px-8 py-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition font-medium button-disabled" disabled>
                    <i class="fas fa-check-circle mr-2"></i> Confirm Selection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal - Horizontal Layout -->
<div id="userDetailsModal" class="modal-overlay hidden">
    <div class="modal-container modal-wide-desktop" style="max-height: 90vh;">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Patient Registration Details</h2>
                <button onclick="closeUserDetailsModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-full hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="modal-body" style="overflow-y: auto;">
            <div class="horizontal-user-details">
                <!-- Personal Information -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-blue-600 mb-4">
                        <i class="fas fa-user mr-2"></i> Personal Information
                    </h4>
                    <div class="space-y-4">
                        <div class="detail-item">
                            <span class="detail-label">Full Name:</span>
                            <span class="detail-value" id="userFullName">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Username:</span>
                            <span class="detail-value" id="userUsername">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value" id="userEmail">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact Number:</span>
                            <span class="detail-value" id="userContact">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value" id="userGender">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age:</span>
                            <span class="detail-value" id="userAge">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Civil Status:</span>
                            <span class="detail-value" id="userCivilStatus">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Occupation:</span>
                            <span class="detail-value" id="userOccupation">—</span>
                        </div>
                    </div>
                </div>

                <!-- Address & Account Information -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-green-600 mb-4">
                        <i class="fas fa-map-marker-alt mr-2"></i> Address Information
                    </h4>
                    <div class="space-y-4 mb-6">
                        <div class="detail-item">
                            <span class="detail-label">Complete Address:</span>
                            <span class="detail-value" id="userAddress">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sitio:</span>
                            <span class="detail-value" id="userSitio">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date of Birth:</span>
                            <span class="detail-value" id="userDateOfBirth">—</span>
                        </div>
                    </div>

                    <h4 class="text-lg font-semibold text-red-600 mb-4 mt-6">
                        <i class="fas fa-user-circle mr-2"></i> Account Information
                    </h4>
                    <div class="space-y-4">
                        <div class="detail-item">
                            <span class="detail-label">Account Status:</span>
                            <span class="detail-value" id="userStatus">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Approved:</span>
                            <span class="detail-value" id="userApproved">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">User Role:</span>
                            <span class="detail-value" id="userRole">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Verification Method:</span>
                            <span class="detail-value" id="userVerificationMethod">—</span>
                        </div>
                        <div class="detail-item" id="uniqueNumberSection" style="display: none;">
                            <span class="detail-label">Patient ID:</span>
                            <span class="detail-value text-blue-600 font-semibold" id="userUniqueNumber">—</span>
                        </div>
                    </div>
                </div>

                <!-- ID Verification -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-purple-600 mb-4">
                        <i class="fas fa-id-card mr-2"></i> ID Verification
                    </h4>
                    <div class="space-y-4">
                        <div class="detail-item">
                            <span class="detail-label">ID Type:</span>
                            <span class="detail-value" id="userIdType">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Status:</span>
                            <span class="detail-value">
                                <span id="userIdValidationStatus" class="px-2 py-1 text-xs rounded-full" style="background: #f3f4f6; color: #6b7280;">—</span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">ID Verified:</span>
                            <span class="detail-value" id="userIdVerified">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Verification Consent:</span>
                            <span class="detail-value" id="userVerificationConsent">—</span>
                        </div>
                        
                        <!-- ID Image Preview -->
                        <div class="mt-4">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">ID Document</h5>
                            <div id="idImageSection" class="hidden">
                                <div class="id-preview-container mb-3">
                                    <img id="userIdImage" src="" alt="ID Image" class="w-full h-auto">
                                </div>
                                <div class="flex space-x-3">
                                    <a id="userIdImageLink" href="#" target="_blank" class="btn-blue text-sm">
                                        <i class="fas fa-external-link-alt mr-2"></i> View Original
                                    </a>
                                    <button onclick="openImageModal()" class="btn-blue text-sm">
                                        <i class="fas fa-search mr-2"></i> Zoom Preview
                                    </button>
                                </div>
                            </div>
                            <div id="noIdImageSection" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <p class="text-yellow-700 text-sm">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    No ID image uploaded
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registration Timeline & Actions -->
                <div class="detail-section">
                    <h4 class="text-lg font-semibold text-indigo-600 mb-4">
                        <i class="fas fa-history mr-2"></i> Registration Timeline
                    </h4>
                    <div class="space-y-4 mb-6">
                        <div class="detail-item">
                            <span class="detail-label">Registered Date:</span>
                            <span class="detail-value" id="userRegisteredDate">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value" id="userUpdatedDate">—</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Verified At:</span>
                            <span class="detail-value" id="userVerifiedAt">—</span>
                        </div>
                    </div>

                    <!-- Verification Notes -->
                    <div id="verificationNotesSection" class="hidden mb-6">
                        <h4 class="text-lg font-semibold text-yellow-600 mb-4">
                            <i class="fas fa-sticky-note mr-2"></i> Verification Notes
                        </h4>
                        <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-4">
                            <p class="text-sm text-gray-700" id="userVerificationNotes">—</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <h4 class="text-lg font-semibold text-gray-600 mb-4">
                        <i class="fas fa-cogs mr-2"></i> Actions
                    </h4>
                    <div class="grid grid-cols-1 gap-4">
                        <button onclick="openApproveConfirmationModal()" 
                                class="btn-approve-white w-full">
                            <i class="fas fa-check mr-2"></i> Approve Registration
                        </button>
                        <button onclick="openDeclineModalFromDetails()" 
                                class="btn-decline-white w-full">
                            <i class="fas fa-times mr-2"></i> Decline Registration
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Cancelled Appointment Details Modal - Wide Desktop -->
<div id="cancelledDetailsModal" class="modal-overlay hidden">
    <div class="modal-container modal-wide-desktop" style="max-height: 90vh;">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <h3 class="text-2xl font-semibold text-gray-900">Cancelled Appointment Details</h3>
                <button type="button" onclick="closeCancelledDetailsModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="modal-body" style="overflow-y: auto;">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Patient Information -->
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                        <i class="fas fa-user mr-2"></i> Patient Information
                    </h4>
                    <div class="space-y-4">
                        <div>
                            <span class="text-sm font-medium text-gray-600 block">Full Name:</span>
                            <p class="text-base text-gray-900 mt-1" id="cancelledFullName">N/A</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Patient ID:</span>
                                <p class="text-base text-gray-900 mt-1" id="cancelledPatientId">N/A</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Contact Number:</span>
                                <p class="text-base text-gray-900 mt-1" id="cancelledContact">N/A</p>
                            </div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 block">Email:</span>
                            <p class="text-base text-gray-900 mt-1" id="cancelledEmail">N/A</p>
                        </div>
                    </div>
                </div>

                <!-- Appointment Details -->
                <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                    <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                        <i class="fas fa-calendar-alt mr-2"></i> Appointment Details
                    </h4>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Original Date:</span>
                                <p class="text-base text-gray-900 mt-1" id="cancelledDate">N/A</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Original Time:</span>
                                <p class="text-base text-gray-900 mt-1" id="cancelledTime">N/A</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Cancelled By:</span>
                                <p class="text-base font-semibold mt-1" id="cancelledBy">N/A</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600 block">Cancelled At:</span>
                                <p class="text-base text-gray-900 mt-1" id="cancelledAt">N/A</p>
                            </div>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-600 block">Original Status:</span>
                            <p class="text-base font-semibold mt-1" id="cancelledOriginalStatus">N/A</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Reason -->
            <div class="mb-6 bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <h4 class="text-lg font-semibold text-red-700 border-b pb-3 mb-4">
                    <i class="fas fa-comment-alt mr-2"></i> Cancellation Reason
                </h4>
                <div class="bg-red-50 border border-red-200 rounded-lg p-5">
                    <p class="text-base text-gray-900 leading-relaxed" id="cancelledReason">
                        No reason provided for cancellation.
                    </p>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <h4 class="text-lg font-semibold text-blue-700 border-b pb-3 mb-4">
                    <i class="fas fa-info-circle mr-2"></i> Additional Information
                </h4>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm font-medium text-gray-600 block">Priority Number:</span>
                        <p class="text-lg text-gray-900 font-semibold mt-1" id="cancelledPriorityNumber">N/A</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm font-medium text-gray-600 block">Invoice Number:</span>
                        <p class="text-lg text-gray-900 font-semibold mt-1" id="cancelledInvoiceNumber">N/A</p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <span class="text-sm font-medium text-gray-600 block">Health Concerns:</span>
                        <p class="text-base text-gray-900 mt-1" id="cancelledHealthConcerns">No health concerns specified</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <div class="flex justify-end">
                <button type="button" onclick="closeCancelledDetailsModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div id="approveConfirmationModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-body">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirm Resident Account Approval</h3>
            <p class="text-gray-600 text-center mb-4">
                Are you sure you want to approve this user account? This action will generate a unique patient number and grant full system access.
            </p>
            <div class="bg-blue-50 p-3 rounded-lg mb-6">
                <p class="text-sm text-blue-700 font-medium text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    An approval email with the unique patient number will be sent to the user.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center space-x-3">
                <button type="button" onclick="closeApproveConfirmationModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <form method="POST" action="" class="inline" id="finalApproveForm">
                    <input type="hidden" name="user_id" id="finalApproveUserId">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" name="approve_user" class="btn-success-blue">
                        <i class="fas fa-check mr-1"></i> Confirm Approval
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Decline Modal -->
<div id="declineModal" class="modal-overlay hidden">
    <div class="modal-container max-w-2xl">
        <div class="modal-header">
            <div class="flex items-center mb-4">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 text-center mb-2">Decline User Account</h3>
            <p class="text-gray-600 text-center">Please provide a reason for declining this user registration.</p>
        </div>
        
        <div class="modal-body">
            <form id="declineForm" method="POST" action="">
                <input type="hidden" name="user_id" id="declineUserId">
                <input type="hidden" name="action" value="decline">
                
                <div class="mb-6">
                    <label for="decline_reason" class="block text-gray-700 text-sm font-semibold mb-3">Reason for Declination *</label>
                    <textarea id="decline_reason" name="decline_reason" rows="6" 
                              class="w-full px-4 py-3 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                              placeholder="Please provide a detailed reason for declining this user account. This will be included in the notification email sent to the user..."
                              required></textarea>
                    <p class="text-xs text-gray-500 mt-2">This reason will be sent to the user via email.</p>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-semibold text-red-800">Important Notice</h4>
                            <p class="text-sm text-red-700 mt-1">
                                Declining this account will prevent the user from accessing the system. They will receive an email notification with the reason provided above.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeclineModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel
                </button>
                <button type="submit" form="declineForm" name="approve_user" class="btn-danger-blue">
                    <i class="fas fa-ban mr-2"></i> Confirm Decline
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Completed Confirmation Modal -->
<div id="completeConfirmationModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-body">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirm Appointment Completion</h3>
            <p class="text-gray-600 text-center mb-4" id="completePatientDetails">
                Are you sure you want to mark this appointment as completed?
            </p>
            <div class="bg-blue-50 p-3 rounded-lg mb-6">
                <p class="text-sm text-blue-700 font-medium text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    This action will update the appointment status to "completed" and cannot be undone.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center space-x-3">
                <button type="button" onclick="closeCompleteConfirmationModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <form method="POST" action="" class="inline" id="finalCompleteForm">
                    <input type="hidden" name="appointment_id" id="finalCompleteAppointmentId">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" name="approve_appointment" class="btn-success-blue">
                        <i class="fas fa-check mr-1"></i> Confirm Completion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Approval Confirmation Modal -->
<div id="appointmentApprovalModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-body">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirm Resident Appointment</h3>
            <p class="text-gray-600 text-center mb-4">
                Are you sure you want to approve this resident's appointment? This action will generate a priority number and invoice for the appointment.
            </p>
            <div class="bg-blue-50 p-3 rounded-lg mb-6">
                <p class="text-sm text-blue-700 font-medium text-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    An approval email with appointment details will be sent to the resident.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-center space-x-3">
                <button type="button" onclick="closeAppointmentApprovalModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <form method="POST" action="" class="inline" id="finalAppointmentApproveForm">
                    <input type="hidden" name="appointment_id" id="finalAppointmentApproveId">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" name="approve_appointment" class="btn-success-blue">
                        <i class="fas fa-check mr-1"></i> Confirm Approval
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectionModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Reject Appointment</h3>
        </div>
        <div class="modal-body">
            <form id="rejectionForm" method="POST" action="">
                <input type="hidden" name="appointment_id" id="reject_appointment_id">
                <input type="hidden" name="action" value="reject">
                
                <div class="mb-4">
                    <label for="rejection_reason" class="block text-gray-700 mb-2 font-medium">Reason for Rejection *</label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="4" 
                              class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                              placeholder="Please provide a reason for rejecting this appointment..." required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRejectionModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <button type="submit" form="rejectionForm" name="approve_appointment" class="btn-danger-blue">
                    Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Appointment Modal -->
<div id="viewModal" class="modal-overlay hidden">
    <div class="modal-container max-w-2xl">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900">Appointment Details</h3>
        </div>
        <div class="modal-body">
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-semibold text-gray-700">Patient Information</h4>
                        <div class="mt-2 space-y-2">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Full Name:</span>
                                <p class="text-sm text-gray-900" id="viewFullName"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Patient ID:</span>
                                <p class="text-sm text-gray-900" id="viewPatientId"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Contact Number:</span>
                                <p class="text-sm text-gray-900" id="viewContact"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Email:</span>
                                <p class="text-sm text-gray-900" id="viewEmail"></p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-700">Appointment Details</h4>
                        <div class="mt-2 space-y-2">
                            <div>
                                <span class="text-sm font-medium text-gray-600">Date:</span>
                                <p class="text-sm text-gray-900" id="viewDate"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Time:</span>
                                <p class="text-sm text-gray-900" id="viewTime"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Status:</span>
                                <p class="text-sm" id="viewStatus"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Priority Number:</span>
                                <p class="text-sm text-gray-900" id="viewPriorityNumber"></p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-gray-600">Invoice Number:</span>
                                <p class="text-sm text-gray-900" id="viewInvoiceNumber"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <h4 class="font-semibold text-gray-700">Health Concerns</h4>
                    <p class="text-sm text-gray-900 mt-2 bg-white p-3 rounded border" id="viewHealthConcerns"></p>
                </div>
                <div class="mt-4" id="viewRejectionReasonSection" style="display: none;">
                    <h4 class="font-semibold text-red-700">Rejection Reason</h4>
                    <p class="text-sm text-red-600 mt-2 bg-red-50 p-3 rounded border" id="viewRejectionReason"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="flex justify-end">
                <button type="button" onclick="closeViewModal()" class="btn-blue">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Slot Modal -->
<div id="editModal" class="modal-overlay hidden">
    <div class="modal-container max-w-md">
        <div class="modal-header">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Edit Appointment Slot</h3>
        </div>
        <div class="modal-body">
            <form id="editSlotForm" method="POST" action="">
                <input type="hidden" name="slot_id" id="edit_slot_id">
                
                <div class="mb-4">
                    <label for="edit_date" class="block text-gray-700 mb-2 font-medium">Date *</label>
                    <input type="date" id="edit_date" name="date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2 font-medium">Time Slot *</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Morning</h3>
                            <div class="space-y-2">
                                <?php foreach ($morningSlots as $slot): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="edit_morning_<?= str_replace(':', '', $slot['start']) ?>" 
                                               name="time_slot" value="<?= $slot['start'] ?> - <?= $slot['end'] ?>" 
                                               class="mr-2" required>
                                        <label for="edit_morning_<?= str_replace(':', '', $slot['start']) ?>"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Afternoon</h3>
                            <div class="space-y-2">
                                <?php foreach ($afternoonSlots as $slot): ?>
                                    <div class="flex items-center">
                                        <input type="radio" id="edit_afternoon_<?= str_replace(':', '', $slot['start']) ?>" 
                                               name="time_slot" value="<?= $slot['start'] ?> - <?= $slot['end'] ?>" 
                                               class="mr-2" required>
                                        <label for="edit_afternoon_<?= str_replace(':', '', $slot['start']) ?>"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="edit_max_slots" class="block text-gray-700 mb-2 font-medium">Maximum Appointments *</label>
                    <select id="edit_max_slots" name="max_slots" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="5">5</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeEditModal()" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-full hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
                <button type="submit" form="editSlotForm" name="update_slot" class="btn-blue">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Zoom Modal -->
<div id="imageModal" class="modal-overlay hidden">
    <div class="modal-container max-w-4xl">
        <div class="modal-header">
            <h3 class="text-lg font-semibold">ID Document Preview</h3>
            <button type="button" onclick="closeImageModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="flex justify-center">
                <img id="zoomedUserIdImage" src="" alt="Zoomed ID Image" class="max-w-full h-auto rounded-lg">
            </div>
        </div>
        <div class="modal-footer">
            <div class="text-center">
                <a id="zoomedUserIdImageLink" href="#" target="_blank" 
                   class="btn-blue">
                    <i class="fas fa-external-link-alt mr-2"></i> Open in New Tab
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Global variable to store current user ID
let currentUserDetailsId = null;

// Calendar and Time Slot functionality
let currentMonth = <?= date('m') ?>;
let currentYear = <?= date('Y') ?>;
let selectedDate = null;
let selectedTimeSlot = null;
const phHolidays = <?= json_encode($phHolidays) ?>;
const dateSlots = <?= json_encode($dateSlots) ?>;
const occupiedSlotsByDate = <?= json_encode($occupiedSlotsByDate) ?>;
const nextAvailableDate = '<?= $nextAvailableDate ?>';
const currentTime = '<?= date('H:i:s') ?>';

// Enhanced Modal Management Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// Enhanced Success/Error Modal Functions
function showSuccessModal(message) {
    document.getElementById('successModalTitle').textContent = 'Success';
    document.getElementById('successModalMessage').innerHTML = message;
    openModal('successModal');
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        closeSuccessModal();
    }, 5000);
}

function closeSuccessModal() {
    closeModal('successModal');
}

function showErrorModal(message) {
    document.getElementById('errorModalTitle').textContent = 'Error';
    document.getElementById('errorModalMessage').textContent = message;
    openModal('errorModal');
    
    // Auto-close after 5 seconds
    setTimeout(() => {
        closeErrorModal();
    }, 5000);
}

function closeErrorModal() {
    closeModal('errorModal');
}

// Time Slot Modal functions
function openTimeSlotModal() {
    if (!selectedDate) {
        showErrorModal('Please select a date first');
        return;
    }
    
    const modalTitle = document.getElementById('modalSelectedDate');
    modalTitle.textContent = document.getElementById('selectedDateDisplay').textContent;
    
    const dateStatusDisplay = document.getElementById('dateStatusDisplay');
    const dateStatusText = document.getElementById('dateStatusText');
    
    if (occupiedSlotsByDate[selectedDate] && occupiedSlotsByDate[selectedDate].length > 0) {
        dateStatusDisplay.classList.remove('hidden');
        const occupiedCount = occupiedSlotsByDate[selectedDate].length;
        dateStatusText.textContent = `This date already has ${occupiedCount} time slot(s) set. You can add additional time slots if needed.`;
    } else {
        dateStatusDisplay.classList.add('hidden');
    }
    
    selectedTimeSlot = null;
    updateConfirmButtonState();
    updateTimeSlotsForDate(selectedDate);
    openModal('timeSlotModal');
}

function closeTimeSlotModal() {
    closeModal('timeSlotModal');
}

function confirmTimeSlotSelection() {
    if (!selectedDate || !selectedTimeSlot) {
        showErrorModal('Please select both a date and time slot');
        return;
    }
    
    const maxSlots = document.getElementById('max_slots').value;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'date';
    dateInput.value = selectedDate;
    
    const timeInput = document.createElement('input');
    timeInput.type = 'hidden';
    timeInput.name = 'time_slot';
    timeInput.value = selectedTimeSlot;
    
    const maxInput = document.createElement('input');
    maxInput.type = 'hidden';
    maxInput.name = 'max_slots';
    maxInput.value = maxSlots;
    
    const submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'add_slot';
    submitInput.value = '1';
    
    form.appendChild(dateInput);
    form.appendChild(timeInput);
    form.appendChild(maxInput);
    form.appendChild(submitInput);
    
    document.body.appendChild(form);
    form.submit();
}

function updateConfirmButtonState() {
    const confirmBtn = document.getElementById('confirmSlotBtn');
    if (selectedTimeSlot) {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('button-disabled');
        confirmBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
    } else {
        confirmBtn.disabled = true;
        confirmBtn.classList.add('button-disabled');
        confirmBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
    }
}

function updateTimeSlotsForDate(dateStr) {
    const currentDate = new Date();
    const isToday = dateStr === currentDate.toISOString().split('T')[0];
    
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected', 'disabled', 'occupied', 'current-time', 'bg-blue-500', 'text-white', 'border-blue-600', 'border-yellow-400', 'bg-yellow-50', 'border-red-600', 'bg-red-50');
        slot.classList.add('bg-white', 'border-gray-200');
        slot.style.cursor = 'pointer';
        
        const statusEl = slot.querySelector('.slot-status');
        statusEl.textContent = 'Available';
        statusEl.className = 'slot-status text-xs text-gray-500';
    });
    
    const occupiedSlots = occupiedSlotsByDate[dateStr] || [];
    
    document.querySelectorAll('.time-slot').forEach(slot => {
        const timeRange = slot.getAttribute('data-time');
        const [startTime, endTime] = timeRange.split(' - ');
        
        // Check if current time is within this time slot
        const isCurrentTime = isToday && 
                             currentTime >= startTime && 
                             currentTime <= endTime;
        
        if (isCurrentTime) {
            slot.classList.add('current-time', 'disabled');
            slot.style.cursor = 'not-allowed';
            
            const statusEl = slot.querySelector('.slot-status');
            statusEl.textContent = 'Ongoing - Cannot Edit';
            statusEl.className = 'slot-status text-xs text-red-600 font-medium';
            
            slot.onclick = null;
        } else if (occupiedSlots.includes(timeRange)) {
            slot.classList.add('occupied', 'disabled');
            slot.style.cursor = 'not-allowed';
            
            const statusEl = slot.querySelector('.slot-status');
            statusEl.textContent = 'Already Set';
            statusEl.className = 'slot-status text-xs text-yellow-600 font-medium';
            
            slot.onclick = null;
        } else {
            slot.onclick = function() {
                if (!this.classList.contains('disabled') && !this.classList.contains('occupied') && !this.classList.contains('current-time')) {
                    selectTimeSlot(this, timeRange);
                }
            };
        }
    });
    
    if (isToday) {
        document.querySelectorAll('.time-slot').forEach(slot => {
            if (slot.classList.contains('disabled')) return;
            
            const timeRange = slot.getAttribute('data-time');
            const [startTime] = timeRange.split(' - ');
            const slotDateTime = new Date(`${dateStr}T${startTime}`);
            
            if (slotDateTime < currentDate) {
                slot.classList.add('disabled');
                slot.style.cursor = 'not-allowed';
                const statusEl = slot.querySelector('.slot-status');
                statusEl.textContent = 'Past';
                statusEl.className = 'slot-status text-xs text-red-500';
            }
        });
    }
    
    selectedTimeSlot = null;
    updateConfirmButtonState();
}

function selectTimeSlot(slotEl, timeRange) {
    if (slotEl.classList.contains('disabled') || slotEl.classList.contains('occupied') || slotEl.classList.contains('current-time')) {
        return;
    }
    
    document.querySelectorAll('.time-slot').forEach(el => {
        if (!el.classList.contains('disabled') && !el.classList.contains('occupied') && !el.classList.contains('current-time')) {
            el.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
            el.classList.add('bg-white', 'border-gray-200');
        }
    });
    
    slotEl.classList.remove('bg-white', 'border-gray-200');
    slotEl.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
    
    selectedTimeSlot = timeRange;
    document.getElementById('selected_time_slot').value = timeRange;
    
    updateConfirmButtonState();
}

// User Details Modal functions
function openUserDetailsModal(user) {
    console.log('User data for modal:', user);
    
    currentUserDetailsId = user.id;
    
    // Set user data in the modal
    document.getElementById('userFullName').textContent = user.full_name || 'N/A';
    document.getElementById('userUsername').textContent = user.username || 'N/A';
    document.getElementById('userEmail').textContent = user.email || 'N/A';
    document.getElementById('userGender').textContent = user.gender ? user.gender.charAt(0).toUpperCase() + user.gender.slice(1) : 'N/A';
    document.getElementById('userAge').textContent = user.age || 'N/A';
    document.getElementById('userContact').textContent = user.contact || 'N/A';
    document.getElementById('userCivilStatus').textContent = user.civil_status || 'N/A';
    document.getElementById('userOccupation').textContent = user.occupation || 'N/A';
    document.getElementById('userDateOfBirth').textContent = user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
    
    document.getElementById('userAddress').textContent = user.address || 'N/A';
    document.getElementById('userSitio').textContent = user.sitio || 'N/A';
    
    const verificationMethod = user.verification_method ? 
        user.verification_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
    document.getElementById('userVerificationMethod').textContent = verificationMethod;
    
    const idVerifiedElement = document.getElementById('userIdVerified');
    if (user.id_verified === 1 || user.id_verified === true) {
        idVerifiedElement.textContent = 'Yes';
        idVerifiedElement.className = 'detail-value text-green-600';
    } else {
        idVerifiedElement.textContent = 'No';
        idVerifiedElement.className = 'detail-value text-red-600';
    }
    
    const consentElement = document.getElementById('userVerificationConsent');
    if (user.verification_consent === 1 || user.verification_consent === true) {
        consentElement.textContent = 'Yes';
        consentElement.className = 'detail-value text-green-600';
    } else {
        consentElement.textContent = 'No';
        consentElement.className = 'detail-value text-red-600';
    }
    
    // Handle ID image
    const idImageSection = document.getElementById('idImageSection');
    const noIdImageSection = document.getElementById('noIdImageSection');
    const idImage = document.getElementById('userIdImage');
    const idImageLink = document.getElementById('userIdImageLink');
    const zoomedIdImage = document.getElementById('zoomedUserIdImage');
    const zoomedIdImageLink = document.getElementById('zoomedUserIdImageLink');
    
    const imagePath = user.display_image_path || user.id_image_path;
    
    if (imagePath && imagePath.trim() !== '') {
        console.log('Displaying ID image from path:', imagePath);
        
        const testImage = new Image();
        testImage.onload = function() {
            idImage.src = imagePath;
            zoomedIdImage.src = imagePath;
            idImageLink.href = imagePath;
            zoomedIdImageLink.href = imagePath;
            
            idImageSection.classList.remove('hidden');
            noIdImageSection.classList.add('hidden');
        };
        
        testImage.onerror = function() {
            console.error('Failed to load ID image:', imagePath);
            idImageSection.classList.add('hidden');
            noIdImageSection.classList.remove('hidden');
        };
        
        testImage.src = imagePath;
        
    } else {
        console.log('No ID image available');
        idImageSection.classList.add('hidden');
        noIdImageSection.classList.remove('hidden');
    }
    
    // Display ID type and validation status
    const idTypeElement = document.getElementById('userIdType');
    const idValidationStatus = document.getElementById('userIdValidationStatus');
    
    if (user.id_type) {
        idTypeElement.textContent = user.id_type;
        
        const isValidId = user.is_valid_id;
        if (isValidId) {
            idValidationStatus.textContent = 'Valid ID';
            idValidationStatus.className = 'px-2 py-1 text-xs rounded-full bg-green-100 text-green-800';
        } else {
            idValidationStatus.textContent = 'Invalid ID Type';
            idValidationStatus.className = 'px-2 py-1 text-xs rounded-full bg-red-100 text-red-800';
        }
    } else {
        idTypeElement.textContent = 'No ID Uploaded';
        idValidationStatus.textContent = 'No ID Found';
        idValidationStatus.className = 'px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800';
    }
    
    // Handle verification notes
    const verificationNotesSection = document.getElementById('verificationNotesSection');
    const verificationNotes = document.getElementById('userVerificationNotes');
    if (user.verification_notes && user.verification_notes.trim() !== '') {
        verificationNotesSection.classList.remove('hidden');
        verificationNotes.textContent = user.verification_notes;
    } else {
        verificationNotesSection.classList.add('hidden');
    }
    
    // Registration details
    document.getElementById('userRegisteredDate').textContent = user.created_at ? 
        new Date(user.created_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
    
    document.getElementById('userUpdatedDate').textContent = user.updated_at ? 
        new Date(user.updated_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
    
    document.getElementById('userVerifiedAt').textContent = user.verified_at ? 
        new Date(user.verified_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'Not verified';
    
    // Status information
    const statusElement = document.getElementById('userStatus');
    statusElement.textContent = user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'Pending';
    statusElement.className = 'detail-value ' + 
        (user.status === 'approved' ? 'text-green-600' :
         user.status === 'pending' ? 'text-yellow-600' :
         user.status === 'declined' ? 'text-red-600' : 'text-yellow-600');
    
    const approvedElement = document.getElementById('userApproved');
    if (user.approved === 1 || user.approved === true) {
        approvedElement.textContent = 'Yes';
        approvedElement.className = 'detail-value text-green-600';
    } else {
        approvedElement.textContent = 'No';
        approvedElement.className = 'detail-value text-red-600';
    }
    
    document.getElementById('userRole').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'Patient';
    
    // Handle unique number
    const uniqueNumberSection = document.getElementById('uniqueNumberSection');
    const uniqueNumberElement = document.getElementById('userUniqueNumber');
    if (user.unique_number) {
        uniqueNumberSection.style.display = 'block';
        uniqueNumberElement.textContent = user.unique_number;
    } else {
        uniqueNumberSection.style.display = 'none';
    }
    
    openModal('userDetailsModal');
}

function closeUserDetailsModal() {
    closeModal('userDetailsModal');
}

// Cancelled Appointment Details Modal functions
function openCancelledDetailsModal(appointment) {
    console.log('Cancelled appointment data:', appointment);
    
    document.getElementById('cancelledFullName').textContent = appointment.full_name || 'N/A';
    document.getElementById('cancelledPatientId').textContent = appointment.unique_number || 'N/A';
    document.getElementById('cancelledContact').textContent = appointment.contact || 'N/A';
    document.getElementById('cancelledEmail').textContent = appointment.email || 'N/A';
    
    document.getElementById('cancelledDate').textContent = appointment.date ? 
        new Date(appointment.date).toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        }) : 'N/A';
    
    document.getElementById('cancelledTime').textContent = appointment.start_time && appointment.end_time ? 
        new Date('1970-01-01T' + appointment.start_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) + ' - ' + 
        new Date('1970-01-01T' + appointment.end_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
    
    document.getElementById('cancelledBy').textContent = appointment.cancelled_by || 'N/A';
    document.getElementById('cancelledAt').textContent = appointment.cancelled_at ? 
        new Date(appointment.cancelled_at).toLocaleString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
    
    const wasPending = !appointment.priority_number && !appointment.invoice_number;
    const originalStatus = wasPending ? 'Pending' : 'Approved';
    document.getElementById('cancelledOriginalStatus').textContent = originalStatus;
    document.getElementById('cancelledOriginalStatus').className = 'text-base font-semibold ' + 
        (wasPending ? 'text-yellow-600 mt-1' : 'text-green-600 mt-1');
    
    const reason = appointment.cancel_reason || 'No reason provided for cancellation.';
    document.getElementById('cancelledReason').textContent = reason;
    
    document.getElementById('cancelledPriorityNumber').textContent = appointment.priority_number || 'N/A';
    document.getElementById('cancelledInvoiceNumber').textContent = appointment.invoice_number || 'N/A';
    document.getElementById('cancelledHealthConcerns').textContent = appointment.health_concerns || 'No health concerns specified';
    
    openModal('cancelledDetailsModal');
}

function closeCancelledDetailsModal() {
    closeModal('cancelledDetailsModal');
}

// Mark as Completed Confirmation Modal functions
function openCompleteConfirmationModal(appointmentId, patientName, appointmentDate, appointmentTime, priorityNumber) {
    document.getElementById('finalCompleteAppointmentId').value = appointmentId;
    
    const detailsElement = document.getElementById('completePatientDetails');
    detailsElement.innerHTML = `
        Are you sure you want to mark <strong>${patientName}</strong>'s appointment as completed?<br><br>
        <strong>Appointment Details:</strong><br>
        Date: ${new Date(appointmentDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}<br>
        Time: ${appointmentTime}<br>
        Priority Number: ${priorityNumber}
    `;
    
    openModal('completeConfirmationModal');
}

function closeCompleteConfirmationModal() {
    closeModal('completeConfirmationModal');
}

// Approve Confirmation Modal functions
function openApproveConfirmationModal() {
    document.getElementById('finalApproveUserId').value = currentUserDetailsId;
    openModal('approveConfirmationModal');
}

function closeApproveConfirmationModal() {
    closeModal('approveConfirmationModal');
}

// Decline Modal functions
function openDeclineModalFromDetails() {
    closeUserDetailsModal();
    setTimeout(() => {
        openDeclineModal(currentUserDetailsId);
    }, 100);
}

function openDeclineModal(userId) {
    document.getElementById('declineUserId').value = userId;
    document.getElementById('decline_reason').value = '';
    openModal('declineModal');
}

function closeDeclineModal() {
    closeModal('declineModal');
}

// Image Modal functions
function openImageModal() {
    openModal('imageModal');
}

function closeImageModal() {
    closeModal('imageModal');
}

// Appointment Approval Confirmation Modal functions
function openAppointmentApprovalModal(appointmentId) {
    document.getElementById('finalAppointmentApproveId').value = appointmentId;
    openModal('appointmentApprovalModal');
}

function closeAppointmentApprovalModal() {
    closeModal('appointmentApprovalModal');
}

// Tab functionality
function switchTab(tabId) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('.tab-content > div').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    document.getElementById(tabId).classList.remove('hidden');
    
    document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
        tabBtn.classList.remove('active');
    });
    
    const activeTabBtn = document.querySelector(`#dashboardTabs button[data-tabs-target="#${tabId}"]`);
    activeTabBtn.classList.add('active');
}

function switchAppointmentTab(tabId) {
    const url = new URL(window.location);
    url.searchParams.set('appointment_tab', tabId);
    window.history.pushState({}, '', url);
    
    document.querySelectorAll('#appointment-management .tab-content > div').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    document.getElementById(tabId).classList.remove('hidden');
    
    document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
        tabBtn.classList.remove('active');
    });
    
    const activeTabBtn = document.querySelector(`#appointmentTabs button[data-tabs-target="#${tabId}"]`);
    activeTabBtn.classList.add('active');
}

// Modal functions
function openEditModal(slot, timeSlotValue) {
    document.getElementById('edit_slot_id').value = slot.id;
    document.getElementById('edit_date').value = slot.date;
    document.getElementById('edit_max_slots').value = slot.max_slots;
    
    const timeSlotInputs = document.querySelectorAll('#editSlotForm input[name="time_slot"]');
    timeSlotInputs.forEach(input => {
        if (input.value === timeSlotValue) {
            input.checked = true;
        }
    });
    
    openModal('editModal');
}

function closeEditModal() {
    closeModal('editModal');
}

function openRejectionModal(appointmentId) {
    document.getElementById('reject_appointment_id').value = appointmentId;
    document.getElementById('rejection_reason').value = '';
    openModal('rejectionModal');
}

function closeRejectionModal() {
    closeModal('rejectionModal');
}

// View Modal functions
function openViewModal(appointment) {
    document.getElementById('viewFullName').textContent = appointment.full_name || 'N/A';
    document.getElementById('viewPatientId').textContent = appointment.unique_number || 'N/A';
    document.getElementById('viewContact').textContent = appointment.contact || 'N/A';
    document.getElementById('viewEmail').textContent = appointment.email || 'N/A';
    document.getElementById('viewDate').textContent = appointment.date ? new Date(appointment.date).toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
    document.getElementById('viewTime').textContent = appointment.start_time && appointment.end_time ? 
        new Date('1970-01-01T' + appointment.start_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) + ' - ' + 
        new Date('1970-01-01T' + appointment.end_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
    
    const statusElement = document.getElementById('viewStatus');
    statusElement.textContent = appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'N/A';
    statusElement.className = 'text-sm font-semibold ' + 
        (appointment.status === 'approved' ? 'text-green-600' :
         appointment.status === 'pending' ? 'text-yellow-600' :
         appointment.status === 'rejected' ? 'text-red-600' :
         appointment.status === 'completed' ? 'text-blue-600' :
         appointment.status === 'missed' ? 'text-yellow-600' : 'text-gray-600');
    
    document.getElementById('viewPriorityNumber').textContent = appointment.priority_number || 'N/A';
    document.getElementById('viewInvoiceNumber').textContent = appointment.invoice_number || 'N/A';
    document.getElementById('viewHealthConcerns').textContent = appointment.health_concerns || 'No health concerns specified';
    
    if (appointment.status === 'rejected' && appointment.rejection_reason) {
        document.getElementById('viewRejectionReason').textContent = appointment.rejection_reason;
        document.getElementById('viewRejectionReasonSection').style.display = 'block';
    } else {
        document.getElementById('viewRejectionReasonSection').style.display = 'none';
    }
    
    openModal('viewModal');
}

function closeViewModal() {
    closeModal('viewModal');
}

// Help modal functions
function openHelpModal() {
    openModal('helpModal');
}

function closeHelpModal() {
    closeModal('helpModal');
}

// Export functionality
function exportData(type) {
    const status = document.getElementById('statusFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    let url = `?export=${type}`;
    if (status !== 'all') {
        url += `&status=${status}`;
    }
    if (date) {
        url += `&date=${date}`;
    }
    
    window.location.href = url;
}

// Filter functionality
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const date = document.getElementById('dateFilter').value;
    
    let url = '?';
    if (status !== 'all') {
        url += `status=${status}&`;
    }
    if (date) {
        url += `date=${date}&`;
    }
    
    if (url === '?') {
        url = '';
    } else {
        url = url.slice(0, -1);
    }
    
    window.location.href = url;
}

// Calendar functionality
function generateCalendar(month, year) {
    const calendarEl = document.getElementById('calendar');
    calendarEl.innerHTML = '';
    
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('currentMonthYear').textContent = `${monthNames[month - 1]} ${year}`;
    
    for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.classList.add('calendar-day', 'disabled', 'text-center', 'p-2', 'rounded', 'bg-gray-100', 'text-gray-400');
        calendarEl.appendChild(emptyCell);
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const currentTime = new Date();
    
    let autoSelected = false;
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dateObj = new Date(year, month - 1, day);
        
        const dayCell = document.createElement('div');
        dayCell.classList.add('calendar-day', 'text-center', 'p-2', 'rounded', 'border', 'border-gray-200');
        
        const isToday = dateObj.toDateString() === today.toDateString();
        const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;
        const isPast = dateObj < today;
        const isHoliday = phHolidays.hasOwnProperty(dateStr);
        const hasSlots = dateSlots.hasOwnProperty(dateStr);
        const hasOccupiedSlots = occupiedSlotsByDate.hasOwnProperty(dateStr) && occupiedSlotsByDate[dateStr].length > 0;
        
        if (isPast) {
            dayCell.classList.add('disabled', 'bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
            dayCell.title = 'This date has passed and cannot be selected';
        } else if (isHoliday) {
            dayCell.classList.add('holiday', 'bg-red-50', 'text-red-700', 'border-red-200', 'cursor-not-allowed');
            dayCell.title = 'Holiday: ' + phHolidays[dateStr];
        } else if (hasOccupiedSlots) {
            dayCell.classList.add('occupied', 'bg-yellow-50', 'text-yellow-800', 'border-yellow-300');
            dayCell.title = 'This date already has time slots set';
            
            const statusIndicator = document.createElement('div');
            statusIndicator.classList.add('date-status-display', 'occupied');
            statusIndicator.textContent = 'Set Date';
            dayCell.appendChild(statusIndicator);
        } else if (isWeekend) {
            dayCell.classList.add('weekend', 'bg-blue-50', 'text-blue-700', 'border-blue-200');
            if (!hasSlots) {
                dayCell.classList.add('no-slots');
            } else {
                dayCell.classList.add('has-slots');
            }
        } else if (isToday) {
            dayCell.classList.add('today', 'bg-blue-100', 'text-blue-700', 'border-blue-200');
            if (!hasSlots) {
                dayCell.classList.add('no-slots');
            } else {
                dayCell.classList.add('has-slots');
            }
        } else {
            dayCell.classList.add('bg-white', 'text-gray-700');
            if (!hasSlots) {
                dayCell.classList.add('no-slots');
            } else {
                dayCell.classList.add('has-slots');
            }
        }
        
        if (!autoSelected && !isPast && !isHoliday && !hasOccupiedSlots && dateStr === nextAvailableDate) {
            dayCell.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
            selectedDate = dateStr;
            autoSelected = true;
            
            document.getElementById('selectedDateDisplay').textContent = `${monthNames[month - 1]} ${day}, ${year}`;
            document.getElementById('selected_date').value = dateStr;
            
            const dateStatusElement = document.getElementById('selectedDateStatus');
            if (hasOccupiedSlots) {
                dateStatusElement.textContent = '⚠ This date already has time slots set';
                dateStatusElement.className = 'text-sm text-yellow-600';
            } else {
                dateStatusElement.textContent = '✓ Available date';
                dateStatusElement.className = 'text-sm text-green-600';
            }
            
            document.getElementById('selectedDateSection').classList.remove('hidden');
        }
        
        const dayNumber = document.createElement('div');
        dayNumber.classList.add('font-semibold', 'text-lg', 'mb-1');
        dayNumber.textContent = day;
        dayCell.appendChild(dayNumber);
        
        if (!isPast && !isHoliday && !hasOccupiedSlots) {
            const dayIndicator = document.createElement('div');
            dayIndicator.classList.add('day-indicator');
            dayCell.appendChild(dayIndicator);
        }
        
        if (isHoliday) {
            const holidayIndicator = document.createElement('div');
            holidayIndicator.classList.add('text-xs', 'font-medium', 'text-red-500');
            holidayIndicator.textContent = 'HOLIDAY';
            dayCell.appendChild(holidayIndicator);
        }
        
        if (isToday) {
            const todayIndicator = document.createElement('div');
            todayIndicator.classList.add('text-xs', 'font-medium', 'text-blue-600');
            todayIndicator.textContent = 'TODAY';
            dayCell.appendChild(todayIndicator);
        }
        
        if (isWeekend && !isPast && !isHoliday && !hasOccupiedSlots) {
            const weekendIndicator = document.createElement('div');
            weekendIndicator.classList.add('text-xs', 'font-medium', 'text-blue-500');
            weekendIndicator.textContent = 'WEEKEND';
            dayCell.appendChild(weekendIndicator);
        }
        
        if (!isPast && !isHoliday) {
            dayCell.addEventListener('click', () => selectDate(dateStr, day, month, year, hasOccupiedSlots));
        } else {
            dayCell.style.cursor = 'not-allowed';
        }
        
        calendarEl.appendChild(dayCell);
    }
}

function selectDate(dateStr, day, month, year, hasOccupiedSlots) {
    selectedDate = dateStr;
    
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('selectedDateDisplay').textContent = `${monthNames[month - 1]} ${day}, ${year}`;
    document.getElementById('selected_date').value = dateStr;
    
    const dateStatusElement = document.getElementById('selectedDateStatus');
    if (hasOccupiedSlots) {
        dateStatusElement.textContent = '⚠ This date already has time slots set';
        dateStatusElement.className = 'text-sm text-yellow-600';
    } else {
        dateStatusElement.textContent = '✓ Available date';
        dateStatusElement.className = 'text-sm text-green-600';
    }
    
    document.querySelectorAll('.calendar-day').forEach(dayEl => {
        dayEl.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
    });
    
    event.currentTarget.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
    
    document.getElementById('selectedDateSection').classList.remove('hidden');
    
    selectedTimeSlot = null;
}

// Initialize tabs and charts
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'appointment-management';
    const activeAppointmentTab = urlParams.get('appointment_tab') || 'add-slot';
    
    switchTab(activeTab);
    switchAppointmentTab(activeAppointmentTab);
    
    document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
        tabBtn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
            switchTab(targetTab);
        });
    });
    
    document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
        tabBtn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
            switchAppointmentTab(targetTab);
        });
    });
    
    generateCalendar(currentMonth, currentYear);
    
    document.getElementById('prevMonth').addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.getElementById('nextMonth').addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.addEventListener('click', function() {
            if (!this.classList.contains('disabled') && !this.classList.contains('occupied') && !this.classList.contains('current-time')) {
                const timeRange = this.getAttribute('data-time');
                selectTimeSlot(this, timeRange);
            }
        });
    });
    
    document.getElementById('statusFilter').addEventListener('change', applyFilters);
    document.getElementById('dateFilter').addEventListener('change', applyFilters);
    
    if (activeTab === 'analytics') {
        initializeCharts();
    }
    
    // Show success message if exists
    <?php if ($success): ?>
        showSuccessModal('<?= addslashes($success) ?>');
    <?php endif; ?>
    
    <?php if ($error): ?>
        showErrorModal('<?= addslashes($error) ?>');
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['timeSlotModal', 'userDetailsModal', 'cancelledDetailsModal', 'completeConfirmationModal', 
                    'approveConfirmationModal', 'declineModal', 'imageModal', 'editModal', 'rejectionModal', 
                    'successModal', 'errorModal', 'helpModal', 'viewModal', 'appointmentApprovalModal'];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && event.target === modal) {
            closeModal(modalId);
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal-overlay.active');
        openModals.forEach(modal => {
            const modalId = modal.id;
            closeModal(modalId);
        });
    }
});

// Refresh analytics function
function refreshAnalytics() {
    const loader = document.getElementById('ajaxLoader');
    loader.style.display = 'block';
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Chart initialization
function initializeCharts() {
    // Appointment Status Distribution Chart
    const appointmentStatusCtx = document.getElementById('appointmentStatusChart').getContext('2d');
    const appointmentStatusData = {
        labels: <?= json_encode(array_map(function($item) { return ucfirst($item['status']); }, $analytics['appointment_status'])) ?>,
        datasets: [{
            data: <?= json_encode(array_map(function($item) { return $item['count']; }, $analytics['appointment_status'])) ?>,
            backgroundColor: [
                '#3B82F6', // Blue for approved
                '#10B981', // Green for completed  
                '#F59E0B', // Yellow for pending
                '#EF4444', // Red for rejected
                '#8B5CF6', // Purple for cancelled
                '#F59E0B', // Yellow for missed (same as pending)
                '#6B7280'  // Gray for others
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    };

    new Chart(appointmentStatusCtx, {
        type: 'pie',
        data: appointmentStatusData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        boxWidth: 12,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Monthly Trend Chart
    const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    const monthlyTrendData = {
        labels: <?= json_encode(array_map(function($item) { 
            return date('M Y', strtotime($item['month'] . '-01'));
        }, $analytics['monthly_trend'])) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_map(function($item) { return $item['count']; }, $analytics['monthly_trend'])) ?>,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3B82F6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    };

    new Chart(monthlyTrendCtx, {
        type: 'line',
        data: monthlyTrendData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });

    // Patient Registration Trend Chart
    const patientRegCtx = document.getElementById('patientRegistrationChart').getContext('2d');
    const patientRegData = {
        labels: <?= json_encode(array_map(function($item) { 
            return date('M Y', strtotime($item['month'] . '-01'));
        }, $analytics['patient_registration_trend'])) ?>,
        datasets: [{
            label: 'New Patients',
            data: <?= json_encode(array_map(function($item) { return $item['count']; }, $analytics['patient_registration_trend'])) ?>,
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10B981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4
        }]
    };

    new Chart(patientRegCtx, {
        type: 'line',
        data: patientRegData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                    }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });

    // Completion Rate Chart
    const completionRateCtx = document.getElementById('completionRateChart').getContext('2d');
    const totalAppointments = <?= array_sum(array_map(function($item) { return $item['count']; }, $analytics['appointment_status'])) ?>;
    const completedAppointments = <?= 
        (function() use ($analytics) {
            $completed = 0;
            foreach ($analytics['appointment_status'] as $item) {
                if ($item['status'] === 'completed') {
                    $completed = $item['count'];
                    break;
                }
            }
            return $completed;
        })() 
    ?>;
    const completionRate = totalAppointments > 0 ? Math.round((completedAppointments / totalAppointments) * 100) : 0;

    new Chart(completionRateCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Remaining'],
            datasets: [{
                data: [completionRate, 100 - completionRate],
                backgroundColor: ['#10B981', '#E5E7EB'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        }
    });

    // Add completion rate text in the center
    const completionRateText = document.createElement('div');
    completionRateText.className = 'absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center';
    completionRateText.innerHTML = `
        <div class="text-2xl font-bold text-gray-900">${completionRate}%</div>
        <div class="text-sm text-gray-600">Completion Rate</div>
    `;
    document.getElementById('completionRateChart').parentNode.style.position = 'relative';
    document.getElementById('completionRateChart').parentNode.appendChild(completionRateText);
}
</script>
</body>
</html>