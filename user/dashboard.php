<?php
// user/dashboard.php
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

// ============================ NEW: AUTOMATICALLY MARK MISSED APPOINTMENTS ============================
// Mark appointments as missed when the appointment time has passed (for pending/approved appointments)
try {
    // Get current date and time
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Find appointments that have passed but are still in pending/approved status
    $stmt = $pdo->prepare("
        SELECT ua.id 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.user_id = ? 
        AND ua.status IN ('pending', 'approved')
        AND (
            a.date < ? 
            OR (a.date = ? AND a.end_time < ?)
        )
    ");
    $stmt->execute([$userId, $currentDate, $currentDate, $currentTime]);
    $missedAppointments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Update them to missed status
    if (!empty($missedAppointments)) {
        $placeholders = str_repeat('?,', count($missedAppointments) - 1) . '?';
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'missed' 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($missedAppointments);
    }
} catch (PDOException $e) {
    // Log error but don't show to user
    error_log("Error marking missed appointments: " . $e->getMessage());
}
// =====================================================================================================

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
            $_SESSION['notification'] = [
                'type' => 'error',
                'title' => 'Invalid File Type',
                'message' => 'Only JPG, JPEG, PNG, and GIF files are allowed.',
                'icon' => 'fas fa-exclamation-triangle',
                'details' => []
            ];
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            $_SESSION['notification'] = [
                'type' => 'error',
                'title' => 'File Too Large',
                'message' => 'File size must be less than 5MB.',
                'icon' => 'fas fa-exclamation-triangle',
                'details' => []
            ];
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
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
                    'title' => 'Profile Updated',
                    'message' => 'Profile image updated successfully!',
                    'icon' => 'fas fa-user-circle',
                    'details' => []
                ];
                
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            } else {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'title' => 'Upload Failed',
                    'message' => 'Failed to upload image. Please try again.',
                    'icon' => 'fas fa-exclamation-triangle',
                    'details' => []
                ];
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    } else {
        $_SESSION['notification'] = [
            'type' => 'error',
            'title' => 'Invalid File',
            'message' => 'Please select a valid image file.',
            'icon' => 'fas fa-exclamation-circle',
            'details' => []
        ];
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
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
            'title' => 'Profile Updated',
            'message' => 'Profile image removed successfully!',
            'icon' => 'fas fa-user-circle',
            'details' => []
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

        // UPDATED: Count only unread announcements that are ACTUALLY TARGETED to this user
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM sitio1_announcements a 
            WHERE a.status = 'active'
            AND (expiry_date IS NULL OR expiry_date >= CURDATE())
            AND (
                -- Public announcements (excluding landing_page)
                (a.audience_type = 'public')
                OR 
                -- Specific announcements targeted to this user
                (a.audience_type = 'specific' AND a.id IN (
                    SELECT announcement_id 
                    FROM announcement_targets 
                    WHERE user_id = ?
                ))
            )
            AND a.id NOT IN (
                SELECT announcement_id 
                FROM user_announcements 
                WHERE user_id = ?
            )
        ");
        $stmt->execute([$userId, $userId]);
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
        $_SESSION['notification'] = [
            'type' => 'error',
            'title' => 'Booking Error',
            'message' => 'Missing required booking information.',
            'icon' => 'fas fa-exclamation-circle',
            'details' => []
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
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
                'title' => 'Health Concerns Required',
                'message' => 'Please select at least one health concern.',
                'icon' => 'fas fa-heartbeat',
                'details' => []
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
                'title' => 'Already Booked',
                'message' => 'You already have an appointment scheduled for ' . date('M d, Y', strtotime($selectedDate)) . '. Please choose a different date.',
                'icon' => 'fas fa-calendar-times',
                'details' => []
            ];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }

        // Check if slot end time has passed (booking allowed until end_time - 1 minute)
        $currentDateTime = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            SELECT a.date, a.end_time 
            FROM sitio1_appointments a 
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        $slotInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($slotInfo) {
            $slotDateTime = $slotInfo['date'] . ' ' . $slotInfo['end_time'];
            $slotEndTime = date('Y-m-d H:i:s', strtotime($slotDateTime . ' -1 minute')); // Allow until 1 minute before end time
            
            if ($currentDateTime > $slotEndTime) {
                $_SESSION['notification'] = [
                    'type' => 'error',
                    'title' => 'Booking Closed',
                    'message' => 'This appointment time slot has already closed. Please select another time slot.',
                    'icon' => 'fas fa-clock',
                    'details' => [
                        'Time Slot' => date('h:i A', strtotime($slotInfo['end_time'])),
                        'Status' => 'Already Closed'
                    ]
                ];
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit();
            }
        }

        // ============================ NEW: GET PRIORITY NUMBER FOR TIME SLOT ============================
        // Get the priority number for this time slot
        $priorityNumber = null;
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(priority_number AS UNSIGNED)) as max_priority 
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE a.id = ? 
            AND ua.status = 'approved'
            AND a.date = ?
        ");
        $stmt->execute([$appointmentId, $selectedDate]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['max_priority'] !== null) {
            // If there are already approved appointments in this time slot, use the same priority number
            $priorityNumber = $result['max_priority'];
        } else {
            // If this is the first approved appointment in this time slot, get the next available number
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(priority_number AS UNSIGNED)), 0) + 1 as next_priority 
                FROM user_appointments 
                WHERE status = 'approved' 
                AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            $priorityResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $priorityNumber = $priorityResult['next_priority'];
        }
        // ================================================================================================

        // Prepare insert
        $healthConcernsStr = implode(', ', $healthConcerns);
        $consentGiven = isset($_POST['consent']) ? 1 : 0;

        $stmt = $pdo->prepare(
            "INSERT INTO user_appointments 
                (user_id, appointment_id, service_id, status, notes, health_concerns, service_type, consent, priority_number) 
             VALUES 
                (:user_id, :appointment_id, :service_id, 'pending', :notes, :health_concerns, :service_type, :consent, :priority_number)"
        );
        $stmt->execute([
            ':user_id'         => $userId,
            ':appointment_id'  => $appointmentId,
            ':service_id'      => $serviceId,
            ':notes'           => $notes,
            ':health_concerns' => $healthConcernsStr,
            ':service_type'    => $serviceType,
            ':consent'         => $consentGiven,
            ':priority_number' => $priorityNumber  // NEW: Add priority number
        ]);

        // Get the selected slot info for notification
        $stmt = $pdo->prepare("
            SELECT a.date, a.start_time, a.end_time 
            FROM sitio1_appointments a 
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        $selectedSlot = $stmt->fetch(PDO::FETCH_ASSOC);

        // In the booking success notification
        $_SESSION['notification'] = [
            'type' => 'success',
            'title' => 'Appointment Booked Successfully!',
            'message' => 'Your health visit has been scheduled successfully. You will receive a confirmation shortly.',
            'icon' => 'fas fa-calendar-check',
            'details' => [
                'Date' => date('F j, Y', strtotime($selectedDate)),
                'Time Slot' => date('g:i A', strtotime($selectedSlot['start_time'])) . ' - ' . date('g:i A', strtotime($selectedSlot['end_time'])),
                'Status' => 'Pending Approval',
                'Health Concerns' => $healthConcernsStr,
            ]
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
            'title' => 'Cancellation Error',
            'message' => 'Please provide a reason for cancellation.',
            'icon' => 'fas fa-exclamation-circle',
            'details' => []
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    if (strlen($cancelReason) < 10) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'title' => 'Cancellation Error',
            'message' => 'Please provide a detailed reason for cancellation (at least 10 characters).',
            'icon' => 'fas fa-exclamation-circle',
            'details' => []
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
                $errorMessage = 'Appointment not found or you do not have permission to cancel it.';
            } elseif ($appointmentInfo['status'] !== 'pending') {
                $errorMessage = 'Only pending appointments can be cancelled. For approved appointments, please contact support.';
            } elseif ($appointmentInfo['is_past']) {
                $errorMessage = 'Past appointments cannot be cancelled.';
            } else {
                $errorMessage = 'Appointment cannot be cancelled at this time.';
            }
            
            $_SESSION['notification'] = [
                'type' => 'error',
                'title' => 'Cancellation Failed',
                'message' => $errorMessage,
                'icon' => 'fas fa-times-circle',
                'details' => []
            ];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        // UPDATE the appointment status to cancelled
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'cancelled', cancel_reason = ?, cancelled_at = NOW(), cancelled_by_user = 1
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$cancelReason, $userAppointmentId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['notification'] = [
                'type' => 'success',
                'title' => 'Appointment Cancelled',
                'message' => 'Your appointment has been cancelled successfully. The slot is now available for others.',
                'icon' => 'fas fa-calendar-times',
                'details' => [
                    'Cancelled On' => date('F j, Y g:i A'),
                    'Reason' => $cancelReason
                ]
            ];
            
            // Redirect to show success modal
            header('Location: ?tab=appointments&appointment_tab=upcoming');
            exit();
            
        } else {
            throw new Exception('Failed to cancel appointment.');
        }
        
    } catch (Exception $e) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'title' => 'Cancellation Error',
            'message' => 'Error cancelling appointment: ' . $e->getMessage(),
            'icon' => 'fas fa-exclamation-triangle',
            'details' => []
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
    
    // ============================ MODIFIED: FILTER OUT PAST DATES ============================
    // Get current date and time
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');
    
    // Then get available slots with appointment booking rules
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
            -- MODIFIED: Allow booking until 1 minute before end time
            CASE 
                -- Future dates are always allowed
                WHEN a.date > CURDATE() THEN 1
                -- Today's appointments: allow booking until 1 minute before end time
                WHEN a.date = CURDATE() AND TIME(NOW()) < a.end_time THEN 1
                -- Otherwise, booking not allowed
                ELSE 0
            END as booking_allowed
        FROM sitio1_appointments a
        JOIN sitio1_staff s ON a.staff_id = s.id
        LEFT JOIN user_appointments ua ON ua.appointment_id = a.id AND ua.status IN ('pending', 'approved', 'completed')
        WHERE a.date >= CURDATE()  -- Only future dates
        AND NOT (a.date = CURDATE() AND a.end_time < TIME(NOW()))  -- Exclude past slots for today
        GROUP BY a.id
        HAVING available_slots > 0 AND is_past = 0 AND is_fully_booked = 0 AND booking_allowed = 1
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
                'total_slots' => 0,
                'available_slots' => 0,
                'user_has_appointment' => in_array($date, $userAppointmentDates)
            ];
        }
        $availableDates[$date]['slots'][] = $slot;
        $availableDates[$date]['total_slots'] += $slot['max_slots'];
        $availableDates[$date]['available_slots'] += $slot['available_slots'];
    }
    $availableDates = array_values($availableDates);
    // =========================================================================================
} catch (PDOException $e) {
    $error = 'Error fetching available dates: ' . $e->getMessage();
}

