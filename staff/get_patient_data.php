<?php
// get_patient_data.php (updated)
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

// Check if health info is already saved
$healthInfoExists = false;
try {
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfoExists = $stmt->fetch() !== false;
} catch (PDOException $e) {
    // Error handling
}

try {
    // Get basic patient info - FIXED: Removed COALESCE for sitio since it doesn't exist in patients table
    $stmt = $pdo->prepare("SELECT p.*, 
                          COALESCE(e.gender, p.gender) as display_gender,
                          u.unique_number, u.email as user_email, u.id as user_id, u.sitio as user_sitio
                          FROM sitio1_patients p 
                          LEFT JOIN sitio1_users u ON p.user_id = u.id
                          LEFT JOIN existing_info_patients e ON p.id = e.patient_id
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
            'gender' => $patient['display_gender'] ?? $patient['gender'] ?? '',
            'height' => '',
            'weight' => '',
            'blood_type' => '',
            'allergies' => '',
            'medical_history' => '',
            'current_medications' => '',
            'family_history' => ''
        ];
    }
    
    // Format last checkup date for input field
    $lastCheckupValue = $patient['last_checkup'] ? date('Y-m-d', strtotime($patient['last_checkup'])) : '';
    
    // Check if health info is complete
    $healthInfoComplete = !empty($healthInfo['height']) && !empty($healthInfo['weight']) && 
                         !empty($healthInfo['blood_type']) && (!empty($healthInfo['gender']) || $isRegisteredUser);
    
    // Display patient information with enhanced layout
    echo '
    <form id="healthInfoForm" method="POST" action="../staff/save_patient_data.php" class="bg-gray-50 p-6 rounded-lg">
        <input type="hidden" name="patient_id" value="' . $patientId . '">
        <input type="hidden" name="save_health_info" value="1">
        
        <!-- Header with Edit Button -->
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-user-injured mr-2 text-primary"></i>' . htmlspecialchars($patient['full_name']) . ' - ' . ($isRegisteredUser ? 'Registered User' : 'Patient') . ' Record
            </h2>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">ID: ' . $patientId . '</span>';
                
    if (!empty($patient['unique_number'])) {
        echo '<span class="text-sm text-blue-600 bg-blue-100 px-2 py-1 rounded">
                <i class="fas fa-id-card mr-1"></i>' . htmlspecialchars($patient['unique_number']) . '
              </span>';
    }
    
    echo '<a href="?tab=add-tab&edit_patient=' . $patientId . '" class="ml-2 px-3 py-1 bg-primary text-white rounded-md hover:bg-blue-700 transition text-sm">
                <i class="fas fa-edit mr-1"></i> Edit
            </a>
            </div>
        </div>';
    
    if ($isRegisteredUser) {
        // Display registered user information with read-only personal details
        echo '
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information (Read-only for registered users) -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Personal Information (Registered User)</h3>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Full Name</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['full_name']) . '</div>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Age</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . ($patient['age'] ?? 'N/A') . '</div>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Gender</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . ($patient['display_gender'] ?? 'N/A') . '</div>
                    <input type="hidden" name="gender" value="' . htmlspecialchars($healthInfo['gender'] ?? '') . '">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Email</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_email'] ?? 'Not specified') . '</div>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Address</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['address'] ?? 'Not specified') . '</div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Sitio</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['user_sitio'] ?? 'Not specified') . '</div>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                    <div class="p-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-800 readonly-field">' . htmlspecialchars($patient['contact'] ?? 'Not specified') . '</div>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2 font-medium">Last Check-up Date</label>
                    <input type="date" name="last_checkup" value="' . $lastCheckupValue . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
            
            <!-- Medical Information (Editable) -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Medical Information</h3>
                
                <div>
                    <label for="height" class="block text-gray-700 mb-2 font-medium">Height (cm) <span class="text-danger">*</span></label>
                    <input type="number" id="height" name="height" step="0.01" min="0" value="' . htmlspecialchars($healthInfo['height'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div>
                    <label for="weight" class="block text-gray-700 mb-2 font-medium">Weight (kg) <span class="text-danger">*</span></label>
                    <input type="number" id="weight" name="weight" step="0.01" min="0" value="' . htmlspecialchars($healthInfo['weight'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div>
                    <label for="blood_type" class="block text-gray-700 mb-2 font-medium">Blood Type <span class="text-danger">*</span></label>
                    <select id="blood_type" name="blood_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+" ' . (($healthInfo['blood_type'] ?? '') == 'A+' ? 'selected' : '') . '>A+</option>
                        <option value="A-" ' . (($healthInfo['blood_type'] ?? '') == 'A-' ? 'selected' : '') . '>A-</option>
                        <option value="B+" ' . (($healthInfo['blood_type'] ?? '') == 'B+' ? 'selected' : '') . '>B+</option>
                        <option value="B-" ' . (($healthInfo['blood_type'] ?? '') == 'B-' ? 'selected' : '') . '>B-</option>
                        <option value="AB+" ' . (($healthInfo['blood_type'] ?? '') == 'AB+' ? 'selected' : '') . '>AB+</option>
                        <option value="AB-" ' . (($healthInfo['blood_type'] ?? '') == 'AB-' ? 'selected' : '') . '>AB-</option>
                        <option value="O+" ' . (($healthInfo['blood_type'] ?? '') == 'O+' ? 'selected' : '') . '>O+</option>
                        <option value="O-" ' . (($healthInfo['blood_type'] ?? '') == 'O-' ? 'selected' : '') . '>O-</option>
                    </select>
                </div>
                
                <div>
                    <label for="allergies" class="block text-gray-700 mb-2 font-medium">Allergies</label>
                    <textarea id="allergies" name="allergies" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                              placeholder="List any known allergies (food, drug, environmental, etc.)">' . htmlspecialchars($healthInfo['allergies'] ?? '') . '</textarea>
                </div>
                
                <div>
                    <label for="current_medications" class="block text-gray-700 mb-2 font-medium">Current Medications</label>
                    <textarea id="current_medications" name="current_medications" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                              placeholder="List current medications with dosage and frequency">' . htmlspecialchars($healthInfo['current_medications'] ?? '') . '</textarea>
                </div>
            </div>
        </div>';
    } else {
        // Display regular patient information (editable)
        echo '
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Personal Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Personal Information</h3>
                
                <div>
                    <label for="full_name" class="block text-gray-700 mb-2 font-medium">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="full_name" name="full_name" value="' . htmlspecialchars($patient['full_name']) . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div>
                    <label for="age" class="block text-gray-700 mb-2 font-medium">Age</label>
                    <input type="number" id="age" name="age" min="0" max="120" value="' . ($patient['age'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div>
                    <label for="gender" class="block text-gray-700 mb-2 font-medium">Gender</label>
                    <select id="gender" name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        <option value="">Select Gender</option>
                        <option value="Male" ' . (($healthInfo['gender'] ?? '') == 'Male' ? 'selected' : '') . '>Male</option>
                        <option value="Female" ' . (($healthInfo['gender'] ?? '') == 'Female' ? 'selected' : '') . '>Female</option>
                        <option value="Other" ' . (($healthInfo['gender'] ?? '') == 'Other' ? 'selected' : '') . '>Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="address" class="block text-gray-700 mb-2 font-medium">Address</label>
                    <input type="text" id="address" name="address" value="' . htmlspecialchars($patient['address'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <div>
                    <label for="sitio" class="block text-gray-700 mb-2 font-medium">Sitio</label>
                    <select id="sitio" name="sitio" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Sitio</option>
                        <option value="Sitio Sagingan" ' . (($patient['sitio'] ?? '') == 'Sitio Sagingan' ? 'selected' : '') . '>Sitio Sagingan — banana grove</option>
                        <option value="Sitio Tuburan" ' . (($patient['sitio'] ?? '') == 'Sitio Tuburan' ? 'selected' : '') . '>Sitio Tuburan — spring or water source</option>
                        <option value="Sitio Malipayon" ' . (($patient['sitio'] ?? '') == 'Sitio Malipayon' ? 'selected' : '') . '>Sitio Malipayon — joyful place</option>
                        <option value="Sitio Kabugason" ' . (($patient['sitio'] ?? '') == 'Sitio Kabugason' ? 'selected' : '') . '>Sitio Kabugason — sunrise spot</option>
                        <option value="Sitio Panaghiusa" ' . (($patient['sitio'] ?? '') == 'Sitio Panaghiusa' ? 'selected' : '') . '>Sitio Panaghiusa — unity</option>
                        <option value="Sitio Lantawan" ' . (($patient['sitio'] ?? '') == 'Sitio Lantawan' ? 'selected' : '') . '>Sitio Lantawan — lookout point</option>
                        <option value="Sitio Kalubihan" ' . (($patient['sitio'] ?? '') == 'Sitio Kalubihan' ? 'selected' : '') . '>Sitio Kalubihan — coconut grove</option>
                        <option value="Sitio Buntod" ' . (($patient['sitio'] ?? '') == 'Sitio Buntod' ? 'selected' : '') . '>Sitio Buntod — small hill</option>
                        <option value="Sitio Tagbaw" ' . (($patient['sitio'] ?? '') == 'Sitio Tagbaw' ? 'selected' : '') . '>Sitio Tagbaw — lush greenery</option>
                        <option value="Sitio Huni sa Hangin" ' . (($patient['sitio'] ?? '') == 'Sitio Huni sa Hangin' ? 'selected' : '') . '>Sitio Huni sa Hangin — sound of the wind</option>
                        <option value="Sitio Katilingban" ' . (($patient['sitio'] ?? '') == 'Sitio Katilingban' ? 'selected' : '') . '>Sitio Katilingban — community</option>
                        <option value="Sitio Banikanhon" ' . (($patient['sitio'] ?? '') == 'Sitio Banikanhon' ? 'selected' : '') . '>Sitio Banikanhon — native identity</option>
                        <option value="Sitio Datu Balas" ' . (($patient['sitio'] ?? '') == 'Sitio Datu Balas' ? 'selected' : '') . '>Sitio Datu Balas — chieftain of the sands</option>
                        <option value="Sitio Sinugdanan" ' . (($patient['sitio'] ?? '') == 'Sitio Sinugdanan' ? 'selected' : '') . '>Sitio Sinugdanan — origin or beginning</option>
                        <option value="Sitio Kabilin" ' . (($patient['sitio'] ?? '') == 'Sitio Kabilin' ? 'selected' : '') . '>Sitio Kabilin — heritage</option>
                        <option value="Sitio Alima" ' . (($patient['sitio'] ?? '') == 'Sitio Alima' ? 'selected' : '') . '>Sitio Alima — care or compassion</option>
                        <option value="Sitio Pundok" ' . (($patient['sitio'] ?? '') == 'Sitio Pundok' ? 'selected' : '') . '>Sitio Pundok — gathering place</option>
                        <option value="Sitio Bahandi" ' . (($patient['sitio'] ?? '') == 'Sitio Bahandi' ? 'selected' : '') . '>Sitio Bahandi — treasure</option>
                        <option value="Sitio Damgo" ' . (($patient['sitio'] ?? '') == 'Sitio Damgo' ? 'selected' : '') . '>Sitio Damgo — dream</option>
                        <option value="Sitio Kalinaw" ' . (($patient['sitio'] ?? '') == 'Sitio Kalinaw' ? 'selected' : '') . '>Sitio Kalinaw — peace</option>
                        <option value="Sitio Bulawanong Adlaw" ' . (($patient['sitio'] ?? '') == 'Sitio Bulawanong Adlaw' ? 'selected' : '') . '>Sitio Bulawanong Adlaw — golden sun</option>
                        <option value="Sitio Padayon" ' . (($patient['sitio'] ?? '') == 'Sitio Padayon' ? 'selected' : '') . '>Sitio Padayon — keep moving forward</option>
                        <option value="Sitio Himaya" ' . (($patient['sitio'] ?? '') == 'Sitio Himaya' ? 'selected' : '') . '>Sitio Himaya — glory</option>
                        <option value="Sitio Panamkon" ' . (($patient['sitio'] ?? '') == 'Sitio Panamkon' ? 'selected' : '') . '>Sitio Panamkon — vision or hope</option>
                        <option value="Sitio Sidlakan" ' . (($patient['sitio'] ?? '') == 'Sitio Sidlakan' ? 'selected' : '') . '>Sitio Sidlakan — eastern light or sunrise</option>
                    </select>
                </div>
                
                <div>
                    <label for="contact" class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                    <input type="text" id="contact" name="contact" value="' . htmlspecialchars($patient['contact'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                
                <div>
                    <label for="last_checkup" class="block text-gray-700 mb-2 font-medium">Last Check-up Date</label>
                    <input type="date" id="last_checkup" name="last_checkup" value="' . $lastCheckupValue . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
            </div>
            
            <!-- Medical Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-secondary mb-4">Medical Information</h3>
                
                <div>
                    <label for="height" class="block text-gray-700 mb-2 font-medium">Height (cm) <span class="text-danger">*</span></label>
                    <input type="number" id="height" name="height" step="0.01" min="0" value="' . htmlspecialchars($healthInfo['height'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div>
                    <label for="weight" class="block text-gray-700 mb-2 font-medium">Weight (kg) <span class="text-danger">*</span></label>
                    <input type="number" id="weight" name="weight" step="0.01" min="0" value="' . htmlspecialchars($healthInfo['weight'] ?? '') . '" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                </div>
                
                <div>
                    <label for="blood_type" class="block text-gray-700 mb-2 font-medium">Blood Type <span class="text-danger">*</span></label>
                    <select id="blood_type" name="blood_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        <option value="">Select Blood Type</option>
                        <option value="A+" ' . (($healthInfo['blood_type'] ?? '') == 'A+' ? 'selected' : '') . '>A+</option>
                        <option value="A-" ' . (($healthInfo['blood_type'] ?? '') == 'A-' ? 'selected' : '') . '>A-</option>
                        <option value="B+" ' . (($healthInfo['blood_type'] ?? '') == 'B+' ? 'selected' : '') . '>B+</option>
                        <option value="B-" ' . (($healthInfo['blood_type'] ?? '') == 'B-' ? 'selected' : '') . '>B-</option>
                        <option value="AB+" ' . (($healthInfo['blood_type'] ?? '') == 'AB+' ? 'selected' : '') . '>AB+</option>
                        <option value="AB-" ' . (($healthInfo['blood_type'] ?? '') == 'AB-' ? 'selected' : '') . '>AB-</option>
                        <option value="O+" ' . (($healthInfo['blood_type'] ?? '') == 'O+' ? 'selected' : '') . '>O+</option>
                        <option value="O-" ' . (($healthInfo['blood_type'] ?? '') == 'O-' ? 'selected' : '') . '>O-</option>
                    </select>
                </div>
                
                <div>
                    <label for="allergies" class="block text-gray-700 mb-2 font-medium">Allergies</label>
                    <textarea id="allergies" name="allergies" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                              placeholder="List any known allergies (food, drug, environmental, etc.)">' . htmlspecialchars($healthInfo['allergies'] ?? '') . '</textarea>
                </div>
                
                <div>
                    <label for="current_medications" class="block text-gray-700 mb-2 font-medium">Current Medications</label>
                    <textarea id="current_medications" name="current_medications" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                              placeholder="List current medications with dosage and frequency">' . htmlspecialchars($healthInfo['current_medications'] ?? '') . '</textarea>
                </div>
            </div>
        </div>';
    }
    
    // Common medical history fields for both types
    echo '
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div>
            <label for="medical_history" class="block text-gray-700 mb-2 font-medium">Medical History</label>
            <textarea id="medical_history" name="medical_history" rows="4" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Detail medical history including past illnesses, surgeries, chronic conditions, hospitalizations, etc.">' . htmlspecialchars($healthInfo['medical_history'] ?? '') . '</textarea>
        </div>
        
        <div>
            <label for="family_history" class="block text-gray-700 mb-2 font-medium">Family History</label>
            <textarea id="family_history" name="family_history" rows="4" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                      placeholder="Detail family medical history (parents, siblings, grandparents - include conditions like diabetes, heart disease, cancer, etc.)">' . htmlspecialchars($healthInfo['family_history'] ?? '') . '</textarea>
        </div>
    </div>';
    
    // Only show visits section if health info is complete
    if ($healthInfoComplete) {
        echo '
        <!-- Visits Tab -->
        <div class="mt-8 border-t border-gray-200 pt-6">
            <h3 class="text-lg font-semibold text-secondary mb-4">Patient Visits</h3>
            
            <!-- Add New Visit Form -->
            <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
                <h4 class="font-medium text-gray-700 mb-3">Record New Visit</h4>
                <form id="addVisitForm" method="POST" action="../staff/save_visit.php">
                    <input type="hidden" name="patient_id" value="' . $patientId . '">
                    <input type="hidden" name="action" value="add">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="visit_date" class="block text-gray-700 mb-2 text-sm font-medium">Visit Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" id="visit_date" name="visit_date" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required
                                   value="' . date('Y-m-d\TH:i') . '">
                        </div>
                        
                        <div>
                            <label for="visit_type" class="block text-gray-700 mb-2 text-sm font-medium">Visit Type <span class="text-danger">*</span></label>
                            <select id="visit_type" name="visit_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                                <option value="">Select Type</option>
                                <option value="checkup">Checkup</option>
                                <option value="consultation">Consultation</option>
                                <option value="emergency">Emergency</option>
                                <option value="followup">Follow-up</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="diagnosis" class="block text-gray-700 mb-2 text-sm font-medium">Diagnosis</label>
                            <textarea id="diagnosis" name="diagnosis" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                                      placeholder="Primary diagnosis or reason for visit"></textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="treatment" class="block text-gray-700 mb-2 text-sm font-medium">Treatment Provided</label>
                            <textarea id="treatment" name="treatment" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                                      placeholder="Treatment provided during this visit"></textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="prescription" class="block text-gray-700 mb-2 text-sm font-medium">Prescription</label>
                            <textarea id="prescription" name="prescription" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                                      placeholder="Medications prescribed (include dosage and instructions)"></textarea>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-gray-700 mb-2 text-sm font-medium">Additional Notes</label>
                            <textarea id="notes" name="notes" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                                      placeholder="Any additional notes or observations"></textarea>
                        </div>
                        
                        <div>
                            <label for="next_visit_date" class="block text-gray-700 mb-2 text-sm font-medium">Next Visit Date</label>
                            <input type="date" id="next_visit_date" name="next_visit_date" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div class="md:col-span-2 flex justify-end space-x-3 pt-2">
                            <button type="reset" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-times mr-2"></i>Clear
                            </button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>Add Visit
                            </button>
                        </div>
                    </div>
                </form>
            </div>';
            
        // Show visit history if it exists
        try {
            $stmt = $pdo->prepare("SELECT pv.*, u.full_name as staff_name 
                                  FROM patient_visits pv 
                                  LEFT JOIN sitio1_users u ON pv.staff_id = u.id 
                                  WHERE pv.patient_id = ? 
                                  ORDER BY pv.visit_date DESC");
            $stmt->execute([$patientId]);
            $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($visits)) {
                echo '
                <div>
                    <h4 class="font-medium text-gray-700 mb-3">Visit History</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-700">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Diagnosis</th>
                                    <th class="px-4 py-2">Treatment</th>
                                    <th class="px-4 py-2">Staff</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';
            
                foreach ($visits as $visit) {
                    $visitDate = date('M j, Y g:i A', strtotime($visit['visit_date']));
                    $visitType = ucfirst($visit['visit_type']);
                    $diagnosis = !empty($visit['diagnosis']) ? 
                        (strlen($visit['diagnosis']) > 30 ? substr($visit['diagnosis'], 0, 30) . '...' : $visit['diagnosis']) : 
                        'N/A';
                    $treatment = !empty($visit['treatment']) ? 
                        (strlen($visit['treatment']) > 30 ? substr($visit['treatment'], 0, 30) . '...' : $visit['treatment']) : 
                        'N/A';
                    
                    // Determine CSS class based on visit type
                    $typeClass = 'visit-type-' . $visit['visit_type'];
                    
                    echo '<tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">' . $visitDate . '</td>
                            <td class="px-4 py-2"><span class="visit-type-badge ' . $typeClass . '">' . $visitType . '</span></td>
                            <td class="px-4 py-2">' . htmlspecialchars($diagnosis) . '</td>
                            <td class="px-4 py-2">' . htmlspecialchars($treatment) . '</td>
                            <td class="px-4 py-2">' . htmlspecialchars($visit['staff_name'] ?? 'N/A') . '</td>
                            <td class="px-4 py-2">
                                <button onclick="viewVisitDetails(' . $visit['id'] . ')" class="text-primary hover:text-blue-700 mr-2" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="editVisit(' . $visit['id'] . ')" class="text-warning hover:text-orange-700 mr-2" title="Edit Visit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteVisit(' . $visit['id'] . ')" class="text-danger hover:text-red-700" title="Delete Visit">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                          </tr>';
                }
                
                echo '</tbody></table></div></div>';
            }
        } catch (PDOException $e) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    Error loading visit history: ' . htmlspecialchars($e->getMessage()) . '
                  </div>';
        }
        
        echo '</div>'; // Close visits tab
    } else {
        echo '
        <div class="mt-8 border-t border-gray-200 pt-6">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
                <i class="fas fa-exclamation-circle text-yellow-500 text-2xl mb-2"></i>
                <h3 class="font-medium text-yellow-700">Complete Health Information Required</h3>
                <p class="text-sm text-yellow-600 mt-1">Please fill out all required health information fields to enable patient visit tracking.</p>
                <p class="text-xs text-yellow-500 mt-2">Required fields: Height, Weight, Blood Type' . (!$isRegisteredUser ? ', Gender' : '') . '</p>
            </div>
        </div>';
    }
    
    echo '
    <div class="mt-6 flex justify-end space-x-4">
        <button type="submit" name="save_health_info" class="bg-primary text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-save mr-2"></i>Save ' . ($isRegisteredUser ? 'Medical Information' : 'Changes') . '
        </button>
    </div>
    </form>';

    // Add the CSS for visit types
    echo '
    <style>
    .visit-type-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .visit-type-checkup {
        background-color: #e0f2fe;
        color: #0369a1;
    }

    .visit-type-consultation {
        background-color: #fef3c7;
        color: #92400e;
    }

    .visit-type-emergency {
        background-color: #fee2e2;
        color: #b91c1c;
    }

    .visit-type-followup {
        background-color: #d1fae5;
        color: #065f46;
    }

    .readonly-field {
        background-color: #f9fafb;
        cursor: not-allowed;
    }
    </style>';

    // Add JavaScript for form validation and visit functionality
    echo '
    <script>
    // Form validation function
    function validateForm() {
        const requiredFields = ["height", "weight", "blood_type"];
        ' . (!$isRegisteredUser ? 'requiredFields.push("gender", "full_name");' : '') . '
        
        let isValid = true;
        
        requiredFields.forEach(field => {
            const element = document.querySelector(`[name="${field}"]`);
            if (element && !element.value.trim()) {
                element.classList.add("border-red-500");
                isValid = false;
            } else {
                element.classList.remove("border-red-500");
            }
        });
        
        if (!isValid) {
            showModalMessage("error", "Please fill in all required fields.");
            return false;
        }
        
        return true;
    }
    
    // Visit functionality
    function viewVisitDetails(visitId) {
        fetch("../staff/get_visit_details.php?id=" + visitId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showVisitModal(data.visit, "view");
                } else {
                    showModalMessage("error", data.message);
                }
            })
            .catch(error => {
                showModalMessage("error", "Error loading visit details: " + error);
            });
    }
    
    function editVisit(visitId) {
        fetch("../staff/get_visit_details.php?id=" + visitId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showVisitModal(data.visit, "edit");
                } else {
                    showModalMessage("error", data.message);
                }
            })
            .catch(error => {
                showModalMessage("error", "Error loading visit details: " + error);
            });
    }
    
    function deleteVisit(visitId) {
        if (confirm("Are you sure you want to delete this visit record? This action cannot be undone.")) {
            fetch("../staff/delete_visit.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "id=" + visitId + "&action=delete"
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showModalMessage("success", data.message);
                    // Reload the page to refresh the visit history
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showModalMessage("error", data.message);
                }
            })
            .catch(error => {
                showModalMessage("error", "Network error: " + error);
            });
        }
    }
    
    function showVisitModal(visit, mode) {
        const modalTitle = mode === "view" ? "View Visit Details" : "Edit Visit Details";
        const isViewMode = mode === "view";
        
        // Format date for datetime-local input
        const visitDate = visit.visit_date ? visit.visit_date.replace(" ", "T").substring(0, 16) : "";
        
        // Create modal HTML using string concatenation instead of template literals
        let modalHTML = \'<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">\' +
                       \'<div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">\' +
                       \'<div class="flex justify-between items-center p-6 border-b">\' +
                       \'<h3 class="text-lg font-semibold">\' + modalTitle + \'</h3>\' +
                       \'<button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 text-xl">\' +
                       \'<i class="fas fa-times"></i></button></div>\' +
                       \'<form id="visitModalForm" method="POST" action="../staff/save_visit.php" class="p-6">\' +
                       \'<input type="hidden" name="visit_id" value="\' + visit.id + \'">\' +
                       \'<input type="hidden" name="patient_id" value="\' + visit.patient_id + \'">\' +
                       \'<input type="hidden" name="action" value="\' + mode + \'">\' +
                       \'<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">\' +
                       \'<div><label class="block text-gray-700 mb-2 text-sm font-medium">Visit Date & Time <span class="text-danger">*</span></label>\' +
                       \'<input type="datetime-local" name="visit_date" value="\' + visitDate + \'" \' +
                       \'class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       (isViewMode ? \'readonly\' : \'\') + \' required></div>\' +
                       \'<div><label class="block text-gray-700 mb-2 text-sm font-medium">Visit Type <span class="text-danger">*</span></label>\' +
                       \'<select name="visit_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       (isViewMode ? \'disabled\' : \'\') + \' required>\' +
                       \'<option value="">Select Type</option>\' +
                       \'<option value="checkup"\' + (visit.visit_type === "checkup" ? \' selected\' : \'\') + \'>Checkup</option>\' +
                       \'<option value="consultation"\' + (visit.visit_type === "consultation" ? \' selected\' : \'\') + \'>Consultation</option>\' +
                       \'<option value="emergency"\' + (visit.visit_type === "emergency" ? \' selected\' : \'\') + \'>Emergency</option>\' +
                       \'<option value="followup"\' + (visit.visit_type === "followup" ? \' selected\' : \'\') + \'>Follow-up</option>\' +
                       \'</select></div></div>\' +
                       \'<div class="mb-4"><label class="block text-gray-700 mb-2 text-sm font-medium">Diagnosis</label>\' +
                       \'<textarea name="diagnosis" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       \'placeholder="Primary diagnosis or reason for visit" \' + (isViewMode ? \'readonly\' : \'\') + \'>\' + (visit.diagnosis || \'\') + \'</textarea></div>\' +
                       \'<div class="mb-4"><label class="block text-gray-700 mb-2 text-sm font-medium">Treatment Provided</label>\' +
                       \'<textarea name="treatment" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       \'placeholder="Treatment provided during this visit" \' + (isViewMode ? \'readonly\' : \'\') + \'>\' + (visit.treatment || \'\') + \'</textarea></div>\' +
                       \'<div class="mb-4"><label class="block text-gray-700 mb-2 text-sm font-medium">Prescription</label>\' +
                       \'<textarea name="prescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       \'placeholder="Medications prescribed" \' + (isViewMode ? \'readonly\' : \'\') + \'>\' + (visit.prescription || \'\') + \'</textarea></div>\' +
                       \'<div class="mb-4"><label class="block text-gray-700 mb-2 text-sm font-medium">Additional Notes</label>\' +
                       \'<textarea name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       \'placeholder="Any additional notes or observations" \' + (isViewMode ? \'readonly\' : \'\') + \'>\' + (visit.notes || \'\') + \'</textarea></div>\' +
                       \'<div class="mb-4"><label class="block text-gray-700 mb-2 text-sm font-medium">Next Visit Date</label>\' +
                       \'<input type="date" name="next_visit_date" value="\' + (visit.next_visit_date || \'\') + \'" \' +
                       \'class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary \' + (isViewMode ? \'bg-gray-100 cursor-not-allowed\' : \'bg-white\') + \'" \' +
                       (isViewMode ? \'readonly\' : \'\') + \'></div>\' +
                       \'<div class="flex justify-end space-x-3 pt-4 border-t">\';
        
        if (isViewMode) {
            modalHTML += \'<button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Close</button>\' +
                        \'<button type="button" onclick="switchToEditMode(\' + visit.id + \')" class="px-4 py-2 bg-warning text-white rounded-lg hover:bg-orange-600 transition">\' +
                        \'<i class="fas fa-edit mr-2"></i>Edit Visit</button>\';
        } else {
            modalHTML += \'<button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Cancel</button>\' +
                        \'<button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition">\' +
                        \'<i class="fas fa-save mr-2"></i>Save Changes</button>\';
        }
        
        modalHTML += \'</div></form></div></div>\';
        
        const modal = document.createElement("div");
        modal.innerHTML = modalHTML;
        document.body.appendChild(modal);
        
        // Handle form submission for edit mode
        if (!isViewMode) {
            const form = modal.querySelector("#visitModalForm");
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                saveVisitChanges(this);
            });
        }
    }
    
    function switchToEditMode(visitId) {
        closeModal();
        // Small delay to ensure modal is closed before opening new one
        setTimeout(() => {
            editVisit(visitId);
        }, 300);
    }
    
    function closeModal() {
        const modal = document.querySelector(".fixed.inset-0");
        if (modal) {
            modal.remove();
        }
    }
    
    function saveVisitChanges(form) {
        const formData = new FormData(form);
        
        fetch("../staff/save_visit.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showModalMessage("success", data.message);
                closeModal();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showModalMessage("error", data.message);
            }
        })
        .catch(error => {
            showModalMessage("error", "Network error: " + error);
        });
    }
    
    // Add real-time validation
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("healthInfoForm");
        if (form) {
            form.addEventListener("input", function(e) {
                if (e.target.name) {
                    e.target.classList.remove("border-red-500");
                }
            });
            
            // Handle form submission
            form.addEventListener("submit", function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        // Handle visit form submission
        const visitForm = document.getElementById("addVisitForm");
        if (visitForm) {
            visitForm.addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch("../staff/save_visit.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showModalMessage("success", data.message);
                        this.reset();
                        // Set default date to current
                        document.getElementById("visit_date").value = "' . date('Y-m-d\TH:i') . '";
                        // Reload the page to show new visit
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showModalMessage("error", data.message);
                    }
                })
                .catch(error => {
                    showModalMessage("error", "Network error: " + error);
                });
            });
        }
    });
    
    function showModalMessage(type, message) {
        // Remove any existing messages
        const existingMessages = document.querySelectorAll(".modal-message");
        existingMessages.forEach(msg => msg.remove());
        
        // Create message element
        const messageDiv = document.createElement("div");
        messageDiv.className = "modal-message fixed top-4 right-4 z-50 px-4 py-3 rounded-md shadow-md " + 
                              (type === "success" ? "bg-green-100 text-green-800 border border-green-200" : "bg-red-100 text-red-800 border border-red-200");
        messageDiv.innerHTML = \'<div class="flex items-center"><i class="fas \' + (type === "success" ? \'fa-check-circle\' : \'fa-exclamation-triangle\') + \' mr-2"></i>\' + message + \'</div>\';
        
        // Add to document
        document.body.appendChild(messageDiv);
        
        // Remove after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }
    </script>';
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-danger">
            <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
            <p class="font-medium">Error loading patient data</p>
            <p class="text-sm text-gray-600 mt-1">' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
}
?>