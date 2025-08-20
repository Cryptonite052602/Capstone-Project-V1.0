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
</style>

<body class="bg-gray-100">
    
    <?php if (isAdmin()): ?>
    <!-- Admin Header -->
    <nav class="bg-[#3C96E1] text-white shadow-lg sticky h-[80px] top-0 z-50">
        <div class="flex items-center w-full sm:w-auto">
            <div class="flex items-center space-x-4 ml-2">
                <!-- Hamburger Menu Button -->
                <button id="hamburger" class="h-10 w-8 mt-5 ml-0 sm:ml-3 cursor-pointer hover:text-gray-200 transition-colors" onclick="toggleSidebar()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" class="w-6 h-6">
                        <path d="M3 12h18M3 6h18M3 18h18" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <!-- Profile section - shown on mobile/desktop-->
            <div class="flex items-center space-x-2 mt-5 mr-5 ml-2 border-r border-white pr-6">
                <div class="bg-gray-300 h-8 w-8 rounded-full"></div>
                <div class="flex flex-col items-start">
                    <span class="text-[#51E800] text-sm">Welcome Admin!</span>
                    <span class="text-xs"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                </div>
            </div>

            <!-- Search bar - Responsive for mobile and desktop -->
            <div class="relative flex-1 top-2 mr-5 sm:mt-2 sm:mx-6 sm:max-w-md lg:max-w-lg">
                <input class="h-10 w-full rounded-3xl pl-7 pr-10 border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 transition-colors" type="search" placeholder="Search">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 cursor-pointer transition-colors" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            
            <!-- Logout Button -->
            <div class="flex items-center">
                <a href="/community-health-tracker/auth/logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">Logout</a>
            </div>
        </div>

        <!-- SIDE BAR CONTENT -->
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar bg-white py-2 w-[90px] h-[40rem] rounded-tr-lg mt-7 rounded-br-lg shadow-[4px_4px_8px_0px_rgba(0,0,0,0.1)] fixed lg:relative z-20">
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
    </nav>

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
                <a href="/community-health-tracker/" class="text-2xl font-bold">CHM Staff</a>
            </div>
            
            <div class="flex items-center space-x-4">
                <span class="font-medium">Welcome, <?= htmlspecialchars($_SESSION['user']['full_name']) ?></span>
                <a href="/community-health-tracker/auth/logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition">Logout</a>
            </div>
        </div>

        <div class="bg-red-700 py-2">
            <div class="container mx-auto px-4">
                <ul class="flex space-x-6">
                    <li><a href="/community-health-tracker/staff/dashboard.php" class="hover:bg-red-800 px-3 py-1 rounded">Dashboard</a></li>
                    <li><a href="/community-health-tracker/staff/manage_accounts.php" class="hover:bg-red-800 px-3 py-1 rounded">Manage Accounts</a></li>
                    <li><a href="/community-health-tracker/staff/existing_info_patients.php" class="hover:bg-red-800 px-3 py-1 rounded">Progress Tracker</a></li>
                    <li><a href="/community-health-tracker/staff/announcements.php" class="hover:bg-red-800 px-3 py-1 rounded">Announcements</a></li>
                    <li><a href="/community-health-tracker/staff/appointments.php" class="hover:bg-red-800 px-3 py-1 rounded">Appointments</a></li>
                </ul>
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
                <a href="/community-health-tracker/" class="text-2xl font-bold">CHM Portal</a>
            </div>

            <div class="flex items-center space-x-4">
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
            <div class="container mx-auto px-4">
                <ul class="flex space-x-6">
                    <li><a href="/community-health-tracker/user/dashboard.php"
                            class="hover:bg-purple-800 px-3 py-1 rounded">Dashboard</a></li>
                    <li><a href="/community-health-tracker/user/appointments.php"
                            class="hover:bg-purple-800 px-3 py-1 rounded">Appointments</a></li>
                    <li><a href="/community-health-tracker/user/health_records.php"
                            class="hover:bg-purple-800 px-3 py-1 rounded">My Record</a></li>
                    <li><a href="/community-health-tracker/user/announcements.php"
                            class="hover:bg-purple-800 px-3 py-1 rounded">Announcements</a></li>
                </ul>
            </div>
        </div>
    </nav>

