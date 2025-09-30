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
$activeTab = $_GET['tab'] ?? 'dashboard';

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
                              WHERE ua.user_id = ? AND ua.status IN ('pending', 'approved') AND a.date >= CURDATE()");
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

// -------------------------------------------------
// Handle appointment booking (with health info + consent)
// MODIFIED: Enhanced validation to prevent booking past or fully booked slots
// AND prevent multiple appointments on the same day
// -------------------------------------------------
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
                // Check if the time slot is still available (any user, any status)
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

                // NEW: Check if user already has an appointment on the same day (any time slot)
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

// Function to automatically reschedule expired appointments for users
function rescheduleUserExpiredAppointments($pdo, $userId) {
    $currentDateTime = date('Y-m-d H:i:s');
    
    // Find all pending appointments that have passed for this user
    $stmt = $pdo->prepare("
        SELECT ua.*, a.date, a.start_time, a.end_time, a.staff_id
        FROM user_appointments ua 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id 
        WHERE ua.user_id = ? AND ua.status = 'pending' 
        AND (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW())))
    ");
    $stmt->execute([$userId]);
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
                        rescheduled_count = COALESCE(rescheduled_count, 0) + 1,
                        status = 'rescheduled'
                    WHERE id = ? AND user_id = ?
                ");
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE user_appointments 
                    SET appointment_id = ?, rescheduled_from = ?, rescheduled_at = NOW(),
                        status = 'rescheduled'
                    WHERE id = ? AND user_id = ?
                ");
            }
            
            $updateStmt->execute([$newSlotId, $appointment['appointment_id'], $appointment['id'], $userId]);
            $rescheduledCount++;
            
        } catch (PDOException $e) {
            error_log("Error rescheduling appointment: " . $e->getMessage());
        }
    }
    
    return $rescheduledCount;
}

// Call this function to reschedule expired appointments for the current user
$rescheduledCount = rescheduleUserExpiredAppointments($pdo, $userId);
if ($rescheduledCount > 0) {
    error_log("Auto-rescheduled $rescheduledCount expired appointments for user $userId");
}

// ... [rest of your existing code continues]

// Modify the appointment fetching logic to include rescheduled appointments
try {
    $query = "
        SELECT ua.*, a.date, a.start_time, a.end_time, s.full_name as staff_name, s.specialization,
               -- Check if appointment is in the past and still pending (should be rescheduled)
               (a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW()))) as is_past
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        JOIN sitio1_staff s ON a.staff_id = s.id
        WHERE ua.user_id = ?
    ";
    
    if ($appointmentTab === 'upcoming') {
        $query .= " AND ua.status IN ('pending', 'approved') AND (a.date > CURDATE() OR (a.date = CURDATE() AND a.end_time > TIME(NOW())))";
    } elseif ($appointmentTab === 'past') {
        $query .= " AND (ua.status = 'completed' OR a.date < CURDATE() OR (a.date = CURDATE() AND a.end_time < TIME(NOW())))";
    } elseif ($appointmentTab === 'cancelled') {
        $query .= " AND ua.status = 'cancelled'";
    }
    
    $query .= " ORDER BY a.date " . ($appointmentTab === 'past' ? 'DESC' : 'ASC') . ", a.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for appointments that should be rescheduled (past but still pending)
    foreach ($appointments as &$appointment) {
        if ($appointment['status'] === 'pending' && $appointment['is_past']) {
            $appointment['status'] = 'rescheduled';
        }
    }
    
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
}

// -------------------------------------------------
// Handle appointment cancellation
// -------------------------------------------------
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
    
    try {
        // Check if appointment belongs to user and is cancellable
        $stmt = $pdo->prepare("
            SELECT ua.id 
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE ua.id = ? AND ua.user_id = ? 
            AND ua.status IN ('pending', 'approved')
            AND a.date >= CURDATE()
        ");
        $stmt->execute([$userAppointmentId, $userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Appointment cannot be cancelled. It may have already been completed, cancelled, or the date has passed.');
        }
        
        // Update with cancellation reason and timestamp
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'cancelled', 
                cancel_reason = ?,
                cancelled_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$cancelReason, $userAppointmentId, $userId]);
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Appointment cancelled successfully'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (Exception $e) {
        $error = 'Error cancelling appointment: ' . $e->getMessage();
    }
}

