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
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
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
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-light);
            overflow: hidden;
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
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #dc2626;
            border: 2px solid #fecaca;
        }
        
        .priority-medium {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
            border: 2px solid #fde68a;
        }
        
        .priority-normal {
            background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
            color: var(--primary-blue);
            border: 2px solid #bfdbfe;
        }
        
        /* Status badges */
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-accepted {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 2px solid #a7f3d0;
        }
        
        .status-dismissed {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        /* Image preview */
        .image-preview {
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--border-light);
            transition: all 0.3s ease;
        }
        
        .image-preview:hover {
            border-color: var(--primary-blue);
            transform: scale(1.02);
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
        
        /* Response buttons */
        .response-buttons {
            display: flex;
            gap: 16px;
            margin-top: 20px;
        }
        
        .btn-accept {
            flex: 1;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        
        .btn-accept:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        
        .btn-dismiss {
            flex: 1;
            background: white;
            color: #374151;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 600;
            border: 2px solid #d1d5db;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-dismiss:hover {
            background: #f3f4f6;
            transform: translateY(-3px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* View image button */
        .btn-view-image {
            background: linear-gradient(135deg, var(--light-blue), #bfdbfe);
            color: var(--dark-blue);
            border: 2px solid #bfdbfe;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-view-image:hover {
            background: #bfdbfe;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
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
            border-radius: 20px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.5rem;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .close-modal {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--light-blue);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.25rem;
            color: var(--primary-blue);
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: var(--primary-blue);
            color: white;
            transform: rotate(90deg);
        }

        /* Announcement card enhancements */
        .announcement-header {
            background: linear-gradient(135deg, var(--light-blue), white);
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-light);
        }

        .announcement-content {
            padding: 1.5rem;
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

        /* Stats cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 115, 211, 0.2);
        }

        .stat-card-secondary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
        }

        /* Message truncation */
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .whitespace-pre-line {
            white-space: pre-line;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .response-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                padding: 1.5rem;
                margin: 0.5rem;
            }
            
            .stat-card, .stat-card-secondary {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
            <div class="mb-6 md:mb-0">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-[#0073D3] to-[#4A90E2] rounded-2xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-bullhorn text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Community Announcements</h1>
                        <p class="text-gray-600 mt-1">Barangay Luz Health Monitoring System</p>
                    </div>
                </div>
                <p class="text-gray-600 max-w-2xl">Stay updated with the latest community health news, alerts, and important information from our staff.</p>
            </div>
            
            <div class="flex space-x-4">
                <div class="stat-card">
                    <p class="text-white/90 font-semibold text-sm">Total Announcements</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= count($announcements) ?></p>
                </div>
                <div class="stat-card-secondary">
                    <p class="text-white/90 font-semibold text-sm">Responded</p>
                    <p class="text-3xl font-bold text-white mt-2">
                        <?php 
                            $respondedCount = count(array_filter($announcements, function($a) {
                                return !empty($a['user_status']);
                            }));
                            echo $respondedCount;
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="bg-gradient-to-r from-red-50 to-red-100 border-2 border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                </div>
                <div>
                    <p class="font-medium"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-gradient-to-r from-green-50 to-green-100 border-2 border-green-200 text-green-700 px-6 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <div>
                    <p class="font-medium"><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="space-y-6">
            <?php if (empty($announcements)): ?>
                <div class="info-card p-12 text-center">
                    <div class="w-24 h-24 bg-gradient-to-br from-blue-50 to-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-bullhorn text-4xl text-blue-300"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-3">No Announcements Yet</h3>
                    <p class="text-gray-500 max-w-md mx-auto mb-8">There are currently no active announcements. Check back later for updates from our community health team.</p>
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-2xl border-2 border-blue-200 max-w-md mx-auto">
                        <h4 class="font-semibold text-blue-800 mb-2">Stay Informed</h4>
                        <p class="text-blue-700 text-sm">Important announcements will appear here when available. Make sure to check regularly for updates.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="info-card overflow-hidden">
                        <!-- Header Section -->
                        <div class="announcement-header">
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-14 h-14 bg-gradient-to-br from-[#0073D3] to-[#4A90E2] rounded-2xl flex items-center justify-center shadow-lg flex-shrink-0">
                                        <i class="fas fa-bullhorn text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-xl text-gray-800 mb-1"><?= htmlspecialchars($announcement['title']) ?></h3>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-gray-600 text-sm">
                                                By <span class="font-semibold text-blue-600"><?= htmlspecialchars($announcement['staff_name'] ?? 'Community Staff') ?></span>
                                            </p>
                                            <span class="text-gray-400">•</span>
                                            <span class="text-gray-500 text-sm">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <span class="priority-badge priority-<?= $announcement['priority'] ?>">
                                        <i class="fas fa-flag text-xs"></i>
                                        <?= ucfirst($announcement['priority']) ?> Priority
                                    </span>
                                    <?php if ($announcement['user_status']): ?>
                                        <div class="status-badge status-<?= $announcement['user_status'] ?>">
                                            <i class="fas fa-<?= $announcement['user_status'] === 'accepted' ? 'check' : 'times' ?> text-xs"></i>
                                            <?= ucfirst($announcement['user_status']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($announcement['expiry_date']): ?>
                                <div class="mt-4 flex items-center gap-2 text-sm text-blue-600 bg-blue-50 px-4 py-2 rounded-lg w-fit">
                                    <i class="fas fa-clock"></i>
                                    <span class="font-medium">Expires:</span>
                                    <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Content Section -->
                        <div class="announcement-content">
                            <?php if ($announcement['image_path']): ?>
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-3">
                                        <p class="text-sm font-semibold text-gray-700">Announcement Image</p>
                                        <button onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')" 
                                                class="btn-view-image">
                                            <i class="fas fa-expand"></i> View Full Image
                                        </button>
                                    </div>
                                    <div class="image-preview max-h-64">
                                        <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                             alt="Announcement Image"
                                             onclick="openImageModal('<?= htmlspecialchars($announcement['image_path']) ?>')"
                                             class="cursor-pointer">
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-6">
                                <p class="text-gray-700 line-clamp-3">
                                    <?= nl2br(htmlspecialchars($announcement['message'])) ?>
                                </p>
                                <?php if (strlen($announcement['message']) > 200): ?>
                                    <button onclick="openViewModal(<?= htmlspecialchars(json_encode($announcement, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)" 
                                            class="text-blue-600 hover:text-blue-800 font-medium text-sm mt-2 inline-flex items-center gap-1">
                                        Read full message
                                        <i class="fas fa-arrow-right text-xs"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Response Section -->
                            <div class="border-t border-gray-100 pt-6">
                                <?php if ($announcement['user_status'] === 'accepted'): ?>
                                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-green-800 font-semibold">You have accepted this announcement</p>
                                                <?php if ($announcement['response_date']): ?>
                                                    <p class="text-green-700 text-sm mt-1">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Responded on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <form method="POST" action="" class="mt-4">
                                            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                            <button type="submit" name="respond_to_announcement" value="dismissed" 
                                                    class="text-sm text-green-600 hover:text-green-800 font-medium underline focus:outline-none inline-flex items-center gap-1">
                                                <i class="fas fa-exchange-alt text-xs"></i>
                                                Change to Dismissed
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($announcement['user_status'] === 'dismissed'): ?>
                                    <div class="bg-gradient-to-r from-gray-50 to-slate-50 border-2 border-gray-200 rounded-xl p-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-times-circle text-gray-500 text-xl"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-gray-800 font-semibold">You have dismissed this announcement</p>
                                                <?php if ($announcement['response_date']): ?>
                                                    <p class="text-gray-600 text-sm mt-1">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Responded on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <form method="POST" action="" class="mt-4">
                                            <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                            <button type="submit" name="respond_to_announcement" value="accepted" 
                                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium underline focus:outline-none inline-flex items-center gap-1">
                                                <i class="fas fa-exchange-alt text-xs"></i>
                                                Change to Accepted
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-2 border-blue-200 rounded-xl p-5">
                                        <div class="flex items-center gap-4 mb-5">
                                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-question-circle text-blue-500 text-xl"></i>
                                            </div>
                                            <div>
                                                <p class="text-blue-800 font-semibold">Action Required</p>
                                                <p class="text-blue-700 text-sm mt-1">Please respond to this announcement to help us track community engagement.</p>
                                            </div>
                                        </div>
                                        <div class="response-buttons">
                                            <form method="POST" action="" class="flex-1">
                                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                                <button type="submit" name="respond_to_announcement" value="accepted" 
                                                        class="btn-accept w-full">
                                                    <i class="fas fa-check-circle"></i>
                                                    Accept Announcement
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="flex-1">
                                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                                <button type="submit" name="respond_to_announcement" value="dismissed" 
                                                        class="btn-dismiss w-full">
                                                    <i class="fas fa-times-circle"></i>
                                                    Dismiss Announcement
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Announcement Modal -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" onclick="closeViewModal()">&times;</button>
            <div id="modalContent"></div>
            <div class="mt-8 flex justify-end">
                <button onclick="closeViewModal()" class="btn-secondary px-6 py-3">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal-overlay">
        <div class="modal-content max-w-4xl">
            <button class="close-modal" onclick="closeImageModal()">&times;</button>
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-image text-white"></i>
                    </div>
                    Announcement Image
                </h2>
                <div class="relative bg-gray-50 rounded-2xl p-2 border-2 border-gray-200">
                    <img id="modalImage" src="" alt="Announcement Image" class="w-full h-auto max-h-[60vh] object-contain rounded-xl">
                </div>
            </div>
            <div class="mt-8 flex justify-end space-x-4">
                <button id="downloadImage" class="btn-primary px-6 py-3">
                    <i class="fas fa-download mr-2"></i> Download Image
                </button>
                <button onclick="closeImageModal()" class="btn-secondary px-6 py-3">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // View Announcement Modal Function
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
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-3xl font-bold text-gray-800">${announcement.title || 'No Title'}</h2>
                        <span class="priority-badge priority-${announcement.priority || 'normal'}">
                            <i class="fas fa-flag text-xs"></i>
                            ${announcement.priority ? announcement.priority.charAt(0).toUpperCase() + announcement.priority.slice(1) : 'Normal'} Priority
                        </span>
                    </div>
                    
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-6 rounded-2xl mb-8 border-2 border-blue-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Posted by:</p>
                                <p class="font-bold text-gray-800 text-lg">${announcement.staff_name || 'Community Staff'}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Posted on:</p>
                                <p class="font-bold text-gray-800 text-lg">${postDate}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Audience:</p>
                                <p class="font-bold text-gray-800">
                                    ${announcement.audience_type === 'public' ? 'All Community Members' : 
                                      announcement.audience_type === 'landing_page' ? 'Public Website' : 
                                      'Specific Recipients'}
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
                                <p class="font-bold text-gray-800">${expiryDate}</p>
                            </div>
                `;
            }
            
            content += `</div></div>`;
            
            if (announcement.image_path) {
                content += `
                    <div class="mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-800 text-xl">Attached Image</h3>
                            <button onclick="openImageModal('${announcement.image_path}')" 
                                    class="btn-view-image">
                                <i class="fas fa-expand mr-1"></i> View Full Image
                            </button>
                        </div>
                        <div class="image-preview max-h-96">
                            <img src="${announcement.image_path}" 
                                 alt="Announcement Image"
                                 onclick="openImageModal('${announcement.image_path}')"
                                 class="cursor-pointer">
                        </div>
                    </div>
                `;
            }
            
            content += `
                <div class="mb-8">
                    <h3 class="font-bold text-gray-800 text-xl mb-4">Message Content</h3>
                    <div class="bg-gray-50 p-8 rounded-2xl border-2 border-gray-200">
                        <p class="text-gray-700 whitespace-pre-line text-lg leading-relaxed">${announcement.message || 'No message provided'}</p>
                    </div>
                </div>
                
                <div class="bg-gradient-to-r from-blue-50 to-gray-50 p-6 rounded-2xl border-2 border-blue-200">
                    <h3 class="font-bold text-gray-800 text-xl mb-6">Your Response</h3>
            `;
            
            if (announcement.user_status === 'accepted') {
                content += `
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-xl p-5">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-green-800 font-bold text-lg">You accepted this announcement</p>
                `;
                
                if (announcement.response_date) {
                    const responseDate = new Date(announcement.response_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    content += `<p class="text-green-700 mt-2"><i class="fas fa-clock mr-2"></i>${responseDate}</p>`;
                }
                
                content += `</div></div></div>`;
            } else if (announcement.user_status === 'dismissed') {
                content += `
                    <div class="bg-gradient-to-r from-gray-50 to-slate-50 border-2 border-gray-200 rounded-xl p-5">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-gray-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-times-circle text-gray-500 text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-800 font-bold text-lg">You dismissed this announcement</p>
                `;
                
                if (announcement.response_date) {
                    const responseDate = new Date(announcement.response_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    content += `<p class="text-gray-600 mt-2"><i class="fas fa-clock mr-2"></i>${responseDate}</p>`;
                }
                
                content += `</div></div></div>`;
            } else {
                content += `
                    <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border-2 border-yellow-200 rounded-xl p-5">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-yellow-500 text-2xl"></i>
                            </div>
                            <div>
                                <p class="text-yellow-800 font-bold text-lg">No response recorded</p>
                                <p class="text-yellow-700 mt-2">Please respond to this announcement using the buttons below.</p>
                            </div>
                        </div>
                        <div class="response-buttons mt-6">
                            <form method="POST" action="" class="flex-1">
                                <input type="hidden" name="announcement_id" value="${announcement.id}">
                                <button type="submit" name="respond_to_announcement" value="accepted" 
                                        class="btn-accept w-full">
                                    <i class="fas fa-check-circle"></i>
                                    Accept Announcement
                                </button>
                            </form>
                            <form method="POST" action="" class="flex-1">
                                <input type="hidden" name="announcement_id" value="${announcement.id}">
                                <button type="submit" name="respond_to_announcement" value="dismissed" 
                                        class="btn-dismiss w-full">
                                    <i class="fas fa-times-circle"></i>
                                    Dismiss Announcement
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            }
            
            content += `</div>`;
            
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

        // Image Modal Functions
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

        // Add animation to announcement cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>