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

// Function to generate unique number - MOVED TO TOP
function generateUniqueNumber($pdo) {
    $prefix = 'CHT'; // Community Health Tracker prefix
    $unique = false;
    $uniqueNumber = '';
    
    while (!$unique) {
        // Generate random 6-digit number
        $randomNumber = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $uniqueNumber = $prefix . $randomNumber;
        
        // Check if this number already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE unique_number = ?");
        $stmt->execute([$uniqueNumber]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $unique = true;
        }
    }
    
    return $uniqueNumber;
}

// Function to send email notification - UPDATED VERSION
function sendAccountStatusEmail($email, $status, $message = '', $uniqueNumber = '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false; // Skip invalid emails
    }

    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabanagarchiel@gmail.com'; // Replace with your email
        $mail->Password   = 'qmdh ofnf bhfj wxsa'; // Replace with your email password
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
                    // Generate unique number using the function that's now defined
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
            $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, 
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
                
                // Send notification email to user
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
                                <li>Priority Number: ' . $priorityNumber . '</li>
                                <li>Invoice Number: ' . $invoiceNumber . '</li>
                            </ul>
                            <p>You can view and download your invoice from your dashboard.</p>
                            <p>Thank you for choosing our services!</p>
                        ';

                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                    }
                }
                
                $success = 'Invoice generated and appointment approved successfully!';
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

// Get available slots
$availableSlots = [];
// Get pending appointments
$pendingAppointments = [];
// Get all appointments
$allAppointments = [];
// Get unapproved users
$unapprovedUsers = [];

try {
    // Get available slots
    $stmt = $pdo->prepare("
        SELECT a.*, 
               COUNT(ua.id) as booked_count 
        FROM sitio1_appointments a 
        LEFT JOIN user_appointments ua ON a.id = ua.appointment_id AND ua.status IN ('pending', 'approved')
        WHERE a.staff_id = ? AND a.date >= CURDATE() 
        GROUP BY a.id 
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$staffId]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending appointments
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, a.date, a.start_time, a.end_time 
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          WHERE a.staff_id = ? AND ua.status = 'pending' AND a.date >= CURDATE() 
                          ORDER BY a.date, a.start_time");
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
?>

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
            min-width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0 0.5rem;
            margin-left: 0.5rem;
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
                        <form method="POST" action="" class="max-w-md">
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
                            if (!empty($availableSlots)) {
                                foreach ($availableSlots as $slot) {
                                    $bookedCount = $slot['booked_count'] ?? 0;
                                    $maxSlots = $slot['max_slots'];
                                    $available = max(0, $maxSlots - $bookedCount);
                                    $totalAvailableSlots += $available;
                                }
                            }
                            ?>
                            <span class="text-sm text-gray-600"><?= $totalAvailableSlots ?> slots available across all time periods</span>
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
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($availableSlots as $slot): 
                                            $currentDate = date('Y-m-d');
                                            $isPast = $slot['date'] < $currentDate;
                                            $isToday = $slot['date'] == $currentDate;
                                            $bookedCount = $slot['booked_count'] ?? 0;
                                            $maxSlots = $slot['max_slots'];
                                            $availableSlotsCount = max(0, $maxSlots - $bookedCount);
                                            $percentage = $maxSlots > 0 ? min(100, ($bookedCount / $maxSlots) * 100) : 0;
                                            $progressColor = $percentage >= 100 ? 'bg-red-500' : ($percentage >= 75 ? 'bg-yellow-500' : 'bg-green-500');
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
                                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                        <div class="h-2.5 rounded-full <?= $progressColor ?>" style="width: <?= $percentage ?>%"></div>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1"><?= round($percentage) ?>% filled</div>
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
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Concerns</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pendingAppointments as $appointment): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($appointment['full_name']) ?></div>
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
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No health concerns specified' ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openInvoiceModal(<?= $appointment['id'] ?>)" 
                                                            class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-green-200 mr-2">
                                                        Approve
                                                    </button>
                                                    <button onclick="openRejectionModal(<?= $appointment['id'] ?>)" 
                                                            class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-red-200">
                                                        Reject
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
                                                                    class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-blue-200">
                                                                Complete
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
                <p class="text-gray-600 mb-4">Would you like to generate an invoice for this appointment?</p>
                
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
                            Generate Invoice
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
        document.getElementById('successMessage').textContent = message;
        const modal = document.getElementById('successModal');
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.querySelector('.modal').classList.add('active');
        }, 10);
        
        // Auto close after 3 seconds
        setTimeout(() => {
            closeSuccessModal();
        }, 3000);
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
        
        // Show success/error modals if messages exist
        <?php if ($success): ?>
            showSuccessModal('<?= addslashes($success) ?>');
            // Refresh the page after a short delay to update the UI
            setTimeout(function() {
                window.location.href = window.location.href.split('?')[0];
            }, 3000);
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
    }
    </script>
</body>

</html>