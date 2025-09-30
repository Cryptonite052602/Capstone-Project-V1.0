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

// Check and add missing columns for rescheduling functionality
function checkAndAddRescheduleColumns($pdo) {
    $columns = [
        'rescheduled_from' => "INT NULL AFTER `status`",
        'rescheduled_at' => "DATETIME NULL AFTER `rescheduled_from`",
        'rescheduled_count' => "INT DEFAULT 0 AFTER `rescheduled_at`"
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
}

// Call this function to ensure columns exist
checkAndAddRescheduleColumns($pdo);

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
        $mail->setFrom('your-email@gmail.com', 'Community Health Tracker');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {
            $mail->Subject = 'Your Account Has Been Approved';
            $mail->Body    = '
                <h2>Account Approved</h2>
                <p>Your account with Community Health Tracker has been approved by our staff.</p>
                <p><strong>Your Unique Identification Number: ' . $uniqueNumber . '</strong></p>
                <p>Please keep this number safe as it will be used to identify you in our system.</p>
                <p>You can now log in and access all features of our system.</p>
                <p>Thank you for joining us!</p>
            ';
        } else {
            $mail->Subject = 'Your Account Approval Was Declined';
            $mail->Body    = '
                <h2>Account Declined</h2>
                <p>We regret to inform you that your account with Community Health Tracker was not approved.</p>
                <p>Reason: ' . htmlspecialchars($message) . '</p>
                <p>If you believe this was a mistake, please contact our support team.</p>
            ';
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to generate appointment ticket PDF
function generateAppointmentTicket($appointmentData, $priorityNumber) {
    // Create HTML content for the ticket
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Appointment Ticket - ' . $priorityNumber . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .ticket { border: 2px solid #3b82f6; padding: 20px; max-width: 600px; margin: 0 auto; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { color: #3b82f6; margin: 0; }
            .header p { color: #6b7280; margin: 5px 0; }
            .section { margin-bottom: 15px; }
            .section h2 { color: #374151; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; font-size: 18px; margin: 15px 0 10px 0; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .info-item { margin-bottom: 8px; }
            .label { font-weight: bold; color: #4b5563; }
            .consent { background-color: #f9fafb; padding: 15px; border-radius: 5px; margin-top: 20px; }
            .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
            .priority-number { 
                text-align: center; 
                font-size: 24px; 
                font-weight: bold; 
                color: #dc2626; 
                background-color: #fef2f2; 
                padding: 10px; 
                border-radius: 5px; 
                margin: 15px 0; 
            }
        </style>
    </head>
    <body>
        <div class="ticket">
            <div class="header">
                <h1>Community Health Tracker</h1>
                <p>Appointment Confirmation Ticket</p>
                <div class="priority-number">Priority Number: ' . $priorityNumber . '</div>
            </div>
            
            <div class="section">
                <h2>Appointment Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Date:</span> ' . date('F j, Y', strtotime($appointmentData['date'])) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Time:</span> ' . date('g:i A', strtotime($appointmentData['start_time'])) . ' - ' . date('g:i A', strtotime($appointmentData['end_time'])) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Health Worker:</span> ' . htmlspecialchars($appointmentData['staff_name']) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Specialization:</span> ' . htmlspecialchars($appointmentData['specialization']) . '
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>Patient Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Name:</span> ' . htmlspecialchars($appointmentData['full_name']) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Contact:</span> ' . htmlspecialchars($appointmentData['contact']) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Email:</span> ' . htmlspecialchars($appointmentData['email']) . '
                    </div>
                    <div class="info-item">
                        <span class="label">Patient ID:</span> ' . htmlspecialchars($appointmentData['unique_number']) . '
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2>Health Concerns</h2>
                <p>' . (!empty($appointmentData['health_concerns']) ? nl2br(htmlspecialchars($appointmentData['health_concerns'])) : 'No specific health concerns provided.') . '</p>
            </div>
            
            <div class="consent">
                <h2>Consent for Health Visit</h2>
                <p>I, ' . htmlspecialchars($appointmentData['full_name']) . ', hereby consent to receive health services from Community Health Tracker. I understand the purpose of the visit and the procedures that may be involved.</p>
                <p>I authorize the health worker to provide appropriate care based on my health concerns and needs.</p>
                <p><strong>Signature:</strong> _________________________________________</p>
                <p><strong>Date:</strong> _________________________</p>
            </div>
            
            <div class="footer">
                <p>Please bring this ticket to your appointment. Arrive 15 minutes early.</p>
                <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

// Function to automatically reschedule expired appointments
function rescheduleExpiredAppointments($pdo) {
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Find all pending appointments that have passed
    $stmt = $pdo->prepare("
        SELECT ua.*, a.date, a.start_time, a.end_time, a.staff_id
        FROM user_appointments ua 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE ua.status = 'pending' 
        AND (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW())))
    ");
    $stmt->execute();
    $expiredAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $rescheduledCount = 0;
    
    foreach ($expiredAppointments as $appointment) {
        // Find the next available date with the same time slot
        $originalDate = $appointment['date'];
        $startTime = $appointment['start_time'];
        $endTime = $appointment['end_time'];
        $staffId = $appointment['staff_id'];
        
        // Calculate next available date (7 days from original date)
        $newDate = date('Y-m-d', strtotime($originalDate . ' +7 days'));
        
        // Check if this slot exists on the new date, if not create it
        $checkStmt = $pdo->prepare("
            SELECT id FROM sitio1_appointments 
            WHERE staff_id = ? AND date = ? AND start_time = ? AND end_time = ?
        ");
        $checkStmt->execute([$staffId, $newDate, $startTime, $endTime]);
        $slotExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slotExists) {
            // Create the slot on the new date
            $maxSlots = 5; // Default value
            $insertStmt = $pdo->prepare("
                INSERT INTO sitio1_appointments (staff_id, date, start_time, end_time, max_slots) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$staffId, $newDate, $startTime, $endTime, $maxSlots]);
            $newSlotId = $pdo->lastInsertId();
        } else {
            $newSlotId = $slotExists['id'];
        }
        
        // Update the appointment with the new date and slot
        try {
            // Check if rescheduled_count column exists
            $columnCheck = $pdo->query("SHOW COLUMNS FROM user_appointments LIKE 'rescheduled_count'");
            $columnExists = $columnCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($columnExists) {
                $updateStmt = $pdo->prepare("
                    UPDATE user_appointments 
                    SET appointment_id = ?, rescheduled_from = ?, rescheduled_at = NOW(), 
                        rescheduled_count = COALESCE(rescheduled_count, 0) + 1
                    WHERE id = ?
                ");
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE user_appointments 
                    SET appointment_id = ?, rescheduled_from = ?, rescheduled_at = NOW()
                    WHERE id = ?
                ");
            }
            
            $updateStmt->execute([$newSlotId, $appointment['appointment_id'], $appointment['id']]);
            $rescheduledCount++;
            
        } catch (PDOException $e) {
            error_log("Error rescheduling appointment: " . $e->getMessage());
        }
    }
    
    return $rescheduledCount;
}

// Call this function to reschedule expired appointments
$rescheduledCount = rescheduleExpiredAppointments($pdo);
if ($rescheduledCount > 0) {
    // Optional: Log or notify about rescheduled appointments
    error_log("Auto-rescheduled $rescheduledCount expired appointments");
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

// Get stats for dashboard
$stats = [
    'total_patients' => 0,
    'pending_consultations' => 0,
    'pending_appointments' => 0,
    'unapproved_users' => 0
];

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
} catch (PDOException $e) {
    // Handle error
}

// Appointment Management Variables
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

// Get Philippine holidays
$currentYear = date('Y');
$phHolidays = getPhilippineHolidays($currentYear);

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
                $status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'completed');
                
                if ($action === 'reject' && empty($rejectionReason)) {
                    $error = 'Please provide a reason for rejecting this appointment.';
                } else {
                    $stmt = $pdo->prepare("UPDATE user_appointments SET status = ?, rejection_reason = ? WHERE id = ?");
                    $stmt->execute([$status, $rejectionReason, $appointmentId]);
                    
                    $success = 'Appointment ' . $status . ' successfully!';
                }
            } catch (PDOException $e) {
                $error = 'Error updating appointment: ' . $e->getMessage();
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

    // Handle invoice generation
    if (isset($_POST['generate_invoice'])) {
        $appointmentId = intval($_POST['appointment_id']);
        
        try {
            // Get appointment details
            $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number,
                   a.date, a.start_time, a.end_time,
                   s.full_name as staff_name, s.specialization
            FROM user_appointments ua
            JOIN sitio1_users u ON ua.user_id = u.id
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            JOIN sitio1_users s ON a.staff_id = s.id
            WHERE ua.id = ?");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                // Generate a unique invoice number
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($appointmentId, 4, '0', STR_PAD_LEFT);
                
                // Generate priority number (based on appointment time and date)
                $priorityNumber = 'P-' . date('md', strtotime($appointment['date'])) . 
                                 '-' . str_replace(':', '', substr($appointment['start_time'], 0, 5));
                
                // Update appointment with invoice details
                $updateStmt = $pdo->prepare("UPDATE user_appointments 
                                            SET invoice_number = ?, priority_number = ?, status = 'approved', 
                                            processed_at = NOW(), invoice_generated_at = NOW() 
                                            WHERE id = ?");
                $updateStmt->execute([$invoiceNumber, $priorityNumber, $appointmentId]);
                
                // Generate appointment ticket HTML
                $ticketHtml = generateAppointmentTicket($appointment, $priorityNumber);
                
                // Store the ticket HTML in session for download
                $_SESSION['appointment_ticket'] = $ticketHtml;
                $_SESSION['ticket_filename'] = 'appointment_ticket_' . $priorityNumber . '.html';
                
                // Send notification email to user with ticket attached as HTML
                if (filter_var($appointment['email'], FILTER_VALIDATE_EMAIL)) {
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
                        $mail->setFrom('your-email@gmail.com', 'Community Health Tracker');
                        $mail->addAddress($appointment['email']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Appointment Has Been Approved - Invoice #' . $invoiceNumber;
                        $mail->Body    = '
                            <h2>Appointment Approved</h2>
                            <p>Your appointment with Community Health Tracker has been approved.</p>
                            <p><strong>Appointment Details:</strong></p>
                            <ul>
                                <li>Date: ' . date('M d, Y', strtotime($appointment['date'])) . '</li>
                                <li>Time: ' . date('h:i A', strtotime($appointment['start_time'])) . ' - ' . date('h:i A', strtotime($appointment['end_time'])) . '</li>
                                <li>Health Worker: ' . htmlspecialchars($appointment['staff_name']) . '</li>
                                <li>Specialization: ' . htmlspecialchars($appointment['specialization']) . '</li>
                                <li>Priority Number: ' . $priorityNumber . '</li>
                                <li>Invoice Number: ' . $invoiceNumber . '</li>
                            </ul>
                            <p>Your appointment ticket has been generated and is available for download from your dashboard.</p>
                            <p>Please bring your ticket to your appointment.</p>
                            <p>Thank you for choosing our services!</p>
                        ';

                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                    }
                }
                
                $success = 'Invoice generated and appointment approved successfully! <a href="download_ticket.php" class="text-blue-600 underline">Download Ticket</a>';
            } else {
                $error = 'Appointment not found.';
            }
        } catch (PDOException $e) {
            $error = 'Error generating invoice: ' . $e->getMessage();
        }
    }
    
    // Handle approval without invoice
    if (isset($_POST['approve_without_invoice'])) {
        $appointmentId = intval($_POST['appointment_id']);
        
        try {
            $stmt = $pdo->prepare("UPDATE user_appointments SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $stmt->execute([$appointmentId]);
            
            $success = 'Appointment approved successfully!';
        } catch (PDOException $e) {
            $error = 'Error approving appointment: ' . $e->getMessage();
        }
    }
}

// Check for success message from URL parameter (after redirect)
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// Get available slots with accurate booking counts
$availableSlots = [];
// Get pending appointments
$pendingAppointments = [];
// Get all appointments
$allAppointments = [];
// Get unapproved users
$unapprovedUsers = [];

try {
    // Get available slots with accurate booking counts
    $stmt = $pdo->prepare("
        SELECT 
            a.*, 
            COUNT(ua.id) as booked_count,
            -- Check if the appointment time is in the past
            (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past
        FROM sitio1_appointments a 
        LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.staff_id = ? 
        GROUP BY a.id 
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending appointments (excluding those that will be rescheduled)
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, a.date, a.start_time, a.end_time
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'pending' 
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $pendingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all appointments with user's unique number
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, u.unique_number, a.date, a.start_time, a.end_time 
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          WHERE a.staff_id = ? 
                          ORDER BY a.date DESC, a.start_time DESC");
    $stmt->execute([$staffId]);
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unapproved users
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined') ORDER BY created_at DESC");
    $unapprovedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update stats after potential changes
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined')");
    $stats['unapproved_users'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    $error = 'Error fetching data: ' . $e->getMessage();
}

// Get available dates for calendar view
$calendarDates = [];
$currentMonth = date('m');
$currentYear = date('Y');

// Get all available slots grouped by date
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

// Function to handle expired appointments
function handleExpiredAppointments($pdo) {
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Find appointments that are past their scheduled time and still pending
    $stmt = $pdo->prepare("
        SELECT ua.*, a.date, a.start_time, a.end_time, a.staff_id
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.status = 'pending'
        AND (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW())))
    ");
    $stmt->execute();
    $expiredAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expiredAppointments as $appointment) {
        $originalDate = $appointment['date'];
        $originalStartTime = $appointment['start_time'];
        $originalEndTime = $appointment['end_time'];
        $staffId = $appointment['staff_id'];
        
        // Calculate next available date (skip weekends and holidays)
        $nextDate = findNextAvailableDate($pdo, $originalDate, $staffId, $originalStartTime, $originalEndTime);
        
        if ($nextDate) {
            // Check if slot already exists on the new date
            $checkStmt = $pdo->prepare("
                SELECT id FROM sitio1_appointments 
                WHERE staff_id = ? AND date = ? AND start_time = ? AND end_time = ?
            ");
            $checkStmt->execute([$staffId, $nextDate, $originalStartTime, $originalEndTime]);
            $existingSlot = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingSlot) {
                // Update appointment to use existing slot
                $updateStmt = $pdo->prepare("
                    UPDATE user_appointments 
                    SET appointment_id = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$existingSlot['id'], $appointment['id']]);
            } else {
                // Create new slot
                $slotStmt = $pdo->prepare("
                    INSERT INTO sitio1_appointments (staff_id, date, start_time, end_time, max_slots) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $slotStmt->execute([$staffId, $nextDate, $originalStartTime, $originalEndTime]);
                $newSlotId = $pdo->lastInsertId();
                
                // Update appointment to use new slot
                $updateStmt = $pdo->prepare("
                    UPDATE user_appointments 
                    SET appointment_id = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newSlotId, $appointment['id']]);
            }
            
            // Log the rescheduling
            error_log("Appointment #{$appointment['id']} rescheduled from {$originalDate} to {$nextDate}");
        } else {
            // If no available date found, keep the appointment but mark it as expired
            $updateStmt = $pdo->prepare("
                UPDATE user_appointments 
                SET status = 'expired' 
                WHERE id = ?
            ");
            $updateStmt->execute([$appointment['id']]);
        }
    }
    
    return count($expiredAppointments);
}

// Function to find the next available date considering holidays and weekends
function findNextAvailableDate($pdo, $originalDate, $staffId, $startTime, $endTime) {
    $currentYear = date('Y');
    $phHolidays = getPhilippineHolidays($currentYear);
    
    // Start from the day after the original date
    $nextDate = date('Y-m-d', strtotime($originalDate . ' +1 day'));
    
    // Try up to 30 days in the future
    for ($i = 0; $i < 30; $i++) {
        $dateObj = new DateTime($nextDate);
        $dayOfWeek = $dateObj->format('w'); // 0 = Sunday, 6 = Saturday
        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
        $isHoliday = array_key_exists($nextDate, $phHolidays);
        
        // Skip weekends and holidays
        if ($isWeekend || $isHoliday) {
            $nextDate = date('Y-m-d', strtotime($nextDate . ' +1 day'));
            continue;
        }
        
        // Check if staff already has appointments at this time on this date
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as conflict_count 
            FROM sitio1_appointments 
            WHERE staff_id = ? AND date = ? AND (
                (start_time < ? AND end_time > ?) OR 
                (start_time < ? AND end_time > ?) OR 
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $checkStmt->execute([$staffId, $nextDate, $endTime, $startTime, $endTime, $startTime, $startTime, $endTime]);
        $conflict = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conflict['conflict_count'] == 0) {
            return $nextDate;
        }
        
        $nextDate = date('Y-m-d', strtotime($nextDate . ' +1 day'));
    }
    
    return false; // No available date found within 30 days
}

// Call this function at the beginning of your script
$rescheduledCount = handleExpiredAppointments($pdo);
if ($rescheduledCount > 0) {
    // Refresh the data to reflect changes
    $stmt = $pdo->prepare("
        SELECT ua.*, u.full_name, u.email, u.contact, a.date, a.start_time, a.end_time 
        FROM user_appointments ua 
        JOIN sitio1_users u ON ua.user_id = u.id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE a.staff_id = ? AND ua.status = 'pending' AND a.date >= CURDATE() 
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $pendingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<script>
    // Add this to your DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    // ... existing code ...
    
    <?php if ($rescheduledCount > 0): ?>
        showSuccessModal('<?= $rescheduledCount ?> expired appointment(s) have been automatically rescheduled to the next available date.');
    <?php endif; ?>
});
</script>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/community-health-tracker/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/community-health-tracker/assets/js/scripts.js" defer></script>
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
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        /* Enhanced Calendar Styles */
.calendar-day {
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: all 0.2s ease-in-out;
    border: 2px solid transparent;
}

.calendar-day:hover:not(.disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.calendar-day.selected {
    border-color: #3b82f6 !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    z-index: 10;
}

.calendar-day.disabled {
    opacity: 0.6;
    cursor: not-allowed !important;
}

.calendar-day .font-semibold {
    font-weight: 600;
}

/* Time Slot Styles */
.time-slot {
    transition: all 0.2s ease-in-out;
    border: 2px solid;
}

.time-slot:hover:not(.disabled):not(.full) {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.time-slot.selected {
    border-color: #3b82f6 !important;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    z-index: 5;
}

/* Ensure text visibility in selected states */
.calendar-day.selected *,
.time-slot.selected * {
    color: white !important;
    opacity: 1 !important;
}

/* Holiday and weekend specific styles */
.calendar-day.holiday:not(.selected) {
    background: linear-gradient(135deg, #fed7d7 0%, #feebeb 100%);
}

.calendar-day.weekend:not(.selected) {
    background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
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
                    
                    <!-- Guide content remains the same as before -->
                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="closeHelpModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
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
                            
                            <div class="mt-4 flex items-center space-x-4">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-blue-100 rounded mr-2"></div>
                                    <span class="text-sm">Selected Date</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-red-100 rounded mr-2"></div>
                                    <span class="text-sm">Holiday</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-gray-100 rounded mr-2"></div>
                                    <span class="text-sm">Unavailable</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Slots Selection -->
                        <div id="timeSlotsSection" class="hidden mb-6 bg-white p-4 rounded-lg shadow">
                            <h3 class="text-lg font-semibold mb-4 text-blue-700">Select Time Slots for <span id="selectedDateDisplay"></span></h3>
                            
                            <div class="mb-4">
                                <h4 class="font-medium mb-2">Morning Slots (8:00 AM - 12:00 PM)</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach ($morningSlots as $index => $slot): ?>
                                        <div class="time-slot" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                            <div class="flex justify-between items-center">
                                                <span><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></span>
                                                <span class="availability-indicator"></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h4 class="font-medium mb-2">Afternoon Slots (1:00 PM - 5:00 PM)</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <?php foreach ($afternoonSlots as $index => $slot): ?>
                                        <div class="time-slot" data-time="<?= $slot['start'] ?> - <?= $slot['end'] ?>">
                                            <div class="flex justify-between items-center">
                                                <span><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></span>
                                                <span class="availability-indicator"></span>
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
                            
                            <button type="button" id="addSlotBtn" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition font-medium">
                                Add Selected Time Slot
                            </button>
                        </div>
                        
                        <!-- Traditional Form (Hidden by default) -->
                        <form method="POST" action="" class="hidden" id="traditionalForm">
                            <div class="mb-4">
                                <label for="date" class="block text-gray-700 mb-2 font-medium">Date *</label>
                                <input type="date" id="date" name="date" min="<?= date('Y-m-d') ?>" 
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2 font-medium">Time Slot *</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-700 mb-2">Morning (8:00 AM - 12:00 PM)</h3>
                                        <div class="space-y-2">
                                            <?php foreach ($morningSlots as $slot): ?>
                                                <div class="flex items-center">
                                                    <input type="radio" id="morning_<?= str_replace(':', '', $slot['start']) ?>" 
                                                           name="time_slot" value="<?= $slot['start'] ?> - <?= $slot['end'] ?>" 
                                                           class="mr-2" required>
                                                    <label for="morning_<?= str_replace(':', '', $slot['start']) ?>"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-700 mb-2">Afternoon (1:00 PM - 5:00 PM)</h3>
                                        <div class="space-y-2">
                                            <?php foreach ($afternoonSlots as $slot): ?>
                                                <div class="flex items-center">
                                                    <input type="radio" id="afternoon_<?= str_replace(':', '', $slot['start']) ?>" 
                                                           name="time_slot" value="<?= $slot['start'] ?> - <?= $slot['end'] ?>" 
                                                           class="mr-2" required>
                                                    <label for="afternoon_<?= str_replace(':', '', $slot['start']) ?>"><?= date('g:i A', strtotime($slot['start'])) ?> - <?= date('g:i A', strtotime($slot['end'])) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="max_slots" class="block text-gray-700 mb-2 font-medium">Maximum Appointments *</label>
                                <select id="max_slots" name="max_slots" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                    <option value="5">5</option>
                                </select>
                            </div>
                            
                            <button type="submit" name="add_slot" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition font-medium">
                                Add Time Slot
                            </button>
                        </form>
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
                                        <?php foreach ($pendingAppointments as $appointment): 
                                            // Check if this appointment was rescheduled
                                            $isRescheduled = false;
                                            try {
                                                $checkRescheduled = $pdo->prepare("SHOW COLUMNS FROM user_appointments LIKE 'rescheduled_count'");
                                                $rescheduledColumnExists = $checkRescheduled->execute() && $checkRescheduled->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($rescheduledColumnExists) {
                                                    $rescheduledStmt = $pdo->prepare("SELECT rescheduled_count FROM user_appointments WHERE id = ?");
                                                    $rescheduledStmt->execute([$appointment['id']]);
                                                    $rescheduledData = $rescheduledStmt->fetch(PDO::FETCH_ASSOC);
                                                    $isRescheduled = !empty($rescheduledData['rescheduled_count']) && $rescheduledData['rescheduled_count'] > 0;
                                                }
                                            } catch (PDOException $e) {
                                                // Column doesn't exist or other error, treat as not rescheduled
                                                $isRescheduled = false;
                                            }
                                        ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                    <?php if ($isRescheduled): ?>
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                            Auto-Rescheduled
                                                        </span>
                                                    <?php endif; ?>
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
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Pending
                                                    </span>
                                                    <?php if ($isRescheduled): ?>
                                                        <div class="text-xs text-gray-500 mt-1">Previously expired</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No health concerns specified' ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openInvoiceModal(<?= $appointment['id'] ?>)" 
                                                            class="bg-green-500 text-white action-button mr-2">
                                                        <i class="fas fa-check-circle mr-1"></i> Approve
                                                    </button>
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
                    
                    <!-- All Appointments -->
                    <div class="hidden p-4 bg-white rounded-lg border border-gray-200" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold text-blue-700">All Appointments</h2>
                            <span class="text-sm text-gray-600"><?= count($allAppointments) ?> total</span>
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
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($allAppointments as $appointment): 
                                            $isPast = strtotime($appointment['date']) < strtotime(date('Y-m-d'));
                                        ?>
                                            <tr class="<?= $isPast ? 'bg-gray-50' : '' ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-blue-600"><?= !empty($appointment['unique_number']) ? htmlspecialchars($appointment['unique_number']) : 'N/A' ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($appointment['contact']) ?></div>
                                                    <?php if ($appointment['status'] === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                                                        <div class="mt-1 text-xs text-red-600">
                                                            <strong>Reason:</strong> <?= htmlspecialchars($appointment['rejection_reason']) ?>
                                                        </div>
                                                    <?php endif; ?>
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
                                                           ($appointment['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>">
                                                        <?= ucfirst($appointment['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php if (!empty($appointment['invoice_number'])): ?>
                                                        <div class="text-sm font-medium text-blue-600"><?= $appointment['invoice_number'] ?></div>
                                                        <div class="text-xs text-gray-500">Priority: <?= $appointment['priority_number'] ?></div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">No invoice</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <?php if ($appointment['status'] === 'approved' && !$isPast): ?>
                                                        <form method="POST" action="" class="inline">
                                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                                            <input type="hidden" name="action" value="complete">
                                                            <button type="submit" name="approve_appointment" 
                                                                    class="bg-blue-500 text-white action-button">
                                                                <i class="fas fa-check-circle mr-1"></i> Mark as Completed
                                                            </button>
                                                        </form>
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
                                                    <button type="submit" name="approve_user" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 mr-2">Approve</button>
                                                </form>
                                                
                                                <!-- Decline with reason modal trigger -->
                                                <button onclick="openDeclineModal(<?= $user['id'] ?>)" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Decline</button>
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

    <!-- Invoice Generation Modal -->
    <div id="invoiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Approve Appointment</h3>
                <p class="text-gray-600 mb-4">Would you like to generate an invoice and appointment ticket for this appointment?</p>
                
                <form id="invoiceForm" method="POST" action="">
                    <input type="hidden" name="appointment_id" id="invoice_appointment_id">
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium">
                            Cancel
                        </button>
                        <button type="submit" name="approve_without_invoice" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            Approve Only
                        </button>
                        <button type="submit" name="generate_invoice" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            Generate Invoice & Ticket
                        </button>
                    </div>
                </form>
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
                    <button type="button" onclick="closeSuccessModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm">
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
                    <button type="button" onclick="closeErrorModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm">
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
                        <button type="button" onclick="closeRejectionModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium">
                            Cancel
                        </button>
                        <button type="submit" name="approve_appointment" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                            Confirm Rejection
                        </button>
                    </div>
                </form>
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
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium">
                            Cancel
                        </button>
                        <button type="submit" name="update_slot" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
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
                            <button type="submit" name="approve_user" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                                Confirm Decline
                            </button>
                            <button type="button" onclick="closeDeclineModal()" class="ml-3 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
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

    function openInvoiceModal(appointmentId) {
        document.getElementById('invoice_appointment_id').value = appointmentId;
        document.getElementById('invoiceModal').classList.remove('hidden');
    }

    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.add('hidden');
    }
    
    function openDeclineModal(userId) {
        document.getElementById('declineUserId').value = userId;
        document.getElementById('decline_reason').value = '';
        document.getElementById('declineModal').classList.remove('hidden');
    }
    
    function closeDeclineModal() {
        document.getElementById('declineModal').classList.add('hidden');
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

    // Calendar functionality
    let currentMonth = <?= date('m') ?>;
    let currentYear = <?= date('Y') ?>;
    let selectedDate = null;
    let selectedTimeSlot = null;
    const phHolidays = <?= json_encode($phHolidays) ?>;
    const dateSlots = <?= json_encode($dateSlots) ?>;

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
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dateObj = new Date(year, month - 1, day);
        
        const dayCell = document.createElement('div');
        dayCell.classList.add('calendar-day', 'text-center', 'p-2', 'rounded', 'border', 'border-gray-200');
        
        // Check if it's a holiday
        const isHoliday = phHolidays.hasOwnProperty(dateStr);
        
        // Check if it's a weekend
        const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;
        
        // Check if it's in the past (including today if time has passed)
        const now = new Date();
        const isPast = dateObj < today || (dateObj.getTime() === today.getTime() && now.getHours() >= 17); // Past if date is before today or today after 5 PM
        
        // Check if it has available slots
        const hasSlots = dateSlots.hasOwnProperty(dateStr);
        
        // Add appropriate classes based on date status
        if (isPast) {
            dayCell.classList.add('disabled', 'bg-gray-100', 'text-gray-400', 'cursor-not-allowed');
            dayCell.title = 'This date has passed and cannot be selected';
        } else if (isHoliday) {
            dayCell.classList.add('holiday', 'bg-red-50', 'text-red-700', 'border-red-200');
            dayCell.title = 'Holiday: ' + phHolidays[dateStr];
        } else if (isWeekend) {
            dayCell.classList.add('weekend', 'bg-blue-50', 'text-blue-700', 'border-blue-200');
        } else {
            dayCell.classList.add('bg-white', 'text-gray-700', 'hover:bg-blue-50', 'hover:border-blue-300', 'cursor-pointer');
        }
        
        // Add selected class if this date is currently selected
        if (selectedDate === dateStr) {
            dayCell.classList.remove('bg-white', 'bg-blue-50', 'bg-red-50', 'bg-gray-100');
            dayCell.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        }
        
        // Add day number - ensure it's always visible
        const dayNumber = document.createElement('div');
        dayNumber.classList.add('font-semibold', 'text-lg', 'mb-1');
        dayNumber.textContent = day;
        dayCell.appendChild(dayNumber);
        
        // Add holiday indicator
        if (isHoliday && !isPast) {
            const holidayIndicator = document.createElement('div');
            holidayIndicator.classList.add('text-xs', 'font-medium');
            holidayIndicator.textContent = 'HOLIDAY';
            dayCell.appendChild(holidayIndicator);
        }
        
        // Add slot availability indicator (only for future dates)
        if (!isPast && hasSlots) {
            const slotIndicator = document.createElement('div');
            slotIndicator.classList.add('text-xs', 'mt-1');
            
            const availableSlots = dateSlots[dateStr].filter(slot => {
                const booked = parseInt(slot.booked_count) || 0;
                const max = parseInt(slot.max_slots) || 0;
                return max - booked > 0;
            });
            
            if (availableSlots.length > 0) {
                slotIndicator.classList.add('text-green-600', 'font-medium');
                slotIndicator.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Available';
            } else {
                slotIndicator.classList.add('text-red-600', 'font-medium');
                slotIndicator.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Full';
            }
            
            dayCell.appendChild(slotIndicator);
        } else if (!isPast) {
            // No slots configured for this future date
            const slotIndicator = document.createElement('div');
            slotIndicator.classList.add('text-xs', 'mt-1', 'text-gray-500');
            slotIndicator.innerHTML = '<i class="fas fa-plus-circle mr-1"></i>Add slots';
            dayCell.appendChild(slotIndicator);
        }
        
        // Add click event if not disabled
        if (!isPast) {
            dayCell.addEventListener('click', () => selectDate(dateStr, day, month, year));
        } else {
            dayCell.style.cursor = 'not-allowed';
        }
        
        calendarEl.appendChild(dayCell);
    }
}
    
    function selectDate(dateStr, day, month, year) {
    // First, check if the date is in the past
    const selectedDateObj = new Date(year, month - 1, day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // More accurate past date checking (including time consideration)
    const now = new Date();
    const isPast = selectedDateObj < today || 
                  (selectedDateObj.getTime() === today.getTime() && now.getHours() >= 17);
    
    if (isPast) {
        showErrorModal('Cannot select past dates. Please choose a future date.');
        return;
    }
    
    selectedDate = dateStr;
    
    // Update selected date display
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('selectedDateDisplay').textContent = `${monthNames[month - 1]} ${day}, ${year}`;
    document.getElementById('selected_date').value = dateStr;
    
    // Update calendar UI - remove selection from all days
    document.querySelectorAll('.calendar-day').forEach(dayEl => {
        dayEl.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        
        // Restore appropriate background colors
        if (dayEl.classList.contains('disabled')) {
            dayEl.classList.add('bg-gray-100', 'text-gray-400');
        } else if (dayEl.classList.contains('holiday')) {
            dayEl.classList.add('bg-red-50', 'text-red-700');
        } else if (dayEl.classList.contains('weekend')) {
            dayEl.classList.add('bg-blue-50', 'text-blue-700');
        } else {
            dayEl.classList.add('bg-white', 'text-gray-700');
        }
    });
    
    // Add selection to clicked day with consistent styling
    event.target.classList.remove('bg-white', 'bg-red-50', 'bg-blue-50', 'bg-gray-100');
    event.target.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
    
    // Ensure text content remains visible
    const dayNumber = event.target.querySelector('div:first-child');
    const indicators = event.target.querySelectorAll('div:not(:first-child)');
    
    if (dayNumber) {
        dayNumber.classList.remove('text-gray-700', 'text-red-700', 'text-blue-700');
        dayNumber.classList.add('text-white');
    }
    
    indicators.forEach(indicator => {
        indicator.classList.remove('text-gray-500', 'text-red-600', 'text-green-600', 'text-blue-600');
        indicator.classList.add('text-blue-100');
    });
    
    // Show time slots section
    document.getElementById('timeSlotsSection').classList.remove('hidden');
    
    // Update time slots availability
    updateTimeSlotsAvailability(dateStr);
    
    // Reset time slot selection
    selectedTimeSlot = null;
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.classList.remove('selected');
    });
}

function updateTimeSlotsAvailability(dateStr) {
    const timeSlots = document.querySelectorAll('.time-slot');
    const now = new Date();
    const selectedDateObj = new Date(dateStr);
    
    timeSlots.forEach(slotEl => {
        const timeRange = slotEl.getAttribute('data-time');
        const [startTime] = timeRange.split(' - ');
        const slotDateTime = new Date(dateStr + 'T' + startTime);
        
        // Reset classes and events
        slotEl.classList.remove('available', 'limited', 'full', 'selected', 
                               'bg-green-50', 'bg-yellow-50', 'bg-red-50', 'bg-blue-500',
                               'text-green-700', 'text-yellow-700', 'text-red-700', 'text-white',
                               'border-green-200', 'border-yellow-200', 'border-red-200', 'border-blue-600');
        
        slotEl.onclick = null;
        
        // Check if this time slot is in the past
        const isPast = slotDateTime < now;
        
        if (isPast) {
            slotEl.classList.add('disabled', 'bg-gray-100', 'text-gray-400', 'border-gray-200', 'cursor-not-allowed');
            slotEl.querySelector('.availability-indicator').innerHTML = '<span class="text-gray-500">Past</span>';
            slotEl.title = 'This time slot has passed and cannot be selected';
            return;
        }
        
        if (dateSlots[dateStr]) {
            const existingSlot = dateSlots[dateStr].find(slot => 
                `${slot.start_time}-${slot.end_time}` === timeRange.replace(' - ', '-')
            );
            
            if (existingSlot) {
                const booked = parseInt(existingSlot.booked_count) || 0;
                const max = parseInt(existingSlot.max_slots) || 0;
                const available = max - booked;
                
                if (available > 0) {
                    if (available >= 3) {
                        slotEl.classList.add('available', 'bg-green-50', 'text-green-700', 'border-green-200', 'cursor-pointer');
                        slotEl.querySelector('.availability-indicator').innerHTML = `<span class="text-green-600 font-medium">${available} available</span>`;
                    } else if (available >= 1) {
                        slotEl.classList.add('limited', 'bg-yellow-50', 'text-yellow-700', 'border-yellow-200', 'cursor-pointer');
                        slotEl.querySelector('.availability-indicator').innerHTML = `<span class="text-yellow-600 font-medium">${available} left</span>`;
                    }
                    
                    // Add click event
                    slotEl.onclick = () => selectTimeSlot(slotEl, timeRange);
                    slotEl.title = `Click to select this time slot (${available} available)`;
                } else {
                    slotEl.classList.add('full', 'bg-red-50', 'text-red-700', 'border-red-200', 'cursor-not-allowed');
                    slotEl.querySelector('.availability-indicator').innerHTML = '<span class="text-red-600 font-medium">Full</span>';
                    slotEl.title = 'This time slot is fully booked';
                }
            } else {
                // Slot doesn't exist yet, so it's available
                slotEl.classList.add('available', 'bg-green-50', 'text-green-700', 'border-green-200', 'cursor-pointer');
                slotEl.querySelector('.availability-indicator').innerHTML = '<span class="text-green-600 font-medium">Available</span>';
                slotEl.title = 'Click to add this time slot';
                
                // Add click event
                slotEl.onclick = () => selectTimeSlot(slotEl, timeRange);
            }
        } else {
            // No slots for this date yet, all are available
            slotEl.classList.add('available', 'bg-green-50', 'text-green-700', 'border-green-200', 'cursor-pointer');
            slotEl.querySelector('.availability-indicator').innerHTML = '<span class="text-green-600 font-medium">Available</span>';
            slotEl.title = 'Click to add this time slot';
            
            // Add click event
            slotEl.onclick = () => selectTimeSlot(slotEl, timeRange);
        }
    });
}
    
    function selectTimeSlot(slotEl, timeRange) {
    // First, check if the selected date-time combination is in the past
    if (selectedDate) {
        const selectedDateTime = new Date(selectedDate + 'T' + timeRange.split(' - ')[0]);
        const now = new Date();
        
        if (selectedDateTime < now) {
            showErrorModal('Cannot select past time slots. Please choose a future time.');
            return;
        }
    }
    
    // Remove previous selection
    document.querySelectorAll('.time-slot').forEach(el => {
        el.classList.remove('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
        
        // Restore appropriate styling
        if (el.classList.contains('available')) {
            el.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
        } else if (el.classList.contains('limited')) {
            el.classList.add('bg-yellow-50', 'text-yellow-700', 'border-yellow-200');
        } else if (el.classList.contains('full')) {
            el.classList.add('bg-red-50', 'text-red-700', 'border-red-200', 'cursor-not-allowed');
        }
    });
    
    // Add selection to clicked slot with consistent styling
    slotEl.classList.remove('bg-green-50', 'bg-yellow-50', 'bg-red-50');
    slotEl.classList.add('selected', 'bg-blue-500', 'text-white', 'border-blue-600');
    
    // Update the availability indicator text color
    const indicator = slotEl.querySelector('.availability-indicator');
    if (indicator) {
        indicator.classList.remove('text-green-600', 'text-yellow-600', 'text-red-600');
        indicator.classList.add('text-blue-100');
    }
    
    selectedTimeSlot = timeRange;
    document.getElementById('selected_time_slot').value = timeRange;
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
    
    // Final validation - check if the selected date-time is in the past
    const [startTime] = selectedTimeSlot.split(' - ');
    const selectedDateTime = new Date(selectedDate + 'T' + startTime);
    const now = new Date();
    
    if (selectedDateTime < now) {
        showErrorModal('Cannot add time slots in the past. Please select a future date and time.');
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
        
        // Show success/error modals if messages exist
        <?php if ($success): ?>
            showSuccessModal('<?= addslashes($success) ?>');
            // Refresh the page after a short delay to update the UI
            setTimeout(function() {
                window.location.href = window.location.href.split('?')[0];
            }, 5000);
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
        
        const invoiceModal = document.getElementById('invoiceModal');
        if (event.target === invoiceModal) {
            closeInvoiceModal();
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
    }
    </script>
</body>

</html>