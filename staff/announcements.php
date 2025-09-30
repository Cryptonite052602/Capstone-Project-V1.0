<?php
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
    $audience_type = $_POST['audience_type']; // 'public' or 'specific'
    $target_users = isset($_POST['target_users']) ? $_POST['target_users'] : [];
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/announcements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $file_path)) {
            $image_path = '/community-health-tracker/uploads/announcements/' . $file_name;
        }
    }
    
    if (!empty($title) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sitio1_announcements 
                                  (staff_id, title, message, priority, expiry_date, status, audience_type, image_path) 
                                  VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
            $stmt->execute([$staffId, $title, $message, $priority, $expiry_date, $audience_type, $image_path]);
            
            $announcementId = $pdo->lastInsertId();
            
            // Handle target users if specific audience
            if ($audience_type === 'specific' && !empty($target_users)) {
                foreach ($target_users as $userId) {
                    $stmt = $pdo->prepare("INSERT INTO announcement_targets (announcement_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$announcementId, $userId]);
                }
                
                // Create notification for specific users
                createTargetedAnnouncementNotification($announcementId, $title, $target_users);
                $success = 'Announcement posted successfully! Notifications sent to selected users.';
            } else {
                // Create notification for all users (public)
                createAnnouncementNotification($announcementId, $title);
                $success = 'Announcement posted successfully! Notifications sent to all users.';
            }
        } catch (PDOException $e) {
            $error = 'Error posting announcement: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'normal';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $audience_type = $_POST['audience_type'];
    $target_users = isset($_POST['target_users']) ? $_POST['target_users'] : [];
    
    // Handle image upload
    $image_path = $_POST['current_image'] ?? null;
    if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/announcements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old image if exists
        if ($image_path) {
            $old_image_path = __DIR__ . '/..' . $image_path;
            if (file_exists($old_image_path)) {
                unlink($old_image_path);
            }
        }
        
        $file_extension = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $file_path)) {
            $image_path = '/community-health-tracker/uploads/announcements/' . $file_name;
        }
    }
    
    if (!empty($title) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("UPDATE sitio1_announcements 
                                  SET title = ?, message = ?, priority = ?, expiry_date = ?, audience_type = ?, image_path = ?
                                  WHERE id = ? AND staff_id = ?");
            $stmt->execute([$title, $message, $priority, $expiry_date, $audience_type, $image_path, $id, $staffId]);
            
            // Update target users if specific audience
            if ($audience_type === 'specific') {
                // Remove existing targets
                $stmt = $pdo->prepare("DELETE FROM announcement_targets WHERE announcement_id = ?");
                $stmt->execute([$id]);
                
                // Add new targets
                if (!empty($target_users)) {
                    foreach ($target_users as $userId) {
                        $stmt = $pdo->prepare("INSERT INTO announcement_targets (announcement_id, user_id) VALUES (?, ?)");
                        $stmt->execute([$id, $userId]);
                    }
                }
            } else {
                // Remove all targets for public announcements
                $stmt = $pdo->prepare("DELETE FROM announcement_targets WHERE announcement_id = ?");
                $stmt->execute([$id]);
            }
            
            $success = 'Announcement updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating announcement: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle delete/archive announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $id = $_POST['id'];
    $action = $_POST['delete_action']; // 'archive' or 'permanent'
    
    try {
        if ($action === 'archive') {
            $stmt = $pdo->prepare("UPDATE sitio1_announcements SET status = 'archived' WHERE id = ? AND staff_id = ?");
            $stmt->execute([$id, $staffId]);
            
            // Mark all related notifications as read
            markAnnouncementNotificationsAsRead($id);
            
            $success = 'Announcement archived successfully! It can be reposted later.';
        } else {
            // First delete targets
            $stmt = $pdo->prepare("DELETE FROM announcement_targets WHERE announcement_id = ?");
            $stmt->execute([$id]);
            
            // Then delete responses
            $stmt = $pdo->prepare("DELETE FROM user_announcements WHERE announcement_id = ?");
            $stmt->execute([$id]);
            
            // Then delete notifications
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE related_id = ? AND type = 'announcement'");
            $stmt->execute([$id]);
            
            // Delete image if exists
            $stmt = $pdo->prepare("SELECT image_path FROM sitio1_announcements WHERE id = ?");
            $stmt->execute([$id]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($announcement && $announcement['image_path']) {
                $image_path = __DIR__ . '/..' . $announcement['image_path'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            // Finally delete announcement
            $stmt = $pdo->prepare("DELETE FROM sitio1_announcements WHERE id = ? AND staff_id = ?");
            $stmt->execute([$id, $staffId]);
            
            $success = 'Announcement permanently deleted!';
        }
    } catch (PDOException $e) {
        $error = 'Error processing announcement: ' . $e->getMessage();
    }
}

// Handle repost announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repost_announcement'])) {
    $id = $_POST['id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE sitio1_announcements 
                              SET status = 'active', post_date = NOW() 
                              WHERE id = ? AND staff_id = ?");
        $stmt->execute([$id, $staffId]);
        
        // Clear previous responses
        $stmt = $pdo->prepare("DELETE FROM user_announcements WHERE announcement_id = ?");
        $stmt->execute([$id]);
        
        // Get announcement details for notification
        $stmt = $pdo->prepare("SELECT title, audience_type FROM sitio1_announcements WHERE id = ?");
        $stmt->execute([$id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($announcement) {
            if ($announcement['audience_type'] === 'specific') {
                // Get target users
                $stmt = $pdo->prepare("SELECT user_id FROM announcement_targets WHERE announcement_id = ?");
                $stmt->execute([$id]);
                $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Create notifications for specific users
                createTargetedAnnouncementNotification($id, $announcement['title'], $target_users);
            } else {
                // Create notifications for all users
                createAnnouncementNotification($id, $announcement['title']);
            }
        }
        
        $success = 'Announcement reposted successfully! New notifications have been sent.';
    } catch (PDOException $e) {
        $error = 'Error reposting announcement: ' . $e->getMessage();
    }
}

// Get all announcements by this staff
$activeAnnouncements = [];
$archivedAnnouncements = [];

try {
    // Get active announcements with user response details
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
    
    // Get archived announcements
    $stmt = $pdo->prepare("SELECT * FROM sitio1_announcements 
                          WHERE staff_id = ? AND status = 'archived' 
                          ORDER BY post_date DESC");
    $stmt->execute([$staffId]);
    $archivedAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get detailed responses for active announcements
    foreach ($activeAnnouncements as &$announcement) {
        // Get users who accepted
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, ua.response_date 
                              FROM user_announcements ua
                              JOIN sitio1_users u ON ua.user_id = u.id
                              WHERE ua.announcement_id = ? AND ua.status = 'accepted'
                              ORDER BY ua.response_date DESC");
        $stmt->execute([$announcement['id']]);
        $announcement['accepted_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get users who dismissed
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, ua.response_date 
                              FROM user_announcements ua
                              JOIN sitio1_users u ON ua.user_id = u.id
                              WHERE ua.announcement_id = ? AND ua.status = 'dismissed'
                              ORDER BY ua.response_date DESC");
        $stmt->execute([$announcement['id']]);
        $announcement['dismissed_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get pending users
        $stmt = $pdo->prepare("SELECT u.id, u.full_name 
                              FROM sitio1_users u
                              WHERE u.approved = TRUE AND u.id NOT IN (
                                  SELECT user_id FROM user_announcements 
                                  WHERE announcement_id = ?
                              )");
        $stmt->execute([$announcement['id']]);
        $announcement['pending_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get target users if specific audience
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
    $error = 'Error fetching announcements: ' . $e->getMessage();
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

<div class="container mx-auto px-4 py-6 max-w-7xl">
    <!-- Header Section -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Community Announcements</h1>
        <p class="text-gray-600">Create and manage announcements for the community</p>
    </div>
    
    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-700"><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
            <button id="createTab" class="tab-button border-b-2 border-blue-500 text-blue-600 font-medium py-4 px-1" onclick="switchMainTab('create')">
                Create Announcement
            </button>
            <button id="activeTab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium py-4 px-1" onclick="switchMainTab('active')">
                Active Announcements
            </button>
            <button id="archivedTab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium py-4 px-1" onclick="switchMainTab('archived')">
                Archived
            </button>
        </nav>
    </div>
    
    <!-- Create Announcement Tab -->
    <div id="createContent" class="main-tab-content">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-5 bg-white border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Create New Announcement</h2>
                <p class="mt-1 text-sm text-gray-500">Share important information with the community</p>
            </div>
            <div class="p-6">
                <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" id="title" name="title" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required placeholder="Enter announcement title">
                        </div>
                        
                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select id="priority" name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                        <textarea id="message" name="message" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required placeholder="Enter detailed message"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label for="announcement_image" class="block text-sm font-medium text-gray-700 mb-1">Image (optional)</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="announcement_image" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Upload an image</span>
                                            <input id="announcement_image" name="announcement_image" type="file" class="sr-only" accept="image/*">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date (optional)</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Audience</label>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input id="audience-public" name="audience_type" type="radio" value="public" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 audience-type-radio" onchange="toggleUserSelection()">
                                        <label for="audience-public" class="ml-3 block text-sm font-medium text-gray-700">Public (All Users)</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="audience-specific" name="audience_type" type="radio" value="specific" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 audience-type-radio" onchange="toggleUserSelection()">
                                        <label for="audience-specific" class="ml-3 block text-sm font-medium text-gray-700">Specific Users</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="user-selection" class="hidden">
                                <label for="user-search" class="block text-sm font-medium text-gray-700 mb-1">Select Users</label>
                                <input type="text" id="user-search" placeholder="Search users..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2">
                                <div class="border border-gray-300 rounded-md p-3 max-h-40 overflow-y-auto bg-gray-50">
                                    <?php foreach ($allUsers as $user): ?>
                                        <div class="flex items-center mb-2 last:mb-0">
                                            <input id="user-<?= $user['id'] ?>" name="target_users[]" type="checkbox" value="<?= $user['id'] ?>" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded user-checkbox">
                                            <label for="user-<?= $user['id'] ?>" class="ml-2 block text-sm text-gray-700"><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)</label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200 flex justify-end">
                        <button type="submit" name="post_announcement" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            Post Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Active Announcements Tab -->
    <div id="activeContent" class="main-tab-content hidden">
        <?php if (empty($activeAnnouncements)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No active announcements</h3>
                <p class="mt-2 text-sm text-gray-500">Get started by creating your first announcement.</p>
                <div class="mt-6">
                    <button onclick="switchMainTab('create')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Create Announcement
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-5">
                <?php foreach ($activeAnnouncements as $announcement): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center mb-2">
                                        <?php if ($announcement['priority'] == 'urgent'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">URGENT</span>
                                        <?php elseif ($announcement['priority'] == 'high'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 mr-2">HIGH</span>
                                        <?php elseif ($announcement['priority'] == 'low'): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">LOW</span>
                                        <?php endif; ?>
                                        <h3 class="text-lg font-medium text-gray-900 truncate"><?= htmlspecialchars($announcement['title']) ?></h3>
                                    </div>
                                    
                                    <div class="flex flex-wrap items-center text-sm text-gray-500 mb-4">
                                        <span>Posted on <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?></span>
                                        <span class="mx-2">•</span>
                                        <span>Audience: <?= $announcement['audience_type'] === 'public' ? 'Public' : 'Specific Users' ?></span>
                                        <?php if ($announcement['expiry_date']): ?>
                                            <span class="mx-2">•</span>
                                            <span>Expires: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4">
                                            <img src="<?= $announcement['image_path'] ?>" alt="Announcement Image" class="max-w-full h-auto rounded-lg max-h-60 object-cover">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-gray-700 whitespace-pre-line mb-4"><?= htmlspecialchars($announcement['message']) ?></p>
                                    
                                    <?php if ($announcement['audience_type'] === 'specific' && isset($announcement['target_users'])): ?>
                                        <div class="mb-4">
                                            <p class="text-sm font-medium text-gray-700 mb-1">Targeted to:</p>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($announcement['target_users'] as $target): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        <?= htmlspecialchars($target['full_name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Response Summary -->
                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <h4 class="text-sm font-medium text-gray-700 mb-3">Response Summary</h4>
                                        <div class="grid grid-cols-3 gap-4 text-center mb-3">
                                            <div class="bg-green-50 p-3 rounded-md">
                                                <p class="text-2xl font-bold text-green-700"><?= $announcement['accepted_count'] ?></p>
                                                <p class="text-xs text-green-600">Accepted</p>
                                            </div>
                                            <div class="bg-yellow-50 p-3 rounded-md">
                                                <p class="text-2xl font-bold text-yellow-700"><?= $announcement['pending_count'] ?></p>
                                                <p class="text-xs text-yellow-600">Pending</p>
                                            </div>
                                            <div class="bg-red-50 p-3 rounded-md">
                                                <p class="text-2xl font-bold text-red-700"><?= $announcement['dismissed_count'] ?></p>
                                                <p class="text-xs text-red-600">Dismissed</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Progress bar -->
                                        <?php 
                                        $totalResponses = $announcement['accepted_count'] + $announcement['dismissed_count'] + $announcement['pending_count'];
                                        $acceptedPercent = $totalResponses > 0 ? ($announcement['accepted_count'] / $totalResponses) * 100 : 0;
                                        $dismissedPercent = $totalResponses > 0 ? ($announcement['dismissed_count'] / $totalResponses) * 100 : 0;
                                        $responseRate = $totalResponses > 0 ? round((($announcement['accepted_count'] + $announcement['dismissed_count']) / $totalResponses) * 100) : 0;
                                        ?>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                                            <div class="bg-green-600 h-2.5 rounded-full" style="width: <?= $acceptedPercent ?>%"></div>
                                            <div class="bg-red-600 h-2.5 rounded-full" style="width: <?= $dismissedPercent ?>%; margin-left: -<?= $dismissedPercent ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-500">
                                            <span>Response Progress</span>
                                            <span><?= $responseRate ?>% Responded</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ml-4 flex-shrink-0 flex space-x-2">
                                    <!-- View Responses Button -->
                                    <button onclick="openResponsesModal(<?= $announcement['id'] ?>)" 
                                            class="inline-flex items-center p-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 tooltip" 
                                            data-tooltip="View Responses">
                                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Edit Button -->
                                    <button onclick="openEditModal(<?= $announcement['id'] ?>, '<?= htmlspecialchars(addslashes($announcement['title'])) ?>', `<?= htmlspecialchars(addslashes($announcement['message'])) ?>`, '<?= $announcement['priority'] ?>', '<?= $announcement['expiry_date'] ?>', '<?= $announcement['audience_type'] ?>', '<?= $announcement['image_path'] ?>')" 
                                            class="inline-flex items-center p-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 tooltip" 
                                            data-tooltip="Edit">
                                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Archive Button -->
                                    <button onclick="openDeleteModal(<?= $announcement['id'] ?>)" 
                                            class="inline-flex items-center p-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 tooltip" 
                                            data-tooltip="Archive">
                                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Archived Announcements Tab -->
    <div id="archivedContent" class="main-tab-content hidden">
        <?php if (empty($archivedAnnouncements)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No archived announcements</h3>
                <p class="mt-2 text-sm text-gray-500">Archived announcements will appear here.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-5">
                <?php foreach ($archivedAnnouncements as $announcement): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-medium text-gray-900 mb-2"><?= htmlspecialchars($announcement['title']) ?></h3>
                                    
                                    <div class="flex flex-wrap items-center text-sm text-gray-500 mb-4">
                                        <span>Posted on <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?></span>
                                        <?php if ($announcement['expiry_date']): ?>
                                            <span class="mx-2">•</span>
                                            <span>Expired: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                        <?php endif; ?>
                                        <span class="mx-2">•</span>
                                        <span>Audience: <?= $announcement['audience_type'] === 'public' ? 'Public' : 'Specific Users' ?></span>
                                    </div>
                                    
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4">
                                            <img src="<?= $announcement['image_path'] ?>" alt="Announcement Image" class="max-w-full h-auto rounded-lg max-h-60 object-cover">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($announcement['message']) ?></p>
                                </div>
                                
                                <div class="ml-4 flex-shrink-0 flex space-x-2">
                                    <!-- Repost Button -->
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="repost_announcement" 
                                                class="inline-flex items-center p-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 tooltip" 
                                                data-tooltip="Repost"
                                                onclick="return confirm('Repost this announcement? This will clear previous responses and send new notifications.')">
                                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    
                                    <!-- Delete Button -->
                                    <button onclick="openPermanentDeleteModal(<?= $announcement['id'] ?>)" 
                                            class="inline-flex items-center p-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 tooltip" 
                                            data-tooltip="Delete Permanently">
                                        <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Edit Announcement</h2>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" class="p-6 space-y-6">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="edit_announcement" value="1">
            <input type="hidden" name="current_image" id="edit_current_image">
            
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="edit_title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" id="edit_title" name="title" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                </div>
                
                <div>
                    <label for="edit_priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select id="edit_priority" name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label for="edit_message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                <textarea id="edit_message" name="message" rows="6" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required></textarea>
            </div>
            
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div>
                    <label for="edit_announcement_image" class="block text-sm font-medium text-gray-700 mb-1">Image (optional)</label>
                    <input type="file" id="edit_announcement_image" name="announcement_image" accept="image/*" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Supported formats: JPG, PNG, GIF. Max size: 2MB</p>
                    <div id="edit_current_image_container" class="mt-2 hidden">
                        <p class="text-sm text-gray-500 mb-1">Current image:</p>
                        <img id="edit_current_image_preview" src="" alt="Current image" class="max-w-full h-auto rounded-lg max-h-40 object-cover">
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_expiry_date" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date (optional)</label>
                        <input type="date" id="edit_expiry_date" name="expiry_date" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Audience</label>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input id="edit-audience-public" name="audience_type" type="radio" value="public" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 edit-audience-type-radio" onchange="toggleEditUserSelection()">
                                <label for="edit-audience-public" class="ml-3 block text-sm font-medium text-gray-700">Public (All Users)</label>
                            </div>
                            <div class="flex items-center">
                                <input id="edit-audience-specific" name="audience_type" type="radio" value="specific" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 edit-audience-type-radio" onchange="toggleEditUserSelection()">
                                <label for="edit-audience-specific" class="ml-3 block text-sm font-medium text-gray-700">Specific Users</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="edit-user-selection" class="hidden">
                        <label for="edit-user-search" class="block text-sm font-medium text-gray-700 mb-1">Select Users</label>
                        <input type="text" id="edit-user-search" placeholder="Search users..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-2">
                        <div class="border border-gray-300 rounded-md p-3 max-h-40 overflow-y-auto bg-gray-50">
                            <?php foreach ($allUsers as $user): ?>
                                <div class="flex items-center mb-2 last:mb-0">
                                    <input id="edit-user-<?= $user['id'] ?>" name="target_users[]" type="checkbox" value="<?= $user['id'] ?>" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded edit-user-checkbox">
                                    <label for="edit-user-<?= $user['id'] ?>" class="ml-2 block text-sm text-gray-700"><?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['username']) ?>)</label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Archive Announcement Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Archive Announcement</h2>
        </div>
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="id" id="delete_id">
            <input type="hidden" name="delete_announcement" value="1">
            
            <p class="mb-4 text-gray-600">Are you sure you want to archive this announcement? It will no longer be visible to users but can be reposted later.</p>
            
            <input type="hidden" name="delete_action" value="archive">
            
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                    Archive
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Delete Announcement</h2>
        </div>
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="id" id="permanent_delete_id">
            <input type="hidden" name="delete_announcement" value="1">
            <input type="hidden" name="delete_action" value="permanent">
            
            <p class="mb-4 text-gray-600">Are you sure you want to permanently delete this announcement? This action cannot be undone and will remove all response data associated with it.</p>
            
            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closePermanentDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Responses Modal -->
<div id="responsesModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Announcement Responses</h2>
                <button onclick="closeResponsesModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <p class="text-gray-600 text-sm mt-1" id="responsesAnnouncementTitle"></p>
        </div>
        
        <div class="p-6">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button id="acceptedTab" class="tab-link border-b-2 border-green-500 text-green-600 font-medium py-4 px-1" onclick="switchTab('accepted')">
                        Accepted (<span id="acceptedCount">0</span>)
                    </button>
                    <button id="pendingTab" class="tab-link border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium py-4 px-1" onclick="switchTab('pending')">
                        Pending (<span id="pendingCount">0</span>)
                    </button>
                    <button id="dismissedTab" class="tab-link border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium py-4 px-1" onclick="switchTab('dismissed')">
                        Dismissed (<span id="dismissedCount">0</span>)
                    </button>
                </nav>
            </div>
            
            <!-- Accepted Tab Content -->
            <div id="acceptedContent" class="tab-content">
                <div class="space-y-3" id="acceptedUsersList">
                    <!-- Accepted users will be loaded here -->
                </div>
            </div>
            
            <!-- Pending Tab Content -->
            <div id="pendingContent" class="tab-content hidden">
                <div class="space-y-3" id="pendingUsersList">
                    <!-- Pending users will be loaded here -->
                </div>
            </div>
            
            <!-- Dismissed Tab Content -->
            <div id="dismissedContent" class="tab-content hidden">
                <div class="space-y-3" id="dismissedUsersList">
                    <!-- Dismissed users will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Main tab navigation
    function switchMainTab(tabName) {
        // Hide all content and deactivate all tabs
        document.querySelectorAll('.main-tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.querySelectorAll('.tab-button').forEach(tab => {
            tab.classList.remove('border-blue-500', 'text-blue-600');
            tab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        });
        
        // Show selected content and activate tab
        document.getElementById(`${tabName}Content`).classList.remove('hidden');
        document.getElementById(`${tabName}Tab`).classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        document.getElementById(`${tabName}Tab`).classList.add('border-blue-500', 'text-blue-600');
    }
    
    // Toggle user selection based on audience type
    function toggleUserSelection() {
        const audienceType = document.querySelector('input[name="audience_type"]:checked').value;
        const userSelection = document.getElementById('user-selection');
        
        if (audienceType === 'specific') {
            userSelection.classList.remove('hidden');
        } else {
            userSelection.classList.add('hidden');
        }
    }
    
    function toggleEditUserSelection() {
        const audienceType = document.querySelector('input[name="audience_type"]:checked').value;
        const userSelection = document.getElementById('edit-user-selection');
        
        if (audienceType === 'specific') {
            userSelection.classList.remove('hidden');
        } else {
            userSelection.classList.add('hidden');
        }
    }
    
    // User search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const userSearch = document.getElementById('user-search');
        if (userSearch) {
            userSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const userItems = document.querySelectorAll('#user-selection .flex.items-center');
                
                userItems.forEach(item => {
                    const label = item.querySelector('label');
                    const userName = label.textContent.toLowerCase();
                    
                    if (userName.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        const editUserSearch = document.getElementById('edit-user-search');
        if (editUserSearch) {
            editUserSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const userItems = document.querySelectorAll('#edit-user-selection .flex.items-center');
                
                userItems.forEach(item => {
                    const label = item.querySelector('label');
                    const userName = label.textContent.toLowerCase();
                    
                    if (userName.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    });
    
    // Modal functions
    function openEditModal(id, title, message, priority, expiryDate, audienceType, imagePath) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_message').value = message;
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_expiry_date').value = expiryDate || '';
        document.getElementById('edit_current_image').value = imagePath || '';
        
        // Set audience type
        document.querySelectorAll('.edit-audience-type-radio').forEach(radio => {
            radio.checked = (radio.value === audienceType);
        });
        
        // Show current image if exists
        const imageContainer = document.getElementById('edit_current_image_container');
        const imagePreview = document.getElementById('edit_current_image_preview');
        
        if (imagePath) {
            imagePreview.src = imagePath;
            imageContainer.classList.remove('hidden');
        } else {
            imageContainer.classList.add('hidden');
        }
        
        // Toggle user selection based on audience type
        toggleEditUserSelection();
        
        // Get and set target users for this announcement
        if (audienceType === 'specific') {
            fetch(`/community-health-tracker/staff/get_announcement_targets.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.edit-user-checkbox').forEach(checkbox => {
                            checkbox.checked = data.targets.includes(parseInt(checkbox.value));
                        });
                    }
                })
                .catch(error => console.error('Error fetching targets:', error));
        }
        
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
    
    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    
    function openPermanentDeleteModal(id) {
        document.getElementById('permanent_delete_id').value = id;
        document.getElementById('permanentDeleteModal').classList.remove('hidden');
    }
    
    function closePermanentDeleteModal() {
        document.getElementById('permanentDeleteModal').classList.add('hidden');
    }
    
    // Responses modal functions
    let currentAnnouncementData = null;
    
    function openResponsesModal(announcementId) {
        // Find the announcement data
        const announcement = [...<?= json_encode($activeAnnouncements) ?>].find(a => a.id == announcementId);
        
        if (announcement) {
            currentAnnouncementData = announcement;
            document.getElementById('responsesAnnouncementTitle').textContent = announcement.title;
            
            // Update counts
            document.getElementById('acceptedCount').textContent = announcement.accepted_count;
            document.getElementById('pendingCount').textContent = announcement.pending_count;
            document.getElementById('dismissedCount').textContent = announcement.dismissed_count;
            
            // Load accepted users
            const acceptedList = document.getElementById('acceptedUsersList');
            acceptedList.innerHTML = announcement.accepted_users.length > 0 ? 
                announcement.accepted_users.map(user => `
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-800">${user.full_name}</p>
                            <p class="text-xs text-gray-500">Responded on ${new Date(user.response_date).toLocaleString()}</p>
                        </div>
                        <a href="/community-health-tracker/staff/view-user.php?id=${user.id}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Profile
                        </a>
                    </div>
                `).join('') : 
                '<p class="text-gray-500 text-center py-4">No users have accepted this announcement yet.</p>';
            
            // Load pending users
            const pendingList = document.getElementById('pendingUsersList');
            pendingList.innerHTML = announcement.pending_users.length > 0 ? 
                announcement.pending_users.map(user => `
                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-800">${user.full_name}</p>
                            <p class="text-xs text-gray-500">No response yet</p>
                        </div>
                        <a href="/community-health-tracker/staff/view-user.php?id=${user.id}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Profile
                        </a>
                    </div>
                `).join('') : 
                '<p class="text-gray-500 text-center py-4">No pending users for this announcement.</p>';
            
            // Load dismissed users
            const dismissedList = document.getElementById('dismissedUsersList');
            dismissedList.innerHTML = announcement.dismissed_users.length > 0 ? 
                announcement.dismissed_users.map(user => `
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-800">${user.full_name}</p>
                            <p class="text-xs text-gray-500">Responded on ${new Date(user.response_date).toLocaleString()}</p>
                        </div>
                        <a href="/community-health-tracker/staff/view-user.php?id=${user.id}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View Profile
                        </a>
                    </div>
                `).join('') : 
                '<p class="text-gray-500 text-center py-4">No users have dismissed this announcement.</p>';
            
            // Show the modal and default to accepted tab
            document.getElementById('responsesModal').classList.remove('hidden');
            switchTab('accepted');
        }
    }
    
    function closeResponsesModal() {
        document.getElementById('responsesModal').classList.add('hidden');
    }
    
    function switchTab(tabName) {
        // Hide all content and deactivate all tabs
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.querySelectorAll('.tab-link').forEach(tab => {
            tab.classList.remove('border-green-500', 'text-green-600');
            tab.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        });
        
        // Show selected content and activate tab
        document.getElementById(`${tabName}Content`).classList.remove('hidden');
        document.getElementById(`${tabName}Tab`).classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
        document.getElementById(`${tabName}Tab`).classList.add('border-green-500', 'text-green-600');
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.id === 'editModal') {
            closeEditModal();
        }
        if (event.target.id === 'deleteModal') {
            closeDeleteModal();
        }
        if (event.target.id === 'permanentDeleteModal') {
            closePermanentDeleteModal();
        }
        if (event.target.id === 'responsesModal') {
            closeResponsesModal();
        }
    });
    
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltips = document.querySelectorAll('.tooltip');
        tooltips.forEach(tooltip => {
            const tooltipText = tooltip.getAttribute('data-tooltip');
            if (tooltipText) {
                tooltip.addEventListener('mouseenter', function(e) {
                    const tooltipElement = document.createElement('div');
                    tooltipElement.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-800 rounded shadow-lg';
                    tooltipElement.textContent = tooltipText;
                    
                    const rect = tooltip.getBoundingClientRect();
                    tooltipElement.style.top = `${rect.top - 30}px`;
                    tooltipElement.style.left = `${rect.left + rect.width / 2 - tooltipElement.offsetWidth / 2}px`;
                    
                    tooltipElement.id = 'current-tooltip';
                    document.body.appendChild(tooltipElement);
                });
                
                tooltip.addEventListener('mouseleave', function() {
                    const existingTooltip = document.getElementById('current-tooltip');
                    if (existingTooltip) {
                        existingTooltip.remove();
                    }
                });
            }
        });
    });
</script>