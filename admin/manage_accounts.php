<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_staff'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $fullName = trim($_POST['full_name']);
        $position = trim($_POST['position']);
        $specialization = trim($_POST['specialization'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        
        if (!empty($username) && !empty($password) && !empty($fullName)) {
            try {
                // Check if username already exists among active accounts
                $stmt = $pdo->prepare("SELECT id FROM sitio1_staff WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['error_message'] = 'Username already exists. Please choose another.';
                    header('Location: manage_accounts.php');
                    exit();
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO sitio1_staff (username, password, full_name, position, specialization, license_number, created_by, status, is_active) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1)");
                $stmt->execute([$username, $hashedPassword, $fullName, $position, $specialization, $license_number, $_SESSION['user_id']]);
                
                $_SESSION['success_message'] = 'Staff account created successfully!';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Error creating staff account: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = 'Please fill in all required fields.';
        }
    } elseif (isset($_POST['toggle_staff_status'])) {
        $staffId = intval($_POST['staff_id']);
        $action = $_POST['action'];
        
        if (in_array($action, ['activate', 'deactivate'])) {
            try {
                $newStatus = ($action === 'activate') ? 'active' : 'inactive';
                $isActive = ($action === 'activate') ? 1 : 0;
                $stmt = $pdo->prepare("UPDATE sitio1_staff SET status = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $isActive, $staffId]);
                
                $_SESSION['success_message'] = 'Staff account ' . $action . 'd successfully!';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'Error updating staff account: ' . $e->getMessage();
                header('Location: manage_accounts.php');
                exit();
            }
        }
    } elseif (isset($_POST['hard_delete'])) {
        $staffId = intval($_POST['staff_id']);
        
        try {
            // First check if staff has any dependent records
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_announcements WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['error_message'] = 'Cannot delete staff member because they have associated announcements. Please deactivate instead.';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // If no dependencies, proceed with deletion
            $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
            $stmt->execute([$staffId]);
            
            $_SESSION['success_message'] = 'Staff account permanently deleted!';
            header('Location: manage_accounts.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Error deleting staff account: ' . $e->getMessage();
            header('Location: manage_accounts.php');
            exit();
        }
    }
}

// Get all staff accounts
$activeStaff = [];
$inactiveStaff = [];

try {
    // Active staff (is_active = 1)
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 1
                         ORDER BY s.created_at DESC");
    $activeStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inactive staff (is_active = 0)
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 0
                         ORDER BY s.created_at DESC");
    $inactiveStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error fetching staff accounts: ' . $e->getMessage();
}

// Get stats for dashboard
$stats = [
    'total_staff' => count($activeStaff) + count($inactiveStaff),
    'active_staff' => count($activeStaff),
    'inactive_staff' => count($inactiveStaff)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Community Health Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                Staff Account Management
            </h1>
            <a href="/community-health-tracker/admin/dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-full mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-full mb-4">
                <?= $_SESSION['success_message'] ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Staff Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Staff</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_staff'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Active Staff Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Active Staff</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['active_staff'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Inactive Staff Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-gray-100 text-gray-600 mr-4">
                        <i class="fas fa-user-times text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Inactive Staff</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['inactive_staff'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Staff Account Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Create New Staff Account</h2>
            
            <form method="POST" action="" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                        <input type="text" id="username" name="username" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               required placeholder="Enter username">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               required placeholder="Enter password">
                    </div>
                </div>
                
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           required placeholder="Enter full name">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="position" class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                        <input type="text" id="position" name="position" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="e.g., Nurse, Doctor">
                    </div>
                    
                    <div>
                        <label for="specialization" class="block text-sm font-medium text-gray-700 mb-2">Specialization (Optional)</label>
                        <input type="text" id="specialization" name="specialization" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="e.g., Pediatrics, Surgery">
                    </div>
                    
                    <div>
                        <label for="license_number" class="block text-sm font-medium text-gray-700 mb-2">License Number</label>
                        <input type="text" id="license_number" name="license_number" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Professional license number">
                    </div>
                </div>
                
                <button type="submit" name="create_staff" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-full hover:bg-blue-700 transition-colors font-medium">
                    <i class="fas fa-user-plus mr-2"></i>Create Staff Account
                </button>
            </form>
        </div>
        
        <!-- Active Staff Accounts -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Active Staff Accounts</h2>
                <span class="text-gray-600"><?= count($activeStaff) ?> active staff member(s)</span>
            </div>
            
            <?php if (empty($activeStaff)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-shield text-gray-300 text-4xl mb-3"></i>
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">No active staff accounts</h3>
                    <p class="text-gray-500">There are no active staff accounts at the moment.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-gray-600">Staff Information</th>
                                <th class="text-left py-3 px-4 text-gray-600">Position & Credentials</th>
                                <th class="text-left py-3 px-4 text-gray-600">Status</th>
                                <th class="text-left py-3 px-4 text-gray-600">Created</th>
                                <th class="text-left py-3 px-4 text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeStaff as $staffMember): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($staffMember['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($staffMember['username']) ?></div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Created by: <?= htmlspecialchars($staffMember['creator_username'] ?? 'System') ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800">
                                            <?= !empty($staffMember['position']) ? htmlspecialchars($staffMember['position']) : 'No position' ?>
                                        </div>
                                        <?php if (!empty($staffMember['specialization'])): ?>
                                            <div class="text-sm text-blue-600 mt-1">
                                                <i class="fas fa-stethoscope mr-1"></i>
                                                <?= htmlspecialchars($staffMember['specialization']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($staffMember['license_number'])): ?>
                                            <div class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-id-card mr-1"></i>
                                                License: <?= htmlspecialchars($staffMember['license_number']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i> Active
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-700">
                                            <?= date('M j, Y', strtotime($staffMember['created_at'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($staffMember['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" name="toggle_staff_status" 
                                                    class="bg-yellow-500 text-white px-4 py-2 rounded-full hover:bg-yellow-600 transition-colors text-sm font-medium"
                                                    onclick="return confirm('Are you sure you want to deactivate this staff account?')">
                                                <i class="fas fa-pause mr-1"></i>Deactivate
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Inactive Staff Accounts -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Inactive Staff Accounts</h2>
                <span class="text-gray-600"><?= count($inactiveStaff) ?> inactive staff member(s)</span>
            </div>
            
            <?php if (empty($inactiveStaff)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-times text-gray-300 text-4xl mb-3"></i>
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">No inactive staff accounts</h3>
                    <p class="text-gray-500">All staff accounts are currently active.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-gray-600">Staff Information</th>
                                <th class="text-left py-3 px-4 text-gray-600">Position & Credentials</th>
                                <th class="text-left py-3 px-4 text-gray-600">Status</th>
                                <th class="text-left py-3 px-4 text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inactiveStaff as $staffMember): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($staffMember['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($staffMember['username']) ?></div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Created by: <?= htmlspecialchars($staffMember['creator_username'] ?? 'System') ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800">
                                            <?= !empty($staffMember['position']) ? htmlspecialchars($staffMember['position']) : 'No position' ?>
                                        </div>
                                        <?php if (!empty($staffMember['specialization'])): ?>
                                            <div class="text-sm text-blue-600 mt-1">
                                                <i class="fas fa-stethoscope mr-1"></i>
                                                <?= htmlspecialchars($staffMember['specialization']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($staffMember['license_number'])): ?>
                                            <div class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-id-card mr-1"></i>
                                                License: <?= htmlspecialchars($staffMember['license_number']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <i class="fas fa-times-circle mr-1"></i> Inactive
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 space-x-2">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" name="toggle_staff_status" 
                                                    class="bg-green-500 text-white px-4 py-2 rounded-full hover:bg-green-600 transition-colors text-sm font-medium"
                                                    onclick="return confirm('Are you sure you want to reactivate this account?')">
                                                <i class="fas fa-play mr-1"></i>Reactivate
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <button type="submit" name="hard_delete" 
                                                    class="bg-red-500 text-white px-4 py-2 rounded-full hover:bg-red-600 transition-colors text-sm font-medium"
                                                    onclick="return confirm('WARNING: This will permanently delete the account if no dependencies exist. Continue?')">
                                                <i class="fas fa-trash mr-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>