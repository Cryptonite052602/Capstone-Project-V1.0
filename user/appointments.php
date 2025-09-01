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
    } catch (PDOException $e) {
        $error = 'Error fetching statistics: ' . $e->getMessage();
    }
}

// APPOINTMENTS CODE
$appointmentTab = $_GET['appointment_tab'] ?? 'upcoming';

// Handle appointment booking (with health info + consent)
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
            $error = "You must select at least one health concern.";
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
                // Generate invoice number (timestamp + random digits)
                $invoiceNumber = 'INV-' . time() . '-' . rand(100, 999);
                
                // Generate priority number (date + sequential number)
                $priorityStmt = $pdo->prepare("SELECT COUNT(*) FROM user_appointments WHERE DATE(created_at) = CURDATE()");
                $priorityStmt->execute();
                $todayAppointments = $priorityStmt->fetchColumn();
                $priorityNumber = 'P-' . date('Ymd') . '-' . ($todayAppointments + 1);

                $stmt = $pdo->prepare("
                    INSERT INTO user_appointments 
                        (user_id, appointment_id, service_id, status, notes, health_concerns, 
                         service_type, consent, invoice_number, priority_number, created_at) 
                    VALUES 
                        (:user_id, :appointment_id, :service_id, 'pending', :notes, :health_concerns, 
                         :service_type, :consent, :invoice_number, :priority_number, NOW())
                ");

                $stmt->execute([
                    ':user_id'         => $userId,
                    ':appointment_id'  => $appointmentId,
                    ':service_id'      => $serviceId,
                    ':notes'           => $notes,
                    ':health_concerns' => $healthConcernsStr,
                    ':service_type'    => $serviceType,
                    ':consent'         => $consentGiven,
                    ':invoice_number'  => $invoiceNumber,
                    ':priority_number' => $priorityNumber
                ]);

                $_SESSION['notification'] = [
                    'type' => 'success',
                    'message' => 'Appointment booked successfully with consent given.'
                ];
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit();
            } catch (PDOException $e) {
                $error = 'Error booking appointment: ' . $e->getMessage();
            }
        }
    }
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointmentId = $_POST['appointment_id'];
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
        $stmt->execute([$appointmentId, $userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Appointment cannot be cancelled. It may have already been processed or is in the past.');
        }
        
        // Update with cancellation reason and timestamp
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'cancelled', 
                cancel_reason = ?,
                cancelled_at = NOW(),
                processed_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$cancelReason, $appointmentId, $userId]);
        
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Appointment cancelled successfully'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (Exception $e) {
        $error = 'Error cancelling appointment: ' . $e->getMessage();
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $error
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get available dates with slots and staff information
$availableDates = [];
$calendarDays = [];

