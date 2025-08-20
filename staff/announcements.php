<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../staff/notification_functions.php'; // New notification functions

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
    
    if (!empty($title) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sitio1_announcements 
                                  (staff_id, title, message, priority, expiry_date, status) 
                                  VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$staffId, $title, $message, $priority, $expiry_date]);
            
            $announcementId = $pdo->lastInsertId();
            
            // Create notification for all users
            createAnnouncementNotification($announcementId, $title);
            
            $success = 'Announcement posted successfully! Notifications sent to all users.';
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
    
    if (!empty($title) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("UPDATE sitio1_announcements 
                                  SET title = ?, message = ?, priority = ?, expiry_date = ? 
                                  WHERE id = ? AND staff_id = ?");
            $stmt->execute([$title, $message, $priority, $expiry_date, $id, $staffId]);
            
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
            // First delete responses
            $stmt = $pdo->prepare("DELETE FROM user_announcements WHERE announcement_id = ?");
            $stmt->execute([$id]);
            
            // Then delete notifications
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE related_id = ? AND type = 'announcement'");
            $stmt->execute([$id]);
            
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
        
        // Get announcement title for notification
        $stmt = $pdo->prepare("SELECT title FROM sitio1_announcements WHERE id = ?");
        $stmt->execute([$id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create new notifications
        if ($announcement) {
            createAnnouncementNotification($id, $announcement['title']);
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
    }
} catch (PDOException $e) {
    $error = 'Error fetching announcements: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold mb-6 text-gray-800">Community Announcements</h1>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="font-semibold"><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Post Announcement Card -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 bg-blue-600 text-white">
                <h2 class="text-xl font-semibold">Create New Announcement</h2>
                <p class="text-blue-100 text-sm">Broadcast important information to the community</p>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 font-medium mb-2">Title *</label>
                        <input type="text" id="title" name="title" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="Enter announcement title">
                    </div>
                    
                    <div class="mb-4">
                        <label for="message" class="block text-gray-700 font-medium mb-2">Message *</label>
                        <textarea id="message" name="message" rows="5" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required placeholder="Enter detailed message"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="priority" class="block text-gray-700 font-medium mb-2">Priority</label>
                            <select id="priority" name="priority" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label for="expiry_date" class="block text-gray-700 font-medium mb-2">Expiry Date (optional)</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="post_announcement" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Post Announcement
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Active Announcements Card -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-6 bg-green-600 text-white">
                <h2 class="text-xl font-semibold">Active Announcements</h2>
                <p class="text-green-100 text-sm">Currently broadcasted messages</p>
            </div>
            <div class="p-6">
                <?php if (empty($activeAnnouncements)): ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900">No active announcements</h3>
                        <p class="mt-1 text-gray-500">Create your first announcement to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($activeAnnouncements as $announcement): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-semibold text-lg text-gray-800 flex items-center">
                                            <?php if ($announcement['priority'] == 'urgent'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">URGENT</span>
                                            <?php elseif ($announcement['priority'] == 'high'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 mr-2">HIGH</span>
                                            <?php elseif ($announcement['priority'] == 'low'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">LOW</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($announcement['title']) ?>
                                        </h3>
                                        <p class="text-gray-500 text-sm">
                                            Posted on: <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?>
                                            <?php if ($announcement['expiry_date']): ?>
                                                | Expires: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <!-- View Responses Button -->
                                        <button onclick="openResponsesModal(<?= $announcement['id'] ?>)" 
                                                class="text-indigo-600 hover:text-indigo-800 tooltip" 
                                                data-tooltip="View Responses">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>
                                        <!-- Edit Button -->
                                        <button onclick="openEditModal(<?= $announcement['id'] ?>, '<?= htmlspecialchars(addslashes($announcement['title'])) ?>', `<?= htmlspecialchars(addslashes($announcement['message'])) ?>`, '<?= $announcement['priority'] ?>', '<?= $announcement['expiry_date'] ?>')" 
                                                class="text-blue-600 hover:text-blue-800 tooltip" 
                                                data-tooltip="Edit">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <!-- Archive Button -->
                                        <button onclick="openDeleteModal(<?= $announcement['id'] ?>)" 
                                                class="text-yellow-600 hover:text-yellow-800 tooltip" 
                                                data-tooltip="Archive">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-gray-700 mb-4 whitespace-pre-line"><?= htmlspecialchars($announcement['message']) ?></p>
                                
                                <!-- Response Summary -->
                                <div class="grid grid-cols-3 gap-2 text-center text-sm mb-2">
                                    <div class="bg-green-100 text-green-800 p-2 rounded-lg">
                                        <p class="font-semibold text-lg"><?= $announcement['accepted_count'] ?></p>
                                        <p>Accepted</p>
                                    </div>
                                    <div class="bg-yellow-100 text-yellow-800 p-2 rounded-lg">
                                        <p class="font-semibold text-lg"><?= $announcement['pending_count'] ?></p>
                                        <p>Pending</p>
                                    </div>
                                    <div class="bg-red-100 text-red-800 p-2 rounded-lg">
                                        <p class="font-semibold text-lg"><?= $announcement['dismissed_count'] ?></p>
                                        <p>Dismissed</p>
                                    </div>
                                </div>
                                
                                <!-- Progress bar -->
                                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-2">
                                    <?php 
                                    $totalResponses = $announcement['accepted_count'] + $announcement['dismissed_count'] + $announcement['pending_count'];
                                    $acceptedPercent = $totalResponses > 0 ? ($announcement['accepted_count'] / $totalResponses) * 100 : 0;
                                    $dismissedPercent = $totalResponses > 0 ? ($announcement['dismissed_count'] / $totalResponses) * 100 : 0;
                                    ?>
                                    <div class="bg-green-600 h-2.5 rounded-full" style="width: <?= $acceptedPercent ?>%"></div>
                                    <div class="bg-red-600 h-2.5 rounded-full" style="width: <?= $dismissedPercent ?>%; margin-left: -<?= $dismissedPercent ?>%"></div>
                                </div>
                                <p class="text-xs text-gray-500 text-right">Response rate: <?= $totalResponses > 0 ? round((($announcement['accepted_count'] + $announcement['dismissed_count']) / $totalResponses) * 100) : 0 ?>%</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Archived Announcements Card -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="p-6 bg-gray-600 text-white">
            <h2 class="text-xl font-semibold">Archived Announcements</h2>
            <p class="text-gray-200 text-sm">Previously broadcasted messages</p>
        </div>
        <div class="p-6">
            <?php if (empty($archivedAnnouncements)): ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No archived announcements</h3>
                    <p class="mt-1 text-gray-500">Archived announcements will appear here.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($archivedAnnouncements as $announcement): ?>
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-white transition-colors duration-200">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-semibold text-lg text-gray-700"><?= htmlspecialchars($announcement['title']) ?></h3>
                                    <p class="text-gray-500 text-sm">
                                        Posted on: <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?>
                                        <?php if ($announcement['expiry_date']): ?>
                                            | Expired: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="flex space-x-2">
                                    <!-- Repost Button -->
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="repost_announcement" 
                                                class="text-green-600 hover:text-green-800 tooltip" 
                                                data-tooltip="Repost"
                                                onclick="return confirm('Repost this announcement? This will clear previous responses and send new notifications.')">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    <!-- Delete Button -->
                                    <button onclick="openPermanentDeleteModal(<?= $announcement['id'] ?>)" 
                                            class="text-red-600 hover:text-red-800 tooltip" 
                                            data-tooltip="Delete Permanently">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <p class="text-gray-600 whitespace-pre-line"><?= htmlspecialchars($announcement['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Edit Announcement</h2>
        </div>
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="edit_announcement" value="1">
            
            <div class="mb-4">
                <label for="edit_title" class="block text-gray-700 font-medium mb-2">Title *</label>
                <input type="text" id="edit_title" name="title" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mb-4">
                <label for="edit_message" class="block text-gray-700 font-medium mb-2">Message *</label>
                <textarea id="edit_message" name="message" rows="6" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label for="edit_priority" class="block text-gray-700 font-medium mb-2">Priority</label>
                    <select id="edit_priority" name="priority" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label for="edit_expiry_date" class="block text-gray-700 font-medium mb-2">Expiry Date (optional)</label>
                    <input type="date" id="edit_expiry_date" name="expiry_date" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-200">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Archive Announcement Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Archive Announcement</h2>
        </div>
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="id" id="delete_id">
            <input type="hidden" name="delete_announcement" value="1">
            
            <p class="mb-4 text-gray-600">Are you sure you want to archive this announcement? It will no longer be visible to users but can be reposted later.</p>
            
            <input type="hidden" name="delete_action" value="archive">
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-200">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition duration-200">
                    Archive
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Permanent Delete Modal -->
<div id="permanentDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Delete Announcement</h2>
        </div>
        <form method="POST" action="" class="p-6">
            <input type="hidden" name="id" id="permanent_delete_id">
            <input type="hidden" name="delete_announcement" value="1">
            <input type="hidden" name="delete_action" value="permanent">
            
            <p class="mb-4 text-gray-600">Are you sure you want to permanently delete this announcement? This action cannot be undone and will remove all response data associated with it.</p>
            
            <div class="flex justify-end space-x-3 pt-4 border-t">
                <button type="button" onclick="closePermanentDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-200">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">
                    Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Responses Modal -->
<div id="responsesModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b sticky top-0 bg-white z-10">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Announcement Responses</h2>
                <button onclick="closeResponsesModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <p class="text-gray-600 text-sm" id="responsesAnnouncementTitle"></p>
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
    // Modal functions
    function openEditModal(id, title, message, priority, expiryDate) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_message').value = message;
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_expiry_date').value = expiryDate || '';
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

