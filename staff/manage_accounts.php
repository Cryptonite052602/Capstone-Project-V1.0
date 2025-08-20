<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Function to send email notification
function sendAccountStatusEmail($email, $status, $message = '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false; // Skip invalid emails
    }

    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'cabanagarchiel@gmail.com'; // Replace with your email
        $mail->Password   = 'qmdh ofnf bhfj wxsa'; // Replace with your email password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Community Health Tracker');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        
        if ($status === 'approved') {
            $mail->Subject = 'Your Account Has Been Approved';
            $mail->Body    = '
                <h2>Account Approved</h2>
                <p>Your account with Community Health Tracker has been approved by our staff.</p>
                <p>You can now log in and access all features of our system.</p>
                <p>Thank you for joining us!</p>
            ';
        } else {
            $mail->Subject = 'Your Account Approval Was Declined';
            $mail->Body    = '
                <h2>Account Declined</h2>
                <p>We regret to inform you that your account with Community Health Tracker was not approved.</p>
                <p>Reason: ' . htmlspecialchars($message) . '</p>
                <p>If you believe this was a mistake, please contact our support team.</p>
            ';
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_user'])) {
        $userId = intval($_POST['user_id']);
        $action = $_POST['action'];
        
        // Get user details first
        try {
            $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception("User not found.");
            }
            
            if (in_array($action, ['approve', 'decline'])) {
                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = TRUE WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Send approval email
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'approved');
                    }
                    
                    $_SESSION['success'] = 'User approved successfully!';
                } else {
                    $declineReason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : 'No reason provided';
                    
                    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = FALSE, status = 'declined' WHERE id = ?");
                    $stmt->execute([$userId]);
                    
                    // Send decline email with reason
                    if (isset($user['email'])) {
                        sendAccountStatusEmail($user['email'], 'declined', $declineReason);
                    }
                    
                    $_SESSION['success'] = 'User declined successfully!';
                }
                
                header('Location: manage_accounts.php');
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error processing user: ' . $e->getMessage();
            header('Location: manage_accounts.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
            header('Location: manage_accounts.php');
            exit();
        }
    }
}

// Get unapproved users
$unapprovedUsers = [];

try {
    $stmt = $pdo->query("SELECT * FROM sitio1_users WHERE approved = FALSE AND (status IS NULL OR status != 'declined') ORDER BY created_at DESC");
    $unapprovedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching accounts: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Manage Patient Accounts</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Pending User Approvals -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4">Pending Patient Approvals</h2>
        
        <?php if (empty($unapprovedUsers)): ?>
            <p class="text-gray-600">No pending patient approvals.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Full Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date Registered</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unapprovedUsers as $user): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['full_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" name="approve_user" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 mr-2">Approve</button>
                                    </form>
                                    
                                    <!-- Decline with reason modal trigger -->
                                    <button onclick="openDeclineModal(<?= $user['id'] ?>)" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Decline</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Decline Modal -->
<div id="declineModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Decline Patient Account</h3>
            <div class="mt-2 px-7 py-3">
                <form id="declineForm" method="POST" action="">
                    <input type="hidden" name="user_id" id="declineUserId">
                    <input type="hidden" name="action" value="decline">
                    <div class="mb-4">
                        <label for="decline_reason" class="block text-gray-700 text-sm font-bold mb-2">Reason for declining:</label>
                        <textarea name="decline_reason" id="decline_reason" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
                    </div>
                    <div class="items-center px-4 py-3">
                        <button type="submit" name="approve_user" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Confirm Decline
                        </button>
                        <button type="button" onclick="closeDeclineModal()" class="ml-3 px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openDeclineModal(userId) {
        document.getElementById('declineUserId').value = userId;
        document.getElementById('declineModal').classList.remove('hidden');
    }
    
    function closeDeclineModal() {
        document.getElementById('declineModal').classList.add('hidden');
    }
</script>

