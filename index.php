<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

if (isLoggedIn()) {
    redirectBasedOnRole();
}
?>

<div class="min-h-screen bg-gray-100">
    <!-- Hero Section - Adjusted to start right below header -->
    <div class="bg-[#0073D3] py-8 px-4 sm:px-6 lg:px-8 relative overflow-hidden mt-0">
        <!-- Animated background circles -->
        <div class="absolute -right-20 -top-20 w-[40rem] h-[40rem] rounded-full bg-gradient-to-br from-white to-sky-200 opacity-20 animate-pulse"></div>
        <div class="absolute right-0 top-1/2 w-[50rem] h-[50rem] rounded-full bg-gradient-to-tr from-white to-sky-300 opacity-30 animate-float"></div>
        
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-8 relative z-10">
            <!-- Left side - Title -->
            <div class="text-white text-center md:text-left max-w-2xl">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-2 leading-tight">Community Health</h1>
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 leading-tight">Monitoring and Tracking System</h2>
                <p class="text-lg md:text-xl opacity-90 mb-6">Secure health Management for your Community | V1.0</p>
                <div class="flex flex-col sm:flex-row items-center gap-4 justify-center md:justify-start">
                    <a href="#" onclick="openLearnMoreModal()"
    class="bg-[#005ba1] text-white hover:bg-[#0073D3] px-6 py-3 rounded-lg transition text-lg font-medium flex items-center justify-center gap-2 hover:scale-105 transform transition-transform duration-300">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
    Learn More
</a>
                </div>
            </div>
            
            <!-- Right side - Logo with circular gradient background -->
            <div class="mt-8 md:mt-0 relative">
                <div class="absolute -z-10 w-[110%] h-[110%] rounded-full bg-gradient-to-br from-white to-sky-300 blur-xl opacity-50 animate-pulse"></div>
                <div class="relative group">
                    <img src="./asssets/images/Capstone Project Image Sample.png" 
                         alt="CHTMS Logo" 
                         class="h-80 w-80 sm:h-96 sm:w-96 md:h-[32rem] md:w-[32rem] lg:h-[36rem] lg:w-[36rem] object-contain transform group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 rounded-full bg-gradient-to-br from-white/20 to-sky-300/20 mix-blend-overlay group-hover:opacity-0 transition-opacity duration-500"></div>
                </div>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
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
                    <div class="bg-blue-100 p-3 rounded-lg mb-4">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
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
                                <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
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
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900">Dr. Michael Chen</h3>
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
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
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

    <!-- CTA Section -->
<div class="bg-[#0073D3] relative">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8 lg:flex lg:items-center lg:justify-between">
        <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
            <span class="block">Ready to improve your community's health?</span>
            <span class="block text-blue-200">Get started today.</span>
        </h2>
        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
            <div class="inline-flex rounded-md shadow">
                <a href="#" onclick="openModal()" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-blue-600 bg-white hover:bg-blue-50">
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
            <button onclick="closeLearnMoreModal()" class="absolute top-4 right-4 z-50 text-gray-500 hover:text-gray-700 bg-white rounded-full p-2 shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Daily Health Tips</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Get 7-9 hours of quality sleep nightly for optimal health</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Drink at least 8 glasses of water throughout the day</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Engage in 30 minutes of moderate exercise daily</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Eat 5-7 servings of fruits and vegetables each day</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Practice mindfulness or meditation for 10-15 minutes daily</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Preventive Care -->
                    <div class="bg-green-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Preventive Care</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Schedule annual physical exams with your primary care physician</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Stay current with recommended vaccinations and immunizations</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Complete age-appropriate cancer screenings (mammograms, colonoscopies)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Monitor and manage chronic conditions with regular check-ups</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <span>Maintain dental health with biannual cleanings and exams</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Community Resources -->
                    <div class="bg-purple-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Community Resources</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Free Health Screenings:</strong> First Saturday of each month at Community Center (9am-1pm)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Nutrition Workshops:</strong> Every Tuesday 6-7pm at the Public Library</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Mental Health Support:</strong> Confidential counseling available M-F 9am-5pm</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Fitness Programs:</strong> Free yoga and Zumba classes at Park Pavilion</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>24/7 Nurse Hotline:</strong> Call (555) 123-4567 for medical advice</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Emergency Preparedness -->
                    <div class="bg-red-50 rounded-xl p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="bg-red-100 p-2 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Emergency Preparedness</h3>
                        </div>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Emergency Contacts:</strong> Post these numbers near your phone</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>First Aid Kits:</strong> Keep one at home, work, and in your car</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>CPR Training:</strong> Next class on 15th of each month at Fire Station #3</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Emergency Signs:</strong> Learn to recognize stroke and heart attack symptoms</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 mt-0.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span><strong>Disaster Plan:</strong> Create a family emergency meeting point and contacts</span>
                            </li>
                        </ul>
                    </div>
                </div>

                
            </div>
        </div>
    </div>
