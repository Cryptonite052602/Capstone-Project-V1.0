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

try {
    // Get basic patient info with COALESCE for gender
    $stmt = $pdo->prepare("SELECT p.*, 
                          COALESCE(e.gender, p.gender) as display_gender,
                          u.unique_number, u.email as user_email, u.id as user_id
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
    
    // Display patient information with enhanced layout
    echo '
    <form id="healthInfoForm" method="POST" action="existing_info_patients.php" class="bg-gray-50 p-6 rounded-lg">
        <input type="hidden" name="patient_id" value="' . $patientId . '">
        
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
                        <option value="O+" ' . (($healthInfo['blood_type'] || '') == 'O+' ? 'selected' : '') . '>O+</option>
                        <option value="O-" ' . (($healthInfo['blood_type'] || '') == 'O-' ? 'selected' : '') . '>O-</option>
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
                        <option value="O+" ' . (($healthInfo['blood_type'] || '') == 'O+' ? 'selected' : '') . '>O+</option>
                        <option value="O-" ' . (($healthInfo['blood_type'] || '') == 'O-' ? 'selected' : '') . '>O-</option>
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
    </div>
    
    <div class="mt-6 flex justify-end space-x-4">
        <button type="submit" name="save_health_info" class="bg-primary text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-save mr-2"></i>Save ' . ($isRegisteredUser ? 'Medical Information' : 'Changes') . '
        </button>
    </div>
    
    <script>
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
    
    // Add real-time validation
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("healthInfoForm");
        if (form) {
            form.addEventListener("input", function(e) {
                if (e.target.name) {
                    e.target.classList.remove("border-red-500");
                }
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
    </script>
    ';
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8 text-danger">
            <i class="fas fa-exclamation-triangle text-3xl mb-3"></i>
            <p class="font-medium">Error loading patient data</p>
            <p class="text-sm text-gray-600 mt-1">' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
}
?>