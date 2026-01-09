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
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    width: 100%;
                    background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                                url('https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    background-attachment: fixed;
                }
                @keyframes fadeIn {
                    from { 
                        opacity: 0; 
                        transform: translateY(20px) scale(0.95); 
                    }
                    to { 
                        opacity: 1; 
                        transform: translateY(0) scale(1); 
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .animate-spin {
                    animation: spin 1s linear infinite;
                }
            </style>
        </head>
        <body>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="relative bg-white/90 backdrop-blur-sm rounded-2xl p-8 max-w-md w-full shadow-xl border border-gray-200 animate-fade-in">
                    <div class="flex flex-col items-center text-center">
                        <!-- Error Spinner -->
                        <div class="relative w-20 h-20 mb-6">
                            <div class="absolute inset-0 rounded-full border-4 border-red-100"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-red-400 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Title -->
                        <h3 class="text-2xl font-semibold text-gray-800 mb-3">Missing Information</h3>
                        
                        <!-- Instruction -->
                        <p class="text-gray-600 text-lg">Please fill in all fields</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '/community-health-tracker/index.php';
                }, 1500);
            </script>
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
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    width: 100%;
                    background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                                url('/community-health-tracker/asssets/images/Dev.jpg');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    background-attachment: fixed;
                }
                @keyframes fadeIn {
                    from { 
                        opacity: 0; 
                        transform: translateY(20px) scale(0.95); 
                    }
                    to { 
                        opacity: 1; 
                        transform: translateY(0) scale(1); 
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .animate-spin {
                    animation: spin 1s linear infinite;
                }
            </style>
        </head>
        <body>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="relative bg-white/90 backdrop-blur-sm rounded-2xl p-8 max-w-md w-full shadow-xl border border-gray-200 animate-fade-in">
                    <div class="flex flex-col items-center text-center">
                        <!-- Success Spinner -->
                        <div class="relative w-20 h-20 mb-6">
                            <div class="absolute inset-0 rounded-full border-4 border-blue-100"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-blue-400 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Title -->
                        <h3 class="text-2xl font-semibold text-gray-800 mb-3">You've successfully signed in.</h3>
                        
                        <!-- Instruction -->
                        <p class="text-gray-600 text-lg">Taking you to your dashboard…</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '../user/dashboard.php';
                }, 1500);
            </script>
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
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    width: 100%;
                    background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                                url('/community-health-tracker/assets/images/Dev.jpg');
                    background-size: cover;
                    background-position: center;
                    background-repeat: no-repeat;
                    background-attachment: fixed;
                }
                @keyframes fadeIn {
                    from { 
                        opacity: 0; 
                        transform: translateY(20px) scale(0.95); 
                    }
                    to { 
                        opacity: 1; 
                        transform: translateY(0) scale(1); 
                    }
                }
                .animate-fade-in {
                    animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .animate-spin {
                    animation: spin 1s linear infinite;
                }
            </style>
        </head>
        <body>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="relative bg-white/90 backdrop-blur-sm rounded-2xl p-8 max-w-md w-full shadow-xl border border-gray-200 animate-fade-in">
                    <div class="flex flex-col items-center text-center">
                        <!-- Error Spinner -->
                        <div class="relative w-20 h-20 mb-6">
                            <div class="absolute inset-0 rounded-full border-4 border-red-100"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-red-400 border-t-transparent animate-spin"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                        </div>
                        
                        <!-- Title -->
                        <h3 class="text-2xl font-semibold text-gray-800 mb-3">Invalid Credentials</h3>
                        
                        <!-- Instruction -->
                        <p class="text-gray-600 text-lg">Please check your username and password</p>
                    </div>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = '../index.php';
                }, 1500);
            </script>
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