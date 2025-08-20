<?php

require_once __DIR__ . '/../includes/auth.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirectBasedOnRole();
    exit();
}

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user';
    
    // Store username for repopulating form
    $_SESSION['login_form_data'] = [
        'username' => $username
    ];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100">
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="absolute inset-0 bg-black bg-opacity-20 backdrop-blur-sm"></div>
                <div class="relative bg-white/50 backdrop-blur-lg rounded-xl p-8 max-w-md w-full shadow-lg border border-white/30 animate-fade-in">
                    <div class="flex flex-col items-center">
                        <div class="relative w-16 h-16 mb-4">
                            <div class="absolute inset-0 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-2 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin" style="animation-delay: -0.3s"></div>
                            <div class="absolute inset-1 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Please fill in all fields</h3>
                        <p class="text-gray-600 text-center">Redirecting you back...</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '/community-health-tracker/index.php';
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
    }
    
    // Attempt login
    $login_result = loginUser($username, $password, $role);

    if ($login_result === true) {
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100">
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="absolute inset-0 bg-black bg-opacity-20 backdrop-blur-sm"></div>
                <div class="relative bg-white/50 backdrop-blur-lg rounded-xl p-8 max-w-md w-full shadow-lg border border-white/30 animate-fade-in">
                    <div class="flex flex-col items-center">
                        <div class="relative w-16 h-16 mb-4">
                            <div class="absolute inset-0 rounded-full border-4 border-green-500/80 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-2 rounded-full border-4 border-green-500/80 border-t-transparent animate-spin" style="animation-delay: -0.3s"></div>
                            <div class="absolute inset-1 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Login successful!</h3>
                        <p class="text-gray-600 text-center">Redirecting to dashboard...</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '/community-health-tracker/user/dashboard.php';
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
    } else {
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100">
            <div class="fixed inset-0 flex items-center justify-center">
                <div class="absolute inset-0 bg-black bg-opacity-20 backdrop-blur-sm"></div>
                <div class="relative bg-white/50 backdrop-blur-lg rounded-xl p-8 max-w-md w-full shadow-lg border border-white/30 animate-fade-in">
                    <div class="flex flex-col items-center">
                        <div class="relative w-16 h-16 mb-4">
                            <div class="absolute inset-0 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-2 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin" style="animation-delay: -0.3s"></div>
                            <div class="absolute inset-1 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500/90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Invalid credentials</h3>
                        <p class="text-gray-600 text-center">Please try again...</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '/community-health-tracker/index.php';
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
    }
}

// If not POST request, redirect to login
header('Location: /community-health-tracker/login.php');
exit();
?>