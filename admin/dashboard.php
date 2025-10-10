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
    </style>
</head>
<body class="bg-gray-50">
    
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-2">Admin Dashboard</h1>
        <p class="text-gray-600 mb-6">Overview of system statistics and activities</p>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Staff Card -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                        <i class="fas fa-user-shield text-blue-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Staff</h3>
                </div>
                <p class="text-3xl font-bold text-blue-600 mb-2"><?= $stats['total_staff'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Registered health staff</p>
                <a href="/community-health-tracker/admin/staff" class="text-blue-600 text-sm font-medium hover:underline">View all staff</a>
            </div>
            
            <!-- Users Card -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                        <i class="fas fa-users text-green-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                </div>
                <p class="text-3xl font-bold text-green-600 mb-2"><?= $stats['total_users'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Registered community users</p>
                <a href="/community-health-tracker/admin/users" class="text-blue-600 text-sm font-medium hover:underline">Manage users</a>
            </div>
            
            <!-- Patients Card -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                        <i class="fas fa-procedures text-purple-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                </div>
                <p class="text-3xl font-bold text-purple-600 mb-2"><?= $stats['total_patients'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Patient records</p>
                <a href="/community-health-tracker/admin/patients" class="text-blue-600 text-sm font-medium hover:underline">View patients</a>
            </div>
            
            <!-- Pending Approvals Card -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                </div>
                <p class="text-3xl font-bold text-yellow-600 mb-2"><?= $stats['pending_approvals'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Awaiting approval</p>
                <a href="/community-health-tracker/admin/approvals" class="text-blue-600 text-sm font-medium hover:underline">Review approvals</a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities Section -->
            <div class="bg-white p-6 rounded-lg shadow lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold flex items-center">
                        <i class="fas fa-history text-gray-600 mr-2"></i> Recent Activities
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
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold mb-6 flex items-center">
                    <i class="fas fa-bolt text-gray-600 mr-2"></i> Quick Actions
                </h2>
                
                <div class="space-y-4">
                    <a href="staff_docs.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center mr-3">
                            <i class="fas fa-user-plus text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Add New Staff</h3>
                            <p class="text-gray-500 text-sm">Register health personnel</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="w-10 h-10 rounded-lg bg-green-600 flex items-center justify-center mr-3">
                            <i class="fas fa-file-export text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Generate Report</h3>
                            <p class="text-gray-500 text-sm">Export system data</p>
                        </div>
                    </a>
                    
                    <a href="/community-health-tracker/admin/settings" class="flex items-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="w-10 h-10 rounded-lg bg-purple-600 flex items-center justify-center mr-3">
                            <i class="fas fa-cog text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">System Settings</h3>
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