// -------------------------------------------------
// Get available dates with slots and staff information
// MODIFIED: Only show slots that have available capacity and are not in the past
// FIXED: Added proper check for fully booked slots
// ENHANCED: Check if user already has appointment on each date
// -------------------------------------------------
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
        AND a.date >= CURDATE()
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

// -------------------------------------------------
// Get counts for each appointment tab - STATUS-BASED VERSION
// -------------------------------------------------
$appointmentCounts = [
    'upcoming' => 0,
    'past' => 0,
    'cancelled' => 0
];

try {
    // Upcoming: Count pending and approved appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        WHERE ua.user_id = ? 
        AND ua.status IN ('pending', 'approved')
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
    
} catch (PDOException $e) {
    $error = 'Error fetching appointment counts: ' . $e->getMessage();
}

// -------------------------------------------------
// Get user's appointments - STATUS-BASED VERSION
// -------------------------------------------------
$appointments = [];

try {
    $query = "
        SELECT ua.*, a.date, a.start_time, a.end_time, s.full_name as staff_name, s.specialization
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        JOIN sitio1_staff s ON a.staff_id = s.id
        WHERE ua.user_id = ?
    ";
    
    if ($appointmentTab === 'upcoming') {
        // Show only approved and pending appointments
        $query .= " AND ua.status IN ('pending', 'approved')";
    } elseif ($appointmentTab === 'past') {
        // Show only completed appointments
        $query .= " AND ua.status = 'completed'";
    } elseif ($appointmentTab === 'cancelled') {
        // Show only cancelled appointments
        $query .= " AND ua.status = 'cancelled'";
    }
    
    // Order by date (newest first for past appointments)
    $query .= " ORDER BY a.date " . ($appointmentTab === 'past' ? 'DESC' : 'ASC') . ", a.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
}

