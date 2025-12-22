<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get stats for dashboard
$stats = [
    'total_staff' => 0,
    'total_users' => 0,
    'total_patients' => 0,
    'pending_approvals' => 0
];

// Get data for charts (last 7 days)
$chart_data = [
    'labels' => [],
    'staff_data' => [],
    'users_data' => [],
    'patients_data' => []
];

try {
    // Get total counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_staff");
    $stats['total_staff'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_patients");
    $stats['total_patients'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sitio1_users WHERE approved = FALSE");
    $stats['pending_approvals'] = $stmt->fetchColumn();
    
    // Get data for line chart (last 7 days)
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data['labels'][] = date('M d', strtotime($date));
        
        // Get staff count up to this date
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_staff WHERE DATE(created_at) <= ?");
        $stmt->execute([$date]);
        $chart_data['staff_data'][] = $stmt->fetchColumn();
        
        // Get users count up to this date
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_users WHERE DATE(created_at) <= ?");
        $stmt->execute([$date]);
        $chart_data['users_data'][] = $stmt->fetchColumn();
        
        // Get patients count up to this date
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sitio1_patients WHERE DATE(created_at) <= ?");
        $stmt->execute([$date]);
        $chart_data['patients_data'][] = $stmt->fetchColumn();
    }
    
    // Calculate percentages for pie chart
    $total_records = $stats['total_staff'] + $stats['total_users'] + $stats['total_patients'];
    if ($total_records > 0) {
        $staff_percentage = round(($stats['total_staff'] / $total_records) * 100, 1);
        $users_percentage = round(($stats['total_users'] / $total_records) * 100, 1);
        $patients_percentage = round(($stats['total_patients'] / $total_records) * 100, 1);
    } else {
        $staff_percentage = $users_percentage = $patients_percentage = 0;
    }
    
} catch (PDOException $e) {
    // Log error and show user-friendly message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Unable to fetch dashboard statistics. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Community Health Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

/* Apply Poppins to all elements and new specific classes */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif !important; /* Overriding default font for everything */
}

/* Base Styles (from your previous request, included for completeness) */

/* Smooth transition for sidebar */
.sidebar {
    transition: transform 0.3s ease-in-out;
}

.sidebar-hidden {
    transform: translateX(-100%);
}

/* Optional: Add overlay for mobile */
.overlay {
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;
}

.overlay-hidden {
    opacity: 0;
    pointer-events: none;
}

/* Blinking colon animation */
@keyframes blink {
    0% {
        opacity: 1;
    }

    50% {
        opacity: 0;
    }

    100% {
        opacity: 1;
    }
}

.blinking-colon {
    animation: blink 1s infinite;
}

/* CLEAN: Simple Navigation Tab Styles */
.nav-tab-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.nav-tab {
    position: relative;
    transition: all 0.3s ease;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    z-index: 1;
}

.nav-tab.active {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
}

.nav-tab.active::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    width: 60%;
    height: 2px;
    background: white;
    border-radius: 2px;
}

.nav-tab:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
}

/* CLEAN: Simple Logout Button - UPDATED FOR FULL ROUND */
.logout-btn {
    background: #ef4444;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px !important; /* Full round radius */
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    outline: none;
}

