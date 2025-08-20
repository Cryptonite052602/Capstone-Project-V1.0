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
$userData = null; // Initialize as null
$error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC); // Get user data
    
    if (!$userData) {
        $error = 'User data not found.';
    }
} catch (PDOException $e) {
    $error = 'Error fetching user data: ' . $e->getMessage();
}

// Get stats for dashboard
$stats = [
    'pending_consultations' => 0,
    'upcoming_appointments' => 0,
    'unread_announcements' => 0
];

if ($userData) { // Only fetch stats if user exists
    try {
        // Pending consultations
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_consultations WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$userId]);
        $stats['pending_consultations'] = $stmt->fetchColumn();

        // Upcoming appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_appointments ua 
                              JOIN sitio1_appointments a ON ua.appointment_id = a.id 
                              WHERE ua.user_id = ? AND ua.status = 'approved' AND a.date >= CURDATE()");
        $stmt->execute([$userId]);
        $stats['upcoming_appointments'] = $stmt->fetchColumn();

        // Unread announcements
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_announcements a 
                              LEFT JOIN user_announcements ua ON a.id = ua.announcement_id AND ua.user_id = ? 
                              WHERE ua.id IS NULL");
        $stmt->execute([$userId]);
        $stats['unread_announcements'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $error = 'Error fetching statistics: ' . $e->getMessage();
    }
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">User Dashboard</h1>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-file-medical text-2xl text-yellow-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Consultations</h3>
                    <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending_consultations'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-calendar-check text-2xl text-green-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Upcoming Appointments</h3>
                    <p class="text-3xl font-bold text-green-600"><?= $stats['upcoming_appointments'] ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="flex items-center">
                <i class="fas fa-bell text-2xl text-blue-600 mr-3"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Unread Announcements</h3>
                    <p class="text-3xl font-bold text-blue-600"><?= $stats['unread_announcements'] ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4">Your Information</h2>
        
        <?php if ($userData): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-gray-600"><span class="font-semibold">Full Name:</span> <?= htmlspecialchars($userData['full_name']) ?></p>
                    <p class="text-gray-600"><span class="font-semibold">Age:</span> <?= $userData['age'] ? htmlspecialchars($userData['age']) : 'N/A' ?></p>
                    <p class="text-gray-600"><span class="font-semibold">Contact:</span> <?= $userData['contact'] ? htmlspecialchars($userData['contact']) : 'N/A' ?></p>
                </div>
                <div>
                    <p class="text-gray-600"><span class="font-semibold">Address:</span> <?= $userData['address'] ? htmlspecialchars($userData['address']) : 'N/A' ?></p>
                    <p class="text-gray-600"><span class="font-semibold">Account Status:</span> 
                        <?= $userData['approved'] ? 'Approved' : 'Pending Approval' ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-600">Your account information could not be loaded.</p>
        <?php endif; ?>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4">Recent Activities</h2>
        <div class="space-y-4">
            <p class="text-gray-600">No recent activities yet.</p>
        </div>
    </div>
</div>