// Handle invoice download
if (isset($_GET['download_invoice']) && is_numeric($_GET['download_invoice'])) {
    $userAppointmentId = intval($_GET['download_invoice']);
    
    // Verify the user owns this appointment
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, u.address,
                          a.date, a.start_time, a.end_time,
                          s.full_name as staff_name, s.specialization, s.license_number
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          JOIN sitio1_staff s ON a.staff_id = s.id 
                          WHERE ua.id = ? AND ua.user_id = ?");
    $stmt->execute([$userAppointmentId, $_SESSION['user']['id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment && !empty($appointment['invoice_number'])) {
        // Generate PDF invoice
        // Check if TCPDF is available via Composer
        $tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
            
            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Community Health Tracker');
            $pdf->SetAuthor('Community Health Tracker');
            $pdf->SetTitle('Invoice #' . $appointment['invoice_number']);
            $pdf->SetSubject('Appointment Invoice');
            
            // Add a page
            $pdf->AddPage();
            
            // Set content
            $html = '
                <h1 style="text-align: center; color: #3b82f6;">Community Health Tracker</h1>
                <h2 style="text-align: center; color: #6b7280;">Appointment Invoice</h2>
                
                <table border="0" cellpadding="5">
                    <tr>
                        <td width="30%"><strong>Invoice Number:</strong></td>
                        <td width="70%">' . $appointment['invoice_number'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Issue Date:</strong></td>
                        <td>' . date('M d, Y') . '</td>
                    </tr>
                    <tr>
                        <td><strong>Priority Number:</strong></td>
                        <td>' . $appointment['priority_number'] . '</td>
                    </tr>
                </table>
                
                <h3 style="color: #3b82f6; margin-top: 20px;">Appointment Details</h3>
                <table border="0" cellpadding="5">
                    <tr>
                        <td width="30%"><strong>Patient Name:</strong></td>
                        <td width="70%">' . $appointment['full_name'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Contact:</strong></td>
                        <td>' . $appointment['contact'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td>' . $appointment['address'] . '</td>
                    </tr>
                    <tr>
                        <td><strong>Appointment Date:</strong></td>
                        <td>' . date('M d, Y', strtotime($appointment['date'])) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Appointment Time:</strong></td>
                        <td>' . date('h:i A', strtotime($appointment['start_time'])) . ' - ' . date('h:i A', strtotime($appointment['end_time'])) . '</td>
                    </tr>
                    <tr>
                        <td><strong>Health Worker:</strong></td>
                        <td>' . $appointment['staff_name'] . ' (' . $appointment['specialization'] . ')</td>
                    </tr>
                    <tr>
                        <td><strong>License Number:</strong></td>
                        <td>' . $appointment['license_number'] . '</td>
                    </tr>
                </table>
                
                <h3 style="color: #3b82f6; margin-top: 20px;">Health Concerns</h3>
                <p>' . $appointment['health_concerns'] . '</p>
                
                <h3 style="color: #3b82f6; margin-top: 20px;">Notes</h3>
                <p>' . ($appointment['notes'] ?: 'No additional notes') . '</p>
                
                <hr style="margin: 20px 0;">
                <p style="text-align: center; color: #6b7280;">Thank you for choosing Community Health Tracker.</p>
            ';
            
            // Output HTML content
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Close and output PDF document
            $pdf->Output('invoice_' . $appointment['invoice_number'] . '.pdf', 'D');
            exit;
        } else {
            // Fallback to simple text invoice if TCPDF not available
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="invoice_' . $appointment['invoice_number'] . '.txt"');
            
            echo "Community Health Tracker\n";
            echo "Appointment Invoice\n\n";
            echo "Invoice Number: " . $appointment['invoice_number'] . "\n";
            echo "Issue Date: " . date('M d, Y') . "\n";
            echo "Priority Number: " . $appointment['priority_number'] . "\n\n";
            
            echo "Appointment Details:\n";
            echo "Patient Name: " . $appointment['full_name'] . "\n";
            echo "Contact: " . $appointment['contact'] . "\n";
            echo "Address: " . $appointment['address'] . "\n";
            echo "Appointment Date: " . date('M d, Y', strtotime($appointment['date'])) . "\n";
            echo "Appointment Time: " . date('h:i A', strtotime($appointment['start_time'])) . " - " . date('h:i A', strtotime($appointment['end_time'])) . "\n";
            echo "Health Worker: " . $appointment['staff_name'] . " (" . $appointment['specialization'] . ")\n";
            echo "License Number: " . $appointment['license_number'] . "\n\n";
            
            echo "Health Concerns:\n" . $appointment['health_concerns'] . "\n\n";
            echo "Notes:\n" . ($appointment['notes'] ?: 'No additional notes') . "\n\n";
            
            echo "Thank you for choosing Community Health Tracker.\n";
            exit;
        }
    } else {
        // Redirect if invoice doesn't exist or user doesn't have permission
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Invoice not found or you do not have permission to access it.'
        ];
        header('Location: /community-health-tracker/user/dashboard.php?tab=appointments');
        exit;
    }
}
?>

<style>
/* Add to your CSS */
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
</style>

<div class="container mx-auto px-4">
    <!-- Success Modal -->
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
                    <button type="button" onclick="hideModal('success')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this modal code near the top of your file, with the other modals -->
