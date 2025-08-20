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
            // Check if announcement is active
            $stmt = $pdo->prepare("SELECT id FROM sitio1_announcements WHERE id = ? AND status = 'active'");
            $stmt->execute([$announcementId]);
            
            if (!$stmt->fetch()) {
                $error = 'This announcement is no longer active';
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

// Get all active announcements with user's response status
$announcements = [];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, ua.status as user_status, ua.response_date
        FROM sitio1_announcements a
        LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ?
        WHERE a.status = 'active'
        ORDER BY a.post_date DESC
    ");
    $stmt->execute([$userId]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching announcements: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Announcements</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php if (empty($announcements)): ?>
            <p class="text-gray-600">No active announcements available.</p>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($announcement['title']) ?></h3>
                        <div class="text-right">
                            <span class="block text-sm text-gray-500">Posted: <?= date('M d, Y h:i A', strtotime($announcement['post_date'])) ?></span>
                            <?php if ($announcement['user_status']): ?>
                                <span class="block text-sm text-gray-500">Responded: <?= date('M d, Y h:i A', strtotime($announcement['response_date'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <p class="text-gray-800"><?= nl2br(htmlspecialchars($announcement['message'])) ?></p>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <?php if ($announcement['user_status'] === 'accepted'): ?>
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-green-600 font-medium">You accepted this announcement on <?= date('M d, Y', strtotime($announcement['response_date'])) ?></p>
                            </div>
                            <form method="POST" action="" class="mt-2">
                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                <button type="submit" name="respond_to_announcement" value="dismissed" class="text-sm text-gray-600 hover:text-gray-800 underline">
                                    Change to Dismissed
                                </button>
                            </form>
                        <?php elseif ($announcement['user_status'] === 'dismissed'): ?>
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <p class="text-gray-600">You dismissed this announcement on <?= date('M d, Y', strtotime($announcement['response_date'])) ?></p>
                            </div>
                            <form method="POST" action="" class="mt-2">
                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                <button type="submit" name="respond_to_announcement" value="accepted" class="text-sm text-gray-600 hover:text-gray-800 underline">
                                    Change to Accepted
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="" class="flex space-x-4">
                                <input type="hidden" name="announcement_id" value="<?= $announcement['id'] ?>">
                                <button type="submit" name="respond_to_announcement" value="accepted" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700 transition flex items-center">
                                    <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Accept
                                </button>
                                <button type="submit" name="respond_to_announcement" value="dismissed" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300 transition flex items-center">
                                    <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Dismiss
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