</div>

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

<!-- Login Modal - Redesigned with wider dimensions -->
<div id="loginModal" class="fixed inset-0 hidden z-50 h-full w-full backdrop-blur-sm bg-black/30 flex justify-center items-center p-4">
    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl mx-auto max-h-[90vh] overflow-y-auto">
        <!-- Close Icon (X) -->
        <button onclick="closeModal()" class="absolute top-4 right-4 z-50 text-gray-500 hover:text-gray-700 bg-white rounded-full p-2 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div id="mainModal" class="p-8">
            <div class="flex items-start gap-4 mb-8">
                <img src="./asssets/images/check-icon.png" alt="check-icon" class="h-12 w-12 flex-shrink-0">
                <p class="text-base text-gray-700">
                    To access records and appointments, please log in with your authorized account or register for a new account to securely continue using the system today online.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 mb-8">
                <button id="openLogin" class="bg-[#FC566C] text-white hover:bg-[#f1233f] px-6 py-3 rounded-lg transition text-base font-medium flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    Login
                </button>

                <button id="openRegister" class="bg-[#FC566C] text-white hover:bg-[#f1233f] px-6 py-3 rounded-lg transition text-base font-medium flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    Register
                </button>
            </div>

            <div class="h-64 rounded-lg overflow-hidden">
                <img src="./asssets/images/healthcare.png" alt="" class="w-full h-full object-cover">
            </div>
        </div>

        <!-- Login Form Modal -->
        <div id="loginFormModal" class="hidden p-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-[#FC566C]">Access Your Account</h2>
                <p class="text-gray-600 mt-2">Sign in to your resident account</p>
            </div>

            <form method="POST" action="/community-health-tracker/auth/login.php" class="space-y-4">
                <input type="hidden" name="role" value="user">
                
                <!-- Username -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="login_username">Username</label>
                    <input type="text" name="username" id="login_username" placeholder="Enter Username" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" />
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="login_password">Password</label>
                    <div class="relative">
                        <input id="login_password" name="password" type="password" placeholder="Password" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent pr-10" />
                        <button type="button" onclick="toggleLoginPassword()" class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                            <i id="loginEyeIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Forgot Password -->
                <div class="flex justify-end">
                    <a href="#" class="text-sm font-medium text-gray-600 hover:text-[#FC566C] hover:underline">Forgot your password?</a>
                </div>

                <!-- Login Button -->
                <div class="pt-2">
                    <button type="submit" class="w-full bg-[#FC566C] text-white py-3 px-4 rounded-lg hover:bg-[#f1233f] transition flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                        Login
                    </button>
                </div>

                <!-- Register Link -->
                <div class="text-center pt-4">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <button id="loginToRegister" type="button" class="font-medium text-[#FC566C] hover:underline">Register here</button>
                    </p>
                </div>
            </form>
        </div>

        <!-- First Registration Modal -->
