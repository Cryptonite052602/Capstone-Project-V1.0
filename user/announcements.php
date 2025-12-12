<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];
$error = '';
$success = '';

// Handle announcement response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_to_announcement'])) {
    $announcementId = $_POST['announcement_id'];
    $status = $_POST['respond_to_announcement'];
    
    // Validate status
    if (!in_array($status, ['accepted', 'dismissed'])) {
        $error = 'Invalid response status';
    } else {
        try {
            // Check if announcement is active and targeted to this user
            $stmt = $pdo->prepare("
                SELECT a.id 
                FROM sitio1_announcements a
                LEFT JOIN announcement_targets at ON a.id = at.announcement_id
                WHERE a.id = ? 
                AND a.status = 'active'
                AND (a.audience_type = 'public' OR at.user_id = ?)
            ");
            $stmt->execute([$announcementId, $userId]);
            
            if (!$stmt->fetch()) {
                $error = 'This announcement is no longer available';
            } else {
                // Check if response already exists
                $stmt = $pdo->prepare("SELECT id FROM user_announcements WHERE user_id = ? AND announcement_id = ?");
                $stmt->execute([$userId, $announcementId]);
                
                if ($stmt->fetch()) {
                    // Update existing response
                    $stmt = $pdo->prepare("UPDATE user_announcements SET status = ?, response_date = NOW() WHERE user_id = ? AND announcement_id = ?");
                    $stmt->execute([$status, $userId, $announcementId]);
                } else {
                    // Insert new response
                    $stmt = $pdo->prepare("INSERT INTO user_announcements (user_id, announcement_id, status, response_date) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$userId, $announcementId, $status]);
                }
                
                $success = 'Response recorded successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Error recording response: ' . $e->getMessage();
        }
    }
}

// Get announcements targeted to this user
$announcements = [];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, ua.status as user_status, ua.response_date,
               s.full_name as staff_name
        FROM sitio1_announcements a
        LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ?
        LEFT JOIN sitio1_staff s ON a.staff_id = s.id
        WHERE a.status = 'active'
        AND (a.audience_type = 'public' OR a.id IN (
            SELECT announcement_id FROM announcement_targets WHERE user_id = ?
        ))
        ORDER BY a.priority DESC, a.post_date DESC
    ");
    $stmt->execute([$userId, $userId]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching announcements: ' . $e->getMessage();
}

// Calculate statistics
$respondedCount = count(array_filter($announcements, function($a) {
    return !empty($a['user_status']);
}));
$acceptedCount = count(array_filter($announcements, function($a) {
    return $a['user_status'] === 'accepted';
}));
$dismissedCount = count(array_filter($announcements, function($a) {
    return $a['user_status'] === 'dismissed';
}));
$pendingCount = count($announcements) - $respondedCount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Luz - Community Announcements</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Color Variables */
        :root {
            --primary-blue: #0073D3;
            --secondary-blue: #4A90E2;
            --light-blue: #E8F2FF;
            --dark-blue: #1B4F8C;
            --success-green: #10B981;
            --warning-yellow: #F59E0B;
            --danger-red: #EF4444;
            --info-teal: #06B6D4;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        /* Layout Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Card Design */
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .btn-primary:hover {
            background: var(--dark-blue);
            border-color: var(--dark-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-green);
            color: white;
            border-color: var(--success-green);
        }

        .btn-success:hover {
            background: #059669;
            border-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-outline-success {
            background: white;
            color: var(--success-green);
            border-color: var(--success-green);
        }

        .btn-outline-success:hover {
            background: #D1FAE5;
        }

        .btn-outline-secondary {
            background: white;
            color: var(--gray-600);
            border-color: var(--gray-300);
        }

        .btn-outline-secondary:hover {
            background: var(--gray-50);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Stats Cards - Consistent with staff page */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* Announcement Item - Updated with consistent styling */
        .announcement-item {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        .announcement-item:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .announcement-title {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .announcement-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .announcement-actions {
            display: flex;
            gap: 0.5rem;
        }

        .announcement-content {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-100);
        }

        /* Priority Badge - Consistent with staff page */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .priority-high {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        .priority-medium {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }

        .priority-normal {
            background: var(--light-blue);
            color: var(--primary-blue);
            border: 1px solid #BFDBFE;
        }

        /* Status Badge - Consistent with staff page */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
        }

        .status-accepted {
            background: #D1FAE5;
            color: #059669;
            border-color: #A7F3D0;
        }

        .status-dismissed {
            background: #F3F4F6;
            color: #374151;
            border-color: #E5E7EB;
        }

        /* Response Section - Updated styling */
        .response-section {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .response-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1rem;
        }

        .response-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn-response {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-accept {
            background: white;
            color: var(--success-green);
            border-color: var(--success-green);
        }

        .btn-accept:hover {
            background: #D1FAE5;
            transform: translateY(-1px);
        }

        .btn-dismiss {
            background: white;
            color: var(--gray-600);
            border-color: var(--gray-300);
        }

        .btn-dismiss:hover {
            background: var(--gray-100);
            transform: translateY(-1px);
        }

        .response-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--gray-200);
        }

        /* Response Stats - Consistent with staff page */
        .response-stats {
            display: flex;
            gap: 1.5rem;
        }

        .response-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .response-icon {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .accepted-icon {
            background: #D1FAE5;
            color: #059669;
        }

        .pending-icon {
            background: #FEF3C7;
            color: #D97706;
        }

        .dismissed-icon {
            background: #FEE2E2;
            color: #DC2626;
        }

        /* Image Preview */
        .image-preview {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            margin-bottom: 1rem;
            max-height: 200px;
            cursor: pointer;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .image-preview:hover img {
            transform: scale(1.05);
        }

        .btn-view-image {
            background: white;
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view-image:hover {
            background: var(--light-blue);
            transform: translateY(-1px);
        }

        /* Modal - Consistent with staff page */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
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

        .modal {
            background: white;
            border-radius: 12px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
            padding: 0.25rem;
            line-height: 1;
        }

        .modal-close:hover {
            color: var(--gray-700);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .empty-description {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        /* Utility Classes */
        .whitespace-pre-line {
            white-space: pre-line;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .announcement-header {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .announcement-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .response-buttons {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .response-stats {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
        }

        @media (max-width: 640px) {
            .modal {
                width: 95%;
                margin: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="main-container px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex flex-col gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Community Announcements</h1>
                    <p class="text-gray-600 mt-1">Stay updated with important barangay health information</p>
                </div>
                
                <!-- Stats Cards - Consistent with staff page -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-bullhorn text-blue-600"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= count($announcements) ?></div>
                                <div class="stat-label">Total Announcements</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= $acceptedCount ?></div>
                                <div class="stat-label">Accepted</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= $pendingCount ?></div>
                                <div class="stat-label">Pending Response</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-times-circle text-gray-600"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= $dismissedCount ?></div>
                                <div class="stat-label">Dismissed</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <span class="text-red-700"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500"></i>
                <span class="text-green-700"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="space-y-4">
            <?php if (empty($announcements)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h3 class="empty-title">No Announcements Available</h3>
                            <p class="empty-description">There are currently no active announcements. Check back later for updates.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <!-- Header -->
                        <div class="announcement-header">
                            <div class="flex-1">
                                <h3 class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></h3>
                                <div class="announcement-meta">
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($announcement['staff_name'] ?? 'Community Staff') ?>
                                    </span>
                                    <span class="mx-2">•</span>
                                    <span class="flex items-center gap-1">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="announcement-actions">
                                <button onclick="openViewModal(<?= htmlspecialchars(json_encode($announcement, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"
                                        class="btn btn-secondary btn-sm"
                                        title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </div>

                        <!-- Priority and Status Badges -->
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                <i class="fas fa-flag text-xs"></i>
                                <?= ucfirst($announcement['priority']) ?> Priority
                            </span>
                            
                            <?php if ($announcement['user_status']): ?>
                                <span class="status-badge status-<?= $announcement['user_status'] ?>">
                                    <?php if ($announcement['user_status'] === 'accepted'): ?>
                                        <i class="fas fa-check text-xs"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times text-xs"></i>
                                    <?php endif; ?>
                                    <?= ucfirst($announcement['user_status']) ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #FEF3C7; color: #D97706; border-color: #FDE68A;">
                                    <i class="fas fa-clock text-xs"></i>
                                    Pending Response
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Image Preview -->
                        <?php if ($announcement['image_path']): ?>
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium text-gray-700">Attached Image</span>
                                    <button onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')" 
                                            class="btn-view-image">
                                        <i class="fas fa-expand mr-1"></i> View Full
                                    </button>
                                </div>
                                <div class="image-preview">
                                    <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                         alt="Announcement Image"
                                         onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')">
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Content Preview -->
                        <div class="announcement-content line-clamp-2">
                            <?= htmlspecialchars($announcement['message']) ?>
                        </div>

                        <!-- Expiry Date -->
                        <?php if ($announcement['expiry_date']): ?>
                            <div class="mb-3">
                                <span class="text-sm text-blue-600 flex items-center gap-1">
                                    <i class="fas fa-clock"></i>
                                    Expires: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <!-- Footer -->
                        <div class="announcement-footer">
                            <div>
                                <?php if (strlen($announcement['message']) > 100): ?>
                                    <button onclick="openViewModal(<?= htmlspecialchars(json_encode($announcement, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)"
                                            class="text-blue-600 hover:text-blue-800 font-medium text-sm inline-flex items-center gap-1">
                                        Read full message
                                        <i class="fas fa-arrow-right text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="response-stats">
                                <?php if ($announcement['response_date']): ?>
                                    <div class="response-stat">
                                        <div class="response-icon <?= $announcement['user_status'] === 'accepted' ? 'accepted-icon' : 'dismissed-icon' ?>">
                                            <i class="fas fa-<?= $announcement['user_status'] === 'accepted' ? 'check' : 'times' ?>"></i>
                                        </div>
                                        <span class="text-gray-600">Responded</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Response Section -->
                        <div class="response-section">
                            <?php if ($announcement['user_status'] === 'accepted'): ?>
                                <div class="response-status">
                                    <div class="response-icon accepted-icon">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">You accepted this announcement</p>
                                        <?php if ($announcement['response_date']): ?>
                                            <p class="text-sm text-gray-500 mt-1">
                                                Responded on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="respond_to_announcement" value="dismissed" 
                                                class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-times mr-1"></i> Dismiss
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($announcement['user_status'] === 'dismissed'): ?>
                                <div class="response-status">
                                    <div class="response-icon dismissed-icon">
                                        <i class="fas fa-times"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">You dismissed this announcement</p>
                                        <?php if ($announcement['response_date']): ?>
                                            <p class="text-sm text-gray-500 mt-1">
                                                Responded on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" action="">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="respond_to_announcement" value="accepted" 
                                                class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-check mr-1"></i> Accept
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <p class="response-title">Please respond to this announcement:</p>
                                    <div class="response-buttons">
                                        <form method="POST" action="" class="flex-1">
                                            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                            <button type="submit" name="respond_to_announcement" value="accepted" 
                                                    class="btn-response btn-accept">
                                                <i class="fas fa-check-circle"></i>
                                                Accept Announcement
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="flex-1">
                                            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                            <button type="submit" name="respond_to_announcement" value="dismissed" 
                                                    class="btn-response btn-dismiss">
                                                <i class="fas fa-times-circle"></i>
                                                Dismiss Announcement
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Announcement Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Announcement Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button onclick="closeViewModal()" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Announcement Image</h2>
                <button class="modal-close" onclick="closeImageModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Announcement Image" class="w-full h-auto rounded-lg">
            </div>
            <div class="modal-footer">
                <button id="downloadImage" class="btn btn-primary">
                    <i class="fas fa-download mr-2"></i> Download Image
                </button>
                <button onclick="closeImageModal()" class="btn btn-secondary">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // View Announcement Modal
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
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">${announcement.title || 'No Title'}</h3>
                            <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                                <span class="flex items-center gap-1">
                                    <i class="fas fa-user"></i>
                                    ${announcement.staff_name || 'Community Staff'}
                                </span>
                                <span class="flex items-center gap-1">
                                    <i class="fas fa-calendar"></i>
                                    ${postDate}
                                </span>
                            </div>
                        </div>
                        <span class="priority-badge priority-${announcement.priority || 'normal'}">
                            <i class="fas fa-flag text-xs"></i>
                            ${announcement.priority ? announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1) : 'Normal'} Priority
                        </span>
                    </div>
                    
                    ${announcement.expiry_date ? `
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock text-blue-500"></i>
                                <div>
                                    <p class="font-medium text-blue-800">Expiration Date</p>
                                    <p class="text-sm text-blue-600">
                                        ${new Date(announcement.expiry_date).toLocaleDateString('en-US', { 
                                            year: 'numeric', 
                                            month: 'long', 
                                            day: 'numeric'
                                        })}
                                    </p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    
                    ${announcement.image_path ? `
                        <div>
                            <h4 class="font-medium text-gray-800 mb-2">Attached Image</h4>
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                <img src="${announcement.image_path}" 
                                     alt="Announcement Image" 
                                     class="w-full h-auto max-h-[300px] object-contain">
                            </div>
                        </div>
                    ` : ''}
                    
                    <div>
                        <h4 class="font-medium text-gray-800 mb-2">Message Content</h4>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <p class="text-gray-700 whitespace-pre-line">${announcement.message || 'No message provided'}</p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-medium text-gray-800 mb-3">Your Response</h4>
            `;
            
            if (announcement.user_status === 'accepted') {
                const responseDate = announcement.response_date ? new Date(announcement.response_date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '';
                
                content += `
                    <div class="flex items-center gap-3 p-4 bg-green-50 rounded-lg border border-green-200">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-green-800">You accepted this announcement</p>
                            ${responseDate ? `<p class="text-sm text-green-600 mt-1">Responded on ${responseDate}</p>` : ''}
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="announcement_id" value="${announcement.id}">
                            <button type="submit" name="respond_to_announcement" value="dismissed" 
                                    class="btn btn-outline-secondary">
                                <i class="fas fa-times mr-2"></i> Dismiss
                            </button>
                        </form>
                    </div>
                `;
            } else if (announcement.user_status === 'dismissed') {
                const responseDate = announcement.response_date ? new Date(announcement.response_date).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '';
                
                content += `
                    <div class="flex items-center gap-3 p-4 bg-gray-100 rounded-lg border border-gray-200">
                        <div class="w-12 h-12 bg-gray-200 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-gray-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800">You dismissed this announcement</p>
                            ${responseDate ? `<p class="text-sm text-gray-600 mt-1">Responded on ${responseDate}</p>` : ''}
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="announcement_id" value="${announcement.id}">
                            <button type="submit" name="respond_to_announcement" value="accepted" 
                                    class="btn btn-outline-success">
                                <i class="fas fa-check mr-2"></i> Accept
                            </button>
                        </form>
                    </div>
                `;
            } else {
                content += `
                    <div class="flex items-center gap-3 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-yellow-800">No response recorded</p>
                            <p class="text-sm text-yellow-600 mt-1">Please respond using the buttons below.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <form method="POST" action="" class="w-full">
                            <input type="hidden" name="announcement_id" value="${announcement.id}">
                            <button type="submit" name="respond_to_announcement" value="accepted" 
                                    class="btn btn-success w-full">
                                <i class="fas fa-check-circle mr-2"></i>
                                Accept Announcement
                            </button>
                        </form>
                        <form method="POST" action="" class="w-full">
                            <input type="hidden" name="announcement_id" value="${announcement.id}">
                            <button type="submit" name="respond_to_announcement" value="dismissed" 
                                    class="btn btn-outline-secondary w-full">
                                <i class="fas fa-times-circle mr-2"></i>
                                Dismiss Announcement
                            </button>
                        </form>
                    </div>
                `;
            }
            
            content += `</div></div>`;
            
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

        // Image Modal
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const image = document.getElementById('modalImage');
            const downloadBtn = document.getElementById('downloadImage');
            
            image.src = imageSrc;
            
            // Set download link
            downloadBtn.onclick = function() {
                const link = document.createElement('a');
                link.href = imageSrc;
                const fileName = imageSrc.split('/').pop() || 'announcement-image.jpg';
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            };
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });

        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeViewModal();
                closeImageModal();
            }
        });

        // Initialize animation for announcement cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.announcement-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(10px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>