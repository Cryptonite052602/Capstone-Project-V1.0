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
                                    <button onclick="openUserDetailsForValidation(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                            class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 mr-2 inline-block">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                    
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

    // Function to open user details with ID validation
    function openUserDetailsForValidation(user) {
        const modal = document.getElementById('userDetailsModal');
        if (!modal) {
            console.error('User details modal not found');
            return;
        }

        // Handle ID image
        const idImageSection = document.getElementById('idImageSection');
        const noIdImageSection = document.getElementById('noIdImageSection');
        const imagePath = user.id_image_path;

        if (imagePath && imagePath.trim() !== '') {
            const testImage = new Image();
            testImage.onload = function() {
                document.getElementById('userIdImage').src = imagePath;
                document.getElementById('userIdImageLink').href = imagePath;
                document.getElementById('imagePathDisplay').textContent = imagePath;
                idImageSection.classList.remove('hidden');
                noIdImageSection.classList.add('hidden');
                
                // Show ID validation section
                document.getElementById('idValidationSection').classList.remove('hidden');
                document.getElementById('idValidationContent').classList.add('hidden');
                document.getElementById('idValidationLoading').classList.add('hidden');
                document.getElementById('noValidationYetSection').classList.remove('hidden');
            };
            testImage.onerror = function() {
                idImageSection.classList.add('hidden');
                noIdImageSection.classList.remove('hidden');
                document.getElementById('idValidationSection').classList.add('hidden');
            };
            testImage.src = imagePath;
        } else {
            idImageSection.classList.add('hidden');
            noIdImageSection.classList.remove('hidden');
            document.getElementById('idValidationSection').classList.add('hidden');
        }

        // Store user ID for validation
        currentUserDetailsId = user.id;

        // Show modal
        modal.classList.remove('hidden');
    }

    function closeUserDetailsModal() {
        document.getElementById('userDetailsModal').classList.add('hidden');
    }

    // Validate ID function
    function validateUploadedId(userId) {
        const idValidationLoading = document.getElementById('idValidationLoading');
        const idValidationContent = document.getElementById('idValidationContent');
        const noValidationYetSection = document.getElementById('noValidationYetSection');
        
        idValidationLoading.classList.remove('hidden');
        idValidationContent.classList.add('hidden');
        noValidationYetSection.classList.add('hidden');
        
        const formData = new FormData();
        formData.append('user_id', userId);
        
        fetch('/community-health-tracker/api/validate_id.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            idValidationLoading.classList.add('hidden');
            
            if (data.success) {
                displayIdValidationResult(data);
            } else {
                showIdValidationError(data.error || 'An error occurred during validation');
            }
        })
        .catch(error => {
            idValidationLoading.classList.add('hidden');
            console.error('Validation error:', error);
            showIdValidationError('Failed to validate ID document. Please try again.');
        });
    }

    function displayIdValidationResult(data) {
        const idValidationContent = document.getElementById('idValidationContent');
        const noValidationYetSection = document.getElementById('noValidationYetSection');
        const validationStatusBox = document.getElementById('validationStatusBox');
        const validationStatusIcon = document.getElementById('validationStatusIcon');
        const validationStatusText = document.getElementById('validationStatusText');
        const validationStatusMessage = document.getElementById('validationStatusMessage');
        const validationIssuesSection = document.getElementById('validationIssuesSection');
        const validationIssuesList = document.getElementById('validationIssuesList');
        const validationSuccessSection = document.getElementById('validationSuccessSection');
        
        idValidationContent.classList.remove('hidden');
        noValidationYetSection.classList.add('hidden');
        
        document.getElementById('validationFileName').textContent = data.file_name || 'N/A';
        document.getElementById('validationFileType').textContent = data.file_type || 'N/A';
        document.getElementById('validationFileSize').textContent = data.file_size_formatted || 'N/A';
        
        if (data.image_width && data.image_height) {
            document.getElementById('validationImageResolution').textContent = data.image_width + 'x' + data.image_height + 'px';
        } else {
            document.getElementById('validationImageResolution').textContent = 'N/A';
        }
        
        if (data.validation_passed) {
            validationStatusBox.className = 'p-3 rounded-lg bg-green-50 border border-green-200';
            validationStatusIcon.className = 'fas fa-check-circle text-green-600 mt-1';
            validationStatusText.textContent = 'Validation Passed';
            validationStatusText.className = 'text-sm font-semibold text-green-700';
            validationStatusMessage.textContent = data.message || 'ID document is valid and meets all requirements';
            validationStatusMessage.className = 'text-xs text-green-600 mt-1';
            
            validationIssuesSection.classList.add('hidden');
            validationSuccessSection.classList.remove('hidden');
        } else {
            validationStatusBox.className = 'p-3 rounded-lg bg-red-50 border border-red-200';
            validationStatusIcon.className = 'fas fa-exclamation-circle text-red-600 mt-1';
            validationStatusText.textContent = 'Validation Failed';
            validationStatusText.className = 'text-sm font-semibold text-red-700';
            validationStatusMessage.textContent = data.message || 'ID document failed validation checks';
            validationStatusMessage.className = 'text-xs text-red-600 mt-1';
            
            validationSuccessSection.classList.add('hidden');
            
            if (data.validation_issues && data.validation_issues.length > 0) {
                validationIssuesList.innerHTML = '';
                data.validation_issues.forEach(issue => {
                    const listItem = document.createElement('li');
                    listItem.className = 'text-xs text-red-600 flex items-start';
                    listItem.innerHTML = '<i class="fas fa-times-circle mr-2 mt-0.5 flex-shrink-0"></i><span>' + escapeHtml(issue) + '</span>';
                    validationIssuesList.appendChild(listItem);
                });
                validationIssuesSection.classList.remove('hidden');
            } else {
                validationIssuesSection.classList.add('hidden');
            }
        }
    }

    function showIdValidationError(errorMessage) {
        const idValidationContent = document.getElementById('idValidationContent');
        const noValidationYetSection = document.getElementById('noValidationYetSection');
        const validationStatusBox = document.getElementById('validationStatusBox');
        const validationStatusIcon = document.getElementById('validationStatusIcon');
        const validationStatusText = document.getElementById('validationStatusText');
        const validationStatusMessage = document.getElementById('validationStatusMessage');
        
        idValidationContent.classList.remove('hidden');
        noValidationYetSection.classList.add('hidden');
        
        validationStatusBox.className = 'p-3 rounded-lg bg-red-50 border border-red-200';
        validationStatusIcon.className = 'fas fa-exclamation-triangle text-red-600 mt-1';
        validationStatusText.textContent = 'Validation Error';
        validationStatusText.className = 'text-sm font-semibold text-red-700';
        validationStatusMessage.textContent = errorMessage;
        validationStatusMessage.className = 'text-xs text-red-600 mt-1';
        
        document.getElementById('validationIssuesSection').classList.add('hidden');
        document.getElementById('validationSuccessSection').classList.add('hidden');
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Store current user ID
    let currentUserDetailsId = null;
</script>

<!-- User Details Modal (simplified for manage_accounts.php) -->
<div id="userDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-10 mx-auto p-4 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-900">Patient Details & ID Validation</h3>
            <button type="button" onclick="closeUserDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="bg-gray-50 rounded-lg mb-4">
            <!-- ID Image Section -->
            <div class="grid grid-cols-1 gap-4 px-4 pb-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h4 class="font-semibold text-gray-700 border-b pb-2 mb-3">ID Document</h4>
                    <div id="idImageSection" class="hidden">
                        <div class="flex flex-col items-center">
                            <div class="w-full h-48 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center mb-3 overflow-hidden">
                                <img id="userIdImage" src="" alt="Uploaded ID Image" class="w-full h-full object-contain">
                            </div>
                            <div class="text-center w-full">
                                <p class="text-xs text-gray-500 mb-2 truncate" id="imagePathDisplay"></p>
                                <a id="userIdImageLink" href="#" target="_blank" class="inline-flex items-center bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 transition">
                                    <i class="fas fa-external-link-alt mr-1"></i> Full Size
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div id="noIdImageSection" class="hidden">
                        <div class="w-full h-48 bg-yellow-50 border-2 border-dashed border-yellow-300 rounded-lg flex flex-col items-center justify-center p-4">
                            <i class="fas fa-id-card text-yellow-500 text-2xl mb-2"></i>
                            <p class="text-yellow-700 text-sm font-medium text-center">No ID Image Uploaded</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ID Validation Section -->
            <div id="idValidationSection" class="hidden px-4 pb-4">
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-700">ID Document Validation</h4>
                        <button type="button" onclick="validateUploadedId(currentUserDetailsId)" 
                                class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded text-xs hover:bg-blue-700 transition">
                            <i class="fas fa-check-circle mr-1"></i> Validate ID
                        </button>
                    </div>
                    
                    <div id="idValidationLoading" class="hidden text-center py-4">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-lg"></i>
                        <p class="text-sm text-gray-600 mt-2">Validating ID document...</p>
                    </div>

                    <div id="idValidationContent" class="hidden space-y-3">
                        <div class="p-3 rounded-lg" id="validationStatusBox">
                            <div class="flex items-start">
                                <i id="validationStatusIcon" class="fas fa-circle text-gray-400 mt-1"></i>
                                <div class="ml-3">
                                    <h5 id="validationStatusText" class="text-sm font-semibold text-gray-700">Checking...</h5>
                                    <p id="validationStatusMessage" class="text-xs text-gray-600 mt-1"></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-200">
                            <div>
                                <span class="text-xs font-medium text-gray-600">File Name:</span>
                                <p id="validationFileName" class="text-xs text-gray-900 mt-1 break-words">N/A</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-gray-600">File Type:</span>
                                <p id="validationFileType" class="text-xs text-gray-900 mt-1">N/A</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-gray-600">File Size:</span>
                                <p id="validationFileSize" class="text-xs text-gray-900 mt-1">N/A</p>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-gray-600">Image Resolution:</span>
                                <p id="validationImageResolution" class="text-xs text-gray-900 mt-1">N/A</p>
                            </div>
                        </div>

                        <div id="validationIssuesSection" class="hidden pt-3 border-t border-gray-200">
                            <h5 class="text-xs font-semibold text-red-700 mb-2">⚠ Validation Issues:</h5>
                            <ul id="validationIssuesList" class="space-y-1"></ul>
                        </div>

                        <div id="validationSuccessSection" class="hidden p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-600 mt-1 mr-3"></i>
                                <div>
                                    <p class="text-xs font-semibold text-green-700">Valid ID Document</p>
                                    <p class="text-xs text-green-600 mt-1">This ID document has passed all validation checks.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="noValidationYetSection" class="text-center py-4">
                        <i class="fas fa-file-circle-question text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">Click "Validate ID" to check the uploaded ID document</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 mt-4">
            <button type="button" onclick="closeUserDetailsModal()" class="px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition font-medium">
                Close
            </button>
        </div>
    </div>
</div>

