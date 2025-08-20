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

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
        <p class="text-gray-600 mt-2">Overview of system statistics and activities</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Staff Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <i class="fas fa-user-shield text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Staff</h3>
                    <p class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($stats['total_staff']) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Registered health staff</p>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100">
                <a href="/community-health-tracker/admin/staff" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                    View all staff
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
        
        <!-- Users Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                    <p class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($stats['total_users']) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Registered community users</p>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100">
                <a href="/community-health-tracker/admin/users" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                    Manage users
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
        
        <!-- Patients Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <i class="fas fa-procedures text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                    <p class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($stats['total_patients']) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Patient records</p>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100">
                <a href="/community-health-tracker/admin/patients" class="text-purple-600 hover:text-purple-800 text-sm font-medium flex items-center">
                    View patients
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
        
        <!-- Pending Approvals Card -->
        <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                    <i class="fas fa-clock text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                    <p class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($stats['pending_approvals']) ?></p>
                    <p class="text-sm text-gray-500 mt-1">Awaiting approval</p>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100">
                <a href="/community-health-tracker/admin/approvals" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium flex items-center">
                    Review approvals
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
                <i class="fas fa-history text-xl text-gray-600 mr-3"></i>
                <h2 class="text-xl font-semibold text-gray-800">Recent Activities</h2>
            </div>
            <a href="/community-health-tracker/admin/activities" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                View all activities
            </a>
        </div>
        
        <div class="space-y-4">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM sitio1_activities ORDER BY created_at DESC LIMIT 5");
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($activities) > 0) {
                    foreach ($activities as $activity) {
                        echo '<div class="flex items-start pb-4 border-b border-gray-100 last:border-0 last:pb-0">';
                        echo '    <div class="p-2 bg-gray-100 rounded-full mr-4">';
                        echo '        <i class="fas ' . htmlspecialchars($activity['icon'] ?? 'fa-info-circle') . ' text-gray-600"></i>';
                        echo '    </div>';
                        echo '    <div class="flex-1">';
                        echo '        <p class="text-gray-800">' . htmlspecialchars($activity['description']) . '</p>';
                        echo '        <p class="text-sm text-gray-500 mt-1">' . htmlspecialchars($activity['created_at']) . '</p>';
                        echo '    </div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="text-gray-600 py-4 text-center">No recent activities found.</p>';
                }
            } catch (PDOException $e) {
                echo '<p class="text-red-500 py-4">Unable to load recent activities.</p>';
                error_log("Activities error: " . $e->getMessage());
            }
            ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
            <i class="fas fa-bolt text-yellow-500 mr-3"></i>
            Quick Actions
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="/community-health-tracker/admin/add-staff" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg border border-blue-100 transition-colors duration-200 flex items-center">
                <div class="bg-blue-100 text-blue-600 p-3 rounded-full mr-4">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">Add New Staff</h3>
                    <p class="text-sm text-gray-600">Register health personnel</p>
                </div>
            </a>
            
            <a href="/community-health-tracker/admin/generate-report" class="bg-green-50 hover:bg-green-100 p-4 rounded-lg border border-green-100 transition-colors duration-200 flex items-center">
                <div class="bg-green-100 text-green-600 p-3 rounded-full mr-4">
                    <i class="fas fa-file-export"></i>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">Generate Report</h3>
                    <p class="text-sm text-gray-600">Export system data</p>
                </div>
            </a>
            
            <a href="/community-health-tracker/admin/settings" class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg border border-purple-100 transition-colors duration-200 flex items-center">
                <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-4">
                    <i class="fas fa-cog"></i>
                </div>
                <div>
                    <h3 class="font-medium text-gray-800">System Settings</h3>
                    <p class="text-sm text-gray-600">Configure application</p>
                </div>
            </a>
        </div>
    </div>
</div>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>