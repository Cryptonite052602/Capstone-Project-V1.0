<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}

// Fetch public announcements from database
$publicAnnouncements = [];
try {
    $stmt = $pdo->prepare("SELECT title, message, priority, post_date 
                          FROM sitio1_announcements 
                          WHERE status = 'active' AND audience_type = 'public' 
                          AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                          ORDER BY post_date DESC 
                          LIMIT 10");
    $stmt->execute();
    $publicAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silently fail - announcements are not critical for page load
    error_log("Error fetching public announcements: " . $e->getMessage());
}
?>

<div class="min-h-screen bg-[#3290BF]">
    <!-- Public Announcements Banner -->

    <!-- Hero Section - Adjusted to start right below header -->
    <div class="bg-[#0073D3] rounded-2xl -mt-20 mb-14 relative overflow-hidden mx-4 sm:mx-6 md:mx-14 md:h-[40rem]">
        <div class="flex flex-col md:flex-row h-full">
            <!-- LEFT -->
            <div
                class="relative bg-white w-full md:w-1/2 h-[22rem] md:h-auto flex items-center justify-center overflow-hidden p-6 sm:p-10">

                <!-- IMAGE BACKGROUND -->
                <img src="asssets/images/hand-image.png" alt="Hand image"
                    class="absolute w-[18rem] sm:w-[24rem] md:w-[30rem] h-[18rem] sm:h-[24rem] md:h-[30rem] object-contain opacity-70 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">

                <!-- TEXT CONTENT -->
                <div class="relative z-10 space-y-2 sm:space-y-3">
                    <h1 class="font-bold text-3xl sm:text-4xl md:text-5xl text-[#0D1B2A]">Barangay Toong</h1>
                    <h2 class="font-semibold text-xl sm:text-2xl md:text-3xl text-[#1B263B]">Health and Monitoring and Tracking</h2>
                    <p class="text-[#415A77] text-base sm:text-lg md:text-xl">Matinud-anong pagbantay sa panglawas sa barangay | V1.0</p>
                    <div class="text-white bg-[#3290BF] w-[18rem] text-center py-4 rounded-full hover:bg-[#69AAD6]">
                        <a href="">
                            <p class="font-bold text-md">Learn more about your Health</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="bg-white w-full md:w-1/2 h-[22rem] md:h-auto">
                <img src="asssets/images/right-image.png" alt="Right section image"
                    class="w-full h-full object-cover object-center" />
            </div>
        </div>
    </div>

    <!-- Core Features Section - Simplified -->
    <div class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-base text-blue-600 font-semibold tracking-wide uppercase">Key Features</h2>
                <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Essential Health Management Tools
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Health Records -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="flex flex-col items-center text-center">
                        <div class="bg-blue-100 p-3 rounded-lg mb-4">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Health Records</h3>
                        <p class="text-gray-600">
                            Secure digital storage for medical history and test results with easy access from any
                            device.
                        </p>
                    </div>
                </div>

                <!-- Appointments -->
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="flex flex-col items-center text-center">
                        <div class="bg-blue-100 p-3 rounded-lg mb-4">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <div class="bg-blue-100 p-3 rounded-lg mb-4">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    <!-- Testimonials Section -->
    <div class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-base text-blue-600 font-semibold tracking-wide uppercase">Testimonials</h2>
                <p class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    What our community members say
                </p>
            </div>

            <div class="mt-10 grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Testimonial 1 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
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
                        "The health tracking system has made it so easy to manage my family's medical appointments and
                        records. The teleconsultation feature saved us during the pandemic."
                    </p>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
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
                        "As a physician, I appreciate how this system streamlines patient records and appointments. It's
                        reduced administrative work and improved patient care coordination."
                    </p>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
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
                        "Our community's health metrics have improved since implementing this system. The health alerts
                        feature helps us respond quickly to emerging issues."
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-[#0073D3] relative">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
            <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                <span class="block">Ready to improve your community's health?</span>
                <span class="block text-blue-200">Get started today.</span>
            </h2>
            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                <div class="inline-flex rounded-md shadow">
                    <a href="#" onclick="openModal()"
                        class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-blue-600 bg-white hover:bg-blue-50">
                        Sign up for free
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-6 right-10 text-white text-md opacity-80">Version 1.0</div>
    </div>
</div>

<!-- Learn More Modal -->
<div id="learnMoreModal" class="fixed inset-0 hidden z-50 bg-black/30">
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-[90vw] h-[90vh] max-h-[90vh] flex flex-col">
            <!-- Close Icon (X) -->
            <button onclick="closeLearnMoreModal()"
                class="absolute top-4 right-4 z-50 text-gray-500 hover:text-gray-700 bg-white rounded-full p-2 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <div class="overflow-y-auto flex-1 p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-[#0073D3]">Community Health Essentials</h2>
                    <p class="text-gray-600 mt-2">Comprehensive guide to maintaining your health and wellness</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Health Tips -->
                    <div class="bg-blue-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Daily Health Tips</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Get 7-9 hours of quality sleep nightly for optimal health</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Drink at least 8 glasses of water throughout the day</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Engage in 30 minutes of moderate exercise daily</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Eat 5-7 servings of fruits and vegetables each day</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Practice mindfulness or meditation for 10-15 minutes daily</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Preventive Care -->
                    <div class="bg-green-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Preventive Care</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Schedule annual physical exams with your primary care physician</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Stay current with recommended vaccinations and immunizations</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Complete age-appropriate cancer screenings (mammograms, colonoscopies)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Monitor and manage chronic conditions with regular check-ups</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span>Maintain dental health with biannual cleanings and exams</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Community Resources -->
                    <div class="bg-purple-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Community Resources</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Free Health Screenings:</strong> First Saturday of each month at Community
                                    Center (9am-1pm)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Nutrition Workshops:</strong> Every Tuesday 6-7pm at the Public
                                    Library</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Mental Health Support:</strong> Confidential counseling available M-F
                                    9am-5pm</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Fitness Programs:</strong> Free yoga and Zumba classes at Park
                                    Pavilion</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>24/7 Nurse Hotline:</strong> Call (555) 123-4567 for medical advice</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Emergency Preparedness -->
                    <div class="bg-red-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-red-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Emergency Preparedness</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Emergency Contacts:</strong> Post these numbers near your phone</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>First Aid Kits:</strong> Keep one at home, work, and in your car</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>CPR Training:</strong> Next class on 15th of each month at Fire Station
                                    #3</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Emergency Signs:</strong> Learn to recognize stroke and heart attack
                                    symptoms</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <span><strong>Disaster Plan:</strong> Create a family emergency meeting point and
                                    contacts</span>
                            </li>
                        </ul>
                    </div>
                </div>


            </div>
        </div>
    </div>
</div>

<style>
    @keyframes scroll {
        0% {
            transform: translateX(0%);
        }

        100% {
            transform: translateX(-50%);
        }
    }

    .animate-scroll {
        animation: scroll 40s linear infinite;
        display: inline-block;
    }

    .announcement-container:hover .animate-scroll {
        animation-play-state: paused;
    }

    /* Ensure proper text rendering during animation */
    .announcement-container {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
</style>

<script>
    // Pause animation when user hovers over announcements
    document.addEventListener('DOMContentLoaded', function () {
        const announcementContainer = document.querySelector('.announcement-container');
        if (announcementContainer) {
            announcementContainer.addEventListener('mouseenter', function () {
                this.style.animationPlayState = 'paused';
            });

            announcementContainer.addEventListener('mouseleave', function () {
                this.style.animationPlayState = 'running';
            });

            // Click to pause/play
            announcementContainer.addEventListener('click', function () {
                if (this.style.animationPlayState === 'paused') {
                    this.style.animationPlayState = 'running';
                } else {
                    this.style.animationPlayState = 'paused';
                }
            });
        }
    });
</script>

<script>
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
</script>