<div id="booking-success-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="booking-success-modal-backdrop"></div>
    <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-md w-full opacity-0 scale-95" id="booking-success-modal-content">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h3 class="mt-3 text-lg font-medium text-gray-900">Successfully Booked!</h3>
            <div class="mt-2 px-4 py-3">
                <p class="text-sm text-gray-500">Your appointment for consultation has been successfully scheduled.</p>
            </div>
            <div class="mt-4">
                <button type="button" onclick="hideModal('booking-success')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                    Continue
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add this JavaScript function to handle the modal -->
<script>
function showBookingSuccessModal() {
    const modal = document.getElementById('booking-success-modal');
    const backdrop = document.getElementById('booking-success-modal-backdrop');
    const content = document.getElementById('booking-success-modal-content');
    
    modal.classList.remove('hidden');
    
    setTimeout(() => {
        backdrop.classList.add('opacity-100');
        content.classList.add('opacity-100', 'scale-100');
    }, 10);
}

// Add this to your existing hideModal function
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

// Modify your appointment booking success handling to show the modal
<?php if (isset($_SESSION['notification']) && $_SESSION['notification']['type'] === 'success' && strpos($_SESSION['notification']['message'], 'booked') !== false): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        showBookingSuccessModal();
    }, 500);
});
<?php endif; ?>
</script>

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
                    <button type="button" onclick="hideModal('error')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
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
                        <button type="button" onclick="hideModal('session')" class="px-4 py-2 <?= $_SESSION['notification']['type'] === 'success' ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500' ?> text-white rounded-md focus:outline-none focus:ring-2">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['notification']); ?>
    <?php endif; ?>

    <h1 class="text-2xl font-bold mb-6 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        User Dashboard
    </h1>

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

    <!-- Main Tabs -->
    <div class="flex border-b border-gray-200 mb-6">
        <a href="?tab=dashboard" class="<?= $activeTab === 'dashboard' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin-round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Dashboard
        </a>
        <a href="?tab=appointments" class="<?= $activeTab === 'appointments' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Appointments
        </a>
    </div>

    <!-- Dashboard Tab Content -->
    <div class="tab-content <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
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
            
            <div class="bg-white p-6 rounded-lg shadow">
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
            
            <div class="bg-white p-6 rounded-lg shadow">
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
        
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Your Information</h2>
            
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
        
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Recent Activities</h2>
            <div class="space-y-4">
                <?php if (!empty($recentActivities)): ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item <?= $activity['status'] ?>">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium text-gray-800"><?= htmlspecialchars($activity['title']) ?></h4>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($activity['description']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php if ($activity['type'] === 'appointment'): ?>
                                            <?= date('M d, Y', strtotime($activity['date'])) ?>
                                        <?php else: ?>
                                            <?= date('M d, Y h:i A', strtotime($activity['created_at'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="px-2 py-1 text-xs rounded-full 
                                    <?= $activity['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                       ($activity['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                       ($activity['status'] === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>">
                                    <?= ucfirst($activity['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-4">No recent activities found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Appointments Tab Content -->
    <div class="tab-content <?= $activeTab === 'appointments' ? 'active' : '' ?>">
        <!-- Enhanced Tabs with Counts and Icons -->
<div class="flex border-b border-gray-200 mb-6">
    <a href="?tab=appointments&appointment_tab=upcoming" class="<?= $appointmentTab === 'upcoming' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Upcoming
        <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $appointmentCounts['upcoming'] ?></span>
    </a>
    <a href="?tab=appointments&appointment_tab=past" class="<?= $appointmentTab === 'past' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 01118 0z" />
        </svg>
        Completed
        <span class="ml-1 bg-green-100 text-green-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $appointmentCounts['past'] ?></span>
    </a>
    <a href="?tab=appointments&appointment_tab=cancelled" class="<?= $appointmentTab === 'cancelled' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Cancelled
        <span class="ml-1 bg-red-100 text-red-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $appointmentCounts['cancelled'] ?></span>
    </a>
</div>

        <div class="flex flex-col md:flex-row gap-6">
            <!-- Left Side - Schedule Health Check-up -->
            <div class="md:w-1/2">
                <div id="book-appointment" class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
                                       class="border rounded-lg p-2 text-center transition <?= ($_GET['date'] ?? '') === $date ? 'border-blue-500 bg-blue-50' : ($userHasAppointment ? 'date-disabled border-gray-200 bg-gray-100 text-gray-500' : ($hasAvailableSlots ? 'border-gray-200 hover:bg-blue-50' : 'border-gray-200 bg-gray-100 text-gray-500 cursor-not-allowed')) ?>">
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
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
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
                            <div class="border border-gray-200 rounded-lg p-6 mb-6">
                                <h3 class="font-semibold text-lg mb-4 flex items-center">
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
                                            class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition flex items-center justify-center" 
                                            <?= $selectedSlot['available_slots'] <= 0 ? 'disabled' : '' ?>>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <?= $selectedSlot['available_slots'] > 0 ? 'Book Appointment' : 'Slot Full' ?>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="bg-red-100 border border-red 400 text-red-700 px-4 py-3 rounded flex items-center">
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
            <div class="md:w-1/2">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
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
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Cancelled Appointments
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
                            <!-- In the appointments display section -->
<?php foreach ($appointments as $appointment): ?>
    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
        <div class="flex justify-between items-start">
            <div class="flex items-start">
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
                    
                    <?php if (!empty($appointment['invoice_number'])): ?>
                        <p class="text-sm text-blue-600 mt-1">
                            <strong>Invoice #:</strong> <?= htmlspecialchars($appointment['invoice_number']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($appointment['priority_number'])): ?>
                        <p class="text-sm text-green-600">
                            <strong>Priority #:</strong> <?= htmlspecialchars($appointment['priority_number']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <span class="px-2 py-1 text-xs rounded-full 
                <?= $appointment['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                   ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                   ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                <?= ucfirst($appointment['status']) ?>
            </span>
        </div>
                                    
                                    <?php if (!empty($appointment['health_concerns'])): ?>
                                        <div class="mt-3 pl-7">
                                            <p class="text-sm text-gray-700">
                                                <span class="font-medium">Health Concerns:</span> <?= htmlspecialchars($appointment['health_concerns']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="mt-2 pl-7">
                                            <p class="text-sm text-gray-700">
                                                <span class="font-medium">Notes:</span> <?= htmlspecialchars($appointment['notes']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointmentTab === 'cancelled' && !empty($appointment['cancel_reason'])): ?>
                                        <div class="mt-2 pl-7">
                                            <p class="text-sm text-gray-700">
                                                <span class="font-medium">Cancellation Reason:</span> <?= htmlspecialchars($appointment['cancel_reason']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointmentTab === 'cancelled' && !empty($appointment['cancelled_at'])): ?>
                                        <div class="mt-2 pl-7">
                                            <p class="text-sm text-gray-700">
                                                <span class="font-medium">Cancelled On:</span> <?= date('M d, Y h:i A', strtotime($appointment['cancelled_at'])) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appointment['invoice_number'])): ?>
                                        <div class="mt-3 pl-7">
                                            <button onclick="downloadInvoice(<?= $appointment['id'] ?>)" 
                                                    class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-blue-200 flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Download Invoice
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($appointment['status'] === 'rescheduled'): ?>
            <div class="mt-2 pl-7">
                <p class="text-sm text-purple-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Your appointment has been moved to a future date. Please check back for the new schedule.
                </p>
            </div>
        <?php endif; ?>
                                    
                                    <?php if ($appointmentTab === 'upcoming' && $appointment['status'] !== 'cancelled'): ?>
                                        <div class="mt-3 pl-7">
                                            <button onclick="openCancelModal(<?= $appointment['id'] ?>)"
                                                    class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Cancel Appointment
                                            </button>
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
</div>

<script>
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
    
    // Also check if the status enum includes 'rescheduled'
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM user_appointments LIKE 'status'");
        $statusColumn = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($statusColumn && strpos($statusColumn['Type'], 'rescheduled') === false) {
            // Add rescheduled to the enum
            $pdo->exec("ALTER TABLE user_appointments MODIFY status ENUM('pending', 'approved', 'completed', 'cancelled', 'rescheduled') NOT NULL DEFAULT 'pending'");
            error_log("Added 'rescheduled' to status enum");
        }
    } catch (PDOException $e) {
        error_log("Error updating status enum: " . $e->getMessage());
    }
}

// Call this function to ensure columns exist
checkAndAddRescheduleColumns($pdo);
</script>

<!-- Cancellation Modal Template -->
<div id="cancel-modal-template" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
    <div class="bg-white rounded-lg shadow-xl transform transition-all max-w-md w-full">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Cancel Appointment</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="appointment_id" id="modal-appointment-id">
                
                <div>
                    <label for="cancel-reason" class="block text-sm font-medium text-gray-700 mb-1">
                        Reason for cancellation <span class="text-red-500">*</span>
                    </label>
                    <textarea id="cancel-reason" name="cancel_reason" rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        required placeholder="Please explain why you need to cancel this appointment"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeCancelModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Go Back
                    </button>
                    <button type="submit" name="cancel_appointment"
                        class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript function to handle download
function downloadInvoice(appointmentId) {
    window.location.href = '<?= $_SERVER['PHP_SELF'] ?>?tab=appointments&download_invoice=' + appointmentId;
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

function openCancelModal(appointmentId) {
    const modal = document.getElementById('cancel-modal-template');
    const clone = modal.cloneNode(true);
    clone.id = 'cancel-modal-active';
    clone.classList.remove('hidden');
    document.getElementById('modal-appointment-id').value = appointmentId;
    document.body.appendChild(clone);
}

function closeCancelModal() {
    const modal = document.getElementById('cancel-modal-active');
    if (modal) {
        modal.remove();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'cancel-modal-active') {
        closeCancelModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const dateSelect = document.getElementById('appointment_date');
    const slotSelect = document.getElementById('appointment_slot');
    
    // Load time slots immediately if date is preselected
    if (dateSelect && dateSelect.value && slotSelect) {
        loadTimeSlots(dateSelect.value);
    }
    
    dateSelect?.addEventListener('change', function() {
        const selectedDate = this.value;
        if (selectedDate && slotSelect) {
            loadTimeSlots(selectedDate);
        }
    });
    
    function loadTimeSlots(selectedDate) {
        slotSelect.innerHTML = '<option value="">Loading available slots...</option>';
        slotSelect.disabled = true;
        
        fetch(`/community-health-tracker/api/appointments.php?available=1&date=${selectedDate}`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);
            
            if (data.slots && data.slots.length > 0) {
                slotSelect.innerHTML = data.slots.map(slot => 
                    `<option value="${slot.id}" ${slot.booked_slots >= slot.max_slots ? 'disabled' : ''}>
                        ${slot.start_time} - ${slot.end_time} 
                        (${slot.max_slots - slot.booked_slots} slot${slot.max_slots - slot.booked_slots !== 1 ? 's' : ''} available)
                    </option>`
                ).join('');
                
                // Show visual feedback for available slots
                const availableSlots = data.slots.filter(slot => slot.booked_slots < slot.max_slots);
                if (availableSlots.length === 0) {
                    slotSelect.innerHTML = '<option value="">No available slots for this date</option>';
                }
            } else {
                slotSelect.innerHTML = '<option value="">No available slots for this date</option>';
            }
            slotSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            slotSelect.innerHTML = `<option value="">Error loading slots. Please try again.</option>`;
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

    // Enhanced modal control functions
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

// Handle form submission with modal feedback
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a specific error to show (like duplicate booking)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) {
        showModal('error', urlParams.get('error'));
    }
    
    // Handle appointment form submission
    const appointmentForm = document.querySelector('form[onsubmit="return validateHealthConcerns()"]');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            if (!validateHealthConcerns()) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Booking...';
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
    }
});
</script>