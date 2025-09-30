<?php
require_once __DIR__ . '/../includes/auth.php';

// Make sure the session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = isset($_SESSION['user']['role']) ? $_SESSION['user']['role'] : '';

// Store the role before clearing session
$redirectRole = $role;

// Clear all session variables
$_SESSION = [];

// Remove the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Determine redirect URL
$redirectUrl = ($redirectRole === 'admin' || $redirectRole === 'staff') 
    ? '/community-health-tracker/index-admin-staff.php' 
    : '/community-health-tracker/index.php';

// Show loading animation before redirect
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="fixed inset-0 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-20 backdrop-blur-sm"></div>
        <div class="relative bg-white/50 backdrop-blur-lg rounded-xl p-8 max-w-md w-full shadow-lg border border-white/30 animate-fade-in">
            <div class="flex flex-col items-center">
                <div class="relative w-16 h-16 mb-4">
                    <div class="absolute inset-0 rounded-full border-4 border-blue-500/80 border-t-transparent animate-spin"></div>
                    <div class="absolute inset-2 rounded-full border-4 border-blue-500/80 border-t-transparent animate-spin" style="animation-delay: -0.3s"></div>
                    <div class="absolute inset-1 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Logging Out</h3>
                <p class="text-gray-600 text-center">Please wait while we securely sign you out...</p>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = '$redirectUrl';
        }, 1000);
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</body>
</html>
HTML;
exit();
?>