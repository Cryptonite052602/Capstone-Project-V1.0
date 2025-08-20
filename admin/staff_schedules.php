<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if admin
if (!isAdmin()) {
    header('Location: /community-health-tracker/auth/login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_schedule'])) {
        $staffId = (int)$_POST['staff_id'];
        $date = $_POST['date'];
        $isWorking = isset($_POST['is_working']) ? 1 : 0;
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'];
        
        // Validate time
        if ($isWorking && strtotime($startTime) >= strtotime($endTime)) {
            $_SESSION['error'] = "End time must be after start time";
        } else {
            try {
                // Update or insert schedule
                $stmt = $pdo->prepare("
                    INSERT INTO sitio1_staff_schedule (staff_id, date, is_working, start_time, end_time)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE is_working=?, start_time=?, end_time=?
                ");
                $stmt->execute([
                    $staffId, $date, $isWorking, $startTime, $endTime,
                    $isWorking, $startTime, $endTime
                ]);
                
                // Update staff active status
                updateStaffActiveStatus($staffId, $date, $isWorking, $pdo);
                
                $_SESSION['message'] = "Schedule updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_default_schedule'])) {
        $staffId = (int)$_POST['staff_id'];
        $workDays = $_POST['work_days'];
        
        // Validate work days string
        if (preg_match('/^[01]{7}$/', $workDays)) {
            try {
                $stmt = $pdo->prepare("UPDATE sitio1_staff SET work_days = ? WHERE id = ?");
                $stmt->execute([$workDays, $staffId]);
                
                $_SESSION['message'] = "Default work days updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Invalid work days format";
        }
    }
    
    header("Location: staff_schedules.php?date=" . urlencode($_POST['date']));
    exit;
}

// Function to update staff active status based on schedule
function updateStaffActiveStatus($staffId, $date, $isWorking, $pdo) {
    if (!$isWorking) {
        // Deactivate if not working on this day
        $stmt = $pdo->prepare("UPDATE sitio1_staff SET is_active = FALSE WHERE id = ?");
        $stmt->execute([$staffId]);
    } else {
        // Check if should be reactivated
        $dayOfWeek = date('N', strtotime($date)) - 1; // 0=Monday
        $stmt = $pdo->prepare("
            SELECT work_days FROM sitio1_staff WHERE id = ?
        ");
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $workDays = str_split($staff['work_days'] ?? '1111100');
        $isDefaultWorking = ($workDays[$dayOfWeek] ?? '0') === '1';
        
        if ($isDefaultWorking) {
            $stmt = $pdo->prepare("UPDATE sitio1_staff SET is_active = TRUE WHERE id = ?");
            $stmt->execute([$staffId]);
        }
    }
}

// Get selected date (default to today)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$dayOfWeek = date('N', strtotime($selectedDate)) - 1; // 0=Monday, 6=Sunday

// Validate date
if (!strtotime($selectedDate)) {
    $selectedDate = date('Y-m-d');
    $dayOfWeek = date('N') - 1;
}

// Get schedules for selected date
try {
    $schedulesQuery = $pdo->prepare("
        SELECT s.*, ss.is_working, ss.start_time, ss.end_time 
        FROM sitio1_staff s
        LEFT JOIN sitio1_staff_schedule ss ON s.id = ss.staff_id AND ss.date = ?
        ORDER BY s.full_name
    ");
    $schedulesQuery->execute([$selectedDate]);
    $schedules = $schedulesQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schedules = [];
    $_SESSION['error'] = "Failed to load schedules: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Schedules</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .half-day {
            background-color: #ffedd5;
            color: #9a3412;
        }
        .day-off {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        .working-day {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php 
    require_once __DIR__ . '/../includes/header.php';
    ?>
    
    <main class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-6">Staff Schedules</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Select Date</h2>
            <form method="get" class="flex items-center space-x-4">
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" 
                    class="border rounded px-3 py-2" required>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-calendar-alt mr-2"></i> View Schedule
                </button>
                <a href="staff_schedules.php" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                    <i class="fas fa-sync-alt mr-2"></i> Today
                </a>
            </form>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Schedule for <?= date('F j, Y', strtotime($selectedDate)) ?></h2>
                <span class="text-gray-600 font-medium">
                    <?= ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$dayOfWeek] ?>
                    <?php if ($dayOfWeek === 5): ?>
                        <span class="text-orange-600">(Half-day)</span>
                    <?php elseif ($dayOfWeek === 6): ?>
                        <span class="text-gray-500">(Day off)</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 border-b text-left">Staff Member</th>
                            <th class="py-3 px-4 border-b text-left">Position</th>
                            <th class="py-3 px-4 border-b text-left">Status</th>
                            <th class="py-3 px-4 border-b text-left">Default Work Days</th>
                            <th class="py-3 px-4 border-b text-left">Schedule for Selected Day</th>
                            <th class="py-3 px-4 border-b text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $staff): 
                            $defaultWorkDays = str_split($staff['work_days'] ?? '1111100');
                            $isDefaultWorking = ($defaultWorkDays[$dayOfWeek] ?? '0') === '1';
                            $isScheduledWorking = $staff['is_working'];
                            $isActive = $staff['is_active'];
                        ?>
                            <tr class="<?= $isActive ? '' : 'bg-gray-100' ?>">
                                <td class="py-3 px-4 border-b">
                                    <?= htmlspecialchars($staff['full_name']) ?>
                                    <?php if (!$isActive): ?>
                                        <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded ml-2">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 border-b"><?= htmlspecialchars($staff['position']) ?></td>
                                <td class="py-3 px-4 border-b">
                                    <?php if ($isActive): ?>
                                        <span class="text-green-600"><i class="fas fa-check-circle"></i> Active</span>
                                    <?php else: ?>
                                        <span class="text-red-600"><i class="fas fa-times-circle"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <div class="flex space-x-1">
                                        <?php foreach ($defaultWorkDays as $i => $day): ?>
                                            <?php
                                            $dayClass = 'day-off';
                                            $dayText = ['M', 'T', 'W', 'T', 'F', 'S', 'S'][$i];
                                            
                                            if ($day === '1') {
                                                $dayClass = ($i === 5) ? 'half-day' : 'working-day';
                                            }
                                            ?>
                                            <span class="<?= $dayClass ?> px-2 py-1 rounded text-xs" title="<?= 
                                                ($day === '1') ? 
                                                    (($i === 5) ? 'Saturday (Half-day)' : ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'][$i]) : 
                                                    'Day off' 
                                            ?>">
                                                <?= $dayText ?>
                                                <?= ($i === 5 && $day === '1') ? '½' : '' ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <?php if ($isScheduledWorking): ?>
                                        <span class="text-green-600">
                                            <i class="fas fa-check"></i> Working 
                                            (<?= date('g:i a', strtotime($staff['start_time'])) ?> - <?= date('g:i a', strtotime($staff['end_time'])) ?>)
                                            <?php if ($dayOfWeek === 5): ?>
                                                <span class="text-orange-600">(Half-day)</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php elseif ($isDefaultWorking): ?>
                                        <span class="<?= $dayOfWeek === 5 ? 'text-orange-600' : 'text-blue-600' ?>">
                                            <i class="fas fa-info-circle"></i> Default schedule
                                            <?php if ($dayOfWeek === 5): ?>
                                                (8:00 am - 12:00 pm)
                                            <?php elseif ($dayOfWeek === 6): ?>
                                                (Day off)
                                            <?php else: ?>
                                                (8:00 am - 5:00 pm)
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-500">
                                            <i class="fas fa-times"></i> Not scheduled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 border-b">
                                    <button onclick="document.getElementById('edit-modal-<?= $staff['id'] ?>').showModal()" 
                                        class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Edit Modal -->
                            <dialog id="edit-modal-<?= $staff['id'] ?>" class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold">Edit Schedule for <?= htmlspecialchars($staff['full_name']) ?></h3>
                                    <button onclick="document.getElementById('edit-modal-<?= $staff['id'] ?>').close()" 
                                        class="text-gray-500 hover:text-gray-700">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                
                                <form method="post" class="space-y-4">
                                    <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                    <input type="hidden" name="date" value="<?= $selectedDate ?>">
                                    
                                    <div class="space-y-4">
                                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded">
                                            <input type="checkbox" id="is_working_<?= $staff['id'] ?>" name="is_working" 
                                                <?= $isScheduledWorking ? 'checked' : '' ?> class="h-5 w-5">
                                            <label for="is_working_<?= $staff['id'] ?>" class="font-medium">Scheduled to work on <?= date('M j', strtotime($selectedDate)) ?></label>
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-4 mt-4">
                                            <div>
                                                <label for="start_time_<?= $staff['id'] ?>" class="block mb-1 text-sm font-medium">Start Time</label>
                                                <input type="time" id="start_time_<?= $staff['id'] ?>" name="start_time" 
                                                    value="<?= $staff['start_time'] ?? ($dayOfWeek === 5 ? '08:00' : '08:00') ?>" 
                                                    class="border rounded px-3 py-2 w-full">
                                            </div>
                                            <div>
                                                <label for="end_time_<?= $staff['id'] ?>" class="block mb-1 text-sm font-medium">End Time</label>
                                                <input type="time" id="end_time_<?= $staff['id'] ?>" name="end_time" 
                                                    value="<?= $staff['end_time'] ?? ($dayOfWeek === 5 ? '12:00' : '17:00') ?>" 
                                                    class="border rounded px-3 py-2 w-full">
                                            </div>
                                        </div>
                                        
                                        <div class="pt-4 border-t">
                                            <h4 class="font-medium mb-2">Default Weekly Schedule</h4>
                                            <p class="text-sm text-gray-600 mb-3">Select days this staff member typically works:</p>
                                            <div class="grid grid-cols-7 gap-1">
                                                <?php 
                                                $days = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
                                                foreach ($days as $index => $day): 
                                                    $isChecked = ($defaultWorkDays[$index] ?? '0') === '1';
                                                    $isSaturday = ($index === 5);
                                                    $isSunday = ($index === 6);
                                                ?>
                                                    <label class="flex flex-col items-center">
                                                        <span class="text-xs mb-1"><?= $day ?><?= $isSaturday ? '½' : '' ?></span>
                                                        <input type="checkbox" name="work_days[]" value="<?= $index ?>" 
                                                            <?= $isChecked ? 'checked' : '' ?> 
                                                            <?= $isSunday ? 'disabled' : '' ?>
                                                            class="h-5 w-5 <?= $isSaturday ? 'border-orange-300' : '' ?>">
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="work_days" id="work_days_<?= $staff['id'] ?>" 
                                                value="<?= htmlspecialchars($staff['work_days'] ?? '1111100') ?>">
                                            <p class="text-xs text-gray-500 mt-2">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Weekdays: 8am-5pm, Saturday: 8am-12pm, Sunday: Day off
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end space-x-3 pt-4 border-t">
                                        <button type="button" onclick="document.getElementById('edit-modal-<?= $staff['id'] ?>').close()" 
                                            class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400">
                                            Cancel
                                        </button>
                                        <button type="submit" name="update_schedule" 
                                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                            Save Schedule
                                        </button>
                                        <button type="submit" name="update_default_schedule" 
                                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                                            Save Default Days
                                        </button>
                                    </div>
                                </form>
                                
                                <script>
                                    // Convert checkbox array to binary string for work days
                                    document.querySelectorAll('#edit-modal-<?= $staff['id'] ?> input[name="work_days[]"]').forEach(checkbox => {
                                        checkbox.addEventListener('change', function() {
                                            let binaryDays = '0000000'.split('');
                                            document.querySelectorAll('#edit-modal-<?= $staff['id'] ?> input[name="work_days[]"]:checked').forEach(checked => {
                                                binaryDays[checked.value] = '1';
                                            });
                                            document.getElementById('work_days_<?= $staff['id'] ?>').value = binaryDays.join('');
                                            
                                            // If Saturday is checked, set default end time to 12:00
                                            if (document.querySelector('#edit-modal-<?= $staff['id'] ?> input[name="work_days[]"][value="5"]').checked) {
                                                document.getElementById('end_time_<?= $staff['id'] ?>').value = '12:00';
                                            }
                                        });
                                    });

                                    // Set default times based on day selection
                                    document.getElementById('is_working_<?= $staff['id'] ?>').addEventListener('change', function() {
                                        if (this.checked) {
                                            const dayIndex = <?= $dayOfWeek ?>;
                                            if (dayIndex === 5) { // Saturday
                                                document.getElementById('start_time_<?= $staff['id'] ?>').value = '08:00';
                                                document.getElementById('end_time_<?= $staff['id'] ?>').value = '12:00';
                                            } else if (dayIndex !== 6) { // Not Sunday
                                                document.getElementById('start_time_<?= $staff['id'] ?>').value = '08:00';
                                                document.getElementById('end_time_<?= $staff['id'] ?>').value = '17:00';
                                            }
                                        }
                                    });
                                </script>
                            </dialog>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($schedules)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-user-slash fa-2x mb-2"></i>
                    <p>No staff members found</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Close modal when clicking outside
        document.querySelectorAll('dialog').forEach(dialog => {
            dialog.addEventListener('click', function(e) {
                if (e.target === dialog) {
                    dialog.close();
                }
            });
        });
    </script>
</body>
</html>