<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];
$userData = null;
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'appointments';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $error = 'User data not found.';
    }
} catch (PDOException $e) {
    $error = 'Error fetching user data: ' . $e->getMessage();
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profile_images/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['profile_image'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file type
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = 'Only JPG, JPEG, PNG, and GIF files are allowed.';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $error = 'File size must be less than 5MB.';
        } else {
            // Generate unique filename
            $newFilename = 'user_' . $userId . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFilename;
            
            // Delete old profile image if exists
            if (!empty($userData['profile_image'])) {
                $oldImagePath = __DIR__ . '/..' . $userData['profile_image'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Update database with relative path
                $relativePath = '/uploads/profile_images/' . $newFilename;
                $stmt = $pdo->prepare("UPDATE sitio1_users SET profile_image = ? WHERE id = ?");
                $stmt->execute([$relativePath, $userId]);
                
                // Update userData array
                $userData['profile_image'] = $relativePath;
                
                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => 'Profile image updated successfully!'
                ];
                
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                $error = 'Failed to upload image. Please try again.';
            }
        }
    } else {
        $error = 'Please select a valid image file.';
    }
}

// Handle profile image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_image'])) {
    if (!empty($userData['profile_image'])) {
        $uploadDir = __DIR__ . '/../uploads/profile_images/';
        $imagePath = __DIR__ . '/..' . $userData['profile_image'];
        
        // Delete file
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        // Update database
        $stmt = $pdo->prepare("UPDATE sitio1_users SET profile_image = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Update userData array
        $userData['profile_image'] = null;
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Profile image removed successfully!'
        ];
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Get stats for dashboard
$stats = [
    'pending_consultations' => 0,
    'upcoming_appointments' => 0,
    'unread_announcements' => 0
];

// Get recent activities (appointments and consultations)
$recentActivities = [];

if ($userData) {
    try {
        // Pending consultations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_consultations WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $stats['pending_consultations'] = $stmt->fetchColumn();

        // Upcoming appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_appointments ua 
                              JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                              WHERE ua.user_id = ? AND ua.status IN ('pending', 'approved') 
                              AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.start_time > TIME(NOW())))");
        $stmt->execute([$userId]);
        $stats['upcoming_appointments'] = $stmt->fetchColumn();

        // Unread announcements
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_announcements a 
                              LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ? 
                              WHERE ua.id IS NULL");
        $stmt->execute([$userId]);
        $stats['unread_announcements'] = $stmt->fetchColumn();
        
        // Get recent activities (appointments and consultations from the last 30 days)
        $stmt = $pdo->prepare("
            (SELECT 
                'appointment' as type,
                ua.status,
                a.date as activity_date,
                CONCAT('Appointment with ', s.full_name) as title,
                CONCAT('Status: ', ua.status) as description,
                a.date,
                NULL as created_at
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            JOIN sitio1_staff s ON a.staff_id = s.id
            WHERE ua.user_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
            
            UNION ALL
            
            (SELECT 
                'consultation' as type,
                status,
                created_at as activity_date,
                'Health Consultation' as title,
                CONCAT('Status: ', status) as description,
                NULL as date,
                created_at
            FROM sitio1_consultations 
            WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
            
            ORDER BY activity_date DESC
            LIMIT 10
        ");
        $stmt->execute([$userId, $userId]);
        $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $error = 'Error fetching statistics: ' . $e->getMessage();
    }
}

// APPOINTMENTS CODE
$appointmentTab = $_GET['appointment_tab'] ?? 'upcoming';

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    if (!isset($_POST['appointment_id'], $_POST['selected_date'], $_POST['consent'])) {
        $error = 'Missing required booking information.';
    } else {
        $appointmentId = intval($_POST['appointment_id']);
        $selectedDate  = $_POST['selected_date'];
        $notes         = !empty($_POST['notes']) ? trim($_POST['notes']) : null;
        $serviceId     = !empty($_POST['service_id']) ? intval($_POST['service_id']) : null;
        $serviceType   = !empty($_POST['service_type']) ? $_POST['service_type'] : 'General Checkup';

        // Collect health concerns
        $healthConcerns = [];
        if (!empty($_POST['health_concerns']) && is_array($_POST['health_concerns'])) {
            $healthConcerns = $_POST['health_concerns'];
        }

        // Validation: must have at least 1 concern
        if (count($healthConcerns) === 0) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Please select at least one health concern.'
            ];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Prevent double booking on the same date
        $checkStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM user_appointments ua
             JOIN sitio1_appointments a ON ua.appointment_id = a.id
             WHERE ua.user_id = ? AND a.date = ? AND ua.status IN ('pending', 'approved')"
        );
        $checkStmt->execute([$userId, $selectedDate]);
        $sameDayAppointment = $checkStmt->fetchColumn();

        if ($sameDayAppointment > 0) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'You already have an appointment scheduled for ' . date('M d, Y', strtotime($selectedDate)) . '. Please choose a different date.'
            ];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Prepare insert
        $healthConcernsStr = implode(', ', $healthConcerns);
        $consentGiven = isset($_POST['consent']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "INSERT INTO user_appointments 
                (user_id, appointment_id, service_id, status, notes, health_concerns, service_type, consent) 
             VALUES 
                (:user_id, :appointment_id, :service_id, 'pending', :notes, :health_concerns, :service_type, :consent)"
        );
        $stmt->execute([
            ':user_id'         => $userId,
            ':appointment_id'  => $appointmentId,
            ':service_id'      => $serviceId,
            ':notes'           => $notes,
            ':health_concerns' => $healthConcernsStr,
            ':service_type'    => $serviceType,
            ':consent'         => $consentGiven
        ]);

        $_SESSION['booking_success'] = true;
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Appointment booked successfully! Your health visit has been scheduled.'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Handle appointment cancellation - Only allow cancellation for pending appointments that haven't passed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    // Ensure appointment id is an integer
    $userAppointmentId = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    $cancelReason = trim($_POST['cancel_reason'] ?? '');
    
    // Validate cancellation reason
    if (empty($cancelReason)) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Please provide a reason for cancellation'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    if (strlen($cancelReason) < 10) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Please provide a detailed reason for cancellation (at least 10 characters)'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    try {
        // Check if appointment belongs to user, is PENDING, and hasn't passed
        $stmt = $pdo->prepare("
            SELECT ua.id, ua.status, a.date, a.start_time, a.id as slot_id
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE ua.id = ? AND ua.user_id = ? 
            AND ua.status = 'pending'
            AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.start_time > TIME(NOW())))
        ");
        $stmt->execute([$userAppointmentId, $userId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            // Check why it can't be cancelled
            $checkStmt = $pdo->prepare("
                SELECT ua.status, a.date, a.start_time,
                       (a.date < CURDATE() OR (a.date = CURDATE() AND a.start_time < TIME(NOW()))) as is_past
                FROM user_appointments ua
                JOIN sitio1_appointments a ON ua.appointment_id = a.id
                WHERE ua.id = ? AND ua.user_id = ?
            ");
            $checkStmt->execute([$userAppointmentId, $userId]);
            $appointmentInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointmentInfo) {
                throw new Exception('Appointment not found or you do not have permission to cancel it.');
            } elseif ($appointmentInfo['status'] !== 'pending') {
                throw new Exception('Only pending appointments can be cancelled. For approved appointments, please contact support.');
            } elseif ($appointmentInfo['is_past']) {
                throw new Exception('Past appointments cannot be cancelled.');
            } else {
                throw new Exception('Appointment cannot be cancelled at this time.');
            }
        }
        
        // UPDATE the appointment status to cancelled
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), cancelled_by_user = 1
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$cancelReason, $userAppointmentId, $userId]);
        
        // In the cancellation handler section, update the success part:
if ($stmt->rowCount() > 0) {
    $_SESSION['cancellation_success'] = true;
    $_SESSION['notification'] = [
        'type' => 'success',
        'message' => 'Appointment cancelled successfully. The slot is now available for others.'
    ];
    
    // Redirect to show feedback modal
    header('Location: ?tab=appointments&appointment_tab=upcoming&cancellation_success=1');
    exit();
    
} else {
    throw new Exception('Failed to cancel appointment.');
}
        
        // Redirect to prevent form resubmission and refresh the slots display
        header('Location: ?tab=appointments&appointment_tab=upcoming&refresh=' . time());
        exit();
        
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Error cancelling appointment: ' . $e->getMessage()
        ];

        // Log failed cancellation attempt with reason
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $logEntry = date('c') . " | user_id={$userId} | posted_id=" . ($originalPostedId ?? $userAppointmentId) . " | resolved_user_appointment_id=" . ($userAppointmentId ?? 'n/a') . " | action=cancel_attempt | result=error | message=" . str_replace("\n", " ", $e->getMessage()) . PHP_EOL;
        @file_put_contents($logDir . '/cancellations.log', $logEntry, FILE_APPEND | LOCK_EX);

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get available dates with slots and staff information
$availableDates = [];

