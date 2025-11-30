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
    <title>Login | Barangay Pahina San Nicolas Healthcare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {  
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f0f4f8;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .left-section {
            flex: 1;
            background: #3C96E1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .left-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #328fdbff;
            z-index: 1;
        }

        .left-content {
            z-index: 2;
            width: 100%;
        }

        .logo-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 5px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .left-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 1.5px;
        }

        .left-subtitle {
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 40px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
            width: 100%;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature {
            display: flex;
            align-items: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(5px);
        }

        .feature i {
            margin-right: 12px;
            color: #a3d9ff;
            font-size: 18px;
            width: 20px;
        }

        .right-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 5px;
            text-align: center;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 12px;
            margin-left: 5px;
        }

        .input-container {
            position: relative;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 0 20px;
            border: 1px solid #d1d5db;
            border-radius: 1000px; /* Full round radius */
            font-size: 14px;
            transition: all 0.3s;
            background: white;
            height: 55px; /* Changed to 55px */
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: #0077AA;
            box-shadow: 0 0 0 3px rgba(0, 119, 170, 0.1);
        }

        .form-select {
            appearance: none;
            padding-right: 40px;
        }

        .select-arrow {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .remember-container {
            display: flex;
            align-items: center;
        }

        .remember-checkbox {
            margin-right: 8px;
        }

        .forgot-link {
            color: #0077AA;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: #0077AA;
            color: white;
            border: none;
            border-radius: 1000px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 55px; /* Changed to 55px */
        }

        .btn-login:hover {
            background: #006699;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 119, 170, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login i {
            margin-left: 8px;
        }

        .btn-login:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.6;
        }

        .support-text {
            text-align: center;
            margin-top: 30px;
            color: #64748b;
            font-size: 14px;
        }

        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }

        .error-icon {
            margin-right: 10px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            padding: 20px;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(20px);
            transition: transform 0.3s ease;
            margin: 0 auto;
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(0, 119, 170, 0.2);
            border-top: 4px solid #0077AA;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .success-check {
            color: #10b981;
            font-size: 50px;
            margin-bottom: 20px;
        }

        .error-x {
            color: #ef4444;
            font-size: 50px;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1e293b;
        }

        .modal-message {
            color: #64748b;
            margin-bottom: 30px;
            line-height: 1.5;
            font-size: 16px;
        }

        .modal-btn {
            padding: 12px 30px;
            background: #0077AA;
            color: white;
            border: none;
            border-radius: 1000px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 auto;
            display: block;
        }

        .modal-btn:hover {
            background: #006699;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .left-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Section - Information -->
        <div class="left-section">
            <div class="left-content">
                <!-- Circular Logo -->
                <div class="logo-container">
                    <img src="asssets/images/toong-logo.png" alt="Barangay Toong Logo">
                </div>
                
                <h1 class="left-title">Barangay Toong</h1>
                <p class="left-subtitle">Healthcare Management System</p>
                
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure & HIPAA Compliant</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-chart-line"></i>
                        <span>Real-time Health Analytics</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-users"></i>
                        <span>Community Health Tracking</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-file-medical"></i>
                        <span>Digital Health Records</span>
                    </div>
                </div>
                <div class="logo-text">Barangay Pahina San Nicolas</div>
            </div>
        </div>

        <!-- Right Section - Login Form -->
        <div class="right-section">
            <div class="login-box">
                <h2 class="login-title">System Administrator</h2>
                <p class="login-subtitle">Sign in to your Admin or Staff account</p>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle error-icon"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="">
                    <div class="form-group">
                        <label for="role" class="form-label">Role *</label>
                        <div class="input-container">
                            <select id="role" name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="admin">Super Admin</option>
                                <option value="staff">Admin</option>
                            </select>
                            <div class="select-arrow">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username" class="form-label">User Name *</label>
                        <div class="input-container">
                            <input type="text" id="username" name="username" class="form-input" placeholder="Enter Your User Name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password *</label>
                        <div class="input-container">
                            <input type="password" id="password" name="password" class="form-input" placeholder="Enter Your Password" required>
                        </div>
                    </div>

                    <div class="remember-forgot">
                        <div class="remember-container">
                            <input type="checkbox" id="remember" class="remember-checkbox">
                            <label for="remember">Remember your Password</label>
                        </div>
                        <a href="#" class="forgot-link">Forgot your Password?</a>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton">
                        <span id="roleText">Select Role Type</span> <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>

            <div class="support-text">
                <p>On Going System Production - CHMTS : V1.0</p>
            </div>
        </div>
    </div>

    <!-- Validation Modal -->
    <div id="validationModal" class="modal">
        <div class="modal-content">
            <div id="loadingSpinner" class="spinner"></div>
            <div id="successIcon" class="success-check" style="display: none;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div id="errorIcon" class="error-x" style="display: none;">
                <i class="fas fa-times-circle"></i>
            </div>
            
            <h3 id="modalTitle" class="modal-title">Authenticating...</h3>
            <p id="modalMessage" class="modal-message">Please wait while we verify your credentials</p>
            
            <button id="successButton" class="modal-btn" style="display: none;">Continue</button>
            <button id="errorButton" class="modal-btn" style="display: none;">Okay</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const roleSelect = document.getElementById('role');
            const roleText = document.getElementById('roleText');
            const loginButton = document.getElementById('loginButton');
            const modal = document.getElementById('validationModal');
            const successButton = document.getElementById('successButton');
            const errorButton = document.getElementById('errorButton');
            
            // Function to update login button text and state based on selected role
            function updateLoginButtonState() {
                if (!roleSelect.value) {
                    roleText.textContent = 'Select Role Type';
                    loginButton.disabled = true;
                } else {
                    if (roleSelect.value === 'admin') {
                        roleText.textContent = 'Login as Admin';
                    } else {
                        roleText.textContent = 'Login as Staff';
                    }
                    loginButton.disabled = false;
                }
            }
            
            // Initialize button state
            updateLoginButtonState();
            
            // Update login button text and state when role selection changes
            roleSelect.addEventListener('change', updateLoginButtonState);
            
            // Function to show modal with smooth animation
            function showModal() {
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            }
            
            // Function to hide modal with smooth animation
            function hideModal() {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                    // Reset modal state
                    document.getElementById('loadingSpinner').style.display = 'block';
                    document.getElementById('successIcon').style.display = 'none';
                    document.getElementById('errorIcon').style.display = 'none';
                    successButton.style.display = 'none';
                    errorButton.style.display = 'none';
                    document.getElementById('modalTitle').textContent = 'Authenticating...';
                    document.getElementById('modalMessage').textContent = 'Please wait while we verify your credentials';
                }, 300);
            }
            
            // Handle login form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Check if role is selected
                if (!roleSelect.value) {
                    // Show error state immediately since no role is selected
                    showModal();
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('errorIcon').style.display = 'block';
                    errorButton.style.display = 'block';
                    
                    document.getElementById('modalTitle').textContent = 'Incorrect Role Selected';
                    document.getElementById('modalMessage').textContent = 'Please choose the correct role based on your username and password.';
                    return;
                }
                
                showModal();
                
                const formData = new FormData(this);
                
                // Simulate API call with timeout
                setTimeout(() => {
                    // For demonstration, we'll simulate a successful login
                    // In a real application, this would be replaced with actual API call
                    const success = true; // Change to false to simulate failed login
                    
                    if (success) {
                        // Show success state
                        document.getElementById('loadingSpinner').style.display = 'none';
                        document.getElementById('successIcon').style.display = 'block';
                        successButton.style.display = 'block';
                        
                        document.getElementById('modalTitle').textContent = 'Login Successful';
                        document.getElementById('modalMessage').textContent = 'You are redirecting to admin dashboard';
                        
                        // Handle success button click
                        successButton.onclick = function() {
                            if (roleSelect.value === 'admin') {
                                window.location.href = 'admin/dashboard.php';
                            } else if (roleSelect.value === 'staff') {
                                window.location.href = 'staff/dashboard.php';
                            }
                        };
                    } else {
                        // Show error state
                        document.getElementById('loadingSpinner').style.display = 'none';
                        document.getElementById('errorIcon').style.display = 'block';
                        errorButton.style.display = 'block';
                        
                        document.getElementById('modalTitle').textContent = 'Incorrect Role Selected';
                        document.getElementById('modalMessage').textContent = 'Please choose the correct role based on your username and password.';
                    }
                }, 2000);
                
                // Actual fetch code (commented out for demo)
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success state
                        document.getElementById('loadingSpinner').style.display = 'none';
                        document.getElementById('successIcon').style.display = 'block';
                        successButton.style.display = 'block';
                        
                        document.getElementById('modalTitle').textContent = 'Login Successful';
                        document.getElementById('modalMessage').textContent = 'You are redirecting to admin dashboard';
                        
                        // Handle success button click
                        successButton.onclick = function() {
                            if (data.role === 'admin') {
                                window.location.href = 'admin/dashboard.php';
                            } else if (data.role === 'staff') {
                                window.location.href = 'staff/dashboard.php';
                            }
                        };
                    } else {
                        // Show error state
                        document.getElementById('loadingSpinner').style.display = 'none';
                        document.getElementById('errorIcon').style.display = 'block';
                        errorButton.style.display = 'block';
                        
                        document.getElementById('modalTitle').textContent = 'Login Failed';
                        document.getElementById('modalMessage').textContent = 'Incorrect username and password';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error state
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('errorIcon').style.display = 'block';
                    errorButton.style.display = 'block';
                    
                    document.getElementById('modalTitle').textContent = 'Error';
                    document.getElementById('modalMessage').textContent = 'An error occurred. Please try again.';
                });
                
            });
            
            // Handle error button click
            errorButton.addEventListener('click', function() {
                hideModal();
            });
        });
    </script>
</body>
</html>