// Get counts for each appointment tab
$appointmentCounts = [
    'upcoming' => 0,
    'past' => 0,
    'cancelled' => 0,
    'rejected' => 0,
    'missed' => 0  // Add missed appointments count
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
    
    // Missed: Count missed appointments (past appointments with status 'pending' or 'approved')
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.user_id = ? 
        AND ua.status = 'missed'
    ");
    $stmt->execute([$userId]);
    $appointmentCounts['missed'] = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error = 'Error fetching appointment counts: ' . $e->getMessage();
}

// Get user's appointments
$appointments = [];

// Get user's appointments with proper priority number display
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
            ua.rejection_reason,
            -- Determine if appointment is missed (past appointment with pending/approved status)
            CASE 
                WHEN ua.status IN ('pending', 'approved') 
                AND (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) 
                THEN 'missed'
                ELSE ua.status
            END as display_status
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
    } elseif ($appointmentTab === 'missed') {
        $query .= " AND ua.status = 'missed'";
    }
    
    $query .= " ORDER BY a.date " . ($appointmentTab === 'past' || $appointmentTab === 'cancelled' || $appointmentTab === 'rejected' || $appointmentTab === 'missed' ? 'DESC' : 'ASC') . ", a.start_time";
    
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
        /* ... (All CSS styles remain exactly the same) ... */
        /* (Keeping all CSS styles as they were, only PHP logic is modified) */
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
            padding: 12px 24px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
        }

        .action-button:hover {
            transform: translateY(-3px) !important;
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

        .status-missed {
            background-color: #f3e8ff !important;
            color: #8b5cf6 !important;
            border: 2px solid #8b5cf6 !important;
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

        /* UPDATED: Enhanced Calendar day styling with slots badge */
        .calendar-day {
            min-height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease-in-out;
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: white;
            padding-top: 1rem;
        }

        .calendar-day:hover {
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }

        .calendar-day.selected {
            border-color: #3b82f6 !important;
            background: #3b82f6 !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateY(-2px) scale(1.02);
            z-index: 10;
        }

        .calendar-day.disabled {
            opacity: 0.4;
            cursor: not-allowed !important;
            background: #f8fafc !important;
            color: #9ca3af !important;
            border-color: #e5e7eb;
        }

        /* UPDATED: Slots badge styling */
        .slots-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #3b82f6;
            color: white;
            border-radius: 6px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            width: 100% !important;
        }

        .profile-action-btn i {
            margin-right: 8px;
        }

        .upload-btn {
            background: #3b82f6 !important;
            color: white !important;
        }

        .upload-btn:hover {
            background: #2563eb !important;
            transform: translateY(-2px) !important;
        }

        .remove-btn {
            background: #ef4444 !important;
            color: white !important;
        }

        .remove-btn:hover {
            background: #dc2626 !important;
            transform: translateY(-2px) !important;
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
            padding: 12px 24px !important;
            background: #3b82f6 !important;
            color: white !important;
            border-radius: 9999px !important;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease !important;
            font-weight: 600 !important;
            text-align: center !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px;
        }

        .file-input-label:hover {
            background: #2563eb !important;
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

        .status-missed {
            background-color: #f3e8ff !important;
            color: #8b5cf6 !important;
            border: 2px solid #8b5cf6 !important;
        }
        
        /* UPDATED: Enhanced Slot Time Radio Button Styles */
        .slot-time-radio {
            display: none;
        }

        .slot-time-label {
            display: block;
            width: 100%;
            padding: 16px;
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .slot-time-label:hover {
            border-color: rgba(59, 130, 246, 0.5);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15), 0 1px 5px rgba(59, 130, 246, 0.1);
            transform: translateY(-1px);
        }

        .slot-time-radio:checked + .slot-time-label {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            transform: translateY(-1px);
        }

        .slot-time-radio:checked + .slot-time-label .slot-time-text,
        .slot-time-radio:checked + .slot-time-label .slot-info-text {
            color: white;
        }

        .slot-time-radio:checked + .slot-time-label .slot-availability {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .slot-time-radio:disabled + .slot-time-label {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8fafc;
            border-color: #e5e7eb;
        }

        .slot-time-radio:disabled + .slot-time-label:hover {
            transform: none;
            box-shadow: none;
            border-color: #e5e7eb;
        }

        .slot-time-text {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .slot-info-text {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.4;
        }

        .slot-availability {
            display: inline-block;
            padding: 4px 8px;
            background: #10b981;
            color: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .slot-availability.unavailable {
            background: #ef4444;
        }

        .slot-availability.closing-soon {
            background: #f59e0b;
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
        
        /* Realistic profile UI */
        .profile-image-wrapper {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }
        
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .profile-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .profile-upload-area:hover {
            border-color: #3b82f6;
            background: #f0f9ff;
        }
        
        .profile-upload-area.dragover {
            border-color: #3b82f6;
            background: #e0f2fe;
        }
        
        /* Modern input styling */
        .modern-input {
            border-radius: 12px !important;
            border: 2px solid #e5e7eb !important;
            padding: 14px 16px !important;
            font-size: 16px !important;
            transition: all 0.3s ease !important;
        }
        
        .modern-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
            outline: none !important;
        }
        
        /* Info card styling */
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
        }
        
        .info-card h3 {
            color: white;
            margin-bottom: 15px;
        }
        
        .info-card p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        /* Responsive profile layout */
        @media (max-width: 768px) {
            .profile-image-wrapper {
                width: 150px;
                height: 150px;
            }
            
            .profile-upload-area {
                padding: 20px;
            }
        }
        
        /* Notification Modals */
        .notification-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .notification-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .notification-modal {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.4s ease;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .notification-modal.active {
            transform: translateY(0);
            opacity: 1;
        }

        /* Success Modal Specific Styles */
        .success-modal .notification-header {
            background: linear-gradient(#2B7CC9, #3C96E1);
            padding: 30px 30px 10px;
            text-align: center;
            color: white;
        }

        .success-modal .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .success-modal .notification-icon i {
            font-size: 40px;
            color: white;
        }

        .success-modal .notification-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
        }

        .success-modal .notification-message {
            font-size: 18px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
        }

        /* Cancellation Modal Specific Styles */
        .cancellation-modal .notification-header {
            background: #f08a6bff;
            padding: 30px 30px 10px;
            text-align: center;
            color: white;
        }

        .cancellation-modal .notification-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .cancellation-modal .notification-icon i {
            font-size: 40px;
            color: white;
        }

        .cancellation-modal .notification-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
        }

        .cancellation-modal .notification-message {
            font-size: 18px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 10px;
        }

        /* Modal Body */
        .notification-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .notification-details {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .notification-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .notification-detail-item:last-child {
            border-bottom: none;
        }

        .notification-detail-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .notification-detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }

        /* Modal Footer */
        .notification-footer {
            padding: 25px 30px 35px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
        }

        .notification-close-btn {
            min-width: 200px;
            padding: 16px 32px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .success-modal .notification-close-btn {
            background: #3C96E1;
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .success-modal .notification-close-btn:hover {
            background: #3C96E1;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .cancellation-modal .notification-close-btn {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .cancellation-modal .notification-close-btn:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Missed Appointment Button */
        .missed-appointment-btn {
            border-radius: 9999px !important;
            padding: 12px 24px !important;
            font-weight: 600 !important;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%) !important;
            color: white !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3) !important;
        }

        .missed-appointment-btn:hover {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4) !important;
        }

        /* Warm Blue Color for Book This Appointment Button */
        .warm-blue-bg {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
        }
        
        .warm-blue-bg:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Success Notification Modal -->
    <div id="success-modal-overlay" class="notification-modal-overlay">
        <div id="success-modal" class="notification-modal success-modal">
            <div class="notification-header">
                <div class="notification-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 id="success-title" class="notification-title">Success!</h3>
                <p id="success-message" class="notification-message">Your action was completed successfully.</p>
            </div>
            
            <div class="notification-body">
                <div id="success-details" class="notification-details" style="display: none;">
                    <div id="success-details-content" class="notification-details-grid">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <div class="notification-footer">
                <button id="success-close-btn" class="notification-close-btn">
                    <i class="fas fa-check"></i>
                    Continue
                </button>
            </div>
        </div>
    </div>

    <!-- Cancellation Notification Modal -->
    <div id="cancellation-modal-overlay" class="notification-modal-overlay">
        <div id="cancellation-modal" class="notification-modal cancellation-modal">
            <div class="notification-header">
                <div class="notification-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3 id="cancellation-title" class="notification-title">Cancelled</h3>
                <p id="cancellation-message" class="notification-message">Your appointment has been cancelled.</p>
            </div>
            
            <div class="notification-body">
                <div id="cancellation-details" class="notification-details" style="display: none;">
                    <div id="cancellation-details-content" class="notification-details-grid">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <div class="notification-footer">
                <button id="cancellation-close-btn" class="notification-close-btn">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Original Notification Modal (for other notifications) -->
    <div id="notification-modal-overlay" class="notification-modal-overlay">
        <div id="notification-modal" class="notification-modal">
            <div class="notification-header" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 40px 30px 30px; text-align: center; color: white;">
                <div class="notification-icon" style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; margin: 0 auto 25px;">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3 id="notification-title" class="notification-title" style="font-size: 28px; font-weight: 700; margin-bottom: 15px; color: white;">Notification</h3>
                <p id="notification-message" class="notification-message" style="font-size: 18px; line-height: 1.5; color: rgba(255, 255, 255, 0.9); margin-bottom: 10px;">Your notification message here.</p>
            </div>
            
            <div class="notification-body">
                <div id="notification-details" class="notification-details" style="display: none;">
                    <div id="notification-details-content" class="notification-details-grid">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            
            <div class="notification-footer">
                <button id="notification-close-btn" class="notification-close-btn" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                    <i class="fas fa-check"></i>
                    OK
                </button>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <div id="cancel-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>

    <!-- Wider modal: changed max-w-md → max-w-2xl and added w-full -->
    <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-2xl w-full">
        <div class="p-8"><!-- Increased padding for better spacing -->
            <h3 class="text-xl font-semibold text-gray-900 mb-6">Cancel Appointment</h3>

            <div id="cancel-warning" class="hidden mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-800" id="cancel-warning-message"></p>
            </div>

            <form method="POST" class="space-y-6" id="cancel-form">
                <input type="hidden" name="cancel_appointment" value="1">
                <input type="hidden" name="appointment_id" id="modal-appointment-id">

                <div>
                    <label for="cancel-reason" class="block text-sm font-lg text-gray-700 mb-2">
                        Reason for cancellation <span class="text-red-500">*</span>
                    </label>

                    <!-- Wider textarea: added text-base and increased padding -->
                    <textarea id="cancel-reason" name="cancel_reason" rows="5"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-base"
                        required placeholder="Kindly provide the reason for canceling this appointment."></textarea>
                </div>

                <div class="flex justify-end space-x-4 pt-6">
                    <button type="button" onclick="closeCancelModal()"
                        class="px-6 py-3 border border-gray-300 rounded-full text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200">
                        Go Back
                    </button>

                    <button type="submit" name="cancel_appointment" id="confirm-cancel-btn"
                        class="px-6 py-3 bg-red-600 text-white rounded-full text-base font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-200">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


        <!-- Appointment Booking Modal -->
        <div id="booking-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-blue-600 mb-4 flex items-center text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Confirm Health Visit
                    </h3>
                    
                    <div id="booking-details" class="space-y-4 mb-6">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                    
                    <form method="POST" action="" class="space-y-6" id="booking-form" onsubmit="return validateHealthConcerns()">
                        <input type="hidden" name="appointment_id" id="modal-appointment-id-input">
                        <input type="hidden" name="selected_date" id="modal-selected-date-input">
                        <input type="hidden" name="service_id" id="modal-service-id-input">
                        <input type="hidden" name="service_type" value="General Checkup">

                        <!-- Health Concerns Section -->
                        <div>
                            <h4 class="font-medium text-gray-700 mb-3 text-lg">Select Health Concerns <span class="text-red-500">*</span></h4>
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
                                               id="modal-concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                               name="health_concerns[]" 
                                               value="<?= $concern ?>"
                                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="modal-concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                               class="ml-3 text-gray-700 text-base"><?= $concern ?></label>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- Other Concern Option -->
                                <div class="flex items-center">
                                    <input type="checkbox" id="modal-other_concern" name="health_concerns[]" value="Other"
                                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="modal-other_concern" class="ml-3 text-gray-700 text-base">Other</label>
                                </div>
                            </div>
                            
                            <div class="mt-4" id="modal-other_concern_container" style="display: none;">
                                <label for="modal-other_concern_specify" class="block text-gray-700 mb-2 text-base">Please specify:</label>
                                <input type="text" id="modal-other_concern_specify" name="other_concern_specify" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input">
                            </div>
                            
                            <div id="health-concerns-error" class="mt-3 text-sm text-red-600 hidden">
                                Please select at least one health concern.
                            </div>
                        </div>
                        
                        <!-- Additional Notes -->
                        <div>
                            <label for="modal-appointment_notes" class="block text-gray-700 mb-2 text-base font-medium">Health Concerns Details</label>
                            <textarea id="modal-appointment_notes" name="notes" rows="4" 
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input" 
                                      placeholder="Describe your symptoms, concerns, or any other relevant information"></textarea>
                        </div>
                        
                        <!-- Consent Checkbox -->
                        <div class="flex items-center">
                            <input type="checkbox" id="modal-consent" name="consent" required
                                   class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="modal-consent" class="ml-3 text-gray-700 text-base">
                                I consent to sharing my health information for this appointment
                            </label>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closeBookingModal()"
                                    class="px-6 py-3 border border-gray-300 rounded-full text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200 modal-button">
                                Cancel
                            </button>
                            <button type="submit" name="book_appointment" 
                                    class="px-6 py-3 bg-blue-600 text-white rounded-full text-base font-medium hover:bg-blue-500 transition flex items-center justify-center modal-button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Book Appointment
                            </button>
                        </div>
                    </form>
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
                            <h3 class="text-xl font text-white">Contact Support Team</h3>
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
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600 mt-0.5 mr-3" viewBox="0 0 20 20" fill="currentColor">
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
                                        <span class="text-2xl font text-blue-600 mr-3">(02) 1234-5678</span>
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
                                        <span class="text-xl font text-green-600 mr-3">support@brgyluzcebucity.com</span>
                                        <button onclick="copyToClipboard('support@brgyluzcebucity.com')" class="text-green-600 hover:text-green-700 text-sm font-medium flex items-center">
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
                                class="px-6 py-3 border border-gray-300 rounded-full text-base font-medium text-gray-700 hover:bg-gray-50 transition duration-200 modal-button">
                                Cancel
                            </button>
                            <button type="submit" name="update_profile_image" id="upload-submit-btn"
                                class="px-6 py-3 bg-blue-600 text-white rounded-full text-base font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200 modal-button"
                                disabled>
                                <i class="fas fa-upload mr-2"></i> Upload Photo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                User Dashboard
            </h1>
            <!-- Help Button -->
            <button onclick="openHelpModal()" class="help-icon text-blue-600 p-8 rounded-full hover:text-blue-500 transition action-button">
                <i class="fas fa-question-circle text-3xl"></i>
            </button>
        </div>

        <!-- Help/Guide Modal -->
<div id="helpModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-8 mx-auto p-0 border w-full max-w-2xl shadow-xl rounded-xl bg-white overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-2xl font-bold text-white mb-1">Dashboard User Guide</h3>
                    <p class="text-blue-100 text-sm">Everything you need to know as a patient</p>
                </div>
                <button onclick="closeHelpModal()" class="text-white hover:text-blue-200 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Welcome Banner -->
        <div class="px-8 pt-6">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-lg p-5 mb-2">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mt-0.5">
                        <i class="fas fa-hands-helping text-blue-500 text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="font-semibold text-blue-800 text-lg mb-1">Welcome to Barangay Health Monitoring and Tracking Platform</h4>
                        <p class="text-blue-700">This guide will help you navigate all the features available to you as a patient. Each section below explains key functionalities.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guide Content -->
        <div class="px-8 py-4">
            <div class="space-y-6">
                <!-- Appointment Management -->
                <div class="flex items-start group hover:bg-gray-50 p-4 rounded-lg transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-blue-200 transition-colors">
                        <i class="fas fa-calendar-alt text-blue-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-lg mb-2">Appointment Management</h4>
                        <p class="text-gray-600">Book new appointments, view upcoming visits, and manage your scheduled consultations with healthcare providers.</p>
                    </div>
                </div>

                <!-- Appointment Status -->
                <div class="flex items-start group hover:bg-gray-50 p-4 rounded-lg transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-green-200 transition-colors">
                        <i class="fas fa-clipboard-check text-green-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-lg mb-2">Appointment Status</h4>
                        <p class="text-gray-600">Track your appointment status in real-time: <span class="font-medium">Pending, Approved, Completed, Cancelled, or Missed</span>.</p>
                    </div>
                </div>

                <!-- Cancellation Policy -->
                <div class="flex items-start group hover:bg-gray-50 p-4 rounded-lg transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-purple-200 transition-colors">
                        <i class="fas fa-ban text-purple-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-lg mb-2">Cancellation Policy</h4>
                        <p class="text-gray-600">Cancel pending appointments directly online. For approved appointments, please contact our support team for assistance.</p>
                    </div>
                </div>

                <!-- Missed Appointments -->
                <div class="flex items-start group hover:bg-gray-50 p-4 rounded-lg transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-orange-200 transition-colors">
                        <i class="fas fa-exclamation-triangle text-orange-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-lg mb-2">Missed Appointments</h4>
                        <p class="text-gray-600">If you miss an appointment, you can easily book a new date for your rescheduled visit through the dashboard.</p>
                    </div>
                </div>

                <!-- Profile Management -->
                <div class="flex items-start group hover:bg-gray-50 p-4 rounded-lg transition-all">
                    <div class="flex-shrink-0 w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center mr-4 group-hover:bg-pink-200 transition-colors">
                        <i class="fas fa-user-circle text-pink-600"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 text-lg mb-2">Profile Management</h4>
                        <p class="text-gray-600">Update your profile photo and personal information to help our staff recognize you and provide personalized care.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-8 py-6 bg-gray-50 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <div class="flex items-center text-gray-500 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Need more help? Contact our support team.</span>
                </div>
                <button type="button" onclick="closeHelpModal()" class="px-7 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-medium rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg flex items-center">
                    <i class="fas fa-check mr-2"></i>
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
                Appointments Management
            </a>
            <a href="?tab=dashboard" class="<?= $activeTab === 'dashboard' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Personal Information
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
                <a href="?tab=appointments&appointment_tab=missed" class="<?= $appointmentTab === 'missed' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                    </svg>
                    Missed
                    <span class="count-badge bg-purple-100 text-purple-800"><?= $appointmentCounts['missed'] ?></span>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                Available Dates
                            </h3>
                            <?php if (!empty($availableDates)): ?>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <?php foreach ($availableDates as $dateInfo): 
                                        $date = $dateInfo['date'];
                                        $hasAvailableSlots = false;
                                        $userHasAppointment = $dateInfo['user_has_appointment'];
                                        $slotsLeft = $dateInfo['available_slots'];
                                        
                                        foreach ($dateInfo['slots'] as $slot) {
                                            if ($slot['available_slots'] > 0 && !$slot['user_has_booked']) {
                                                $hasAvailableSlots = true;
                                            }
                                        }
                                    ?>
                                        <a href="<?= $userHasAppointment ? '#' : '?tab=appointments&date=' . $date ?>" 
                                           class="calendar-day <?= ($_GET['date'] ?? '') === $date ? 'selected' : ($userHasAppointment ? 'date-disabled' : ($hasAvailableSlots ? '' : 'date-disabled')) ?>">
                                            <!-- Slots badge in top left -->
                                            <?php if ($slotsLeft > 0 && !$userHasAppointment): ?>
                                                <div class="slots-badge">
                                                    <?= $slotsLeft ?> slot<?= $slotsLeft > 1 ? 's' : '' ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="font-medium text-lg"><?= date('D', strtotime($date)) ?></div>
                                            <div class="text-base"><?= date('M j', strtotime($date)) ?></div>
                                            <?php if ($userHasAppointment): ?>
                                                <div class="text-xs text-white mt-1 mb-4">You have appointment</div>
                                            <?php elseif (!$hasAvailableSlots): ?>
                                                <div class="text-xs text-white mt-1 mb-4">Fully Booked</div>
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
                                    <select id="appointment_date" name="date" class="w-full pl-12 pr-12 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 modern-input">
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
                                        <label class="block text-gray-700 mb-2 font-medium">Available Time Slots:</label>
                                        <?php if ($selectedDateInfo && !empty($selectedDateInfo['slots']) && !$userHasAppointmentOnSelectedDate): ?>
                                            <div class="space-y-3">
                                                <?php 
                                                $selectedSlotId = $_GET['slot'] ?? null;
                                                foreach ($selectedDateInfo['slots'] as $slot): 
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
                                                    $availabilityClass = 'slot-availability';
                                                    
                                                    if ($slotDate === date('Y-m-d')) {
                                                        // Today's slot - check if current time is before end time
                                                        $currentDateTime = date('Y-m-d H:i:s');
                                                        $slotDateTime = $slotDate . ' ' . $slotEndTime;
                                                        $slotEndTimeMinusOne = date('Y-m-d H:i:s', strtotime($slotDateTime . ' -1 minute'));
                                                        
                                                        if ($currentDateTime > $slotEndTimeMinusOne) {
                                                            $slotStatus = 'unavailable';
                                                            $statusText = 'Already Closed';
                                                            $availabilityClass = 'slot-availability unavailable';
                                                            $isAvailable = false;
                                                        }
                                                    }
                                                    
                                                    // Check if this slot is the selected one (from URL or form submission)
                                                    $isSelected = ($selectedSlotId == $slot['slot_id']);
                                                ?>
                                                    <div class="relative">
                                                        <input type="radio" 
                                                               id="slot_<?= $slot['slot_id'] ?>" 
                                                               name="slot" 
                                                               value="<?= $slot['slot_id'] ?>" 
                                                               class="slot-time-radio"
                                                               <?= !$isAvailable || $slotStatus === 'unavailable' ? 'disabled' : '' ?>
                                                               <?= $isSelected ? 'checked' : '' ?>
                                                               required>
                                                        <label for="slot_<?= $slot['slot_id'] ?>" class="slot-time-label <?= $isSelected ? 'border-blue-500 bg-blue-50' : '' ?>">
                                                            <div class="slot-time-text">
                                                                <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                            </div>
                                                            <div class="slot-info-text">
                                                                <span class="font-medium">Health Worker:</span> <?= htmlspecialchars($slot['staff_name']) ?>
                                                                <?php if (!empty($slot['specialization'])): ?>
                                                                    (<?= htmlspecialchars($slot['specialization']) ?>)
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if (!$isAvailable): ?>
                                                                <div class="slot-availability unavailable">Fully booked</div>
                                                            <?php elseif ($slotStatus === 'unavailable'): ?>
                                                                <div class="slot-availability unavailable"><?= $statusText ?></div>
                                                            <?php else: ?>
                                                                <div class="<?= $availabilityClass ?>">
                                                                    <?= "{$slot['available_slots']} slot" . ($slot['available_slots'] > 1 ? 's' : '') . " available" ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </label>
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
                                    <button type="submit" id="find-availability-btn" class="w-full bg-blue-600 text-white py-3 px-4 rounded-full hover:bg-blue-700 transition flex items-center justify-center action-button">
                                        <svg id="search-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <span id="btn-text">Find Availability</span>
                                        <svg id="loading-spinner" class="animate-spin h-5 w-5 mr-2 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
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
                                $currentDateTime = date('Y-m-d H:i:s');
                                $slotDateTime = $selectedSlot['date'] . ' ' . $selectedSlot['end_time'];
                                $slotEndTimeMinusOne = date('Y-m-d H:i:s', strtotime($slotDateTime . ' -1 minute'));
                                
                                $bookingAllowed = true;
                                $bookingMessage = '';
                                
                                if ($selectedSlot['date'] === date('Y-m-d')) {
                                    if ($currentDateTime > $slotEndTimeMinusOne) {
                                        $bookingAllowed = false;
                                        $bookingMessage = 'This appointment time slot has already closed. Please select another time slot.';
                                    }
                                }
                            ?>
                                <div class="border border-gray-200 rounded-lg p-6 mb-6 stats-card">
                                    <h3 class="font-semibold text-lg mb-4 flex items-center text-blue-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Slot Selected
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
                                        <div class="mt-6">
                                            <button onclick="openBookingModal(
                                                <?= $selectedSlot['slot_id'] ?>, 
                                                '<?= $selectedDate ?>', 
                                                '<?= htmlspecialchars($selectedSlot['staff_name']) ?>', 
                                                '<?= !empty($selectedSlot['specialization']) ? htmlspecialchars($selectedSlot['specialization']) : '' ?>', 
                                                '<?= date('M d, Y', strtotime($selectedDate)) ?>', 
                                                '<?= date('h:i A', strtotime($selectedSlot['start_time'])) ?>', 
                                                '<?= date('h:i A', strtotime($selectedSlot['end_time'])) ?>', 
                                                <?= $selectedSlot['available_slots'] ?>, 
                                                <?= $selectedSlot['max_slots'] ?>)"
                                                    class="w-full warm-blue-bg text-white py-3 px-4 rounded-full hover:bg-blue-700 transition flex items-center justify-center action-button">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Book This Appointment
                                            </button>
                                        </div>
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
                            <?php elseif ($appointmentTab === 'missed'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                </svg>
                                Missed Appointments
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
                                                <?php if ($appointment['display_status'] === 'approved'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                <?php elseif ($appointment['display_status'] === 'pending'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                                    </svg>
                                                <?php elseif ($appointment['display_status'] === 'completed'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
                                                    </svg>
                                                <?php elseif ($appointment['display_status'] === 'missed'): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
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
                                            <!-- UPDATED Status Badge - Always shows status name, not priority number -->
                                            <span class="status-badge-enhanced <?= $appointment['display_status'] === 'approved' ? 'status-approved' : 
                                                   ($appointment['display_status'] === 'pending' ? 'status-pending' : 
                                                   ($appointment['display_status'] === 'completed' ? 'status-completed' : 
                                                   ($appointment['display_status'] === 'rejected' ? 'status-rejected' : 
                                                   ($appointment['display_status'] === 'missed' ? 'status-missed' : 'status-cancelled')))) ?>">
                                                <?php 
                                                $statusText = [
                                                    'pending' => 'Appointment Pending',
                                                    'approved' => 'Appointment Approved', 
                                                    'completed' => 'Appointment Completed',
                                                    'cancelled' => 'Appointment Cancelled',
                                                    'rejected' => 'Appointment Rejected',
                                                    'missed' => 'Missed Appointment'
                                                ];
                                                echo $statusText[$appointment['display_status']] ?? 'Appointment ' . ucfirst($appointment['display_status']);
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
                                        
                                        <!-- Special Action for Missed Appointments -->
                                        <?php if ($appointment['display_status'] === 'missed'): ?>
                                            <div class="mt-4 pl-7">
                                                <div class="bg-gradient-to-r from-purple-50 to-white border border-purple-200 rounded-lg p-4 shadow-sm">
                                                    <div class="flex items-center mb-3">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 01118 0z" />
                                                        </svg>
                                                        <div>
                                                            <h4 class="font-semibold text-purple-800">Missed Appointment</h4>
                                                            <p class="text-purple-600 text-sm">You can book another date for your new appointment</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <a href="?tab=appointments" class="w-full bg-gradient-to-r from-purple-600 to-purple-700 text-white px-5 py-3 rounded-full text-base font-semibold hover:from-purple-700 hover:to-purple-800 flex items-center justify-center transition duration-200 action-button missed-appointment-btn">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                        </svg>
                                                        Book New Appointment
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Redesigned Ticket Display for Approved Appointments -->
                                        <?php if ($appointment['display_status'] === 'approved' && !empty($appointment['priority_number'])): ?>
                                            <div class="mt-4 pl-7">
                                                <div class="bg-gradient-to-r from-white to-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm">
                                                    <!-- Updated Priority Number Display with Time Slot Info -->
                                                    <div class="mb-4">
                                                        <div class="text-center">
                                                            <div class="text-sm text-gray-600 mb-2 font-medium">Your Priority Number</div>
                                                            <div class="flex items-center justify-center gap-4">
                                                                <div class="flex items-center justify-center bg-gradient-to-r from-red-50 to-orange-50 border-2 border-red-300 rounded-xl px-8 py-6 shadow-lg">
                                                                    <div class="text-center">
                                                                        <div class="text-5xl text-red-600 font-black tracking-wider"><?= htmlspecialchars($appointment['priority_number'] ?? '--') ?></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="text-sm text-gray-600 mt-3">
                                                                <div class="flex items-center justify-center gap-4">
                                                                    <span class="flex items-center">
                                                                        <?= date('M d, Y', strtotime($appointment['date'])) ?>
                                                                    </span>
                                                                    <span class="flex items-center">
                                                                        <?= date('h:i A', strtotime($appointment['start_time'])) ?>
                                                                    </span>
                                                                </div>
                                                                <div class="mt-2 text-xs text-blue-600 font-medium">
                                                                    <i class="fas fa-info-circle mr-1"></i>
                                                                    This number is specific to your time slot
                                                                </div>
                                                            </div>
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
                                        <?php elseif ($appointment['display_status'] === 'approved' && empty($appointment['priority_number'])): ?>
                                            <div class="mt-2 pl-7">
                                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 rounded text-sm">
                                                    Priority number will be available after assignment.
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Rescheduled Notice -->
                                        <?php if ($appointment['display_status'] === 'rescheduled'): ?>
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
                                        <?php if ($appointmentTab === 'upcoming' && $appointment['display_status'] === 'pending'): 
                                            // Get current date and time
                                            $currentDateTime = new DateTime();
                                            $appointmentDateTime = new DateTime($appointment['date'] . ' ' . $appointment['start_time']);
                                            
                                            // Check if appointment slot is in the past
                                            $isPastAppointment = $appointmentDateTime < $currentDateTime;
                                        ?>
                                            <?php if (!$isPastAppointment): ?>
                                                <div class="mt-4 pl-7">
                                                    <button onclick="openCancelModal(<?= $appointment['id'] ?>, '<?= $appointment['display_status'] ?>', '<?= $appointment['date'] ?>', '<?= $appointment['start_time'] ?>')"
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
                                        <?php if ($appointmentTab === 'upcoming' && $appointment['display_status'] === 'approved'): ?>
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
                        <div class="profile-image-wrapper">
                            <?php if (!empty($userData['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($userData['profile_image']) ?>" 
                                     alt="Profile Image" 
                                     class="profile-image"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMTAwIiBjeT0iMTAwIiByPSIxMDAiIGZpbGw9IiNlNWU3ZWIiLz48cGF0aCBkPSJNMTAwIDExMEE3NSA3NSAwIDAxMTAwIDM1IDc1IDc1IDAgMDExMDAgMTEwek0xMDAgMTAwQTY1IDY1IDAgMDExMDAgMzUgNjUgNjUgMCAwMTEwMCAxMDB6IiBmaWxsPSIjOWNhM2FmIi8+PC9zdmc+'">
                            <?php else: ?>
                                <div class="flex flex-col items-center justify-center w-full h-full">
                                    <i class="fas fa-user text-6xl text-gray-400 mb-3"></i>
                                    <span class="text-gray-500 text-sm">No profile photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-3 text-center">
                            <?= !empty($userData['profile_image']) ? 'Current Profile Photo' : 'No Profile Photo Set' ?>
                        </p>
                    </div>
                    
                    <!-- Profile Image Actions -->
                    <div class="flex-1">
                        <div class="space-y-6">
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3 text-lg">Profile Photo Settings</h4>
                                <p class="text-gray-600 mb-4">
                                    Upload a clear photo of yourself to help our health staff recognize you during appointments. 
                                    This improves your experience at the health center.
                                </p>
                                
                                <!-- Upload Area -->
                                <div class="profile-upload-area" id="upload-area" ondragover="handleDragOver(event)" ondrop="handleDrop(event)" ondragleave="handleDragLeave(event)">
                                    <div class="mb-4">
                                        <i class="fas fa-cloud-upload-alt text-4xl text-blue-500 mb-3"></i>
                                        <p class="text-gray-700 font-medium mb-2">Drag & drop your photo here</p>
                                        <p class="text-gray-500 text-sm">or click to browse files</p>
                                    </div>
                                    
                                    <div class="file-input-wrapper">
                                        <input type="file" id="profile_image" name="profile_image" 
                                               accept="image/jpeg,image/png,image/gif" 
                                               class="file-input"
                                               onchange="previewImage(this)">
                                        <label for="profile_image" class="file-input-label">
                                            <i class="fas fa-folder-open"></i> Browse Files
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-3">Supported formats: JPG, PNG, GIF. Max size: 5MB</p>
                                </div>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-6">
                            <!-- Personal Info Card -->
                            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                                <h3 class="font-semibold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-id-card text-blue-500 mr-2"></i>
                                    Personal Information
                                </h3>
                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div class="bg-blue-50 p-2 rounded-lg mr-3">
                                            <i class="fas fa-user text-blue-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Full Name</p>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($userData['full_name']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-start">
                                        <div class="bg-blue-50 p-2 rounded-lg mr-3">
                                            <i class="fas fa-birthday-cake text-blue-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Age</p>
                                            <p class="font-medium text-gray-800"><?= $userData['age'] ? htmlspecialchars($userData['age']) : 'N/A' ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-start">
                                        <div class="bg-blue-50 p-2 rounded-lg mr-3">
                                            <i class="fas fa-phone text-blue-500"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">Contact Number</p>
                                            <p class="font-medium text-gray-800"><?= $userData['contact'] ? htmlspecialchars($userData['contact']) : 'N/A' ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Status Card -->
                            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                                <h3 class="font-semibold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-shield-alt text-green-500 mr-2"></i>
                                    Account Status
                                </h3>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-gray-500">Verification Status</p>
                                        <p class="font-medium <?= $userData['approved'] ? 'text-green-600' : 'text-yellow-600' ?>">
                                            <?= $userData['approved'] ? 'Verified Account' : 'Pending Verification' ?>
                                        </p>
                                    </div>
                                    <div class="bg-<?= $userData['approved'] ? 'green' : 'yellow' ?>-100 text-<?= $userData['approved'] ? 'green' : 'yellow' ?>-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <?= $userData['approved'] ? 'Active' : 'Pending' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Address Card -->
                            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm h-full">
                                <h3 class="font-semibold text-gray-800 mb-4 text-lg flex items-center">
                                    <i class="fas fa-home text-purple-500 mr-2"></i>
                                    Address Information
                                </h3>
                                <div class="flex items-start">
                                    <div class="bg-purple-50 p-2 rounded-lg mr-3">
                                        <i class="fas fa-map-marker-alt text-purple-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 mb-1">Complete Address</p>
                                        <p class="font-medium text-gray-800"><?= $userData['address'] ? htmlspecialchars($userData['address']) : 'N/A' ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Actions Card -->
                            <div class="info-card">
                                <h3 class="text-white mb-4 text-lg font-semibold flex items-center">
                                    <i class="fas fa-bolt mr-2"></i>
                                    Quick Actions
                                </h3>
                                <div class="space-y-3">
                                    <p class="text-white text-sm">
                                        <i class="fas fa-calendar-check mr-2"></i>
                                        Book a new appointment
                                    </p>
                                    <p class="text-white text-sm">
                                        <i class="fas fa-bell mr-2"></i>
                                        View notifications
                                    </p>
                                    <p class="text-white text-sm">
                                        <i class="fas fa-question-circle mr-2"></i>
                                        Get help & support
                                    </p>
                                </div>
                                <div class="mt-6">
                                    <a href="?tab=appointments" class="bg-white text-blue-600 px-6 py-3 rounded-full font-semibold hover:bg-gray-100 transition duration-200 inline-flex items-center action-button">
                                        <i class="fas fa-plus mr-2"></i>
                                        Schedule Appointment
                                    </a>
                                </div>
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
                                case 'missed':
                                    $statusConfig = [
                                        'class' => 'status-missed',
                                        'bg' => 'bg-purple-50',
                                        'border' => 'border-purple-200'
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
    // Enhanced Notification Functions
    function showSuccessNotification(notification) {
        const overlay = document.getElementById('success-modal-overlay');
        const modal = document.getElementById('success-modal');
        const title = document.getElementById('success-title');
        const message = document.getElementById('success-message');
        const detailsContainer = document.getElementById('success-details');
        const detailsContent = document.getElementById('success-details-content');
        const closeBtn = document.getElementById('success-close-btn');
        
        // Set modal content
        title.textContent = notification.title || 'Success!';
        message.textContent = notification.message || '';
        
        // Show details if available
        if (notification.details && Object.keys(notification.details).length > 0) {
            detailsContainer.style.display = 'block';
            detailsContent.innerHTML = '';
            
            for (const [key, value] of Object.entries(notification.details)) {
                const detailItem = document.createElement('div');
                detailItem.className = 'notification-detail-item';
                detailItem.innerHTML = `
                    <span class="notification-detail-label">${key}</span>
                    <span class="notification-detail-value">${value}</span>
                `;
                detailsContent.appendChild(detailItem);
            }
        } else {
            detailsContainer.style.display = 'none';
        }
        
        // Show modal with animation
        overlay.classList.add('active');
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideSuccessNotification();
        }, 5000);
    }
    
    function hideSuccessNotification() {
        const overlay = document.getElementById('success-modal-overlay');
        const modal = document.getElementById('success-modal');
        
        modal.classList.remove('active');
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }
    
    function showCancellationNotification(notification) {
        const overlay = document.getElementById('cancellation-modal-overlay');
        const modal = document.getElementById('cancellation-modal');
        const title = document.getElementById('cancellation-title');
        const message = document.getElementById('cancellation-message');
        const detailsContainer = document.getElementById('cancellation-details');
        const detailsContent = document.getElementById('cancellation-details-content');
        const closeBtn = document.getElementById('cancellation-close-btn');
        
        // Set modal content
        title.textContent = notification.title || 'Cancelled';
        message.textContent = notification.message || '';
        
        // Show details if available
        if (notification.details && Object.keys(notification.details).length > 0) {
            detailsContainer.style.display = 'block';
            detailsContent.innerHTML = '';
            
            for (const [key, value] of Object.entries(notification.details)) {
                const detailItem = document.createElement('div');
                detailItem.className = 'notification-detail-item';
                detailItem.innerHTML = `
                    <span class="notification-detail-label">${key}</span>
                    <span class="notification-detail-value">${value}</span>
                `;
                detailsContent.appendChild(detailItem);
            }
        } else {
            detailsContainer.style.display = 'none';
        }
        
        // Show modal with animation
        overlay.classList.add('active');
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideCancellationNotification();
        }, 5000);
    }
    
    function hideCancellationNotification() {
        const overlay = document.getElementById('cancellation-modal-overlay');
        const modal = document.getElementById('cancellation-modal');
        
        modal.classList.remove('active');
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }
    
    function showGenericNotification(notification) {
        const overlay = document.getElementById('notification-modal-overlay');
        const modal = document.getElementById('notification-modal');
        const icon = document.querySelector('#notification-modal .notification-icon i');
        const title = document.getElementById('notification-title');
        const message = document.getElementById('notification-message');
        const detailsContainer = document.getElementById('notification-details');
        const detailsContent = document.getElementById('notification-details-content');
        const closeBtn = document.getElementById('notification-close-btn');
        
        // Set modal content based on notification type
        title.textContent = notification.title || 'Notification';
        message.textContent = notification.message || '';
        
        // Set icon based on type
        if (notification.icon) {
            icon.className = notification.icon;
        }
        
        // Show details if available
        if (notification.details && Object.keys(notification.details).length > 0) {
            detailsContainer.style.display = 'block';
            detailsContent.innerHTML = '';
            
            for (const [key, value] of Object.entries(notification.details)) {
                const detailItem = document.createElement('div');
                detailItem.className = 'notification-detail-item';
                detailItem.innerHTML = `
                    <span class="notification-detail-label">${key}</span>
                    <span class="notification-detail-value">${value}</span>
                `;
                detailsContent.appendChild(detailItem);
            }
        } else {
            detailsContainer.style.display = 'none';
        }
        
        // Show modal with animation
        overlay.classList.add('active');
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);
        
        // Auto-hide after 5 seconds for success messages
        if (notification.type === 'success') {
            setTimeout(() => {
                hideGenericNotification();
            }, 5000);
        }
    }
    
    function hideGenericNotification() {
        const overlay = document.getElementById('notification-modal-overlay');
        const modal = document.getElementById('notification-modal');
        
        modal.classList.remove('active');
        setTimeout(() => {
            overlay.classList.remove('active');
        }, 300);
    }
    
    // Close notification when clicking outside
    document.getElementById('success-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            hideSuccessNotification();
        }
    });
    
    document.getElementById('cancellation-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            hideCancellationNotification();
        }
    });
    
    document.getElementById('notification-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            hideGenericNotification();
        }
    });
    
    // Close notification with buttons
    document.getElementById('success-close-btn').addEventListener('click', hideSuccessNotification);
    document.getElementById('cancellation-close-btn').addEventListener('click', hideCancellationNotification);
    document.getElementById('notification-close-btn').addEventListener('click', hideGenericNotification);

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

    // Function to select slot and scroll to booking button
    function selectSlot(slotId) {
        const radio = document.getElementById('slot_' + slotId);
        if (radio && !radio.disabled) {
            radio.checked = true;
            // Find the booking button and scroll to it
            const bookingButton = document.querySelector('[onclick*="openBookingModal"]');
            if (bookingButton) {
                bookingButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    // Booking Modal Functions
    function openBookingModal(appointmentId, selectedDate, staffName, specialization, dateStr, startTime, endTime, availableSlots, maxSlots) {
        // Set form inputs
        document.getElementById('modal-appointment-id-input').value = appointmentId;
        document.getElementById('modal-selected-date-input').value = selectedDate;
        document.getElementById('modal-service-id-input').value = appointmentId;
        
        // Populate booking details
        const bookingDetails = document.getElementById('booking-details');
        bookingDetails.innerHTML = `
            <div class="space-y-4">
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <div>
                        <p class="font-medium text-gray-700">Health Worker</p>
                        <p class="text-gray-600">${staffName}${specialization ? ' (' + specialization + ')' : ''}</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <div>
                        <p class="font-medium text-gray-700">Date & Time</p>
                        <p class="text-gray-600">${dateStr} at ${startTime} - ${endTime}</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <div>
                        <p class="font-medium text-gray-700">Availability</p>
                        <p class="text-gray-600">${availableSlots} slot${availableSlots > 1 ? 's' : ''} remaining (out of ${maxSlots})</p>
                    </div>
                </div>
            </div>
        `;
        
        // Reset form
        document.getElementById('booking-form').reset();
        document.getElementById('modal-other_concern_container').style.display = 'none';
        document.getElementById('health-concerns-error').classList.add('hidden');
        
        // Show modal
        document.getElementById('booking-modal').classList.remove('hidden');
    }

    function closeBookingModal() {
        document.getElementById('booking-modal').classList.add('hidden');
    }

    function validateHealthConcerns() {
        const checkboxes = document.querySelectorAll('#booking-form input[name="health_concerns[]"]:checked');
        const errorElement = document.getElementById('health-concerns-error');
        
        if (checkboxes.length === 0) {
            errorElement.classList.remove('hidden');
            return false;
        }

        const otherChecked = document.getElementById("modal-other_concern").checked;
        const otherInput = document.getElementById("modal-other_concern_specify").value.trim();

        if (otherChecked && otherInput === "") {
            showGenericNotification({
                type: 'error',
                title: 'Health Concern Required',
                message: 'Please specify your other health concern.',
                icon: 'fas fa-heartbeat',
                details: {}
            });
            return false;
        }

        errorElement.classList.add('hidden');
        return true;
    }

    // Toggle Other field visibility in modal
    document.getElementById("modal-other_concern").addEventListener("change", function () {
        document.getElementById("modal-other_concern_container").style.display = this.checked ? "block" : "none";
    });

    // Enhanced cancellation functions - ONLY FOR PENDING APPOINTMENTS
    function openCancelModal(appointmentId, status, date, startTime) {
        const currentDateTime = new Date();
        const appointmentDateTime = new Date(date + ' ' + startTime);
        
        // Check if appointment slot is in the past based on real-time
        if (appointmentDateTime < currentDateTime) {
            showGenericNotification({
                type: 'error',
                title: 'Cannot Cancel',
                message: 'Past appointments cannot be cancelled.',
                icon: 'fas fa-calendar-times',
                details: {}
            });
            return;
        }
        
        // Only allow cancellation for pending appointments
        if (status !== 'pending') {
            showGenericNotification({
                type: 'error',
                title: 'Cannot Cancel',
                message: 'Only pending appointments can be cancelled. Approved appointments require staff assistance.',
                icon: 'fas fa-exclamation-triangle',
                details: {}
            });
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

    // Drag and drop functions for profile upload
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('upload-area').classList.add('dragover');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('upload-area').classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('upload-area').classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                const input = document.getElementById('profile_image');
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;
                previewImage(input);
            } else {
                showGenericNotification({
                    type: 'error',
                    title: 'Invalid File',
                    message: 'Please drop an image file (JPG, PNG, GIF)',
                    icon: 'fas fa-file-image',
                    details: {}
                });
            }
        }
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

        // Initialize drag and drop for profile upload
        const uploadArea = document.getElementById('upload-area');
        if (uploadArea) {
            uploadArea.addEventListener('click', function() {
                document.getElementById('profile_image').click();
            });
        }
        
        // Add spinner functionality to Find Availability button
        const findAvailabilityBtn = document.getElementById('find-availability-btn');
        if (findAvailabilityBtn) {
            const appointmentForm = document.getElementById('appointment-form');
            appointmentForm.addEventListener('submit', function() {
                // Show spinner and hide search icon
                const searchIcon = document.getElementById('search-icon');
                const loadingSpinner = document.getElementById('loading-spinner');
                const btnText = document.getElementById('btn-text');
                
                if (searchIcon && loadingSpinner && btnText) {
                    searchIcon.classList.add('hidden');
                    loadingSpinner.classList.remove('hidden');
                    btnText.textContent = 'Finding Availability...';
                    findAvailabilityBtn.disabled = true;
                }
            });
        }
        
        // Check for session notification on page load
        <?php if (isset($_SESSION['notification'])): ?>
            <?php $notification = $_SESSION['notification']; ?>
            <?php if ($notification['type'] === 'success' && strpos($notification['title'], 'Appointment Booked') !== false): ?>
                showSuccessNotification(<?= json_encode($notification) ?>);
            <?php elseif ($notification['type'] === 'success' && strpos($notification['title'], 'Appointment Cancelled') !== false): ?>
                showCancellationNotification(<?= json_encode($notification) ?>);
            <?php else: ?>
                showGenericNotification(<?= json_encode($notification) ?>);
            <?php endif; ?>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const cancelModal = document.getElementById('cancel-modal');
        if (event.target === cancelModal) {
            closeCancelModal();
        }
        
        const bookingModal = document.getElementById('booking-modal');
        if (event.target === bookingModal) {
            closeBookingModal();
        }
        
        const contactModal = document.getElementById('contact-info-modal');
        if (event.target === contactModal) {
            closeContactModal();
        }
        
        const profileImageModal = document.getElementById('profile-image-modal');
        if (event.target === profileImageModal) {
            closeProfileImageModal();
        }
        
        const helpModal = document.getElementById('helpModal');
        if (event.target === helpModal) {
            closeHelpModal();
        }
        
        const successModal = document.getElementById('success-modal-overlay');
        if (event.target === successModal) {
            hideSuccessNotification();
        }
        
        const cancellationModal = document.getElementById('cancellation-modal-overlay');
        if (event.target === cancellationModal) {
            hideCancellationNotification();
        }
        
        const genericModal = document.getElementById('notification-modal-overlay');
        if (event.target === genericModal) {
            hideGenericNotification();
        }
    }

    // Add this function to handle copying contact info
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            showGenericNotification({
                type: 'success',
                title: 'Copied!',
                message: 'Text copied to clipboard',
                icon: 'fas fa-copy',
                details: {}
            });
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
        });
    }
    </script>

</body>
</html>