try {
    // First, get all dates where user has appointments (excluding cancelled)
$userAppointmentDates = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT a.date 
    FROM user_appointments ua
    JOIN sitio1_appointments a ON ua.appointment_id = a.id
    WHERE ua.user_id = ? 
    AND ua.status IN ('pending', 'approved')
    AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.start_time > TIME(NOW())))
");
$stmt->execute([$userId]);
$userAppointmentDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Then get available slots with appointment booking rules
    $currentTime = date('H:i:s');
    $currentDate = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            a.date, 
            a.id as slot_id,
            a.start_time, 
            a.end_time,
            a.max_slots,
            s.full_name as staff_name,
            s.specialization,
            COUNT(ua.id) as booked_slots,
            (a.max_slots - COUNT(ua.id)) as available_slots,
            -- Check if current user has already booked this slot (excluding cancelled appointments)
            EXISTS (
                SELECT 1 FROM user_appointments ua2 
                WHERE ua2.appointment_id = a.id 
                AND ua2.user_id = ? 
                AND ua2.status IN ('pending', 'approved', 'completed')
            ) as user_has_booked,
            -- Check if the appointment time is in the past
            (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past,
            -- Check if slot is fully booked (considering all statuses except cancelled)
            (COUNT(ua.id) >= a.max_slots) as is_fully_booked,
            -- Check if booking is allowed based on appointment rules
            CASE 
                -- Fixed Slot System: Allow booking until slot end time
                WHEN a.date = CURDATE() AND ? BETWEEN a.start_time AND a.end_time THEN 1
                -- Strict Start-Time System: Block booking after start time
                WHEN a.date = CURDATE() AND ? > a.start_time THEN 0
                -- Grace Period System: Allow booking within 15 minutes after start time
                WHEN a.date = CURDATE() AND TIMEDIFF(?, a.start_time) <= '00:15:00' THEN 1
                -- Future dates are always allowed
                WHEN a.date > CURDATE() THEN 1
                ELSE 0
            END as booking_allowed
        FROM sitio1_appointments a
        JOIN sitio1_staff s ON a.staff_id = s.id
        LEFT JOIN user_appointments ua ON ua.appointment_id = a.id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.date >= CURDATE()
        GROUP BY a.id
        HAVING available_slots > 0 AND is_past = 0 AND is_fully_booked = 0 AND booking_allowed = 1
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$userId, $currentTime, $currentTime, $currentTime]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($availableSlots as $slot) {
        $date = $slot['date'];
        if (!isset($availableDates[$date])) {
            $availableDates[$date] = [
                'date' => $date,
                'slots' => [],
                'user_has_appointment' => in_array($date, $userAppointmentDates)
            ];
        }
        $availableDates[$date]['slots'][] = $slot;
    }
    $availableDates = array_values($availableDates);
} catch (PDOException $e) {
    $error = 'Error fetching available dates: ' . $e->getMessage();
}

// Get counts for each appointment tab
$appointmentCounts = [
    'upcoming' => 0,
    'past' => 0,
    'cancelled' => 0,
    'rejected' => 0
];

try {
    // Upcoming: Count pending and approved appointments that are in the future
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.user_id = ? 
        AND ua.status IN ('pending', 'approved')
        AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.start_time > TIME(NOW())))
    ");
    $stmt->execute([$userId]);
    $appointmentCounts['upcoming'] = $stmt->fetch()['count'];

    // Past: Count completed appointments only
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        WHERE ua.user_id = ? 
        AND ua.status = 'completed'
    ");
    $stmt->execute([$userId]);
    $appointmentCounts['past'] = $stmt->fetch()['count'];

    // Cancelled: Count cancelled appointments only
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        WHERE ua.user_id = ? 
        AND ua.status = 'cancelled'
    ");
    $stmt->execute([$userId]);
    $appointmentCounts['cancelled'] = $stmt->fetch()['count'];

    // Rejected: Count rejected appointments only
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        WHERE ua.user_id = ? 
        AND ua.status = 'rejected'
    ");
    $stmt->execute([$userId]);
    $appointmentCounts['rejected'] = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error = 'Error fetching appointment counts: ' . $e->getMessage();
}

// Get user's appointments
$appointments = [];