try {
    // Get holidays
    $holidays = [];
    $holidayStmt = $pdo->query("SELECT date, description FROM sitio1_holidays WHERE date >= CURDATE()");
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->query("
        SELECT 
            a.date, 
            a.id as slot_id,
            a.start_time, 
            a.end_time,
            a.max_slots,
            s.full_name as staff_name,
            s.specialization,
            COUNT(ua.id) as booked_slots,
            (a.max_slots - COUNT(ua.id)) as available_slots
        FROM sitio1_appointments a
        JOIN sitio1_staff s ON a.staff_id = s.id
        LEFT JOIN user_appointments ua ON ua.appointment_id = a.id AND ua.status IN ('pending', 'approved')
        WHERE a.date >= CURDATE()
        GROUP BY a.id
        ORDER BY a.date, a.start_time
    ");
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($availableSlots as $slot) {
        $date = $slot['date'];
        if (!isset($availableDates[$date])) {
            $availableDates[$date] = [
                'date' => $date,
                'slots' => [],
                'total_slots' => 0,
                'available_slots' => 0,
                'is_holiday' => isset($holidays[$date])
            ];
        }
        $availableDates[$date]['slots'][] = $slot;
        $availableDates[$date]['total_slots'] += $slot['max_slots'];
        $availableDates[$date]['available_slots'] += $slot['available_slots'];
    }
    $availableDates = array_values($availableDates);
    
    // Generate calendar days for the current and next month
    $currentMonth = date('Y-m');
    $nextMonth = date('Y-m', strtotime('+1 month'));
    
    // Get first and last day of current month
    $firstDayCurrent = date('Y-m-01');
    $lastDayCurrent = date('Y-m-t');
    
    // Get first and last day of next month
    $firstDayNext = date('Y-m-01', strtotime('+1 month'));
    $lastDayNext = date('Y-m-t', strtotime('+1 month'));
    
    // Create calendar array
    $startDate = $firstDayCurrent;
    $endDate = $lastDayNext;
    
    $currentDate = $startDate;
    while ($currentDate <= $endDate) {
        $dateInfo = [
            'date' => $currentDate,
            'day' => date('j', strtotime($currentDate)),
            'month' => date('n', strtotime($currentDate)),
            'year' => date('Y', strtotime($currentDate)),
            'is_current_month' => date('Y-m', strtotime($currentDate)) === $currentMonth,
            'is_next_month' => date('Y-m', strtotime($currentDate)) === $nextMonth,
            'is_today' => $currentDate === date('Y-m-d'),
            'is_past' => $currentDate < date('Y-m-d'),
            'has_slots' => false,
            'is_holiday' => isset($holidays[$currentDate]),
            'slots_available' => 0,
            'slots_total' => 0
        ];
        
        // Check if this date has available slots
        foreach ($availableDates as $availDate) {
            if ($availDate['date'] === $currentDate) {
                $dateInfo['has_slots'] = true;
                $dateInfo['slots_available'] = $availDate['available_slots'];
                $dateInfo['slots_total'] = $availDate['total_slots'];
                break;
            }
        }
        
        $calendarDays[] = $dateInfo;
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }
} catch (PDOException $e) {
    $error = 'Error fetching available dates: ' . $e->getMessage();
}

// Get user's appointments
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
        $query .= " AND ua.status IN ('pending', 'approved') AND a.date >= CURDATE()";
    } elseif ($appointmentTab === 'past') {
        $query .= " AND (ua.status = 'completed' OR (a.date < CURDATE() AND ua.status NOT IN ('rejected', 'cancelled')))";
    } elseif ($appointmentTab === 'cancelled') {
        $query .= " AND ua.status IN ('rejected', 'cancelled')";
    }
    
    $query .= " ORDER BY a.date " . ($appointmentTab === 'past' ? 'DESC' : 'ASC') . ", a.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
}

