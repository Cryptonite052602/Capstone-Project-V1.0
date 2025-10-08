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
            $requiredValues = ['pending', 'approved', 'completed', 'cancelled', 'rejected', 'rescheduled'];
            
            $missingValues = [];
            foreach ($requiredValues as $value) {
                if (stripos($currentType, $value) === false) {
                    $missingValues[] = $value;
                }
            }
            
            if (!empty($missingValues)) {
                // Update the enum to include missing values
                $newEnum = "ENUM('pending', 'approved', 'completed', 'cancelled', 'rejected', 'rescheduled') NOT NULL DEFAULT 'pending'";
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
        $mail->setFrom('cabanagarchiel@gmail.com', 'Community Health Tracker');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {
            $mail->Subject = 'Account Approval - Community Health Tracker';
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
                        .unique-number {
                            background: #e8f5e8;
                            border: 2px solid #4CAF50;
                            padding: 15px;
                            border-radius: 8px;
                            text-align: center;
                            margin: 20px 0;
                            font-size: 20px;
                            font-weight: bold;
                            color: #2e7d32;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 30px;
                            padding-top: 20px;
                            border-top: 1px solid #ddd;
                            color: #666;
                            font-size: 12px;
                        }
                        .button {
                            display: inline-block;
                            background: #4CAF50;
                            color: white;
                            padding: 12px 24px;
                            text-decoration: none;
                            border-radius: 5px;
                            margin: 10px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="logo">🏥 Community Health Tracker</div>
                        <h1>Account Approved</h1>
                    </div>
                    <div class="content">
                        <p>Dear Valued Patient,</p>
                        
                        <p>We are pleased to inform you that your account registration with <strong>Community Health Tracker</strong> has been successfully approved by our administrative team.</p>
                        
                        <div class="unique-number">
                            Your Unique Identification Number:<br>
                            <strong>' . $uniqueNumber . '</strong>
                        </div>
                        
                        <p>Please keep this number secure as it will be used to identify you in our healthcare system for all future appointments and medical records.</p>
                        
                        <p>With your approved account, you can now:</p>
                        <ul>
                            <li>Schedule appointments with healthcare providers</li>
                            <li>Access your medical history and records</li>
                            <li>Receive personalized health recommendations</li>
                            <li>Communicate securely with healthcare staff</li>
                        </ul>
                        
                        <p style="text-align: center;">
                            <a href="https://your-health-portal.com/login" class="button">Access Your Account</a>
                        </p>
                        
                        <p>If you have any questions or require assistance, please don\'t hesitate to contact our support team.</p>
                        
                        <p>Thank you for choosing Community Health Tracker for your healthcare needs.</p>
                        
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
        } else {
            $mail->Subject = 'Account Registration Update - Community Health Tracker';
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
                            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
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
                        .reason-box {
                            background: #ffeaea;
                            border: 2px solid #ff6b6b;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 20px 0;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 30px;
                            padding-top: 20px;
                            border-top: 1px solid #ddd;
                            color: #666;
                            font-size: 12px;
                        }
                        .contact-info {
                            background: #e3f2fd;
                            padding: 15px;
                            border-radius: 8px;
                            margin: 20px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="logo">🏥 Community Health Tracker</div>
                        <h1>Account Registration Update</h1>
                    </div>
                    <div class="content">
                        <p>Dear Applicant,</p>
                        
                        <p>Thank you for your interest in registering with <strong>Community Health Tracker</strong>. After careful review of your application, we regret to inform you that we are unable to approve your account registration at this time.</p>
                        
                        <div class="reason-box">
                            <strong>Reason for Declination:</strong><br>
                            ' . htmlspecialchars($message) . '
                        </div>
                        
                        <p>This decision may be due to various factors including incomplete information, documentation requirements, or current system capacity limitations.</p>
                        
                        <div class="contact-info">
                            <strong>Need Assistance?</strong><br>
                            If you believe this decision was made in error, or if you would like to provide additional information for reconsideration, please contact our support team:<br>
                            📞 Support Hotline: (02) 8-123-4567<br>
                            ✉️ Email: support@communityhealthtracker.ph
                        </div>
                        
                        <p>We appreciate your understanding and encourage you to reach out if you have any questions about this decision or if you wish to reapply in the future.</p>
                        
                        <p>Thank you for considering Community Health Tracker for your healthcare needs.</p>
                        
                        <p>Sincerely,<br>
                        <strong>The Community Health Tracker Team</strong></p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; ' . date('Y') . ' Community Health Tracker. All rights reserved.</p>
                    </div>
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

// Function to generate sequential priority number
function generatePriorityNumber($pdo, $appointmentId, $staffId, $date) {
    // Get the count of approved appointments for this staff on this date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as appointment_count 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ? AND a.date = ? AND ua.status = 'approved'
        AND ua.id <= ?
    ");
    $stmt->execute([$staffId, $date, $appointmentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sequenceNumber = $result['appointment_count'] ?? 1;
    
    // Get staff initials
    $stmt = $pdo->prepare("SELECT full_name FROM sitio1_users WHERE id = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $initials = 'HW';
    if ($staff && !empty($staff['full_name'])) {
        $nameParts = explode(' ', $staff['full_name']);
        $initials = '';
        foreach ($nameParts as $part) {
            if (!empty(trim($part))) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }
        if (empty($initials)) $initials = 'HW';
    }
    
    // Format: HW_INITIALS-01, HW_INITIALS-02, etc.
    return $initials . '-' . str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT);
}

// Function to generate appointment ticket
function generateAppointmentTicket($appointmentData, $priorityNumber) {
    // Create HTML content for the ticket
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
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
                </div>
                
                <div class="section">
                    <h2>📅 Appointment Details</h2>
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
                            <span class="label">Health Worker:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['staff_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Specialization:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['specialization']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>👤 Patient Information</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Name:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['full_name']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Contact:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['contact']) . '</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Patient ID:</span>
                            <span class="value">' . htmlspecialchars($appointmentData['unique_number']) . '</span>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2>🏥 Health Concerns</h2>
                    <div style="padding: 10px 0;">
                        ' . (!empty($appointmentData['health_concerns']) ? nl2br(htmlspecialchars($appointmentData['health_concerns'])) : 'General Checkup') . '
                    </div>
                </div>

                <div class="barcode">
                    *' . $priorityNumber . '*
                </div>
                
                <div class="footer">
                    <p>🚨 Please bring this ticket to your appointment</p>
                    <p>⏰ Arrive 15 minutes before your scheduled time</p>
                    <p>📅 Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
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
                $invoiceNumber = $appointment['invoice_number'] ?? 'N/A';
                $healthConcerns = str_replace(["\t", "\n", "\r"], " ", $appointment['health_concerns'] ?? 'No concerns specified');
                
                echo "$appointmentId\t$patientName\t$patientId\t$contactNumber\t$date\t$time\t$status\t$priorityNumber\t$invoiceNumber\t$healthConcerns\n";
            }
            exit;
            
        } elseif ($exportType === 'pdf') {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->SetTitle('Appointments Report');
            
            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
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
                    
                    $success = 'Appointment slot added successfully!';
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
                    $success = 'Appointment slot updated successfully!';
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
                    $success = 'Appointment slot deleted successfully!';
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
                if ($action === 'approve') {
                    // Get appointment details for priority number generation
                    $stmt = $pdo->prepare("
                        SELECT a.staff_id, a.date 
                        FROM user_appointments ua
                        JOIN sitio1_appointments a ON ua.appointment_id = a.id
                        WHERE ua.id = ?
                    ");
                    $stmt->execute([$appointmentId]);
                    $appointmentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$appointmentDetails) {
                        throw new Exception('Appointment not found');
                    }
                    
                    // Generate invoice and priority number
                    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);
                    $priorityNumber = generatePriorityNumber($pdo, $appointmentId, $appointmentDetails['staff_id'], $appointmentDetails['date']);
                    
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
                        
                        $success = 'Appointment approved successfully! Priority Number: <strong>' . $priorityNumber . '</strong> | Invoice: <strong>' . $invoiceNumber . '</strong>';
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
                            $success = 'Appointment rejected successfully!';
                        } else {
                            $error = 'Failed to reject appointment. Please try again.';
                        }
                    }
                } elseif ($action === 'complete') {
                    $stmt = $pdo->prepare("UPDATE user_appointments SET status = 'completed', completed_at = NOW() WHERE id = ?");
                    $stmt->execute([$appointmentId]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = 'Appointment marked as completed successfully!';
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
                    
                    $success = 'User approved successfully! Unique number: ' . $uniqueNumber;
                    
                    // Refresh the page to update the UI
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode('User approved successfully! Unique number: ' . $uniqueNumber));
                    exit();
                } else {
                    $declineReason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = FALSE, status = 'declined' WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Send decline email with reason
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'declined', $declineReason);
                    }
                    
                    $success = 'User declined successfully!';
                    
                    // Refresh the page to update the UI
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode('User declined successfully!'));
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
        // Verify the appointment exists and is cancelled
        $stmt = $pdo->prepare("
            SELECT ua.id 
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE ua.id = ? AND ua.status = 'cancelled' AND a.staff_id = ?
        ");
        $stmt->execute([$cancelledAppointmentId, $staffId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            throw new Exception('Cancelled appointment not found or you do not have permission to delete it.');
        }
        
        // Delete the cancelled appointment
        $stmt = $pdo->prepare("DELETE FROM user_appointments WHERE id = ?");
        $stmt->execute([$cancelledAppointmentId]);
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Cancelled appointment deleted successfully.'
        ];
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=appointments&appointment_tab=cancelled');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Error deleting cancelled appointment: ' . $e->getMessage()
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Check for success message from URL parameter (after redirect)
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterDate = $_GET['date'] ?? '';

// Get data for dashboard
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_patients WHERE added_by = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $stats['total_patients'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_consultations WHERE status = 'pending'");
    $stats['pending_consultations'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM user_appointments WHERE status = 'pending'");
    $stats['pending_appointments'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $stats['unapproved_users'] = $stmt->fetchColumn();
    
    // Get available slots with accurate booking counts - ONLY FUTURE SLOTS
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            COUNT(ua.id) as booked_count,
            -- Check if the appointment time is in the past
            (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past
        FROM sitio1_appointments a 
        LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.staff_id = ? 
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))
        GROUP BY a.id 
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending appointments
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'pending' 
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $pendingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming appointments (approved but not completed)
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time,
               ua.priority_number, ua.invoice_number
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'approved' 
        AND (a.date >= CURDATE())
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
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
    
    // Get all appointments with filters
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
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unapproved users
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined') ORDER BY created_at DESC");
    $unapprovedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        GROUP_CONCAT(CONCAT(a.start_time, '-', a.end_time) SEPARATOR ',') as time_slots
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            border-bottom: 2px solid #3b82f6;
            color: #2563eb;
        }
        .modal {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.9);
            transition: all 0.3s ease-out;
        }
        .modal.active {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
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
            border-radius: 12px !important;
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
            border-color: #3b82f6 !important;
            background: #3b82f6 !important;
            color: white !important;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
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
            border-color: #3b82f6 !important;
            background: #3b82f6 !important;
            color: white !important;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
            transform: scale(1.02);
            z-index: 5;
        }

        .time-slot.selected * {
            color: white !important;
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
            border-color: #3b82f6;
            color: #1e40af;
            font-weight: bold;
        }
        
        /* Modal buttons */
        .modal-button {
            border-radius: 8px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
        }

        /* Simple hover effect for available dates */
        .calendar-day:not(.disabled):not(.selected):hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .time-slot:not(.disabled):not(.selected):hover {
            border-color: #3b82f6;
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
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Staff Dashboard
            </h1>
            <!-- Help Button -->
            <button onclick="openHelpModal()" class="help-icon bg-gray-200 text-gray-600 p-2 rounded-full hover:bg-gray-300 transition">
                <i class="fas fa-question-circle text-xl"></i>
            </button>
        </div>

        <!-- Help/Guide Modal -->
        <div id="helpModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl leading-6 font-medium text-gray-900">Staff Dashboard Guide</h3>
                        <button onclick="closeHelpModal()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
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
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="closeHelpModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium modal-button">
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
        <div class="mb-6 border-b border-gray-200">
            <ul class="flex flex-wrap -mb-px" id="dashboardTabs" role="tablist">
                <li class="mr-2" role="presentation">
                    <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="appointment-tab" data-tabs-target="#appointment-management" type="button" role="tab" aria-controls="appointment-management" aria-selected="false">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Appointment Management
                        <span class="count-badge bg-blue-100 text-blue-800 ml-2"><?= $stats['pending_appointments'] ?></span>
                    </button>
                </li>
                <li class="mr-2" role="presentation">
                    <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="account-tab" data-tabs-target="#account-management" type="button" role="tab" aria-controls="account-management" aria-selected="false">
                        <i class="fas fa-user-check mr-2"></i>
                        Account Approvals
                        <span class="count-badge bg-red-100 text-red-800 ml-2"><?= $stats['unapproved_users'] ?></span>
                    </button>
                </li>
            </ul>
        </div>
        
        <!-- Tab Contents -->
        <div class="tab-content">
            <!-- Appointment Management Section -->
            <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="appointment-management" role="tabpanel" aria-labelledby="appointment-tab">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-blue-700">Appointment Management</h2>
                </div>
                
                <!-- Navigation Tabs -->
                <div class="mb-6 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px" id="appointmentTabs" role="tablist">
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="add-slot-tab" data-tabs-target="#add-slot" type="button" role="tab" aria-controls="add-slot" aria-selected="false">
                                <i class="fas fa-plus-circle mr-2"></i>
                                Add Slot
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="available-slots-tab" data-tabs-target="#available-slots" type="button" role="tab" aria-controls="available-slots" aria-selected="false">
                                <i class="fas fa-list-alt mr-2"></i>
                                Available Slots
                                <span class="count-badge bg-blue-100 text-blue-800 ml-2"><?= count($availableSlots) ?></span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="pending-tab" data-tabs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">
                                <i class="fas fa-clock mr-2"></i>
                                Pending Appointments
                                <span class="count-badge bg-yellow-100 text-yellow-800 ml-2"><?= count($pendingAppointments) ?></span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="upcoming-tab" data-tabs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="false">
                                <i class="fas fa-calendar-day mr-2"></i>
                                Upcoming Appointments
                                <span class="count-badge bg-green-100 text-green-800 ml-2"><?= count($upcomingAppointments) ?></span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="cancelled-tab" data-tabs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="false">
                                <i class="fas fa-times-circle mr-2"></i>
                                Cancelled Appointments
                                <span class="count-badge bg-red-100 text-red-800 ml-2"><?= count($cancelledAppointments) ?></span>
                            </button>
                        </li>
                        <li class="mr-2" role="presentation">
                            <button class="inline-flex items-center p-4 border-b-2 rounded-t-lg" id="all-tab" data-tabs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="false">
                                <i class="fas fa-history mr-2"></i>
                                All Appointments
                                <span class="count-badge bg-gray-100 text-gray-800 ml-2"><?= count($allAppointments) ?></span>
                            </button>
                        </li>
                    </ul>
                </div>
                
                <!-- Tab Contents -->
                <div class="tab-content">
                    <!-- Add Available Slot -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="add-slot" role="tabpanel" aria-labelledby="add-slot-tab">
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
                                    <div class="w-4 h-4 bg-gray-100 rounded mr-2"></div>
                                    <span class="text-sm">Unavailable</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Slots Selection -->
                        <div id="timeSlotsSection" class="hidden mb-6 bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4 text-blue-700">Select Time Slots for <span id="selectedDateDisplay" class="text-blue-600"></span></h3>
                            
                            <div class="mb-4">
                                <h4 class="font-medium mb-2">Morning Slots (8:00 AM - 12:00 PM)</h4>
                                <div class="grid grid-cols-2 gap-3" id="morningSlotsContainer">
                                    <?php foreach ($morningSlots as $index => $slot): ?>
                                        <div class="time-slot border rounded-lg p-3 cursor-pointer" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                            <div class="flex justify-between items-center">
                                                <span class="font-medium"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></span>
                                                <span class="availability-indicator text-sm text-gray-500">Available</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h4 class="font-medium mb-2">Afternoon Slots (1:00 PM - 5:00 PM)</h4>
                                <div class="grid grid-cols-2 gap-3" id="afternoonSlotsContainer">
                                    <?php foreach ($afternoonSlots as $index => $slot): ?>
                                        <div class="time-slot border rounded-lg p-3 cursor-pointer" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                            <div class="flex justify-between items-center">
                                                <span class="font-medium"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></span>
                                                <span class="availability-indicator text-sm text-gray-500">Available</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="max_slots" class="block text-gray-700 mb-2 font-medium">Maximum Appointments per Slot *</label>
                                <select id="max_slots" name="max_slots" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                            
                            <input type="hidden" id="selected_date" name="date">
                            <input type="hidden" id="selected_time_slot" name="time_slot">
                            
                            <button type="button" id="addSlotBtn" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition font-medium action-button button-disabled" disabled>
                                <i class="fas fa-plus-circle mr-2"></i> Add Selected Time Slot
                            </button>
                        </div>
                    </div>
                    
                    <!-- Available Slots -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="available-slots" role="tabpanel" aria-labelledby="available-slots-tab">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-blue-700">Your Available Slots</h2>
                            <?php
                            // Calculate total available slots across all time slots
                            $totalAvailableSlots = 0;
                            $totalBookedSlots = 0;
                            $totalMaxSlots = 0;
                            
                            if (!empty($availableSlots)) {
                                foreach ($availableSlots as $slot) {
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
                        
                        <?php if (empty($availableSlots)): ?>
                            <div class="bg-blue-50 p-4 rounded-lg text-center">
                                <p class="text-gray-600">No available slots found.</p>
                                <button onclick="switchAppointmentTab('add-slot')" class="mt-2 text-blue-600 hover:underline font-medium">
                                    Click here to add a new slot
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
                                        <?php foreach ($availableSlots as $slot): 
                                            $currentDate = date('Y-m-d');
                                            $currentTime = date('H:i:s');
                                            $isPast = $slot['date'] < $currentDate || 
                                                     ($slot['date'] == $currentDate && $slot['end_time'] < $currentTime);
                                            $isToday = $slot['date'] == $currentDate;
                                            $bookedCount = $slot['booked_count'] ?? 0;
                                            $maxSlots = $slot['max_slots'];
                                            $availableSlotsCount = max(0, $maxSlots - $bookedCount);
                                            $percentage = $maxSlots > 0 ? min(100, ($bookedCount / $maxSlots) * 100) : 0;
                                            
                                            // Determine status class
                                            $statusClass = 'slot-available';
                                            $statusText = 'Available';
                                            
                                            if ($isPast) {
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
                                                    <?php if (!$isPast): ?>
                                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($slot)) ?>, '<?= $slot['start_time'] ?> - <?= $slot['end_time'] ?>')" 
                                                                class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                                                            <button type="submit" name="delete_slot" 
                                                                    class="text-red-600 hover:text-red-900" 
                                                                    onclick="return confirm('Are you sure you want to delete this slot?')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">No actions available</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pending Appointments -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-blue-700">Pending Appointments</h2>
                            <span class="text-sm text-gray-600"><?= count($pendingAppointments) ?> pending</span>
                        </div>
                        
                        <?php if (empty($pendingAppointments)): ?>
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
                                        <?php foreach ($pendingAppointments as $appointment): ?>
                                            <tr>
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
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Pending
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No health concerns specified' ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" name="approve_appointment" 
                                                                class="bg-green-500 text-white action-button mr-2">
                                                            <i class="fas fa-check-circle mr-1"></i> Approve
                                                        </button>
                                                    </form>
                                                    <button onclick="openRejectionModal(<?= $appointment['id'] ?>)" 
                                                            class="bg-red-500 text-white action-button">
                                                        <i class="fas fa-times-circle mr-1"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Upcoming Appointments -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-blue-700">Upcoming Appointments</h2>
                            <span class="text-sm text-gray-600"><?= count($upcomingAppointments) ?> upcoming</span>
                        </div>
                        
                        <?php if (empty($upcomingAppointments)): ?>
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
                                        <?php foreach ($upcomingAppointments as $appointment): 
                                            $isToday = $appointment['date'] == date('Y-m-d');
                                        ?>
                                            <tr>
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
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Approved
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                        <input type="hidden" name="action" value="complete">
                                                        <button type="submit" name="approve_appointment" 
                                                                class="bg-blue-500 text-white action-button">
                                                            <i class="fas fa-check-circle mr-1"></i> Mark Completed
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Cancelled Appointments -->
                    <div class="hidden p-6 bg-white rounded-lg border border-gray-200" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-red-700">Cancelled Appointments</h2>
                            <span class="text-sm text-gray-600"><?= count($cancelledAppointments) ?> cancelled</span>
                        </div>
                        
                        <?php if (empty($cancelledAppointments)): ?>
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
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cancelled At</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($cancelledAppointments as $appointment): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900"><?= date('M d, Y', strtotime($appointment['date'])) ?></div>
                                                    <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($appointment['start_time'])) ?> - <?= date('g:i A', strtotime($appointment['end_time'])) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?= ($appointment['cancelled_by'] == 'Cancelled by Patient' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800') ?>">
                                                        <?= htmlspecialchars($appointment['cancelled_by']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 max-w-xs"><?= !empty($appointment['cancel_reason']) ? htmlspecialchars($appointment['cancel_reason']) : 'No reason provided' ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= date('M d, Y g:i A', strtotime($appointment['cancelled_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to permanently delete this cancelled appointment? This action cannot be undone.');">
                                                        <input type="hidden" name="cancelled_appointment_id" value="<?= $appointment['id'] ?>">
                                                        <button type="submit" name="delete_cancelled_appointment" class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash mr-1"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- All Appointments -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-blue-700">All Appointments</h2>
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
                                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                                    <button onclick="exportData('excel')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition font-medium flex items-center">
                                        <i class="fas fa-file-excel mr-2"></i> Export Excel
                                    </button>
                                    <button onclick="exportData('pdf')" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium flex items-center">
                                        <i class="fas fa-file-pdf mr-2"></i> Export PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($allAppointments)): ?>
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
                                        <?php foreach ($allAppointments as $appointment): 
                                            $isPast = strtotime($appointment['date']) < strtotime(date('Y-m-d'));
                                        ?>
                                            <tr class="<?= $isPast ? 'bg-gray-50' : '' ?>">
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
                                                           ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))) ?>">
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
                                                            class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition font-medium">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Account Management Section -->
            <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="account-management" role="tabpanel" aria-labelledby="account-tab">
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
                                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
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
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $user['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                                   ($user['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                   'bg-red-100 text-red-800') ?>">
                                                <?= ucfirst($user['status'] ?? 'pending') ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <?php if ($user['status'] !== 'approved'): ?>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" name="approve_user" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 mr-2 action-button">Approve</button>
                                                </form>
                                                
                                                <!-- Decline with reason modal trigger -->
                                                <button onclick="openDeclineModal(<?= $user['id'] ?>)" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 action-button">Decline</button>
                                            <?php else: ?>
                                                <span class="text-gray-400">No actions available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 p-5 border w-full max-w-md shadow-lg rounded-md bg-white modal">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-3" id="successMessage"></h3>
                <div class="px-4 py-3 sm:px-6">
                    <button type="button" onclick="closeSuccessModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm modal-button">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 p-5 border w-full max-w-md shadow-lg rounded-md bg-white modal">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-3" id="errorMessage"></h3>
                <div class="px-4 py-3 sm:px-6">
                    <button type="button" onclick="closeErrorModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm modal-button">
                        OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Reject Appointment</h3>
                <form id="rejectionForm" method="POST" action="">
                    <input type="hidden" name="appointment_id" id="reject_appointment_id">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="mb-4">
                        <label for="rejection_reason" class="block text-gray-700 mb-2 font-medium">Reason for Rejection *</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" 
                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                  placeholder="Please provide a reason for rejecting this appointment..." required></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeRejectionModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium modal-button">
                            Cancel
                        </button>
                        <button type="submit" name="approve_appointment" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium modal-button">
                            Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Appointment Modal -->
    <div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Appointment Details</h3>
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
                    <?php if ($appointment['status'] === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                    <div class="mt-4">
                        <h4 class="font-semibold text-red-700">Rejection Reason</h4>
                        <p class="text-sm text-red-600 mt-2 bg-red-50 p-3 rounded border" id="viewRejectionReason"></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeViewModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium modal-button">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Slot Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Appointment Slot</h3>
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
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium modal-button">
                            Cancel
                        </button>
                        <button type="submit" name="update_slot" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium modal-button">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div id="declineModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Decline Patient Account</h3>
                <div class="mt-2 px-7 py-3">
                    <form id="declineForm" method="POST" action="">
                        <input type="hidden" name="user_id" id="declineUserId">
                        <input type="hidden" name="action" value="decline">
                        <div class="mb-4">
                            <label for="decline_reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for declining:</label>
                            <textarea name="decline_reason" id="decline_reason" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                        </div>
                        <div class="items-center px-4 py-3">
                            <button type="submit" name="approve_user" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 modal-button">
                                Confirm Decline
                            </button>
                            <button type="button" onclick="closeDeclineModal()" class="ml-3 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 modal-button">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Tab functionality
    function switchTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content > div').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Show selected tab content
        document.getElementById(tabId).classList.remove('hidden');
        
        // Update active tab style
        document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
            tabBtn.classList.remove('border-blue-500', 'text-blue-600');
            tabBtn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        });
        
        // Set active tab
        const activeTabBtn = document.querySelector(`#dashboardTabs button[data-tabs-target="#${tabId}"]`);
        activeTabBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        activeTabBtn.classList.add('border-blue-500', 'text-blue-600');
    }

    function switchAppointmentTab(tabId) {
        // Hide all tab contents
        document.querySelectorAll('#appointment-management .tab-content > div').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Show selected tab content
        document.getElementById(tabId).classList.remove('hidden');
        
        // Update active tab style
        document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
            tabBtn.classList.remove('border-blue-500', 'text-blue-600');
            tabBtn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        });
        
        // Set active tab
        const activeTabBtn = document.querySelector(`#appointmentTabs button[data-tabs-target="#${tabId}"]`);
        activeTabBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        activeTabBtn.classList.add('border-blue-500', 'text-blue-600');
    }

    // Modal functions
    function openEditModal(slot, timeSlotValue) {
        document.getElementById('edit_slot_id').value = slot.id;
        document.getElementById('edit_date').value = slot.date;
        document.getElementById('edit_max_slots').value = slot.max_slots;
        
        // Set the time slot radio button
        const timeSlotInputs = document.querySelectorAll('#editSlotForm input[name="time_slot"]');
        timeSlotInputs.forEach(input => {
            if (input.value === timeSlotValue) {
                input.checked = true;
            }
        });
        
        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    function openRejectionModal(appointmentId) {
        document.getElementById('reject_appointment_id').value = appointmentId;
        document.getElementById('rejection_reason').value = '';
        document.getElementById('rejectionModal').classList.remove('hidden');
    }

    function closeRejectionModal() {
        document.getElementById('rejectionModal').classList.add('hidden');
    }
    
    function openDeclineModal(userId) {
        document.getElementById('declineUserId').value = userId;
        document.getElementById('decline_reason').value = '';
        document.getElementById('declineModal').classList.remove('hidden');
    }
    
    function closeDeclineModal() {
        document.getElementById('declineModal').classList.add('hidden');
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
        
        // Status with color coding
        const statusElement = document.getElementById('viewStatus');
        statusElement.textContent = appointment.status ? appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1) : 'N/A';
        statusElement.className = 'text-sm font-semibold ' + 
            (appointment.status === 'approved' ? 'text-green-600' :
             appointment.status === 'pending' ? 'text-yellow-600' :
             appointment.status === 'rejected' ? 'text-red-600' :
             appointment.status === 'completed' ? 'text-blue-600' : 'text-gray-600');
        
        document.getElementById('viewPriorityNumber').textContent = appointment.priority_number || 'N/A';
        document.getElementById('viewInvoiceNumber').textContent = appointment.invoice_number || 'N/A';
        document.getElementById('viewHealthConcerns').textContent = appointment.health_concerns || 'No health concerns specified';
        
        if (appointment.status === 'rejected' && appointment.rejection_reason) {
            document.getElementById('viewRejectionReason').textContent = appointment.rejection_reason;
            document.getElementById('viewRejectionReason').parentElement.style.display = 'block';
        } else {
            document.getElementById('viewRejectionReason').parentElement.style.display = 'none';
        }
        
        document.getElementById('viewModal').classList.remove('hidden');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }
    
    function showSuccessModal(message) {
        document.getElementById('successMessage').innerHTML = message;
        const modal = document.getElementById('successModal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.querySelector('.modal').classList.add('active');
        }, 10);
        
        // Auto close after 5 seconds
        setTimeout(() => {
            closeSuccessModal();
        }, 5000);
    }
    
    function closeSuccessModal() {
        const modal = document.getElementById('successModal');
        modal.querySelector('.modal').classList.remove('active');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    
    function showErrorModal(message) {
        document.getElementById('errorMessage').textContent = message;
        const modal = document.getElementById('errorModal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.querySelector('.modal').classList.add('active');
        }, 10);
        
        // Auto close after 5 seconds
        setTimeout(() => {
            closeErrorModal();
        }, 5000);
    }
    
    function closeErrorModal() {
        const modal = document.getElementById('errorModal');
        modal.querySelector('.modal').classList.remove('active');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Help modal functions
    function openHelpModal() {
        document.getElementById('helpModal').classList.remove('hidden');
    }

    function closeHelpModal() {
        document.getElementById('helpModal').classList.add('hidden');
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
        
        // Remove trailing & or ? if no parameters
        if (url === '?') {
            url = '';
        } else {
            url = url.slice(0, -1);
        }
        
        window.location.href = url;
    }

    // Calendar functionality
    let currentMonth = <?= date('m') ?>;
    let currentYear = <?= date('Y') ?>;
    let selectedDate = null;
    let selectedTimeSlot = null;
    const phHolidays = <?= json_encode($phHolidays) ?>;
    const dateSlots = <?= json_encode($dateSlots) ?>;
    const nextAvailableDate = '<?= $nextAvailableDate ?>';

    function generateCalendar(month, year) {
        const calendarEl = document.getElementById('calendar');
        calendarEl.innerHTML = '';
        
        const firstDay = new Date(year, month - 1, 1).getDay();
        const daysInMonth = new Date(year, month, 0).getDate();
        
        // Update month/year display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('currentMonthYear').textContent = `${monthNames[month - 1]} ${year}`;
        
        // Add empty cells for days before the first day of the month
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.classList.add('calendar-day', 'disabled', 'text-center', 'p-2', 'rounded', 'bg-gray-100', 'text-gray-400');
            calendarEl.appendChild(emptyCell);
        }
        
        // Add cells for each day of the month
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const currentTime = new Date();
        
        // Auto-select the next available date on first load
        let autoSelected = false;
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
            const dateObj = new Date(year, month - 1, day);
            
            const dayCell = document.createElement('div');
            dayCell.classList.add('calendar-day', 'text-center', 'p-2', 'rounded', 'border', 'border-gray-200');
            
            // Check if it's today
            const isToday = dateObj.toDateString() === today.toDateString();
            
            // Check if it's a weekend
            const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;
            
            // Check if it's in the past
            const isPast = dateObj < today;
            
            // Check if it's a holiday
            const isHoliday = phHolidays.hasOwnProperty(dateStr);
            
            // Check if date has available slots
            const hasSlots = dateSlots.hasOwnProperty(dateStr);
            
            // Add appropriate classes based on date status
            if (isPast) {
                dayCell.classList.add('disabled', 'bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
                dayCell.title = 'This date has passed and cannot be selected';
            } else if (isHoliday) {
                dayCell.classList.add('holiday', 'bg-red-50', 'text-red-700', 'border-red-200', 'cursor-not-allowed');
                dayCell.title = 'Holiday: ' + phHolidays[dateStr];
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
            
            // Auto-select the next available date on first load
            if (!autoSelected && !isPast && !isHoliday && hasSlots && dateStr === nextAvailableDate) {
                dayCell.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
                selectedDate = dateStr;
                autoSelected = true;
                
                // Update selected date display
                document.getElementById('selectedDateDisplay').textContent = `${monthNames[month - 1]} ${day}, ${year}`;
                document.getElementById('selected_date').value = dateStr;
                
                // Show time slots section
                document.getElementById('timeSlotsSection').classList.remove('hidden');
                
                // Update time slots for selected date
                updateTimeSlotsForDate(dateStr);
            }
            
            // Add day number
            const dayNumber = document.createElement('div');
            dayNumber.classList.add('font-semibold', 'text-lg', 'mb-1');
            dayNumber.textContent = day;
            dayCell.appendChild(dayNumber);
            
            // Add day indicator for slots
            if (!isPast && !isHoliday) {
                const dayIndicator = document.createElement('div');
                dayIndicator.classList.add('day-indicator');
                dayCell.appendChild(dayIndicator);
            }
            
            // Add holiday indicator
            if (isHoliday) {
                const holidayIndicator = document.createElement('div');
                holidayIndicator.classList.add('text-xs', 'font-medium', 'text-red-500');
                holidayIndicator.textContent = 'HOLIDAY';
                dayCell.appendChild(holidayIndicator);
            }
            
            // Add today indicator
            if (isToday) {
                const todayIndicator = document.createElement('div');
                todayIndicator.classList.add('text-xs', 'font-medium', 'text-blue-600');
                todayIndicator.textContent = 'TODAY';
                dayCell.appendChild(todayIndicator);
            }
            
            // Add weekend indicator
            if (isWeekend && !isPast && !isHoliday) {
                const weekendIndicator = document.createElement('div');
                weekendIndicator.classList.add('text-xs', 'font-medium', 'text-blue-500');
                weekendIndicator.textContent = 'WEEKEND';
                dayCell.appendChild(weekendIndicator);
            }
            
            // Add click event if not disabled
            if (!isPast && !isHoliday) {
                dayCell.addEventListener('click', () => selectDate(dateStr, day, month, year));
            } else {
                dayCell.style.cursor = 'not-allowed';
            }
            
            calendarEl.appendChild(dayCell);
        }
    }
    
    function selectDate(dateStr, day, month, year) {
        selectedDate = dateStr;
        
        // Update selected date display
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        document.getElementById('selectedDateDisplay').textContent = `${monthNames[month - 1]} ${day}, ${year}`;
        document.getElementById('selected_date').value = dateStr;
        
        // Update calendar UI
        document.querySelectorAll('.calendar-day').forEach(dayEl => {
            dayEl.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        });
        
        // Add selection to clicked day
        event.currentTarget.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        
        // Show time slots section
        document.getElementById('timeSlotsSection').classList.remove('hidden');
        
        // Update time slots for selected date
        updateTimeSlotsForDate(dateStr);
        
        // Reset time slot selection and update button state
        selectedTimeSlot = null;
        updateAddSlotButtonState();
    }
    
    function updateTimeSlotsForDate(dateStr) {
        const currentDate = new Date();
        const isToday = dateStr === currentDate.toISOString().split('T')[0];
        
        // Reset all time slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected', 'disabled', 'bg-blue-500', 'text-white', 'border-blue-600');
            slot.classList.add('bg-white', 'border-gray-200');
            slot.style.cursor = 'pointer';
            
            // Reset availability indicator
            const indicator = slot.querySelector('.availability-indicator');
            indicator.textContent = 'Available';
            indicator.classList.remove('text-red-500', 'text-green-500');
            indicator.classList.add('text-gray-500');
            
            // Remove existing click events
            const newSlot = slot.cloneNode(true);
            slot.parentNode.replaceChild(newSlot, slot);
        });
        
        // Add click events to time slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    selectTimeSlot(this, this.getAttribute('data-time'));
                }
            });
        });
        
        // Disable past time slots for today
        if (isToday) {
            document.querySelectorAll('.time-slot').forEach(slot => {
                const timeRange = slot.getAttribute('data-time');
                const [startTime] = timeRange.split(' - ');
                const slotDateTime = new Date(`${dateStr}T${startTime}`);
                
                if (slotDateTime < currentDate) {
                    slot.classList.add('disabled');
                    slot.style.cursor = 'not-allowed';
                    const indicator = slot.querySelector('.availability-indicator');
                    indicator.textContent = 'Past';
                    indicator.classList.remove('text-gray-500');
                    indicator.classList.add('text-red-500');
                }
            });
        }
        
        // Reset time slot selection and update button state
        selectedTimeSlot = null;
        updateAddSlotButtonState();
    }
    
    function selectTimeSlot(slotEl, timeRange) {
        if (slotEl.classList.contains('disabled')) {
            return;
        }
        
        // Remove previous selection
        document.querySelectorAll('.time-slot').forEach(el => {
            if (!el.classList.contains('disabled')) {
                el.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
                el.classList.add('bg-white', 'border-gray-200');
            }
        });
        
        // Add selection to clicked slot
        slotEl.classList.remove('bg-white', 'border-gray-200');
        slotEl.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        
        selectedTimeSlot = timeRange;
        document.getElementById('selected_time_slot').value = timeRange;
        
        // Update button state
        updateAddSlotButtonState();
    }
    
    function updateAddSlotButtonState() {
        const addSlotBtn = document.getElementById('addSlotBtn');
        if (selectedDate && selectedTimeSlot) {
            addSlotBtn.disabled = false;
            addSlotBtn.classList.remove('button-disabled');
            addSlotBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
        } else {
            addSlotBtn.disabled = true;
            addSlotBtn.classList.add('button-disabled');
            addSlotBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        }
    }
    
    function addSelectedSlot() {
        if (!selectedDate) {
            showErrorModal('Please select a date');
            return;
        }
        
        if (!selectedTimeSlot) {
            showErrorModal('Please select a time slot');
            return;
        }
        
        const maxSlots = document.getElementById('max_slots').value;
        
        // Create a form and submit it
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
    
    // Initialize tabs
    document.addEventListener('DOMContentLoaded', function() {
        // Set first tab as active by default
        switchTab('appointment-management');
        switchAppointmentTab('add-slot');
        
        // Add click event listeners to all tab buttons
        document.querySelectorAll('#dashboardTabs button').forEach(tabBtn => {
            tabBtn.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
                switchTab(targetTab);
            });
        });
        
        // Add click event listeners to appointment tab buttons
        document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
            tabBtn.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
                switchAppointmentTab(targetTab);
            });
        });
        
        // Initialize calendar
        generateCalendar(currentMonth, currentYear);
        
        // Month navigation
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
        
        // Add slot button
        document.getElementById('addSlotBtn').addEventListener('click', addSelectedSlot);
        
        // Add time slot selection
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    const timeRange = this.getAttribute('data-time');
                    selectTimeSlot(this, timeRange);
                }
            });
        });
        
        // Filter event listeners
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('dateFilter').addEventListener('change', applyFilters);
        
        // Show success/error modals if messages exist
        <?php if ($success): ?>
            showSuccessModal('<?= addslashes($success) ?>');
        <?php endif; ?>
        
        <?php if ($error): ?>
            showErrorModal('<?= addslashes($error) ?>');
        <?php endif; ?>
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            closeEditModal();
        }
        
        const rejectionModal = document.getElementById('rejectionModal');
        if (event.target === rejectionModal) {
            closeRejectionModal();
        }
        
        const successModal = document.getElementById('successModal');
        if (event.target === successModal) {
            closeSuccessModal();
        }
        
        const errorModal = document.getElementById('errorModal');
        if (event.target === errorModal) {
            closeErrorModal();
        }
        
        const declineModal = document.getElementById('declineModal');
        if (event.target === declineModal) {
            closeDeclineModal();
        }
        
        const helpModal = document.getElementById('helpModal');
        if (event.target === helpModal) {
            closeHelpModal();
        }
        
        const viewModal = document.getElementById('viewModal');
        if (event.target === viewModal) {
            closeViewModal();
        }
    }
    </script>
</body>
</html>