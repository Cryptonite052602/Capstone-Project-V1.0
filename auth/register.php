<?php
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $sitio = trim($_POST['sitio'] ?? '');
    $civilStatus = trim($_POST['civil_status'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    
    // Identity verification fields
    $verificationMethod = trim($_POST['verification_method'] ?? 'manual_verification');
    $verificationNotes = trim($_POST['verification_notes'] ?? '');
    $verificationConsent = isset($_POST['verification_consent']) ? 1 : 0;
    
    // Validate required fields
    if (empty($username)) {
        showGlassModal('error', 'Username is required.');
        exit();
    }
    
    if (empty($email)) {
        showGlassModal('error', 'Email is required.');
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        showGlassModal('error', 'Please enter a valid email address.');
        exit();
    }
    
    if (empty($password)) {
        showGlassModal('error', 'Password is required.');
        exit();
    }
    
    if (empty($fullName)) {
        showGlassModal('error', 'Full name is required.');
        exit();
    }
    
    // DATE OF BIRTH VALIDATION - ADDED FIELD
    if (empty($dateOfBirth)) {
        showGlassModal('error', 'Date of birth is required.');
        exit();
    }
    
    // Validate date format and ensure it's not in the future
    $dobTimestamp = strtotime($dateOfBirth);
    if (!$dobTimestamp || $dobTimestamp > time()) {
        showGlassModal('error', 'Please enter a valid date of birth.');
        exit();
    }
    
    if (empty($gender)) {
        showGlassModal('error', 'Gender is required.');
        exit();
    }
    
    if (empty($sitio)) {
        showGlassModal('error', 'Sitio is required.');
        exit();
    }

    // Civil status may be required depending on local policy; accept empty but trim
    if (empty($civilStatus)) {
        // Not strictly required; set to NULL if empty
        $civilStatus = null;
    }
    
    if ($password !== $confirmPassword) {
        showGlassModal('error', 'Passwords do not match.');
        exit();
    }
    
    // Validate identity verification based on method
    if ($verificationMethod === 'id_upload') {
        if (empty($_FILES['id_image']['name'])) {
            showGlassModal('error', 'ID document is required for ID upload verification.');
            exit();
        }
        
        if (!$verificationConsent) {
            showGlassModal('error', 'You must consent to ID verification to proceed.');
            exit();
        }
        
        // Validate file upload
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if ($_FILES['id_image']['size'] > $maxFileSize) {
            showGlassModal('error', 'File size exceeds 5MB limit.');
            exit();
        }
        
        $fileType = mime_content_type($_FILES['id_image']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            showGlassModal('error', 'Invalid file type. Only JPEG, PNG, GIF, and PDF files are allowed.');
            exit();
        }
    }
    
    // Check if username or email exists
    try {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            showGlassModal('error', 'Username already exists. Please choose a different one.');
            exit();
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            showGlassModal('error', 'Email already exists. Please use a different email address.');
            exit();
        }
    } catch (PDOException $e) {
        showGlassModal('error', 'Registration check failed: ' . $e->getMessage());
        exit();
    }
    
    // Handle file upload if ID verification method is selected
    $idImagePath = null;
    if ($verificationMethod === 'id_upload' && isset($_FILES['id_image']) && $_FILES['id_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/id_documents/';
        
        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION);
        $filename = 'id_' . $username . '_' . time() . '.' . $fileExtension;
        $idImagePath = 'uploads/id_documents/' . $filename;
        $fullPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($_FILES['id_image']['tmp_name'], $fullPath)) {
            showGlassModal('error', 'Failed to upload ID document. Please try again.');
            exit();
        }
    }
    
    // Proceed with registration
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user with verification data INCLUDING DATE OF BIRTH
        $stmt = $pdo->prepare("INSERT INTO sitio1_users 
            (username, email, password, full_name, date_of_birth, age, gender, address, sitio, contact, civil_status, occupation,
             verification_method, id_image_path, verification_notes, verification_consent, approved) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $fullName,
            $dateOfBirth, // ADDED DATE OF BIRTH
            $age,
            $gender,
            $address,
            $sitio,
            $contact,
            $civilStatus,
            $occupation,
            $verificationMethod,
            $idImagePath,
            $verificationNotes,
            $verificationConsent
        ]);
        
        // Generate success message based on verification method
        $successMessage = 'Registration Successful';
        $description = 'Your Account is Pending Approval by Staff. ';
        
        if ($verificationMethod === 'id_upload') {
            $description .= 'Your ID document has been uploaded and will be reviewed.';
        } else {
            $description .= 'Staff will contact you using the provided contact information to complete verification.';
        }
        
        $description .= ' You will receive an email notification once your registration is approved or declined by the staff.';
        
        showGlassModal('success', $successMessage, $description);
        exit();
        
    } catch (PDOException $e) {
        // Clean up uploaded file if registration fails
        if ($idImagePath && file_exists(__DIR__ . '/../' . $idImagePath)) {
            unlink(__DIR__ . '/../' . $idImagePath);
        }
        
        showGlassModal('error', 'Registration failed: ' . $e->getMessage());
        exit();
    }
}

function showGlassModal($type, $title, $description = '') {
    $icon = '';
    $color = '';
    $redirectUrl = $type === 'success' ? '../index.php' : 'javascript:history.back()';
    
    if ($type === 'success') {
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>';
        $color = 'text-green-400';
    } else {
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>';
        $color = 'text-red-400';
    }
    
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Status</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .animate-fade-in {
                animation: fadeIn 0.4s ease-out forwards;
            }
            .glass-panel {
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 255, 255, 0.18);
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
                border-radius: 1rem;
            }
        </style>
    </head>
    <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
        <div class="fixed inset-0 flex items-center justify-center">
            <div class="glass-panel p-8 max-w-md w-full animate-fade-in">
                <div class="flex flex-col items-center text-center space-y-4">
                    <div class="p-4 rounded-full bg-white/20">
                        $icon
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">$title</h2>
                    <p class="text-gray-600">$description</p>
                    <button onclick="window.location.href='$redirectUrl'" class="mt-4 px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        Continue
                    </button>
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
    exit();
}
?>