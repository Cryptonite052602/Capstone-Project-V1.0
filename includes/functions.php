<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Get the current user's ID
 */
function getUserId() {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Check if the current user has a specific role
 */
function hasRole($role) {
    return ($_SESSION['user']['role'] ?? null) === $role;
}

/**
 * Redirect with a flash message
 */
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash'][$type] = $message;
    header("Location: $url");
    exit();
}

/**
 * Get flash message and clear it
 */
function getFlashMessage($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * Generate a CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function formatDate($dateString, $format = 'M d, Y') {
    if (empty($dateString)) return '';
    $date = new DateTime($dateString);
    return $date->format($format);
}

/**
 * Get the base URL
 */
function baseUrl($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return "$protocol://$host$basePath/$path";
}

/**
 * Check if the request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Get pagination parameters
 */
function getPaginationParams($defaultPerPage = 10) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : $defaultPerPage;
    return [$page, $perPage];
}

/**
 * Generate pagination links
 */
function paginate($totalItems, $currentPage, $perPage, $url) {
    $totalPages = ceil($totalItems / $perPage);
    $links = [];
    
    // Previous link
    if ($currentPage > 1) {
        $links[] = [
            'url' => "$url?page=" . ($currentPage - 1) . "&per_page=$perPage",
            'label' => '&laquo; Previous',
            'active' => false
        ];
    }
    
    // Page links
    for ($i = 1; $i <= $totalPages; $i++) {
        $links[] = [
            'url' => "$url?page=$i&per_page=$perPage",
            'label' => $i,
            'active' => $i === $currentPage
        ];
    }
    
    // Next link
    if ($currentPage < $totalPages) {
        $links[] = [
            'url' => "$url?page=" . ($currentPage + 1) . "&per_page=$perPage",
            'label' => 'Next &raquo;',
            'active' => false
        ];
    }
    
    return $links;
}

/**
 * Generate a random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Get current URL
 */
function currentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Get user IP address
 */
function getUserIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>