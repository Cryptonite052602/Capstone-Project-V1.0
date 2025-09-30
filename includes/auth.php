<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function isAdmin() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'; 
}

function isStaff() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'staff';
}

function isUser() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'user';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: /community-health-tracker/auth/login.php');
        exit();
    }
}

function redirectBasedOnRole() {
    if (isLoggedIn()) {
        if (isAdmin()) {
            header('Location: /community-health-tracker/admin/dashboard.php');
        } elseif (isStaff()) {
            header('Location: /community-health-tracker/staff/dashboard.php');
        } elseif (isUser()) {
            header('Location: /community-health-tracker/user/dashboard.php');
        }
        exit();
    }
}

function loginUser($username, $password, $role) {
    global $pdo;
    
    $table = '';
    switch ($role) {
        case 'admin':
            $table = 'admin';
            break;
        case 'staff':
            $table = 'sitio1_staff';
            break;
        case 'user':
            $table = 'sitio1_users';
            break;
        default:
            return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // For staff accounts, check if they're active
        if ($role === 'staff' && isset($user['status']) && $user['status'] !== 'active') {
            return 'Your staff account is deactivated. Please contact an administrator.';
        }
        
        // For regular users, check if they're approved
        if ($role === 'user' && !$user['approved']) {
            return 'Your account is pending approval by the Admin!';
        }
        
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $role
        ];
        return true;
    }
    
    return false;
}
?>