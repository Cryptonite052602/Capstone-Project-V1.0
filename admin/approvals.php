<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get filter status
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

// Fetch users based on filter
try {
    if ($filter === 'pending') {
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE status = 'pending' ORDER BY created_at DESC");
    } elseif ($filter === 'approved') {
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE status = 'approved' ORDER BY created_at DESC");
    } elseif ($filter === 'declined') {
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE status = 'declined' ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users ORDER BY created_at DESC");
    }
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch users. Please try again later.";
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approvals - Community Health Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

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

        .user-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fef3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .status-declined {
            background-color: #f8d7da;
            color: #721c24;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .filter-btn.active {
            background-color: #3b82f6;
            color: white;
        }

        .filter-btn:not(.active) {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .filter-btn:not(.active):hover {
            background-color: #e5e7eb;
        }
    </style>
</head>
<body class="bg-gray-100">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                User Approvals
            </h1>
            <a href="/community-health-tracker/admin/dashboard" class="text-blue-600 hover:underline flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['success_message'] ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Pending Approvals Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                </div>
                <p class="text-3xl font-bold text-yellow-600 mb-2">
                    <?php 
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'pending'");
                        echo $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo "0";
                    }
                    ?>
                </p>
                <p class="text-gray-500 text-sm mb-4">Awaiting approval</p>
            </div>
            
            <!-- Approved Users Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                        <i class="fas fa-user-check text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Approved Users</h3>
                </div>
                <p class="text-3xl font-bold text-green-600 mb-2">
                    <?php 
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'approved'");
                        echo $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo "0";
                    }
                    ?>
                </p>
                <p class="text-gray-500 text-sm mb-4">Verified residents</p>
            </div>
            
            <!-- Declined Users Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4">
                        <i class="fas fa-user-times text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Declined Users</h3>
                </div>
                <p class="text-3xl font-bold text-red-600 mb-2">
                    <?php 
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE status = 'declined'");
                        echo $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo "0";
                    }
                    ?>
                </p>
                <p class="text-gray-500 text-sm mb-4">Not approved</p>
            </div>
            
            <!-- Total Users Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                </div>
                <p class="text-3xl font-bold text-blue-600 mb-2">
                    <?php 
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users");
                        echo $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        echo "0";
                    }
                    ?>
                </p>
                <p class="text-gray-500 text-sm mb-4">All registered users</p>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="stats-card mb-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center text-blue-600">
                <i class="fas fa-filter text-blue-600 mr-2"></i> Filter Users
            </h2>
            <div class="flex space-x-4">
                <a href="?filter=pending" class="filter-btn <?= $filter === 'pending' ? 'active' : '' ?>">
                    <i class="fas fa-clock mr-2"></i> Pending Approval
                </a>
                <a href="?filter=approved" class="filter-btn <?= $filter === 'approved' ? 'active' : '' ?>">
                    <i class="fas fa-user-check mr-2"></i> Approved Users
                </a>
                <a href="?filter=declined" class="filter-btn <?= $filter === 'declined' ? 'active' : '' ?>">
                    <i class="fas fa-user-times mr-2"></i> Declined Users
                </a>
                <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">
                    <i class="fas fa-users mr-2"></i> All Users
                </a>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="stats-card">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold flex items-center text-blue-600">
                    <?php if ($filter === 'pending'): ?>
                        <i class="fas fa-clock text-blue-600 mr-2"></i> Pending Approvals
                    <?php elseif ($filter === 'approved'): ?>
                        <i class="fas fa-user-check text-blue-600 mr-2"></i> Approved Users
                    <?php elseif ($filter === 'declined'): ?>
                        <i class="fas fa-user-times text-blue-600 mr-2"></i> Declined Users
                    <?php else: ?>
                        <i class="fas fa-users text-blue-600 mr-2"></i> All Users
                    <?php endif; ?>
                </h2>
                <p class="text-gray-600"><?= count($users) ?> user(s) found</p>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-users text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-500 mb-2">No users found</h3>
                    <p class="text-gray-500">There are no users matching your current filter.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                <div class="flex items-start mb-4 md:mb-0">
                                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover">
                                        <?php else: ?>
                                            <i class="fas fa-user text-gray-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($user['full_name']) ?></h3>
                                        <p class="text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span class="status-badge status-<?= $user['status'] ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                            <?php if ($user['id_verified']): ?>
                                                <span class="status-badge" style="background-color: #d1f2eb; color: #0d9488;">
                                                    <i class="fas fa-id-card mr-1"></i> ID Verified
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:items-end">
                                    <p class="text-gray-500 text-sm mb-2">Registered: <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                                    
                                    <?php if ($user['status'] === 'approved' && !empty($user['verified_at'])): ?>
                                        <p class="text-green-600 text-sm font-medium">
                                            <i class="fas fa-check-circle mr-1"></i> Approved on <?= date('M j, Y', strtotime($user['verified_at'])) ?>
                                        </p>
                                    <?php elseif ($user['status'] === 'declined'): ?>
                                        <p class="text-red-600 text-sm font-medium">
                                            <i class="fas fa-times-circle mr-1"></i> Declined
                                        </p>
                                    <?php else: ?>
                                        <p class="text-yellow-600 text-sm font-medium">
                                            <i class="fas fa-clock mr-1"></i> Awaiting Approval
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- User Details -->
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-500">Contact</p>
                                        <p class="font-medium"><?= !empty($user['contact']) ? htmlspecialchars($user['contact']) : 'Not provided' ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Address</p>
                                        <p class="font-medium"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not provided' ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Sitio</p>
                                        <p class="font-medium"><?= !empty($user['sitio']) ? htmlspecialchars($user['sitio']) : 'Not provided' ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Verification Method</p>
                                        <p class="font-medium"><?= str_replace('_', ' ', ucfirst($user['verification_method'])) ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($user['id_image_path'])): ?>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-500 mb-2">ID Document</p>
                                        <a href="<?= htmlspecialchars($user['id_image_path']) ?>" target="_blank" class="inline-flex items-center text-blue-600 hover:underline">
                                            <i class="fas fa-external-link-alt mr-1"></i> View ID Document
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user['verification_notes'])): ?>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-500 mb-2">Verification Notes</p>
                                        <p class="text-gray-700 bg-gray-50 p-3 rounded-md"><?= htmlspecialchars($user['verification_notes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>