<?php else: ?>
    <!-- Public Header (Not logged in) -->
    <nav class="bg-white text-black shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-5 flex justify-between items-center">
            <div class="flex justify-between items-center px-4">
                <div class="h-8 w-8">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M12 6v12M6 12h12" stroke-linecap="round" />
                        <path d="M3 12h2l2 4 3-8 3 8 2-4h2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <a href="/community-health-tracker/" class="text-2xl font-bold">CHTMS</a>
            </div>

            <!-- Center/Right side: nav links -->
            <ul class="flex items-center space-x-20 font-semibold">
                <li>
                    <a href="#"
                        class="text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Home</a>
                </li>
                <li>
                    <a href="#"
                        class="text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">About</a>
                </li>
                <li>
                    <a href="#"
                        class="text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Services</a>
                </li>
                <li>
                    <a href="#"
                        class="text-gray-700 hover:text-[#FC566C] hover:underline underline-offset-4 transition-all duration-300 ease-in-out">Contact</a>
                </li>
            </ul>

            <!-- Login Open/Close Modal -->
            <div class="flex items-center space-x-4">
                <a href="#" onclick="openModal()"
                    class="bg-[#FC566C] text-white hover:bg-[#f1233f] px-4 py-2 rounded-lg transition">
                    Book Appointment
                </a>
            </div>

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

                    <!-- New Form register modal -->
                    <div id="registerFormModal" class="hidden animate__animated animate__fadeInRight">
                        <div class="items-center text-center my-12">
                            <h2 class="text-[25px] font-bold text-[#FC566C] font-semibold">Register Your Account
                            </h2>
                            <p>Sign up to your resident account</p>
                        </div>
                        <form class="space-y-4">
                            <div class="my-8 mx-auto w-full max-w-md">
                                <div class="mb-3">
                                    <label for="Full-Name">Full Name <span class="text-red-500">*</span></label>
                                    <input type="text" placeholder="Full Name"
                                        class="w-full p-4 mt-2 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                </div>

                                <div class="flex gap-4 mb-3">
                                    <!-- Age Field -->
                                    <div class="w-1/2">
                                        <label for="age">Age <span class="text-red-500">*</span></label>
                                        <input type="text" placeholder="Age"
                                            class="w-full mt-2 p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                    </div>

                                    <!-- Contact Number Field -->
                                    <div class="w-1/2">
                                        <label for="contact">Contact Number <span class="text-red-500">*</span></label>
                                        <input type="text" placeholder="Contact Number"
                                            class="w-full mt-2 p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="Address">Address<span class="text-red-500">*</span></label>
                                    <input type="text" placeholder="Address"
                                        class="w-full mt-2 p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                </div>

                                <button id="openSecondRegister" type="submit"
                                    class="bg-[#FC566C] w-full p-4 rounded text-white hover:bg-[#f1233f] flex items-center justify-center ">
                                    Continue
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-1 mr-2" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>

                                <div class="flex justify-center mt-5 text-md font-semibold space-x-1">
                                    <p>Already have an account?</p>
                                    <button id="registerToLogin" type="button"
                                        class="text-[#FC566C] hover:underline">Login</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Second Registration Modal -->
                    <div id="secondRegisterFormModal" class="hidden animate__animated animate__fadeInRight">

                        <!-- Back Icon(<-) -->
                        <button class="h-6 w-6 rounded" id="backToFirstRegister">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                class="w-6 h-6 text-[#FC566C]">
                                <path d="M15.75 19.5L8.25 12l7.5-7.5v15z" />
                            </svg>
                        </button>

                        <div class="items-center text-center my-12">
                            <h2 class="text-[25px] font-bold text-[#FC566C] font-semibold">Complete Your Registration
                            </h2>
                            <p>Add your account credentials</p>
                        </div>
                        <form class="space-y-4">
                            <div class="my-8 mx-auto w-full max-w-md">
                                <div class="mb-3">
                                    <label for="email">Email <span class="text-red-500">*</span></label>
                                    <input type="email" placeholder="Email"
                                        class="w-full p-4 mt-2 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                </div>

                                <div class=" gap-4 mb-3">
                                    <!-- Password Field -->
                                    <div class="max-w-md">
                                        <label for="password">Password <span class="text-red-500">*</span></label>
                                        <input type="password" placeholder="Password"
                                            class="w-full mt-2 p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                    </div>

                                    <!-- Confirm Password Field -->
                                    <div class="max-w-md mt-2">
                                        <label for="confirm-password" class="mt-4"> <!-- Move mt-4 here -->
                                            Confirm Password <span class="text-red-500">*</span>
                                        </label>
                                        <input type="password" placeholder="Confirm Password"
                                            class="w-full mt-2 p-4 border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-[#3C96E1]" />
                                    </div>
                                </div>

                                <button type="submit"
                                    class="bg-[#FC566C] w-full p-4 rounded mt-6 text-white hover:bg-[#f1233f] flex items-center justify-center">
                                    Complete Registration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<main class="container mx-auto mt-24"> <!-- Added mt-24 to account for the fixed header height -->

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