<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user']['id'];
$user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 'user';

// Directory for profile pictures
$upload_dir = __DIR__ . '/../uploads/profiles/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle profile picture removal
if (isset($_POST['remove']) && $_POST['remove'] == '1') {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $removed = false;
    
    foreach ($allowed_extensions as $ext) {
        $file_path = $upload_dir . 'profile_' . $user_id . '.' . $ext;
        if (file_exists($file_path)) {
            unlink($file_path);
            $removed = true;
            break;
        }
    }
    
    if ($removed) {
        echo json_encode(['success' => true, 'message' => 'Profile picture removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No profile picture found']);
    }
    exit;
}

// Handle file upload
if (!isset($_FILES['profile_image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['profile_image'];
$max_file_size = 2 * 1024 * 1024; // 2MB
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

if ($file['size'] > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 2MB limit']);
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF allowed']);
    exit;
}

// Remove old profile picture if exists
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
foreach ($allowed_extensions as $ext) {
    $old_file = $upload_dir . 'profile_' . $user_id . '.' . $ext;
    if (file_exists($old_file)) {
        unlink($old_file);
    }
}

// Determine file extension
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$extension = strtolower($extension);

// Validate extension
if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
    exit;
}

// Generate unique filename
$filename = 'profile_' . $user_id . '.' . $extension;
$destination = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Update database if needed (optional)
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->execute([$filename, $user_id]);
    } catch (PDOException $e) {
        // Log error but don't fail the upload
        error_log("Profile picture database update failed: " . $e->getMessage());
    }
    
    // Generate URL for the uploaded file
    $profile_url = '/community-health-tracker/uploads/profiles/' . $filename;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile picture uploaded successfully',
        'profile_url' => $profile_url
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
}