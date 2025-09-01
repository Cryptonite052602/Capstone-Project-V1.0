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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --secondary: #FC566C;
            --secondary-dark: #e04a5f;
            --light-bg: #F8FAFC;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 50%, #bae6fd 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 550px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .form-section {
            flex: 1;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .image-section {
            flex: 1;
            position: relative;
            display: none;
        }
        
        @media (min-width: 768px) {
            .image-section {
                display: block;
            }
        }
        
        .login-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(59, 130, 246, 0.3), rgba(59, 130, 246, 0.7));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 2rem;
            color: white;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            background: var(--secondary);
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            box-shadow: 0 4px 6px rgba(252, 86, 108, 0.2);
        }
        
        .logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--secondary);
            margin-left: 0.75rem;
        }
        
        .welcome-text {
            margin-bottom: 2rem;
        }
        
        .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .welcome-text p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .input-container {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 10;
        }
        
        .form-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-select {
            width: 100%;
            padding: 0.85rem 2.8rem;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            font-size: 0.9rem;
            appearance: none;
            background: white;
            transition: all 0.2s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .select-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            pointer-events: none;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.9rem 1rem;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.5rem;
        }
        
        .btn-login:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(252, 86, 108, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .support-text {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
        
        .error-message {
            background: #FEF2F2;
            color: #DC2626;
            padding: 0.85rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            border-left: 4px solid #DC2626;
        }
        
        .error-icon {
            margin-right: 0.6rem;
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
        }
        
        .modal.show {
            opacity: 1;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 90%;
            max-width: 380px;
            text-align: center;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(59, 130, 246, 0.2);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.2rem;
        }
        
        .success-check {
            color: #10B981;
            font-size: 3.5rem;
            margin-bottom: 1.2rem;
        }
        
        .error-x {
            color: #EF4444;
            font-size: 3.5rem;
            margin-bottom: 1.2rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--text-primary);
        }
        
        .modal-message {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .modal-btn {
            padding: 0.7rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .modal-btn:hover {
            background: var(--primary-dark);
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Form Section -->
        <div class="form-section">
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-hospital"></i>
                </div>
                <div class="logo-text">Barangay Pahina San Nicolas</div>
            </div>

            <div class="welcome-text">
                <h2>Welcome Back</h2>
                <p>Sign in to your Admin/Staff account</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle error-icon"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="">
                <div class="form-group">
                    <label for="role" class="form-label">Role</label>
                    <div class="input-container">
                        <div class="input-icon">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <select id="role" name="role" class="form-select" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                        <div class="select-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-container">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-container">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>

            <div class="support-text">
                <p>Need help? Contact support@chtms.org</p>
            </div>
        </div>

        <!-- Image Section -->
        <div class="image-section">
            <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" 
                 alt="Healthcare professionals" class="login-image">
            <div class="image-overlay">
                <h2 class="text-2xl font-bold mb-2">Comprehensive Healthcare Management</h2>
                <p class="text-lg">Streamlined system for medical professionals to provide exceptional patient care</p>
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
            
            <button id="modalButton" class="modal-btn" style="display: none;">Try Again</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const modal = document.getElementById('validationModal');
            const modalButton = document.getElementById('modalButton');
            
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
                }, 300);
            }
            
            // Handle login form submission
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                showModal();
                
                const formData = new FormData(this);
                
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
                        
                        document.getElementById('modalTitle').textContent = 'Login Successful!';
                        document.getElementById('modalMessage').textContent = `Redirecting to ${data.role} dashboard...`;
                        
                        setTimeout(() => {
                            if (data.role === 'admin') {
                                window.location.href = 'admin/dashboard.php';
                            } else if (data.role === 'staff') {
                                window.location.href = 'staff/dashboard.php';
                            }
                        }, 1500);
                    } else {
                        // Show error state
                        document.getElementById('loadingSpinner').style.display = 'none';
                        document.getElementById('errorIcon').style.display = 'block';
                        modalButton.style.display = 'block';
                        
                        document.getElementById('modalTitle').textContent = 'Login Failed';
                        document.getElementById('modalMessage').textContent = data.message || 'Invalid credentials. Please try again.';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show error state
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('errorIcon').style.display = 'block';
                    modalButton.style.display = 'block';
                    
                    document.getElementById('modalTitle').textContent = 'Error';
                    document.getElementById('modalMessage').textContent = 'An error occurred. Please try again.';
                });
            });
            
            // Handle modal button click
            modalButton.addEventListener('click', function() {
                hideModal();
            });
        });
    </script>
</body>
</html>