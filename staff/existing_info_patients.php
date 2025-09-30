<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

$message = '';
$error = '';

// Handle form submission for editing health info - MODIFIED VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
    // Validate required fields but allow gender to be auto-populated from patient table if empty
    $required = ['patient_id', 'height', 'weight', 'blood_type'];
    $missing = array();
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    
    // Get patient gender from database if not provided in form
    $gender = $_POST['gender'];
    if (empty($gender)) {
        try {
            $stmt = $pdo->prepare("SELECT gender FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$_POST['patient_id']]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($patient && !empty($patient['gender'])) {
                $gender = $patient['gender'];
            }
        } catch (PDOException $e) {
            // If we can't get gender from database, add it to missing fields
            $missing[] = 'gender';
        }
    }
    
    if (!empty($missing)) {
        $error = "Please fill in all required fields: " . implode(', ', str_replace('_', ' ', $missing));
    } else {
        try {
            $patient_id = $_POST['patient_id'];
            $height = $_POST['height'];
            $weight = $_POST['weight'];
            $blood_type = $_POST['blood_type'];
            $allergies = !empty($_POST['allergies']) ? $_POST['allergies'] : null;
            $medical_history = !empty($_POST['medical_history']) ? $_POST['medical_history'] : null;
            $current_medications = !empty($_POST['current_medications']) ? $_POST['current_medications'] : null;
            $family_history = !empty($_POST['family_history']) ? $_POST['family_history'] : null;

            // Check if record exists
            $stmt = $pdo->prepare("SELECT id FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                    gender = ?, height = ?, weight = ?, blood_type = ?, allergies = ?, 
                    medical_history = ?, current_medications = ?, family_history = ?,
                    updated_at = NOW()
                    WHERE patient_id = ?");
                $stmt->execute([
                    $gender, $height, $weight, $blood_type, $allergies,
                    $medical_history, $current_medications, $family_history,
                    $patient_id
                ]);
                
                // Also update the gender in the main patient table
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET gender = ? WHERE id = ?");
                $stmt->execute([$gender, $patient_id]);
                
                $message = "Patient health information updated successfully!";
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type, allergies, 
                    medical_history, current_medications, family_history)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $patient_id, $gender, $height, $weight, $blood_type, $allergies,
                    $medical_history, $current_medications, $family_history
                ]);
                
                // Also update the gender in the main patient table
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET gender = ? WHERE id = ?");
                $stmt->execute([$gender, $patient_id]);
                
                $message = "Patient health information saved successfully!";
            }
            
            // Refresh health info after update
            $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $health_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Error saving patient health information: " . $e->getMessage();
        }
    }
}

