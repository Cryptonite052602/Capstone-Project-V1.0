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
$activeTab = $_GET['tab'] ?? 'appointments'; // Changed default to appointments

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
                'message' => 'You must select at least one health concern.'
            ];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        } else {
            // If "Other" was checked, replace with user input
            if (in_array('Other', $healthConcerns) && !empty($_POST['other_concern_specify'])) {
                $other = trim($_POST['other_concern_specify']);
                $healthConcerns = array_diff($healthConcerns, ['Other']); 
                $healthConcerns[] = $other;
            }

            $healthConcernsStr = implode(', ', $healthConcerns);

            // Consent (required)
            $consentGiven = isset($_POST['consent']) ? 1 : 0;

            try {
                // Check if the time slot is still available
                $checkStmt = $pdo->prepare("
                    SELECT 
                        a.max_slots,
                        COUNT(ua.id) as booked_slots,
                        (a.max_slots - COUNT(ua.id)) as available_slots,
                        (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past
                    FROM sitio1_appointments a
                    LEFT JOIN user_appointments ua ON ua.appointment_id = a.id AND ua.status IN ('pending', 'approved', 'completed')
                    WHERE a.id = ? AND a.date = ?
                    GROUP BY a.id
                ");
                $checkStmt->execute([$appointmentId, $selectedDate]);
                $slotAvailability = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if slot exists, is available, and is not in the past
                if (!$slotAvailability || $slotAvailability['available_slots'] <= 0 || $slotAvailability['is_past']) {
                    $_SESSION['notification'] = [
                        'type' => 'error',
                        'message' => 'This time slot is no longer available. Please choose a different time.'
                    ];
                    header('Location: ' . $_SERVER['HTTP_REFERER']);
                    exit();
                }

                // Check if user already has an appointment on the same day
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM user_appointments ua
                    JOIN sitio1_appointments a ON ua.appointment_id = a.id
                    WHERE ua.user_id = ? 
                    AND a.date = ?
                    AND ua.status IN ('pending', 'approved')
                ");
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

                $stmt = $pdo->prepare("
                    INSERT INTO user_appointments 
                        (user_id, appointment_id, service_id, status, notes, health_concerns, service_type, consent) 
                    VALUES 
                        (:user_id, :appointment_id, :service_id, 'pending', :notes, :health_concerns, :service_type, :consent)
                ");
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
            } catch (PDOException $e) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'message' => 'Error booking appointment: ' . $e->getMessage()
                ];
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit();
            }
        }
    }
}

// Handle appointment cancellation - FIXED: Only allow cancellation for pending appointments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $userAppointmentId = $_POST['appointment_id'];
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
        // Check if appointment belongs to user and is PENDING (not approved)
        $stmt = $pdo->prepare("
            SELECT ua.id, ua.status, a.date, a.start_time
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE ua.id = ? AND ua.user_id = ? 
            AND ua.status = 'pending'  -- ONLY allow cancellation of pending appointments
        ");
        $stmt->execute([$userAppointmentId, $userId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            // Check why it can't be cancelled
            $checkStmt = $pdo->prepare("
                SELECT ua.status, a.date, a.start_time
                FROM user_appointments ua
                JOIN sitio1_appointments a ON ua.appointment_id = a.id
                WHERE ua.id = ? AND ua.user_id = ?
            ");
            $checkStmt->execute([$userAppointmentId, $userId]);
            $appointmentInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointmentInfo) {
                throw new Exception('Appointment not found or you do not have permission to cancel it.');
            } elseif ($appointmentInfo['status'] === 'approved') {
                throw new Exception('Approved appointments cannot be cancelled by users. Please contact support.');
            } elseif ($appointmentInfo['status'] === 'completed') {
                throw new Exception('Completed appointments cannot be cancelled.');
            } elseif ($appointmentInfo['status'] === 'cancelled') {
                throw new Exception('This appointment has already been cancelled.');
            } elseif ($appointmentInfo['status'] === 'rejected') {
                throw new Exception('This appointment has been rejected by staff.');
            } else {
                throw new Exception('Appointment cannot be cancelled at this time.');
            }
        }
        
        // UPDATE the appointment status to cancelled instead of deleting
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW()
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$cancelReason, $userAppointmentId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'message' => 'Appointment cancelled successfully.'
            ];
        } else {
            throw new Exception('Failed to cancel appointment.');
        }
        
        // Redirect to prevent form resubmission
        header('Location: ?tab=appointments');
        exit();
        
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Error cancelling appointment: ' . $e->getMessage()
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get available dates with slots and staff information
$availableDates = [];

