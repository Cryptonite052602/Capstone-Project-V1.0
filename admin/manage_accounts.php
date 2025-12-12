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
                // Check if username already exists among ALL accounts (both active and inactive)
                $stmt = $pdo->prepare("SELECT id FROM sitio1_staff WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['message'] = 'Username already exists. Please choose another.';
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
                // More specific error handling for duplicate entry
                if ($e->getCode() == 23000) {
                    $_SESSION['message'] = 'Username already exists. Please choose another.';
                    $_SESSION['message_type'] = 'error';
                } else {
                    $_SESSION['message'] = 'Error creating staff account: ' . $e->getMessage();
                    $_SESSION['message_type'] = 'error';
                }
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
                $_SESSION['message'] = 'Error updating staff account: ' . $e->getMessage();
                $_SESSION['message_type'] = 'error';
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
                $_SESSION['message'] = 'Cannot delete staff member because they have associated announcements. Please deactivate instead.';
                $_SESSION['message_type'] = 'error';
                header('Location: manage_accounts.php');
                exit();
            }
            
            // If no dependencies, proceed with deletion
            $stmt = $pdo->prepare("DELETE FROM sitio1_staff WHERE id = ?");
            $stmt->execute([$staffId]);
            
            $_SESSION['message'] = 'Staff account permanently deleted!';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_accounts.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error deleting staff account: ' . $e->getMessage();
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
    $_SESSION['message'] = 'Error fetching staff accounts: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
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
    <title>Staff Management - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ffffff;
            --gray: #95a5a6;
            --border: #e2e8f0;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px 10px 0 0;
        }

        .card-body {
            padding: 1.5rem;
            background: white;
            border-radius: 0 0 10px 10px;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: white;
            color: #2c3e50;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }

        .form-control-small {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: white;
            color: #2c3e50;
        }

        .form-control-small:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.75rem;
            font-weight: 500;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: white;
            color: var(--primary);
            border: 2px solid rgba(52, 152, 219, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: #f0f9ff;
            border-color: rgba(52, 152, 219, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-success {
            background: white;
            color: var(--success);
            border: 2px solid rgba(46, 204, 113, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-success:hover {
            background: #f0fdf4;
            border-color: rgba(46, 204, 113, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-warning {
            background: white;
            color: var(--warning);
            border: 2px solid rgba(243, 156, 18, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-warning:hover {
            background: #fef3c7;
            border-color: rgba(243, 156, 18, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-danger {
            background: white;
            color: var(--danger);
            border: 2px solid rgba(231, 76, 60, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            background: #fef2f2;
            border-color: rgba(231, 76, 60, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: white;
            color: var(--secondary);
            border: 2px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--gray);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-sm {
            padding: 0.625rem 1.25rem;
            font-size: 0.75rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
            background: white;
        }

        .badge-active {
            background: #d1fae5;
            color: #059669;
            border-color: #a7f3d0;
        }

        .badge-inactive {
            background: #f3f4f6;
            color: #6b7280;
            border-color: #e5e7eb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
            font-weight: 500;
        }

        .staff-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .staff-item:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
            background: #f8fafc;
        }

        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .staff-avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .staff-avatar-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .staff-avatar-secondary {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
        }

        .staff-avatar-success {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        .staff-avatar-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .staff-details {
            flex: 1;
        }

        .staff-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .staff-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .staff-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .form-container {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .staff-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }

        /* Message styling */
        .message-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #10b981;
            color: #065f46;
            border-radius: 10px;
        }

        .message-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #ef4444;
            color: #7f1d1d;
            border-radius: 10px;
        }

        /* Form container styling */
        .form-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-sm);
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-col {
            flex: 1;
        }

        .form-help {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.375rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background: #d1fae5;
            color: #059669;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        /* Position badges */
        .position-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
        }

        .position-doctor {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .position-nurse {
            background: #f0f9ff;
            color: #0c4a6e;
            border-color: #bae6fd;
        }

        .position-midwife {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .position-admin {
            background: #fdf2f8;
            color: #9d174d;
            border-color: #fbcfe8;
        }

        .position-other {
            background: #f8fafc;
            color: #475569;
            border-color: #e2e8f0;
        }

        /* Custom modal styling */
        .custom-modal {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            border: none;
            padding: 0;
            max-width: 800px;
            width: 90%;
        }

        .custom-modal::backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Form grid layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Search and filter */
        .search-container {
            position: relative;
            flex: 1;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            padding-left: 3rem;
            width: 100%;
        }

        .filter-select {
            min-width: 200px;
        }

        /* Info grid for staff details */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 0.875rem;
            color: #1e293b;
            font-weight: 500;
        }

        /* Active/Inactive tabs */
        .tab-container {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            margin-bottom: -2px;
        }

        .tab:hover {
            color: #334155;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Required field indicator */
        .required-field::after {
            content: " *";
            color: #ef4444;
        }

        /* Form action buttons */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 mt-16">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-secondary mb-2">Staff Account Management</h1>
            <p class="text-gray-600">Manage staff accounts and permissions for the health center</p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'message-error' : 'message-success' ?>">
                <i class="fas <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-3 text-xl"></i>
                <span class="font-medium"><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid mb-8">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_staff'] ?></div>
                <div class="stat-label">Total Staff Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_staff'] ?></div>
                <div class="stat-label">Active Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['inactive_staff'] ?></div>
                <div class="stat-label">Inactive Staff</div>
            </div>
        </div>

        <!-- Create Staff Account -->
        <div class="card mb-8">
            <div class="card-header">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-50 mr-4">
                            <i class="fas fa-user-plus text-xl" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-secondary">Create New Staff Account</h2>
                            <p class="text-sm text-gray-600">Add a new staff member to the system</p>
                        </div>
                    </div>
                    <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-medium">
                        New Staff
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="space-y-6">
                    <div class="form-container">
                        <!-- Account Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-user-circle"></i>
                                Account Information
                            </h3>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="username" class="form-label required-field">
                                        <i class="fas fa-user mr-2"></i>Username
                                    </label>
                                    <input type="text" id="username" name="username" required 
                                           class="form-control" placeholder="Enter username" 
                                           value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                                    <div class="form-help">
                                        <i class="fas fa-info-circle"></i>
                                        Must be unique across all accounts
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="form-label required-field">
                                        <i class="fas fa-lock mr-2"></i>Password
                                    </label>
                                    <input type="password" id="password" name="password" required 
                                           class="form-control" placeholder="Enter password">
                                    <div class="form-help">
                                        <i class="fas fa-shield-alt"></i>
                                        Minimum 8 characters recommended
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="full_name" class="form-label required-field">
                                        <i class="fas fa-id-card mr-2"></i>Full Name
                                    </label>
                                    <input type="text" id="full_name" name="full_name" required 
                                           class="form-control" placeholder="Enter full name" 
                                           value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Professional Information Section -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-briefcase-medical"></i>
                                Professional Information
                            </h3>
                            <div class="space-y-4">
                                <div class="form-group">
                                    <label for="position" class="form-label">
                                        <i class="fas fa-briefcase mr-2"></i>Position
                                    </label>
                                    <input type="text" id="position" name="position" 
                                           class="form-control-small" placeholder="e.g., Nurse, Doctor" 
                                           value="<?= isset($_POST['position']) ? htmlspecialchars($_POST['position']) : '' ?>">
                                    <div class="form-help">
                                        <i class="fas fa-question-circle"></i>
                                        Staff's role in the health center
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="specialization" class="form-label">
                                        <i class="fas fa-stethoscope mr-2"></i>Specialization
                                    </label>
                                    <input type="text" id="specialization" name="specialization" 
                                           class="form-control-small" placeholder="e.g., Pediatrics, Surgery" 
                                           value="<?= isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : '' ?>">
                                    <div class="form-help">
                                        <i class="fas fa-user-md"></i>
                                        Medical specialization (if applicable)
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="license_number" class="form-label">
                                        <i class="fas fa-id-card-alt mr-2"></i>License Number
                                    </label>
                                    <input type="text" id="license_number" name="license_number" 
                                           class="form-control-small" placeholder="Professional license number" 
                                           value="<?= isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : '' ?>">
                                    <div class="form-help">
                                        <i class="fas fa-certificate"></i>
                                        PRC or professional license number
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_staff" 
                                class="btn btn-success px-8 py-3">
                            <i class="fas fa-user-plus mr-2"></i>Create Staff Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Staff Accounts Tabs -->
        <div class="card mb-8">
            <div class="card-header">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-green-50 mr-4">
                            <i class="fas fa-users text-xl" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-secondary">Staff Accounts</h2>
                            <p class="text-sm text-gray-600">Manage all staff member accounts</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="px-3 py-1 bg-green-100 text-green-800 rounded-full font-medium">
                            <?= count($activeStaff) ?> active
                        </div>
                        <div class="px-3 py-1 bg-gray-100 text-gray-800 rounded-full font-medium">
                            <?= count($inactiveStaff) ?> inactive
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="px-6 pt-4">
                <div class="tab-container">
                    <div class="tab active" onclick="showTab('active')">
                        <i class="fas fa-user-check mr-2"></i>Active Staff
                        <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            <?= count($activeStaff) ?>
                        </span>
                    </div>
                    <div class="tab" onclick="showTab('inactive')">
                        <i class="fas fa-user-times mr-2"></i>Inactive Staff
                        <span class="ml-2 px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                            <?= count($inactiveStaff) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Active Staff -->
            <div id="active-tab" class="card-body">
                <?php if (empty($activeStaff)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-shield empty-icon"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Active Staff Accounts</h3>
                        <p class="text-gray-600 mb-6">There are no active staff accounts at the moment.</p>
                        <p class="text-sm text-gray-500">Create a new staff account using the form above</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($activeStaff as $staffMember): 
                            $initials = getInitials($staffMember['full_name']);
                            $positionClass = getPositionClass($staffMember['position'] ?? 'other');
                        ?>
                            <div class="staff-item">
                                <div class="staff-header">
                                    <div class="staff-info">
                                        <div class="staff-avatar staff-avatar-primary">
                                            <?= $initials ?>
                                        </div>
                                        <div class="staff-details">
                                            <h3 class="font-semibold text-lg text-secondary"><?= htmlspecialchars($staffMember['full_name']) ?></h3>
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <span class="text-sm text-gray-600">
                                                    <i class="fas fa-user mr-1"></i>@<?= htmlspecialchars($staffMember['username']) ?>
                                                </span>
                                                <?php if (!empty($staffMember['position'])): ?>
                                                    <span class="position-badge <?= $positionClass ?>">
                                                        <i class="fas fa-briefcase mr-1"></i><?= htmlspecialchars($staffMember['position']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($staffMember['specialization'])): ?>
                                                    <span class="text-sm text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                                        <i class="fas fa-stethoscope mr-1"></i><?= htmlspecialchars($staffMember['specialization']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="status-indicator status-active">
                                                    <i class="fas fa-check-circle"></i> ACTIVE
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="staff-actions">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" name="toggle_staff_status" 
                                                    class="btn btn-warning btn-sm"
                                                    onclick="return confirm('Are you sure you want to deactivate this staff account?')">
                                                <i class="fas fa-pause mr-2"></i>Deactivate
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="info-grid">
                                    <?php if (!empty($staffMember['license_number'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">License Number</span>
                                            <span class="info-value"><?= htmlspecialchars($staffMember['license_number']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <span class="info-label">Created By</span>
                                        <span class="info-value"><?= htmlspecialchars($staffMember['creator_username'] ?? 'System') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Account Created</span>
                                        <span class="info-value"><?= date('M j, Y g:i A', strtotime($staffMember['created_at'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status</span>
                                        <span class="info-value">
                                            <span class="status-indicator status-active">
                                                <i class="fas fa-circle mr-1"></i>Active
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Inactive Staff -->
            <div id="inactive-tab" class="card-body" style="display: none;">
                <?php if (empty($inactiveStaff)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-times empty-icon"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Inactive Staff Accounts</h3>
                        <p class="text-gray-600">All staff accounts are currently active.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($inactiveStaff as $staffMember): 
                            $initials = getInitials($staffMember['full_name']);
                            $positionClass = getPositionClass($staffMember['position'] ?? 'other');
                        ?>
                            <div class="staff-item">
                                <div class="staff-header">
                                    <div class="staff-info">
                                        <div class="staff-avatar staff-avatar-secondary">
                                            <?= $initials ?>
                                        </div>
                                        <div class="staff-details">
                                            <h3 class="font-semibold text-lg text-secondary"><?= htmlspecialchars($staffMember['full_name']) ?></h3>
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <span class="text-sm text-gray-600">
                                                    <i class="fas fa-user mr-1"></i>@<?= htmlspecialchars($staffMember['username']) ?>
                                                </span>
                                                <?php if (!empty($staffMember['position'])): ?>
                                                    <span class="position-badge <?= $positionClass ?>">
                                                        <i class="fas fa-briefcase mr-1"></i><?= htmlspecialchars($staffMember['position']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($staffMember['specialization'])): ?>
                                                    <span class="text-sm text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                                        <i class="fas fa-stethoscope mr-1"></i><?= htmlspecialchars($staffMember['specialization']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="status-indicator status-inactive">
                                                    <i class="fas fa-times-circle"></i> INACTIVE
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="staff-actions">
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" name="toggle_staff_status" 
                                                    class="btn btn-success btn-sm"
                                                    onclick="return confirm('Are you sure you want to reactivate this account?')">
                                                <i class="fas fa-play mr-2"></i>Reactivate
                                            </button>
                                        </form>
                                        
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="staff_id" value="<?= $staffMember['id'] ?>">
                                            <button type="submit" name="hard_delete" 
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('WARNING: This will permanently delete the account if no dependencies exist. Continue?')">
                                                <i class="fas fa-trash mr-2"></i>Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="info-grid">
                                    <?php if (!empty($staffMember['license_number'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">License Number</span>
                                            <span class="info-value"><?= htmlspecialchars($staffMember['license_number']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <span class="info-label">Created By</span>
                                        <span class="info-value"><?= htmlspecialchars($staffMember['creator_username'] ?? 'System') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Account Created</span>
                                        <span class="info-value"><?= date('M j, Y', strtotime($staffMember['created_at'])) ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Status</span>
                                        <span class="info-value">
                                            <span class="status-indicator status-inactive">
                                                <i class="fas fa-circle mr-1"></i>Inactive
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Update active tab
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            
            // Show corresponding content
            document.getElementById('active-tab').style.display = tabName === 'active' ? 'block' : 'none';
            document.getElementById('inactive-tab').style.display = tabName === 'inactive' ? 'block' : 'none';
        }
        
        // Confirm before taking sensitive actions
        document.addEventListener('DOMContentLoaded', function() {
            // Handle form submissions for sensitive actions
            document.querySelectorAll('form[class*="inline"]').forEach(form => {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.textContent;
                    const isDelete = submitBtn.textContent.includes('Delete');
                    const isDeactivate = submitBtn.textContent.includes('Deactivate');
                    
                    if (isDelete || isDeactivate) {
                        submitBtn.addEventListener('click', function(e) {
                            if (!form.dataset.confirmed) {
                                e.preventDefault();
                                const message = isDelete 
                                    ? 'WARNING: This will permanently delete the account if no dependencies exist. Continue?'
                                    : 'Are you sure you want to deactivate this staff account?';
                                
                                if (confirm(message)) {
                                    form.dataset.confirmed = true;
                                    submitBtn.click();
                                }
                            }
                        });
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to get initials from full name
function getInitials($fullName) {
    $names = explode(' ', $fullName);
    $initials = '';
    
    if (count($names) >= 2) {
        $initials = strtoupper(substr($names[0], 0, 1) . substr($names[count($names)-1], 0, 1));
    } else {
        $initials = strtoupper(substr($fullName, 0, 2));
    }
    
    return $initials;
}

// Helper function to get position class for styling
function getPositionClass($position) {
    $position = strtolower($position);
    
    if (str_contains($position, 'doctor') || str_contains($position, 'physician')) {
        return 'position-doctor';
    } elseif (str_contains($position, 'nurse')) {
        return 'position-nurse';
    } elseif (str_contains($position, 'midwife')) {
        return 'position-midwife';
    } elseif (str_contains($position, 'admin') || str_contains($position, 'manager')) {
        return 'position-admin';
    } else {
        return 'position-other';
    }
}