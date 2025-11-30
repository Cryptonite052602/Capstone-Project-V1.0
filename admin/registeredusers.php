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
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

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
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR sitio LIKE ? OR contact LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $count_params = array_merge($count_params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filter === 'pending') {
    $where_conditions[] = "status = 'pending'";
} elseif ($filter === 'approved') {
    $where_conditions[] = "status = 'approved'";
} elseif ($filter === 'declined') {
    $where_conditions[] = "status = 'declined'";
}

if ($role_filter === 'patient') {
    $where_conditions[] = "role = 'patient'";
} elseif ($role_filter === 'staff') {
    $where_conditions[] = "role = 'staff'";
} elseif ($role_filter === 'admin') {
    $where_conditions[] = "role = 'admin'";
}

// Build WHERE clause
$where_clause = implode(" AND ", $where_conditions);

// Count query
$count_query = "SELECT COUNT(*) as total FROM sitio1_users WHERE $where_clause";

// Main query
$query = "SELECT * FROM sitio1_users 
          WHERE $where_clause 
          ORDER BY created_at DESC 
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

// Get users for current page
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Main Query Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch users. Please try again later.";
    $users = [];
}

// Get stats for dashboard
$stats = [
    'total_users' => 0,
    'pending_users' => 0,
    'approved_users' => 0,
    'declined_users' => 0,
    'verified_users' => 0
];