try {
    $query = "
        SELECT 
            ua.*, 
            a.date, 
            a.start_time, 
            a.end_time, 
            s.full_name as staff_name, 
            s.specialization,
            ua.invoice_number, 
            ua.priority_number, 
            ua.health_concerns, 
            ua.notes,
            ua.cancel_reason, 
            ua.cancelled_at,
            ua.processed_at,
            ua.rejection_reason
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        JOIN sitio1_staff s ON a.staff_id = s.id
        WHERE ua.user_id = ?
    ";
    
    if ($appointmentTab === 'upcoming') {
        $query .= " AND ua.status IN ('pending', 'approved') 
                   AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.start_time > TIME(NOW())))";
    } elseif ($appointmentTab === 'past') {
        $query .= " AND ua.status = 'completed'";
    } elseif ($appointmentTab === 'cancelled') {
        $query .= " AND ua.status = 'cancelled'";
    } elseif ($appointmentTab === 'rejected') {
        $query .= " AND ua.status = 'rejected'";
    }
    
    $query .= " ORDER BY a.date " . ($appointmentTab === 'past' || $appointmentTab === 'cancelled' || $appointmentTab === 'rejected' ? 'DESC' : 'ASC') . ", a.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Community Health Tracker</title>
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

        /* Tab styling */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Activity styling */
        .activity-item {
            border-left: 3px solid #3b82f6;
            padding-left: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #3b82f6;
        }
        .activity-item.completed {
            border-left-color: #10b981;
        }
        .activity-item.completed::before {
            background: #10b981;
        }
        .activity-item.cancelled {
            border-left-color: #ef4444;
        }
        .activity-item.cancelled::before {
            background: #ef4444;
        }
        .activity-item.pending {
            border-left-color: #f59e0b;
        }
        .activity-item.pending::before {
            background: #f59e0b;
        }

        /* Disabled date styling */
        .date-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
            background-color: #f3f4f6 !important;
        }
        .date-disabled:hover {
            background-color: #f3f4f6 !important;
        }

        /* Disabled slot styling */
        .slot-disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
            background-color: #f3f4f6 !important;
        }
        .slot-disabled:hover {
            background-color: #f3f4f6 !important;
        }

        /* FULLY ROUNDED BUTTON STYLES */
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

        .download-btn {
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .download-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12) !important;
        }

        .download-btn:active {
            transform: translateY(0) !important;
        }

        /* Loading animation */
        .btn-loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Enhanced status indicators - SIMPLIFIED */
        .status-badge {
            padding: 4px 12px !important;
            border-radius: 20px !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        .status-pending {
            background-color: #fef3c7 !important;
            color: #d97706 !important;
        }

        .status-approved {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
        }

        .status-completed {
            background-color: #dbeafe !important;
            color: #1e40af !important;
        }

        .status-cancelled {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
        }

        .status-rejected {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
        }

        .status-rescheduled {
            background-color: #f3e8ff !important;
            color: #7c3aed !important;
        }

        /* Cancellation warning */
        .cancellation-warning {
            background: linear-gradient(135deg, #fef3f2 0%, #fff6f6 100%);
            border: 1px solid #fed7d7;
            border-left: 4px solid #f56565;
        }

        /* Modal buttons */
        .modal-button {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
        }

        /* Blue theme for consistency with staff dashboard */
        .blue-theme-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .blue-theme-text {
            color: #3b82f6;
        }

        .blue-theme-border {
            border-color: #3b82f6;
        }

        /* Calendar day styling */
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

        /* Count badge styling */
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

        /* Stats card styling */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        /* Tab active state */
        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #2563eb;
        }

        /* Activity item hover effects */
        .activity-item {
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

        /* Dashboard stats styling */
        .dashboard-stats {
            margin-bottom: 2rem;
        }

        /* Cancel button styling - Warm red background with full rounded corners */
        .cancel-button {
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            background-color: #f87171 !important;
            color: white !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 6px rgba(248, 113, 113, 0.2) !important;
        }

        .cancel-button:hover {
            background-color: #ef4444 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3) !important;
        }

        .cancel-button:active {
            transform: translateY(0) !important;
        }

        /* Contact button styling */
        .contact-button {
            border-radius: 9999px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            background-color: #3b82f6 !important;
            color: white !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2) !important;
        }

        .contact-button:hover {
            background-color: #2563eb !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3) !important;
        }

        .contact-button:active {
            transform: translateY(0) !important;
        }

        /* Form button styling */
        .form-button {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        /* Simplified Profile Image Styles */
        .profile-image-container {
            position: relative;
            display: inline-block;
        }

        .profile-image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .profile-image-preview:hover {
            border-color: #3b82f6;
        }

        .profile-image-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }

        .profile-action-btn {
            border-radius: 8px;
            padding: 12px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .profile-action-btn i {
            margin-right: 8px;
        }

        .upload-btn {
            background: #3b82f6;
            color: white;
        }

        .upload-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .remove-btn {
            background: #ef4444;
            color: white;
        }

        .remove-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .profile-info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .profile-section {
            margin-bottom: 2rem;
        }

        .profile-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #3b82f6;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .profile-section-title i {
            margin-right: 8px;
        }

        /* File input styling */
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .file-input-label:hover {
            background: #2563eb;
        }

        /* Image preview in modal */
        .image-preview-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            display: none;
        }

        /* Enhanced Status Badge Styles - Larger, Modified Radius, Better Padding */
        .status-badge-enhanced {
            padding: 8px 16px !important;
            border-radius: 12px !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            text-transform: none !important;
            letter-spacing: 0.5px !important;
            text-align: center !important;
            min-width: 160px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1.2 !important;
        }

        /* Enhanced Button Styles - Modified Radius, Better Padding */
        .download-btn-enhanced {
            border-radius: 12px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            text-align: center !important;
            min-width: 140px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .cancel-button-enhanced {
            border-radius: 12px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            background-color: #f87171 !important;
            color: white !important;
            transition: all 0.3s ease !important;
            text-align: center !important;
            min-width: 200px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 6px rgba(248, 113, 113, 0.2) !important;
        }

        .cancel-button-enhanced:hover {
            background-color: #ef4444 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3) !important;
        }

        .contact-button-enhanced {
            border-radius: 12px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            background-color: #3b82f6 !important;
            color: white !important;
            transition: all 0.3s ease !important;
            text-align: center !important;
            min-width: 220px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2) !important;
        }

        .contact-button-enhanced:hover {
            background-color: #2563eb !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3) !important;
        }

        /* Enhanced status colors */
        .status-pending {
            background-color: #fef3c7 !important;
            color: #d97706 !important;
            border: 2px solid #fbbf24 !important;
        }

        .status-approved {
            background-color: #d1fae5 !important;
            color: #065f46 !important;
            border: 2px solid #10b981 !important;
        }

        .status-completed {
            background-color: #dbeafe !important;
            color: #1e40af !important;
            border: 2px solid #3b82f6 !important;
        }

        .status-cancelled {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
            border: 2px solid #ef4444 !important;
        }

        .status-rejected {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
            border: 2px solid #ef4444 !important;
        }
        
        /* Slot status indicators */
        .slot-available {
            border-left: 4px solid #10b981;
        }
        
        .slot-closing-soon {
            border-left: 4px solid #f59e0b;
        }
        
        .slot-unavailable {
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6">
        <!-- Cancellation Modal -->
        <div id="cancel-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cancel Appointment</h3>
                    <div id="cancel-warning" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800" id="cancel-warning-message"></p>
                    </div>
                    <form method="POST" class="space-y-4" id="cancel-form">
                        <input type="hidden" name="cancel_appointment" value="1">
                        <input type="hidden" name="appointment_id" id="modal-appointment-id">
                        
                        <div>
                            <label for="cancel-reason" class="block text-sm font-medium text-gray-700 mb-1">
                                Reason for cancellation <span class="text-red-500">*</span>
                            </label>
                            <textarea id="cancel-reason" name="cancel_reason" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required placeholder="Please explain why you need to cancel this appointment"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeCancelModal()"
                                class="px-6 py-3 border border-gray-300 rounded-full text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200 modal-button">
                                Go Back
                            </button>
                            <button type="submit" name="cancel_appointment" id="confirm-cancel-btn"
                                class="px-6 py-3 bg-red-600 text-white rounded-full text-base font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200 modal-button cancel-button">
                                Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Cancellation Feedback Modal -->
        <div id="cancellation-feedback-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="cancellation-feedback-backdrop"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full opacity-0 scale-95" id="cancellation-feedback-content">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100" id="cancellation-icon">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="mt-3 text-lg font-medium text-gray-900" id="cancellation-title">Appointment Cancelled</h3>
                    <div class="mt-2 px-4 py-3">
                        <p class="text-sm text-gray-500" id="cancellation-message">Your appointment has been cancelled successfully.</p>
                    </div>
                    <div class="mt-4">
                        <button type="button" onclick="hideCancellationFeedback()" class="px-6 py-3 bg-green-600 text-white rounded-full hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 modal-button">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info Modal - Redesigned -->
        <div id="contact-info-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>
            <div class="bg-white rounded-2xl shadow-2xl transform transition-all duration-300 w-full max-w-2xl max-h-[90vh] overflow-hidden">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-white bg-opacity-20 p-2 rounded-full mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-white">Contact Support Team</h3>
                        </div>
                        <button type="button" onclick="closeContactModal()" class="text-white hover:text-blue-100 transition-colors duration-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                    <!-- Important Notice -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
                        <div class="flex items-start">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <div>
                                <h4 class="text-lg font-semibold text-yellow-800 mb-1">Approved Appointments</h4>
                                <p class="text-yellow-700 text-base">
                                    For approved appointments, please contact our support team directly to cancel. 
                                    This ensures proper handling of your appointment and helps us serve you better.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Methods -->
                    <div class="space-y-6">
                        <!-- Phone Section -->
                        <div class="bg-blue-50 rounded-xl p-5 border border-blue-200">
                            <div class="flex items-start mb-3">
                                <div class="bg-blue-100 p-2 rounded-lg mr-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">Call Us Directly</h4>
                                    <p class="text-gray-600 text-base mb-2">Speak with our support team for immediate assistance</p>
                                    <div class="flex items-center">
                                        <span class="text-2xl font-bold text-blue-600 mr-3">(02) 1234-5678</span>
                                        <button onclick="copyToClipboard('(02) 1234-5678')" class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Section -->
                        <div class="bg-green-50 rounded-xl p-5 border border-green-200">
                            <div class="flex items-start mb-3">
                                <div class="bg-green-100 p-2 rounded-lg mr-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">Send Us an Email</h4>
                                    <p class="text-gray-600 text-base mb-2">We'll respond to your inquiry within 24 hours</p>
                                    <div class="flex items-center">
                                        <span class="text-xl font-medium text-green-600 mr-3">support@communityhealthtracker.com</span>
                                        <button onclick="copyToClipboard('support@communityhealthtracker.com')" class="text-green-600 hover:text-green-700 text-sm font-medium flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Office Hours Section -->
                        <div class="bg-purple-50 rounded-xl p-5 border border-purple-200">
                            <div class="flex items-start">
                                <div class="bg-purple-100 p-2 rounded-lg mr-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-1">Office Hours</h4>
                                    <p class="text-gray-600 text-base mb-3">Our support team is available during these hours</p>
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center py-2 border-b border-purple-100">
                                            <span class="text-gray-700 font-medium">Monday - Friday</span>
                                            <span class="text-purple-600 font-semibold text-lg">8:00 AM - 5:00 PM</span>
                                        </div>
                                        <div class="flex justify-between items-center py-2 border-b border-purple-100">
                                            <span class="text-gray-700 font-medium">Saturday</span>
                                            <span class="text-purple-600 font-semibold text-lg">9:00 AM - 1:00 PM</span>
                                        </div>
                                        <div class="flex justify-between items-center py-2">
                                            <span class="text-gray-700 font-medium">Sunday</span>
                                            <span class="text-gray-500 font-medium">Closed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="bg-gray-50 rounded-xl p-5 border border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-800 mb-3">What to Prepare When Contacting Us</h4>
                            <ul class="space-y-2 text-gray-600 text-base">
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Your full name and appointment reference number</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>The date and time of your scheduled appointment</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Reason for cancellation or rescheduling</span>
                                </li>
                                <li class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Your preferred alternative date/time if rescheduling</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <p class="text-gray-600 text-sm">
                            Need urgent assistance? Call us now for immediate help.
                        </p>
                        <button type="button" onclick="closeContactModal()"
                            class="bg-blue-600 text-white px-8 py-3 rounded-full text-base font-semibold hover:bg-blue-700 transition duration-200 flex items-center action-button">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Got It, Thanks!
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Modal for Appointment Booking -->
        <div id="success-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="success-modal-backdrop"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full opacity-0 scale-95" id="success-modal-content">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h3 class="mt-3 text-lg font-medium text-gray-900">Appointment Booked Successfully!</h3>
                    <div class="mt-2 px-4 py-3">
                        <p class="text-sm text-gray-500">Your health visit has been scheduled. You will receive a confirmation shortly.</p>
                    </div>
                    <div class="mt-4">
                        <button type="button" onclick="hideModal('success')" class="px-6 py-3 bg-green-600 text-white rounded-full hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 modal-button">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
        <div id="error-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="error-modal-backdrop"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full opacity-0 scale-95" id="error-modal-content">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h3 class="mt-3 text-lg font-medium text-gray-900">Booking Error</h3>
                    <div class="mt-2 px-4 py-3">
                        <p class="text-sm text-gray-500" id="error-message"></p>
                    </div>
                    <div class="mt-4">
                        <button type="button" onclick="hideModal('error')" class="px-6 py-3 bg-red-600 text-white rounded-full hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 modal-button">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Image Upload Modal -->
        <div id="profile-image-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Update Profile Photo</h3>
                    
                    <!-- Image Preview -->
                    <div class="image-preview-container">
                        <img id="image-preview" class="image-preview" alt="Image preview">
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4" id="profile-image-form">
                        <div>
                            <div class="file-input-wrapper">
                                <input type="file" id="profile_image" name="profile_image" 
                                       accept="image/jpeg,image/png,image/gif" 
                                       class="file-input"
                                       onchange="previewImage(this)">
                                <label for="profile_image" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Image
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 mt-2 text-center">Supported formats: JPG, PNG, GIF. Max size: 5MB</p>
                        </div>
                        
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeProfileImageModal()"
                                class="px-6 py-3 border border-gray-300 rounded-lg text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200">
                                Cancel
                            </button>
                            <button type="submit" name="update_profile_image" id="upload-submit-btn"
                                class="px-6 py-3 bg-blue-600 text-white rounded-lg text-base font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200"
                                disabled>
                                <i class="fas fa-upload mr-2"></i> Upload Photo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Session Notification Modal -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div id="session-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="session-modal-backdrop"></div>
                <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full opacity-0 scale-95" id="session-modal-content">
                    <div class="p-6 text-center">
                        <?php if ($_SESSION['notification']['type'] === 'success'): ?>
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">Success!</h3>
                        <?php else: ?>
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">Error!</h3>
                        <?php endif; ?>
                        <div class="mt-2 px-4 py-3">
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['notification']['message']) ?></p>
                        </div>
                        <div class="mt-4">
                            <button type="button" onclick="hideModal('session')" class="px-6 py-3 <?= $_SESSION['notification']['type'] === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500' ?> text-white rounded-full focus:outline-none focus:ring-2 modal-button">
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>

        <!-- Auto-show success modal after booking -->
        <?php if (isset($_SESSION['booking_success']) && $_SESSION['booking_success']): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        showSuccessModal();
                    }, 500);
                });
            </script>
            <?php unset($_SESSION['booking_success']); ?>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                User Dashboard
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
                        <h3 class="text-2xl leading-6 font-medium text-gray-900">User Dashboard Guide</h3>
                        <button onclick="closeHelpModal()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg mb-6">
                        <p class="text-blue-800"><strong>Welcome to the Community Health Tracker User Dashboard!</strong> This guide will help you understand how to use all the features available to you as a patient.</p>
                    </div>
                    
                    <!-- Guide content -->
                    <div class="space-y-4">
                        <div class="border-l-4 border-blue-500 pl-4">
                            <h4 class="font-semibold text-lg text-gray-800">Appointment Management</h4>
                            <p class="text-gray-600">Book new appointments, view your upcoming appointments, and manage your scheduled visits.</p>
                        </div>
                        
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-semibold text-lg text-gray-800">Appointment Status</h4>
                            <p class="text-gray-600">Track your appointment status: Pending, Approved, Completed, Cancelled, or Rejected.</p>
                        </div>

                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-semibold text-lg text-gray-800">Cancellation Policy</h4>
                            <p class="text-gray-600">You can cancel pending appointments online. Approved appointments require contacting support.</p>
                        </div>

                        <div class="border-l-4 border-orange-500 pl-4">
                            <h4 class="font-semibold text-lg text-gray-800">Profile Management</h4>
                            <p class="text-gray-600">Update your profile photo and personal information to help staff recognize you.</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="closeHelpModal()" class="px-6 py-3 bg-blue-600 text-white rounded-full hover:bg-blue-700 transition font-medium modal-button">
                            Got it, thanks!
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Stats Section - Always visible -->
        <div class="dashboard-stats">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stats-card">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Pending Consultations</h3>
                            <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending_consultations'] ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Upcoming Appointments</h3>
                            <p class="text-3xl font-bold text-green-600"><?= $stats['upcoming_appointments'] ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Unread Announcements</h3>
                            <p class="text-3xl font-bold text-blue-600"><?= $stats['unread_announcements'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Tabs - Appointments first -->
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?tab=appointments" class="<?= $activeTab === 'appointments' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Appointments
            </a>
            <a href="?tab=dashboard" class="<?= $activeTab === 'dashboard' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Dashboard
            </a>
        </div>

        <!-- Appointments Tab Content - Now first -->
<div class="tab-content <?= $activeTab === 'appointments' ? 'active' : '' ?>">
    <!-- Enhanced Tabs with Counts and Icons -->
    <div class="flex border-b border-gray-200 mb-6">
        <a href="?tab=appointments&appointment_tab=upcoming" class="<?= $appointmentTab === 'upcoming' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Upcoming
            <span class="count-badge bg-blue-100 text-blue-800"><?= $appointmentCounts['upcoming'] ?></span>
        </a>
        <a href="?tab=appointments&appointment_tab=past" class="<?= $appointmentTab === 'past' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
            </svg>
            Completed
            <span class="count-badge bg-green-100 text-green-800"><?= $appointmentCounts['past'] ?></span>
        </a>
        <a href="?tab=appointments&appointment_tab=cancelled" class="<?= $appointmentTab === 'cancelled' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancelled
            <span class="count-badge bg-red-100 text-red-800"><?= $appointmentCounts['cancelled'] ?></span>
        </a>
        <a href="?tab=appointments&appointment_tab=rejected" class="<?= $appointmentTab === 'rejected' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            Rejected
            <span class="count-badge bg-red-100 text-red-800"><?= $appointmentCounts['rejected'] ?></span>
        </a>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Left Side - Schedule Health Check-up -->
        <div class="lg:w-1/2">
            <div id="book-appointment" class="bg-white p-6 rounded-lg shadow stats-card">
                <h2 class="text-xl font-semibold mb-4 flex items-center blue-theme-text">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Schedule Health Check-up
                </h2>
                
                <!-- Enhanced Date Selection with Calendar Grid -->
                <div class="mb-6">
                    <h3 class="font-medium text-gray-700 mb-3 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2  0 002 2z" />
                        </svg>
                        Available Dates
                    </h3>
                    <?php if (!empty($availableDates)): ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <?php foreach ($availableDates as $dateInfo): 
                                $date = $dateInfo['date'];
                                $hasAvailableSlots = false;
                                $userHasAppointment = $dateInfo['user_has_appointment'];
                                
                                foreach ($dateInfo['slots'] as $slot) {
                                    if ($slot['available_slots'] > 0 && !$slot['user_has_booked']) {
                                        $hasAvailableSlots = true;
                                    }
                                }
                            ?>
                                <a href="<?= $userHasAppointment ? '#' : '?tab=appointments&date=' . $date ?>" 
                                   class="calendar-day <?= ($_GET['date'] ?? '') === $date ? 'selected' : ($userHasAppointment ? 'date-disabled' : ($hasAvailableSlots ? '' : 'date-disabled')) ?>">
                                    <div class="font-medium"><?= date('D', strtotime($date)) ?></div>
                                    <div class="text-sm"><?= date('M j', strtotime($date)) ?></div>
                                    <?php if ($userHasAppointment): ?>
                                        <div class="text-xs text-yellow-600 mt-1">You have appointment</div>
                                    <?php elseif (!$hasAvailableSlots): ?>
                                        <div class="text-xs text-red-500 mt-1">Fully Booked</div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No available dates found. Please check back later.</p>
                    <?php endif; ?>
                </div>

                <form id="appointment-form" method="GET" action="" class="mb-6">
                    <input type="hidden" name="tab" value="appointments">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="appointment_date" class="block text-gray-700 mb-2 font-medium">Or select specific date:</label>
                            <select id="appointment_date" name="date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Select date --</option>
                                <?php foreach ($availableDates as $dateInfo): 
                                    $date = $dateInfo['date'];
                                    $hasAvailableSlots = false;
                                    $userHasAppointment = $dateInfo['user_has_appointment'];
                                    
                                    foreach ($dateInfo['slots'] as $slot) {
                                        if ($slot['available_slots'] > 0 && !$slot['user_has_booked']) {
                                            $hasAvailableSlots = true;
                                        }
                                    }
                                ?>
                                    <option value="<?= $date ?>" <?= ($_GET['date'] ?? '') === $date ? 'selected' : '' ?> <?= $userHasAppointment ? 'disabled' : '' ?>>
                                        <?= date('l, F j, Y', strtotime($date)) ?>
                                        <?= $userHasAppointment ? ' (You have an appointment)' : '' ?>
                                        <?= !$hasAvailableSlots ? ' (Fully Booked)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (isset($_GET['date'])): 
                            $selectedDate = $_GET['date'];
                            $selectedDateInfo = null;
                            $userHasAppointmentOnSelectedDate = false;
                            
                            foreach ($availableDates as $dateInfo) {
                                if ($dateInfo['date'] === $selectedDate) {
                                    $selectedDateInfo = $dateInfo;
                                    $userHasAppointmentOnSelectedDate = $dateInfo['user_has_appointment'];
                                    break;
                                }
                            }
                        ?>
                            <div>
                                <label for="appointment_slot" class="block text-gray-700 mb-2 font-medium">Available Time Slots:</label>
                                <?php if ($selectedDateInfo && !empty($selectedDateInfo['slots']) && !$userHasAppointmentOnSelectedDate): ?>
                                    <div class="space-y-2">
                                        <?php foreach ($selectedDateInfo['slots'] as $slot): 
                                            $isAvailable = $slot['available_slots'] > 0;
                                            $userHasBooked = $slot['user_has_booked'];
                                            // Hide slots that user has already booked
                                            if ($userHasBooked) continue;
                                            
                                            // Determine slot status based on appointment booking rules
                                            $currentTime = date('H:i:s');
                                            $slotStartTime = $slot['start_time'];
                                            $slotEndTime = $slot['end_time'];
                                            $slotDate = $slot['date'];
                                            
                                            $slotStatus = 'available';
                                            $statusText = 'Available';
                                            $statusClass = 'slot-available';
                                            
                                            if ($slotDate === date('Y-m-d')) {
                                                // Today's slot - apply booking rules
                                                if ($currentTime > $slotEndTime) {
                                                    $slotStatus = 'unavailable';
                                                    $statusText = 'Time Passed';
                                                    $statusClass = 'slot-unavailable';
                                                } elseif ($currentTime > $slotStartTime) {
                                                    // Check if within grace period (15 minutes)
                                                    $gracePeriodEnd = date('H:i:s', strtotime($slotStartTime . ' +15 minutes'));
                                                    if ($currentTime <= $gracePeriodEnd) {
                                                        $slotStatus = 'closing-soon';
                                                        $statusText = 'Closing Soon';
                                                        $statusClass = 'slot-closing-soon';
                                                    } else {
                                                        $slotStatus = 'unavailable';
                                                        $statusText = 'Booking Closed';
                                                        $statusClass = 'slot-unavailable';
                                                    }
                                                }
                                            }
                                        ?>
                                            <div class="border rounded-lg p-3 <?= $statusClass ?> <?= $isAvailable && $slotStatus !== 'unavailable' ? 'border-gray-200 hover:bg-blue-50' : 'slot-disabled border-gray-200 bg-gray-100 text-gray-500' ?>">
                                                <div class="flex items-center">
                                                    <input 
                                                        type="radio" 
                                                        id="slot_<?= $slot['slot_id'] ?>" 
                                                        name="slot" 
                                                        value="<?= $slot['slot_id'] ?>" 
                                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" 
                                                        <?= !$isAvailable || $slotStatus === 'unavailable' ? 'disabled' : '' ?>
                                                        required
                                                    >
                                                    <label for="slot_<?= $slot['slot_id'] ?>" class="ml-3 block">
                                                        <div class="font-medium">
                                                            <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                            <?php if ($slotStatus === 'closing-soon'): ?>
                                                                <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                    <i class="fas fa-clock mr-1"></i> Closing Soon
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm">
                                                            <span class="font-medium">Health Worker:</span> <?= htmlspecialchars($slot['staff_name']) ?>
                                                            <?php if (!empty($slot['specialization'])): ?>
                                                                (<?= htmlspecialchars($slot['specialization']) ?>)
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm <?= $isAvailable && $slotStatus !== 'unavailable' ? 'text-green-600' : 'text-red-600' ?>">
                                                            <?php if (!$isAvailable): ?>
                                                                Fully booked
                                                            <?php elseif ($slotStatus === 'unavailable'): ?>
                                                                Booking closed
                                                            <?php else: ?>
                                                                <?= "{$slot['available_slots']} slot" . ($slot['available_slots'] > 1 ? 's' : '') . " available" ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($userHasAppointmentOnSelectedDate): ?>
                                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        You already have an appointment scheduled for this date.
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-500 text-sm">No available slots for this date.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-full hover:bg-blue-700 transition flex items-center justify-center action-button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Find Availability
                            </button>
                        </div>
                    </div>
                </form>

                <?php if (isset($_GET['date']) && isset($_GET['slot']) && !$userHasAppointmentOnSelectedDate): ?>
                    <?php
                    $selectedDate = $_GET['date'];
                    $selectedSlotId = $_GET['slot'];
                    $selectedSlot = null;
                    
                    // Find the selected slot
                    foreach ($availableDates as $dateInfo) {
                        if ($dateInfo['date'] === $selectedDate) {
                            foreach ($dateInfo['slots'] as $slot) {
                                if ($slot['slot_id'] == $selectedSlotId) {
                                    $selectedSlot = $slot;
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if ($selectedSlot && !$selectedSlot['user_has_booked']): 
                        // Check if booking is still allowed based on appointment rules
                        $currentTime = date('H:i:s');
                        $slotStartTime = $selectedSlot['start_time'];
                        $slotEndTime = $selectedSlot['end_time'];
                        $slotDate = $selectedSlot['date'];
                        
                        $bookingAllowed = true;
                        $bookingMessage = '';
                        
                        if ($slotDate === date('Y-m-d')) {
                            if ($currentTime > $slotEndTime) {
                                $bookingAllowed = false;
                                $bookingMessage = 'This appointment time has already passed.';
                            } elseif ($currentTime > $slotStartTime) {
                                // Check grace period
                                $gracePeriodEnd = date('H:i:s', strtotime($slotStartTime . ' +15 minutes'));
                                if ($currentTime > $gracePeriodEnd) {
                                    $bookingAllowed = false;
                                    $bookingMessage = 'Booking for this time slot has closed.';
                                } else {
                                    $bookingMessage = 'Booking available (within grace period)';
                                }
                            }
                        }
                    ?>
                        <div class="border border-gray-200 rounded-lg p-6 mb-6 stats-card">
                            <h3 class="font-semibold text-lg mb-4 flex items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Confirm Health Visit
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <div>
                                        <p class="font-medium text-gray-700">Health Worker</p>
                                        <p><?= htmlspecialchars($selectedSlot['staff_name']) ?>
                                        <?php if (!empty($selectedSlot['specialization'])): ?>
                                            (<?= htmlspecialchars($selectedSlot['specialization']) ?>)
                                        <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <div>
                                        <p class="font-medium text-gray-700">Date & Time</p>
                                        <p><?= date('M d, Y', strtotime($selectedDate)) ?> at <?= date('h:i A', strtotime($selectedSlot['start_time'])) ?> - <?= date('h:i A', strtotime($selectedSlot['end_time'])) ?></p>
                                        <?php if ($bookingMessage): ?>
                                            <p class="text-sm <?= $bookingAllowed ? 'text-green-600' : 'text-red-600' ?>"><?= $bookingMessage ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <div>
                                        <p class="font-medium text-gray-700">Availability</p>
                                        <p><?= $selectedSlot['available_slots'] ?> slot<?= $selectedSlot['available_slots'] > 1 ? 's' : '' ?> remaining (out of <?= $selectedSlot['max_slots'] ?>)</p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($bookingAllowed): ?>
                                <form method="POST" action="" class="mt-6" onsubmit="return validateHealthConcerns()">
                                    <input type="hidden" name="appointment_id" value="<?= $selectedSlot['slot_id'] ?>">
                                    <input type="hidden" name="selected_date" value="<?= $selectedDate ?>">
                                    <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                    <input type="hidden" name="service_type" value="General Checkup">

                                    <!-- Health Concerns Section -->
                                    <div class="mb-6">
                                        <h4 class="font-medium text-gray-700 mb-3">Select Health Concerns</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <?php 
                                            $healthConcerns = [
                                                'Asthma', 'Tuberculosis', 'Malnutrition', 'Obesity',
                                                'Pneumonia', 'Dengue', 'Anemia', 'Arthritis',
                                                'Stroke', 'Cancer', 'Depression'
                                            ];
                                            
                                            foreach ($healthConcerns as $concern): ?>
                                                <div class="flex items-center">
                                                    <input type="checkbox" 
                                                           id="concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                                           name="health_concerns[]" 
                                                           value="<?= $concern ?>"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                                           class="ml-2 text-gray-700"><?= $concern ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Other Concern Option -->
                                            <div class="flex items-center">
                                                <input type="checkbox" id="other_concern" name="health_concerns[]" value="Other"
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="other_concern" class="ml-2 text-gray-700">Other</label>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3" id="other_concern_container" style="display: none;">
                                            <label for="other_concern_specify" class="block text-gray-700 mb-1 text-sm">Please specify:</label>
                                            <input type="text" id="other_concern_specify" name="other_concern_specify" 
                                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Notes -->
                                    <div class="mb-4">
                                        <label for="appointment_notes" class="block text-gray-700 mb-2 font-medium">Health Concerns Details</label>
                                        <textarea id="appointment_notes" name="notes" rows="3" 
                                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                  placeholder="Describe your symptoms, concerns, or any other relevant information"></textarea>
                                    </div>
                                    
                                    <!-- Consent Checkbox -->
                                    <div class="flex items-center mb-4">
                                        <input type="checkbox" id="consent" name="consent" required
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="consent" class="ml-2 text-gray-700">
                                            I consent to sharing my health information for this appointment
                                        </label>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <button type="submit" name="book_appointment" 
                                            class="w-full bg-green-600 text-white py-3 px-4 rounded-full hover:bg-green-700 transition flex items-center justify-center action-button">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Book Appointment
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="mt-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                                    <div class="flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                        <span><?= $bookingMessage ?> Please select a different time slot.</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <?php if ($selectedSlot && $selectedSlot['user_has_booked']): ?>
                                You have already booked this time slot. Please choose a different time.
                            <?php else: ?>
                                Selected slot is no longer available. Please choose another.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side - Appointments List -->
        <div class="lg:w-1/2">
            <div class="bg-white p-6 rounded-lg shadow stats-card">
                <h2 class="text-xl font-semibold mb-4 flex items-center blue-theme-text">
                    <?php if ($appointmentTab === 'upcoming'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Upcoming Appointments
                    <?php elseif ($appointmentTab === 'past'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
                        </svg>
                        Completed Appointments
                    <?php elseif ($appointmentTab === 'cancelled'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancelled Appointments
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        Rejected Appointments
                    <?php endif; ?>
                </h2>

                <?php if (empty($appointments)): ?>
                    <div class="text-center py-8">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                        </svg>
                        <p class="text-gray-600 mt-2">No <?= $appointmentTab ?> appointments found.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition stats-card">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-start">
                                        <!-- Status icons -->
                                        <?php if ($appointment['status'] === 'approved'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        <?php elseif ($appointment['status'] === 'pending'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                            </svg>
                                        <?php elseif ($appointment['status'] === 'completed'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        <?php endif; ?>
                                        <div>
                                            <!-- Added "Health Worker:" label -->
                                            <h3 class="font-semibold">Health Worker: <?= htmlspecialchars($appointment['staff_name']) ?>
                                            <?php if (!empty($appointment['specialization'])): ?>
                                                (<?= htmlspecialchars($appointment['specialization']) ?>)
                                            <?php endif; ?>
                                            </h3>
                                            <p class="text-sm text-gray-600">
                                                <?= date('M d, Y', strtotime($appointment['date'])) ?> 
                                                at <?= date('h:i A', strtotime($appointment['start_time'])) ?> - <?= date('h:i A', strtotime($appointment['end_time'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <!-- Updated Status Badge - Larger, Modified Radius, Better Padding -->
                                    <span class="status-badge-enhanced <?= $appointment['status'] === 'approved' ? 'status-approved' : 
                                           ($appointment['status'] === 'pending' ? 'status-pending' : 
                                           ($appointment['status'] === 'completed' ? 'status-completed' : 
                                           ($appointment['status'] === 'rejected' ? 'status-rejected' : 'status-cancelled'))) ?>">
                                        <?php 
                                        $statusText = [
                                            'pending' => 'Appointment Pending',
                                            'approved' => 'Appointment Approved', 
                                            'completed' => 'Appointment Completed',
                                            'cancelled' => 'Appointment Cancelled',
                                            'rejected' => 'Appointment Rejected'
                                        ];
                                        echo $statusText[$appointment['status']] ?? 'Appointment ' . ucfirst($appointment['status']);
                                        ?>
                                    </span>
                                </div>
                                
                                <!-- Health Concerns - Always Visible -->
                                <div class="mt-3 pl-7">
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Health Concerns:</span> 
                                        <?= !empty($appointment['health_concerns']) ? htmlspecialchars($appointment['health_concerns']) : 'No health concerns specified' ?>
                                    </p>
                                </div>
                                
                                <!-- Notes - Always Visible -->
                                <div class="mt-2 pl-7">
                                    <p class="text-sm text-gray-700">
                                        <span class="font-medium">Notes:</span> 
                                        <?= !empty($appointment['notes']) ? htmlspecialchars($appointment['notes']) : 'No additional notes' ?>
                                    </p>
                                </div>
                                
                                <!-- Rejection Reason for Rejected Appointments -->
                                <?php if ($appointmentTab === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                                    <div class="mt-3 pl-7">
                                        <p class="text-sm text-red-700">
                                            <span class="font-medium">Rejection Reason:</span> <?= htmlspecialchars($appointment['rejection_reason']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Redesigned Ticket Display for Approved Appointments -->
                                <?php if ($appointment['status'] === 'approved' && !empty($appointment['priority_number'])): ?>
                                    <div class="mt-4 pl-7">
                                        <div class="bg-gradient-to-r from-white to-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm">
                                            <!-- Priority Number Display -->
                                            <div class="mb-4">
                                                <div class="text-center">
                                                    <div class="text-sm text-gray-600 mb-2 font-medium">Your Priority Number</div>
                                                    <div class="flex items-center justify-center gap-4">
                                                        <div class="flex items-center justify-center bg-red-50 border border-red-200 rounded-xl px-6 py-4">
                                                            <div class="text-center">
                                                                <div class="text-4xl text-red-600 font-black tracking-wide"><?= htmlspecialchars($appointment['priority_number']) ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="text-sm text-gray-600 mt-2">Date: <?= date('M d, Y', strtotime($appointment['date'])) ?> • Time: <?= date('h:i A', strtotime($appointment['start_time'])) ?></div>
                                                </div>
                                            </div>

                                            <!-- Action Buttons - Vertical Layout -->
                                            <div class="flex flex-col gap-3 mt-4">
                                                <button onclick="previewAppointmentTicket(<?= $appointment['id'] ?>)" 
                                                        class="w-full bg-blue-600 text-white px-5 py-3 rounded-full text-base font-semibold hover:bg-blue-700 flex items-center justify-center transition duration-200 ease-in-out transform hover:scale-105 action-button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    Preview Ticket
                                                </button>

                                                <button onclick="downloadAppointmentTicket(<?= $appointment['id'] ?>)" 
                                                        class="w-full bg-green-600 text-white px-5 py-3 rounded-full text-base font-semibold hover:bg-green-700 flex items-center justify-center transition duration-200 ease-in-out transform hover:scale-105 action-button">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                                    </svg>
                                                    Download Ticket
                                                </button>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                                            <p class="font-medium text-blue-800 mb-1">Important Instructions:</p>
                                            <ul class="list-disc list-inside text-blue-700 space-y-1 text-sm">
                                                <li>Show this priority number at the health center reception</li>
                                                <li>Use "Preview Ticket" to view your ticket clearly on screen</li>
                                                <li>Use "Download Ticket" to save a printable copy</li>
                                                <li>Arrive 15 minutes before your scheduled appointment time</li>
                                            </ul>
                                        </div>
                                    </div>
                                <?php elseif ($appointment['status'] === 'approved' && empty($appointment['priority_number'])): ?>
                                    <div class="mt-2 pl-7">
                                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 rounded text-sm">
                                            Priority number will be available after assignment.
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Rescheduled Notice -->
                                <?php if ($appointment['status'] === 'rescheduled'): ?>
                                    <div class="mt-2 pl-7">
                                        <p class="text-sm text-purple-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                                            </svg>
                                            Your appointment has been moved to a future date. Please check back for the new schedule.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Cancellation Section - ONLY FOR PENDING APPOINTMENTS -->
                                <?php if ($appointmentTab === 'upcoming' && $appointment['status'] === 'pending'): 
                                    // Get current date and time
                                    $currentDateTime = new DateTime();
                                    $appointmentDateTime = new DateTime($appointment['date'] . ' ' . $appointment['start_time']);
                                    
                                    // Check if appointment slot is in the past
                                    $isPastAppointment = $appointmentDateTime < $currentDateTime;
                                ?>
                                    <?php if (!$isPastAppointment): ?>
                                        <div class="mt-4 pl-7">
                                            <button onclick="openCancelModal(<?= $appointment['id'] ?>, '<?= $appointment['status'] ?>', '<?= $appointment['date'] ?>', '<?= $appointment['start_time'] ?>')"
                                                    class="w-full bg-red-600 text-white px-5 py-3 rounded-full text-base font-semibold hover:bg-red-700 flex items-center justify-center transition duration-200 action-button">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Cancel Appointment
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2 pl-7">
                                            <p class="text-sm text-gray-500 flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                                </svg>
                                                This appointment time has passed and cannot be cancelled.
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Contact Support for Approved Appointments - Larger Button -->
                                <?php if ($appointmentTab === 'upcoming' && $appointment['status'] === 'approved'): ?>
                                    <div class="mt-4 pl-7">
                                        <button onclick="showContactModal()"
                                                class="w-full bg-blue-600 text-white px-5 py-3 rounded-full text-base font-semibold hover:bg-blue-700 flex items-center justify-center transition duration-200 action-button">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            Contact Support to Cancel
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- Cancellation Reason Display for Cancelled Appointments -->
                                <?php if ($appointmentTab === 'cancelled' && !empty($appointment['cancel_reason'])): ?>
                                    <div class="mt-3 pl-7">
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">Cancellation Reason:</span> <?= htmlspecialchars($appointment['cancel_reason']) ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Cancelled on: <?= date('M d, Y g:i A', strtotime($appointment['cancelled_at'])) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

        <!-- Dashboard Tab Content - Now second -->
        <div class="tab-content <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
            <!-- Profile Image Section - Redesigned -->
            <div class="profile-info-card mb-8">
                <h2 class="profile-section-title">
                    <i class="fas fa-user-circle"></i> Profile Photo
                </h2>
                
                <div class="flex flex-col md:flex-row items-center gap-8">
                    <!-- Profile Image Display -->
                    <div class="flex flex-col items-center">
                        <div class="profile-image-container">
                            <?php if (!empty($userData['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($userData['profile_image']) ?>" 
                                     alt="Profile Image" 
                                     class="profile-image-preview"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDE1MCAxNTAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iNzUiIGN5PSI3NSIgcj0iNzUiIGZpbGw9IiNlNWU3ZWIiLz48cGF0aCBkPSJNNzUgODVBNzUgNzUgMCAwMTc1IDE1IDc1IDc1IDAgMDE3NSA4NXpNNzUgNzVBNjUgNjUgMCAwMTc1IDEwIDY1IDY1IDAgMDE3NSA3NXoiIGZpbGw9IiM5Y2EzYWYiLz48L3N2Zz4='">
                            <?php else: ?>
                                <div class="profile-image-preview bg-gray-200 flex items-center justify-center">
                                    <i class="fas fa-user text-4xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-3 text-center">
                            <?= !empty($userData['profile_image']) ? 'Current Profile Photo' : 'No Profile Photo Set' ?>
                        </p>
                    </div>
                    
                    <!-- Profile Image Actions -->
                    <div class="flex-1">
                        <div class="space-y-4">
                            <div>
                                <h4 class="font-medium text-gray-700 mb-2">Profile Photo Settings</h4>
                                <p class="text-sm text-gray-600">
                                    Upload a clear photo of yourself to help our health staff recognize you during appointments. 
                                    This improves your experience at the health center.
                                </p>
                            </div>
                            
                            <div class="profile-image-actions">
                                <button type="button" onclick="openProfileImageModal()" 
                                        class="profile-action-btn upload-btn">
                                    <i class="fas fa-camera"></i> Upload New Photo
                                </button>
                                
                                <?php if (!empty($userData['profile_image'])): ?>
                                    <form method="POST" class="w-full">
                                        <button type="submit" name="remove_profile_image" 
                                                class="profile-action-btn remove-btn w-full"
                                                onclick="return confirm('Are you sure you want to remove your profile photo?')">
                                            <i class="fas fa-trash"></i> Remove Current Photo
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-sm text-blue-800 flex items-start">
                                    <i class="fas fa-info-circle mt-0.5 mr-2 flex-shrink-0"></i>
                                    <span>Your profile photo helps our staff provide personalized care. Please ensure the photo is clear and recent.</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <!-- User Information Section -->
            <div class="profile-info-card mb-8">
                <h2 class="profile-section-title">
                    <i class="fas fa-user"></i> Your Information
                </h2>
                
                <?php if ($userData): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <div>
                                <span class="font-semibold text-gray-700">Full Name:</span>
                                <p class="text-gray-600"><?= htmlspecialchars($userData['full_name']) ?></p>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Age:</span>
                                <p class="text-gray-600"><?= $userData['age'] ? htmlspecialchars($userData['age']) : 'N/A' ?></p>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Contact:</span>
                                <p class="text-gray-600"><?= $userData['contact'] ? htmlspecialchars($userData['contact']) : 'N/A' ?></p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <span class="font-semibold text-gray-700">Address:</span>
                                <p class="text-gray-600"><?= $userData['address'] ? htmlspecialchars($userData['address']) : 'N/A' ?></p>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-700">Account Status:</span>
                                <p class="text-gray-600">
                                    <?= $userData['approved'] ? 
                                        '<span class="text-green-600 font-medium">Approved</span>' : 
                                        '<span class="text-yellow-600 font-medium">Pending Approval</span>' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">Your account information could not be loaded.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activities Section -->
            <div class="profile-info-card">
                <h2 class="profile-section-title">
                    <i class="fas fa-history"></i> Recent Activities
                </h2>
                <div class="space-y-4">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): 
                            // Determine status color based on activity status
                            $statusConfig = [
                                'class' => '',
                                'bg' => '',
                                'border' => ''
                            ];
                            
                            switch($activity['status']) {
                                case 'pending':
                                    $statusConfig = [
                                        'class' => 'status-pending',
                                        'bg' => 'bg-yellow-50',
                                        'border' => 'border-yellow-200'
                                    ];
                                    break;
                                case 'approved':
                                    $statusConfig = [
                                        'class' => 'status-approved',
                                        'bg' => 'bg-green-50',
                                        'border' => 'border-green-200'
                                    ];
                                    break;
                                case 'completed':
                                    $statusConfig = [
                                        'class' => 'status-completed',
                                        'bg' => 'bg-blue-50',
                                        'border' => 'border-blue-200'
                                    ];
                                    break;
                                case 'cancelled':
                                    $statusConfig = [
                                        'class' => 'status-cancelled',
                                        'bg' => 'bg-red-50',
                                        'border' => 'border-red-200'
                                    ];
                                    break;
                                case 'rejected':
                                    $statusConfig = [
                                        'class' => 'status-rejected',
                                        'bg' => 'bg-red-50',
                                        'border' => 'border-red-200'
                                    ];
                                    break;
                                case 'rescheduled':
                                    $statusConfig = [
                                        'class' => 'status-rescheduled',
                                        'bg' => 'bg-purple-50',
                                        'border' => 'border-purple-200'
                                    ];
                                    break;
                                case 'in_progress':
                                    $statusConfig = [
                                        'class' => 'status-completed',
                                        'bg' => 'bg-blue-50',
                                        'border' => 'border-blue-200'
                                    ];
                                    break;
                                default:
                                    $statusConfig = [
                                        'class' => 'status-pending',
                                        'bg' => 'bg-gray-50',
                                        'border' => 'border-gray-200'
                                    ];
                            }
                        ?>
                            <div class="border-l-4 <?= $statusConfig['border'] ?> <?= $statusConfig['bg'] ?> pl-4 py-4 rounded-r-lg transition-all duration-200 hover:shadow-sm">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($activity['title']) ?></h4>
                                            <span class="<?= $statusConfig['class'] ?> status-badge">
                                                <?= strtoupper($activity['status']) ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-600 mb-2"><?= htmlspecialchars($activity['description']) ?></p>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="fas fa-calendar mr-1"></i>
                                            <?php if ($activity['type'] === 'appointment'): ?>
                                                <?= date('F j, Y', strtotime($activity['date'])) ?>
                                            <?php else: ?>
                                                <?= date('F j, Y \a\t g:i A', strtotime($activity['created_at'])) ?>
                                            <?php endif; ?>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-tag mr-1"></i>
                                            <?= ucfirst($activity['type']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 text-lg">No recent activities found.</p>
                            <p class="text-gray-500 mt-2">Your appointments and consultations will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // JavaScript function to handle downloads
    function downloadInvoice(appointmentId) {
        window.location.href = '<?= $_SERVER['PHP_SELF'] ?>?tab=appointments&download_invoice=' + appointmentId;
    }

    function downloadAppointmentTicket(appointmentId) {
        // Show loading indicator
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<svg class="animate-spin h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating...';
        button.disabled = true;
        
        // Download PDF version
        window.open('generate_ticket.php?appointment_id=' + appointmentId, '_blank');
        
        // Reset button after 3 seconds
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        }, 3000);
    }

    function previewAppointmentTicket(appointmentId) {
        // Open HTML version for easy screenshot
        window.open('ticket_preview.php?appointment_id=' + appointmentId, '_blank', 'width=600,height=800');
    }

    function validateHealthConcerns() {
        const checkboxes = document.querySelectorAll('input[name="health_concerns[]"]:checked');
        if (checkboxes.length === 0) {
            alert("Please select at least one health concern.");
            return false;
        }

        const otherChecked = document.getElementById("other_concern").checked;
        const otherInput = document.getElementById("other_concern_specify").value.trim();

        if (otherChecked && otherInput === "") {
            alert("Please specify your other health concern.");
            return false;
        }

        return true;
    }

    // Toggle Other field visibility
    document.getElementById("other_concern").addEventListener("change", function () {
        document.getElementById("other_concern_container").style.display = this.checked ? "block" : "none";
    });

        // Enhanced cancellation functions - ONLY FOR PENDING APPOINTMENTS
    function openCancelModal(appointmentId, status, date, startTime) {
        const currentDateTime = new Date();
        const appointmentDateTime = new Date(date + ' ' + startTime);
        
        // Check if appointment slot is in the past based on real-time
        if (appointmentDateTime < currentDateTime) {
            showErrorModal('Past appointments cannot be cancelled.');
            return;
        }
        
        // Only allow cancellation for pending appointments
        if (status !== 'pending') {
            showErrorModal('Only pending appointments can be cancelled. Approved appointments require staff assistance.');
            return;
        }
        
        document.getElementById('modal-appointment-id').value = appointmentId;
        document.getElementById('cancel-reason').value = '';
        
        // Hide warning for pending appointments
        document.getElementById('cancel-warning').classList.add('hidden');
        
        document.getElementById('cancel-modal').classList.remove('hidden');
    }

    function showContactModal() {
        document.getElementById('contact-info-modal').classList.remove('hidden');
    }

    function closeContactModal() {
        document.getElementById('contact-info-modal').classList.add('hidden');
    }

    function closeCancelModal() {
        document.getElementById('cancel-modal').classList.add('hidden');
    }

    // Profile Image Modal Functions
    function openProfileImageModal() {
        document.getElementById('profile-image-modal').classList.remove('hidden');
        // Reset form and preview
        document.getElementById('profile-image-form').reset();
        document.getElementById('image-preview').style.display = 'none';
        document.getElementById('upload-submit-btn').disabled = true;
    }

    function closeProfileImageModal() {
        document.getElementById('profile-image-modal').classList.add('hidden');
    }

    function previewImage(input) {
        const preview = document.getElementById('image-preview');
        const submitBtn = document.getElementById('upload-submit-btn');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                submitBtn.disabled = false;
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    // Success Modal Functions
    function showSuccessModal() {
        const modal = document.getElementById('success-modal');
        const backdrop = document.getElementById('success-modal-backdrop');
        const content = document.getElementById('success-modal-content');
        
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            backdrop.classList.add('opacity-100');
            content.classList.add('opacity-100', 'scale-100');
        }, 10);
    }

    function hideModal(type) {
        const modal = document.getElementById(`${type}-modal`);
        const backdrop = document.getElementById(`${type}-modal-backdrop`);
        const content = document.getElementById(`${type}-modal-content`);
        
        if (backdrop && content) {
            backdrop.classList.remove('opacity-100');
            content.classList.remove('opacity-100', 'scale-100');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    }

    function showErrorModal(message) {
        document.getElementById('error-message').textContent = message;
        showModal('error');
    }

    function showModal(type, message) {
        const modal = document.getElementById(`${type}-modal`);
        const messageElement = document.getElementById(`${type}-message`);
        
        if (messageElement && message) {
            messageElement.textContent = message;
        }
        
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            document.getElementById(`${type}-modal-backdrop`).classList.add('opacity-100');
            document.getElementById(`${type}-modal-content`).classList.add('opacity-100', 'scale-100');
        }, 10);
    }

    // Help modal functions
    function openHelpModal() {
        document.getElementById('helpModal').classList.remove('hidden');
    }

    function closeHelpModal() {
        document.getElementById('helpModal').classList.add('hidden');
    }

    // Enhanced form validation for cancellation
    document.addEventListener('DOMContentLoaded', function() {
        const cancelForm = document.getElementById('cancel-form');
        if (cancelForm) {
            cancelForm.addEventListener('submit', function(e) {
                const cancelReason = document.getElementById('cancel-reason').value.trim();
                if (cancelReason.length < 10) {
                    e.preventDefault();
                    document.getElementById('cancel-warning').classList.remove('hidden');
                    document.getElementById('cancel-warning-message').textContent = 'Please provide a detailed reason for cancellation (at least 10 characters).';
                    return false;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('confirm-cancel-btn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Cancelling...';
                submitBtn.disabled = true;
            });
        }

        // Handle session notification modal
        const sessionModal = document.getElementById('session-modal');
        if (sessionModal) {
            const backdrop = document.getElementById('session-modal-backdrop');
            const content = document.getElementById('session-modal-content');
            
            // Show modal with animation
            setTimeout(() => {
                backdrop.classList.add('opacity-100');
                content.classList.add('opacity-100', 'scale-100');
            }, 10);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                hideModal('session');
            }, 3000);
        }

        // Auto-show success modal if booking was successful
        <?php if (isset($_SESSION['booking_success']) && $_SESSION['booking_success']): ?>
            setTimeout(() => {
                showSuccessModal();
            }, 500);
        <?php endif; ?>
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const cancelModal = document.getElementById('cancel-modal');
        if (event.target === cancelModal) {
            closeCancelModal();
        }
        
        const contactModal = document.getElementById('contact-info-modal');
        if (event.target === contactModal) {
            closeContactModal();
        }
        
        const profileImageModal = document.getElementById('profile-image-modal');
        if (event.target === profileImageModal) {
            closeProfileImageModal();
        }
        
        const successModal = document.getElementById('success-modal');
        if (event.target === successModal) {
            hideModal('success');
        }
        
        const errorModal = document.getElementById('error-modal');
        if (event.target === errorModal) {
            hideModal('error');
        }
        
        const helpModal = document.getElementById('helpModal');
        if (event.target === helpModal) {
            closeHelpModal();
        }
    }

    // Cancellation feedback functions
    function showCancellationFeedback(success, message) {
        const modal = document.getElementById('cancellation-feedback-modal');
        const backdrop = document.getElementById('cancellation-feedback-backdrop');
        const content = document.getElementById('cancellation-feedback-content');
        const icon = document.getElementById('cancellation-icon');
        const title = document.getElementById('cancellation-title');
        const messageEl = document.getElementById('cancellation-message');
        
        if (success) {
            icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100';
            icon.innerHTML = '<svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>';
            title.textContent = 'Appointment Cancelled';
            title.className = 'mt-3 text-lg font-medium text-gray-900';
        } else {
            icon.className = 'mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100';
            icon.innerHTML = '<svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>';
            title.textContent = 'Cancellation Failed';
            title.className = 'mt-3 text-lg font-medium text-gray-900';
        }
        
        messageEl.textContent = message;
        
        modal.classList.remove('hidden');
        
        setTimeout(() => {
            backdrop.classList.add('opacity-100');
            content.classList.add('opacity-100', 'scale-100');
        }, 10);
        
        // Auto close after 5 seconds
        setTimeout(() => {
            hideCancellationFeedback();
        }, 5000);
    }

    function hideCancellationFeedback() {
        const modal = document.getElementById('cancellation-feedback-modal');
        const backdrop = document.getElementById('cancellation-feedback-backdrop');
        const content = document.getElementById('cancellation-feedback-content');
        
        backdrop.classList.remove('opacity-100');
        content.classList.remove('opacity-100', 'scale-100');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            // Refresh the page to update the appointments list
            window.location.reload();
        }, 300);
    }

    // Add to window.onclick function
    window.onclick = function(event) {
        // ... existing code ...
        
        const cancellationFeedbackModal = document.getElementById('cancellation-feedback-modal');
        if (event.target === cancellationFeedbackModal) {
            hideCancellationFeedback();
        }
    }

    // Add this function to handle copying contact info
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show temporary success message
            const originalEvent = event;
            const button = originalEvent.target.closest('button');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>Copied!';
            button.classList.remove('text-blue-600', 'text-green-600');
            button.classList.add('text-gray-600');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('text-gray-600');
                if (text.includes('@')) {
                    button.classList.add('text-green-600');
                } else {
                    button.classList.add('text-blue-600');
                }
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }

    // Update the existing showContactModal function if needed
    function showContactModal() {
        document.getElementById('contact-info-modal').classList.remove('hidden');
    }

    function closeContactModal() {
        document.getElementById('contact-info-modal').classList.add('hidden');
    }
    </script>
</body>
</html>