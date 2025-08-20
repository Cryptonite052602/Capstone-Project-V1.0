<?php
require_once __DIR__ . '/../includes/auth.php';

if (!isAdmin() && !isStaff()) {
    header('HTTP/1.0 403 Forbidden');
    exit(); 
}

if (!isset($_GET['id'])) {
    header('HTTP/1.0 400 Bad Request');
    exit();
}

$userId = intval($_GET['id']);

global $pdo;

try {
    $stmt = $pdo->prepare("UPDATE sitio1_users SET approved = TRUE, approved_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id'], $userId]);
    
    $_SESSION['success'] = 'User account approved successfully!';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error approving user account: ' . $e->getMessage();
}

$redirect = isAdmin() ? '/community-health-tracker/admin/manage_accounts.php' : '/community-health-tracker/staff/dashboard.php';
header("Location: $redirect");
exit();
?>