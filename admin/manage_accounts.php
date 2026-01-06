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
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT id FROM sitio1_staff WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['message'] = 'Username already exists.';
                    $_SESSION['message_type'] = 'error';
                    header('Location: manage_accounts.php');
                    exit();
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO sitio1_staff (username, password, full_name, position, specialization, license_number, created_by, status, is_active) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1)");
                $stmt->execute([$username, $hashedPassword, $fullName, $position, $specialization, $license_number, $_SESSION['user_id']]);
                
                $_SESSION['message'] = 'Staff account created successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        } else {
            $_SESSION['message'] = 'Please fill in all required fields.';
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
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
                
                $_SESSION['message'] = 'Staff account ' . $action . 'd successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: manage_accounts.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Error updating account: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
        }
    } elseif (isset($_POST['hard_delete'])) {
        $staffId = intval($_POST['staff_id']);
        
        try {
            // Check for dependencies
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_announcements WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['message'] = 'Cannot delete - staff has associated announcements.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
            $stmt->execute([$staffId]);
            
            $_SESSION['message'] = 'Staff account deleted!';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error deleting account: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            header('Location: manage_accounts.php');
            exit();
        }
    }
}

// Get all staff accounts
$activeStaff = [];
$inactiveStaff = [];

try {
    // Active staff
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 1
                         ORDER BY s.created_at DESC");
    $activeStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Inactive staff
    $stmt = $pdo->query("SELECT s.*, creator.username as creator_username 
                         FROM sitio1_staff s
                         LEFT JOIN sitio1_staff creator ON s.created_by = creator.id
                         WHERE s.is_active = 0
                         ORDER BY s.created_at DESC");
    $inactiveStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['message'] = 'Error loading staff: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8fafc;
        }
        .card {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .tab {
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 500;
        }
        .tab.active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-6 mt-16">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Staff Account Management</h1>
            <p class="text-gray-600 text-sm">Create and manage staff accounts</p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 rounded-lg flex items-center <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
                <i class="fas <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-3"></i>
                <span><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <!-- Create Staff Form -->
        <div class="card mb-6 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Create New Staff Account</h2>
            <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" name="username" required 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="Username">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" name="password" required 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="Password">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="full_name" required 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="Full Name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" name="position" 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="e.g., Nurse, Doctor">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                    <input type="text" name="specialization" 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="e.g., Pediatrics">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">License Number</label>
                    <input type="text" name="license_number" 
                           class="w-full p-2 border border-gray-300 rounded" 
                           placeholder="License number">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" name="create_staff" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Staff Accounts -->
        <div class="card">
            <div class="p-6 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Staff Accounts</h2>
                <div class="flex space-x-4 mt-2">
                    <div class="tab active" onclick="showTab('active')">
                        Active Staff (<?= count($activeStaff) ?>)
                    </div>
                    <div class="tab" onclick="showTab('inactive')">
                        Inactive Staff (<?= count($inactiveStaff) ?>)
                    </div>
                </div>
            </div>
            
            <!-- Active Staff -->
            <div id="active-tab" class="p-6">
                <?php if (empty($activeStaff)): ?>
                    <p class="text-gray-500 text-center py-4">No active staff accounts.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($activeStaff as $staff): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($staff['full_name']) ?></h3>
                                        <p class="text-sm text-gray-600">@<?= htmlspecialchars($staff['username']) ?></p>
                                    </div>
                                    <span class="badge badge-success">Active</span>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-3">
                                    <?php if ($staff['position']): ?>
                                        <div>
                                            <span class="text-gray-500">Position:</span>
                                            <span class="font-medium ml-1"><?= htmlspecialchars($staff['position']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($staff['specialization']): ?>
                                        <div>
                                            <span class="text-gray-500">Specialization:</span>
                                            <span class="font-medium ml-1"><?= htmlspecialchars($staff['specialization']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="text-gray-500">Created:</span>
                                        <span class="font-medium ml-1"><?= date('M j, Y', strtotime($staff['created_at'])) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">By:</span>
                                        <span class="font-medium ml-1"><?= htmlspecialchars($staff['creator_username'] ?? 'System') ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <input type="hidden" name="action" value="deactivate">
                                        <button type="submit" name="toggle_staff_status" 
                                                class="btn btn-warning text-sm"
                                                onclick="return confirm('Deactivate this account?')">
                                            <i class="fas fa-pause"></i> Deactivate
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inactive Staff -->
            <div id="inactive-tab" class="p-6" style="display: none;">
                <?php if (empty($inactiveStaff)): ?>
                    <p class="text-gray-500 text-center py-4">No inactive staff accounts.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($inactiveStaff as $staff): ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($staff['full_name']) ?></h3>
                                        <p class="text-sm text-gray-600">@<?= htmlspecialchars($staff['username']) ?></p>
                                    </div>
                                    <span class="badge badge-error">Inactive</span>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-3">
                                    <?php if ($staff['position']): ?>
                                        <div>
                                            <span class="text-gray-500">Position:</span>
                                            <span class="font-medium ml-1"><?= htmlspecialchars($staff['position']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="text-gray-500">Created:</span>
                                        <span class="font-medium ml-1"><?= date('M j, Y', strtotime($staff['created_at'])) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">By:</span>
                                        <span class="font-medium ml-1"><?= htmlspecialchars($staff['creator_username'] ?? 'System') ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" name="toggle_staff_status" 
                                                class="btn btn-success text-sm"
                                                onclick="return confirm('Reactivate this account?')">
                                            <i class="fas fa-play"></i> Activate
                                        </button>
                                    </form>
                                    
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                        <button type="submit" name="hard_delete" 
                                                class="btn btn-danger text-sm"
                                                onclick="return confirm('Permanently delete this account?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            
            // Show content
            document.getElementById('active-tab').style.display = tabName === 'active' ? 'block' : 'none';
            document.getElementById('inactive-tab').style.display = tabName === 'inactive' ? 'block' : 'none';
        }
    </script>
</body>
</html>