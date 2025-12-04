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
            } elseif ($audience_type === 'public') {
                createAnnouncementNotification($announcementId, $title);
                $success = 'Message broadcasted to all users successfully!';
            } else {
                // For landing_page announcements, no notifications needed
                $success = 'Landing page announcement published successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error sending message: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Handle edit operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $priority = $_POST['priority'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    try {
        $stmt = $pdo->prepare("UPDATE sitio1_announcements SET title = ?, message = ?, priority = ?, expiry_date = ? WHERE id = ? AND staff_id = ?");
        $stmt->execute([$title, $message, $priority, $expiry_date, $id, $staffId]);
        $success = 'Announcement updated successfully!';
    } catch (PDOException $e) {
        $error = 'Error updating announcement: ' . $e->getMessage();
    }
}

// Handle archive operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_announcement'])) {
    $id = $_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE sitio1_announcements SET status = 'archived' WHERE id = ? AND staff_id = ?");
        $stmt->execute([$id, $staffId]);
        $success = 'Announcement archived successfully!';
    } catch (PDOException $e) {
        $error = 'Error archiving announcement: ' . $e->getMessage();
    }
}

// Handle repost operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repost_announcement'])) {
    $id = $_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE sitio1_announcements SET status = 'active', post_date = NOW() WHERE id = ? AND staff_id = ?");
        $stmt->execute([$id, $staffId]);
        $success = 'Announcement reposted successfully!';
    } catch (PDOException $e) {
        $error = 'Error reposting announcement: ' . $e->getMessage();
    }
}

// Handle delete operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $id = $_POST['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM sitio1_announcements WHERE id = ? AND staff_id = ? AND status = 'archived'");
        $stmt->execute([$id, $staffId]);
        $success = 'Announcement deleted permanently!';
    } catch (PDOException $e) {
        $error = 'Error deleting announcement: ' . $e->getMessage();
    }
}

// Get all announcements by this staff
$activeAnnouncements = [];
$archivedAnnouncements = [];

