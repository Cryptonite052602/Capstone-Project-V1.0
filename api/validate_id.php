<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only allow staff members to validate IDs
if (!isStaff() && !isAdmin()) {
    jsonResponse(['error' => 'Unauthorized access'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get user ID from request
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

if (!$userId) {
    jsonResponse(['error' => 'User ID is required'], 400);
}

global $pdo;

try {
    // Get user details including ID image path
    $stmt = $pdo->prepare("SELECT id, id_image_path, id_verified, verification_method FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(['error' => 'User not found'], 404);
    }

    // If no ID image uploaded
    if (empty($user['id_image_path'])) {
        jsonResponse([
            'success' => true,
            'is_valid' => false,
            'file_exists' => false,
            'validation_issues' => ['No ID document uploaded'],
            'validation_passed' => false,
            'message' => 'No ID document has been uploaded by this user'
        ]);
    }

    // Validate the uploaded ID
    $validation = validateUploadedId($user['id_image_path']);

    // Update the validation status in database
    if ($validation['validation_passed']) {
        $stmt = $pdo->prepare("UPDATE sitio1_users SET id_verified = 1, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }

    // Return validation result
    jsonResponse([
        'success' => true,
        'user_id' => $userId,
        'id_image_path' => $user['id_image_path'],
        'is_valid' => $validation['is_valid'],
        'file_exists' => $validation['file_exists'],
        'file_name' => $validation['file_name'],
        'file_type' => $validation['file_type'],
        'file_size' => $validation['file_size'],
        'file_size_formatted' => $validation['file_size_formatted'],
        'image_width' => $validation['image_width'] ?? null,
        'image_height' => $validation['image_height'] ?? null,
        'validation_issues' => $validation['validation_issues'],
        'validation_passed' => $validation['validation_passed'],
        'message' => $validation['validation_passed'] 
            ? 'ID document is valid and meets all requirements' 
            : 'ID document failed validation. See validation_issues for details'
    ]);

} catch (PDOException $e) {
    error_log("Database error in validate_id.php: " . $e->getMessage());
    jsonResponse(['error' => 'Database error occurred'], 500);
}
?>