try {
    // First, get all dates where user has appointments
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
    
    // Then get available slots
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
            -- Check if current user has already booked this slot (including completed appointments)
            EXISTS (
                SELECT 1 FROM user_appointments ua2 
                WHERE ua2.appointment_id = a.id 
                AND ua2.user_id = ? 
                AND ua2.status IN ('pending', 'approved', 'completed')
            ) as user_has_booked,
            -- Check if the appointment time is in the past
            (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past,
            -- Check if slot is fully booked (considering all statuses)
            (COUNT(ua.id) >= a.max_slots) as is_fully_booked
        FROM sitio1_appointments a
        JOIN sitio1_staff s ON a.staff_id = s.id
        LEFT JOIN user_appointments ua ON ua.appointment_id = a.id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.date >= CURDATE()
        GROUP BY a.id
        HAVING available_slots > 0 AND is_past = 0 AND is_fully_booked = 0
        ORDER BY a.date, a.start_time
    ");
    $stmt->execute([$userId]);
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

        /* Enhanced button styles */
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

        .download-btn {
            border-radius: 10px !important;
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
            border-radius: 10px !important;
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
                                class="px-6 py-3 border border-gray-300 rounded-lg text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200 modal-button">
                                Go Back
                            </button>
                            <button type="submit" name="cancel_appointment" id="confirm-cancel-btn"
                                class="px-6 py-3 bg-red-600 text-white rounded-lg text-base font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200 modal-button">
                                Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Contact Info Modal -->
        <div id="contact-info-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Contact Support</h3>
                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                        <p class="text-sm text-blue-800 mb-2">For approved appointments, please contact our support team to cancel:</p>
                        <div class="space-y-2 text-sm">
                            <p><strong>Email:</strong> support@communityhealthtracker.com</p>
                            <p><strong>Phone:</strong> (02) 1234-5678</p>
                            <p><strong>Office Hours:</strong> Mon-Fri, 8:00 AM - 5:00 PM</p>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" onclick="closeContactModal()"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg text-base font-medium hover:bg-blue-700 transition duration-200 modal-button">
                            OK
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
                        <button type="button" onclick="hideModal('success')" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 modal-button">
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
                        <button type="button" onclick="hideModal('error')" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 modal-button">
                            Try Again
                        </button>
                    </div>
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
                            <button type="button" onclick="hideModal('session')" class="px-6 py-3 <?= $_SESSION['notification']['type'] === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500' ?> text-white rounded-lg focus:outline-none focus:ring-2 modal-button">
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
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="closeHelpModal()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium modal-button">
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
                                                ?>
                                                    <div class="border rounded-lg p-3 <?= $isAvailable ? 'border-gray-200 hover:bg-blue-50' : 'slot-disabled border-gray-200 bg-gray-100 text-gray-500' ?>">
                                                        <div class="flex items-center">
                                                            <input 
                                                                type="radio" 
                                                                id="slot_<?= $slot['slot_id'] ?>" 
                                                                name="slot" 
                                                                value="<?= $slot['slot_id'] ?>" 
                                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" 
                                                                <?= !$isAvailable ? 'disabled' : '' ?>
                                                                required
                                                            >
                                                            <label for="slot_<?= $slot['slot_id'] ?>" class="ml-3 block">
                                                                <div class="font-medium">
                                                                    <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                                </div>
                                                                <div class="text-sm">
                                                                    <span class="font-medium">Health Worker:</span> <?= htmlspecialchars($slot['staff_name']) ?>
                                                                    <?php if (!empty($slot['specialization'])): ?>
                                                                        (<?= htmlspecialchars($slot['specialization']) ?>)
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-sm <?= $isAvailable ? 'text-green-600' : 'text-red-600' ?>">
                                                                    <?= $isAvailable ? 
                                                                        "{$slot['available_slots']} slot" . ($slot['available_slots'] > 1 ? 's' : '') . " available" : 
                                                                        'Fully booked' ?>
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
                                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition flex items-center justify-center action-button">
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
                            
                            if ($selectedSlot && !$selectedSlot['user_has_booked']): ?>
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
                                                class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition flex items-center justify-center action-button" 
                                                <?= $selectedSlot['available_slots'] <= 0 ? 'disabled' : '' ?>>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <?= $selectedSlot['available_slots'] > 0 ? 'Book Appointment' : 'Slot Full' ?>
                                        </button>
                                    </form>
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
                                                    <h3 class="font-semibold">Health Worker: <?= htmlspecialchars($appointment['staff_name']) ?>
                                                    <?php if (!empty($appointment['specialization'])): ?>
                                                        (<?= htmlspecialchars($appointment['specialization']) ?>)
                                                    <?php endif; ?>
                                                    </h3>
                                                    <p class="text-sm text-gray-600">
                                                        <?= date('M d, Y', strtotime($appointment['date'])) ?> 
                                                        at <?= date('h:i A', strtotime($appointment['start_time'])) ?> - <?= date('h:i A', strtotime($appointment['end_time'])) ?>
                                                    </p>
                                                    
                                                    <!-- Priority Number and Invoice Display -->
                                                    <?php if (!empty($appointment['invoice_number'])): ?>
                                                        <p class="text-sm text-blue-600 mt-1">
                                                            <strong>Invoice #:</strong> <?= htmlspecialchars($appointment['invoice_number']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($appointment['priority_number'])): ?>
                                                        <p class="text-sm text-green-600 font-bold mt-1">
                                                            <strong>Priority Number:</strong> <?= htmlspecialchars($appointment['priority_number']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <span class="status-badge <?= $appointment['status'] === 'approved' ? 'status-approved' : 
                                                   ($appointment['status'] === 'pending' ? 'status-pending' : 
                                                   ($appointment['status'] === 'completed' ? 'status-completed' : 
                                                   ($appointment['status'] === 'rejected' ? 'status-rejected' : 'status-cancelled'))) ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Health Concerns -->
                                        <?php if (!empty($appointment['health_concerns'])): ?>
                                            <div class="mt-3 pl-7">
                                                <p class="text-sm text-gray-700">
                                                    <span class="font-medium">Health Concerns:</span> <?= htmlspecialchars($appointment['health_concerns']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Notes -->
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <div class="mt-2 pl-7">
                                                <p class="text-sm text-gray-700">
                                                    <span class="font-medium">Notes:</span> <?= htmlspecialchars($appointment['notes']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Rejection Reason for Rejected Appointments -->
                                        <?php if ($appointmentTab === 'rejected' && !empty($appointment['rejection_reason'])): ?>
                                            <div class="mt-3 pl-7">
                                                <p class="text-sm text-red-700">
                                                    <span class="font-medium">Rejection Reason:</span> <?= htmlspecialchars($appointment['rejection_reason']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Download Section for Approved Appointments -->
<?php if ($appointment['status'] === 'approved' && !empty($appointment['priority_number'])): ?>
    <div class="mt-3 pl-7">
        <p class="text-sm text-green-600 font-bold mb-2">
            <strong>Priority Number:</strong> <?= htmlspecialchars($appointment['priority_number']) ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <?php if (!empty($appointment['invoice_number'])): ?>
                <button onclick="downloadInvoice(<?= $appointment['id'] ?>)" 
                        class="bg-blue-100 text-blue-800 px-3 py-2 rounded-full text-sm font-medium hover:bg-blue-200 flex items-center transition duration-200 ease-in-out transform hover:scale-105 download-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download Invoice
                </button>
            <?php endif; ?>
            
            <button onclick="previewAppointmentTicket(<?= $appointment['id'] ?>)" 
                    class="bg-purple-100 text-purple-800 px-3 py-2 rounded-full text-sm font-medium hover:bg-purple-200 flex items-center transition duration-200 ease-in-out transform hover:scale-105 download-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                Preview Ticket
            </button>
            
            <button onclick="downloadAppointmentTicket(<?= $appointment['id'] ?>)" 
                    class="bg-green-100 text-green-800 px-3 py-2 rounded-full text-sm font-medium hover:bg-green-200 flex items-center transition duration-200 ease-in-out transform hover:scale-105 download-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                Download Ticket
            </button>
        </div>
        
        <!-- Instructions for screenshot -->
        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
            <strong>Tip:</strong> Use "Preview Ticket" to view your ticket, then take a screenshot or use your browser's print function to save it as PDF.
        </div>
    </div>
<?php elseif ($appointment['status'] === 'approved' && empty($appointment['priority_number'])): ?>
    <div class="mt-2 pl-7">
        <p class="text-sm text-yellow-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            Ticket will be available after priority number is assigned.
        </p>
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
                                            $isFutureAppointment = $appointment['date'] > date('Y-m-d') || 
                                                                  ($appointment['date'] == date('Y-m-d') && $appointment['start_time'] > date('H:i:s'));
                                        ?>
                                            <?php if ($isFutureAppointment): ?>
                                                <div class="mt-3 pl-7">
                                                    <button onclick="openCancelModal(<?= $appointment['id'] ?>, '<?= $appointment['status'] ?>', '<?= $appointment['date'] ?>')"
                                                            class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center transition duration-200">
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

                                        <!-- Contact Support for Approved Appointments -->
                                        <?php if ($appointmentTab === 'upcoming' && $appointment['status'] === 'approved'): ?>
                                            <div class="mt-3 pl-7">
                                                <button onclick="showContactModal()"
                                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center transition duration-200">
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
            <div class="stats-card mb-8">
                <h2 class="text-xl font-semibold mb-4 blue-theme-text">Your Information</h2>
                
                <?php if ($userData): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-gray-600"><span class="font-semibold">Full Name:</span> <?= htmlspecialchars($userData['full_name']) ?></p>
                            <p class="text-gray-600"><span class="font-semibold">Age:</span> <?= $userData['age'] ? htmlspecialchars($userData['age']) : 'N/A' ?></p>
                            <p class="text-gray-600"><span class="font-semibold">Contact:</span> <?= $userData['contact'] ? htmlspecialchars($userData['contact']) : 'N/A' ?></p>
                        </div>
                        <div>
                            <p class="text-gray-600"><span class="font-semibold">Address:</span> <?= $userData['address'] ? htmlspecialchars($userData['address']) : 'N/A' ?></p>
                            <p class="text-gray-600"><span class="font-semibold">Account Status:</span> 
                                <?= $userData['approved'] ? 'Approved' : 'Pending Approval' ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">Your account information could not be loaded.</p>
                <?php endif; ?>
            </div>
            
            <div class="stats-card">
                <h2 class="text-xl font-semibold mb-4 blue-theme-text">Recent Activities</h2>
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
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <?php if ($activity['type'] === 'appointment'): ?>
                                                <?= date('F j, Y', strtotime($activity['date'])) ?>
                                            <?php else: ?>
                                                <?= date('F j, Y \a\t g:i A', strtotime($activity['created_at'])) ?>
                                            <?php endif; ?>
                                            <span class="mx-2">•</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                                            </svg>
                                            <?= ucfirst($activity['type']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 01118 0z" />
                            </svg>
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
    function openCancelModal(appointmentId, status, date) {
        const currentDate = new Date();
        const appointmentDate = new Date(date);
        
        // Check if appointment is in the past
        if (appointmentDate < currentDate) {
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
    </script>
</body>
</html>

<!-- Updated Code Here -->