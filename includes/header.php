<?php
ob_start(); // Start output buffering

require_once __DIR__ . '/auth.php';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Get current page to determine active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get user's profile picture if exists
$profile_picture = null;
if (isset($_SESSION['user']['id'])) {
    // Define paths for profile pictures
    $profile_dir = __DIR__ . '/../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!file_exists($profile_dir)) {
        mkdir($profile_dir, 0777, true);
    }
    
    // Check for profile picture
    $user_id = $_SESSION['user']['id'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    foreach ($allowed_extensions as $ext) {
        $potential_file = $profile_dir . 'profile_' . $user_id . '.' . $ext;
        if (file_exists($potential_file)) {
            $profile_picture = '/community-health-tracker/uploads/profiles/profile_' . $user_id . '.' . $ext;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Luz Health Monitoring and Tracking</title>
    <link rel="icon" type="image/png" href="../asssets/images/Luz.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/community-health-tracker/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/community-health-tracker/assets/js/scripts.js" defer></script>
</head>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

body,
.sidebar,
.nav-tab,
.logout-btn,
.time-display-container,
.continue-btn,
.complete-btn,
.profile-upload-btn,
.profile-remove-btn,
.logout-cancel-btn,
.logout-confirm-btn {
    font-family: 'Poppins', sans-serif !important;
}

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
        background: #2B7CC9;
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
        background: #3C96E1;
        transform: translateY(-1px);
        color: white;
    }

    /* NEW: Improved time display containers - Horizontal layout */
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
        font-size: 0.75rem;
        margin-left: 0.25rem;
        opacity: 0.9;
        font-family: 'Poppins', sans-serif;
        font-weight: 500;
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
</style>

<body class="bg-[#F8F8F]">
    <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
            <!-- Admin Header - Updated to match user/staff design -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Health Center Admin Panel</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search Bar -->
                        <div class="search-container">
                            
                        </div>
                        
                        <!-- Profile Section -->
                        <div class="profile-section">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('admin')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-2xl text-gray-400 absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <span class="welcome-text">Welcome Super Admin!</span>
                                <span class="username-text"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                            </div>
                        </div>
                        
                        <!-- Enhanced Logout Button - UPDATED FOR FULL ROUND -->
                        <button type="button" onclick="showLogoutModal('admin')" class="logout-btn">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between admin-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="../admin/dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="../admin/manage_accounts.php"
                                    class="nav-tab <?= ($current_page == 'manage_accounts.php') ? 'active' : '' ?>">
                                    Manage Accounts
                                </a>
                            </div>
                        
                            <div class="nav-connection">
                                <a href="../admin/appointments.php"
                                    class="nav-tab <?= ($current_page == 'appointments.php') ? 'active' : '' ?>">
                                    Appointments
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="../admin/Inventory_medicine.php"
                                    class="nav-tab <?= ($current_page == 'Inventory_medicine.php') ? 'active' : '' ?>">
                                    Medicine Inventory
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container admin-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="admin-ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="admin-ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="admin-ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="admin-ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="admin-ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

        <?php elseif (isStaff()): ?>
            <!-- Staff Header -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Health Center Staff Panel</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-10">
                        <div class="flex items-center space-x-5">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('staff')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium">Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        </div>
                        <button type="button" onclick="showLogoutModal('staff')" class="logout-btn bg-white text-[#3C96E1] hover:bg-[#2B7CC9] hover:text-white">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between staff-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/existing_info_patients.php"
                                    class="nav-tab <?= ($current_page == 'existing_info_patients.php') ? 'active' : '' ?>">
                                    Medical Records
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="/community-health-tracker/staff/announcements.php"
                                    class="nav-tab <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container staff-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="staff-ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="staff-ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="staff-ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

        <?php elseif (isUser()): ?>
            <!-- User Header -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <!-- Barangay Toong Logo -->
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="logo-image">
                        <!-- Updated Header Title with Barangay Toong text -->
                        <div class="header-title-container">
                            <div class="barangay-text">Barangay Luz</div>
                            <a href="/community-health-tracker/" class="main-title">Resident Consultation Portal</a>
                        </div>
                    </div>

                    <div class="flex items-center space-x-10">
                        <div class="flex items-center space-x-5">
                            <div class="profile-avatar <?php echo $profile_picture ? 'has-image' : ''; ?>"
                                 style="<?php echo $profile_picture ? 'background-image: url(\'' . $profile_picture . '\')' : ''; ?>"
                                 onclick="openProfileModal('user')">
                                <?php if (!$profile_picture): ?>
                                    <i class="fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center"></i>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        </div>
                        <!-- Enhanced Logout Button - UPDATED FOR FULL ROUND -->
                        <button type="button" onclick="showLogoutModal('user')" class="logout-btn bg-white text-[#3C96E1] hover:bg-[#2B7CC9] hover:text-white">
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#2B7CC9] py-3">
                    <div class="container mx-auto px-4 flex items-center justify-between user-nav-container">
                        <div class="nav-tab-container">
                            <div class="nav-connection">
                                <a href="dashboard.php"
                                    class="nav-tab <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="health_records.php"
                                    class="nav-tab <?= ($current_page == 'health_records.php') ? 'active' : '' ?>">
                                    My Record
                                </a>
                            </div>
                            <div class="nav-connection">
                                <a href="announcements.php"
                                    class="nav-tab <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </div>
                        </div>

                        <!-- Date and Time Display - Horizontal layout on the right side -->
                        <div class="time-display-container user-time-container">
                            <div class="time-display-horizontal">
                                <div class="date-display-horizontal">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span id="ph-date"><?php echo date('M j, Y'); ?></span>
                                </div>
                                <div class="time-separator"></div>
                                <div class="time-display-main-horizontal">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span id="ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                    <span class="time-zone">PHT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <!-- Public Header (Not logged in) -->
        <style>
            .mobile-menu {
                display: none;
            }

            .mobile-menu.active {
                display: block;
            }

            .touch-target {
                position: relative;
            }

            .touch-target::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 40px;
                height: 40px;
            }

            .circle-image {
                width: 65px;
                height: 65px;
                border-radius: 50%;
                object-fit: cover;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .nav-container {
                padding-top: 1rem;
                padding-bottom: 1rem;
            }

            .logo-text {
                line-height: 1.2;
            }

            .nav-link {
                font-size: 1.1rem;
                padding: 0.5rem 1rem;
            }
        </style>
        </head>

        <body>
            <nav class="text-black py-10 px-4 sm:px-14 sticky top-0 z-50">
                <div class="py-5 bg-[#FFFFFF] rounded-2xl flex shadow-2xl justify-between items-center">
                    <!-- Logo/Title with two-line text -->
                    <div class="flex items-center mx-8 sm:mx-16">
                        <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo"
                            class="circle-image mr-4">
                        <div class="logo-text">
                            <div class="font-bold text-xl leading-tight">Barangay Luz</div>
                            <div class="text-lg text-gray-700">Monitoring and Tracking</div>
                        </div>
                    </div>

                    <!-- Mobile menu button - hidden on desktop -->
                    <button class="md:hidden mx-8 touch-target p-2" onclick="toggleMobileMenu()">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <!-- Desktop navigation - centered nav list -->
                    <div class="hidden md:flex items-center flex-1 justify-center">
                        <!-- Centered nav links -->
                        <ul class="flex items-center space-x-8 font-semibold">
                            <li>
                                <a href="#"
                                    class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Home</a>
                            </li>
                            <li>
                                <a href="#about"
                                    class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">About</a>
                            </li>
                            <li>
                                <a href="#services"
                                    class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Services</a>
                            </li>
                            <li>
                                <a href="#contact"
                                    class="nav-link text-gray-700 hover:text-[#4A90E2] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Contact</a>
                            </li>
                        </ul>
                    </div>

                    <!-- Book Appointment button - positioned to the right -->
                    <div class="hidden md:flex items-center">
                        <a href="#" onclick="openModal()"
                            class="complete-btn bg-[#4A90E2] mx-16 text-lg text-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg">
                            Book Appointment
                        </a>
                    </div>
                </div>

                <!-- Mobile menu content - only shows on mobile -->
                <div id="mobile-menu" class="mobile-menu md:hidden bg-white border-t">
                    <div class="px-2 pt-2 pb-3 space-y-1">
                        <a href="#" class="block px-3 py-2 text-gray-700 hover:bg-gray-100 rounded nav-link">Home</a>
                        <a href="#" class="block px-3 py-2 text-gray-700 hover:bg-gray-100 rounded nav-link">About</a>
                        <a href="#" class="block px-3 py-2 text-gray-700 hover:bg-gray-100 rounded nav-link">Services</a>
                        <a href="#" class="block px-3 py-2 text-gray-700 hover:bg-gray-100 rounded nav-link">Contact</a>
                        <a href="#" onclick="openModal()"
                            class="complete-btn bg-[#4A90E2] text-white px-5 py-3 rounded-full transition-all text-center mx-1 mt-2 flex items-center justify-center gap-2 nav-link shadow-md hover:shadow-lg">
                            Book Appointment
                        </a>
                    </div>
                </div>
            </nav>

            <style>
                /* Mobile menu styles */
                .mobile-menu {
                    transition: all 0.3s ease;
                    max-height: 0;
                    overflow: hidden;
                }

                .mobile-menu-open {
                    max-height: 1000px;
                }

                /* Better touch targets for mobile */
                .touch-target {
                    min-height: 48px;
                    min-width: 48px;
                }
            </style>

            <script>
                // Mobile menu toggle
                function toggleMobileMenu() {
                    const mobileMenu = document.getElementById('mobile-menu');
                    mobileMenu.classList.toggle('mobile-menu-open');
                }

                // Close mobile menu when clicking outside
                document.addEventListener('click', function (event) {
                    const mobileMenu = document.getElementById('mobile-menu');
                    const mobileMenuButton = document.querySelector('.md\\:hidden.touch-target');

                    if (mobileMenu && mobileMenuButton &&
                        !mobileMenu.contains(event.target) &&
                        !mobileMenuButton.contains(event.target) &&
                        mobileMenu.classList.contains('mobile-menu-open')) {
                        mobileMenu.classList.remove('mobile-menu-open');
                    }
                });
            </script>

            <!-- Login Modal -->
<div id="loginModal" class="fixed inset-0 hidden z-50 h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
    <div class="relative bg-white p-4 sm:p-6 rounded-lg shadow-lg w-full max-w-2xl mx-auto max-h-[90vh] overflow-y-auto modal-content">
        <!-- Main Modal Content -->
        <div id="mainModal" class="py-4">
            <!-- Close Button with consistent padding -->
            <button onclick="closeModal()"
                class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Logo at the top - bigger and circular -->
            <div class="flex justify-center mb-4 mx-4">
                <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo" 
                     class="w-20 h-20 rounded-full object-cover border-4 border-[#3C96E1] shadow-lg">
            </div>

            <!-- Main Title - Bigger -->
            <div class="text-center mb-2 mx-4">
                <h1 class="text-2xl font-bold text-[#4A90E2]">Barangay Luz Cebu City</h1>
            </div>

            <!-- Instruction Text - Smaller -->
            <div class="flex flex-col items-center mb-6 mx-4">
                <p class="text-xs text-center text-gray-600 max-w-md leading-relaxed">
                    To access records and appointments, please log in with your authorized account or register for a new account to securely continue using the system today online.
                </p>
            </div>

            <!-- Healthcare Image -->
            <div class="mb-6 mx-4">
                <img src="./asssets/images/Dev.jpg" alt="Healthcare illustration" 
                     class="w-full h-48 object-cover rounded-lg shadow-md">
            </div>

            <!-- Buttons in vertical position with consistent margins -->
            <div class="flex flex-col gap-3 mx-4">
                <button id="openLogin"
                    class="complete-btn bg-[#4A90E2] w-full h-14 rounded-full text-white flex items-center justify-center shadow-md hover:shadow-lg text-lg font-semibold">
                    Login
                </button>

                <button id="openRegister"
                    class="complete-btn bg-[#4A90E2] w-full h-14 rounded-full text-white flex items-center justify-center shadow-md hover:shadow-lg text-lg font-semibold">
                    Register
                </button>
            </div>
        </div>

        <!-- Login Form Modal -->
        <div id="loginFormModal" class="hidden py-4">
            <!-- Close Button with consistent padding -->
            <button onclick="closeModal()"
                class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Logo at the top - consistent with main modal -->
            <div class="flex justify-center mb-4 mx-4">
                <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo" 
                     class="w-16 h-16 rounded-full object-cover border-4 border-[#3C96E1] shadow-lg">
            </div>

            <!-- Main Title - consistent styling -->
            <div class="text-center mb-6 mx-4">
                <h2 class="text-xl font-bold text-[#4A90E2]">Access Your Account</h2>
                <p class="text-gray-600 mt-2 text-sm">Sign in to your resident account</p>
            </div>

            <form method="POST" action="auth/login.php" class="space-y-6">
                <input type="hidden" name="role" value="user">
                <div class="space-y-6 mx-4">
                    <!-- Username -->
                    <div>
                        <label for="login-username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="login-username" placeholder="Enter Username"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" required />
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="login-password" class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input id="login-password" name="password" type="password" placeholder="Password"
                                class="w-full p-3 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" required />
                            <button type="button" onclick="toggleLoginPassword()"
                                class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                                <i id="login-eyeIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Forgot Password -->
                    <div class="text-right mt-4">
                        <a href="#" class="text-sm text-[#3C96E2] hover:underline">Forgot your password?</a>
                    </div>

                    <!-- Login Button - consistent with main modal buttons -->
                    <div class="mt-6">
                        <button type="submit"
                            class="complete-btn bg-[#3C96E1] w-full p-3 rounded-full text-white transition-all duration-200 font-medium shadow-md hover:shadow-lg text-lg h-14">
                            Login
                        </button>
                    </div>

                    <!-- Register Link -->
                    <div class="flex justify-center text-sm font-medium space-x-1 mt-4">
                        <p class="text-gray-600">Don't have an account?</p>
                        <button id="loginToRegister" type="button"
                            class="text-[#3C96E2] hover:underline">Register</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- First Registration Modal -->
        <div id="registerFormModal" class="hidden py-4">
            <!-- Close Button with consistent padding -->
            <button onclick="closeModal()"
                class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Logo at the top - consistent with main modal -->
            <div class="flex justify-center mb-4 mx-4">
                <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo" 
                     class="w-16 h-16 rounded-full object-cover border-4 border-[#3C96E1] shadow-lg">
            </div>

            <!-- Main Title - consistent styling -->
            <div class="text-center mb-6 mx-4">
                <h2 class="text-xl font-bold text-[#3C96E1]">Register Your Account</h2>
                <p class="text-gray-600 mt-2 text-sm">Sign up to your resident account</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6 mx-4 text-sm"
                    role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6 mx-4 text-sm"
                    role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form id="firstRegisterForm" class="space-y-6">
                <div class="space-y-6 mx-4">
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="full_name" name="full_name" placeholder="Full Name"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                            required />
                    </div>

                    <!-- Date of Birth Field - Updated with consistent styling -->
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth <span class="text-red-500">*</span></label>
                        <input type="date" 
                               id="date_of_birth" 
                               name="date_of_birth" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                               required
                               max="<?= date('Y-m-d') ?>">
                        <p class="text-xs text-gray-600 mt-2">Please enter your exact date of birth for accurate record keeping</p>
                    </div>

                    <!-- Age Field - Auto-calculated and read-only -->
                    <div>
                        <label for="age" class="block text-sm font-medium text-gray-700 mb-2">Age (Auto-calculated) <span class="text-red-500">*</span></label>
                        <input type="number" 
                               id="age" 
                               name="age" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input bg-gray-100 cursor-not-allowed"
                               readonly
                               required>
                        <p class="text-xs text-gray-600 mt-2">Age is automatically calculated from your date of birth</p>
                    </div>

                    <!-- Gender Field -->
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
                        <select id="gender" name="gender"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                            required>
                            <option value="" disabled selected>Select Gender</option>
                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Contact Number Field -->
                    <div>
                        <label for="contact" class="block text-sm font-medium text-gray-700 mb-2">Contact Number <span class="text-red-500">*</span></label>
                        <input type="tel" id="contact" name="contact" placeholder="Contact Number"
                            value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                            required />
                    </div>

                    <!-- Updated Address Field - Changed to Barangay Luz Cebu City -->
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input type="text" id="address" name="address" 
                                value="Barangay Luz, Cebu City"
                                readonly
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input bg-gray-100 cursor-not-allowed"
                                required />
                            <button type="button"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-not-allowed"
                                title="Address auto-detected" disabled>
                                <i class="fas fa-check-circle text-sm text-green-500"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <i class="fas fa-info-circle text-[#3C96E1] mr-1"></i>
                            Your address has been automatically set to Barangay Luz, Cebu City
                        </p>
                    </div>

                    <!-- New Sitio Field -->
                    <div>
                        <label for="sitio" class="block text-sm font-medium text-gray-700 mb-2">Sitio <span class="text-red-500">*</span></label>
                        <select id="sitio" name="sitio"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                            required>
                            <option value="" disabled selected>Select your Sitio</option>
                            <option value="Proper Luz">Proper Luz</option>
                            <option value="Lower Luz">Lower Luz</option>
                            <option value="Upper Luz">Upper Luz</option>
                            <option value="Luz Proper">Luz Proper</option>
                            <option value="Luz Heights">Luz Heights</option>
                            <option value="Panganiban">Panganiban</option>
                            <option value="Balagtas">Balagtas</option>
                            <option value="Carbon">Carbon</option>
                            <!-- Add more sitios as needed for Barangay Luz -->
                        </select>
                    </div>

                    <!-- Civil Status and Occupation -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="civil_status" class="block text-sm font-medium text-gray-700 mb-2">Civil Status <span class="text-red-500">*</span></label>
                            <select id="civil_status" name="civil_status" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" required>
                                <option value="" disabled selected>Select Civil Status</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="widowed">Widowed</option>
                                <option value="separated">Separated</option>
                                <option value="divorced">Divorced</option>
                            </select>
                        </div>

                        <div>
                            <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">Occupation</label>
                            <input type="text" id="occupation" name="occupation" placeholder="Occupation (optional)"
                                value="<?php echo isset($_POST['occupation']) ? htmlspecialchars($_POST['occupation']) : ''; ?>"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input" />
                        </div>
                    </div>

                    <!-- Continue Button - consistent styling -->
                    <div class="mt-6">
                        <button type="button" id="openSecondRegister"
                            class="continue-btn bg-[#3C96E1] w-full rounded-full text-white flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-all duration-200 shadow-md hover:shadow-lg active:scale-[0.98] text-lg h-14"
                            disabled>
                            Continue
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Login Link -->
                    <div class="flex flex-col sm:flex-row justify-center text-sm font-medium space-y-1 sm:space-y-0 sm:space-x-1 mt-4">
                        <p class="text-gray-600">Already have an account?</p>
                        <button id="registerToLogin" type="button"
                            class="text-[#3C96E1] hover:underline">Login</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Second Registration Modal -->
        <div id="secondRegisterFormModal" class="hidden py-4">
            <!-- Close Button with consistent padding -->
            <button onclick="closeModal()"
                class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Back Button -->
            <div class="mx-4 mb-4">
                <button
                    class="h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 transition-colors"
                    id="backToFirstRegister" aria-label="Back">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                        class="w-5 h-5 text-[#FC566C]">
                        <path d="M15.75 19.5L8.25 12l7.5-7.5v15z" />
                    </svg>
                </button>
            </div>

            <!-- Logo at the top - consistent with main modal -->
            <div class="flex justify-center mb-4 mx-4">
                <img src="./asssets/images/Luz.jpg" alt="Barangay Luz Logo" 
                     class="w-16 h-16 rounded-full object-cover border-4 border-[#3C96E1] shadow-lg">
            </div>

            <!-- Main Title - consistent styling -->
            <div class="text-center mb-6 mx-4">
                <h2 class="text-xl font-bold text-[#3C96E1]">Complete Your Registration</h2>
                <p class="text-gray-600 mt-2 text-sm">Add your account credentials</p>
            </div>

            <form method="POST" action="auth/register.php" id="secondRegisterForm" enctype="multipart/form-data">
                <div class="space-y-6 mx-4">
                    <!-- Hidden fields to pass data from first form -->
                    <input type="hidden" name="full_name" id="hidden_full_name" value="">
                    <input type="hidden" name="date_of_birth" id="hidden_date_of_birth" value="">
                    <input type="hidden" name="age" id="hidden_age" value="">
                    <input type="hidden" name="gender" id="hidden_gender" value="">
                    <input type="hidden" name="contact" id="hidden_contact" value="">
                    <input type="hidden" name="address" id="hidden_address" value="Barangay Luz, Cebu City">
                    <input type="hidden" name="sitio" id="hidden_sitio" value="">
                    <input type="hidden" name="civil_status" id="hidden_civil_status" value="">
                    <input type="hidden" name="occupation" id="hidden_occupation" value="">

                    <div class="space-y-6">
                        <div>
                            <label for="reg-username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="reg-username" name="username" placeholder="Username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                                required />
                        </div>

                        <div>
                            <label for="reg-email" class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="reg-email" name="email" placeholder="Email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                                required />
                        </div>

                        <div>
                            <label for="reg-password" class="block text-sm font-medium text-gray-700 mb-2">Password <span class="text-red-500">*</span></label>
                            <input type="password" id="reg-password" name="password"
                                placeholder="Password (min 8 characters)"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                                minlength="8" required />
                            <p class="text-xs text-gray-500 mt-2">Password must be at least 8 characters</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password <span class="text-red-500">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                placeholder="Confirm Password"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] form-input"
                                minlength="8" required />
                            <p id="passwordMatchError" class="text-xs text-red-500 mt-2 hidden">Passwords do not match</p>
                        </div>
                    </div>

                    <!-- ID Verification Section -->
                    <div class="border-t border-gray-200 pt-8 mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Identity Verification</h3>
                        
                        <!-- Verification Method Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Verification Method</label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="radio" name="verification_method" value="manual_verification" class="mr-3" checked>
                                    <span class="text-sm">Manual Verification (Staff will contact you)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="verification_method" value="id_upload" class="mr-3">
                                    <span class="text-sm">Upload ID Document</span>
                                </label>
                            </div>
                        </div>

                        <!-- ID Upload Section (Initially Hidden) -->
                        <div id="idUploadSection" class="hidden space-y-4 bg-blue-50 p-4 rounded-lg border border-blue-200 mt-4">
                            <!-- File Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload ID Document <span class="text-red-500">*</span>
                                </label>
                                <p class="text-xs text-gray-600 mb-3">
                                    Acceptable documents: Scanned/photo ID showing name, photo, and barangay address. 
                                    Barangay clearance or voter's ID are also accepted.
                                </p>
                                <input type="file" id="id_image" name="id_image" 
                                    accept=".jpg,.jpeg,.png,.gif,.pdf"
                                    class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                <p class="text-xs text-gray-500 mt-2">Max file size: 5MB (JPEG, PNG, GIF, PDF)</p>
                                <div id="filePreview" class="mt-3 hidden">
                                    <img id="previewImage" class="max-w-full h-32 object-contain border rounded">
                                </div>
                            </div>

                            <!-- Consent Checkbox -->
                            <div class="bg-white p-3 rounded border mt-4">
                                <label class="flex items-start">
                                    <input type="checkbox" name="verification_consent" value="1" 
                                        class="mt-1 mr-3" required>
                                    <span class="text-sm text-gray-700">
                                        I consent to upload my ID/photo for verification purposes. 
                                        <span class="block text-xs text-gray-500 mt-2">
                                            "Your ID/photo will only be used to verify your residency and identity for barangay health services. It will not be shared without your permission."
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Manual Verification Notes -->
                        <div id="manualVerificationSection" class="space-y-4 bg-yellow-50 p-4 rounded-lg border border-yellow-200 mt-4">
                            <div>
                                <label for="verification_notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Additional Information for Manual Verification
                                </label>
                                <textarea id="verification_notes" name="verification_notes" 
                                    placeholder="Please provide any additional information that might help staff verify your identity..."
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm"
                                    rows="3"></textarea>
                                <p class="text-xs text-gray-500 mt-2">
                                    Our staff will contact you using the provided contact information to complete the verification process.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Complete Registration Button - consistent styling -->
                    <div class="mt-6">
                        <button type="submit" id="submitButton"
                            class="complete-btn bg-[#3C96E1] w-full rounded-full text-white transition-all duration-200 font-medium shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed text-lg h-14">
                            Complete Registration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

        <?php endif; ?>

        <!-- Profile Picture Upload Modal -->
        <div id="profileModal" class="fixed inset-0 hidden z-[70] h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
            <div class="relative bg-white p-6 sm:p-8 rounded-lg shadow-lg w-full max-w-md mx-auto modal-content profile-modal-content">
                <!-- Close Button -->
                <button onclick="closeProfileModal()"
                    class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Modal Title -->
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-gray-800">Profile Picture</h3>
                    <p class="text-gray-600 mt-2">Upload or change your profile picture</p>
                </div>

                <!-- Current Profile Picture Preview -->
                <div class="mb-6">
                    <img id="profilePreview" src="<?php echo $profile_picture ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEyIiBmaWxsPSIjZDFkNWRiIi8+PHBhdGggZD0iTTEyIDExYTIgMiAwIDEgMCAwLTQgMiAyIDAgMCAwIDAgNHoiIGZpbGw9IiM5Y2EzYWYiLz48cGF0aCBkPSJNMTIgMTVhNCA0IDAgMCAwLTQgNGg4YTQgNCAwIDAgMC00LTR6IiBmaWxsPSIjOWNhM2FmIi8+PC9zdmc+' ?>"
                         alt="Profile Preview" class="profile-preview">
                </div>

                <!-- Upload Form -->
                <form id="profileUploadForm" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="user_id" value="<?php echo isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : '' ?>">
                    <input type="hidden" name="user_type" id="profileUserType" value="">
                    
                    <div>
                        <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-2">
                            Choose Profile Picture
                        </label>
                        <input type="file" id="profile_image" name="profile_image" 
                            accept=".jpg,.jpeg,.png,.gif"
                            class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-500 mt-2">Max file size: 2MB (JPEG, PNG, GIF)</p>
                        <div id="profileUploadError" class="text-xs text-red-500 mt-2 hidden"></div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="removeProfilePicture()" 
                            class="profile-remove-btn flex-1 <?php echo !$profile_picture ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?php echo !$profile_picture ? 'disabled' : '' ?>>
                            <i class="fas fa-trash-alt"></i>
                            Remove
                        </button>
                        <button type="submit" class="profile-upload-btn flex-1">
                            <i class="fas fa-upload"></i>
                            Upload
                        </button>
                    </div>
                </form>

                <!-- Loading Indicator -->
                <div id="profileLoading" class="hidden mt-4 text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#3C96E1]"></div>
                    <p class="text-sm text-gray-600 mt-2">Uploading...</p>
                </div>
            </div>
        </div>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal" class="fixed inset-0 hidden z-[60] h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
            <div class="relative bg-white p-6 sm:p-8 rounded-lg shadow-lg w-full max-w-md mx-auto modal-content logout-modal-content">
                <!-- Close Button -->
                <button onclick="closeLogoutModal()"
                    class="modal-close-btn absolute top-4 right-4 text-gray-500 hover:text-gray-700 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Warning Icon -->
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                </div>

                <!-- Modal Title -->
                <div class="text-center mb-2">
                    <h3 class="text-xl font-bold text-gray-800">Confirm Logout</h3>
                </div>

                <!-- Modal Message -->
                <div class="text-center mb-6">
                    <p class="text-gray-600">Are you sure you want to logout?</p>
                    <p class="text-sm text-gray-500 mt-1">You will need to log in again to access your account.</p>
                </div>

                <!-- Modal Buttons -->
                <div class="logout-modal-buttons">
                    <button type="button" onclick="closeLogoutModal()" class="logout-cancel-btn">
                        Cancel
                    </button>
                    <button type="button" id="confirmLogoutBtn" class="logout-confirm-btn">
                        Yes, Logout
                    </button>
                </div>
            </div>
        </div>

        <main class="container mx-auto mt-24"> <!-- Added mt-24 to account for the fixed header height -->
            <!-- Your main content here -->
        </main>

        <!-- Hidden refresh indicator -->
        <div id="refreshIndicator" class="refresh-indicator"></div>

        <script>
            // Global variable to store logout URL
            let logoutUrl = '';

            // Function to open profile picture modal
            function openProfileModal(userType) {
                const modal = document.getElementById("profileModal");
                const modalContent = modal.querySelector('.modal-content');
                const userTypeInput = document.getElementById('profileUserType');
                
                // Set user type for form submission
                userTypeInput.value = userType;
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
                
                // Set focus to upload button for accessibility
                setTimeout(() => {
                    document.querySelector('.profile-upload-btn').focus();
                }, 50);
            }

            // Function to close profile modal
            function closeProfileModal() {
                const modal = document.getElementById("profileModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                }, 300);
            }

            // Function to show logout confirmation modal
            function showLogoutModal(userType) {
                // Set the logout URL based on user type
                switch(userType) {
                    case 'admin':
                        logoutUrl = '../auth/logout.php';
                        break;
                    case 'staff':
                        logoutUrl = '/community-health-tracker/auth/logout.php';
                        break;
                    case 'user':
                        logoutUrl = '../auth/logout_user.php';
                        break;
                    default:
                        logoutUrl = '../auth/logout.php';
                }
                
                const modal = document.getElementById("logoutModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
                
                // Set focus to cancel button for accessibility
                setTimeout(() => {
                    document.querySelector('.logout-cancel-btn').focus();
                }, 50);
            }

            // Function to close logout modal
            function closeLogoutModal() {
                const modal = document.getElementById("logoutModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                }, 300);
            }

            // Handle logout confirmation
            document.getElementById('confirmLogoutBtn').addEventListener('click', function() {
                // Redirect to logout URL
                window.location.href = logoutUrl;
            });

            // Profile picture upload functionality
            document.addEventListener('DOMContentLoaded', function() {
                const profileUploadForm = document.getElementById('profileUploadForm');
                const profileImageInput = document.getElementById('profile_image');
                const profilePreview = document.getElementById('profilePreview');
                const profileUploadError = document.getElementById('profileUploadError');
                const profileLoading = document.getElementById('profileLoading');
                const removeProfileBtn = document.querySelector('.profile-remove-btn');

                // Preview image when file is selected
                profileImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        // Validate file size (2MB)
                        if (file.size > 2 * 1024 * 1024) {
                            profileUploadError.textContent = 'File size exceeds 2MB limit.';
                            profileUploadError.classList.remove('hidden');
                            this.value = '';
                            return;
                        }

                        // Validate file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            profileUploadError.textContent = 'Invalid file type. Please upload JPEG, PNG, or GIF images.';
                            profileUploadError.classList.remove('hidden');
                            this.value = '';
                            return;
                        }

                        // Clear any previous errors
                        profileUploadError.classList.add('hidden');

                        // Create preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profilePreview.src = e.target.result;
                        }
                        reader.readAsDataURL(file);
                    }
                });

                // Handle form submission
                profileUploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const file = profileImageInput.files[0];
                    
                    if (!file) {
                        profileUploadError.textContent = 'Please select a file to upload.';
                        profileUploadError.classList.remove('hidden');
                        return;
                    }

                    // Show loading indicator
                    profileLoading.classList.remove('hidden');
                    profileUploadForm.classList.add('opacity-50');
                    
                    // Submit via AJAX
                    fetch('/community-health-tracker/auth/upload_profile.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update profile picture in header
                            updateProfilePicture(data.profile_url);
                            
                            // Show success message
                            alert('Profile picture updated successfully!');
                            
                            // Close modal
                            closeProfileModal();
                        } else {
                            // Show error
                            profileUploadError.textContent = data.message || 'Upload failed. Please try again.';
                            profileUploadError.classList.remove('hidden');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        profileUploadError.textContent = 'An error occurred. Please try again.';
                        profileUploadError.classList.remove('hidden');
                    })
                    .finally(() => {
                        // Hide loading indicator
                        profileLoading.classList.add('hidden');
                        profileUploadForm.classList.remove('opacity-50');
                    });
                });

                // Remove profile picture
                window.removeProfilePicture = function() {
                    if (!confirm('Are you sure you want to remove your profile picture?')) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('user_id', document.querySelector('input[name="user_id"]').value);
                    formData.append('user_type', document.getElementById('profileUserType').value);
                    formData.append('remove', '1');

                    // Show loading indicator
                    profileLoading.classList.remove('hidden');
                    profileUploadForm.classList.add('opacity-50');
                    
                    fetch('/community-health-tracker/auth/upload_profile.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update profile picture in header
                            updateProfilePicture(null);
                            
                            // Reset preview to default
                            profilePreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEyIiBmaWxsPSIjZDFkNWRiIi8+PHBhdGggZD0iTTEyIDExYTIgMiAwIDEgMCAwLTQgMiAyIDAgMCAwIDAgNHoiIGZpbGw9IiM5Y2EzYWYiLz48cGF0aCBkPSJNMTIgMTVhNCA0IDAgMCAwLTQgNGg4YTQgNCAwIDAgMC00LTR6IiBmaWxsPSIjOWNhM2FmIi8+PC9zdmc+';
                            
                            // Disable remove button
                            removeProfileBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            removeProfileBtn.disabled = true;
                            
                            // Show success message
                            alert('Profile picture removed successfully!');
                        } else {
                            alert(data.message || 'Failed to remove profile picture.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    })
                    .finally(() => {
                        // Hide loading indicator
                        profileLoading.classList.add('hidden');
                        profileUploadForm.classList.remove('opacity-50');
                    });
                };

                // Function to update profile picture in header
                function updateProfilePicture(imageUrl) {
                    const profileAvatars = document.querySelectorAll('.profile-avatar');
                    profileAvatars.forEach(avatar => {
                        if (imageUrl) {
                            avatar.style.backgroundImage = `url('${imageUrl}')`;
                            avatar.classList.add('has-image');
                            // Remove the icon if it exists
                            const icon = avatar.querySelector('i');
                            if (icon) {
                                icon.remove();
                            }
                        } else {
                            avatar.style.backgroundImage = '';
                            avatar.classList.remove('has-image');
                            // Add the default icon
                            if (!avatar.querySelector('i')) {
                                const icon = document.createElement('i');
                                icon.className = 'fas fa-user-circle text-4xl text-white absolute inset-0 flex items-center justify-center';
                                avatar.appendChild(icon);
                            }
                        }
                    });
                }
            });

            // Close modal when clicking outside
            document.getElementById('profileModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProfileModal();
                }
            });

            document.getElementById('logoutModal')?.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeLogoutModal();
                }
            });

            // Close modals with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const profileModal = document.getElementById('profileModal');
                    const logoutModal = document.getElementById('logoutModal');
                    
                    if (!profileModal.classList.contains('hidden')) {
                        closeProfileModal();
                    } else if (!logoutModal.classList.contains('hidden')) {
                        closeLogoutModal();
                    }
                }
                
                // Handle Enter key on confirm button
                if (e.key === 'Enter' && document.activeElement.id === 'confirmLogoutBtn') {
                    document.getElementById('confirmLogoutBtn').click();
                }
            });

            // Function to update Philippine time in real-time
            function updatePhilippineTime() {
                const now = new Date();

                // Get the current time in the Philippines (UTC+8)
                // Since we're using the server's timezone setting (Asia/Manila),
                // we can use local time methods
                const hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';

                // Convert to 12-hour format
                let hours12 = hours % 12;
                hours12 = hours12 ? hours12 : 12; // Convert 0 to 12
                const hoursStr = hours12.toString().padStart(2, '0');

                // Format date
                const options = { month: 'short', day: 'numeric', year: 'numeric' };
                const dateStr = now.toLocaleDateString('en-US', options);

                // Update the elements for user
                if (document.getElementById('ph-date')) {
                    document.getElementById('ph-date').textContent = dateStr;
                    document.getElementById('ph-hours').textContent = hoursStr;
                    document.getElementById('ph-minutes').textContent = minutes;
                    document.getElementById('ph-seconds').textContent = seconds;
                    document.getElementById('ph-ampm').textContent = ampm;
                }

                // Update the elements for staff
                if (document.getElementById('staff-ph-date')) {
                    document.getElementById('staff-ph-date').textContent = dateStr;
                    document.getElementById('staff-ph-hours').textContent = hoursStr;
                    document.getElementById('staff-ph-minutes').textContent = minutes;
                    document.getElementById('staff-ph-seconds').textContent = seconds;
                    document.getElementById('staff-ph-ampm').textContent = ampm;
                }

                // Update the elements for admin
                if (document.getElementById('admin-ph-date')) {
                    document.getElementById('admin-ph-date').textContent = dateStr;
                    document.getElementById('admin-ph-hours').textContent = hoursStr;
                    document.getElementById('admin-ph-minutes').textContent = minutes;
                    document.getElementById('admin-ph-seconds').textContent = seconds;
                    document.getElementById('admin-ph-ampm').textContent = ampm;
                }

                // Update the hidden refresh indicator (for debugging/verification)
                document.getElementById('refreshIndicator').textContent = `Last refresh: ${now.toLocaleTimeString()}`;
            }

            // Update time immediately and then every second
            updatePhilippineTime();
            let timeInterval = setInterval(updatePhilippineTime, 1000);

            // Advanced time synchronization function
            function synchronizeTime() {
                const now = new Date();
                const milliseconds = now.getMilliseconds();

                // Calculate delay to sync with the next second change
                const delay = 1000 - milliseconds;

                // Clear existing interval
                clearInterval(timeInterval);

                // Set new interval that starts at the next second
                setTimeout(() => {
                    updatePhilippineTime();
                    timeInterval = setInterval(updatePhilippineTime, 1000);
                }, delay);
            }

            // Start synchronized timekeeping
            synchronizeTime();

            // Clean Navigation Tab Interaction
            document.addEventListener('DOMContentLoaded', function () {
                const navTabs = document.querySelectorAll('.nav-tab');

                navTabs.forEach(tab => {
                    tab.addEventListener('click', function (e) {
                        // Prevent default if it's not a link
                        if (this.getAttribute('href') === '#') {
                            e.preventDefault();
                        }

                        // Remove active class from all tabs
                        navTabs.forEach(t => t.classList.remove('active'));

                        // Add active class to clicked tab
                        this.classList.add('active');

                        // Store active state in sessionStorage
                        sessionStorage.setItem('activeNav', this.getAttribute('href'));
                    });
                });

                // Check if there's an active nav stored
                const activeNav = sessionStorage.getItem('activeNav');
                if (activeNav) {
                    const activeTab = document.querySelector(`.nav-tab[href="${activeNav}"]`);
                    if (activeTab) {
                        // Remove active class from all tabs first
                        navTabs.forEach(tab => tab.classList.remove('active'));
                        // Add active class to stored tab
                        activeTab.classList.add('active');
                    }
                }

                // Background time synchronization
                function backgroundTimeSync() {
                    // Check time accuracy every 30 seconds
                    setInterval(() => {
                        const now = new Date();
                        const expectedSeconds = (now.getSeconds() + 1) % 60;

                        // Schedule a check for the next second
                        setTimeout(() => {
                            const checkTime = new Date();
                            if (checkTime.getSeconds() !== expectedSeconds) {
                                // Time is out of sync, resynchronize
                                synchronizeTime();
                            }
                        }, 1000 - now.getMilliseconds());
                    }, 30000); // Check every 30 seconds
                }

                // Start background time synchronization
                backgroundTimeSync();

                // REGISTRATION FORM VALIDATION FUNCTIONALITY
                const firstRegisterForm = document.getElementById('firstRegisterForm');
                const secondRegisterForm = document.getElementById('secondRegisterForm');
                const openSecondRegister = document.getElementById('openSecondRegister');
                const backToFirstRegister = document.getElementById('backToFirstRegister');
                const registerFormModal = document.getElementById('registerFormModal');
                const secondRegisterFormModal = document.getElementById('secondRegisterFormModal');
                const password = document.getElementById('reg-password');
                const confirmPassword = document.getElementById('confirm_password');
                const passwordMatchError = document.getElementById('passwordMatchError');
                const submitButton = document.getElementById('submitButton');

                // Age auto-calculation functionality
                const dateOfBirthInput = document.getElementById('date_of_birth');
                const ageInput = document.getElementById('age');
                
                function calculateAge(birthDate) {
                    if (!birthDate) return '';
                    
                    const today = new Date();
                    const birthDateObj = new Date(birthDate);
                    
                    // Validate date
                    if (isNaN(birthDateObj.getTime())) {
                        return '';
                    }
                    
                    let age = today.getFullYear() - birthDateObj.getFullYear();
                    const monthDiff = today.getMonth() - birthDateObj.getMonth();
                    const dayDiff = today.getDate() - birthDateObj.getDate();
                    
                    // Adjust age if birthday hasn't occurred yet this year
                    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                        age--;
                    }
                    
                    // Return empty string for invalid ages (negative or too high)
                    if (age < 0 || age > 120) {
                        return '';
                    }
                    
                    return age;
                }
                
                // Enhanced first form validation function
                function validateFirstFormCompletion() {
                    const requiredFields = firstRegisterForm.querySelectorAll('[required]');
                    let allFilled = true;
                    
                    requiredFields.forEach(field => {
                        const value = field.value.trim();
                        
                        if (!value) {
                            allFilled = false;
                            return;
                        }
                        
                        // Special validation for specific fields
                        switch(field.id) {
                            case 'age':
                                const ageValue = parseInt(value);
                                if (isNaN(ageValue) || ageValue < 0 || ageValue > 120) {
                                    allFilled = false;
                                }
                                break;
                                
                            case 'contact':
                                // Validate phone number format (Philippine numbers)
                                const cleanedContact = value.replace(/\D/g, '');
                                if (cleanedContact.length < 10 || cleanedContact.length > 12) {
                                    allFilled = false;
                                }
                                break;
                                
                            case 'gender':
                            case 'sitio':
                            case 'civil_status':
                                // For select fields, check if a valid option is selected
                                if (value === '' || field.value === '') {
                                    allFilled = false;
                                }
                                break;
                                
                            case 'date_of_birth':
                                // Validate date is not in the future
                                const selectedDate = new Date(value);
                                const today = new Date();
                                if (selectedDate > today) {
                                    allFilled = false;
                                }
                                break;
                        }
                    });
                    
                    // Additional validation for address field
                    const addressField = document.getElementById('address');
                    if (!addressField.value.trim()) {
                        allFilled = false;
                    }
                    
                    openSecondRegister.disabled = !allFilled;
                    
                    // Update button styling based on state
                    if (allFilled) {
                        openSecondRegister.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
                        openSecondRegister.classList.add('hover:shadow-lg', 'active:scale-[0.98]');
                    } else {
                        openSecondRegister.classList.add('disabled:opacity-50', 'disabled:cursor-not-allowed');
                        openSecondRegister.classList.remove('hover:shadow-lg', 'active:scale-[0.98]');
                    }
                    
                    return allFilled;
                }
                
                // Setup form validation
                function setupFormValidation() {
                    // Add event listeners to first form fields
                    const firstFormFields = firstRegisterForm.querySelectorAll('input, select');
                    firstFormFields.forEach(field => {
                        field.addEventListener('input', function() {
                            // Re-calculate age if date of birth changes
                            if (field.id === 'date_of_birth') {
                                const birthDate = field.value;
                                const age = calculateAge(birthDate);
                                
                                if (age !== '' && age >= 0) {
                                    ageInput.value = age;
                                } else {
                                    ageInput.value = '';
                                }
                            }
                            
                            validateFirstFormCompletion();
                        });
                        
                        field.addEventListener('change', function() {
                            validateFirstFormCompletion();
                        });
                        
                        field.addEventListener('blur', function() {
                            validateFirstFormCompletion();
                        });
                    });
                    
                    // Initialize validation on page load
                    validateFirstFormCompletion();
                }
                
                // Call setup function
                setupFormValidation();
                
                // Go back to first form
                backToFirstRegister.addEventListener('click', function () {
                    secondRegisterFormModal.classList.add('hidden');
                    registerFormModal.classList.remove('hidden');

                    // Scroll to top of first form on mobile
                    if (window.innerWidth <= 768) {
                        window.scrollTo(0, 0);
                    }
                });

                // Enhanced password matching validation
                function validatePasswordMatch() {
                    const passwordValue = password.value.trim();
                    const confirmPasswordValue = confirmPassword.value.trim();
                    
                    if (passwordValue && confirmPasswordValue) {
                        if (passwordValue !== confirmPasswordValue) {
                            confirmPassword.classList.add('border-red-500');
                            passwordMatchError.classList.remove('hidden');
                            return false;
                        } else {
                            confirmPassword.classList.remove('border-red-500');
                            passwordMatchError.classList.add('hidden');
                            return true;
                        }
                    }
                    return true; // Return true if one or both are empty (will be caught by required validation)
                }

                // Enhanced second form completion check
                function checkSecondFormCompletion() {
                    const allRequiredFilled = Array.from(secondRegisterForm.querySelectorAll('[required]')).every(
                        field => {
                            const value = field.value.trim();
                            
                            // Skip validation for consent if manual verification is selected
                            if (field.name === 'verification_consent') {
                                const selectedMethod = document.querySelector('input[name="verification_method"]:checked');
                                if (selectedMethod && selectedMethod.value === 'manual_verification') {
                                    return true; // Skip consent requirement for manual verification
                                }
                            }
                            
                            return value !== '';
                        }
                    );

                    const passwordsMatch = validatePasswordMatch();
                    const verificationValid = validateVerificationSection();

                    submitButton.disabled = !allRequiredFilled || !passwordsMatch || !verificationValid;
                    
                    // Update button styling
                    if (!submitButton.disabled) {
                        submitButton.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
                    } else {
                        submitButton.classList.add('disabled:opacity-50', 'disabled:cursor-not-allowed');
                    }
                }

                // Update all event listeners for second form
                const secondFormFields = secondRegisterForm.querySelectorAll('input, select, textarea');
                secondFormFields.forEach(field => {
                    field.addEventListener('input', checkSecondFormCompletion);
                    field.addEventListener('change', checkSecondFormCompletion);
                    field.addEventListener('blur', checkSecondFormCompletion);
                });

                // Form submission validation
                if (secondRegisterForm) {
                    secondRegisterForm.addEventListener('submit', function (e) {
                        if (!validatePasswordMatch()) {
                            e.preventDefault();
                            return false;
                        }
                        return true;
                    });
                }

                // Handle mobile virtual keyboard issues
                if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    const inputs = document.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.addEventListener('focus', function () {
                            // Scroll the input into view with some padding
                            setTimeout(() => {
                                this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }, 300);
                        });
                    });
                }
            });

            // Page visibility API to optimize time updates
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    // Page is hidden, reduce update frequency to save resources
                    clearInterval(timeInterval);
                    timeInterval = setInterval(updatePhilippineTime, 5000); // Update every 5 seconds when tab is hidden
                } else {
                    // Page is visible, resume normal update frequency
                    clearInterval(timeInterval);
                    synchronizeTime(); // Resync time when returning to the tab
                }
            });

            // Enhanced Modal functions with smooth transitions
            function openModal() {
                const modal = document.getElementById("loginModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modal.classList.remove("hidden");
                modal.classList.add("flex");
                
                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.add('open');
                }, 10);
            }

            function closeModal() {
                const modal = document.getElementById("loginModal");
                const modalContent = modal.querySelector('.modal-content');
                
                modalContent.classList.remove('open');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    modal.classList.remove("flex");
                    modal.classList.add("hidden");
                    
                    // Reset to main modal view
                    document.getElementById('mainModal').classList.remove('hidden');
                    document.getElementById('loginFormModal').classList.add('hidden');
                    document.getElementById('registerFormModal').classList.add('hidden');
                    document.getElementById('secondRegisterFormModal').classList.add('hidden');
                }, 300);
            }

            const openLoginBtn = document.getElementById('openLogin');
            const openRegisterBtn = document.getElementById('openRegister');
            const mainModal = document.getElementById('mainModal');
            const loginFormModal = document.getElementById('loginFormModal');
            const registerFormModal = document.getElementById('registerFormModal');
            const secondRegisterFormModal = document.getElementById('secondRegisterFormModal');

            function switchModal(fromModal, toModal) {
                fromModal.classList.add('hidden');
                toModal.classList.remove('hidden');
                
                // Scroll to top on mobile
                if (window.innerWidth <= 768) {
                    window.scrollTo(0, 0);
                }
            }

            openLoginBtn.addEventListener('click', () => {
                switchModal(mainModal, loginFormModal);
            });

            openRegisterBtn.addEventListener('click', () => {
                switchModal(mainModal, registerFormModal);
            });

            // From Login modal → to Register modal
            document.getElementById('loginToRegister').addEventListener('click', function () {
                switchModal(loginFormModal, registerFormModal);
            });

            document.getElementById('registerToLogin').addEventListener('click', function () {
                switchModal(registerFormModal, loginFormModal);
            });

            document.getElementById('openSecondRegister').addEventListener('click', function (e) {
                e.preventDefault(); // Prevent form submission
                
                // Collect data from first form and set to hidden fields
                const firstFormData = new FormData(document.getElementById('firstRegisterForm'));
                
                // Set hidden field values
                document.getElementById('hidden_full_name').value = firstFormData.get('full_name');
                document.getElementById('hidden_date_of_birth').value = firstFormData.get('date_of_birth');
                document.getElementById('hidden_age').value = firstFormData.get('age');
                document.getElementById('hidden_gender').value = firstFormData.get('gender');
                document.getElementById('hidden_contact').value = firstFormData.get('contact');
                document.getElementById('hidden_sitio').value = firstFormData.get('sitio');
                document.getElementById('hidden_civil_status').value = firstFormData.get('civil_status');
                document.getElementById('hidden_occupation').value = firstFormData.get('occupation');
                
                switchModal(registerFormModal, secondRegisterFormModal);
            });

            // Back button from second registration form to first registration form
            document.getElementById('backToFirstRegister').addEventListener('click', function () {
                switchModal(secondRegisterFormModal, registerFormModal);
            });

            function toggleLoginPassword() {
                const input = document.getElementById("login-password");
                const icon = document.getElementById("login-eyeIcon");

                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("fa-eye");
                    icon.classList.add("fa-eye-slash");
                } else {
                    input.type = "password";
                    icon.classList.remove("fa-eye-slash");
                    icon.classList.add("fa-eye");
                }
            }

            // Close modal when clicking outside
            document.getElementById('loginModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        </script>

        <script>
            // ID Verification Method Toggle and Validation
document.addEventListener('DOMContentLoaded', function() {
    const verificationMethodRadios = document.querySelectorAll('input[name="verification_method"]');
    const idUploadSection = document.getElementById('idUploadSection');
    const manualVerificationSection = document.getElementById('manualVerificationSection');
    const idImageInput = document.getElementById('id_image');
    const filePreview = document.getElementById('filePreview');
    const previewImage = document.getElementById('previewImage');
    const verificationConsent = document.querySelector('input[name="verification_consent"]');
    const submitButton = document.getElementById('submitButton');

    // Validate verification section
    function validateVerificationSection() {
        const selectedMethod = document.querySelector('input[name="verification_method"]:checked');
        if (!selectedMethod) return true;

        let isValid = true;

        if (selectedMethod.value === 'id_upload') {
            const hasFile = idImageInput && idImageInput.files.length > 0;
            const hasConsent = verificationConsent && verificationConsent.checked;
            isValid = hasFile && hasConsent;
        }

        return isValid;
    }

    // Toggle verification sections based on selection
    verificationMethodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'id_upload') {
                idUploadSection.classList.remove('hidden');
                manualVerificationSection.classList.add('hidden');
                // Make consent required for ID upload
                if (verificationConsent) {
                    verificationConsent.required = true;
                }
            } else {
                idUploadSection.classList.add('hidden');
                manualVerificationSection.classList.remove('hidden');
                // Remove required for manual verification
                if (verificationConsent) {
                    verificationConsent.required = false;
                }
            }
            
            // Re-validate the entire form
            if (window.checkSecondFormCompletion) {
                checkSecondFormCompletion();
            }
        });
    });

    // File preview functionality
    if (idImageInput) {
        idImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        filePreview.classList.remove('hidden');
                    }
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    // Show PDF placeholder
                    previewImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTE0IDJINmEyIDIgMCAwIDAtMiAydjE2YTIgMiAwIDAgMCAyIDJoMTJhMiAyIDAgMCAwIDItMlY4eiIgc3Ryb2tlPSIjMzMzIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIvPgo8cGF0aCBkPSJNMTQgMnY2aDYiIHN0cm9rZT0iIzMzMyIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+';
                    previewImage.alt = 'PDF Document';
                    filePreview.classList.remove('hidden');
                } else {
                    filePreview.classList.add('hidden');
                }
            } else {
                filePreview.classList.add('hidden');
            }
            
            // Re-validate the entire form
            if (window.checkSecondFormCompletion) {
                checkSecondFormCompletion();
            }
        });
    }

    // Consent checkbox validation
    if (verificationConsent) {
        verificationConsent.addEventListener('change', function() {
            if (window.checkSecondFormCompletion) {
                checkSecondFormCompletion();
            }
        });
    }

    // Add validation for file size
    if (idImageInput) {
        idImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit. Please choose a smaller file.');
                this.value = '';
                filePreview.classList.add('hidden');
                if (window.checkSecondFormCompletion) {
                    checkSecondFormCompletion();
                }
            }
        });
    }

    // Form submission enhancement
    const secondRegisterForm = document.getElementById('secondRegisterForm');
    if (secondRegisterForm) {
        secondRegisterForm.addEventListener('submit', function(e) {
            // Additional validation before submission
            const selectedMethod = document.querySelector('input[name="verification_method"]:checked');
            
            if (selectedMethod && selectedMethod.value === 'id_upload') {
                if (!idImageInput.files.length) {
                    e.preventDefault();
                    alert('Please upload an ID document for verification.');
                    return false;
                }
                
                if (!verificationConsent.checked) {
                    e.preventDefault();
                    alert('You must consent to ID verification to proceed.');
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // Make validateVerificationSection function available globally
    window.validateVerificationSection = validateVerificationSection;
    
    // Also listen for changes in verification method
    const verificationMethodRadios2 = document.querySelectorAll('input[name="verification_method"]');
    verificationMethodRadios2.forEach(radio => {
        radio.addEventListener('change', function() {
            if (window.checkSecondFormCompletion) {
                checkSecondFormCompletion();
            }
        });
    });
});
        </script>
        
    </body>

</html>