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
$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'upcoming';

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointmentId = $_POST['appointment_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE user_appointments SET status = 'rejected' WHERE id = ? AND user_id = ?");
        $stmt->execute([$appointmentId, $userId]);
        
        // Set success message for modal
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => 'Appointment cancelled successfully!'
        ];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    } catch (PDOException $e) {
        $error = 'Error cancelling appointment: ' . $e->getMessage();
    }
}

// Get available dates with slots and staff information
$availableDates = [];

try {
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
    
    // Organize by date
    foreach ($availableSlots as $slot) {
        $date = $slot['date'];
        if (!isset($availableDates[$date])) {
            $availableDates[$date] = [
                'date' => $date,
                'slots' => []
            ];
        }
        $availableDates[$date]['slots'][] = $slot;
    }
    $availableDates = array_values($availableDates);
} catch (PDOException $e) {
    $error = 'Error fetching available dates: ' . $e->getMessage();
}

// Get counts for each tab
$counts = [
    'upcoming' => 0,
    'past' => 0,
    'cancelled' => 0
];

try {
    // Upcoming count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.user_id = ? AND ua.status IN ('pending', 'approved') AND a.date >= CURDATE()
    ");
    $stmt->execute([$userId]);
    $counts['upcoming'] = $stmt->fetch()['count'];

    // Past count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE ua.user_id = ? AND (ua.status = 'completed' OR a.date < CURDATE())
    ");
    $stmt->execute([$userId]);
    $counts['past'] = $stmt->fetch()['count'];

    // Cancelled count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM user_appointments ua
        WHERE ua.user_id = ? AND ua.status = 'rejected'
    ");
    $stmt->execute([$userId]);
    $counts['cancelled'] = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $error = 'Error fetching appointment counts: ' . $e->getMessage();
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
    
    if ($activeTab === 'upcoming') {
        $query .= " AND ua.status IN ('pending', 'approved') AND a.date >= CURDATE()";
    } elseif ($activeTab === 'past') {
        $query .= " AND (ua.status = 'completed' OR a.date < CURDATE())";
    } elseif ($activeTab === 'cancelled') {
        $query .= " AND ua.status = 'rejected'";
    }
    
    $query .= " ORDER BY a.date " . ($activeTab === 'past' ? 'DESC' : 'ASC') . ", a.start_time";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
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

</style>

<div class="container mx-auto px-4">
    <!-- Success Modal -->
    <div id="success-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="success-modal-backdrop"></div>
        <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-sm w-full opacity-0 scale-95" id="success-modal-content">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="mt-3 text-lg font-medium text-gray-900">Appointment Successfully Booked</h3>
                <div class="mt-2 px-4 py-3">
                    <p class="text-sm text-gray-500" id="success-message"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="error-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="error-modal-backdrop"></div>
        <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-sm w-full opacity-0 scale-95" id="error-modal-content">
            <div class="p-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h3 class="mt-3 text-lg font-medium text-gray-900">You have an active appointment.</h3>
                <div class="mt-2 px-4 py-3">
                    <p class="text-sm text-gray-500" id="error-message"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Notification -->
    <?php if (isset($_SESSION['notification'])): ?>
        <div id="session-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity duration-300 opacity-0" id="session-modal-backdrop"></div>
            <div class="bg-white rounded-lg shadow-xl transform transition-all duration-300 max-w-sm w-full opacity-0 scale-95" id="session-modal-content">
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
                </div>
            </div>
        </div>
        <?php unset($_SESSION['notification']); ?>
    <?php endif; ?>

    <h1 class="text-2xl font-bold mb-6 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        Community Health Appointments
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

    <!-- Enhanced Tabs with Counts and Icons -->
    <div class="flex border-b border-gray-200 mb-6">
        <a href="?tab=upcoming" class="<?= $activeTab === 'upcoming' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Upcoming
            <span class="ml-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $counts['upcoming'] ?></span>
        </a>
        <a href="?tab=past" class="<?= $activeTab === 'past' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Completed
            <span class="ml-1 bg-green-100 text-green-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $counts['past'] ?></span>
        </a>
        <a href="?tab=cancelled" class="<?= $activeTab === 'cancelled' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 font-medium flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Cancelled
            <span class="ml-1 bg-red-100 text-red-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $counts['cancelled'] ?></span>
        </a>
        <a href="#book-appointment" class="ml-auto px-4 py-2 font-medium text-blue-600 hover:text-blue-800 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            + New Appointment
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
                                foreach ($dateInfo['slots'] as $slot) {
                                    if ($slot['available_slots'] > 0) {
                                        $hasAvailableSlots = true;
                                        break;
                                    }
                                }
                            ?>
                                <a href="?date=<?= $date ?>" class="border rounded-lg p-2 text-center transition <?= ($_GET['date'] ?? '') === $date ? 'border-blue-500 bg-blue-50' : ($hasAvailableSlots ? 'border-gray-200 hover:bg-blue-50' : 'border-gray-200 bg-gray-100 text-gray-500 cursor-not-allowed') ?>">
                                    <div class="font-medium"><?= date('D', strtotime($date)) ?></div>
                                    <div class="text-sm"><?= date('M j', strtotime($date)) ?></div>
                                    <?php if (!$hasAvailableSlots): ?>
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
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label for="appointment_date" class="block text-gray-700 mb-2 font-medium">Or select specific date:</label>
                            <select id="appointment_date" name="date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Select date --</option>
                                <?php foreach ($availableDates as $dateInfo): 
                                    $date = $dateInfo['date'];
                                    $hasAvailableSlots = false;
                                    foreach ($dateInfo['slots'] as $slot) {
                                        if ($slot['available_slots'] > 0) {
                                            $hasAvailableSlots = true;
                                            break;
                                        }
                                    }
                                ?>
                                    <option value="<?= $date ?>" <?= ($_GET['date'] ?? '') === $date ? 'selected' : '' ?> <?= !$hasAvailableSlots ? 'disabled' : '' ?>>
                                        <?= date('l, F j, Y', strtotime($date)) ?>
                                        <?= !$hasAvailableSlots ? ' (Fully Booked)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if (isset($_GET['date'])): 
                            $selectedDate = $_GET['date'];
                            $selectedDateInfo = null;
                            foreach ($availableDates as $dateInfo) {
                                if ($dateInfo['date'] === $selectedDate) {
                                    $selectedDateInfo = $dateInfo;
                                    break;
                                }
                            }
                        ?>
                            <div>
                                <label for="appointment_slot" class="block text-gray-700 mb-2 font-medium">Available Time Slots:</label>
                                <?php if ($selectedDateInfo && !empty($selectedDateInfo['slots'])): ?>
                                    <div class="space-y-2">
                                        <?php foreach ($selectedDateInfo['slots'] as $slot): 
                                            $isAvailable = $slot['available_slots'] > 0;
                                        ?>
                                            <div class="border rounded-lg p-3 <?= $isAvailable ? 'border-gray-200 hover:bg-blue-50' : 'border-gray-200 bg-gray-100 text-gray-500' ?>">
                                                <div class="flex items-center">
                                                    <input 
                                                        type="radio" 
                                                        id="slot_<?= $slot['slot_id'] ?>" 
                                                        name="slot" 
                                                        value="<?= $slot['slot_id'] ?>" 
                                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500" 
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

                <?php if (isset($_GET['date']) && isset($_GET['slot'])): ?>
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
                    
                    if ($selectedSlot): ?>
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
        
        <form method="POST" action="/community-health-tracker/api/appointments.php" class="ajax-form mt-6">
            <input type="hidden" name="appointment_id" value="<?= $selectedSlot['slot_id'] ?>">
            <input type="hidden" name="selected_date" value="<?= $selectedDate ?>">
            
            <!-- Health Conditions Section -->
            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3">Select Health Conditions</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php 
                    $healthConditions = [
                        'Asthma', 'Tuberculosis', 'Malnutrition', 'Obesity',
                        'Pneumonia', 'Dengue', 'Anemia', 'Arthritis',
                        'Stroke', 'Cancer', 'Depression'
                    ];
                    
                    foreach ($healthConditions as $condition): ?>
                        <div class="flex items-center">
                            <input type="checkbox" id="condition_<?= strtolower(str_replace(' ', '_', $condition)) ?>" 
                                   name="health_condition[]" value="<?= $condition ?>"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="condition_<?= strtolower(str_replace(' ', '_', $condition)) ?>" 
                                   class="ml-2 text-gray-700"><?= $condition ?></label>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Other Condition Option -->
                    <div class="flex items-center">
                        <input type="checkbox" id="other_condition" name="health_condition[]" value="Other"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="other_condition" class="ml-2 text-gray-700">Other</label>
                    </div>
                </div>
                
                <div class="mt-3" id="other_condition_container" style="display: none;">
                    <label for="other_condition_specify" class="block text-gray-700 mb-1 text-sm">Please specify:</label>
                    <input type="text" id="other_condition_specify" name="other_condition_specify" 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <!-- Existing Health Concerns Textarea -->
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
            <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition flex items-center justify-center" <?= $selectedSlot['available_slots'] <= 0 ? 'disabled' : '' ?>>
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
        Selected slot is no longer available. Please choose another.
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('appointmentForm');
    const otherCheckbox = document.getElementById('other_condition');
    const otherContainer = document.getElementById('other_condition_container');
    const conditionsError = document.getElementById('conditions-error');
    const consentError = document.getElementById('consent-error');

    // Toggle other condition input field
    otherCheckbox.addEventListener('change', function() {
        otherContainer.style.display = this.checked ? 'block' : 'none';
        if (this.checked) {
            document.getElementById('other_condition_specify').focus();
        }
    });

    // Real-time validation
    form.addEventListener('change', function() {
        validateForm();
    });

    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
        }
    });

    function validateForm() {
        let isValid = true;
        
        // Validate health conditions
        const checkedConditions = document.querySelectorAll('input[name="health_condition[]"]:checked');
        if (checkedConditions.length === 0) {
            conditionsError.classList.remove('hidden');
            isValid = false;
        } else {
            conditionsError.classList.add('hidden');
        }

        // Validate other condition if checked
        if (otherCheckbox.checked && !document.getElementById('other_condition_specify').value.trim()) {
            isValid = false;
        }

        // Validate consent
        if (!document.getElementById('consent').checked) {
            consentError.classList.remove('hidden');
            isValid = false;
        } else {
            consentError.classList.add('hidden');
        }

        return isValid;
    }

    // Initialize form state
    validateForm();
});
</script>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side - Appointments List -->
        <div class="md:w-1/2">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <?php if ($activeTab === 'upcoming'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Upcoming Appointments
                    <?php elseif ($activeTab === 'past'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-gray-600 mt-2">No <?= $activeTab ?> appointments found.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
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
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        <?php elseif ($appointment['status'] === 'completed'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                                        </div>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $appointment['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                           ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($appointment['notes'])): ?>
                                    <div class="mt-3 pl-7">
                                        <p class="text-sm text-gray-700">
                                            <span class="font-medium">Notes:</span> <?= htmlspecialchars($appointment['notes']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($activeTab === 'upcoming' && $appointment['status'] !== 'rejected'): ?>
    <button onclick="openCancelModal(<?= $appointment['id'] ?>)"
            class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Cancel Appointment
    </button>
<?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

    // Handle form submission
    const appointmentForm = document.querySelector('.ajax-form');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            `;
            
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointment_id: formData.get('appointment_id'),
                    notes: formData.get('notes')
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                if (data.success) {
                    // Show success modal
                    showModal('success', 'Appointment successfully applied!');
                    
                    // Hide modal after 2 seconds and reload
                    setTimeout(() => {
                        hideModal('success');
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                // Show error modal
                showModal('error', error.message);
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                
                // Hide error modal after 3 seconds
                setTimeout(() => {
                    hideModal('error');
                }, 3000);
            });
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

    // Modal control functions
    function showModal(type, message) {
        const modal = document.getElementById(`${type}-modal`);
        const messageElement = document.getElementById(`${type}-message`);
        
        if (messageElement) {
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
});
</script>


<?php

// Handle appointment cancellation with reason
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
            throw new Exception('Appointment cannot be cancelled');
        }
        
        // Update with cancellation reason and timestamp
        $stmt = $pdo->prepare("
            UPDATE user_appointments 
            SET status = 'rejected', 
                cancel_reason = ?,
                cancelled_at = NOW()
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
    }
}

?>

<script>

// Add to your existing JavaScript
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

</script>