// Handle invoice download
if (isset($_GET['download_invoice']) && is_numeric($_GET['download_invoice'])) {
    $appointmentId = intval($_GET['download_invoice']);
    
    // Verify the user owns this appointment
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.email, u.contact, u.address,
                          a.date, a.start_time, a.end_time,
                          s.full_name as staff_name, s.specialization, s.license_number
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          JOIN sitio1_staff s ON a.staff_id = s.id 
                          WHERE ua.id = ? AND ua.user_id = ?");
    $stmt->execute([$appointmentId, $_SESSION['user']['id']]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment && !empty($appointment['invoice_number'])) {
        // Generate PDF invoice
        require_once __DIR__ . '/../vendor/autoload.php'; // For TCPDF
        
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
        // Redirect if invoice doesn't exist or user doesn't have permission
        header('Location: /community-health-tracker/user/appointments.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Health Appointments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Calendar Styles - Redesigned */
        .calendar-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #3b82f6;
            color: white;
        }

        .calendar-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            color: white;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .calendar-nav button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .calendar-weekdays div {
            text-align: center;
            padding: 12px 0;
            font-weight: 600;
            color: #64748b;
            font-size: 0.875rem;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e2e8f0;
        }

        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 10px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .calendar-day.other-month {
            background: #f8fafc;
            color: #cbd5e1;
        }

        .calendar-day-number {
            align-self: flex-end;
            font-weight: 500;
            margin-bottom: 8px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .calendar-day.today .calendar-day-number {
            background: #3b82f6;
            color: white;
        }

        .calendar-day-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
        }

        .calendar-day.past {
            background: #f8fafc;
            color: #cbd5e1;
            cursor: not-allowed;
        }

        .calendar-day.past .calendar-day-button {
            display: none;
        }

        .calendar-day-button {
            margin-top: 8px;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .calendar-day-button.available {
            background: #10b981;
            color: white;
        }

        .calendar-day-button.available:hover {
            background: #059669;
        }

        .calendar-day-button.fully-booked {
            background: #ef4444;
            color: white;
            cursor: not-allowed;
        }

        .calendar-day-button.no-slots {
            background: #3b82f6;
            color: white;
            cursor: not-allowed;
        }

        .calendar-day.holiday {
            background: #fdf4ff;
        }

        .calendar-day.holiday .calendar-day-button {
            background: #a855f7;
            color: white;
            cursor: not-allowed;
        }

        .calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .legend-available {
            background: #10b981;
        }

        .legend-fully-booked {
            background: #ef4444;
        }

        .legend-no-slots {
            background: #3b82f6;
        }

        .legend-holiday {
            background: #a855f7;
        }

        /* Slot selection styling */
        .time-slots-container {
            margin-top: 20px;
        }

        .time-slot {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .time-slot.available {
            border-color: #10b981;
            background: #f0fdf4;
        }

        .time-slot.available:hover {
            background: #dcfce7;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .time-slot.unavailable {
            border-color: #fecaca;
            background: #fef2f2;
            opacity: 0.7;
        }

        .slot-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .slot-time {
            font-weight: 600;
            color: #1e293b;
        }

        .slot-staff {
            font-size: 0.875rem;
            color: #64748b;
        }

        .slot-availability {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .slot-availability.available {
            background: #dcfce7;
            color: #166534;
        }

        .slot-availability.unavailable {
            background: #fecaca;
            color: #991b1b;
        }

        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 500px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
        }

        /* Health concerns checkboxes */
        .health-concerns-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .health-concern-item {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Success Modal -->
        <div id="success-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="text-center p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <i class="fa-solid fa-check text-green-600 text-xl"></i>
                    </div>
                    <h3 class="mt-3 text-lg font-medium text-gray-900">Appointment Successfully Booked</h3>
                    <div class="mt-2 px-4 py-3">
                        <p class="text-sm text-gray-500" id="success-message"></p>
                    </div>
                    <div class="mt-4">
                        <button onclick="hideModal('success')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
        <div id="error-modal" class="modal-overlay">
            <div class="modal-content">
                <div class="text-center p-6">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <i class="fa-solid fa-xmark text-red-600 text-xl"></i>
                    </div>
                    <h3 class="mt-3 text-lg font-medium text-gray-900">You have an active appointment.</h3>
                    <div class="mt-2 px-4 py-3">
                        <p class="text-sm text-gray-500" id="error-message"></p>
                    </div>
                    <div class="mt-4">
                        <button onclick="hideModal('error')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Session Notification -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div id="session-modal" class="modal-overlay" style="display: flex;">
                <div class="modal-content">
                    <div class="text-center p-6">
                        <?php if ($_SESSION['notification']['type'] === 'success'): ?>
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                                <i class="fa-solid fa-check text-green-600 text-xl"></i>
                            </div>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">Success!</h3>
                        <?php else: ?>
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                <i class="fa-solid fa-xmark text-red-600 text-xl"></i>
                            </div>
                            <h3 class="mt-3 text-lg font-medium text-gray-900">Error!</h3>
                        <?php endif; ?>
                        <div class="mt-2 px-4 py-3">
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($_SESSION['notification']['message']) ?></p>
                        </div>
                        <div class="mt-4">
                            <button onclick="hideModal('session')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>

        <h1 class="text-2xl font-bold mb-6 flex items-center">
            <i class="fa-solid fa-calendar-check text-blue-600 mr-2 text-2xl"></i>
            Community Health Appointments
        </h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fa-solid fa-circle-check mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Enhanced Tabs with Counts and Icons -->
        <div class="flex border-b border-gray-200 mb-6">
            <a href="?tab=upcoming" class="<?= $activeTab === 'upcoming' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <i class="fa-solid fa-calendar-days mr-1"></i>
                Upcoming
                <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $stats['upcoming_appointments'] ?></span>
            </a>
            <a href="?tab=past" class="<?= $activeTab === 'past' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <i class="fa-solid fa-check-circle mr-1"></i>
                Completed
                <span class="ml-1 bg-green-100 text-green-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $stats['pending_consultations'] ?></span>
            </a>
            <a href="?tab=cancelled" class="<?= $activeTab === 'cancelled' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
                <i class="fa-solid fa-ban mr-1"></i>
                Cancelled
                <span class="ml-1 bg-red-100 text-red-800 text-xs font-semibold px-2 py-0.5 rounded-full">0</span>
            </a>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Left Side - Schedule Health Check-up -->
            <div class="lg:w-1/2">
                <div id="book-appointment" class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="calendar-wrapper">
                        <div class="calendar-header">
                            <h2 id="current-month-display"><?= date('F Y') ?></h2>
                            <div class="calendar-nav">
                                <button id="prev-year">
                                    <i class="fa-solid fa-angles-left"></i>
                                </button>
                                <button id="prev-month">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>
                                <button id="today">Today</button>
                                <button id="next-month">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                                <button id="next-year">
                                    <i class="fa-solid fa-angles-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="calendar-weekdays">
                            <div>Sun</div>
                            <div>Mon</div>
                            <div>Tue</div>
                            <div>Wed</div>
                            <div>Thu</div>
                            <div>Fri</div>
                            <div>Sat</div>
                        </div>
                        
                        <div class="calendar-days" id="calendar-days-container">
                            <!-- Calendar days will be populated by JavaScript -->
                        </div>
                        
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <div class="legend-color legend-available"></div>
                                <span>Available Slots</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-fully-booked"></div>
                                <span>Fully Booked</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-no-slots"></div>
                                <span>No Slots Scheduled</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color legend-holiday"></div>
                                <span>Holiday</span>
                            </div>
                        </div>
                    </div>

                    <!-- Time slots section (appears when a date is selected) -->
                    <?php if (isset($_GET['date'])): ?>
                        <div class="time-slots-container p-6">
                            <h3 class="font-semibold text-lg mb-4 flex items-center">
                                <i class="fa-solid fa-clock text-green-600 mr-2"></i>
                                Time Slots for <?= date('M d, Y', strtotime($_GET['date'])) ?>
                            </h3>
                            
                            <div class="space-y-3 mb-4">
                                <?php 
                                $selectedDate = $_GET['date'];
                                $selectedDateInfo = null;
                                foreach ($availableDates as $dateInfo) {
                                    if ($dateInfo['date'] === $selectedDate) {
                                        $selectedDateInfo = $dateInfo;
                                        break;
                                    }
                                }
                                ?>
                                
                                <?php if ($selectedDateInfo && !empty($selectedDateInfo['slots'])): ?>
                                    <?php foreach ($selectedDateInfo['slots'] as $slot): 
                                        $isAvailable = $slot['available_slots'] > 0;
                                    ?>
                                        <div class="time-slot <?= $isAvailable ? 'available' : 'unavailable' ?>">
                                            <div class="slot-info">
                                                <div>
                                                    <div class="slot-time">
                                                        <?= date('h:i A', strtotime($slot['start_time'])) ?> - <?= date('h:i A', strtotime($slot['end_time'])) ?>
                                                    </div>
                                                    <div class="slot-staff">
                                                        <span class="font-medium">Health Worker:</span> <?= htmlspecialchars($slot['staff_name']) ?>
                                                        <?php if (!empty($slot['specialization'])): ?>
                                                            (<?= htmlspecialchars($slot['specialization']) ?>)
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="slot-availability <?= $isAvailable ? 'available' : 'unavailable' ?>">
                                                    <?= $isAvailable ? 
                                                        "{$slot['available_slots']} slot" . ($slot['available_slots'] > 1 ? 's' : '') : 
                                                        'Fully booked' ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($isAvailable): ?>
                                                <div class="mt-3">
                                                    <input 
                                                        type="radio" 
                                                        id="slot_<?= $slot['slot_id'] ?>" 
                                                        name="slot" 
                                                        value="<?= $slot['slot_id'] ?>" 
                                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500" 
                                                        onchange="selectSlot(<?= $slot['slot_id'] ?>, '<?= $slot['start_time'] ?>', '<?= $slot['end_time'] ?>', '<?= htmlspecialchars($slot['staff_name']) ?>', '<?= htmlspecialchars($slot['specialization']) ?>')"
                                                        required
                                                    >
                                                    <label for="slot_<?= $slot['slot_id'] ?>" class="ml-2 text-sm text-gray-700">
                                                        Select this time slot
                                                    </label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded flex items-center">
                                        <i class="fa-solid fa-circle-exclamation mr-2"></i>
                                        No available slots for this date. Please choose another.
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div id="slot-details" class="hidden border-t pt-4 mt-4">
                                <h4 class="font-medium text-gray-700 mb-2">Selected Time Slot:</h4>
                                <div id="slot-info" class="text-sm text-gray-600"></div>
                                
                                <form method="POST" action="" class="mt-4" onsubmit="return validateHealthConcerns()">
                                    <input type="hidden" name="appointment_id" id="selected_slot_id">
                                    <input type="hidden" name="selected_date" value="<?= $selectedDate ?>">
                                    <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                    <input type="hidden" name="service_type" value="General Checkup">

                                    <!-- Health Concerns Section -->
                                    <div class="mb-4">
                                        <h4 class="font-medium text-gray-700 mb-3">Select Health Concerns</h4>
                                        <div class="health-concerns-grid">
                                            <?php 
                                            $healthConcerns = [
                                                'Asthma', 'Tuberculosis', 'Malnutrition', 'Obesity',
                                                'Pneumonia', 'Dengue', 'Anemia', 'Arthritis',
                                                'Stroke', 'Cancer', 'Depression'
                                            ];
                                            
                                            foreach ($healthConcerns as $concern): ?>
                                                <div class="health-concern-item">
                                                    <input type="checkbox" 
                                                           id="concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                                           name="health_concerns[]" 
                                                           value="<?= $concern ?>"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="concern_<?= strtolower(str_replace(' ', '_', $concern)) ?>" 
                                                           class="ml-2 text-gray-700 text-sm"><?= $concern ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Other Concern Option -->
                                            <div class="health-concern-item">
                                                <input type="checkbox" id="other_concern" name="health_concerns[]" value="Other"
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="other_concern" class="ml-2 text-gray-700 text-sm">Other</label>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-3" id="other_concern_container" style="display: none;">
                                            <label for="other_concern_specify" class="block text-gray-700 mb-1 text-sm">Please specify:</label>
                                            <input type="text" id="other_concern_specify" name="other_concern_specify" 
                                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Notes -->
                                    <div class="mb-4">
                                        <label for="appointment_notes" class="block text-gray-700 mb-2 text-sm font-medium">Health Concerns Details</label>
                                        <textarea id="appointment_notes" name="notes" rows="3" 
                                                  class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" 
                                                  placeholder="Describe your symptoms, concerns, or any other relevant information"></textarea>
                                    </div>
                                    
                                    <!-- Consent Checkbox -->
                                    <div class="flex items-center mb-4">
                                        <input type="checkbox" id="consent" name="consent" required
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="consent" class="ml-2 text-gray-700 text-sm">
                                            I consent to sharing my health information for this appointment
                                        </label>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <button type="submit" name="book_appointment" 
                                            class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition flex items-center justify-center text-sm">
                                        <i class="fa-solid fa-calendar-check mr-2"></i>
                                        Book Appointment
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side - Appointments List -->
            <div class="lg:w-1/2">
                <div class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <?php if ($activeTab === 'upcoming'): ?>
                            <i class="fa-solid fa-calendar-days text-blue-600 mr-2"></i>
                            Transactions
                        <?php elseif ($activeTab === 'past'): ?>
                            <i class="fa-solid fa-check-circle text-green-600 mr-2"></i>
                            Completed Appointments
                        <?php else: ?>
                            <i class="fa-solid fa-ban text-red-600 mr-2"></i>
                            Cancelled Appointments
                        <?php endif; ?>
                    </h2>

                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-8">
                            <i class="fa-regular fa-face-frown-open text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-600 mt-2">No <?= $activeTab ?> appointments found.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($appointments as $appointment): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-start">
                                            <?php if ($appointment['status'] === 'approved'): ?>
                                                <i class="fa-solid fa-check-circle text-green-500 mr-2 mt-0.5"></i>
                                            <?php elseif ($appointment['status'] === 'pending'): ?>
                                                <i class="fa-solid fa-clock text-yellow-500 mr-2 mt-0.5"></i>
                                            <?php elseif ($appointment['status'] === 'completed'): ?>
                                                <i class="fa-solid fa-circle-check text-blue-500 mr-2 mt-0.5"></i>
                                            <?php else: ?>
                                                <i class="fa-solid fa-ban text-red-500 mr-2 mt-0.5"></i>
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
                                    
                                    <?php if (!empty($appointment['invoice_number'])): ?>
                                        <div class="mt-3 pl-7">
                                            <button onclick="downloadInvoice(<?= $appointment['id'] ?>)" 
                                                    class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-blue-200 flex items-center">
                                                <i class="fa-solid fa-download mr-1"></i>
                                                Download Invoice
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($activeTab === 'upcoming' && $appointment['status'] !== 'cancelled' && $appointment['status'] !== 'rejected'): ?>
                                        <div class="mt-3 pl-7">
                                            <button onclick="openCancelModal(<?= $appointment['id'] ?>)"
                                                    class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
                                                <i class="fa-solid fa-xmark mr-1"></i>
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

    <!-- Cancellation modal -->
    <div id="cancel-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Cancel Appointment</h3>
                <form method="POST" action="" class="space-y-4">
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
    // Calendar navigation and rendering
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    // Available dates from PHP converted to a format JavaScript can use
    const availableDates = <?= json_encode($availableDates) ?>;
    const holidays = <?= json_encode($holidays) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        renderCalendar(currentMonth, currentYear);
        
        // Set up navigation buttons
        document.getElementById('prev-year').addEventListener('click', () => {
            currentYear--;
            renderCalendar(currentMonth, currentYear);
        });
        
        document.getElementById('prev-month').addEventListener('click', () => {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        });
        
        document.getElementById('today').addEventListener('click', () => {
            currentDate = new Date();
            currentMonth = currentDate.getMonth();
            currentYear = currentDate.getFullYear();
            renderCalendar(currentMonth, currentYear);
        });
        
        document.getElementById('next-month').addEventListener('click', () => {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        });
        
        document.getElementById('next-year').addEventListener('click', () => {
            currentYear++;
            renderCalendar(currentMonth, currentYear);
        });
        
        // Toggle Other field visibility
        const otherCheckbox = document.getElementById("other_concern");
        if (otherCheckbox) {
            otherCheckbox.addEventListener("change", function() {
                const otherContainer = document.getElementById("other_concern_container");
                if (otherContainer) {
                    otherContainer.style.display = this.checked ? "block" : "none";
                }
            });
        }
        
        // Handle session notification modal
        const sessionModal = document.getElementById('session-modal');
        if (sessionModal && sessionModal.style.display === 'flex') {
            setTimeout(() => {
                hideModal('session');
            }, 3000);
        }
    });

    function renderCalendar(month, year) {
        const calendarContainer = document.getElementById('calendar-days-container');
        const monthDisplay = document.getElementById('current-month-display');
        
        // Update month display
        monthDisplay.textContent = `${monthNames[month]} ${year}`;
        
        // Clear previous calendar
        calendarContainer.innerHTML = '';
        
        // Get first day of month and number of days
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        // Get days from previous month to show
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Create days from previous month
        for (let i = firstDay - 1; i >= 0; i--) {
            const day = daysInPrevMonth - i;
            const dateStr = formatDate(year, month - 1, day);
            const dayElement = createDayElement(day, dateStr, true, false);
            calendarContainer.appendChild(dayElement);
        }
        
        // Create days for current month
        const today = new Date();
        for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = formatDate(year, month, i);
            const isToday = today.getDate() === i && 
                           today.getMonth() === month && 
                           today.getFullYear() === year;
            const isPast = new Date(dateStr) < new Date().setHours(0, 0, 0, 0);
            const dayElement = createDayElement(i, dateStr, false, isToday, isPast);
            calendarContainer.appendChild(dayElement);
        }
        
        // Calculate how many next month days to show (to fill the grid)
        const totalCells = 42; // 6 rows x 7 columns
        const daysSoFar = firstDay + daysInMonth;
        const nextMonthDays = totalCells - daysSoFar;
        
        // Create days from next month
        for (let i = 1; i <= nextMonthDays; i++) {
            const dateStr = formatDate(year, month + 1, i);
            const dayElement = createDayElement(i, dateStr, true, false);
            calendarContainer.appendChild(dayElement);
        }
    }

    function formatDate(year, month, day) {
        return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    function createDayElement(day, dateStr, isOtherMonth, isToday = false, isPast = false) {
        const dayElement = document.createElement('div');
        
        // Determine day status
        const dateInfo = availableDates.find(d => d.date === dateStr);
        const isHoliday = holidays[dateStr];
        
        let dayStatus = 'no-slots';
        let buttonText = 'No slots';
        
        if (isHoliday) {
            dayStatus = 'holiday';
            buttonText = 'Holiday';
        } else if (dateInfo) {
            if (dateInfo.available_slots > 0) {
                dayStatus = 'available';
                buttonText = `${dateInfo.available_slots} slot${dateInfo.available_slots > 1 ? 's' : ''}`;
            } else {
                dayStatus = 'fully-booked';
                buttonText = 'Fully booked';
            }
        }
        
        // Add appropriate classes
        let classes = ['calendar-day'];
        if (isOtherMonth) classes.push('other-month');
        if (isToday) classes.push('today');
        if (isPast) classes.push('past');
        
        dayElement.className = classes.join(' ');
        
        // Create day content
        dayElement.innerHTML = `
            <div class="calendar-day-number">${day}</div>
            <div class="calendar-day-content">
                ${!isOtherMonth && !isPast ? 
                    `<button class="calendar-day-button ${dayStatus}" onclick="selectDate('${dateStr}')">
                        ${buttonText}
                    </button>` : 
                    `<div class="text-xs text-center">${isHoliday ? 'Holiday' : (isPast ? 'Past' : '')}</div>`
                }
            </div>
        `;
        
        return dayElement;
    }

    function selectDate(date) {
        window.location.href = '?date=' + date;
    }

    function selectSlot(slotId, startTime, endTime, staffName, specialization) {
        document.getElementById('selected_slot_id').value = slotId;
        
        const slotInfo = document.getElementById('slot-info');
        slotInfo.innerHTML = `
            <div class="flex items-start mb-1">
                <i class="fa-regular fa-clock text-gray-500 mr-2 mt-0.5"></i>
                <div>
                    <span class="font-medium">Time:</span> ${formatTime(startTime)} - ${formatTime(endTime)}
                </div>
            </div>
            <div class="flex items-start">
                <i class="fa-solid fa-user-md text-gray-500 mr-2 mt-0.5"></i>
                <div>
                    <span class="font-medium">Health Worker:</span> ${staffName} ${specialization ? '(' + specialization + ')' : ''}
                </div>
            </div>
        `;
        
        document.getElementById('slot-details').classList.remove('hidden');
    }

    function formatTime(timeStr) {
        const time = new Date('1970-01-01T' + timeStr + 'Z');
        return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

    function openCancelModal(appointmentId) {
        document.getElementById('modal-appointment-id').value = appointmentId;
        showModal('cancel');
    }

    function closeCancelModal() {
        hideModal('cancel');
    }

    function downloadInvoice(appointmentId) {
        window.location.href = '?download_invoice=' + appointmentId;
    }

    function showModal(type) {
        const modal = document.getElementById(`${type}-modal`);
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function hideModal(type) {
        const modal = document.getElementById(`${type}-modal`);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.style.display = 'none';
        }
    });
    </script>
</body>
</html>