try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Pending users
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'pending'");
    $stats['pending_users'] = $stmt->fetchColumn();
    
    // Approved users
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'approved'");
    $stats['approved_users'] = $stmt->fetchColumn();
    
    // Declined users
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'declined'");
    $stats['declined_users'] = $stmt->fetchColumn();
    
    // ID Verified users
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE id_verified = 1");
    $stats['verified_users'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats Query Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Community Health Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                User Management
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <!-- Total Users Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Users</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_users'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Pending Users Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Pending Approval</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['pending_users'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Approved Users Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Approved Users</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['approved_users'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Declined Users Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                        <i class="fas fa-user-times text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Declined Users</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['declined_users'] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Verified Users Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-teal-100 text-teal-600 mr-4">
                        <i class="fas fa-id-card text-xl"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">ID Verified</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['verified_users'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-800">Filter Users</h2>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search Form -->
                    <form method="GET" class="flex">
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search users..." 
                               class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent w-full">
                        <input type="hidden" name="filter" value="<?= $filter ?>">
                        <input type="hidden" name="role" value="<?= $role_filter ?>">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    
                    <!-- Status Filter -->
                    <div class="flex space-x-2">
                        <a href="?filter=all&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            All Status
                        </a>
                        <a href="?filter=pending&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Pending
                        </a>
                        <a href="?filter=approved&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'approved' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Approved
                        </a>
                        <a href="?filter=declined&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $filter === 'declined' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Declined
                        </a>
                    </div>
                    
                    <!-- Role Filter -->
                    <div class="flex space-x-2">
                        <a href="?filter=<?= $filter ?>&role=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $role_filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            All Roles
                        </a>
                        <a href="?filter=<?= $filter ?>&role=patient<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $role_filter === 'patient' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Patients
                        </a>
                        <a href="?filter=<?= $filter ?>&role=staff<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $role_filter === 'staff' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Staff
                        </a>
                        <a href="?filter=<?= $filter ?>&role=admin<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="px-4 py-2 rounded-full <?= $role_filter === 'admin' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                            Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-semibold text-gray-800">
                    <?php 
                    if ($filter === 'pending') {
                        echo "Pending Users";
                    } elseif ($filter === 'approved') {
                        echo "Approved Users";
                    } elseif ($filter === 'declined') {
                        echo "Declined Users";
                    } else {
                        echo "All Users";
                    }
                    
                    if ($role_filter === 'patient') {
                        echo " - Patients";
                    } elseif ($role_filter === 'staff') {
                        echo " - Staff";
                    } elseif ($role_filter === 'admin') {
                        echo " - Administrators";
                    }
                    ?>
                </h2>
                <div class="text-gray-600">
                    Showing <?= count($users) ?> of <?= $total_records ?> user(s)
                </div>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-users text-gray-300 text-4xl mb-3"></i>
                    <h3 class="text-lg font-semibold text-gray-500 mb-2">No users found</h3>
                    <p class="text-gray-500">There are no users matching your current filter.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-gray-600">User Info</th>
                                <th class="text-left py-3 px-4 text-gray-600">Role</th>
                                <th class="text-left py-3 px-4 text-gray-600">Contact & Address</th>
                                <th class="text-left py-3 px-4 text-gray-600">Verification</th>
                                <th class="text-left py-3 px-4 text-gray-600">Status</th>
                                <th class="text-left py-3 px-4 text-gray-600">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($user['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">@<?= htmlspecialchars($user['username']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= $user['age'] ?? 'N/A' ?> • <?= ucfirst($user['gender'] ?? 'Not specified') ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-shield-alt mr-1"></i> Admin
                                            </span>
                                        <?php elseif ($user['role'] === 'staff'): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-user-md mr-1"></i> Staff
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-user mr-1"></i> Patient
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($user['specialization'])): ?>
                                            <div class="text-xs text-gray-600 mt-1">
                                                <?= htmlspecialchars($user['specialization']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-700">
                                            <?= !empty($user['contact']) ? htmlspecialchars($user['contact']) : 'No contact' ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= !empty($user['sitio']) ? htmlspecialchars($user['sitio']) : 'No sitio' ?>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?= !empty($user['civil_status']) ? ucfirst($user['civil_status']) : 'N/A' ?> • 
                                            <?= !empty($user['occupation']) ? htmlspecialchars($user['occupation']) : 'N/A' ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="space-y-1">
                                            <?php if ($user['id_verified']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-id-card mr-1"></i> ID Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    <i class="fas fa-id-card mr-1"></i> Not Verified
                                                </span>
                                            <?php endif; ?>
                                            
                                            <div class="text-xs text-gray-500">
                                                Method: <?= str_replace('_', ' ', ucfirst($user['verification_method'])) ?>
                                            </div>
                                            
                                            <?php if (!empty($user['id_image_path'])): ?>
                                                <a href="<?= htmlspecialchars($user['id_image_path']) ?>" target="_blank" 
                                                   class="inline-flex items-center text-xs text-blue-600 hover:underline">
                                                    <i class="fas fa-external-link-alt mr-1"></i> View ID
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($user['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i> Approved
                                            </span>
                                            <?php if ($user['verified_at']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <?= date('M j, Y', strtotime($user['verified_at'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-clock mr-1"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <i class="fas fa-times-circle mr-1"></i> Declined
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($user['verification_notes'])): ?>
                                            <div class="text-xs text-gray-600 mt-1">
                                                Note: <?= htmlspecialchars(substr($user['verification_notes'], 0, 50)) ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-sm text-gray-700">
                                            <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= date('g:i A', strtotime($user['created_at'])) ?>
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
                            <a href="?page=<?= $current_page - 1 ?>&filter=<?= $filter ?>&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
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
                                echo '<a href="?page=1&filter=' . $filter . '&role=' . $role_filter . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                                      class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="w-10 h-10 flex items-center justify-center text-gray-500">...</span>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="?page=<?= $i ?>&filter=<?= $filter ?>&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                                   class="w-10 h-10 flex items-center justify-center rounded-full transition-colors <?= $i == $current_page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; 
                            
                            // Show last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="w-10 h-10 flex items-center justify-center text-gray-500">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&filter=' . $filter . '&role=' . $role_filter . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                                      class="w-10 h-10 flex items-center justify-center bg-gray-200 text-gray-700 rounded-full hover:bg-gray-300 transition-colors">' . $total_pages . '</a>';
                            }
                            ?>
                        </div>

                        <!-- Next Button -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&filter=<?= $filter ?>&role=<?= $role_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
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