try {
    $stmt = $pdo->prepare("SELECT a.*, 
                          COUNT(CASE WHEN ua.status = 'accepted' THEN 1 END) as accepted_count,
                          COUNT(CASE WHEN ua.status = 'dismissed' THEN 1 END) as dismissed_count,
                          COUNT(CASE WHEN ua.status IS NULL THEN 1 END) as pending_count
                          FROM sitio1_announcements a
                          LEFT JOIN sitio1_users u ON u.approved = TRUE AND a.audience_type IN ('public', 'specific')
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
    <title>Barangay Luz - Announcement Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Consistent blue theme matching landing page */
        :root {
            --primary-blue: #0073D3;
            --secondary-blue: #4A90E2;
            --light-blue: #E8F2FF;
            --dark-blue: #1B4F8C;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --border-light: #e5e7eb;
        }

        body {
            background-color: white;
            color: var(--text-dark);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
            font-weight: 600;
            border-radius: 9999px;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-blue);
        }

        .btn-primary:hover {
            background-color: var(--dark-blue);
            border-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background-color: white;
            color: var(--primary-blue);
            font-weight: 600;
            border-radius: 9999px;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-blue);
        }

        .btn-secondary:hover {
            background-color: var(--light-blue);
            transform: translateY(-2px);
        }

        .info-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-light);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            border-radius: 16px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .close-modal {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-light);
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--text-dark);
        }

        /* Priority badges */
        .priority-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
        }
        
        .priority-high {
            background-color: #fee2e2;
            color: #dc2626;
            border: 2px solid #fecaca;
        }
        
        .priority-medium {
            background-color: #fef3c7;
            color: #d97706;
            border: 2px solid #fde68a;
        }
        
        .priority-normal {
            background-color: var(--light-blue);
            color: var(--primary-blue);
            border: 2px solid #bfdbfe;
        }

        /* Form styling */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 115, 211, 0.1);
        }
        
        .form-textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            font-size: 0.875rem;
            min-height: 140px;
            resize: vertical;
            transition: all 0.3s ease;
            background-color: white;
            line-height: 1.5;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 115, 211, 0.1);
        }
        
        /* Tab styling */
        .tab-button {
            padding: 0.875rem 1.75rem;
            font-weight: 600;
            color: var(--text-light);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }
        
        .tab-button.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
        }
        
        .tab-button:hover:not(.active) {
            color: var(--primary-blue);
        }

        /* Announcement card */
        .announcement-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--primary-blue);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .announcement-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        /* Audience options */
        .audience-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .audience-option {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .audience-option:hover {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
        }
        
        .audience-option.selected {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
        }
        
        /* User selection */
        .user-selection-container {
            max-height: 200px;
            overflow-y: auto;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 0.75rem;
            background: white;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        
        .user-item:hover {
            background-color: var(--light-blue);
        }
        
        .user-item input[type="checkbox"] {
            margin-right: 0.75rem;
            accent-color: var(--primary-blue);
        }
        
        /* File upload */
        .file-upload {
            border: 2px dashed var(--border-light);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .file-upload:hover {
            border-color: var(--primary-blue);
            background-color: var(--light-blue);
        }
        
        /* Stats boxes */
        .stats-container {
            display: flex;
            gap: 1.5rem;
        }
        
        .stat-box {
            flex: 1;
            background: linear-gradient(135deg, var(--light-blue), white);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid var(--border-light);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-blue);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-light);
            font-weight: 600;
        }

        /* Response stats */
        .response-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }
        
        .response-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 0.5rem;
        }
        
        .response-value {
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .response-label {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-blue);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .stats-container {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.25rem;
                margin: 0.5rem;
            }
            
            .audience-options {
                gap: 0.75rem;
            }
            
            .audience-option {
                padding: 1rem;
            }
        }

        /* Utility classes */
        .hidden {
            display: none !important;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .whitespace-pre-line {
            white-space: pre-line;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div class="mb-6 md:mb-0">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#0073D3] to-[#4A90E2] rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-bullhorn text-white text-xl"></i>
                    </div>
                    <div>
                        <div>Announcement Management</div>
                        <div class="text-sm font-normal text-gray-600 mt-1">Barangay Luz Health Monitoring System</div>
                    </div>
                </h1>
                <p class="text-gray-600 mt-3">Create and manage community health announcements</p>
            </div>
            <div class="flex space-x-4">
                <div class="bg-gradient-to-br from-[#0073D3] to-[#4A90E2] p-6 rounded-2xl border border-blue-200 shadow-lg">
                    <p class="text-white font-semibold text-sm">Active Announcements</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= count($activeAnnouncements) ?></p>
                </div>
                <div class="bg-gradient-to-br from-emerald-500 to-green-500 p-6 rounded-2xl border border-green-200 shadow-lg">
                    <p class="text-white font-semibold text-sm">Community Members</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= count($allUsers) ?></p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border-2 border-green-200 text-green-700 px-6 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle mr-3 text-lg"></i>
                <span class="font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Create Announcement Form -->
            <div class="lg:col-span-2">
                <div class="info-card p-8">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#0073D3] to-[#4A90E2] rounded-2xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-plus-circle text-white text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Create Announcement</h2>
                            <p class="text-gray-600 text-sm">Send important messages to community members</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
                        <div class="form-group">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" required class="form-input" 
                                   placeholder="Enter announcement title" maxlength="100">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Message *</label>
                            <textarea name="message" required class="form-textarea" 
                                      placeholder="Type your announcement message here..." 
                                      maxlength="500"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <div class="flex gap-3">
                                    <label class="flex-1">
                                        <input type="radio" name="priority" value="normal" class="hidden peer" checked>
                                        <span class="block px-4 py-3 border-2 rounded-xl cursor-pointer text-center text-sm font-medium peer-checked:bg-blue-50 peer-checked:border-blue-500 peer-checked:text-blue-700">
                                            Normal
                                        </span>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" name="priority" value="medium" class="hidden peer">
                                        <span class="block px-4 py-3 border-2 rounded-xl cursor-pointer text-center text-sm font-medium peer-checked:bg-yellow-50 peer-checked:border-yellow-500 peer-checked:text-yellow-700">
                                            Medium
                                        </span>
                                    </label>
                                    <label class="flex-1">
                                        <input type="radio" name="priority" value="high" class="hidden peer">
                                        <span class="block px-4 py-3 border-2 rounded-xl cursor-pointer text-center text-sm font-medium peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-700">
                                            High
                                        </span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" class="form-input" min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Send To</label>
                            <div class="audience-options">
                                <label class="audience-option" onclick="selectAudience(this, 'landing_page')">
                                    <input type="radio" name="audience_type" value="landing_page" checked class="hidden">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-globe text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800">Landing Page</div>
                                            <div class="text-sm text-gray-500 mt-1">Visible to all website visitors</div>
                                        </div>
                                    </div>
                                </label>
                                <label class="audience-option" onclick="selectAudience(this, 'public')">
                                    <input type="radio" name="audience_type" value="public" class="hidden">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-users text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800">All Users</div>
                                            <div class="text-sm text-gray-500 mt-1">All registered community members</div>
                                        </div>
                                    </div>
                                </label>
                                <label class="audience-option" onclick="selectAudience(this, 'specific')">
                                    <input type="radio" name="audience_type" value="specific" class="hidden">
                                    <div class="flex items-start gap-4">
                                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user-friends text-blue-600"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800">Specific Users</div>
                                            <div class="text-sm text-gray-500 mt-1">Select individual recipients</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div id="user-selection" class="form-group hidden">
                            <label class="form-label">Select Users</label>
                            <input type="text" id="user-search" placeholder="Search users by name..." 
                                   class="form-input mb-4">
                            <div class="user-selection-container">
                                <?php if (empty($allUsers)): ?>
                                    <p class="text-gray-500 text-center py-6">No users available</p>
                                <?php else: ?>
                                    <?php foreach ($allUsers as $user): ?>
                                        <label class="user-item">
                                            <input type="checkbox" name="target_users[]" value="<?= $user['id'] ?>" 
                                                   class="w-4 h-4" onchange="updateSelectedUserCount()">
                                            <span class="text-sm font-medium"><?= htmlspecialchars($user['full_name']) ?></span>
                                            <span class="text-xs text-gray-500 ml-2">@<?= htmlspecialchars($user['username']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm font-medium text-blue-600 mt-3" id="selected-count">0 users selected</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Image (Optional)</label>
                            <div class="file-upload" onclick="document.getElementById('announcement_image').click()">
                                <input type="file" id="announcement_image" name="announcement_image" 
                                       accept="image/*" class="hidden" onchange="updateImageName(this)">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3"></i>
                                <p class="text-sm text-gray-700 font-medium" id="image-name">Click to upload image</p>
                                <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF • Max 5MB</p>
                            </div>
                        </div>
                        
                        <button type="submit" name="post_announcement" 
                                class="btn-primary w-full flex items-center justify-center gap-3 px-8 py-4 rounded-full font-semibold text-lg shadow-lg">
                            <i class="fas fa-paper-plane"></i>
                            Publish Announcement
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Right Column - Announcement List -->
            <div class="lg:col-span-1">
                <div class="info-card p-6">
                    <!-- Tabs -->
                    <div class="flex border-b border-gray-200 mb-6">
                        <button id="active-tab" class="tab-button active">
                            <i class="fas fa-bell mr-2"></i>
                            Active (<?= count($activeAnnouncements) ?>)
                        </button>
                        <button id="archived-tab" class="tab-button">
                            <i class="fas fa-archive mr-2"></i>
                            Archived (<?= count($archivedAnnouncements) ?>)
                        </button>
                    </div>
                    
                    <!-- Active Announcements -->
                    <div id="active-messages" class="space-y-4">
                        <?php if (empty($activeAnnouncements)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-bullhorn text-blue-500 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">No active announcements</h3>
                                <p class="text-gray-500 text-sm">Create your first announcement to get started</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeAnnouncements as $announcement): ?>
                                <div class="announcement-card">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-gray-800 truncate text-sm"><?= htmlspecialchars($announcement['title']) ?></h4>
                                            <p class="text-gray-500 text-xs mt-1">
                                                <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1 ml-2">
                                            <button onclick="openViewModal(<?= htmlspecialchars(json_encode($announcement, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"
                                                    class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                    title="View Details">
                                                <i class="fas fa-eye text-sm"></i>
                                            </button>
                                            <div class="relative">
                                                <button class="p-2 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                                        onclick="toggleDropdown(this)"
                                                        title="More Options">
                                                    <i class="fas fa-ellipsis-v text-sm"></i>
                                                </button>
                                                <div class="dropdown-menu hidden absolute right-0 mt-2 bg-white border border-gray-200 rounded-xl shadow-xl py-2 z-10 min-w-[140px]">
                                                    <button onclick="openEditModal(<?= htmlspecialchars(json_encode($announcement, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"
                                                            class="block w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition-colors">
                                                        <i class="fas fa-edit mr-3 text-xs"></i> Edit
                                                    </button>
                                                    <form method="POST" action="" class="block">
                                                        <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                                        <button type="submit" name="archive_announcement"
                                                                class="block w-full text-left px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-orange-600 transition-colors"
                                                                onclick="return confirm('Archive this announcement?')">
                                                            <i class="fas fa-archive mr-3 text-xs"></i> Archive
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-gray-600 text-xs mb-4 line-clamp-2">
                                        <?= htmlspecialchars(substr($announcement['message'], 0, 100)) ?>
                                        <?php if (strlen($announcement['message']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                            <i class="fas fa-flag text-xs"></i>
                                            <?= ucfirst($announcement['priority']) ?>
                                        </span>
                                        <div class="flex items-center gap-3 text-xs">
                                            <span class="text-green-600 font-semibold" title="Accepted">
                                                <i class="fas fa-check mr-1"></i><?= $announcement['accepted_count'] ?>
                                            </span>
                                            <span class="text-yellow-600 font-semibold" title="Pending">
                                                <i class="fas fa-clock mr-1"></i><?= $announcement['pending_count'] ?>
                                            </span>
                                            <span class="text-red-600 font-semibold" title="Dismissed">
                                                <i class="fas fa-times mr-1"></i><?= $announcement['dismissed_count'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Archived Announcements -->
                    <div id="archived-messages" class="hidden space-y-3">
                        <?php if (empty($archivedAnnouncements)): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-archive text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">No archived announcements</h3>
                                <p class="text-gray-500 text-sm">Archived announcements will appear here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($archivedAnnouncements as $announcement): ?>
                                <div class="announcement-card opacity-75 hover:opacity-100 transition-opacity">
                                    <div class="flex justify-between items-center">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-700 text-sm mb-1 truncate"><?= htmlspecialchars($announcement['title']) ?></div>
                                            <div class="text-xs text-gray-500">
                                                Archived on <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 ml-2">
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                                <button type="submit" name="repost_announcement"
                                                        class="p-2 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                        title="Repost Announcement"
                                                        onclick="return confirm('Repost this announcement?')">
                                                    <i class="fas fa-redo text-sm"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="inline">
                                                <input type="hidden" name="id" value="<?= $announcement['id'] ?>">
                                                <button type="submit" name="delete_announcement"
                                                        class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="Delete Permanently"
                                                        onclick="return confirm('Permanently delete this announcement?')">
                                                    <i class="fas fa-trash text-sm"></i>
                                                </button>
                                            </form>
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

    <!-- View Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
            <div id="modalContent"></div>
            <div class="mt-8 flex justify-end space-x-3">
                <button onclick="closeViewModal()" class="btn-secondary px-6 py-3 rounded-full">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content max-w-lg">
            <button class="close-modal" onclick="closeEditModal()">&times;</button>
            <form method="POST" action="" id="edit-form" class="space-y-6">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="edit_announcement" value="1">
                
                <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-edit text-white"></i>
                    </div>
                    Edit Announcement
                </h3>
                
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" id="edit-title" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" id="edit-message" required class="form-textarea"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="edit-priority" class="form-input">
                            <option value="normal">Normal</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="edit-expiry" class="form-input">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeEditModal()" 
                            class="btn-secondary px-6 py-3 rounded-full">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" 
                            class="btn-primary px-6 py-3 rounded-full">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab functionality
        document.getElementById('active-tab').addEventListener('click', function() {
            document.getElementById('active-messages').classList.remove('hidden');
            document.getElementById('archived-messages').classList.add('hidden');
            document.getElementById('active-tab').classList.add('active');
            document.getElementById('archived-tab').classList.remove('active');
        });
        
        document.getElementById('archived-tab').addEventListener('click', function() {
            document.getElementById('active-messages').classList.add('hidden');
            document.getElementById('archived-messages').classList.remove('hidden');
            document.getElementById('active-tab').classList.remove('active');
            document.getElementById('archived-tab').classList.add('active');
        });
        
        // Toggle dropdown
        function toggleDropdown(button) {
            const dropdown = button.nextElementSibling;
            dropdown.classList.toggle('hidden');
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.add('hidden');
                }
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });
        
        // Audience selection
        function selectAudience(element, value) {
            // Remove selected class from all options
            element.parentElement.querySelectorAll('.audience-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Update radio button
            const radioInput = element.querySelector('input[type="radio"]');
            radioInput.checked = true;
            
            // Toggle user selection
            toggleUserSelection();
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
                const fileName = input.files[0].name;
                document.getElementById('image-name').innerHTML = 
                    `<i class="fas fa-check-circle text-green-500 mr-2"></i> ${fileName}`;
            }
        }
        
        // Update selected user count
        function updateSelectedUserCount() {
            const checkboxes = document.querySelectorAll('#user-selection input[type="checkbox"]:checked');
            const count = checkboxes.length;
            document.getElementById('selected-count').textContent = `${count} user${count !== 1 ? 's' : ''} selected`;
        }
        
        // User search
        document.getElementById('user-search')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const userItems = document.querySelectorAll('.user-item');
            
            userItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'flex' : 'none';
            });
            
            updateSelectedUserCount();
        });
        
        // View Modal Functions
        function openViewModal(announcement) {
            const modal = document.getElementById('viewModal');
            const modalContent = document.getElementById('modalContent');
            
            const postDate = new Date(announcement.post_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            let content = `
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">${announcement.title || 'No Title'}</h2>
                        <span class="priority-badge priority-${announcement.priority || 'normal'}">
                            <i class="fas fa-flag text-xs"></i>
                            ${announcement.priority ? announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1) : 'Normal'} Priority
                        </span>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-xl mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Posted by:</p>
                                <p class="font-semibold text-gray-800"><?= $_SESSION['user']['full_name'] ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Posted on:</p>
                                <p class="font-semibold text-gray-800">${postDate}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Audience:</p>
                                <p class="font-semibold text-gray-800">
                                    ${announcement.audience_type === 'public' ? 'All Users' : 
                                      announcement.audience_type === 'landing_page' ? 'Landing Page' : 
                                      'Specific Users'}
                                </p>
                            </div>
            `;
            
            if (announcement.expiry_date) {
                const expiryDate = new Date(announcement.expiry_date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
                content += `
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Expires:</p>
                                <p class="font-semibold text-gray-800">${expiryDate}</p>
                            </div>
                `;
            }
            
            content += `</div></div>`;
            
            if (announcement.image_path) {
                content += `
                    <div class="mb-6">
                        <h3 class="font-semibold text-gray-800 mb-3">Attached Image</h3>
                        <div class="image-preview border-2 border-gray-200 rounded-xl overflow-hidden">
                            <img src="${announcement.image_path}" alt="Announcement Image" 
                                 class="w-full h-auto max-h-[300px] object-contain">
                        </div>
                    </div>
                `;
            }
            
            content += `
                <div class="mb-8">
                    <h3 class="font-semibold text-gray-800 mb-3">Message Content</h3>
                    <div class="bg-gray-50 p-6 rounded-xl border border-gray-200">
                        <p class="text-gray-700 whitespace-pre-line">${announcement.message || 'No message provided'}</p>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-blue-50 to-gray-50 p-6 rounded-2xl border border-blue-100">
                    <h3 class="font-semibold text-gray-800 mb-4">Response Statistics</h3>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="p-4 bg-green-50 border-2 border-green-200 rounded-xl">
                            <p class="text-green-700 font-bold text-2xl">${announcement.accepted_count || 0}</p>
                            <p class="text-sm text-green-600 font-medium mt-1">Accepted</p>
                        </div>
                        <div class="p-4 bg-yellow-50 border-2 border-yellow-200 rounded-xl">
                            <p class="text-yellow-700 font-bold text-2xl">${announcement.pending_count || 0}</p>
                            <p class="text-sm text-yellow-600 font-medium mt-1">Pending</p>
                        </div>
                        <div class="p-4 bg-red-50 border-2 border-red-200 rounded-xl">
                            <p class="text-red-700 font-bold text-2xl">${announcement.dismissed_count || 0}</p>
                            <p class="text-sm text-red-600 font-medium mt-1">Dismissed</p>
                        </div>
                    </div>
                </div>
            `;
            
            modalContent.innerHTML = content;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close View Modal
        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Edit Modal Functions
        function openEditModal(announcement) {
            const modal = document.getElementById('editModal');
            document.getElementById('edit-id').value = announcement.id || '';
            document.getElementById('edit-title').value = announcement.title || '';
            document.getElementById('edit-message').value = announcement.message || '';
            document.getElementById('edit-priority').value = announcement.priority || 'normal';
            document.getElementById('edit-expiry').value = announcement.expiry_date || '';
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeViewModal();
                closeEditModal();
            }
        });

        // Initialize audience selection
        document.addEventListener('DOMContentLoaded', function() {
            const defaultAudience = document.querySelector('input[name="audience_type"]:checked');
            if (defaultAudience) {
                const parentLabel = defaultAudience.closest('.audience-option');
                if (parentLabel) {
                    parentLabel.classList.add('selected');
                }
            }
            
            // Add click listeners to user items
            document.querySelectorAll('.user-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!e.target.matches('input[type="checkbox"]')) {
                        const checkbox = this.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            updateSelectedUserCount();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>