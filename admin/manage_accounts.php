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
        
        if (!empty($username) && !empty($password) && !empty($fullName)) {
            try {
                // Check if username already exists among active accounts
                $stmt = $pdo->prepare("SELECT id FROM sitio1_staff WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = 'Username already exists. Please choose another.';
                    header('Location: manage_accounts.php');
                    exit();
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO sitio1_staff (username, password, full_name, position, created_by, status, is_active) 
                                      VALUES (?, ?, ?, ?, ?, 'active', 1)");
                $stmt->execute([$username, $hashedPassword, $fullName, $position, $_SESSION['user']['id']]);
                
                $_SESSION['success'] = 'Staff account created successfully!';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error creating staff account: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Please fill in all required fields.';
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
                
                $_SESSION['success'] = 'Staff account ' . $action . 'd successfully!';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error updating staff account: ' . $e->getMessage();
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
                $_SESSION['error'] = 'Cannot delete staff member because they have associated announcements. Please deactivate instead.';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // If no dependencies, proceed with deletion
            $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
            $stmt->execute([$staffId]);
            
            $_SESSION['success'] = 'Staff account permanently deleted!';
            header('Location: manage_accounts.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error deleting staff account: ' . $e->getMessage();
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
    $_SESSION['error'] = 'Error fetching staff accounts: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Manage Staff Accounts</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 gap-8">
        <!-- Create Staff Account -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h2 class="text-xl font-semibold mb-4">Create Staff Account</h2>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 mb-2">Username *</label>
                        <input type="text" id="username" name="username" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 mb-2">Password *</label>
                        <input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div class="mb-4">
                    <label for="position" class="block text-gray-700 mb-2">Position</label>
                    <input type="text" id="position" name="position" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <button type="submit" name="create_staff" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">Create Staff Account</button>
            </form>
        </div>
    </div>
    
    <!-- Active Staff Accounts List -->
    <div class="bg-white p-6 rounded-lg shadow mt-8">
        <h2 class="text-xl font-semibold mb-4">Active Staff Accounts</h2>
        
        <?php if (empty($activeStaff)): ?>
            <p class="text-gray-600">No active staff accounts found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Full Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Position</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Created At</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Created By</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeStaff as $staffMember): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['username']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['full_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['position'] ?? 'N/A') ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $staffMember['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= ucfirst($staffMember['status'] ?? 'active') ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y h:i A', strtotime($staffMember['created_at'])) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?= htmlspecialchars($staffMember['creator_username'] ?? 'System') ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 space-x-2">
                                    <?php if ($staffMember['status'] === 'active'): ?>
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" name="toggle_staff_status" class="text-yellow-600 hover:underline" onclick="return confirm('Are you sure you want to deactivate this staff account?')">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" name="toggle_staff_status" class="text-green-600 hover:underline" onclick="return confirm('Are you sure you want to activate this staff account?')">Activate</button>
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
    
    <!-- Inactive Staff Accounts List -->
    <div class="bg-white p-6 rounded-lg shadow mt-8">
        <h2 class="text-xl font-semibold mb-4">Inactive Staff Accounts</h2>
        
        <?php if (empty($inactiveStaff)): ?>
            <p class="text-gray-600">No inactive staff accounts found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Full Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Position</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inactiveStaff as $staffMember): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['username']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['full_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($staffMember['position'] ?? 'N/A') ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full <?= $staffMember['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= ucfirst($staffMember['status'] ?? 'inactive') ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200 space-x-2">
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" name="toggle_staff_status" class="text-green-600 hover:underline" onclick="return confirm('Are you sure you want to reactivate this account?')">Reactivate</button>
                                    </form>
                                    
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                        <button type="submit" name="hard_delete" class="text-red-600 hover:underline" onclick="return confirm('WARNING: This will permanently delete the account if no dependencies exist. Continue?')">Permanently Delete</button>
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

