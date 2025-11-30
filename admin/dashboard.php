<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get stats for dashboard
$stats = [
    'total_staff' => 0,
    'total_users' => 0,
    'total_patients' => 0,
    'pending_approvals' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff");
    $stats['total_staff'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_patients");
    $stats['total_patients'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE");
    $stats['pending_approvals'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch dashboard statistics. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Community Health Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        /* Quick action cards */
        .quick-action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Admin Dashboard
            </h1>
            <p class="text-gray-600">Overview of system statistics and activities</p>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Staff Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                        <i class="fas fa-user-shield text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Staff</h3>
                </div>
                <p class="text-3xl font-bold text-blue-600 mb-2"><?= $stats['total_staff'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Registered health staff</p>
                <a href="/community-health-tracker/admin/staffrecords.php" class="text-blue-600 text-sm font-medium hover:underline">View all staff</a>
            </div>
            
            <!-- Users Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                </div>
                <p class="text-3xl font-bold text-green-600 mb-2"><?= $stats['total_users'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Registered community users</p>
                <a href="/community-health-tracker/admin/registeredusers.php" class="text-blue-600 text-sm font-medium hover:underline">Manage users</a>
            </div>
            
            <!-- Patients Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center mr-4">
                        <i class="fas fa-procedures text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                </div>
                <p class="text-3xl font-bold text-purple-600 mb-2"><?= $stats['total_patients'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Patient records</p>
                <a href="/community-health-tracker/admin/viewpatients.php" class="text-blue-600 text-sm font-medium hover:underline">View patients</a>
            </div>
            
            <!-- Pending Approvals Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                </div>
                <p class="text-3xl font-bold text-yellow-600 mb-2"><?= $stats['pending_approvals'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Awaiting approval</p>
                <a href="/community-health-tracker/admin/approvals.php" class="text-blue-600 text-sm font-medium hover:underline">Review approvals</a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities Section -->
            <div class="stats-card lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold flex items-center text-blue-600">
                        <i class="fas fa-history text-blue-600 mr-2"></i> Recent Activities
                    </h2>
                    <a href="/community-health-tracker/admin/activities" class="text-blue-600 text-sm hover:underline">View all activities</a>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-user-plus text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">New staff member registered</p>
                            <p class="text-gray-500 text-sm">2 hours ago</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-user-check text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">User account approved</p>
                            <p class="text-gray-500 text-sm">5 hours ago</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-file-medical text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">New patient record added</p>
                            <p class="text-gray-500 text-sm">Yesterday</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-stethoscope text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">Health check-up completed</p>
                            <p class="text-gray-500 text-sm">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="stats-card">
                <h2 class="text-xl font-semibold mb-6 flex items-center text-blue-600">
                    <i class="fas fa-bolt text-blue-600 mr-2"></i> Quick Actions
                </h2>
                
                <div class="space-y-4">
                    <a href="staff_docs.php" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-blue-600 flex items-center justify-center mr-4">
                            <i class="fas fa-user-plus text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Add New Staff</h3>
                            <p class="text-gray-500 text-sm">Register health personnel</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-green-600 flex items-center justify-center mr-4">
                            <i class="fas fa-file-export text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Generate Report</h3>
                            <p class="text-gray-500 text-sm">Export system data</p>
                        </div>
                    </a>
                    
                    <a href="/community-health-tracker/admin/settings" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-purple-600 flex items-center justify-center mr-4">
                            <i class="fas fa-cog text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">System Settings</h3>
                            <p class="text-gray-500 text-sm">Configure application</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    
</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>