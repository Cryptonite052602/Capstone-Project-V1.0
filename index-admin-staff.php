<?php
require_once __DIR__ . '/includes/auth.php';

// Check if user is already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

// Logic to handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    if (!empty($username) && !empty($password) && !empty($role)) {
        // This function should be defined in your includes/auth.php
        $result = loginUser($username, $password, $role);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            if ($result === true) {
                echo json_encode(['success' => true, 'role' => $role]);
            } else {
                echo json_encode(['success' => false, 'message' => $result ?: 'Invalid username or password.']);
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Barangay Luz, Cebu City</title>
    <link rel="icon" type="image/png" href="./asssets/images/Luz.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {  
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            background: #f0f4f8;
        }

        .container {
            display: flex;
            width: 100%;
            max-width: 1100px; /* Slightly wider for better proportions */
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        /* --- Left Branding --- */
        .left-section {
            flex: 1.2;
            background: #3C96E1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 80px 40px; 
            color: white;
            text-align: center;
        }

        .logo-container {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 30px;
            border: 6px solid rgba(255, 255, 255, 0.3);
            background: white;
        }

        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .left-title { font-size: 34px; font-weight: 700; margin-bottom: 12px; }
        .left-subtitle { font-size: 20px; opacity: 0.9; margin-bottom: 45px; }

        .feature {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            font-size: 15px;
            margin-bottom: 12px;
            width: 100%;
            max-width: 350px;
            text-align: left;
        }
        .feature i { margin-right: 15px; font-size: 1.2rem; color: #a3d9ff; }

        /* --- Right Login Section --- */
        .right-section { 
            flex: 1; 
            padding: 80px 60px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
        }

        .login-title { font-size: 28px; font-weight: 700; color: #1e293b; text-align: center; margin-bottom: 8px; }
        .login-subtitle { color: #64748b; font-size: 16px; text-align: center; margin-bottom: 40px; }

        .form-group { margin-bottom: 24px; position: relative; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 10px; margin-left: 5px; }

        .form-select, .form-input {
            width: 100%;
            padding: 0 25px;
            border: 2px solid #e2e8f0;
            border-radius: 50px;
            font-size: 15px;
            height: 60px;
            outline: none;
            transition: all 0.3s ease;
            background-color: #f8fafc;
            appearance: none;
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 18px;
            cursor: pointer;
        }

        .form-select:focus, .form-input:focus {
            border-color: #3C96E1;
            background-color: #fff;
            box-shadow: 0 0 0 5px rgba(60, 150, 225, 0.15);
        }

        .btn-login {
            width: 100%;
            height: 60px;
            background: #3C96E1;
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .btn-login:disabled { background: #cbd5e1; cursor: not-allowed; }
        .btn-login:hover:not(:disabled) { background: #2d81c7; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(60, 150, 225, 0.2); }

        .support-text { text-align: center; margin-top: 45px; color: #94a3b8; font-size: 13px; }

        /* --- BIGGER MODAL DESIGN --- */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .modal.show { display: flex; opacity: 1; }

        .modal-content {
            background: white;
            padding: 60px 40px; /* Much bigger padding */
            border-radius: 30px;
            text-align: center;
            max-width: 500px; /* Wider modal */
            width: 90%;
            transform: scale(0.8);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }

        .modal.show .modal-content { transform: scale(1); }

        .modal-icon {
            font-size: 80px;
            margin-bottom: 25px;
        }

        .modal-title { font-size: 28px; font-weight: 700; margin-bottom: 15px; color: #1e293b; }
        .modal-message { font-size: 18px; color: #64748b; line-height: 1.6; margin-bottom: 35px; }

        .spinner {
            width: 80px; height: 80px;
            border: 6px solid #f3f3f3;
            border-top: 6px solid #3C96E1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 25px;
        }

        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .modal-btn {
            padding: 15px 45px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-close { background: #f1f5f9; color: #475569; }
        .btn-close:hover { background: #e2e8f0; }

        @media (max-width: 768px) { 
            .left-section { display: none; }
            .right-section { padding: 60px 30px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="left-section">
            <div class="logo-container">
                <img src="asssets/images/Luz.jpg" alt="Logo">
            </div>
            <h1 class="left-title">Barangay Luz, Cebu City</h1>
            <p class="left-subtitle">Healthcare Management System</p>
            <div class="feature"><i class="fas fa-shield-virus"></i> Secure HIPAA Compliance</div>
            <div class="feature"><i class="fas fa-chart-line"></i> Real-time Health Data</div>
            <div class="feature"><i class="fas fa-clock"></i> 24/7 Technical Reliability</div>
        </div>

        <div class="right-section">
            <div class="login-box">
                <h2 class="login-title">Administrator Access</h2>
                <p class="login-subtitle">Please sign in to your workstation</p>

                <form id="loginForm">
                    <div class="form-group">
                        <label class="form-label">System Role *</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="">Select Role Type</option>
                            <option value="admin">Super Admin</option>
                            <option value="staff">Admin / Staff</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-input" placeholder="Workstation username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" placeholder="Workstation password" required>
                    </div>

                    <button type="submit" class="btn-login" id="loginButton" disabled>
                        <span id="roleText">Select Role Type</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="support-text">Production Version • BLMTS : V1.0</div>
            </div>
        </div>
    </div>

    <div id="validationModal" class="modal">
        <div class="modal-content">
            <div id="modalLoading">
                <div class="spinner"></div>
                <h3 class="modal-title">Verifying Identity</h3>
                <p class="modal-message">Connecting to the secure health server...</p>
            </div>

            <div id="modalResponse" style="display:none;">
                <div id="iconContainer" class="modal-icon"></div>
                <h3 id="resTitle" class="modal-title"></h3>
                <p id="resMsg" class="modal-message"></p>
                <button id="modalActionBtn" class="modal-btn" onclick="closeModal()"></button>
            </div>
        </div>
    </div>

    <script>
        const roleSelect = document.getElementById('role');
        const loginButton = document.getElementById('loginButton');
        const roleText = document.getElementById('roleText');
        const modal = document.getElementById('validationModal');

        // Toggle button state and text
        roleSelect.addEventListener('change', function() {
            if (this.value) {
                roleText.textContent = 'Login as ' + (this.value === 'admin' ? 'Super Admin' : 'Admin/Staff');
                loginButton.disabled = false;
            } else {
                roleText.textContent = 'Select Role Type';
                loginButton.disabled = true;
            }
        });

        // AJAX Handle
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show Modal and animate in
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            
            // Reset to loading state
            document.getElementById('modalLoading').style.display = 'block';
            document.getElementById('modalResponse').style.display = 'none';

            fetch('', {
                method: 'POST',
                body: new FormData(this),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(data => {
                setTimeout(() => { // Small delay to feel realistic
                    document.getElementById('modalLoading').style.display = 'none';
                    document.getElementById('modalResponse').style.display = 'block';
                    
                    const iconBox = document.getElementById('iconContainer');
                    const actionBtn = document.getElementById('modalActionBtn');

                    if(data.success) {
                        iconBox.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i>';
                        document.getElementById('resTitle').textContent = 'Access Granted';
                        document.getElementById('resMsg').textContent = 'Verification successful. Welcome to the Barangay Luz Portal.';
                        actionBtn.textContent = 'Redirecting...';
                        actionBtn.className = 'modal-btn';
                        actionBtn.style.background = '#10b981';
                        actionBtn.style.color = 'white';
                        
                        setTimeout(() => {
                            window.location.href = data.role + '/dashboard.php';
                        }, 2000);
                    } else {
                        iconBox.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i>';
                        document.getElementById('resTitle').textContent = 'Login Failed';
                        document.getElementById('resMsg').textContent = data.message;
                        actionBtn.textContent = 'Try Again';
                        actionBtn.className = 'modal-btn btn-close';
                    }
                }, 800);
            })
            .catch(err => {
                document.getElementById('modalLoading').style.display = 'none';
                document.getElementById('modalResponse').style.display = 'block';
                document.getElementById('iconContainer').innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>';
                document.getElementById('resTitle').textContent = 'Connection Error';
                document.getElementById('resMsg').textContent = 'Unable to connect to the server. Please check your internet.';
                document.getElementById('modalActionBtn').textContent = 'Close';
                document.getElementById('modalActionBtn').className = 'modal-btn btn-close';
            });
        });

        function closeModal() {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 400);
        }
    </script>
</body>
</html>