<div id="registerFormModal" class="hidden p-6">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-[#FC566C]">Register Your Account</h2>
        <p class="text-gray-600 mt-2">Sign up to your resident account</p>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded text-sm" role="alert">
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-极 py-3 rounded text-sm" role="alert">
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>
    
    <form id="firstRegisterForm" class="space-y-4">
        <!-- Full Name -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="Full Name" 
                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" required />
        </div>

        <!-- Age and Gender - Now side by side -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="age">Age</label>
                <input type="number" id="age极 name="age" placeholder="Age" min="1" max="120"
                    value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" required />
            </div>

            <!-- Gender Field -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1" for="gender">Gender</label>
                <select id="gender" name="gender" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
        </div>

        <!-- Contact and Address -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="contact">Contact Number</label>
            <input type="tel" id="contact" name="contact" placeholder="Contact Number"
                value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" required />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1" for="address">Address</label>
            <input type="text" id="address" name="address" placeholder="Address"
                value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2极 focus:ring-[#3C96极1] focus:border-transparent" required />
        </div>

        <!-- Continue Button -->
        <div class="pt-2">
            <button type="button" id="openSecondRegister"
                class="w-full bg-[#FC566C] text-white py-3 px-4 rounded-lg hover:bg-[#f1233f] transition flex items-center justify-center gap-2">
                Continue
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- Login Link -->
        <div class="text-center pt-4">
            <p class="text-sm text-gray-600">
                Already have an account? 
                <button id="registerToLogin" type="button" class="font-medium text-[#FC566C] hover:underline">Login here</button>
            </p>
        </div>
    </form>
</div>

        <!-- Second Registration Modal - Redesigned -->
        <div id="secondRegisterFormModal" class="hidden p-8">
            <button class="text-[#FC566C] hover:text-[#f1233f] mb-6 flex items-center gap-1" id="backToFirstRegister">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                    <path d="M15.75 19.5L8.25 12l7.5-7.5v15z" />
                </svg>
                <span>Back to Personal Information</span>
            </button>

            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-[#FC566C]">Complete Your Registration</h2>
                <p class="text-gray-600 mt-2">Add your account credentials</p>
            </div>

            <form method="POST" action="/community-health-tracker/auth/register.php" id="secondRegisterForm">
                <!-- Hidden fields to pass data from first form -->
                <input type="hidden" name="full_name" id="hidden_full_name" value="">
                <input type="hidden" name="age" id="hidden_age" value="">
                <input type="hidden" name="gender" id="hidden_gender" value="">
                <input type="hidden" name="contact" id="hidden_contact" value="">
                <input type="hidden" name="address" id="hidden_address" value="">
                
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Username -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="reg_username">Username *</label>
                            <input type="text" id="reg_username" name="username" placeholder="Username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" required />
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email *</label>
                            <input type="email" id="email" name="email" placeholder="Email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent" required />
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="reg_password">Password *</label>
                            <div class="relative">
                                <input type="password" id="reg_password" name="password" placeholder="Password (min 8 characters)"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent pr-10" 
                                    minlength="8" required />
                                <button type="button" onclick="toggleRegPassword()" class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                                    <i id="regEyeIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Password must be at least 8 characters</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="confirm_password">Confirm Password *</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3C96E1] focus:border-transparent pr-10" 
                                    minlength="8" required />
                                <button type="button" onclick="toggleConfirmPassword()" class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500">
                                    <i id="confirmEyeIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="passwordMatchError" class="mt-1 text-xs text-red-500 hidden">Passwords do not match</p>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" id="submitButton"
                            class="w-full bg-[#FC566C] text-white py-3 px-4 rounded-lg hover:bg-[#f1233f] transition flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Complete Registration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal() {
    document.getElementById('loginModal').classList.remove('hidden');
    document.getElementById('mainModal').classList.remove('hidden');
    document.getElementById('loginFormModal').classList.add('hidden');
    document.getElementById('registerFormModal').classList.add('hidden');
    document.getElementById('secondRegisterFormModal').classList.add('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('loginModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function openLearnMoreModal() {
    document.getElementById('learnMoreModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLearnMoreModal() {
    document.getElementById('learnMoreModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function toggleLoginPassword() {
    const password = document.getElementById('login_password');
    const eyeIcon = document.getElementById('loginEyeIcon');
    if (password.type === 'password') {
        password.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function toggleRegPassword() {
    const password = document.getElementById('reg_password');
    const eyeIcon = document.getElementById('regEyeIcon');
    if (password.type === 'password') {
        password.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function toggleConfirmPassword() {
    const password = document.getElementById('confirm_password');
    const eyeIcon = document.getElementById('confirmEyeIcon');
    if (password.type === 'password') {
        password.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Modal navigation
    document.getElementById('openLogin').addEventListener('click', function() {
        document.getElementById('mainModal').classList.add('hidden');
        document.getElementById('loginFormModal').classList.remove('hidden');
    });

    document.getElementById('openRegister').addEventListener('click', function() {
        document.getElementById('mainModal').classList.add('hidden');
        document.getElementById('registerFormModal').classList.remove('hidden');
    });

    document.getElementById('loginToRegister').addEventListener('click', function() {
        document.getElementById('loginFormModal').classList.add('hidden');
        document.getElementById('registerFormModal').classList.remove('hidden');
    });

    document.getElementById('registerToLogin').addEventListener('click', function() {
        document.getElementById('registerFormModal').classList.add('hidden');
        document.getElementById('loginFormModal').classList.remove('hidden');
    });

    // Form elements
    const firstRegisterForm = document.getElementById('firstRegisterForm');
    const secondRegisterForm = document.getElementById('secondRegisterForm');
    const openSecondRegister = document.getElementById('openSecondRegister');
    const backToFirstRegister = document.getElementById('backToFirstRegister');
    const registerFormModal = document.getElementById('registerFormModal');
    const secondRegisterFormModal = document.getElementById('secondRegisterFormModal');
    const password = document.getElementById('reg_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatchError = document.getElementById('passwordMatchError');
    const submitButton = document.getElementById('submitButton');

    // Store form data when moving to second form
    openSecondRegister.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Validate first form
        const requiredFields = firstRegisterForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('border-red-500');
            } else {
                field.classList.remove('border-red-500');
            }
        });
        
        if (!isValid) {
            alert('Please fill in all required fields in the first form.');
            return;
        }
        
        // Transfer data to hidden fields in second form
        document.getElementById('hidden_full_name').value = document.getElementById('full_name').value;
        document.getElementById('hidden_age').value = document.getElementById('age').value;
        document.getElementById('hidden_gender').value = document.getElementById('gender').value;
        document.getElementById('hidden_contact').value = document.getElementById('contact').value;
        document.getElementById('hidden_address').value = document.getElementById('address').value;
        
        // Hide first modal, show second modal
        registerFormModal.classList.add('hidden');
        secondRegisterFormModal.classList.remove('hidden');
    });
    
    // Go back to first form
    backToFirstRegister.addEventListener('click', function() {
        secondRegisterFormModal.classList.add('hidden');
        registerFormModal.classList.remove('hidden');
    });
    
    // Password matching validation
    function validatePasswordMatch() {
        if (password && confirmPassword && password.value && confirmPassword.value && password.value !== confirmPassword.value) {
            confirmPassword.classList.add('border-red-500');
            passwordMatchError.classList.remove('hidden');
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            return false;
        } else {
            confirmPassword.classList.remove('border-red-500');
            passwordMatchError.classList.add('hidden');
            submitButton.disabled = false;
            submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            return true;
        }
    }

    // Real-time validation
    if (password && confirmPassword) {
        password.addEventListener('input', validatePasswordMatch);
        confirmPassword.addEventListener('input', validatePasswordMatch);
    }

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
    
    // Handle viewport changes
    function handleViewport() {
        const modal = document.getElementById('loginModal');
        const learnMoreModal = document.getElementById('learnMoreModal');
        
        if (modal) {
            if (window.innerWidth < 640) {
                modal.classList.add('items-start');
                modal.classList.remove('items-center');
            } else {
                modal.classList.remove('items-start');
                modal.classList.add('items-center');
            }
        }
        
        if (learnMoreModal) {
            if (window.innerWidth < 640) {
                learnMoreModal.classList.add('items-start');
                learnMoreModal.classList.remove('items-center');
            } else {
                learnMoreModal.classList.remove('items-start');
                learnMoreModal.classList.add('items-center');
            }
        }
    }
    
    window.addEventListener('resize', handleViewport);
    handleViewport();
});
</script>