.logout-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* NEW: Improved time display containers - Horizontal layout */
.time-display-container {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.6rem 1.2rem;
    border-radius: 0.75rem;
    margin-left: auto;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.staff-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

.user-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

.admin-time-container {
    background-color: rgba(255, 255, 255, 0.15);
}

/* NEW: Horizontal time display styles */
.time-display-horizontal {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.date-display-horizontal {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.time-display-main-horizontal {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 1px;
    white-space: nowrap;
}

.time-separator {
    height: 24px;
    width: 1px;
    background: rgba(255, 255, 255, 0.4);
    margin: 0 0.5rem;
}

.time-zone {
    font-size: 0.75rem;
    margin-left: 0.25rem;
    opacity: 0.9;
    font-style: italic;
    font-weight: 500;
}

/* Hidden refresh indicator */
.refresh-indicator {
    position: absolute;
    width: 0;
    height: 0;
    overflow: hidden;
    opacity: 0;
}

/* Form validation styles */
.form-input:invalid {
    border-color: #fca5a5;
}

.form-input:valid {
    border-color: #74b4fdff;
}

/* Updated Registration Button Styles with Rounded XL Sides */
.continue-btn, .complete-btn {
    width: 100%;
    border-radius: 9999px !important; /* rounded-full equivalent */
    padding: 0.75rem 1.5rem !important;
    font-size: 1rem !important;
    font-weight: 600 !important;
    color: white !important;
    transition: all 0.3s ease !important;
    border: none !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
    text-decoration: none !important; /* Remove underline for links */
}

/* First Registration Modal Button (Red) */
.continue-btn {
    background-color: #4A90E2 !important;
}

.continue-btn:hover {
    background-color: #337ed3ff !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(252, 86, 108, 0.3) !important;
}

/* Complete Registration, Login, and Book Appointment Buttons (Warm Blue) */
.complete-btn {
    background-color: #4A90E2 !important;
}

.complete-btn:hover {
    background-color: #357ABD !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(74, 144, 226, 0.3) !important;
}

.continue-btn:active, .complete-btn:active {
    transform: scale(0.98) !important;
}

.continue-btn:disabled, .complete-btn:disabled {
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
    opacity: 0.5 !important;
}

.continue-btn:disabled {
    background-color: #4A90E2 !important;
}

.complete-btn:disabled {
    background-color: #4A90E2 !important;
}

.continue-btn svg, .complete-btn svg {
    width: 16px !important;
    height: 16px !important;
    margin-left: 8px !important;
}

.continue-btn:disabled:hover, .complete-btn:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Logo image styles */
.logo-image {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Header title styles */
.header-title-container {
    display: flex;
    flex-direction: column;
}

.barangay-text {
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    margin-bottom: 2px;
    opacity: 0.9;
}

.main-title {
    font-size: 1.5rem;
    font-weight: bold;
    line-height: 1.2;
}

/* Profile section styles */
.profile-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-left: 1rem;
    border-left: 1px solid rgba(255, 255, 255, 0.3);
}

.profile-avatar {
    background-color: #d1d5db;
    height: 32px;
    width: 32px;
    border-radius: 50%;
    background-size: cover;
    background-position: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.profile-avatar:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.profile-avatar.has-image::after {
    content: 'Change';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.profile-avatar.has-image:hover::after {
    opacity: 1;
}

.profile-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.welcome-text {
    color: white;
    font-size: 0.875rem;
}

.username-text {
    font-size: 0.75rem;
}

/* NEW: Enhanced Modal Styles */
.modal-overlay {
    background: rgba(0, 0, 0, 0.5);
    transition: opacity 0.3s ease-in-out;
}

.modal-content {
    transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
    transform: scale(0.95);
    opacity: 0;
}

.modal-content.open {
    transform: scale(1);
    opacity: 1;
}

.modal-close-btn {
    transition: all 0.3s ease;
    padding: 0.5rem;
    border-radius: 50%;
}

.modal-close-btn:hover {
    background-color: rgba(0, 0, 0, 0.1);
    transform: rotate(90deg);
}

/* Profile Picture Upload Modal */
.profile-modal-content {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.profile-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #3C96E1;
    margin: 0 auto;
}

.profile-upload-btn {
    background: #3C96E1;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-upload-btn:hover {
    background: #2B7CC9;
    transform: translateY(-1px);
}

.profile-remove-btn {
    background: #ef4444;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-remove-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Logout Modal Styles */
.logout-modal-content {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.logout-modal-buttons {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.logout-cancel-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid #d1d5db;
    background: white;
    color: #4b5563;
    cursor: pointer;
}

.logout-cancel-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.logout-confirm-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    border-radius: 9999px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    background: #ef4444;
    color: white;
    cursor: pointer;
}

.logout-confirm-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Add these new styles for disabled buttons */
.continue-btn:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}

.continue-btn:disabled:hover {
    background-color: #4A90E2 !important;
    transform: none !important;
    box-shadow: none !important;
}

/* Responsive adjustments */
@media (max-width: 1024px) {

    .staff-nav-container,
    .user-nav-container,
    .admin-nav-container {
        flex-direction: column;
        gap: 0.5rem;
    }

    .time-display-container {
        margin-left: 0;
        align-self: flex-end;
    }

    .nav-tab-container {
        justify-content: center;
    }

    .search-input {
        width: 200px;
    }
}

@media (max-width: 768px) {
    .time-display-container {
        padding: 0.5rem 1rem;
    }

    .date-display-horizontal {
        font-size: 0.8rem;
    }

    .time-display-main-horizontal {
        font-size: 0.9rem;
    }

    .nav-tab {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }

    .logout-btn {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
    }

    .time-zone {
        display: none;
    }

    .logo-image {
        width: 50px;
        height: 50px;
    }

    .barangay-text {
        font-size: 0.8rem;
    }

    .main-title {
        font-size: 1.25rem;
    }

    .search-input {
        width: 180px;
    }

    .profile-section {
        padding-left: 0.5rem;
    }

    .profile-avatar {
        height: 28px;
        width: 28px;
    }
}

@media (max-width: 640px) {
    .time-display-horizontal {
        flex-direction: column;
        gap: 0.2rem;
    }

    .time-separator {
        display: none;
    }

    .date-display-horizontal {
        font-size: 0.75rem;
    }

    .time-display-main-horizontal {
        font-size: 0.85rem;
    }

    .nav-tab-container {
        flex-wrap: wrap;
        justify-content: center;
    }

    .nav-tab {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }

    .logo-image {
        width: 45px;
        height: 45px;
    }

    .barangay-text {
        font-size: 0.75rem;
    }

    .main-title {
        font-size: 1.1rem;
    }

    .search-input {
        width: 150px;
        font-size: 0.875rem;
    }

    .profile-info {
        display: none;
    }
}

/* --- NEW DASHBOARD STYLES --- */

/* Stats card styling */
.stats-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

/* Quick action cards */
.quick-action-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

.quick-action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

/* Progress bar */
.progress-bar {
    height: 8px;
    border-radius: 4px;
    background-color: #e5e7eb;
    overflow: hidden;
    margin-top: 8px;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}
    </style>
</head>
<body class="bg-gray-100">
    
    <div class="container mx-auto px-4 py-6">
        <!-- Dashboard Header -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Admin Dashboard
            </h1>
            <p class="text-gray-600">Overview of system statistics and activities</p>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards with Visualizations -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Staff Card -->
            <div class="stats-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                            <i class="fas fa-user-shield text-blue-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Total Staff</h3>
                    </div>
                    <span class="text-sm font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                        <?= isset($staff_percentage) ? $staff_percentage : 0 ?>%
                    </span>
                </div>
                <p class="text-3xl font-bold text-blue-600 mb-2"><?= $stats['total_staff'] ?></p>
                <p class="text-gray-500 text-sm mb-2">Registered health staff</p>
                <div class="progress-bar">
                    <div class="progress-fill bg-blue-600" style="width: <?= isset($staff_percentage) ? $staff_percentage : 0 ?>%"></div>
                </div>
                <a href="/community-health-tracker/admin/staffrecords.php" class="block mt-4 text-blue-600 text-sm font-medium hover:underline">View all staff →</a>
            </div>
            
            <!-- Users Card -->
            <div class="stats-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                            <i class="fas fa-users text-green-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                    </div>
                    <span class="text-sm font-medium px-2 py-1 rounded-full bg-green-100 text-green-800">
                        <?= isset($users_percentage) ? $users_percentage : 0 ?>%
                    </span>
                </div>
                <p class="text-3xl font-bold text-green-600 mb-2"><?= $stats['total_users'] ?></p>
                <p class="text-gray-500 text-sm mb-2">Registered community users</p>
                <div class="progress-bar">
                    <div class="progress-fill bg-green-600" style="width: <?= isset($users_percentage) ? $users_percentage : 0 ?>%"></div>
                </div>
                <a href="/community-health-tracker/admin/registeredusers.php" class="block mt-4 text-blue-600 text-sm font-medium hover:underline">Manage users →</a>
            </div>
            
            <!-- Patients Card -->
            <div class="stats-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center mr-4">
                            <i class="fas fa-procedures text-purple-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700">Total Patients</h3>
                    </div>
                    <span class="text-sm font-medium px-2 py-1 rounded-full bg-purple-100 text-purple-800">
                        <?= isset($patients_percentage) ? $patients_percentage : 0 ?>%
                    </span>
                </div>
                <p class="text-3xl font-bold text-purple-600 mb-2"><?= $stats['total_patients'] ?></p>
                <p class="text-gray-500 text-sm mb-2">Patient records</p>
                <div class="progress-bar">
                    <div class="progress-fill bg-purple-600" style="width: <?= isset($patients_percentage) ? $patients_percentage : 0 ?>%"></div>
                </div>
                <a href="/community-health-tracker/admin/viewpatients.php" class="block mt-4 text-blue-600 text-sm font-medium hover:underline">View patients →</a>
            </div>
            
            <!-- Pending Approvals Card -->
            <div class="stats-card">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center mr-4">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                </div>
                <p class="text-3xl font-bold text-yellow-600 mb-2"><?= $stats['pending_approvals'] ?></p>
                <p class="text-gray-500 text-sm mb-4">Awaiting approval</p>
                <!-- Mini gauge for pending approvals -->
                <div class="relative h-2 bg-gray-200 rounded-full overflow-hidden mt-2">
                    <?php
                    $max_pending = max($stats['pending_approvals'], 10); // Ensure minimum scale
                    $pending_width = min(($stats['pending_approvals'] / $max_pending) * 100, 100);
                    ?>
                    <div class="absolute h-full bg-yellow-500 rounded-full" style="width: <?= $pending_width ?>%"></div>
                </div>
                <a href="/community-health-tracker/admin/approvals.php" class="block mt-4 text-blue-600 text-sm font-medium hover:underline">Review approvals →</a>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Line Chart -->
            <div class="stats-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold flex items-center text-blue-600">
                        <i class="fas fa-chart-line text-blue-600 mr-2"></i> Growth Trend (Last 7 Days)
                    </h2>
                    <div class="flex space-x-2">
                        <button onclick="toggleDataset('staff')" class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200">Staff</button>
                        <button onclick="toggleDataset('users')" class="text-xs px-3 py-1 rounded-full bg-green-100 text-green-800 hover:bg-green-200">Users</button>
                        <button onclick="toggleDataset('patients')" class="text-xs px-3 py-1 rounded-full bg-purple-100 text-purple-800 hover:bg-purple-200">Patients</button>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
            
            <!-- Pie Chart -->
            <div class="stats-card">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold flex items-center text-blue-600">
                        <i class="fas fa-chart-pie text-blue-600 mr-2"></i> Distribution Overview
                    </h2>
                    <span class="text-gray-600 text-sm">Total: <?= $total_records ?></span>
                </div>
                <div class="h-80">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Activities Section -->
            <div class="stats-card lg:col-span-2">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold flex items-center text-blue-600">
                        <i class="fas fa-history text-blue-600 mr-2"></i> Recent Activities
                    </h2>
                    <a href="/community-health-tracker/admin/activities" class="text-blue-600 text-sm hover:underline">View all activities</a>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-user-plus text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">New staff member registered</p>
                            <p class="text-gray-500 text-sm">2 hours ago</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-user-check text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">User account approved</p>
                            <p class="text-gray-500 text-sm">5 hours ago</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-file-medical text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">New patient record added</p>
                            <p class="text-gray-500 text-sm">Yesterday</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center mr-3 mt-1">
                            <i class="fas fa-stethoscope text-gray-600"></i>
                        </div>
                        <div>
                            <p class="text-gray-700">Health check-up completed</p>
                            <p class="text-gray-500 text-sm">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="stats-card">
                <h2 class="text-xl font-semibold mb-6 flex items-center text-blue-600">
                    <i class="fas fa-bolt text-blue-600 mr-2"></i> Quick Actions
                </h2>
                
                <div class="space-y-4">
                    <a href="staff_docs.php" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-blue-600 flex items-center justify-center mr-4">
                            <i class="fas fa-user-plus text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Add New Staff</h3>
                            <p class="text-gray-500 text-sm">Register health personnel</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-green-600 flex items-center justify-center mr-4">
                            <i class="fas fa-file-export text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">Generate Report</h3>
                            <p class="text-gray-500 text-sm">Export system data</p>
                        </div>
                    </a>
                    
                    <a href="/community-health-tracker/admin/settings" class="quick-action-card flex items-center">
                        <div class="w-12 h-12 rounded-lg bg-purple-600 flex items-center justify-center mr-4">
                            <i class="fas fa-cog text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800">System Settings</h3>
                            <p class="text-gray-500 text-sm">Configure application</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const chartLabels = <?= json_encode($chart_data['labels']) ?>;
        const staffData = <?= json_encode($chart_data['staff_data']) ?>;
        const usersData = <?= json_encode($chart_data['users_data']) ?>;
        const patientsData = <?= json_encode($chart_data['patients_data']) ?>;
        
        const totalStaff = <?= $stats['total_staff'] ?>;
        const totalUsers = <?= $stats['total_users'] ?>;
        const totalPatients = <?= $stats['total_patients'] ?>;
        
        // Line Chart Configuration
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        let growthChart = new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Staff',
                        data: staffData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Users',
                        data: usersData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Patients',
                        data: patientsData,
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Function to toggle datasets
        function toggleDataset(type) {
            const datasets = growthChart.data.datasets;
            
            datasets.forEach((dataset, index) => {
                if (dataset.label.toLowerCase() === type) {
                    dataset.hidden = !dataset.hidden;
                }
            });
            
            growthChart.update();
        }
        
        // Pie Chart Configuration
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Staff', 'Users', 'Patients'],
                datasets: [{
                    data: [totalStaff, totalUsers, totalPatients],
                    backgroundColor: [
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6'
                    ],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
        
        // Animate progress bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 300);
            });
        });
    </script>
    
</body>
</html>

<?php
// require_once __DIR__ . '/../includes/footer.php';
?>