// Handle form submission for adding new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $fullName = trim($_POST['full_name']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    $lastCheckup = trim($_POST['last_checkup']);
    $userId = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    // Medical information
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $bloodType = trim($_POST['blood_type']);
    $allergies = trim($_POST['allergies']);
    $medicalHistory = trim($_POST['medical_history']);
    $currentMedications = trim($_POST['current_medications']);
    $familyHistory = trim($_POST['family_history']);
    
    if (!empty($fullName)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into main patients table (removed consultation_type)
            $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                (full_name, age, gender, address, contact, last_checkup, added_by, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $age, $gender, $address, $contact, $lastCheckup, $_SESSION['user']['id'], $userId]);
            $patientId = $pdo->lastInsertId();
            
            // Get patient count for this user to generate display ID
            $stmt = $pdo->prepare("SELECT COUNT(*) as patient_count FROM sitio1_patients WHERE added_by = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $patientCount = $result['patient_count'];
            
            // Insert into medical info table
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, blood_type, allergies, medical_history, current_medications, family_history) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, $gender, $height, $weight, $bloodType, 
                $allergies, $medicalHistory, $currentMedications, $familyHistory
            ]);
            
            $pdo->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'Patient record added successfully! Patient ID: ' . $patientCount;
            header('Location: existing_info_patients.php?tab=patients-tab');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding patient record: ' . $e->getMessage();
        }
    } else {
        $error = 'Full name is required.';
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle patient deletion - MODIFIED TO PRESERVE USER_ID
if (isset($_GET['delete_patient'])) {
    $patientId = $_GET['delete_patient'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get patient data including user_id
        $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ? AND added_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient) {
            // Insert into deleted_patients table - preserve the user_id
            $stmt = $pdo->prepare("INSERT INTO deleted_patients 
                (original_id, full_name, age, gender, address, contact, last_checkup, added_by, user_id, deleted_at, deleted_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([
                $patient['id'], 
                $patient['full_name'], 
                $patient['age'], 
                $patient['gender'], 
                $patient['address'], 
                $patient['contact'], 
                $patient['last_checkup'], 
                $patient['added_by'],
                $patient['user_id'], // This preserves the user linkage for restoration
                $_SESSION['user']['id']
            ]);
            
            // Delete from main table
            $stmt = $pdo->prepare("DELETE FROM sitio1_patients WHERE id = ?");
            $stmt->execute([$patientId]);
            
            // Delete health info
            $stmt = $pdo->prepare("DELETE FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patientId]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Patient record moved to archive successfully!';
            header('Location: existing_info_patients.php');
            exit();
        } else {
            $error = 'Patient not found!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error deleting patient record: ' . $e->getMessage();
    }
}



// Handle patient restoration - MODIFIED TO ENSURE GENDER IS PRESERVED
if (isset($_GET['restore_patient'])) {
    $patientId = $_GET['restore_patient'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get archived patient data including user_id
        $stmt = $pdo->prepare("SELECT * FROM deleted_patients WHERE original_id = ? AND deleted_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $archivedPatient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archivedPatient) {
            // Restore to main patients table - preserve the user_id
            $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                (id, full_name, age, gender, address, contact, last_checkup, added_by, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $archivedPatient['original_id'], 
                $archivedPatient['full_name'], 
                $archivedPatient['age'], 
                $archivedPatient['gender'], 
                $archivedPatient['address'], 
                $archivedPatient['contact'], 
                $archivedPatient['last_checkup'], 
                $archivedPatient['added_by'],
                $archivedPatient['user_id'] // This preserves the user linkage
            ]);
            
            // Also restore health info with gender
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, blood_type) 
                VALUES (?, ?, 0, 0, '') 
                ON DUPLICATE KEY UPDATE gender = VALUES(gender)");
            $stmt->execute([$patientId, $archivedPatient['gender']]);
            
            // Delete from archive
            $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE original_id = ?");
            $stmt->execute([$patientId]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Patient record restored successfully!';
            header('Location: deleted_patients.php');
            exit();
        } else {
            $error = 'Archived patient not found!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error restoring patient record: ' . $e->getMessage();
    }
}

// Handle patient restoration - ADD THIS CODE AFTER THE DELETION HANDLING
if (isset($_GET['restore_patient'])) {
    $patientId = $_GET['restore_patient'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get archived patient data including user_id
        $stmt = $pdo->prepare("SELECT * FROM deleted_patients WHERE original_id = ? AND deleted_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $archivedPatient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archivedPatient) {
            // Restore to main patients table - preserve the user_id
            $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                (id, full_name, age, gender, address, contact, last_checkup, added_by, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $archivedPatient['original_id'], 
                $archivedPatient['full_name'], 
                $archivedPatient['age'], 
                $archivedPatient['gender'], 
                $archivedPatient['address'], 
                $archivedPatient['contact'], 
                $archivedPatient['last_checkup'], 
                $archivedPatient['added_by'],
                $archivedPatient['user_id'] // This preserves the user linkage
            ]);
            
            // Also restore health info if it exists in archive (you might need to implement this)
            // For now, we'll just create an empty health record
            $stmt = $pdo->prepare("INSERT IGNORE INTO existing_info_patients (patient_id) VALUES (?)");
            $stmt->execute([$patientId]);
            
            // Delete from archive
            $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE original_id = ?");
            $stmt->execute([$patientId]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Patient record restored successfully!';
            header('Location: deleted_patients.php');
            exit();
        } else {
            $error = 'Archived patient not found!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error restoring patient record: ' . $e->getMessage();
    }
}

// Handle converting user to patient - MODIFIED TO ENSURE GENDER IS SET
if (isset($_GET['convert_to_patient'])) {
    $userId = $_GET['convert_to_patient'];
    
    try {
        // Get user details including gender
        $stmt = $pdo->prepare("SELECT * FROM sitio1_users WHERE id = ? AND approved = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check if user already exists as a patient
            $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE user_id = ? AND added_by = ?");
            $stmt->execute([$userId, $_SESSION['user']['id']]);
            $existingPatient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingPatient) {
                $error = 'This user is already registered as a patient.';
            } else {
                // Start transaction
                $pdo->beginTransaction();
                
                // Insert into main patients table with gender from user
                $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                    (full_name, age, gender, address, contact, added_by, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user['full_name'], $user['age'], $user['gender'], $user['address'], $user['contact'],
                    $_SESSION['user']['id'], $userId
                ]);
                $patientId = $pdo->lastInsertId();
                
                // Insert medical info with gender from user - ADD ALL REQUIRED FIELDS
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type) 
                    VALUES (?, ?, 0, 0, '')");
                $stmt->execute([$patientId, $user['gender']]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = 'User converted to patient successfully!';
                header('Location: existing_info_patients.php?tab=patients-tab');
                exit();
            }
        } else {
            $error = 'User not found or not approved!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error converting user to patient: ' . $e->getMessage();
    }
}

// Get search term if exists
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchBy = isset($_GET['search_by']) ? trim($_GET['search_by']) : 'name';

// Get patient ID if selected
$selectedPatientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_POST['patient_id']) ? $_POST['patient_id'] : '');

// Check if view printed information is requested
$viewPrinted = isset($_GET['view_printed']) && $_GET['view_printed'] == 'true';

// Get list of patients matching search
$patients = [];
$searchedUsers = [];
if (!empty($searchTerm)) {
    try {
        if ($searchBy === 'unique_number') {
            // Search by unique number from sitio1_users table
            $query = "SELECT id, full_name, email, age, gender, address, contact, unique_number, 'user' as type
                     FROM sitio1_users 
                     WHERE approved = 1 AND unique_number LIKE ? 
                     ORDER BY full_name LIMIT 10";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute(["%$searchTerm%"]);
            $searchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Search by name (default) - FIXED: removed consultation_type and added gender
            $query = "SELECT p.id, p.full_name, p.age, p.gender, e.blood_type, e.height, e.weight
                     FROM sitio1_patients p 
                     LEFT JOIN existing_info_patients e ON p.id = e.patient_id 
                     WHERE p.added_by = ? AND p.full_name LIKE ? 
                     ORDER BY p.full_name LIMIT 10";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user']['id'], "%$searchTerm%"]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching patients: " . $e->getMessage();
    }
}

// Get all patients with their medical info for the patient list
try {
    $stmt = $pdo->prepare("SELECT p.*, e.height, e.weight, e.blood_type, e.allergies,
                          e.medical_history, e.current_medications, e.family_history,
                          u.unique_number, u.email as user_email
                          FROM sitio1_patients p
                          LEFT JOIN existing_info_patients e ON p.id = e.patient_id
                          LEFT JOIN sitio1_users u ON p.user_id = u.id
                          WHERE p.added_by = ? AND p.deleted_at IS NULL
                          ORDER BY p.created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user']['id']]);
    $allPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching patient records: " . $e->getMessage();
}

// Get existing health info if patient is selected - MODIFIED TO GET GENDER
if (!empty($selectedPatientId)) {
    try {
        // Get basic patient info - FIXED: added gender field
        $stmt = $pdo->prepare("SELECT p.*, u.unique_number, u.email as user_email 
                              FROM sitio1_patients p 
                              LEFT JOIN sitio1_users u ON p.user_id = u.id
                              WHERE p.id = ? AND p.added_by = ?");
        $stmt->execute([$selectedPatientId, $_SESSION['user']['id']]);
        $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient_details) {
            $error = "Patient not found!";
        } else {
            // Get health info
            $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$selectedPatientId]);
            $health_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If health info exists but doesn't have gender, use the one from patient table
            if ($health_info && empty($health_info['gender']) && !empty($patient_details['gender'])) {
                $health_info['gender'] = $patient_details['gender'];
            } elseif (!$health_info && !empty($patient_details['gender'])) {
                // If no health info exists yet, create a temporary array with patient gender
                $health_info = ['gender' => $patient_details['gender']];
            }
        }
    } catch (PDOException $e) {
        $error = "Error fetching health information: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Health Records</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3498db',
                        secondary: '#2c3e50',
                        success: '#2ecc71',
                        danger: '#e74c3c',
                        warning: '#f39c12',
                        info: '#17a2b8'
                    }
                }
            }
        }
    </script>
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .patient-card {
            transition: all 0.3s ease;
        }
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .modal {
            transition: opacity 0.3s ease;
        }
        .required-field::after {
            content: " *";
            color: #e74c3c;
        }
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        .patient-table th, .patient-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .patient-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #2c3e50;
        }
        .patient-table tr:hover {
            background-color: #f1f5f9;
        }
        .patient-id {
            font-weight: bold;
            color: #3498db;
        }
        .user-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        /* Custom button styles */
        .btn-view {
            background-color: #3498db;
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        .btn-view:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .btn-archive {
            background-color: #e74c3c;
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        .btn-archive:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .btn-add-patient {
            background-color: #2ecc71;
            color: white;
            border-radius: 8px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        .btn-add-patient:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-secondary">Patient Health Records</h1>
        
        <?php if ($message): ?>
            <div id="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="flex border-b">
                <button class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition" data-tab="patients-tab">
                    <i class="fas fa-list mr-2"></i>Patient Records
                </button>
                <button class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition" data-tab="add-tab">
                    <i class="fas fa-plus-circle mr-2"></i>Add New Patient
                </button>
            </div>
            
            <!-- Patients Tab -->
            <div id="patients-tab" class="tab-content p-6 active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-secondary">Patient Records</h2>
                    <a href="deleted_patients.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                        <i class="fas fa-archive mr-2"></i>View Archive
                    </a>
                </div>
                
                <!-- Search Form -->
                <form method="get" action="" class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <input type="hidden" name="tab" value="patients-tab">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="search_by" class="block text-gray-700 mb-2 font-medium">Search By</label>
                            <select id="search_by" name="search_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>>Name</option>
                                <option value="unique_number" <?= $searchBy === 'unique_number' ? 'selected' : '' ?>>Unique Number</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="search" class="block text-gray-700 mb-2 font-medium">Search Term</label>
                            <div class="flex items-center">
                                <div class="relative flex-grow">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" 
                                        placeholder="<?= $searchBy === 'unique_number' ? 'Enter unique number...' : 'Search patients by name...' ?>">
                                </div>
                                <button type="submit" class="ml-3 inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition">
                                    <i class="fas fa-search mr-2"></i> Search
                                </button>
                                <?php if (!empty($searchTerm)): ?>
                                    <a href="existing_info_patients.php" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                                        <i class="fas fa-times mr-2"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($searchTerm)): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-secondary">Search Results for "<?= htmlspecialchars($searchTerm) ?>"</h3>
                        </div>
                        
                        <?php if (empty($patients) && empty($searchedUsers)): ?>
                            <div class="p-6 text-center">
                                <i class="fas fa-search text-3xl text-gray-400 mb-3"></i>
                                <p class="text-gray-500">No patients or users found matching your search.</p>
                            </div>
                        <?php else: ?>
                            <!-- Display Patients Search Results -->
                            <?php if (!empty($patients)): ?>
                                <div class="p-4">
                                    <h4 class="text-md font-medium text-secondary mb-3">Patient Records</h4>
                                    <div class="overflow-x-auto">
                                        <table class="patient-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <th>Blood Type</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($patients as $index => $patient): ?>
                                                    <tr>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                        <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
                                                        <td class="font-semibold text-primary"><?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <button onclick="openViewModal(<?= $patient['id'] ?>)" class="btn-view inline-flex items-center mr-2">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <a href="?delete_patient=<?= $patient['id'] ?>" class="btn-archive inline-flex items-center" onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                                <i class="fas fa-trash-alt mr-1"></i> Archive
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Display Users Search Results -->
                            <?php if (!empty($searchedUsers)): ?>
                                <div class="p-4 border-t border-gray-200">
                                    <h4 class="text-md font-medium text-secondary mb-3">Registered Users</h4>
                                    <div class="overflow-x-auto">
                                        <table class="patient-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <th>Contact</th>
                                                    <th>Unique Number</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($searchedUsers as $index => $user): ?>
                                                    <tr>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td><?= $user['age'] ?? 'N/A' ?></td>
                                                        <td><?= htmlspecialchars($user['gender'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['contact'] ?? 'N/A') ?></td>
                                                        <td class="font-semibold text-primary"><?= htmlspecialchars($user['unique_number'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <a href="?convert_to_patient=<?= $user['id'] ?>" class="btn-add-patient inline-flex items-center" onclick="return confirm('Are you sure you want to add this user as a patient?')">
                                                                <i class="fas fa-user-plus mr-1"></i> Add as Patient
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($searchTerm)): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-secondary">All Patient Records</h3>
                    </div>
                    
                    <?php if (empty($allPatients)): ?>
                        <div class="text-center py-12 bg-gray-50 rounded-lg">
                            <i class="fas fa-user-injured text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No patients found</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by adding a new patient.</p>
                            <div class="mt-6">
                                <button data-tab="add-tab" class="tab-trigger inline-flex items-center px-4 py-2 bg-primary text-white rounded-md hover:bg-blue-700 transition">
                                    <i class="fas fa-plus-circle mr-2"></i>Add Patient
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="patient-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Blood Type</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allPatients as $index => $patient): ?>
                                        <tr>
                                            <td class="patient-id"><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                            <td><?= $patient['age'] ?? 'N/A' ?></td>
                                            <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
                                            <td class="font-semibold text-primary"><?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (!empty($patient['unique_number'])): ?>
                                                    <span class="user-badge">Registered User</span>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Regular Patient</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button onclick="openViewModal(<?= $patient['id'] ?>)" class="btn-view inline-flex items-center mr-2">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </button>
                                                <a href="?delete_patient=<?= $patient['id'] ?>" class="btn-archive inline-flex items-center" onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                    <i class="fas fa-trash-alt mr-1"></i> Archive
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 text-center p-4 border-t border-gray-200">
                            <a href="all_patients.php" class="inline-flex items-center px-4 py-2 bg-secondary text-white rounded-md hover:bg-gray-700 transition">
                                <i class="fas fa-list mr-2"></i>View All Patients
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Patient Tab -->
            <div id="add-tab" class="tab-content p-6">
                <h2 class="text-xl font-semibold mb-6 text-secondary">Add New Patient</h2>
                
                <form method="POST" action="" class="bg-gray-50 p-6 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information -->
                        <div>
                            <label for="full_name" class="block text-gray-700 mb-2 font-medium">Full Name <span class="text-danger">*</span></label>
                            <input type="text" id="full_name" name="full_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" required>
                        </div>
                        
                        <div>
                            <label for="age" class="block text-gray-700 mb-2 font-medium">Age</label>
                            <input type="number" id="age" name="age" min="0" max="120" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="gender" class="block text-gray-700 mb-2 font-medium">Gender</label>
                            <select id="gender" name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="address" class="block text-gray-700 mb-2 font-medium">Address</label>
                            <input type="text" id="address" name="address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="contact" class="block text-gray-700 mb-2 font-medium">Contact Number</label>
                            <input type="text" id="contact" name="contact" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="last_checkup" class="block text-gray-700 mb-2 font-medium">Last Check-up Date</label>
                            <input type="date" id="last_checkup" name="last_checkup" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <!-- Medical Information -->
                        <div>
                            <label for="height" class="block text-gray-700 mb-2 font-medium">Height (cm)</label>
                            <input type="number" id="height" name="height" step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="weight" class="block text-gray-700 mb-2 font-medium">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" step="0.01" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="blood_type" class="block text-gray-700 mb-2 font-medium">Blood Type</label>
                            <select id="blood_type" name="blood_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label for="allergies" class="block text-gray-700 mb-2 font-medium">Allergies</label>
                            <textarea id="allergies" name="allergies" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                        </div>
                        
                        <div>
                            <label for="current_medications" class="block text-gray-700 mb-2 font-medium">Current Medications</label>
                            <textarea id="current_medications" name="current_medications" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="medical_history" class="block text-gray-700 mb-2 font-medium">Medical History</label>
                        <textarea id="medical_history" name="medical_history" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    
                    <div class="mt-6">
                        <label for="family_history" class="block text-gray-700 mb-2 font-medium">Family History</label>
                        <textarea id="family_history" name="family_history" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"></textarea>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="reset" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i>Reset
                        </button>
                        <button type="submit" name="add_patient" class="bg-primary text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-save mr-2"></i>Add Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View/Edit Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-semibold text-secondary">Patient Health Information</h3>
                <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div id="modalContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 flex justify-end space-x-4">
    <button onclick="closeViewModal()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
        <i class="fas fa-times mr-2"></i>Close
    </button>
    <button onclick="printPatientRecord()" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-blue-700 transition">
        <i class="fas fa-print mr-2"></i>Print
    </button>
    <button onclick="exportToExcel()" class="px-4 py-2 bg-success text-white rounded-md hover:bg-green-700 transition">
        <i class="fas fa-file-excel mr-2"></i>Excel
    </button>
    <button onclick="exportToPDF()" class="px-4 py-2 bg-danger text-white rounded-md hover:bg-red-700 transition">
        <i class="fas fa-file-pdf mr-2"></i>PDF
    </button>
</div>
        </div>
    </div>

    <script>
        // Consolidated export/print functions
        function printPatientRecord() {
            const patientId = getPatientId();
            if (patientId) {
                window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank');
            } else {
                alert('No patient selected');
            }
        }

        function exportToExcel() {
            const patientId = getPatientId();
            if (patientId) {
                window.location.href = `/community-health-tracker/api/export_patient.php?id=${patientId}&format=excel`;
            } else {
                alert('No patient selected');
            }
        }

        function exportToPDF() {
            const patientId = getPatientId();
            if (patientId) {
                window.location.href = `/community-health-tracker/api/export_patient.php?id=${patientId}&format=pdf`;
            } else {
                alert('No patient selected');
            }
        }

        // Helper function to get patient ID
        function getPatientId() {
            // Try multiple possible selectors to find the patient ID
            const selectors = [
                '#healthInfoForm input[name="patient_id"]',
                'input[name="patient_id"]',
                '[name="patient_id"]',
                '#patient_id'
            ];
            
            for (const selector of selectors) {
                const element = document.querySelector(selector);
                if (element && element.value) {
                    return element.value;
                }
            }
            
            return null;
        }
    </script>

    <script>
        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var successMessage = document.querySelector('.bg-green-100');
                var errorMessage = document.querySelector('.bg-red-100');
                
                if (successMessage) {
                    successMessage.style.display = 'none';
                }
                
                if (errorMessage) {
                    errorMessage.style.display = 'none';
                }
            }, 3000);
        });
        
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            const tabTriggers = document.querySelectorAll('.tab-trigger');
            
            // Handle tab button clicks
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    // Update active tab button
                    tabButtons.forEach(btn => btn.classList.remove('border-primary', 'text-primary'));
                    button.classList.add('border-primary', 'text-primary');
                    
                    // Show active tab content
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');
                    
                    // Update URL with tab parameter
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });
            
            // Handle external tab triggers
            tabTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    const tabId = trigger.getAttribute('data-tab');
                    
                    // Update active tab button
                    tabButtons.forEach(btn => {
                        if (btn.getAttribute('data-tab') === tabId) {
                            btn.classList.add('border-primary', 'text-primary');
                        } else {
                            btn.classList.remove('border-primary', 'text-primary');
                        }
                    });
                    
                    // Show active tab content
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');
                    
                    // Update URL with tab parameter
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });
            
            // Check if URL has tab parameter
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabButton = document.querySelector(`.tab-btn[data-tab="${tabParam}"]`);
                if (tabButton) tabButton.click();
            }
        });
        
        // Open view modal
        function openViewModal(patientId) {
            // Show loading state
            document.getElementById('modalContent').innerHTML = `
                <div class="flex justify-center items-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-primary"></i>
                </div>
            `;
            
            // Show modal
            document.getElementById('viewModal').style.display = 'flex';
            
            // Load patient data via AJAX - updated path for consistency
            fetch(`./get_patient_data.php?id=${patientId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-8 text-danger">
                            <i class="fas fa-exclamation-circle text-3xl mb-3"></i>
                            <p>Error loading patient data. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        // Close view modal
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeViewModal();
            }
        };
        
        // Clear search on page refresh
        if (window.history.replaceState && !window.location.search.includes('search=')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>