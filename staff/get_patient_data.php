<?php
// get_patient_data.php (updated version)
require_once __DIR__ . '/../includes/auth.php';
redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

// Get patient ID from request
$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patientId <= 0) {
    echo '<div class="text-center py-8 text-danger">Invalid patient ID</div>';
    exit();
}

try {
    // Get basic patient info with ALL fields from both patient and user tables
    $stmt = $pdo->prepare("SELECT 
        p.*, 
        u.id as user_id,
        u.full_name as user_full_name,
        u.email as user_email, 
        u.age as user_age,
        u.gender as user_gender,
        u.civil_status as user_civil_status,
        u.occupation as user_occupation,
        u.address as user_address,
        u.sitio as user_sitio,
        u.contact as user_contact,
        u.unique_number,
        -- Date of birth from both tables
        u.date_of_birth as user_date_of_birth,
        -- Use COALESCE to prefer patient data over user data
        COALESCE(p.full_name, u.full_name) as display_full_name,
        COALESCE(p.age, u.age) as display_age,
        COALESCE(p.date_of_birth, u.date_of_birth) as display_date_of_birth,
        COALESCE(p.gender, u.gender) as display_gender,
        COALESCE(p.civil_status, u.civil_status) as display_civil_status,
        COALESCE(p.occupation, u.occupation) as display_occupation,
        COALESCE(p.address, u.address) as display_address,
        COALESCE(p.sitio, u.sitio) as display_sitio,
        COALESCE(p.contact, u.contact) as display_contact
    FROM sitio1_patients p 
    LEFT JOIN sitio1_users u ON p.user_id = u.id
    WHERE p.id = ? AND p.added_by = ?");

    $stmt->execute([$patientId, $_SESSION['user']['id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo '<div class="text-center py-8 text-danger">Patient not found</div>';
        exit();
    }

    // Check if this is a registered user
    $isRegisteredUser = !empty($patient['user_id']);

    // Get health info
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$healthInfo) {
        // Initialize empty health info if none exists
        $healthInfo = [
            'gender' => $patient['display_gender'] ?? '',
            'height' => '',
            'weight' => '',
            'temperature' => '',
            'blood_pressure' => '',
            'blood_type' => '',
            'allergies' => '',
            'medical_history' => '',
            'current_medications' => '',
            'family_history' => '',
            'immunization_record' => '',
            'chronic_conditions' => ''
        ];
    }

    // Format dates for display
    $lastCheckupValue = $patient['last_checkup'] ? date('Y-m-d', strtotime($patient['last_checkup'])) : '';

    // Format date of birth for display - if it exists, show in readable format
    $dateOfBirthDisplay = '';
    $dateOfBirthValue = '';
    if (!empty($patient['display_date_of_birth'])) {
        $dateOfBirthDisplay = date('M d, Y', strtotime($patient['display_date_of_birth']));
        $dateOfBirthValue = date('Y-m-d', strtotime($patient['display_date_of_birth']));
    }

    // Format user date of birth specifically for registered users
    $userDateOfBirthDisplay = '';
    if (!empty($patient['user_date_of_birth'])) {
        $userDateOfBirthDisplay = date('M d, Y', strtotime($patient['user_date_of_birth']));
    }

    // Check if health info is complete
    $healthInfoComplete = !empty($healthInfo['height']) && !empty($healthInfo['weight']) &&
        !empty($healthInfo['blood_type']) && !empty($healthInfo['gender']);

    // Display patient information
    echo '
    <form id="healthInfoForm" method="POST" action="../staff/save_patient_data.php" class="bg-gray-50 rounded-2xl">
        <input type="hidden" name="patient_id" value="' . $patientId . '">
        <input type="hidden" name="save_health_info" value="1">';

    if ($isRegisteredUser) {

        // DISPLAY FOR REGISTERED PATIENTS (REGISTERED USERS)
        echo '
        <h3 class="text-2xl font-normal border-b border-black-100 py-4 text-[#2563EB] mb-8 gap-4 flex items-center">
            <i class="fa-solid fa-address-card text-3xl"></i>
            Personal Information
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
    <!-- Personal Information Column 1 -->
    <div class="space-y-4">
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Full Name</label>
                <div class="w-full block px-4 py-3 bg-gray-100 border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_full_name']) . '</div>
            <input" type="hidden" name="full_name" value="' . htmlspecialchars($patient['user_full_name']) . '">
        </div>
        
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Age</label>
                <div class="w-full block px-4 py-3 bg-gray-100 border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . ($patient['user_age'] ?? 'N/A') . '</div>
            <input type="hidden" name="age" value="' . ($patient['user_age'] ?? '') . '">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Occupation</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_occupation'] ?? 'N/A') . '</div>
            <input type="hidden" name="occupation" value="' . htmlspecialchars($patient['user_occupation'] ?? '') . '">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Complete Address</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_address'] ?? 'Not specified') . '</div>
            <input type="hidden" name="address" value="' . htmlspecialchars($patient['user_address'] ?? '') . '">
        </div>
    </div>
    
    <!-- Personal Information Column 2 -->
    <div class="space-y-4">
        <div class="w-full">
            <div class="w-full">
                <label class="block text-gray-700 mb-2 font-medium required-field">Email</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_email'] ?? 'Not specified') . '</div>
            </div>
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Gender</label>
                <div class="w-full block px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . ($patient['user_gender'] ?? 'N/A') . '</div>
            <input type="hidden" name="gender" value="' . htmlspecialchars($patient['user_gender'] ?? '') . '">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Sitio</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_sitio'] ?? 'Not specified') . '</div>
            <input type="hidden" name="sitio" value="' . htmlspecialchars($patient['user_sitio'] ?? '') . '">
        </div>
    </div>
    
    <!-- Personal Information Column 3 -->
    <div class="space-y-4">
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Date of Birth</label>
                <div class="w-full block px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . $userDateOfBirthDisplay . '</div>
            <input type="hidden" name="date_of_birth" value="' . htmlspecialchars($patient['user_date_of_birth'] ?? '') . '">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Civil Status</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_civil_status'] ?? 'N/A') . '</div>
            <input type="hidden" name="civil_status" value="' . htmlspecialchars($patient['user_civil_status'] ?? '') . '">
        </div>

         <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                <div class="w-full px-4 py-3 bg-gray-100 border border border-[#85ccfb] rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_contact'] ?? 'Not specified') . '</div>
            <input type="hidden" name="contact" value="' . htmlspecialchars($patient['user_contact'] ?? '') . '">
        </div>
    </div>
</div>';
    } else {
        // ==== DISPLAY FOR REGULAR PATIENTS (NOT REGISTERED USERS) ====
        echo '
<h3 class="text-2xl font-normal border-b border-black-100 py-4 text-[#2563EB] mb-8 gap-4 flex items-center">
    <svg width="42" height="38" viewBox="0 0 42 38" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 2.06875C0.00381259 1.52162 0.222709 0.997953 0.609402 0.61087C0.996095 0.223787 1.51954 0.00436385 2.06667 0H39.6C40.7417 0 41.6667 0.927083 41.6667 2.06875V35.4312C41.6629 35.9784 41.444 36.502 41.0573 36.8891C40.6706 37.2762 40.1471 37.4956 39.6 37.5H2.06667C1.51836 37.4994 0.992702 37.2812 0.605186 36.8933C0.217671 36.5054 -2.78032e-07 35.9796 0 35.4312V2.06875ZM8.33333 25V29.1667H33.3333V25H8.33333ZM8.33333 8.33333V20.8333H20.8333V8.33333H8.33333ZM25 8.33333V12.5H33.3333V8.33333H25ZM25 16.6667V20.8333H33.3333V16.6667H25ZM12.5 12.5H16.6667V16.6667H12.5V12.5Z" fill="#2563EB"/>
    </svg>
    Personal Information
</h3>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">

    <!-- COLUMN 1 -->
    <div class="space-y-4">
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Full Name</label>
            <input type="text" name="full_name" value="' . htmlspecialchars($patient['display_full_name']) . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]" required>
        </div>

         <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Gender</label>
            <select name="gender"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]" required>
                <option value="">Select Gender</option>
                <option value="Male" ' . (($patient['display_gender'] ?? '') == 'Male' ? 'selected' : '') . '>Male</option>
                <option value="Female" ' . (($patient['display_gender'] ?? '') == 'Female' ? 'selected' : '') . '>Female</option>
                <option value="Other" ' . (($patient['display_gender'] ?? '') == 'Other' ? 'selected' : '') . '>Other</option>
            </select>
        </div>

          <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Sitio</label>
            <select name="sitio"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                <option value="">Select Sitio</option>
                <option value="Proper Luz" ' . (($patient['display_sitio'] ?? '') == 'Proper Luz' ? 'selected' : '') . '>Proper Luz</option>
                <option value="Lower Luz" ' . (($patient['display_sitio'] ?? '') == 'Lower Luz' ? 'selected' : '') . '>Lower Luz</option>
                <option value="Upper Luz" ' . (($patient['display_sitio'] ?? '') == 'Upper Luz' ? 'selected' : '') . '>Upper Luz</option>
            </select>
        </div>
    </div>

    <!-- COLUMN 2 -->
    <div class="space-y-4">
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Date of Birth</label>
            <input type="date" name="date_of_birth" value="' . $dateOfBirthValue . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]" required>
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Civil Status</label>
            <select name="civil_status"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
                <option value="">Select Status</option>
                <option value="Single" ' . (($patient['display_civil_status'] ?? '') == 'Single' ? 'selected' : '') . '>Single</option>
                <option value="Married" ' . (($patient['display_civil_status'] ?? '') == 'Married' ? 'selected' : '') . '>Married</option>
                <option value="Widowed" ' . (($patient['display_civil_status'] ?? '') == 'Widowed' ? 'selected' : '') . '>Widowed</option>
                <option value="Separated" ' . (($patient['display_civil_status'] ?? '') == 'Separated' ? 'selected' : '') . '>Separated</option>
                <option value="Divorced" ' . (($patient['display_civil_status'] ?? '') == 'Divorced' ? 'selected' : '') . '>Divorced</option>
            </select>
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Complete Address</label>
            <input type="text" name="address" value="' . htmlspecialchars($patient['display_address'] ?? '') . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
        </div>
    </div>

    <!-- COLUMN 3 -->
    <div class="space-y-4">
        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium required-field">Age</label>
            <input type="number" name="age" value="' . ($patient['display_age'] ?? '') . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Occupation</label>
            <input type="text" name="occupation" value="' . htmlspecialchars($patient['display_occupation'] ?? '') . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
        </div>

        <div class="w-full">
            <label class="block text-gray-700 mb-2 font-medium">Contact Number</label>
            <input type="text" name="contact" value="' . htmlspecialchars($patient['display_contact'] ?? '') . '"
                class="w-full px-4 py-3 bg-white border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
        </div>
    </div>

</div>';

    }

    // DISPLAY FOR MEDICAL INFORMATION (BOTH TYPES OF PATIENTS)
    echo '
           <!-- Medical Information -->
<div class="space-y-4">
    <h3 class="text-2xl font-normal border-b border-black-100 py-4 text-[#2563EB] mb-8 gap-4 flex items-center">
        <svg width="42" height="42" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.5833 26.9104V28.125C14.5833 30.6114 15.5711 32.996 17.3292 34.7541C19.0874 36.5123 21.4719 37.5 23.9583 37.5C26.4447 37.5 28.8293 36.5123 30.5875 34.7541C32.3456 32.996 33.3333 30.6114 33.3333 28.125V24.6458C31.9427 24.1544 30.7706 23.1871 30.0243 21.915C29.2779 20.6429 29.0052 19.1479 29.2546 17.6942C29.5039 16.2405 30.2591 14.9218 31.3867 13.9711C32.5144 13.0204 33.9418 12.499 35.4167 12.499C36.8916 12.499 38.319 13.0204 39.4466 13.9711C40.5742 14.9218 41.3295 16.2405 41.5788 17.6942C41.8281 19.1479 41.5555 20.6429 40.8091 21.915C40.0627 23.1871 38.8906 24.1544 37.5 24.6458V28.125C37.5 31.7165 36.0733 35.1608 33.5337 37.7004C30.9942 40.24 27.5498 41.6667 23.9583 41.6667C20.3669 41.6667 16.9225 40.24 14.3829 37.7004C11.8434 35.1608 10.4167 31.7165 10.4167 28.125V26.9104C7.50365 26.418 4.85919 24.9098 2.95235 22.6532C1.04551 20.3967 -0.000452952 17.5377 1.47146e-07 14.5833V4.16667C1.47146e-07 3.0616 0.438987 2.00179 1.22039 1.22039C2.00179 0.438987 3.0616 0 4.16667 0L6.25 0C6.80253 0 7.33244 0.219493 7.72314 0.610194C8.11384 1.00089 8.33333 1.5308 8.33333 2.08333C8.33333 2.63587 8.11384 3.16577 7.72314 3.55647C7.33244 3.94717 6.80253 4.16667 6.25 4.16667H4.16667V14.5833C4.16667 16.7935 5.04464 18.9131 6.60744 20.4759C8.17025 22.0387 10.2899 22.9167 12.5 22.9167C14.7101 22.9167 16.8298 22.0387 18.3926 20.4759C19.9554 18.9131 20.8333 16.7935 20.8333 14.5833V4.16667H18.75C18.1975 4.16667 17.6676 3.94717 17.2769 3.55647C16.8862 3.16577 16.6667 2.63587 16.6667 2.08333C16.6667 1.5308 16.8862 1.00089 17.2769 0.610194C17.6676 0.219493 18.1975 0 18.75 0L20.8333 0C21.9384 0 22.9982 0.438987 23.7796 1.22039C24.561 2.00179 25 3.0616 25 4.16667V14.5833C25.0005 17.5377 23.9545 20.3967 22.0477 22.6532C20.1408 24.9098 17.4963 26.418 14.5833 26.9104ZM35.4167 20.8333C35.9692 20.8333 36.4991 20.6138 36.8898 20.2231C37.2805 19.8324 37.5 19.3025 37.5 18.75C37.5 18.1975 37.2805 17.6676 36.8898 17.2769C36.4991 16.8862 35.9692 16.6667 35.4167 16.6667C34.8641 16.6667 34.3342 16.8862 33.9435 17.2769C33.5528 17.6676 33.3333 18.1975 33.3333 18.75C33.3333 19.3025 33.5528 19.8324 33.9435 20.2231C34.3342 20.6138 34.8641 20.8333 35.4167 20.8333Z" fill="#2563EB"/>
        </svg>
        Medical Information
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Column 1 -->
        <div class="space-y-4">
            <div class="w-full">
                <label for="height" class="block text-gray-700 mb-2 font-medium required-field">Height (cm)</label>
                <input type="number" id="height" name="height" placeholder="0.0" step="0.1" min="0" value="' . htmlspecialchars($healthInfo['height'] ?? '') . '" 
                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
            </div>

            <div class="w-full">
                <label for="blood_pressure" class="block text-gray-700 mb-2 font-medium required-field">Blood Pressure</label>
                <input type="text" id="blood_pressure" name="blood_pressure" value="' . htmlspecialchars($healthInfo['blood_pressure'] ?? '') . '" 
                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="120/80">
            </div>
        </div>
        
        <!-- Column 2 -->
      <div class="space-y-4">
    <div class="w-full">
        <label for="weight" class="block text-gray-700 mb-2 font-medium required-field">Weight (kg)</label>
        <input type="number" id="weight" name="weight" placeholder="0.0" step="0.1" min="0" value="' . htmlspecialchars($healthInfo['weight'] ?? '') . '" 
               class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
    </div>
    
    <div class="w-full">
        <label for="blood_type" class="block text-gray-700 mb-2 font-medium required-field">Blood Type</label>
        <select id="blood_type" name="blood_type" 
                class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary appearance-none" 
                required>
            <option value="">Select Blood Type</option>
            <option value="A+" ' . (($healthInfo['blood_type'] ?? '') == 'A+' ? 'selected' : '') . '>A+</option>
            <option value="A-" ' . (($healthInfo['blood_type'] ?? '') == 'A-' ? 'selected' : '') . '>A-</option>
            <option value="B+" ' . (($healthInfo['blood_type'] ?? '') == 'B+' ? 'selected' : '') . '>B+</option>
            <option value="B-" ' . (($healthInfo['blood_type'] ?? '') == 'B-' ? 'selected' : '') . '>B-</option>
            <option value="AB+" ' . (($healthInfo['blood_type'] ?? '') == 'AB+' ? 'selected' : '') . '>AB+</option>
            <option value="AB-" ' . (($healthInfo['blood_type'] ?? '') == 'AB-' ? 'selected' : '') . '>AB-</option>
            <option value="O+" ' . (($healthInfo['blood_type'] ?? '') == 'O+' ? 'selected' : '') . '>O+</option>
            <option value="O-" ' . (($healthInfo['blood_type'] ?? '') == 'O-' ? 'selected' : '') . '>O-</option>
            <option value="Unknown" ' . (($healthInfo['blood_type'] ?? '') == 'Unknown' ? 'selected' : '') . '>Unknown</option>
        </select>
    </div>
</div>
        
        <!-- Column 3 -->
        <div class="space-y-4">
            <div class="w-full">
                <label for="temperature" class="block text-gray-700 mb-2 font-medium required-field">Temperature (°C)</label>
                <input type="number" id="temperature" name="temperature" placeholder="0" step="0.1" min="0" max="45" value="' . htmlspecialchars($healthInfo['temperature'] ?? '') . '" 
                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

             <div class="w-full">
                <label for="last_checkup" class="block text-gray-700 mb-2 font-medium required-field">Last Check-up Date</label>
                <input type="date" id="last_checkup" name="last_checkup" value="' . $lastCheckupValue . '" 
                       class="w-full px-4 py-3 border border-[#85ccfb] rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
            </div>
            
            <!-- You can add more fields here if needed -->
            <!-- For example, you might want to add BMI calculation or other medical fields -->
        </div>
    </div>
</div>';

    // Common medical history fields
    echo '
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="w-full">
            <label for="allergies" class="block text-gray-700 mb-2 font-medium required-field">Allergies</label>
            <textarea id="allergies" name="allergies" rows="3" 
                      class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Food, drug, environmental allergies...">' . htmlspecialchars($healthInfo['allergies'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="current_medications" class="block text-gray-700 mb-2 font-medium required-field">Current Medications</label>
            <textarea id="current_medications" name="current_medications" rows="3" 
                      class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Medications with dosage and frequency...">' . htmlspecialchars($healthInfo['current_medications'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="immunization_record" class="block text-gray-700 mb-2 font-medium required-field">Immunization Record</label>
            <textarea id="immunization_record" name="immunization_record" rows="3" 
                      class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Provide immunization history or recent vaccines">' . htmlspecialchars($healthInfo['immunization_record'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="chronic_conditions" class="block text-gray-700 mb-2 font-medium required-field">Chronic Conditions</label>
            <textarea id="chronic_conditions" name="chronic_conditions" rows="3" 
                      class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Hypertension, diabetes, asthma, etc...">' . htmlspecialchars($healthInfo['chronic_conditions'] ?? '') . '</textarea>
        </div>
    </div>
    
    <div class="mt-6 w-full">
        <label for="medical_history" class="block text-gray-700 mb-2 font-medium required-field">Medical History</label>
        <textarea id="medical_history" name="medical_history" rows="4" 
                  class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                  placeholder="Past illnesses, surgeries, hospitalizations, chronic conditions...">' . htmlspecialchars($healthInfo['medical_history'] ?? '') . '</textarea>
    </div>
    
    <div class="mt-6 w-full">
        <label for="family_history" class="block text-gray-700 mb-2 font-medium required-field">Family Medical History</label>
        <textarea id="family_history" name="family_history" rows="4" 
                  class="w-full px-4 py-3 border border-[#85ccfb] rounded-2xl    focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                  placeholder="Family history of diseases (parents, siblings)...">' . htmlspecialchars($healthInfo['family_history'] ?? '') . '</textarea>
    </div>';

} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-danger">Error loading patient data: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>