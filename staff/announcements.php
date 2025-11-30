<?php
// staff_announcements.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../staff/notification_functions.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$staffId = $_SESSION['user']['id'];
$error = '';
$success = '';

// Handle form submission for new announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $audience_type = isset($_POST['audience_type']) ? $_POST['audience_type'] : 'public';
    $target_users = isset($_POST['target_users']) ? (is_array($_POST['target_users']) ? array_filter($_POST['target_users']) : []) : [];
    
    if ($audience_type === 'specific' && empty($target_users)) {
        $error = 'Please select at least one user for specific announcement.';
    } elseif (!empty($title) && !empty($message)) {
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/announcements/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($file_extension), $allowed_ext)) {
                $file_name = uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $file_path)) {
                    $image_path = '/community-health-tracker/uploads/announcements/' . $file_name;
                }
            }
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO sitio1_announcements 
                                  (staff_id, title, message, priority, expiry_date, status, audience_type, image_path, post_date) 
                                  VALUES (?, ?, ?, ?, ?, 'active', ?, ?, NOW())");
            $stmt->execute([$staffId, $title, $message, $priority, $expiry_date, $audience_type, $image_path]);
            
            $announcementId = $pdo->lastInsertId();
            
            // Handle target users if specific audience
            if ($audience_type === 'specific' && !empty($target_users)) {
                foreach ($target_users as $userId) {
                    $stmt = $pdo->prepare("INSERT INTO announcement_targets (announcement_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$announcementId, $userId]);
                }
                
                createTargetedAnnouncementNotification($announcementId, $title, $target_users);
                $success = 'Message sent to ' . count($target_users) . ' user(s) successfully!';
            } else {
                createAnnouncementNotification($announcementId, $title);
                $success = 'Message broadcasted to all users successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error sending message: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle other operations (edit, delete, archive, repost) - same as before
// [Previous code for edit, delete, archive, repost operations]

// Get all announcements by this staff
$activeAnnouncements = [];
$archivedAnnouncements = [];

try {
    $stmt = $pdo->prepare("SELECT a.*, 
                          COUNT(CASE WHEN ua.status = 'accepted' THEN 1 END) as accepted_count,
                          COUNT(CASE WHEN ua.status = 'dismissed' THEN 1 END) as dismissed_count,
                          COUNT(CASE WHEN ua.status IS NULL THEN 1 END) as pending_count
                          FROM sitio1_announcements a
                          LEFT JOIN sitio1_users u ON u.approved = TRUE
                          LEFT JOIN user_announcements ua ON ua.announcement_id = a.id AND ua.user_id = u.id
                          WHERE a.staff_id = ? AND a.status = 'active'
                          GROUP BY a.id
                          ORDER BY a.post_date DESC");
    $stmt->execute([$staffId]);
    $activeAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM sitio1_announcements 
                          WHERE staff_id = ? AND status = 'archived' 
                          ORDER BY post_date DESC");
    $stmt->execute([$staffId]);
    $archivedAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed responses
    foreach ($activeAnnouncements as &$announcement) {
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, ua.response_date 
                              FROM user_announcements ua
                              JOIN sitio1_users u ON ua.user_id = u.id
                              WHERE ua.announcement_id = ? AND ua.status = 'accepted'
                              ORDER BY ua.response_date DESC");
        $stmt->execute([$announcement['id']]);
        $announcement['accepted_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, ua.response_date 
                              FROM user_announcements ua
                              JOIN sitio1_users u ON ua.user_id = u.id
                              WHERE ua.announcement_id = ? AND ua.status = 'dismissed'
                              ORDER BY ua.response_date DESC");
        $stmt->execute([$announcement['id']]);
        $announcement['dismissed_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("SELECT u.id, u.full_name 
                              FROM sitio1_users u
                              WHERE u.approved = TRUE AND u.id NOT IN (
                                  SELECT user_id FROM user_announcements 
                                  WHERE announcement_id = ?
                              )");
        $stmt->execute([$announcement['id']]);
        $announcement['pending_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($announcement['audience_type'] === 'specific') {
            $stmt = $pdo->prepare("SELECT u.id, u.full_name 
                                  FROM announcement_targets at
                                  JOIN sitio1_users u ON at.user_id = u.id
                                  WHERE at.announcement_id = ?");
            $stmt->execute([$announcement['id']]);
            $announcement['target_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $error = 'Error fetching messages: ' . $e->getMessage();
}

// Get all users for targeting
$allUsers = [];
try {
    $stmt = $pdo->prepare("SELECT id, full_name, username FROM sitio1_users WHERE approved = TRUE AND status = 'approved' ORDER BY full_name");
    $stmt->execute();
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Messenger - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-card {
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        .priority-high { border-left-color: #dc3545; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-normal { border-left-color: #28a745; }
        .tab-active {
            border-bottom: 3px solid #007bff;
            color: #007bff;
            font-weight: 600;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .announcement-bubble {
            max-width: 80%;
            border-radius: 18px;
            padding: 12px 16px;
            margin-bottom: 8px;
            position: relative;
        }
        .sent {
            background: #007bff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        .custom-radio {
            position: relative;
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #fff;
            border: 2px solid #ccc;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }
        .custom-radio.checked {
            border-color: #007bff;
        }
        .custom-radio.checked:after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 10px;
            height: 10px;
            background-color: #007bff;
            border-radius: 50%;
        }
        .custom-checkbox {
            position: relative;
            display: inline-block;
            width: 18px;
            height: 18px;
            background-color: #fff;
            border: 2px solid #ccc;
            border-radius: 3px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .custom-checkbox.checked {
            border-color: #007bff;
            background-color: #007bff;
        }
        .custom-checkbox.checked:after {
            content: '✓';
            position: absolute;
            top: -1px;
            left: 2px;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6 max-w-6xl">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-3 rounded-full mr-4">
                        <i class="fas fa-bullhorn text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Community Announcements</h1>
                        <p class="text-gray-600">Share updates and information with community members</p>
                    </div>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Active Announcements</p>
                        <p class="text-2xl font-bold text-blue-600"><?= count($activeAnnouncements) ?></p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">Community Members</p>
                        <p class="text-2xl font-bold text-green-600"><?= count($allUsers) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md flex items-start">
                <i class="fas fa-exclamation-circle text-red-500 mt-0.5 mr-3"></i>
                <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md flex items-start">
                <i class="fas fa-check-circle text-green-500 mt-0.5 mr-3"></i>
                <p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Sidebar - New Announcement -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm p-6 sticky top-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Create Announcement
                    </h2>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" name="title" required placeholder="Announcement title" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                            <textarea name="message" rows="4" required placeholder="Type your announcement here..." 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="normal">Normal</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                                <input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                        
                        <?php
// staff_announcements.php - Updated section for audience selection
?>

<!-- In the staff announcement form -->
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
    <div class="space-y-2">
        <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
            <input type="radio" name="audience_type" value="public" class="h-4 w-4 text-blue-600" onchange="toggleUserSelection()">
            <span class="ml-3">
                <span class="font-medium text-gray-800">All Members</span>
                <span class="text-xs text-gray-500 block">Broadcast to registered users</span>
            </span>
        </label>
        
        <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
            <input type="radio" name="audience_type" value="landing_page" checked class="h-4 w-4 text-blue-600" onchange="toggleUserSelection()">
            <span class="ml-3">
                <span class="font-medium text-gray-800">Landing Page</span>
                <span class="text-xs text-gray-500 block">Visible to all website visitors</span>
            </span>
        </label>
        
        <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
            <input type="radio" name="audience_type" value="specific" class="h-4 w-4 text-blue-600" onchange="toggleUserSelection()">
            <span class="ml-3">
                <span class="font-medium text-gray-800">Specific Users</span>
                <span class="text-xs text-gray-500 block">Select recipients</span>
            </span>
        </label>
    </div>
</div>
                        
                        <div id="user-selection" class="hidden bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h4 class="font-medium text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-users text-blue-600 mr-2"></i>
                                Select Recipients
                            </h4>
                            
                            <input type="text" id="user-search" placeholder="Search members..." 
                                   class="w-full px-3 py-2 border border-blue-300 rounded-lg mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            
                            <div class="border border-blue-200 rounded-lg p-3 max-h-48 overflow-y-auto bg-white">
                                <?php if (empty($allUsers)): ?>
                                    <p class="text-gray-500 text-sm text-center py-2">No users available</p>
                                <?php else: ?>
                                    <div class="space-y-2" id="user-list">
                                        <?php foreach ($allUsers as $user): ?>
                                            <label class="flex items-center p-2 hover:bg-gray-100 rounded cursor-pointer transition user-item">
                                                <span class="custom-checkbox" onclick="toggleCheckbox(this)"></span>
                                                <input type="checkbox" name="target_users[]" value="<?= $user['id'] ?>" class="hidden user-checkbox">
                                                <span class="ml-2 text-sm">
                                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($user['full_name']) ?></span>
                                                    <span class="text-gray-500 text-xs">@<?= htmlspecialchars($user['username']) ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-600 mt-2" id="selected-count">0 users selected</p>
                        </div>
                        
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-blue-400 transition cursor-pointer" onclick="document.getElementById('announcement_image').click()">
                            <input type="file" id="announcement_image" name="announcement_image" accept="image/*" class="hidden" onchange="updateImageName(this)">
                            <i class="fas fa-image text-gray-400 text-xl mb-2"></i>
                            <p class="text-gray-600 font-medium text-sm" id="image-name">Add Image (optional)</p>
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF • Max 2MB</p>
                        </div>
                        
                        <button type="submit" name="post_announcement" 
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition transform hover:scale-105 flex items-center justify-center shadow-md">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Publish Announcement
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Main Content - Announcement History -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Tabs -->
                    <div class="border-b border-gray-200 bg-white">
                        <nav class="flex -mb-px">
                            <button id="active-tab" class="tab-active py-4 px-6 text-center font-medium text-sm border-b-2 border-blue-500 text-blue-600">
                                <i class="fas fa-bell mr-2"></i>
                                Active (<?= count($activeAnnouncements) ?>)
                            </button>
                            <button id="archived-tab" class="py-4 px-6 text-center font-medium text-sm text-gray-500 hover:text-gray-700 border-b-2 border-transparent">
                                <i class="fas fa-archive mr-2"></i>
                                Archived (<?= count($archivedAnnouncements) ?>)
                            </button>
                        </nav>
                    </div>
                    
                    <!-- Active Announcements -->
                    <div id="active-messages" class="p-6 space-y-6 max-h-[600px] overflow-y-auto">
                        <?php if (empty($activeAnnouncements)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-bullhorn text-gray-300 text-5xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No active announcements</h3>
                                <p class="text-gray-500">Create your first announcement to get started</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeAnnouncements as $announcement): ?>
                                <div class="message-card bg-white rounded-lg shadow-sm p-5 
                                    priority-<?= $announcement['priority'] ?>">
                                    
                                    <!-- Announcement Header -->
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-start space-x-3">
                                            <div class="user-avatar bg-gradient-to-r from-blue-500 to-purple-600 flex-shrink-0">
                                                <?= strtoupper(substr($_SESSION['user']['full_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($announcement['title']) ?></h3>
                                                <div class="flex items-center text-sm text-gray-500 mt-1 space-x-4">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?= $_SESSION['user']['full_name'] ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?>
                                                    </span>
                                                    <span class="flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                                        <?= $announcement['audience_type'] === 'public' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                                                        <?= $announcement['audience_type'] === 'public' ? '🌍 Everyone' : '👥 Selected Users' ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="relative group">
                                            <button class="p-2 rounded-full hover:bg-gray-100 transition">
                                                <i class="fas fa-ellipsis-v text-gray-500"></i>
                                            </button>
                                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden group-hover:block border border-gray-200">
                                                <button onclick="openEditModal(<?= $announcement['id'] ?>, '<?= htmlspecialchars(addslashes($announcement['title'])) ?>', `<?= htmlspecialchars(addslashes($announcement['message'])) ?>`, '<?= $announcement['priority'] ?>', '<?= $announcement['expiry_date'] ?>', '<?= $announcement['audience_type'] ?>', '<?= $announcement['image_path'] ?>')" 
                                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                                                    <i class="fas fa-edit mr-2"></i> Edit
                                                </button>
                                                <button onclick="openDeleteModal(<?= $announcement['id'] ?>)" 
                                                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                                                    <i class="fas fa-archive mr-2"></i> Archive
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Announcement Content -->
                                    <div class="mb-4 pl-12">
                                        <div class="announcement-bubble sent">
                                            <p class="text-white whitespace-pre-wrap"><?= htmlspecialchars($announcement['message']) ?></p>
                                            
                                            <?php if ($announcement['image_path']): ?>
                                                <div class="mt-3 rounded-lg overflow-hidden">
                                                    <img src="<?= $announcement['image_path'] ?>" alt="Announcement Image" 
                                                         class="max-w-xs rounded-lg cursor-pointer hover:opacity-90 transition"
                                                         onclick="openImageModal('<?= $announcement['image_path'] ?>')">
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="message-time text-blue-100 text-right">
                                                <?= date('h:i A', strtotime($announcement['post_date'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Response Stats -->
                                    <div class="flex items-center justify-between pt-4 border-t border-gray-200 pl-12">
                                        <div class="flex space-x-6">
                                            <div class="text-center">
                                                <div class="text-green-600 font-bold text-lg"><?= $announcement['accepted_count'] ?></div>
                                                <div class="text-xs text-gray-500">👍 Accepted</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-yellow-600 font-bold text-lg"><?= $announcement['pending_count'] ?></div>
                                                <div class="text-xs text-gray-500">⏳ Pending</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-red-600 font-bold text-lg"><?= $announcement['dismissed_count'] ?></div>
                                                <div class="text-xs text-gray-500">👎 Dismissed</div>
                                            </div>
                                        </div>
                                        
                                        <button onclick="openResponsesModal(<?= $announcement['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center bg-blue-50 px-3 py-2 rounded-lg transition">
                                            <i class="fas fa-chart-bar mr-2"></i>
                                            View Responses
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Archived Announcements -->
                    <div id="archived-messages" class="p-6 space-y-4 hidden max-h-[600px] overflow-y-auto">
                        <?php if (empty($archivedAnnouncements)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-archive text-gray-300 text-5xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No archived announcements</h3>
                                <p class="text-gray-500">Archived announcements will appear here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($archivedAnnouncements as $announcement): ?>
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 opacity-75 hover:opacity-100 transition">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-3 mb-2">
                                                <div class="user-avatar bg-gray-400 text-xs">
                                                    <?= strtoupper(substr($_SESSION['user']['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <h3 class="font-semibold text-gray-700"><?= htmlspecialchars($announcement['title']) ?></h3>
                                                    <div class="flex items-center text-xs text-gray-500 space-x-3">
                                                        <span><?= date('M d, Y', strtotime($announcement['post_date'])) ?></span>
                                                        <span>•</span>
                                                        <span><?= $announcement['audience_type'] === 'public' ? 'Everyone' : 'Selected Users' ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-gray-600 line-clamp-2 text-sm ml-12"><?= htmlspecialchars($announcement['message']) ?></p>
                                        </div>
                                        
                                        <div class="ml-4 flex-shrink-0 flex gap-2">
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                                <button type="submit" name="repost_announcement" 
                                                        class="p-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-600 transition" 
                                                        title="Repost" onclick="return confirm('Repost this announcement?')">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </form>
                                            <button onclick="openPermanentDeleteModal(<?= $announcement['id'] ?>)" 
                                                    class="p-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition" 
                                                    title="Delete Permanently">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.getElementById('active-tab').addEventListener('click', function() {
            document.getElementById('active-messages').classList.remove('hidden');
            document.getElementById('archived-messages').classList.add('hidden');
            document.getElementById('active-tab').classList.add('tab-active');
            document.getElementById('archived-tab').classList.remove('tab-active');
        });
        
        document.getElementById('archived-tab').addEventListener('click', function() {
            document.getElementById('active-messages').classList.add('hidden');
            document.getElementById('archived-messages').classList.remove('hidden');
            document.getElementById('active-tab').classList.remove('tab-active');
            document.getElementById('archived-tab').classList.add('tab-active');
        });
        
        // Custom radio button functionality
        function toggleRadio(element) {
            const parent = element.parentElement;
            const radioInput = parent.querySelector('input[type="radio"]');
            
            // Uncheck all radios in the same group
            document.querySelectorAll(`input[name="${radioInput.name}"]`).forEach(input => {
                input.checked = false;
                input.parentElement.querySelector('.custom-radio').classList.remove('checked');
            });
            
            // Check the clicked radio
            radioInput.checked = true;
            element.classList.add('checked');
            
            // Toggle user selection visibility
            toggleUserSelection();
        }
        
        // Custom checkbox functionality
        function toggleCheckbox(element) {
            const parent = element.parentElement;
            const checkbox = parent.querySelector('input[type="checkbox"]');
            
            checkbox.checked = !checkbox.checked;
            element.classList.toggle('checked', checkbox.checked);
            
            updateSelectedUserCount();
        }
        
        // Toggle user selection
        function toggleUserSelection() {
            const audienceType = document.querySelector('input[name="audience_type"]:checked').value;
            const userSelection = document.getElementById('user-selection');
            
            if (audienceType === 'specific') {
                userSelection.classList.remove('hidden');
                updateSelectedUserCount();
            } else {
                userSelection.classList.add('hidden');
            }
        }
        
        // Update image name
        function updateImageName(input) {
            if (input.files && input.files[0]) {
                document.getElementById('image-name').textContent = '✓ ' + input.files[0].name;
            }
        }
        
        // Update selected user count
        function updateSelectedUserCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selected-count').textContent = count + ' user' + (count !== 1 ? 's' : '') + ' selected';
        }
        
        // User search
        document.addEventListener('DOMContentLoaded', function() {
            const userSearch = document.getElementById('user-search');
            if (userSearch) {
                userSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const userItems = document.querySelectorAll('#user-list .user-item');
                    
                    userItems.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
                    });
                });
            }
            
            // Initialize custom radio buttons
            document.querySelectorAll('.custom-radio').forEach(radio => {
                const input = radio.parentElement.querySelector('input[type="radio"]');
                if (input.checked) {
                    radio.classList.add('checked');
                }
            });
            
            // Initialize custom checkboxes
            document.querySelectorAll('.custom-checkbox').forEach(checkbox => {
                const input = checkbox.parentElement.querySelector('input[type="checkbox"]');
                if (input.checked) {
                    checkbox.classList.add('checked');
                }
            });
        });

        // Modal functions (implement as needed)
        function openEditModal(id, title, message, priority, expiryDate, audienceType, imagePath) {
            // Your edit modal implementation
            console.log('Edit modal for:', id);
        }

        function openDeleteModal(id) {
            // Your delete modal implementation
            console.log('Delete modal for:', id);
        }

        function openResponsesModal(announcementId) {
            // Your responses modal implementation
            console.log('Responses modal for:', announcementId);
        }

        function openImageModal(imageSrc) {
            // Your image modal implementation
            console.log('Image modal for:', imageSrc);
        }

        function openPermanentDeleteModal(id) {
            // Your permanent delete modal implementation
            console.log('Permanent delete modal for:', id);
        }
    </script>
</body>
</html>