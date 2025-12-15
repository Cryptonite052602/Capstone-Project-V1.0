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
    <form id="healthInfoForm" method="POST" action="../staff/save_patient_data.php" class="bg-gray-50 p-6 rounded-2xl">
        <input type="hidden" name="patient_id" value="' . $patientId . '">
        <input type="hidden" name="save_health_info" value="1">';
        
    if ($isRegisteredUser) {
        // Display registered user information (read-only personal details)
        echo '
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information (Read-only for registered users) -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Personal Information (Registered User)</h3>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Full Name</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_full_name']) . '</div>
                    <input type="hidden" name="full_name" value="' . htmlspecialchars($patient['user_full_name']) . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Date of Birth</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . $userDateOfBirthDisplay . '</div>
                    <input type="hidden" name="date_of_birth" value="' . htmlspecialchars($patient['user_date_of_birth'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Age</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . ($patient['user_age'] ?? 'N/A') . '</div>
                    <input type="hidden" name="age" value="' . ($patient['user_age'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Gender</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . ($patient['user_gender'] ?? 'N/A') . '</div>
                    <input type="hidden" name="gender" value="' . htmlspecialchars($patient['user_gender'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Civil Status</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_civil_status'] ?? 'N/A') . '</div>
                    <input type="hidden" name="civil_status" value="' . htmlspecialchars($patient['user_civil_status'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Occupation</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_occupation'] ?? 'N/A') . '</div>
                    <input type="hidden" name="occupation" value="' . htmlspecialchars($patient['user_occupation'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Email</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_email'] ?? 'Not specified') . '</div>
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Address</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_address'] ?? 'Not specified') . '</div>
                    <input type="hidden" name="address" value="' . htmlspecialchars($patient['user_address'] ?? '') . '">
                </div>

                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Sitio</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_sitio'] ?? 'Not specified') . '</div>
                    <input type="hidden" name="sitio" value="' . htmlspecialchars($patient['user_sitio'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                    <div class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-full text-gray-800 readonly-field">' . htmlspecialchars($patient['user_contact'] ?? 'Not specified') . '</div>
                    <input type="hidden" name="contact" value="' . htmlspecialchars($patient['user_contact'] ?? '') . '">
                </div>
                
                <div class="w-full">
                    <label for="last_checkup" class="block text-gray-700 mb-2 font-medium">Last Check-up Date</label>
                    <input type="date" id="last_checkup" name="last_checkup" value="' . $lastCheckupValue . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>';
    } else {
        // Display regular patient information (editable)
        echo '
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Personal Information</h3>
                
                <div class="w-full">
                    <label for="full_name" class="block text-gray-700 mb-2 font-medium required-field">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="' . htmlspecialchars($patient['display_full_name']) . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div class="w-full">
                    <label for="date_of_birth" class="block text-gray-700 mb-2 font-medium required-field">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="' . $dateOfBirthValue . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                    <p class="text-sm text-gray-500 mt-1">Display: ' . $dateOfBirthDisplay . '</p>
                </div>
                
                <div class="w-full">
                    <label for="age" class="block text-gray-700 mb-2 font-medium">Age</label>
                    <input type="number" id="age" name="age" min="0" max="120" value="' . ($patient['display_age'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div class="w-full">
                    <label for="gender" class="block text-gray-700 mb-2 font-medium required-field">Gender</label>
                    <select id="gender" name="gender" class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        <option value="">Select Gender</option>
                        <option value="Male" ' . (($patient['display_gender'] ?? '') == 'Male' ? 'selected' : '') . '>Male</option>
                        <option value="Female" ' . (($patient['display_gender'] ?? '') == 'Female' ? 'selected' : '') . '>Female</option>
                        <option value="Other" ' . (($patient['display_gender'] ?? '') == 'Other' ? 'selected' : '') . '>Other</option>
                    </select>
                </div>
                
                <div class="w-full">
                    <label for="civil_status" class="block text-gray-700 mb-2 font-medium">Civil Status</label>
                    <select id="civil_status" name="civil_status" class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Status</option>
                        <option value="Single" ' . (($patient['display_civil_status'] ?? '') == 'Single' ? 'selected' : '') . '>Single</option>
                        <option value="Married" ' . (($patient['display_civil_status'] ?? '') == 'Married' ? 'selected' : '') . '>Married</option>
                        <option value="Widowed" ' . (($patient['display_civil_status'] ?? '') == 'Widowed' ? 'selected' : '') . '>Widowed</option>
                        <option value="Separated" ' . (($patient['display_civil_status'] ?? '') == 'Separated' ? 'selected' : '') . '>Separated</option>
                        <option value="Divorced" ' . (($patient['display_civil_status'] ?? '') == 'Divorced' ? 'selected' : '') . '>Divorced</option>
                    </select>
                </div>
                
                <div class="w-full">
                    <label for="occupation" class="block text-gray-700 mb-2 font-medium">Occupation</label>
                    <input type="text" id="occupation" name="occupation" value="' . htmlspecialchars($patient['display_occupation'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="Current occupation">
                </div>
                
                <div class="w-full">
                    <label for="address" class="block text-gray-700 mb-2 font-medium">Address</label>
                    <input type="text" id="address" name="address" value="' . htmlspecialchars($patient['display_address'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div class="w-full">
                    <label for="sitio" class="block text-gray-700 mb-2 font-medium">Sitio</label>
                    <select id="sitio" name="sitio" class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Sitio</option>
                        <option value="Proper Luz" ' . (($patient['display_sitio'] ?? '') == 'Proper Luz' ? 'selected' : '') . '>Proper Luz</option>
                        <option value="Lower Luz" ' . (($patient['display_sitio'] ?? '') == 'Lower Luz' ? 'selected' : '') . '>Lower Luz</option>
                        <option value="Upper Luz" ' . (($patient['display_sitio'] ?? '') == 'Upper Luz' ? 'selected' : '') . '>Upper Luz</option>
                        <option value="Luz Proper" ' . (($patient['display_sitio'] ?? '') == 'Luz Proper' ? 'selected' : '') . '>Luz Proper</option>
                        <option value="Luz Heights" ' . (($patient['display_sitio'] ?? '') == 'Luz Heights' ? 'selected' : '') . '>Luz Heights</option>
                        <option value="Panganiban" ' . (($patient['display_sitio'] ?? '') == 'Panganiban' ? 'selected' : '') . '>Panganiban</option>
                        <option value="Balagtas" ' . (($patient['display_sitio'] ?? '') == 'Balagtas' ? 'selected' : '') . '>Balagtas</option>
                        <option value="Carbon" ' . (($patient['display_sitio'] ?? '') == 'Carbon' ? 'selected' : '') . '>Carbon</option>
                        <!-- Add other Luz sitios as needed -->
                    </select>
                </div>
                
                <div class="w-full">
                    <label for="contact" class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                    <input type="text" id="contact" name="contact" value="' . htmlspecialchars($patient['display_contact'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div class="w-full">
                    <label for="last_checkup" class="block text-gray-700 mb-2 font-medium">Last Check-up Date</label>
                    <input type="date" id="last_checkup" name="last_checkup" value="' . $lastCheckupValue . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>';
    }
    
    // Common medical information section for both types
    echo '
            <!-- Medical Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Medical Information</h3>
                
                <div class="w-full">
                    <label for="height" class="block text-gray-700 mb-2 font-medium required-field">Height (cm)</label>
                    <input type="number" id="height" name="height" step="0.1" min="0" value="' . htmlspecialchars($healthInfo['height'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div class="w-full">
                    <label for="weight" class="block text-gray-700 mb-2 font-medium required-field">Weight (kg)</label>
                    <input type="number" id="weight" name="weight" step="0.1" min="0" value="' . htmlspecialchars($healthInfo['weight'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div class="w-full">
                    <label for="temperature" class="block text-gray-700 mb-2 font-medium">Temperature (°C)</label>
                    <input type="number" id="temperature" name="temperature" step="0.1" min="0" max="45" value="' . htmlspecialchars($healthInfo['temperature'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div class="w-full">
                    <label for="blood_pressure" class="block text-gray-700 mb-2 font-medium">Blood Pressure</label>
                    <input type="text" id="blood_pressure" name="blood_pressure" value="' . htmlspecialchars($healthInfo['blood_pressure'] ?? '') . '" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="120/80">
                </div>
                
                <div class="w-full">
                    <label for="blood_type" class="block text-gray-700 mb-2 font-medium required-field">Blood Type</label>
                    <select id="blood_type" name="blood_type" class="w-full px-4 py-3 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
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
        </div>';
    
    // Common medical history fields
    echo '
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="w-full">
            <label for="allergies" class="block text-gray-700 mb-2 font-medium">Allergies</label>
            <textarea id="allergies" name="allergies" rows="3" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Food, drug, environmental allergies...">' . htmlspecialchars($healthInfo['allergies'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="current_medications" class="block text-gray-700 mb-2 font-medium">Current Medications</label>
            <textarea id="current_medications" name="current_medications" rows="3" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Medications with dosage and frequency...">' . htmlspecialchars($healthInfo['current_medications'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="immunization_record" class="block text-gray-700 mb-2 font-medium">Immunization Record</label>
            <textarea id="immunization_record" name="immunization_record" rows="3" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Vaccinations received with dates...">' . htmlspecialchars($healthInfo['immunization_record'] ?? '') . '</textarea>
        </div>
        
        <div class="w-full">
            <label for="chronic_conditions" class="block text-gray-700 mb-2 font-medium">Chronic Conditions</label>
            <textarea id="chronic_conditions" name="chronic_conditions" rows="3" 
                      class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Hypertension, diabetes, asthma, etc...">' . htmlspecialchars($healthInfo['chronic_conditions'] ?? '') . '</textarea>
        </div>
    </div>
    
    <div class="mt-6 w-full">
        <label for="medical_history" class="block text-gray-700 mb-2 font-medium">Medical History</label>
        <textarea id="medical_history" name="medical_history" rows="4" 
                  class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                  placeholder="Past illnesses, surgeries, hospitalizations, chronic conditions...">' . htmlspecialchars($healthInfo['medical_history'] ?? '') . '</textarea>
    </div>
    
    <div class="mt-6 w-full">
        <label for="family_history" class="block text-gray-700 mb-2 font-medium">Family Medical History</label>
        <textarea id="family_history" name="family_history" rows="4" 
                  class="w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                  placeholder="Family history of diseases (parents, siblings)...">' . htmlspecialchars($healthInfo['family_history'] ?? '') . '</textarea>
    </div>';
    
    // Submit button
    echo '
    <div class="mt-8 flex justify-end space-x-4">
        <button type="submit" name="save_health_info" class="bg-primary text-white py-3 px-8 rounded-full hover:bg-blue-700 transition font-medium">
            <i class="fas fa-save mr-2"></i>Save ' . ($isRegisteredUser ? 'Medical Information' : 'All Information') . '
        </button>
    </div>
    </form>';

} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-danger">Error loading patient data: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>