<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

// Fetch active announcements for landing page
$announcements = [];
$hasAnnouncements = false;
try {
    $stmt = $pdo->prepare("SELECT title, message, priority, post_date, expiry_date, image_path 
                          FROM sitio1_announcements 
                          WHERE status = 'active' AND audience_type = 'landing_page' 
                          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                          ORDER BY 
                            CASE priority 
                                WHEN 'high' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'normal' THEN 3
                            END,
                            post_date DESC 
                          LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasAnnouncements = !empty($announcements);
} catch (PDOException $e) {
    // Silently fail - announcements are not critical for page load
    error_log("Error fetching announcements: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Toong - Health Monitoring and Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Custom animations and styles */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        @keyframes gentle-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes gentle-glow {
            0%, 100% { 
                box-shadow: 0 25px 50px -12px rgba(59, 130, 246, 0.5),
                            0 0 0 4px rgba(59, 130, 246, 0.3);
            }
            50% { 
                box-shadow: 0 35px 60px -15px rgba(59, 130, 246, 0.7),
                            0 0 0 4px rgba(59, 130, 246, 0.5);
            }
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        .animate-gentle-pulse {
            animation: gentle-pulse 2s ease-in-out infinite;
        }
        .animate-gentle-glow {
            animation: gentle-glow 2s ease-in-out infinite;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Custom smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Ensure floating icon is above everything */
        .floating-announcement-container {
            z-index: 9999;
        }
        
        /* Floating button shadows and glow */
        .floating-announcement-container .shadow-2xl {
            box-shadow: 0 25px 50px -12px rgba(59, 130, 246, 0.5);
        }
        
        .floating-announcement-container .shadow-3xl {
            box-shadow: 0 35px 60px -15px rgba(59, 130, 246, 0.6);
        }
        
        .floating-announcement-container .ring-4 {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
        }
        
        .floating-announcement-container .ring-3 {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-[#4A90E2]">
    <!-- Floating Announcement Icon - Always Show if there are announcements -->
    <?php if ($hasAnnouncements): ?>
        <div id="floatingAnnouncement" class="floating-announcement-container fixed bottom-6 left-6 z-[9999]">
            <div class="relative">
                <!-- Pulsing animation for high priority announcements -->
                <?php 
                $hasHighPriority = false;
                foreach ($announcements as $announcement) {
                    if ($announcement['priority'] == 'high') {
                        $hasHighPriority = true;
                        break;
                    }
                }
                ?>
                
                <?php if ($hasHighPriority): ?>
                    <!-- Pulsing dot for high priority -->
                    <div class="absolute -top-1 -right-1 z-10">
                        <div class="relative">
                            <div class="animate-ping absolute inline-flex h-4 w-4 rounded-full bg-red-500 opacity-75"></div>
                            <div class="relative inline-flex rounded-full h-4 w-4 bg-red-600"></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Main circular floating button -->
                <button onclick="scrollToAnnouncements()"
                    class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full shadow-2xl hover:shadow-3xl transform hover:scale-110 transition-all duration-300 flex items-center justify-center group relative border-4 border-white ring-4 ring-blue-300 ring-opacity-50">
                    
                    <!-- Announcement icon with white color -->
                    <div class="relative">
                        <i class="fa-solid fa-person-burst text-3xl text-white"></i>
                        
                        <?php if ($hasHighPriority): ?>
                            <!-- Exclamation mark overlay for high priority -->
                            <i class="fas fa-exclamation text-xs text-red-300 absolute -top-1 -right-1 bg-white rounded-full p-0.5"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tooltip on hover -->
                    <div class="absolute left-full ml-3 top-1/2 transform -translate-y-1/2 hidden group-hover:block min-w-max z-50">
                        <div class="bg-gray-900 text-white text-sm rounded-lg py-2 px-3 shadow-xl">
                            <span class="font-semibold">View Announcements</span>
                            <div class="text-xs text-gray-300 mt-1"><?= count($announcements) ?> new update(s)</div>
                        </div>
                        <!-- Tooltip arrow -->
                        <div class="absolute right-full top-1/2 transform -translate-y-1/2">
                            <div class="w-0 h-0 border-t-4 border-b-4 border-l-4 border-transparent border-l-gray-900"></div>
                        </div>
                    </div>
                </button>
                
                <!-- Count badge with red -->
                <div class="absolute -bottom-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-7 w-7 flex items-center justify-center shadow-lg border-2 border-white">
                    <?= count($announcements) ?>
                </div>
            </div>
        </div>
        
        <!-- Mobile-friendly floating button (smaller for mobile) -->
        <div id="floatingAnnouncementMobile" class="floating-announcement-container fixed bottom-4 left-4 z-[9999] md:hidden">
            <div class="relative">
                <?php if ($hasHighPriority): ?>
                    <div class="absolute -top-1 -right-1 z-10">
                        <div class="relative">
                            <div class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-red-500 opacity-75"></div>
                            <div class="relative inline-flex rounded-full h-3 w-3 bg-red-600"></div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <button onclick="scrollToAnnouncements()"
                    class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full shadow-2xl hover:shadow-3xl transform hover:scale-110 transition-all duration-300 flex items-center justify-center group relative border-3 border-white ring-3 ring-blue-300 ring-opacity-50">
                    <i class="fas fa-bullhorn text-xl text-white"></i>
                    <div class="absolute -bottom-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-lg border-2 border-white">
                        <?= count($announcements) ?>
                    </div>
                </button>
            </div>
        </div>
        
        <!-- Alternative icon option with bell icon (more traditional for notifications) -->
        <div id="floatingAnnouncementAlt" class="floating-announcement-container fixed bottom-6 right-6 z-[9998] hidden">
            <div class="relative">
                <button onclick="scrollToAnnouncements()"
                    class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-full p-4 shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300 flex items-center justify-center group relative border-2 border-blue-300">
                    <i class="fas fa-bell text-2xl text-white"></i>
                    <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-6 w-6 flex items-center justify-center shadow-lg border-2 border-white">
                        <?= count($announcements) ?>
                    </div>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Container -->
    <div class="flex-1">
        <!-- Hero Section -->
        <div class="bg-[#0073D3] rounded-2xl -mt-20 mb-14 relative overflow-hidden mx-4 sm:mx-6 md:mx-14 md:h-[40rem]">
            <!-- Background Image -->
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat opacity-20" 
                 style="background-image: url('asssets/images/hero-background.jpg');"></div>
            
            <div class="flex flex-col md:flex-row h-full relative z-10">
                <!-- LEFT -->
                <div class="relative bg-white/90 backdrop-blur-sm w-full md:w-1/2 h-[22rem] md:h-auto flex items-center justify-center overflow-hidden p-6 sm:p-10">
                    <!-- IMAGE BACKGROUND -->
                    <img src="asssets/images/Medical.png" alt="Hand image"
                        class="absolute w-[18rem] sm:w-[24rem] md:w-[30rem] h-[18rem] sm:h-[24rem] md:h-[30rem] object-contain opacity-70 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">

                    <!-- TEXT CONTENT -->
                    <div class="relative z-10 space-y-2 sm:space-y-3">
                        <h1 class="font-bold text-5xl sm:text-4xl md:text-4xl text-[#0D1B2A]">Barangay Luz, Cebu City</h1>
                        <h2 class="font-semibold text-xl sm:text-2xl md:text-3xl text-[#1B263B]">Health Monitoring and Tracking</h2>
                        <p class="text-[#415A77] text-base sm:text-lg md:text-xl">Matinud-anong pagbantay sa panglawas sa barangay | V1.0</p>
                        <div class="text-white bg-[#4A90E2] w-[15rem] text-center py-4 rounded-full hover:bg-[#6BA8EB] transition-colors cursor-pointer">
                            <a href="#" onclick="openLearnMoreModal()">
                                <p class="font-semi-bold text-xl">Important Notice</p>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <div class="bg-white w-full md:w-1/2 h-[22rem] md:h-auto">
                    <img src="asssets/images/Dev.jpg" alt="Right section image"
                        class="w-full h-full object-cover object-center" />
                </div>
            </div>
        </div>

        <!-- Core Features Section -->
        <div class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-base text-[#4A90E2] font-semibold tracking-wide uppercase">Key Features</h2>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Essential Health Management Tools
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Health Records -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex flex-col items-center text-center">
                            <div class="bg-[#E8F2FF] p-3 rounded-lg mb-4">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Health Records</h3>
                            <p class="text-gray-600">
                                Secure digital storage for medical history and test results with easy access from any device.
                            </p>
                        </div>
                    </div>

                    <!-- Appointments -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex flex-col items-center text-center">
                            <div class="bg-[#E8F2FF] p-3 rounded-lg mb-4">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Appointments</h3>
                            <p class="text-gray-600">
                                Easy online scheduling with health providers and automated reminders.
                            </p>
                        </div>
                    </div>

                    <!-- Health Alerts -->
                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex flex-col items-center text-center">
                            <div class="bg-[#E8F2FF] p-3 rounded-lg mb-4">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Health Alerts</h3>
                            <p class="text-gray-600">
                                Important notifications about community health and emergency updates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Section -->
        <div id="announcementsSection" class="py-12 bg-gradient-to-b from-blue-50 to-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-bullhorn text-2xl text-blue-600"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900">Latest Announcements</h2>
                    </div>
                    <p class="text-gray-600 max-w-2xl mx-auto">
                        Stay informed with the latest health updates, events, and important information from Barangay Luz Health Center
                    </p>
                </div>

                <?php if (empty($announcements)): ?>
                    <!-- Empty State -->
                    <div class="bg-white rounded-2xl border-2 border-dashed border-blue-200 p-12 text-center">
                        <div class="mb-4">
                            <i class="fas fa-bullhorn text-6xl text-blue-200"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Announcements Yet</h3>
                        <p class="text-gray-500 max-w-md mx-auto">
                            Check back soon for important health updates and community announcements.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Announcements Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <?php foreach ($announcements as $index => $announcement): ?>
                            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                                <!-- Announcement Header -->
                                <div class="p-6 border-b border-gray-100">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> High Priority
                                                </span>
                                            <?php elseif ($announcement['priority'] == 'medium'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> Medium Priority
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                    <i class="fas fa-info-circle mr-1"></i> Announcement
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h3>
                                    
                                    <?php if ($announcement['expiry_date']): ?>
                                        <div class="flex items-center text-sm text-gray-600 mt-2">
                                            <i class="fas fa-clock mr-2 text-gray-400"></i>
                                            <span>Valid until <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Announcement Body -->
                                <div class="p-6">
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($announcement['title']) ?>"
                                                 class="w-full h-48 object-cover hover:scale-105 transition-transform duration-300">
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-gray-700 whitespace-pre-line max-h-40 overflow-y-auto pr-2">
                                        <?= nl2br(htmlspecialchars($announcement['message'])) ?>
                                    </div>

                                    <!-- Read More Button (if content is long) -->
                                    <?php if (strlen($announcement['message']) > 200): ?>
                                        <button onclick="openAnnouncementModal(<?= $index ?>)" 
                                                class="mt-4 text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center">
                                            Read More
                                            <i class="fas fa-chevron-right ml-1 text-xs"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- Announcement Footer -->
                                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            <span>Posted <?= date('M d, Y', strtotime($announcement['post_date'])) ?></span>
                                        </div>
                                        <?php if ($announcement['priority'] == 'high'): ?>
                                            <div class="animate-pulse">
                                                <i class="fas fa-bell text-red-500"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- View All Announcements -->
                    <div class="text-center">
                        <button onclick="openAnnouncementsModal()" 
                                class="inline-flex items-center px-10 py-5 border border-transparent text-lg font-medium rounded-full text-white bg-[#4A90E2] hover:bg-[#3a80d2] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4A90E2] transition-colors duration-200">
                            <i class="fas fa-list mr-5"></i>
                            View All Announcements
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Testimonials Section -->
        <div class="py-12 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-base text-[#4A90E2] font-semibold tracking-wide uppercase">Testimonials</h2>
                    <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        What our community members say
                    </p>
                </div>

                <div class="mt-10 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Testimonial 1 -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 rounded-full bg-[#E8F2FF] flex items-center justify-center">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path
                                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Sarah Johnson</h3>
                                <p class="text-gray-500">Community Member</p>
                            </div>
                        </div>
                        <p class="text-gray-600">
                            "The health tracking system has made it so easy to manage my family's medical appointments and records. The teleconsultation feature saved us during the pandemic."
                        </p>
                    </div>

                    <!-- Testimonial 2 -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 rounded-full bg-[#E8F2FF] flex items-center justify-center">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path
                                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Dr. Lance Christine L. Gallardo</h3>
                                <p class="text-gray-500">Healthcare Provider</p>
                            </div>
                        </div>
                        <p class="text-gray-600">
                            "As a physician, I appreciate how this system streamlines patient records and appointments. It's reduced administrative work and improved patient care coordination."
                        </p>
                    </div>

                    <!-- Testimonial 3 -->
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center mb-4">
                            <div class="h-12 w-12 rounded-full bg-[#E8F2FF] flex items-center justify-center">
                                <svg class="h-6 w-6 text-[#4A90E2]" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path
                                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">Robert Garcia</h3>
                                <p class="text-gray-500">Community Leader</p>
                            </div>
                        </div>
                        <p class="text-gray-600">
                            "Our community's health metrics have improved since implementing this system. The health alerts feature helps us respond quickly to emerging issues."
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-[#0073D3] relative">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
        <div>
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                <span class="block">Ready to improve your community's health?</span>
                <span class="block text-blue-200">Get started today.</span>
            </h2>
            
            <div class="mt-4 flex items-center space-x-6">
                <a href="https://twitter.com/yourcommunity" target="_blank" rel="noopener noreferrer" class="text-white hover:text-blue-200 transition-colors" aria-label="Twitter">
                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.007-.531.801-.581 1.498-1.309 2.049-2.138-.745.33-1.558.552-2.396.653.859-.516 1.517-1.332 1.815-2.28-.79-.475-1.688-.823-2.622-1.018-.767-.621-1.854-1.28-3.033-1.28-2.26 0-4.09 1.83-4.09 4.09 0 .32.036.63.104.93-3.4-.17-6.42-1.79-8.44-4.25-.35.6-.55 1.29-.55 2.02 0 1.42.72 2.68 1.82 3.42-.67 0-1.3-.2-1.85-.5v.05c0 1.99 1.41 3.65 3.27 4.03-.34.09-.7.14-1.07.14-.26 0-.52-.02-.77-.07.51 1.62 2.02 2.8 3.79 2.83-1.39 1.09-3.14 1.74-5.05 1.74-.32 0-.64-.02-.95-.06 1.8 1.15 3.93 1.82 6.13 1.82"/>
                    </svg>
                </a>
                <a href="https://www.facebook.com/BarangayLuzCebuCity2023" target="_blank" rel="noopener noreferrer" class="text-white hover:text-blue-200 transition-colors" aria-label="Facebook">
                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h8v-7h-3v-3h3V9c0-3.32 1.54-5 5-5h3v3h-3c-1.1 0-1 0-1 1v2h4l-1 3h-3v7h6c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                    </svg>
                </a>
                <a href="https://instagram.com/yourcommunity" target="_blank" rel="noopener noreferrer" class="text-white hover:text-blue-200 transition-colors" aria-label="Instagram">
                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M12 4.75c2.4 0 2.67.01 3.62.05a4.78 4.78 0 0 1 3.23 1.12c.98.98 1.12 2.08 1.12 3.23.04.95.05 1.22.05 3.62s-.01 2.67-.05 3.62a4.78 4.78 0 0 1-1.12 3.23c-.98.98-2.08 1.12-3.23 1.12-.95.04-1.22.05-3.62.05s-2.67-.01-3.62-.05a4.78 4.78 0 0 1-3.23-1.12c-.98-.98-1.12-2.08-1.12-3.23-.04-.95-.05-1.22-.05-3.62s.01-2.67.05-3.62a4.78 4.78 0 0 1 1.12-3.23c.98-.98 2.08-1.12 3.23-1.12.95-.04 1.22-.05 3.62-.05zm0-2c-2.48 0-2.82.01-3.79.05a6.78 6.78 0 0 0-4.79 1.66A6.78 6.78 0 0 0 2.05 8.21c-.04.97-.05 1.3-.05 3.79s.01 2.82.05 3.79a6.78 6.78 0 0 0 1.66 4.79 6.78 6.78 0 0 0 4.79 1.66c.97.04 1.3.05 3.79.05s2.82-.01 3.79-.05a6.78 6.78 0 0 0 4.79-1.66 6.78 6.78 0 0 0 1.66-4.79c.04-.97.05-1.3.05-3.79s-.01-2.82-.05-3.79a6.78 6.78 0 0 0-1.66-4.79A6.78 6.78 0 0 0 15.79 2.05c-.97-.04-1.3-.05-3.79-.05zm0 3.75a4.25 4.25 0 1 0 0 8.5 4.25 4.25 0 0 0 0-8.5zm0 6.5a2.25 2.25 0 1 1 0-4.5 2.25 2.25 0 0 1 0 4.5zm5.35-6.55a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                    </svg>
                </a>
            </div>
        </div>
        
        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
            <div class="inline-flex rounded-md shadow">
                <a href="/register.php"
                    class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-[#4A90E2] bg-white hover:bg-blue-50 transition-colors">
                    Sign up for free
                </a>
            </div>
        </div>
    </div>
    <div class="absolute bottom-6 right-10 text-white text-md opacity-80">Version 1.0</div>
</div> 

    <!-- Learn More Modal -->
<div id="learnMoreModal"
    class="fixed inset-0 z-50 hidden bg-black/40 backdrop-blur-sm flex items-center justify-center px-4">

    <div
        class="relative w-full max-w-7xl h-[92vh] bg-white rounded-3xl shadow-2xl flex flex-col overflow-hidden
               animate-[fadeIn_0.3s_ease-out]">

        <!-- Header -->
        <div
            class="sticky top-0 z-20 bg-white border-b border-blue-100 px-10 py-6 flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-blue-700">
                    Community Health Essentials
                </h2>
                <p class="text-base text-gray-500 mt-1">
                    A complete guide to wellness, prevention, and safety
                </p>
            </div>

            <button onclick="closeLearnMoreModal()"
                class="w-12 h-12 flex items-center justify-center rounded-full
                       bg-blue-50 text-blue-700 hover:bg-blue-100 transition text-xl">
                ✕
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto px-10 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

                <!-- Card -->
                <div
                    class="bg-blue-50 border border-blue-100 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                    <h3 class="flex items-center gap-4 text-2xl font-semibold text-blue-700 mb-6">
                        <span class="bg-blue-100 px-4 py-2 rounded-xl text-blue-700">✔</span>
                        Daily Health Tips
                    </h3>
                    <ul class="space-y-4 text-gray-700 text-lg leading-relaxed">
                        <li>• Get 7–9 hours of quality sleep</li>
                        <li>• Drink at least 8 glasses of water</li>
                        <li>• Exercise for 30 minutes daily</li>
                        <li>• Eat fruits and vegetables daily</li>
                        <li>• Practice mindfulness or meditation</li>
                    </ul>
                </div>

                <div
                    class="bg-blue-50 border border-blue-100 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                    <h3 class="flex items-center gap-4 text-2xl font-semibold text-blue-700 mb-6">
                        <span class="bg-blue-100 px-4 py-2 rounded-xl">🩺</span>
                        Preventive Care
                    </h3>
                    <ul class="space-y-4 text-gray-700 text-lg">
                        <li>• Annual physical checkups</li>
                        <li>• Updated vaccinations</li>
                        <li>• Age-appropriate screenings</li>
                        <li>• Chronic condition monitoring</li>
                        <li>• Dental exams twice a year</li>
                    </ul>
                </div>

                <div
                    class="bg-blue-50 border border-blue-100 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                    <h3 class="flex items-center gap-4 text-2xl font-semibold text-blue-700 mb-6">
                        <span class="bg-blue-100 px-4 py-2 rounded-xl">👥</span>
                        Community Resources
                    </h3>
                    <ul class="space-y-4 text-gray-700 text-lg">
                        <li><strong>Free Screenings:</strong> Monthly (Community Center)</li>
                        <li><strong>Nutrition Workshops:</strong> Tuesdays</li>
                        <li><strong>Mental Health:</strong> M–F, 9am–5pm</li>
                        <li><strong>Fitness Programs:</strong> Yoga & Zumba</li>
                        <li><strong>Nurse Hotline:</strong> (555) 123-4567</li>
                    </ul>
                </div>

                <div
                    class="bg-blue-50 border border-blue-100 rounded-2xl p-8 shadow-sm hover:shadow-md transition">
                    <h3 class="flex items-center gap-4 text-2xl font-semibold text-blue-700 mb-6">
                        <span class="bg-blue-100 px-4 py-2 rounded-xl">🚨</span>
                        Emergency Preparedness
                    </h3>
                    <ul class="space-y-4 text-gray-700 text-lg">
                        <li>• Keep emergency numbers visible</li>
                        <li>• Maintain first-aid kits</li>
                        <li>• Attend CPR training monthly</li>
                        <li>• Learn emergency warning signs</li>
                        <li>• Create a family disaster plan</li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</div>


    <!-- Announcements Modal -->
    <div id="announcementsModal" class="fixed inset-0 hidden z-50 bg-black/30">
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <!-- Close Icon (X) -->
                <button onclick="closeAnnouncementsModal()"
                    class="absolute top-4 right-4 z-50 text-gray-500 hover:text-gray-700 bg-white rounded-full p-2 shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <div class="overflow-y-auto flex-1 p-8">
                    <div class="text-center mb-8">
                        <div class="flex items-center justify-center gap-3 mb-4">
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-bullhorn text-2xl text-blue-600"></i>
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900">All Announcements</h2>
                        </div>
                        <p class="text-gray-600 max-w-2xl mx-auto">
                            Stay updated with all important announcements from Barangay Luz Health Center
                        </p>
                    </div>

                    <?php if (empty($announcements)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-bullhorn text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No announcements available at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($announcements as $index => $announcement): ?>
                                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <?php if ($announcement['priority'] == 'high'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 border border-red-200">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> High Priority
                                                </span>
                                            <?php elseif ($announcement['priority'] == 'medium'): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                    <i class="fas fa-exclamation-circle mr-1"></i> Medium Priority
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 border border-blue-200">
                                                    <i class="fas fa-info-circle mr-1"></i> Announcement
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= date('M d, Y', strtotime($announcement['post_date'])) ?>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-xl font-bold text-gray-900 mb-3">
                                        <?= htmlspecialchars($announcement['title']) ?>
                                    </h3>
                                    
                                    <?php if ($announcement['image_path']): ?>
                                        <div class="mb-4 rounded-lg overflow-hidden">
                                            <img src="<?= htmlspecialchars($announcement['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($announcement['title']) ?>"
                                                 class="w-full h-64 object-cover">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-gray-700 whitespace-pre-line">
                                        <?= nl2br(htmlspecialchars($announcement['message'])) ?>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-calendar-alt mr-2"></i>
                                                <span>Posted: <?= date('F j, Y', strtotime($announcement['post_date'])) ?></span>
                                            </div>
                                            <?php if ($announcement['expiry_date']): ?>
                                                <div class="flex items-center">
                                                    <i class="fas fa-clock mr-2"></i>
                                                    <span>Valid until: <?= date('M d, Y', strtotime($announcement['expiry_date'])) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                    <div class="text-center">
                        <p class="text-gray-600 mb-4">
                            For the latest updates, please check this section regularly or contact the Barangay Health Center.
                        </p>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to scroll to announcements section
        function scrollToAnnouncements() {
            const announcementsSection = document.getElementById('announcementsSection');
            if (announcementsSection) {
                announcementsSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Add a visual highlight effect
                announcementsSection.classList.add('ring-4', 'ring-blue-300', 'ring-opacity-50', 'rounded-xl');
                setTimeout(() => {
                    announcementsSection.classList.remove('ring-4', 'ring-blue-300', 'ring-opacity-50', 'rounded-xl');
                }, 1500);
                
                // Add pulsing animation to the first announcement card
                const firstCard = announcementsSection.querySelector('.bg-white.rounded-xl');
                if (firstCard) {
                    firstCard.classList.add('animate-pulse');
                    setTimeout(() => {
                        firstCard.classList.remove('animate-pulse');
                    }, 3000);
                }
            }
        }

        // Announcement Modal Functions
        function openAnnouncementsModal() {
            document.getElementById('announcementsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAnnouncementsModal() {
            document.getElementById('announcementsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openAnnouncementModal(index) {
            // If you want individual announcement modals, implement here
            // For now, just open the full announcements modal
            openAnnouncementsModal();
        }

        // Learn More Modal Functions
        function openLearnMoreModal() {
            document.getElementById('learnMoreModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
        }

        function closeLearnMoreModal() {
            document.getElementById('learnMoreModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            document.body.style.position = 'static';
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const announcementsModal = document.getElementById('announcementsModal');
            const learnMoreModal = document.getElementById('learnMoreModal');
            
            if (announcementsModal && !announcementsModal.classList.contains('hidden') && 
                event.target === announcementsModal) {
                closeAnnouncementsModal();
            }
            
            if (learnMoreModal && !learnMoreModal.classList.contains('hidden') && 
                event.target === learnMoreModal) {
                closeLearnMoreModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAnnouncementsModal();
                closeLearnMoreModal();
            }
        });

        // Add floating announcement animation for attention
        window.addEventListener('load', function() {
            const floatingAnnouncement = document.getElementById('floatingAnnouncement');
            const floatingAnnouncementMobile = document.getElementById('floatingAnnouncementMobile');
            
            if (floatingAnnouncement) {
                // Initial bounce animation
                setTimeout(() => {
                    floatingAnnouncement.classList.add('animate-bounce');
                    setTimeout(() => {
                        floatingAnnouncement.classList.remove('animate-bounce');
                    }, 1000);
                }, 1000);

                // Add floating animation
                floatingAnnouncement.classList.add('animate-float');
                
                // Gentle glow pulse every 10 seconds
                setInterval(() => {
                    const button = floatingAnnouncement.querySelector('button');
                    if (button) {
                        button.classList.add('animate-gentle-glow');
                        setTimeout(() => {
                            button.classList.remove('animate-gentle-glow');
                        }, 2000);
                    }
                }, 10000);
            }
            
            if (floatingAnnouncementMobile) {
                // Add floating animation for mobile
                floatingAnnouncementMobile.classList.add('animate-float');
                
                // Gentle pulse for mobile
                setInterval(() => {
                    const button = floatingAnnouncementMobile.querySelector('button');
                    if (button) {
                        button.classList.add('animate-gentle-pulse');
                        setTimeout(() => {
                            button.classList.remove('animate-gentle-pulse');
                        }, 2000);
                    }
                }, 10000);
            }
        });

        // Ensure floating icon stays on top of modals
        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('[id$="Modal"]');
            modals.forEach(modal => {
                modal.style.zIndex = '9998'; // One below floating icon
            });
        });
    </script>
</body>
</html>