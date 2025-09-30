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

<div class="container mx-auto px-4 py-6 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Community Announcements</h1>
        <p class="text-gray-600">Important updates and information from community staff</p>
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

    <!-- Announcements List -->
    <div class="space-y-6">
        <?php if (empty($announcements)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No announcements available</h3>
                <p class="mt-2 text-sm text-gray-500">Check back later for community updates and announcements.</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Announcement Header -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <?php if ($announcement['priority'] == 'urgent'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">URGENT</span>
                                    <?php elseif ($announcement['priority'] == 'high'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 mr-2">HIGH PRIORITY</span>
                                    <?php elseif ($announcement['priority'] == 'low'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">LOW PRIORITY</span>
                                    <?php endif; ?>
                                    <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($announcement['title']) ?></h3>
                                </div>
                                
                                <div class="flex flex-wrap items-center text-sm text-gray-500">
                                    <span>Posted by <?= htmlspecialchars($announcement['staff_name'] ?? 'Staff') ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?></span>
                                    <?php if ($announcement['expiry_date']): ?>
                                        <span class="mx-2">•</span>
                                        <span>Expires: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($announcement['user_status']): ?>
                                <div class="mt-3 sm:mt-0 sm:ml-4">
                                    <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                        <?= $announcement['user_status'] === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?php if ($announcement['user_status'] === 'accepted'): ?>
                                            <svg class="-ml-1 mr-1.5 h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Accepted
                                        <?php else: ?>
                                            <svg class="-ml-1 mr-1.5 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                            Dismissed
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Announcement Content -->
                    <div class="p-6">
                        <?php if ($announcement['image_path']): ?>
                            <div class="mb-6 rounded-lg overflow-hidden border border-gray-200">
                                <img src="<?= $announcement['image_path'] ?>" alt="Announcement Image" class="w-full h-auto max-h-96 object-contain cursor-pointer" onclick="openImageModal('<?= $announcement['image_path'] ?>')">
                                <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Announcement Image</span>
                                    <a href="<?= $announcement['image_path'] ?>" download class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                        <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Download
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="prose max-w-none mb-6">
                            <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($announcement['message']) ?></p>
                        </div>
                        
                        <!-- Response Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <?php if ($announcement['user_status'] === 'accepted'): ?>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="text-green-800 font-medium">You accepted this announcement</p>
                                            <p class="text-green-700 text-sm">on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?></p>
                                        </div>
                                    </div>
                                    <form method="POST" action="" class="mt-3">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="respond_to_announcement" value="dismissed" class="text-sm text-green-600 hover:text-green-800 underline focus:outline-none">
                                            Change to Dismissed
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($announcement['user_status'] === 'dismissed'): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 text-gray-500 mr-3" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 000 1.414L8.414 10l2.293 2.293a1 1 0 001.414-1.414L10.414 10l2.293-2.293a1 1 0 00-1.414-1.414L10 8.586l-1.293-1.293a1 1 0 00-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        <div>
                                            <p class="text-gray-800">You dismissed this announcement</p>
                                            <p class="text-gray-600 text-sm">on <?= date('M d, Y \a\t h:i A', strtotime($announcement['response_date'])) ?></p>
                                        </div>
                                    </div>
                                    <form method="POST" action="" class="mt-3">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="respond_to_announcement" value="accepted" class="text-sm text-gray-600 hover:text-gray-800 underline focus:outline-none">
                                            Change to Accepted
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-3">Please respond to this announcement:</p>
                                    <form method="POST" action="" class="flex flex-col sm:flex-row sm:space-x-4 space-y-3 sm:space-y-0">
                                        <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                        <button type="submit" name="respond_to_announcement" value="accepted" class="flex-1 bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Accept
                                        </button>
                                        <button type="submit" name="respond_to_announcement" value="dismissed" class="flex-1 bg-gray-100 text-gray-800 py-3 px-6 rounded-lg hover:bg-gray-200 transition flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Dismiss
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center hidden z-50 p-4">
    <div class="max-w-4xl max-h-full">
        <div class="relative">
            <button onclick="closeImageModal()" class="absolute -top-10 right-0 text-white hover:text-gray-300 focus:outline-none">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <img id="modalImage" src="" alt="Announcement Image" class="max-w-full max-h-screen object-contain">
        </div>
    </div>
</div>

<script>
    // Image modal functions
    function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal() {
        document.getElementById('imageModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside the image
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target.id === 'imageModal') {
            closeImageModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });
</script>