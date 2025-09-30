<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

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
    }
}

// Get available slots
$availableSlots = [];
// Get pending appointments
$pendingAppointments = [];
// Get all appointments
$allAppointments = [];

try {
    // Get available slots
    $stmt = $pdo->prepare("SELECT * FROM sitio1_appointments WHERE staff_id = ? AND date >= CURDATE() ORDER BY date, start_time");
    $stmt->execute([$staffId]);
    $availableSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending appointments
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.contact, a.date, a.start_time, a.end_time 
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          WHERE a.staff_id = ? AND ua.status = 'pending' AND a.date >= CURDATE() 
                          ORDER BY a.date, a.start_time");
    $stmt->execute([$staffId]);
    $pendingAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all appointments
    $stmt = $pdo->prepare("SELECT ua.*, u.full_name, u.contact, a.date, a.start_time, a.end_time 
                          FROM user_appointments ua 
                          JOIN sitio1_users u ON ua.user_id = u.id 
                          JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                          WHERE a.staff_id = ? 
                          ORDER BY a.date DESC, a.start_time DESC");
    $stmt->execute([$staffId]);
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching appointment data: ' . $e->getMessage();
}
?>

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

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6 text-blue-800">Appointment Management</h1>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <!-- Navigation Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px" id="appointmentTabs" role="tablist">
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg" id="add-slot-tab" data-tabs-target="#add-slot" type="button" role="tab" aria-controls="add-slot" aria-selected="false">Add Slot</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg" id="available-slots-tab" data-tabs-target="#available-slots" type="button" role="tab" aria-controls="available-slots" aria-selected="false">Available Slots</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg" id="pending-tab" data-tabs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">Pending Appointments</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg" id="all-tab" data-tabs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="false">All Appointments</button>
            </li>
        </ul>
    </div>
    
    <!-- Tab Contents -->
    <div class="tab-content">
        <!-- Add Available Slot -->
        <div class="hidden p-4 bg-white rounded-lg shadow" id="add-slot" role="tabpanel" aria-labelledby="add-slot-tab">
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
        <div class="hidden p-4 bg-white rounded-lg shadow" id="available-slots" role="tabpanel" aria-labelledby="available-slots-tab">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-blue-700">Your Available Slots</h2>
                <span class="text-sm text-gray-600"><?= count($availableSlots) ?> slots available</span>
            </div>
            
            <?php if (empty($availableSlots)): ?>
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <p class="text-gray-600">No available slots found.</p>
                    <button onclick="switchTab('add-slot')" class="mt-2 text-blue-600 hover:underline font-medium">
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slots</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($availableSlots as $slot): 
                                $currentDate = date('Y-m-d');
                                $isPast = $slot['date'] < $currentDate;
                                $isToday = $slot['date'] == $currentDate;
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
                                        <?= $slot['max_slots'] ?>
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
        <div class="hidden p-4 bg-white rounded-lg shadow" id="pending" role="tabpanel" aria-labelledby="pending-tab">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" action="" class="inline mr-2">
                                            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" name="approve_appointment" 
                                                    class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium hover:bg-green-200">
                                                Approve
                                            </button>
                                        </form>
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
        <div class="hidden p-4 bg-white rounded-lg shadow" id="all" role="tabpanel" aria-labelledby="all-tab">
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
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
    document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
        tabBtn.classList.remove('border-blue-500', 'text-blue-600');
        tabBtn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    });
    
    // Set active tab
    const activeTabBtn = document.querySelector(`#appointmentTabs button[data-tabs-target="#${tabId}"]`);
    activeTabBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
    activeTabBtn.classList.add('border-blue-500', 'text-blue-600');
}

// Initialize tabs
document.addEventListener('DOMContentLoaded', function() {
    // Set first tab as active by default
    switchTab('add-slot');
    
    // Add click event listeners to all tab buttons
    document.querySelectorAll('#appointmentTabs button').forEach(tabBtn => {
        tabBtn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tabs-target').replace('#', '');
            switchTab(targetTab);
        });
    });
});

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
}
</script>

