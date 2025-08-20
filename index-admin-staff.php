<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (!empty($username) && !empty($password) && !empty($role)) {
        $result = loginUser($username, $password, $role);
        if ($result === true) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'role' => $role]);
            exit;
        } else {
            $error = $result ?: 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Healthcare System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .login-grid {
            display: grid;
            grid-template-columns: 1fr;
            height: 100vh;
        }
        @media (min-width: 768px) {
            .login-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .form-section {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            width: 100%;
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 50%, #bae6fd 100%);
        }
        .image-section {
            position: relative;
            overflow: hidden;
            height: 100%;
            background-color: #f3f4f6;
        }
        .login-image {
            position: absolute;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            min-width: 100%;
            min-height: 100%;
        }
        .form-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        .input-with-icon {
            padding-left: 2.5rem;
        }
        .select-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }
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
</head>

<body>
    <div class="login-grid">

        <!-- Form Section -->
        <div class="form-section">
            <div class="form-container">
                <div class="flex justify-center mb-8">
                    <div class="flex items-center">
                        <div class="bg-[#FC566C] p-2 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <h1 class="text-4xl font-bold text-[#FC566C] ml-3">CHTMS</h1>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-2xl font-semibold text-gray-800">Welcome Back</h2>
                    <p class="text-sm text-gray-600 mt-1">Sign in to your Admin / Staff Account</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded text-sm flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="" class="space-y-6">
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <div class="relative">
                            <div class="input-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <select id="role" name="role" required
                                class="block w-full px-4 py-2.5 pl-10 text-sm border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent appearance-none">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                            <div class="select-icon">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="relative">
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <input type="text" name="username" id="username" placeholder="Enter username" required
                                class="block w-full px-4 py-2.5 pl-10 text-sm border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent input-with-icon">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" name="password" id="password" placeholder="Enter password" required
                                class="block w-full px-4 py-2.5 pl-10 text-sm border border-blue-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent input-with-icon">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" 
                            class="w-full px-4 py-2.5 bg-[#FC566C] text-white font-medium rounded-lg hover:bg-[#e04a5f] focus:outline-none focus:ring-2 focus:ring-[#FC566C] focus:ring-opacity-50 transition-colors flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sign in
                        </button>
                    </div>
                </form>
                
                <div class="mt-6 text-center text-sm text-gray-500">
                    <p>Need help? Contact support@chtms.org</p>
                </div>
            </div>
        </div>

        <!-- Image Section -->
        <div class="hidden md:block image-section">
            <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" 
                 alt="Healthcare professional" class="login-image">
            <div class="absolute inset-0 bg-black bg-opacity-20 flex items-center justify-center">
                <div class="text-white text-center p-8 max-w-md">
                    <h2 class="text-3xl font-bold mb-4">Comprehensive Healthcare Management</h2>
                    <p class="text-lg">Streamlined system for medical professionals to provide exceptional patient care</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Validation Modal -->
    <div id="validationModal" class="modal">
        <div class="absolute inset-0 bg-black bg-opacity-20 backdrop-blur-sm"></div>
        <div class="relative bg-white/50 backdrop-blur-lg rounded-xl p-8 max-w-md w-full shadow-lg border border-white/30 animate-fade-in">
            <div class="flex flex-col items-center">
                <div class="relative w-16 h-16 mb-4">
                    <div id="successSpinner" class="absolute inset-0 rounded-full border-4 border-green-500/80 border-t-transparent animate-spin hidden"></div>
                    <div id="successSpinnerInner" class="absolute inset-2 rounded-full border-4 border-green-500/80 border-t-transparent animate-spin hidden" style="animation-delay: -0.3s"></div>
                    <div id="errorSpinner" class="absolute inset-0 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin hidden"></div>
                    <div id="errorSpinnerInner" class="absolute inset-2 rounded-full border-4 border-red-500/80 border-t-transparent animate-spin hidden" style="animation-delay: -0.3s"></div>
                    <div id="loadingSpinner" class="absolute inset-0 rounded-full border-4 border-blue-500/80 border-t-transparent animate-spin"></div>
                    <div id="loadingSpinnerInner" class="absolute inset-2 rounded-full border-4 border-blue-500/80 border-t-transparent animate-spin" style="animation-delay: -0.3s"></div>
                    <div class="absolute inset-1 flex items-center justify-center">
                        <svg id="successIcon" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500/90 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg id="errorIcon" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500/90 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <div id="loadingIcon" class="loader w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                    </div>
                </div>
                <h3 id="modalTitle" class="text-xl font-semibold text-gray-800 mb-2">Authenticating...</h3>
                <p id="modalMessage" class="text-gray-600 text-center">Please wait while we verify your credentials</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const modal = document.getElementById('validationModal');
            modal.style.display = 'flex';
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('loadingSpinnerInner').classList.add('hidden');
                    document.getElementById('loadingIcon').classList.add('hidden');
                    
                    document.getElementById('successSpinner').classList.remove('hidden');
                    document.getElementById('successSpinnerInner').classList.remove('hidden');
                    document.getElementById('successIcon').classList.remove('hidden');
                    
                    document.getElementById('modalTitle').textContent = 'Login Successful!';
                    document.getElementById('modalMessage').textContent = `Redirecting to ${data.role} dashboard...`;
                    
                    setTimeout(() => {
                        if (data.role === 'admin') {
                            window.location.href = 'admin/dashboard.php';
                        } else if (data.role === 'staff') {
                            window.location.href = 'staff/dashboard.php';
                        }
                    }, 1000);
                } else {
                    // Show error state
                    document.getElementById('loadingSpinner').classList.add('hidden');
                    document.getElementById('loadingSpinnerInner').classList.add('hidden');
                    document.getElementById('loadingIcon').classList.add('hidden');
                    
                    document.getElementById('errorSpinner').classList.remove('hidden');
                    document.getElementById('errorSpinnerInner').classList.remove('hidden');
                    document.getElementById('errorIcon').classList.remove('hidden');
                    
                    document.getElementById('modalTitle').textContent = 'Login Failed';
                    document.getElementById('modalMessage').textContent = 'Invalid credentials. Please try again.';
                    
                    setTimeout(() => {
                        modal.style.display = 'none';
                        window.location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error state
                document.getElementById('loadingSpinner').classList.add('hidden');
                document.getElementById('loadingSpinnerInner').classList.add('hidden');
                document.getElementById('loadingIcon').classList.add('hidden');
                
                document.getElementById('errorSpinner').classList.remove('hidden');
                document.getElementById('errorSpinnerInner').classList.remove('hidden');
                document.getElementById('errorIcon').classList.remove('hidden');
                
                document.getElementById('modalTitle').textContent = 'Error';
                document.getElementById('modalMessage').textContent = 'An error occurred. Please try again.';
                
                setTimeout(() => {
                    modal.style.display = 'none';
                    window.location.reload();
                }, 2000);
            });
        });
    </script>
</body>
</html>