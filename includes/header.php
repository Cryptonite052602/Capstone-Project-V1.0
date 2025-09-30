<?php
ob_start(); // Start output buffering


require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/header.php';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Get current page to determine active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/community-health-tracker/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="/community-health-tracker/assets/js/scripts.js" defer></script>
</head>
<style>
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
        0% { opacity: 1; }
        50% { opacity: 0; }
        100% { opacity: 1; }
    }
    
    .blinking-colon {
        animation: blink 1s infinite;
    }
    
    /* Navigation button styles */
    .nav-button {
        position: relative;
        transition: all 0.3s ease;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
    }
    
    .nav-button.active {
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    .nav-button.active::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        height: 3px;
        background-color: white;
        border-radius: 8px;
    }
    
    /* Connection line for navigation */
    .nav-connection {
        position: relative;
    }
    
    .nav-connection::before {
        content: '';
        position: absolute;
        top: 50%;
        left: -12px;
        width: 24px;
        height: 2px;
        background-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-50%);
        border-radius: 8px;
    }
    
    .nav-connection:first-child::before {
        display: none;
    }
    
    /* Curved connection for active states */
    .nav-button.active ~ .nav-button::before {
        background-color: white;
        height: 3px;
    }
    
    /* Hover effects */
    .nav-button:hover:not(.active) {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    /* Time display containers */
    .staff-time-container, .user-time-container {
        display: flex;
        align-items: center;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        margin-left: auto;
    }
    
    .staff-time-container {
        background-color: rgba(255, 255, 255, 0.15);
    }
    
    .user-time-container {
        background-color: rgba(255, 255, 255, 0.15);
    }
    
    /* Time zone indicator */
    .time-zone {
        font-size: 0.7rem;
        margin-left: 0.5rem;
        opacity: 0.8;
        font-style: italic;
    }
    
    /* Hidden refresh indicator */
    .refresh-indicator {
        position: absolute;
        width: 0;
        height: 0;
        overflow: hidden;
        opacity: 0;
    }
    
    /* Responsive adjustments */
    @media (max-width: 1024px) {
        .staff-nav-container, .user-nav-container {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .staff-time-container, .user-time-container {
            margin-left: 0;
            align-self: flex-end;
        }
    }
    
    @media (max-width: 768px) {
        .time-zone {
            display: none; /* Hide time zone on very small screens */
        }
    }
</style>

<body class="bg-gray-100">
    <?php if (isLoggedIn()): ?>
        <?php if (isAdmin()): ?>
            <!-- Admin Header -->
            <nav class="bg-[#3C96E1] text-white shadow-lg sticky top-0 z-50 h-[80px]">
  <div class="flex justify-between items-center h-full px-4">
    
    <!-- LEFT: Hamburger + Profile -->
    <div class="flex items-center space-x-4">
      <!-- Hamburger -->
      <button id="hamburger" 
        class="h-10 w-8 cursor-pointer hover:text-gray-200 transition-colors"
        onclick="toggleSidebar()">
        <svg xmlns="http://www.w3.org/2000/svg" 
             viewBox="0 0 24 24" fill="none" 
             stroke="currentColor" stroke-width="2" 
             class="w-6 h-6">
          <path d="M3 12h18M3 6h18M3 18h18" stroke-linecap="round" />
        </svg>
      </button>

      <!-- Profile -->
      <div class="flex items-center space-x-2 border-l border-white pl-4">
        <div class="bg-gray-300 h-8 w-8 rounded-full"></div>
        <div class="flex flex-col items-start">
          <span class="text-[#51E800] text-sm">Welcome Super Admin!</span>
          <span class="text-xs"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
        </div>
      </div>
    </div>
    
    <!-- CENTER: Search -->
    <div class="flex-1 flex items-center justify-center">
      <div class="relative w-full max-w-md">
        <input 
          type="search" 
          placeholder="Search"
          class="h-10 w-full rounded-3xl pl-7 pr-10 border border-gray-300 
                 focus:border-blue-500 focus:outline-none focus:ring-2 
                 focus:ring-blue-200 transition-colors text-black"
        >
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-5 w-5 absolute right-3 top-1/2 transform -translate-y-1/2 
                    text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" 
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
    </div>
    
    <!-- RIGHT: Logout -->
    <div class="flex items-center">
      <a href="/community-health-tracker/auth/logout.php" 
         class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">
        Logout
      </a>
    </div>
  </div>
</nav>

<!-- SIDEBAR -->
<div id="sidebar" 
     class="sidebar bg-white py-2 w-[90px] h-screen rounded-tr-lg rounded-br-lg 
            shadow-[4px_4px_8px_0px_rgba(0,0,0,0.1)] fixed transition-all duration-300 
            left-0 top-[80px] z-20 overflow-y-auto">
  <div class="container mx-auto px-4">
    <div class="ml-3 mt-7 space-y-[3rem]">
      <!-- Dashboard -->
      <div class="div">
                                <a href="/community-health-tracker/admin/dashboard.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="24.000000pt" height="24.000000pt"
                                        viewBox="0 0 24.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M24 186 c-3 -7 -4 -42 -2 -77 l3 -64 48 -3 c28 -2 47 1 47 7 0 6 -18 11 -40 11 l-40 0 0 60 c0 60 0 60 29 60 17 0 33 -4 36 -10 3 -5 26 -10 51 -10 32 0 44 -4 44 -15 0 -8 5 -15 11 -15 6 0 9 10 7 23 -2 18 -10 22 -48 25 -25 2 -49 7 -55 13 -14 14 -85 11 -91 -5z" />
                                            <path d="M168 82 c-32 -31 -37 -62 -10 -62 25 0 76 55 69 74 -9 23 -28 19 -59 -12z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>

                            <!-- Manage Accounts -->
                            <div class="div">
                                <a href="/community-health-tracker/admin/manage_accounts.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="24.000000pt" height="24.000000pt"
                                        viewBox="0 0 24.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M90 205 c-15 -18 -10 -45 13 -59 34 -22 73 27 47 59 -16 19 -44 19 -60 0z m46 -16 c10 -17 -13 -36 -27 -22 -12 12 -4 33 11 33 5 0 12 -5 16 -11z" />
                                            <path
                                                d="M63 118 c-19 -9 -23 -19 -23 -55 0 -23 5 -43 10 -43 6 0 10 18 10 40 0 37 2 39 35 46 20 3 39 4 42 0 9 -9 -19 -37 -33 -32 -17 7 -38 -20 -30 -39 4 -13 18 -15 63 -13 l58 3 3 41 c3 35 0 42 -23 52 -32 15 -82 14 -112 0z m117 -48 c0 -25 -4 -30 -25 -30 -25 0 -25 0 -10 30 9 17 20 30 25 30 6 0 10 -13 10 -30z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>

                            <!-- Patient Info -->
                            <div class="div">
                                <a href="/community-health-tracker/admin/patient_info.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="24.000000pt" height="24.000000pt"
                                        viewBox="0 0 24.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M165 197 c-3 -7 -5 -47 -3 -88 3 -66 5 -74 23 -74 18 0 20 7 20 85 0 73 -2 85 -18 88 -9 2 -20 -3 -22 -11z" />
                                            <path
                                                d="M100 90 c0 -53 2 -60 20 -60 18 0 20 7 20 60 0 53 -2 60 -20 60 -18 0 -20 -7 -20 -60z" />
                                            <path d="M34 65 c-4 -9 -2 -21 4 -27 15 -15 44 -1 40 19 -4 23 -36 29 -44 8z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>

                            <!-- Reports -->
                            <div class="div">
                                <a href="/community-health-tracker/admin/reports.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="24.000000pt" height="24.000000pt"
                                        viewBox="0 0 24.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M64 207 c-3 -8 -4 -45 -2 -83 l3 -69 70 0 70 0 3 53 c2 43 -1 58 -20 82 -20 24 -30 29 -71 29 -31 1 -49 -4 -53 -12z m62 -29 c4 -6 -3 -19 -16 -30 -28 -22 -30 -21 -23 15 5 28 26 36 39 15z m34 -46 c0 -5 -9 -17 -20 -27 -19 -18 -22 -18 -37 -2 -16 16 -16 17 4 17 12 0 25 5 28 10 8 12 25 13 25 2z" />
                                            <path d="M30 115 c0 -88 7 -95 94 -95 77 0 71 18 -6 22 l-63 3 -3 73 c-4 95 -22 93 -22 -3z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>

                            <!-- Appointments -->
                            <div class="div">
                                <a href="/community-health-tracker/admin/appointments.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="22.000000pt" height="24.000000pt"
                                        viewBox="0 0 22.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M32 207 c-19 -20 -22 -35 -22 -98 0 -45 5 -80 12 -87 16 -16 160 -16 176 0 7 7 12 42 12 88 0 69 -2 78 -25 99 -14 12 -31 18 -37 15 -16 -10 -69 -11 -75 -2 -7 13 -19 9 -41 -15z m151 -36 c8 -8 -8 -11 -65 -11 -74 0 -112 13 -66 23 37 8 118 1 131 -12z m7 -67 c0 -62 -13 -74 -78 -74 -32 0 -63 5 -70 12 -7 7 -12 31 -12 55 l0 43 80 0 80 0 0 -36z" />
                                            <path
                                                d="M120 105 c-7 -9 -21 -13 -31 -10 -24 8 -26 -17 -4 -35 13 -11 21 -8 45 15 17 16 30 32 30 37 0 14 -27 9 -40 -7z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>

                            <!-- Schedules -->
                            <div class="div">
                                <a href="/community-health-tracker/admin/staff_schedules.php">
                                    <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="24.000000pt" height="24.000000pt"
                                        viewBox="0 0 24.000000 24.000000" preserveAspectRatio="xMidYMid meet">
                                        <g transform="translate(0.000000,24.000000) scale(0.100000,-0.100000)" fill="#FC566C"
                                            stroke="none">
                                            <path
                                                d="M70 201 c0 -5 -9 -11 -20 -14 -19 -5 -21 -12 -18 -79 l3 -73 85 0 85 0 3 73 c2 62 0 73 -15 79 -10 4 -22 11 -26 17 -6 8 -10 7 -14 -1 -6 -15 -63 -18 -63 -3 0 6 -4 10 -10 10 -5 0 -10 -4 -10 -9z m100 -59 c0 -5 -14 -21 -30 -37 -24 -23 -34 -27 -47 -19 -26 16 -29 38 -5 32 13 -4 27 1 38 13 17 19 44 26 44 11z m10 -62 c0 -13 -7 -20 -20 -20 -23 0 -25 5 -7 25 17 20 27 18 27 -5z" />
                                        </g>
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- LOGOUT CONTENT -->
                        <div class="ml-3.5 mt-[8rem]">
                            <a href="/community-health-tracker/auth/logout.php">
                                <svg version="1.0" xmlns="http://www.w3.org/2000/svg" width="22.000000pt" height="22.000000pt"
                                    viewBox="0 0 22.000000 22.000000" preserveAspectRatio="xMidYMid meet">
                                    <g transform="translate(0.000000,22.000000) scale(0.100000,-0.100000)" fill="#000000"
                                        stroke="none">
                                        <path
                                            d="M46 188 c-41 -34 -47 -104 -14 -142 35 -42 104 -47 144 -12 31 27 14 37 -20 11 -49 -37 -126 3 -126 65 0 62 77 102 126 65 31 -23 52 -19 27 6 -12 13 -39 23 -65 26 -37 4 -49 1 -72 -19z" />
                                        <path
                                            d="M180 132 c0 -7 -15 -12 -40 -12 -22 0 -40 -4 -40 -10 0 -5 18 -10 40 -10 25 0 40 -5 40 -12 0 -8 6 -6 17 5 16 16 16 18 0 34 -11 11 -17 13 -17 5z" />
                                    </g>
                                </svg>
                            </a>
                        </div>
                    </div>
</div>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    if (sidebar.classList.contains("-ml-[90px]")) {
      sidebar.classList.remove("-ml-[90px]");
    } else {
      sidebar.classList.add("-ml-[90px]");
    }
  }
</script>


        <?php elseif (isStaff()): ?>
            <!-- Staff Header -->
            <nav class="bg-red-600 text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 6v12M6 12h12" stroke-linecap="round"/>
                                <path d="M3 12h2l2 4 3-8 3 8 2-4h2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <a href="/community-health-tracker/" class="text-2xl font-bold">STAFF ADMIN</a>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <span class="font-medium">Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        <a href="/community-health-tracker/auth/logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">Logout</a>
                    </div>
                </div>

                <div class="bg-red-700 py-2">
                    <div class="container mx-auto px-4 flex items-center justify-between staff-nav-container">
                        <ul class="flex space-x-6">
                            <li class="nav-connection">
                                <a href="/community-health-tracker/staff/dashboard.php" 
                                   class="nav-button <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </li>
                            <li class="nav-connection">
                                <a href="/community-health-tracker/staff/existing_info_patients.php" 
                                   class="nav-button <?= ($current_page == 'existing_info_patients.php') ? 'active' : '' ?>">
                                    Medical Records
                                </a>
                            </li>
                            <li class="nav-connection">
                                <a href="/community-health-tracker/staff/announcements.php" 
                                   class="nav-button <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Real-time Philippine Date and Time for Staff - Right side -->
                        <div class="staff-time-container">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-red-200"></i>
                                <span id="staff-ph-date" class="font-medium text-sm">
                                    <?php echo date('M j, Y'); ?>
                                </span>
                            </div>
                            <div class="h-5 w-px bg-red-400 mx-3"></div>
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2 text-red-200"></i>
                                <span id="staff-ph-time" class="font-mono font-bold tracking-wide text-sm">
                                    <span id="staff-ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="staff-ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="staff-ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                </span>
                                <span class="time-zone">PHT</span>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

        <?php elseif (isUser()): ?>
            <!-- User Header -->
            <nav class="bg-purple-600 text-white shadow-lg sticky top-0 z-50">
                <div class="container mx-auto px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M12 6v12M6 12h12" stroke-linecap="round" />
                                <path d="M3 12h2l2 4 3-8 3 8 2-4h2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <a href="/community-health-tracker/" class="text-2xl font-bold">USER RESIDENT</a>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Real-time Philippine Date and Time - Horizontal Layout -->
                        <div class="user-time-container">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-day mr-2 text-purple-200"></i>
                                <span id="ph-date" class="font-medium text-sm">
                                    <?php echo date('M j, Y'); ?>
                                </span>
                            </div>
                            <div class="h-5 w-px bg-purple-400 mx-3"></div>
                            <div class="flex items-center">
                                <i class="fas fa-clock mr-2 text-purple-200"></i>
                                <span id="ph-time" class="font-mono font-bold tracking-wide text-sm">
                                    <span id="ph-hours"><?php echo date('h'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-minutes"><?php echo date('i'); ?></span>
                                    <span class="blinking-colon">:</span>
                                    <span id="ph-seconds"><?php echo date('s'); ?></span>
                                    <span id="ph-ampm" class="ml-1"><?php echo date('A'); ?></span>
                                </span>
                                <span class="time-zone">PHT</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span class="font-medium"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                        </div>
                        <a href="/community-health-tracker/auth/logout.php"
                            class="bg-purple-500 hover:bg-purple-700 px-4 py-2 rounded-lg transition flex items-center space-x-2">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <div class="bg-purple-700 py-2">
                    <div class="container mx-auto px-4 flex items-center justify-between user-nav-container">
                        <ul class="flex space-x-6">
                            <li class="nav-connection">
                                <a href="/community-health-tracker/user/dashboard.php"
                                   class="nav-button <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                                    Dashboard
                                </a>
                            </li>
                            <li class="nav-connection">
                                <a href="/community-health-tracker/user/health_records.php"
                                   class="nav-button <?= ($current_page == 'health_records.php') ? 'active' : '' ?>">
                                    My Record
                                </a>
                            </li>
                            <li class="nav-connection">
                                <a href="/community-health-tracker/user/announcements.php"
                                   class="nav-button <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                                    Announcements
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Additional space for balance - could add other elements here if needed -->
                        <div class="opacity-0 pointer-events-none">
                            <!-- Invisible spacer for layout balance -->
                        </div>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    <?php else: ?>

        <script>
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
            
            // Update the hidden refresh indicator (for debugging/verification)
            document.getElementById('refreshIndicator').textContent = `Last refresh: ${now.toLocaleTimeString()}`;
        }
        
        // Update time immediately and then every second
        updatePhilippineTime();
        const timeInterval = setInterval(updatePhilippineTime, 1000);
        
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
                setInterval(updatePhilippineTime, 1000);
            }, delay);
        }
        
        // Start synchronized timekeeping
        synchronizeTime();
        
        // Navigation button interaction
        document.addEventListener('DOMContentLoaded', function() {
            const navButtons = document.querySelectorAll('.nav-button');
            
            navButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    navButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Store active state in sessionStorage
                    sessionStorage.setItem('activeNav', this.getAttribute('href'));
                });
            });
            
            // Check if there's an active nav stored
            const activeNav = sessionStorage.getItem('activeNav');
            if (activeNav) {
                const activeButton = document.querySelector(`.nav-button[href="${activeNav}"]`);
                if (activeButton) {
                    // Remove active class from all buttons first
                    navButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to stored button
                    activeButton.classList.add('active');
                }
            }
            
            // Background time synchronization (hidden from user)
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
        });
        
        // Page visibility API to optimize time updates
        document.addEventListener('visibilitychange', function() {
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
        
        // ... (rest of your existing JavaScript code) ...
    </script>
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
    <nav class="bg-white text-black shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 nav-container flex justify-between items-center">
            <!-- Logo/Title with two-line text -->
            <div class="flex items-center">
                <img src="asssets/images/Barangay Toong.jpg" alt="Barangay Toong Logo" class="circle-image mr-4">
                <div class="logo-text">
                    <div class="font-bold text-xl leading-tight">Barangay Toong</div>
                    <div class="text-lg text-gray-700">Monitoring and Tracking</div>
                </div>
            </div>

            <!-- Mobile menu button - hidden on desktop -->
            <button class="md:hidden touch-target p-2" onclick="toggleMobileMenu()">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Desktop navigation - centered nav list -->
            <div class="hidden md:flex items-center flex-1 justify-center">
                <!-- Centered nav links -->
                <ul class="flex items-center space-x-8 font-semibold">
                    <li>
                        <a href="#"
                            class="nav-link text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Home</a>
                    </li>
                    <li>
                        <a href="#"
                            class="nav-link text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">About</a>
                    </li>
                    <li>
                        <a href="#"
                            class="nav-link text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Services</a>
                    </li>
                    <li>
                        <a href="#"
                            class="nav-link text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Contact</a>
                    </li>
                </ul>
            </div>

            <!-- Book Appointment button - positioned to the right -->
            <div class="hidden md:flex items-center">
                <a href="#" onclick="openModal()"
                    class="bg-[#FC566C] text-white hover:bg-[#f1233f] px-6 py-2 rounded-lg transition flex items-center gap-2 nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
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
                   class="block bg-[#FC566C] text-white hover:bg-[#f1233f] px-4 py-3 rounded-lg transition text-center mx-1 mt-2 flex items-center justify-center gap-2 nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
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
    document.addEventListener('click', function(event) {
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
                <div id="loginModal"
                    class="fixed inset-0 hidden z-50 h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center">
                    <div class="relative bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl mx-auto h-[650px] mt-[5px]">

                        <!-- Close Icon (X) -->
                        <button onclick="closeModal()"
                            class="absolute top-5 right-6 text-white text-bold bg-black rounded-full p-2 hover:bg-gray-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <div id="mainModal">
                            <div class="flex mx-3 my-10 h-[50px]">
                                <img src="./asssets/images/check-icon.png" alt="check-icon" class="h-15 w-15">
                                <p class="text-[15px] text-justify font-medium text-center justify-center">To access
                                    records
                                    and appointments, please log in with your authorized account or register for a new
                                    account to securely continue using the system today online.</p>
                            </div>

                            <div class="flex text-white gap-4 mx-4 h-[60px] text-center justify-center text-lg">
                                <button id="openLogin"
                                    class="bg-[#FC566C] w-[300px] h-[60px] rounded flex items-center justify-center hover:bg-[#f1233f]">
                                    Login
                                </button>

                                <button id="openRegister"
                                    class="bg-[#FC566C] w-[300px] h-[60px] rounded flex items-center justify-center hover:bg-[#f1233f]">
                                    Register
                                </button>
                            </div>

                            <div class="m-4 h-[390px]">
                                <img src="./asssets/images/healthcare.png" alt="" class="w-full h-full object-cover">
                            </div>
                        </div>


                        <!-- New Login Form Modal -->
                        <div id="loginFormModal" class="hidden animate__animated animate__fadeInRight px-4">
                            <div class="items-center text-center mt-10">
                                <h2 class="text-[25px] text-[#FC566C] font-semibold">Access Your Account</h2>
                                <p>Sign in to your resident account</p>
                            </div>

                            <form method="POST" action="/community-health-tracker/auth/login.php" class="space-y-4">

                            <input type="hidden" name="role" value="user">
                                <div class="my-10 mx-auto w-full max-w-md">
                                    <!-- Username -->
                                    <div class="mb-4">
                                        <label class="block text-left" for="">Username <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" name="username" id="username" placeholder="Enter Username" class="mt-2 w-full p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                    </div>

                                    <!-- Password -->
                                    <div class="mb-4">
                                        <label class="block text-left" for="password">Password <span
                                                class="text-red-500">*</span></label>
                                        <div class="relative mt-2">
                                            <input id="password" name="password" type="password" placeholder="Password" class="w-full p-4 pr-10 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                            <button type="button" onclick="togglePassword()"
                                                class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                                                <i id="eyeIcon" class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Forgot Password -->
                                    <div class="mt-3 font-medium text-left text-md">
                                        <a href="#" class="text-black hover:underline">Forgot your password?</a>
                                    </div>

                                    <!-- Login Button -->
                                    <div class="mt-6">
                                        <button type="submit"
                                            class="bg-[#FC566C] w-full p-3 rounded text-white hover:bg-[#f1233f]">Login</button>
                                    </div>

                                    <!-- Register Link -->
                                    <div class="flex justify-center mt-5 text-md font-semibold space-x-1">
                                        <p>Don't have an account?</p>
                                        <button id="loginToRegister" type="button"
                                            class="text-[#FC566C] hover:underline">Register</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                       <!-- First Registration Modal -->
<div id="registerFormModal" class="hidden animate__animated animate__fadeInRight">
    <div class="items-center text-center pt-4 md:pt-6 pb-2 md:pb-4 px-4">
        <h2 class="text-xl md:text-[25px] font-bold text-[#FC566C]">Register Your Account</h2>
        <p class="text-sm md:text-base mt-1">Sign up to your resident account</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="mx-auto w-full max-w-lg bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm md:text-base" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="mx-auto w-full max-w-lg bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-sm md:text-base" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>
    
    <form id="firstRegisterForm" class="space-y-4">
        <div class="mx-auto w-full max-w-lg px-4 md:px-6 pb-4">
            <div class="mb-4">
                <label for="full_name" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" id="full_name" name="full_name" placeholder="Full Name" 
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                    class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="age" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Age <span class="text-red-500">*</span></label>
                    <input type="number" id="age" name="age" placeholder="Age" min="1" max="120"
                        value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
                </div>

                <div>
                    <label for="gender" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                    <select id="gender" name="gender" 
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label for="contact" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Contact Number <span class="text-red-500">*</span></label>
                    <input type="tel" id="contact" name="contact" placeholder="Contact Number"
                        value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
                </div>
            </div>

            <div class="mb-6">
                <label for="address" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Address<span class="text-red-500">*</span></label>
                <input type="text" id="address" name="address" placeholder="Address"
                    value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>"
                    class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
            </div>

            <button type="button" id="openSecondRegister"
                class="bg-[#FC566C] w-full p-3 rounded-md text-white hover:bg-[#f1233f] flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed text-sm md:text-base font-medium transition-colors duration-200"
                disabled>
                Continue
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <div class="flex flex-col md:flex-row justify-center mt-4 text-sm md:text-base font-medium space-y-1 md:space-y-0 md:space-x-1">
                <p>Already have an account?</p>
                <button id="registerToLogin" type="button"
                    class="text-[#FC566C] hover:underline">Login</button>
            </div>
        </div>
    </form>
</div>

<!-- Second Registration Modal -->
<div id="secondRegisterFormModal" class="hidden animate__animated animate__fadeInRight">
    <button class="h-8 w-8 rounded-full mt-4 ml-4 flex items-center justify-center hover:bg-gray-100 transition-colors" id="backToFirstRegister" aria-label="Back">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-[#FC566C]">
            <path d="M15.75 19.5L8.25 12l7.5-7.5v15z" />
        </svg>
    </button>

    <div class="items-center text-center pt-2 md:pt-4 pb-2 md:pb-4 px-4">
        <h2 class="text-xl md:text-2xl font-bold text-[#FC566C]">Complete Your Registration</h2>
        <p class="text-gray-600 mt-1 text-sm md:text-base">Add your account credentials</p>
    </div>

    <form method="POST" action="/community-health-tracker/auth/register.php" class="px-4 pb-6" id="secondRegisterForm">
        <div class="mx-auto w-full max-w-md space-y-4">
            <!-- Hidden fields to pass data from first form -->
            <input type="hidden" name="full_name" id="hidden_full_name" value="">
            <input type="hidden" name="age" id="hidden_age" value="">
            <input type="hidden" name="gender" id="hidden_gender" value="">
            <input type="hidden" name="contact" id="hidden_contact" value="">
            <input type="hidden" name="address" id="hidden_address" value="">
            
            <div class="space-y-4">
                <div>
                    <label for="username" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                    <input type="text" id="username" name="username" placeholder="Username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
                </div>
                
                <div>
                    <label for="email" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" placeholder="Email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" required />
                </div>

                <div>
                    <label for="password" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Password (min 8 characters)"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" 
                        minlength="8" required />
                    <p class="text-xs text-gray-500 mt-1">Password must be at least 8 characters</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm md:text-base font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password"
                        class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-[#3C96E1] text-sm md:text-base" 
                        minlength="8" required />
                    <p id="passwordMatchError" class="text-xs text-red-500 mt-1 hidden">Passwords do not match</p>
                </div>
            </div>

            <button type="submit" id="submitButton"
                class="bg-[#FC566C] w-full p-3 rounded-md mt-2 text-white hover:bg-[#f1233f] transition-colors duration-200 font-medium disabled:opacity-50 disabled:cursor-not-allowed text-sm md:text-base">
                Complete Registration
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form elements
    const firstRegisterForm = document.getElementById('firstRegisterForm');
    const secondRegisterForm = document.getElementById('secondRegisterForm');
    const openSecondRegister = document.getElementById('openSecondRegister');
    const backToFirstRegister = document.getElementById('backToFirstRegister');
    const registerFormModal = document.getElementById('registerFormModal');
    const secondRegisterFormModal = document.getElementById('secondRegisterFormModal');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatchError = document.getElementById('passwordMatchError');
    const submitButton = document.getElementById('submitButton');

    // Get all required fields from first form
    const firstFormRequiredFields = firstRegisterForm.querySelectorAll('[required]');
    
    // Function to check if all required fields in first form are filled
    function checkFirstFormCompletion() {
        let allFilled = true;
        
        firstFormRequiredFields.forEach(field => {
            if (!field.value.trim()) {
                allFilled = false;
            }
        });
        
        openSecondRegister.disabled = !allFilled;
    }
    
    // Add event listeners to first form fields
    firstFormRequiredFields.forEach(field => {
        field.addEventListener('input', checkFirstFormCompletion);
        field.addEventListener('change', checkFirstFormCompletion);
    });
    
    // Initial check
    checkFirstFormCompletion();

    // Store form data when moving to second form
    openSecondRegister.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Transfer data to hidden fields in second form
        document.getElementById('hidden_full_name').value = document.getElementById('full_name').value;
        document.getElementById('hidden_age').value = document.getElementById('age').value;
        document.getElementById('hidden_gender').value = document.getElementById('gender').value;
        document.getElementById('hidden_contact').value = document.getElementById('contact').value;
        document.getElementById('hidden_address').value = document.getElementById('address').value;
        
        // Hide first modal, show second modal
        registerFormModal.classList.add('hidden');
        secondRegisterFormModal.classList.remove('hidden');
        
        // Scroll to top of second form on mobile
        if (window.innerWidth <= 768) {
            window.scrollTo(0, 0);
        }
    });
    
    // Go back to first form
    backToFirstRegister.addEventListener('click', function() {
        secondRegisterFormModal.classList.add('hidden');
        registerFormModal.classList.remove('hidden');
        
        // Scroll to top of first form on mobile
        if (window.innerWidth <= 768) {
            window.scrollTo(0, 0);
        }
    });
    
    // Password matching validation
    function validatePasswordMatch() {
        if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
            confirmPassword.classList.add('border-red-500');
            passwordMatchError.classList.remove('hidden');
            submitButton.disabled = true;
            return false;
        } else {
            confirmPassword.classList.remove('border-red-500');
            passwordMatchError.classList.add('hidden');
            
            // Only enable if all required fields are filled
            const allFilled = Array.from(secondRegisterForm.querySelectorAll('[required]')).every(
                field => field.value.trim()
            );
            
            submitButton.disabled = !allFilled;
            return true;
        }
    }

    // Check if all required fields in second form are filled
    function checkSecondFormCompletion() {
        const allFilled = Array.from(secondRegisterForm.querySelectorAll('[required]')).every(
            field => field.value.trim()
        );
        
        // Only enable if passwords match (if both password fields have values)
        const passwordsMatch = !password.value || !confirmPassword.value || password.value === confirmPassword.value;
        
        submitButton.disabled = !allFilled || !passwordsMatch;
    }
    
    // Add event listeners to second form fields
    const secondFormFields = secondRegisterForm.querySelectorAll('input');
    secondFormFields.forEach(field => {
        field.addEventListener('input', function() {
            if (field.id === 'password' || field.id === 'confirm_password') {
                validatePasswordMatch();
            } else {
                checkSecondFormCompletion();
            }
        });
        
        field.addEventListener('change', function() {
            if (field.id === 'password' || field.id === 'confirm_password') {
                validatePasswordMatch();
            } else {
                checkSecondFormCompletion();
            }
        });
    });

    // Initial check for second form
    checkSecondFormCompletion();

    // Form submission validation
    if (secondRegisterForm) {
        secondRegisterForm.addEventListener('submit', function(e) {
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
            input.addEventListener('focus', function() {
                // Scroll the input into view with some padding
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
    }
});
</script>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
    </div>

    <main class="container mx-auto mt-24"> <!-- Added mt-24 to account for the fixed header height -->
        <!-- Your main content here -->
    </main>

    <script>
        function openModal() {
            document.getElementById("loginModal").classList.remove("hidden");
            document.getElementById("loginModal").classList.add("flex");
        }

        function closeModal() {
            document.getElementById("loginModal").classList.remove("flex");
            document.getElementById("loginModal").classList.add("hidden");
        }
        
        const openLoginBtn = document.getElementById('openLogin');
        const openRegisterBtn = document.getElementById('openRegister');
        const mainModal = document.getElementById('mainModal');
        const loginFormModal = document.getElementById('loginFormModal');
        const registerFormModal = document.getElementById('registerFormModal');
        const secondRegisterFormModal = document.getElementById('secondRegisterFormModal');

        openLoginBtn.addEventListener('click', () => {
            mainModal.classList.add('hidden');
            loginFormModal.classList.remove('hidden');
        });

        openRegisterBtn.addEventListener('click', () => {
            mainModal.classList.add('hidden');
            registerFormModal.classList.remove('hidden');
        });
        
        // From Login modal → to Register modal
        document.getElementById('loginToRegister').addEventListener('click', function () {
            document.getElementById('loginFormModal').classList.add('hidden');
            document.getElementById('registerFormModal').classList.remove('hidden');
        });
        
        document.getElementById('registerToLogin').addEventListener('click', function () {
            document.getElementById('registerFormModal').classList.add('hidden');
            document.getElementById('loginFormModal').classList.remove('hidden');
        });
        
        document.getElementById('openSecondRegister').addEventListener('click', function (e) {
            e.preventDefault(); // Prevent form submission
            registerFormModal.classList.add('hidden');
            secondRegisterFormModal.classList.remove('hidden');
        });

        // Back button from second registration form to first registration form
        document.getElementById('backToFirstRegister').addEventListener('click', function () {
            secondRegisterFormModal.classList.add('hidden');
            registerFormModal.classList.remove('hidden');
        });

        // Also update the registerToLogin button to hide the second form if it's visible
        document.getElementById('registerToLogin').addEventListener('click', function () {
            registerFormModal.classList.add('hidden');
            secondRegisterFormModal.classList.add('hidden');
            loginFormModal.classList.remove('hidden');
        });
        
        function backToMainModal() {
            mainModal.classList.remove('hidden');
            loginFormModal.classList.add('hidden');
            registerFormModal.classList.add('hidden');
        }
        
        function togglePassword() {
            const input = document.getElementById("password");
            const icon = document.getElementById("eyeIcon");

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
        
        document.getElementById('backToFirstRegister').addEventListener('click', function () {
            secondRegisterFormModal.classList.add('hidden');
            registerFormModal.classList.remove('hidden');
        });
        
        // JavaScript function to toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            // Toggle sidebar visibility
            sidebar.classList.toggle('sidebar-hidden');
            
            // Toggle overlay for mobile (optional)
            if (overlay) {
                overlay.classList.toggle('overlay-hidden');
            }
        }

        // Optional: Close sidebar when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('overlay');
                
                // Hide sidebar if it's visible
                if (!sidebar.classList.contains('sidebar-hidden')) {
                    sidebar.classList.add('sidebar-hidden');
                    if (overlay) {
                        overlay.classList.add('overlay-hidden');
                    }
                }
            }
        });

        // Optional: Handle window resize to show/hide sidebar appropriately
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            // On larger screens, ensure sidebar is visible
            if (window.innerWidth >= 1024) { // lg breakpoint
                sidebar.classList.remove('sidebar-hidden');
                if (overlay) {
                    overlay.classList.add('overlay-hidden');
                }
            }
        });
    </script>
</body>
</html>

<!-- What is Lorem Ipsum?
Lorem Ipsum is simply dummy text of the printing and typesetting industry. -->