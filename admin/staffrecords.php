<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$records_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $records_per_page;

// Build base query conditions
$where_conditions = ["1=1"];
$params = [];
$count_params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR full_name LIKE ? OR position LIKE ? OR specialization LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $count_params = array_merge($count_params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filter === 'active') {
    $where_conditions[] = "status = 'active' AND is_active = 1";
} elseif ($filter === 'inactive') {
    $where_conditions[] = "(status = 'inactive' OR is_active = 0)";
}

// Build WHERE clause
$where_clause = implode(" AND ", $where_conditions);

// Count query
$count_query = "SELECT COUNT(*) as total FROM sitio1_staff WHERE $where_clause";

// Main query with creator information
$query = "SELECT s.*, u.username as created_by_username 
          FROM sitio1_staff s 
          LEFT JOIN sitio1_users u ON s.created_by = u.id 
          WHERE $where_clause 
          ORDER BY s.created_at DESC 
          LIMIT $records_per_page OFFSET $offset";

// Get total count for pagination
try {
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Count Query Error: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
}

// Get staff for current page
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Main Query Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch staff. Please try again later.";
    $staff = [];
}

// Get stats for dashboard
$stats = [
    'total_staff' => 0,
    'active_staff' => 0,
    'inactive_staff' => 0
];

try {
    // Total staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff");
    $stats['total_staff'] = $stmt->fetchColumn();
    
    // Active staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff WHERE status = 'active' AND is_active = 1");
    $stats['active_staff'] = $stmt->fetchColumn();
    
    // Inactive staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff WHERE status = 'inactive' OR is_active = 0");
    $stats['inactive_staff'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats Query Error: " . $e->getMessage());
}
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
                Staff Management
            </h1>
            <a href="/community-health-tracker/admin/dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-full hover:bg-blue-700 transition-colors flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
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
        
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-800">Filter Staff</h2>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search Form -->
                    <form method="GET" class="flex">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search staff..." 
                               class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                        <input type="hidden" name="filter" value="<?= $filter ?>">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    
                    <!-- Status Filter -->
                    <div class="flex space-x-2">
                        <a href="?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            All Staff
                        </a>
                        <a href="?filter=active<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Active
                        </a>
                        <a href="?filter=inactive<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'inactive' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Inactive
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Staff List -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold text-gray-800">
                    <?php 
                    if ($filter === 'active') {
                        echo "Active Staff Members";
                    } elseif ($filter === 'inactive') {
                        echo "Inactive Staff Members";
                    } else {
                        echo "All Staff Members";
                    }
                    ?>
                </h2>
                <div class="text-gray-600">
                    Showing <?= count($staff) ?> of <?= $total_records ?> staff member(s)
                </div>
            </div>
            
            <?php if (empty($staff)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-user-shield text-gray-300 text-4xl mb-3"></i>
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">No staff members found</h3>
                    <p class="text-gray-500">There are no staff members matching your current filter.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-gray-600">Staff Information</th>
                                <th class="text-left py-3 px-4 text-gray-600">Position & Specialization</th>
                                <th class="text-left py-3 px-4 text-gray-600">Status</th>
                                <th class="text-left py-3 px-4 text-gray-600">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff as $staff_member): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($staff_member['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($staff_member['username']) ?></div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Created by: <?= !empty($staff_member['created_by_username']) ? htmlspecialchars($staff_member['created_by_username']) : 'System' ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800">
                                            <?= !empty($staff_member['position']) ? htmlspecialchars($staff_member['position']) : 'No position' ?>
                                        </div>
                                        <?php if (!empty($staff_member['specialization'])): ?>
                                            <div class="text-sm text-blue-600 mt-1">
                                                <i class="fas fa-stethoscope mr-1"></i>
                                                <?= htmlspecialchars($staff_member['specialization']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($staff_member['license_number'])): ?>
                                            <div class="text-xs text-green-600 mt-1">
                                                <i class="fas fa-id-card mr-1"></i>
                                                License: <?= htmlspecialchars($staff_member['license_number']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($staff_member['status'] === 'active' && $staff_member['is_active'] == 1): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-times-circle mr-1"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div class="text-xs text-gray-500 mt-2">
                                            <?php if ($staff_member['is_active'] == 1): ?>
                                                <span class="text-green-600">✓ Account Active</span>
                                            <?php else: ?>
                                                <span class="text-red-600">✗ Account Disabled</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-700">
                                            <?= date('M j, Y', strtotime($staff_member['created_at'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($staff_member['created_at'])) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Circular Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex flex-col sm:flex-row justify-between items-center mt-6 pt-6 border-t border-gray-200 space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-600">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </div>
                    <div class="flex items-center space-x-2">
                        <!-- Previous Button -->
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&filter=<?= $filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                               class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-10 h-10 flex items-center justify-center bg-gray-100 text-gray-400 rounded-full cursor-not-allowed">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <div class="flex space-x-1">
                            <?php 
                            // Show page numbers
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            // Show first page if not in range
                            if ($start_page > 1) {
                                echo '<a href="?page=1&filter=' . $filter . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                                      class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="w-10 h-10 flex items-center justify-center text-gray-500">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="?page=<?= $i ?>&filter=<?= $filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                                   class="w-10 h-10 flex items-center justify-center rounded-full transition-colors <?= $i == $current_page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; 
                            
                            // Show last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="w-10 h-10 flex items-center justify-center text-gray-500">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&filter=' . $filter . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                                      class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>

                        <!-- Next Button -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&filter=<?= $filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                               class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="w-10 h-10 flex items-center justify-center bg-gray-100 text-gray-400 rounded-full cursor-not-allowed">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>