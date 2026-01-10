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

// Check if columns exist in the database
$civilStatusExists = false;
$occupationExists = false;
$sitioExists = false;
$dateOfBirthExists = false;

try {
    // Check if civil_status column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'civil_status'");
    $stmt->execute();
    $civilStatusExists = $stmt->rowCount() > 0;
    
    // Check if occupation column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'occupation'");
    $stmt->execute();
    $occupationExists = $stmt->rowCount() > 0;
    
    // Check if sitio column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'sitio'");
    $stmt->execute();
    $sitioExists = $stmt->rowCount() > 0;
    
    // Check if date_of_birth column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'date_of_birth'");
    $stmt->execute();
    $dateOfBirthExists = $stmt->rowCount() > 0;
    
    // If date_of_birth column doesn't exist, add it
    if (!$dateOfBirthExists) {
        $pdo->exec("ALTER TABLE sitio1_patients ADD COLUMN date_of_birth DATE NULL AFTER full_name");
        $dateOfBirthExists = true;
    }
    
    // Check if deleted_patients table exists, if not create it
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'deleted_patients'");
    $stmt->execute();
    $deletedPatientsTableExists = $stmt->rowCount() > 0;

    if (!$deletedPatientsTableExists) {
        // Create deleted_patients table with ALL columns that might exist in sitio1_patients
        $createTableQuery = "CREATE TABLE deleted_patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_id INT NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            date_of_birth DATE NULL,
            age INT,
            gender VARCHAR(50),
            address TEXT,
            contact VARCHAR(100),
            last_checkup DATE,
            added_by INT,
            user_id INT,
            deleted_by INT,
            -- Auto-generated timestamps
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            -- Optional columns from sitio1_patients
            sitio VARCHAR(255) NULL,
            civil_status VARCHAR(100) NULL,
            occupation VARCHAR(255) NULL,
            consent_given TINYINT(1) DEFAULT 1,
            consent_date TIMESTAMP NULL,
            deleted_reason VARCHAR(500) NULL
        )";
        
        $pdo->exec($createTableQuery);
    }
} catch (PDOException $e) {
    // If we can't check columns, assume they don't exist
    $civilStatusExists = false;
    $occupationExists = false;
    $sitioExists = false;
    $dateOfBirthExists = false;
}

// Handle form submission for editing health info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
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
            $temperature = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
            $blood_pressure = !empty($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null;
            $allergies = !empty($_POST['allergies']) ? $_POST['allergies'] : null;
            $medical_history = !empty($_POST['medical_history']) ? $_POST['medical_history'] : null;
            $current_medications = !empty($_POST['current_medications']) ? $_POST['current_medications'] : null;
            $family_history = !empty($_POST['family_history']) ? $_POST['family_history'] : null;
            $immunization_record = !empty($_POST['immunization_record']) ? $_POST['immunization_record'] : null;
            $chronic_conditions = !empty($_POST['chronic_conditions']) ? $_POST['chronic_conditions'] : null;

            // Check if record exists
            $stmt = $pdo->prepare("SELECT id FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                    gender = ?, height = ?, weight = ?, blood_type = ?, temperature = ?, 
                    blood_pressure = ?, allergies = ?, medical_history = ?, 
                    current_medications = ?, family_history = ?, immunization_record = ?,
                    chronic_conditions = ?, updated_at = NOW()
                    WHERE patient_id = ?");
                $stmt->execute([
                    $gender, $height, $weight, $blood_type, $temperature,
                    $blood_pressure, $allergies, $medical_history, 
                    $current_medications, $family_history, $immunization_record,
                    $chronic_conditions, $patient_id
                ]);
                
                // Also update the gender in the main patient table
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET gender = ? WHERE id = ?");
                $stmt->execute([$gender, $patient_id]);
                
                $message = "Patient health information updated successfully!";
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type, temperature,
                    blood_pressure, allergies, medical_history, current_medications, 
                    family_history, immunization_record, chronic_conditions)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $patient_id, $gender, $height, $weight, $blood_type, $temperature,
                    $blood_pressure, $allergies, $medical_history, 
                    $current_medications, $family_history, $immunization_record,
                    $chronic_conditions
                ]);
                
                // Also update the gender in the main patient table
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET gender = ? WHERE id = ?");
                $stmt->execute([$gender, $patient_id]);
                
                $message = "Patient health information saved successfully!";
            }
            
        } catch (PDOException $e) {
            $error = "Error saving patient health information: " . $e->getMessage();
        }
    }
}

// Handle form submission for adding new patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $fullName = trim($_POST['full_name']);
    $dateOfBirth = trim($_POST['date_of_birth']);
    $age = intval($_POST['age']);
    $gender = trim($_POST['gender']);
    $civil_status = trim($_POST['civil_status']);
    $occupation = trim($_POST['occupation']);
    $address = trim($_POST['address']);
    $sitio = trim($_POST['sitio']);
    $contact = trim($_POST['contact']);
    $lastCheckup = trim($_POST['last_checkup']);
    $consent_given = 1; // Always give consent since we removed the checkbox
    $userId = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    // Medical information
    $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $temperature = !empty($_POST['temperature']) ? floatval($_POST['temperature']) : null;
    $blood_pressure = trim($_POST['blood_pressure']);
    $bloodType = trim($_POST['blood_type']);
    $allergies = trim($_POST['allergies']);
    $medicalHistory = trim($_POST['medical_history']);
    $currentMedications = trim($_POST['current_medications']);
    $familyHistory = trim($_POST['family_history']);
    $immunizationRecord = trim($_POST['immunization_record']);
    $chronicConditions = trim($_POST['chronic_conditions']);
    
    if (!empty($fullName) && !empty($dateOfBirth)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Build dynamic INSERT query based on available columns
            $columns = ["full_name", "date_of_birth", "age", "gender", "address", "contact", "last_checkup", "consent_given", "consent_date", "added_by", "user_id"];
            $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "NOW()", "?", "?"];
            $values = [$fullName, $dateOfBirth, $age, $gender, $address, $contact, $lastCheckup, $consent_given, $_SESSION['user']['id'], $userId];
            
            if ($sitioExists) {
                $columns[] = "sitio";
                $placeholders[] = "?";
                $values[] = $sitio;
            }
            
            if ($civilStatusExists) {
                $columns[] = "civil_status";
                $placeholders[] = "?";
                $values[] = $civil_status;
            }
            
            if ($occupationExists) {
                $columns[] = "occupation";
                $placeholders[] = "?";
                $values[] = $occupation;
            }
            
            $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            
            // Insert into main patients table
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute($values);
            $patientId = $pdo->lastInsertId();
            
            // Insert into medical info table
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, gender, height, weight, temperature, blood_pressure, 
                blood_type, allergies, medical_history, current_medications, 
                family_history, immunization_record, chronic_conditions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, $gender, $height, $weight, $temperature, $blood_pressure,
                $bloodType, $allergies, $medicalHistory, $currentMedications, 
                $familyHistory, $immunizationRecord, $chronicConditions
            ]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Patient record added successfully!';
            header('Location: existing_info_patients.php?tab=patients-tab');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding patient record: ' . $e->getMessage();
        }
    } else {
        $error = 'Full name and date of birth are required.';
    }
}

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle patient deletion
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
            // Get column information from deleted_patients table
            $stmt = $pdo->prepare("SHOW COLUMNS FROM deleted_patients");
            $stmt->execute();
            $deletedTableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter columns that exist in both source and destination
            $columns = [];
            $placeholders = [];
            $values = [];
            
            foreach ($patient as $column => $value) {
                // Skip id column as we want to use original_id instead
                if ($column === 'id') {
                    $columns[] = 'original_id';
                    $placeholders[] = '?';
                    $values[] = $value;
                    continue;
                }
                
                // Only include columns that exist in deleted_patients table
                // and are not auto-generated
                if (in_array($column, $deletedTableColumns) && 
                    !in_array($column, ['id', 'deleted_at'])) { // Exclude auto columns
                    $columns[] = $column;
                    $placeholders[] = '?';
                    $values[] = $value;
                }
            }
            
            // Add deleted_by column
            $columns[] = 'deleted_by';
            $placeholders[] = '?';
            $values[] = $_SESSION['user']['id'];
            
            // Note: deleted_at is handled by DEFAULT CURRENT_TIMESTAMP
            
            $insertQuery = "INSERT INTO deleted_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            
            // Debug: Uncomment to see the generated query
            // error_log("Delete Query: " . $insertQuery);
            // error_log("Values: " . implode(', ', $values));
            
            // Insert into deleted_patients table
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute($values);
            
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

// Handle patient restoration
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
            // Get column information from sitio1_patients table
            $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients");
            $stmt->execute();
            $mainTableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter columns that exist in both source and destination
            $columns = [];
            $placeholders = [];
            $values = [];
            
            foreach ($archivedPatient as $column => $value) {
                // Skip columns that don't exist in sitio1_patients
                if (!in_array($column, $mainTableColumns)) {
                    continue;
                }
                
                // Map original_id back to id
                if ($column === 'original_id') {
                    $columns[] = 'id';
                    $placeholders[] = "?";
                    $values[] = $value;
                    continue;
                }
                
                // Skip metadata columns from deleted_patients
                if (in_array($column, ['deleted_by', 'deleted_at', 'id', 'created_at'])) {
                    continue;
                }
                
                $columns[] = $column;
                $placeholders[] = "?";
                $values[] = $value;
            }
            
            $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            
            // Debug: Uncomment to see the generated query
            // error_log("Restore Query: " . $insertQuery);
            // error_log("Values: " . implode(', ', $values));
            
            // Restore to main patients table
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute($values);
            
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

// Handle converting user to patient
if (isset($_GET['convert_to_patient'])) {
    $userId = $_GET['convert_to_patient'];
    
    try {
        // Get user details including gender and sitio
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
                
                // Get gender from user table
                $userGender = '';
                if (!empty($user['gender'])) {
                    $userGender = $user['gender'];
                    // Convert enum values to proper format
                    if ($userGender === 'male') $userGender = 'Male';
                    if ($userGender === 'female') $userGender = 'Female';
                    if ($userGender === 'other') $userGender = 'Other';
                }
                
                // Build dynamic INSERT query for conversion based on available columns
                $columns = ["full_name", "date_of_birth", "age", "gender", "address", "contact", "added_by", "user_id", "consent_given", "consent_date"];
                $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "1", "NOW()"];
                $values = [
                    $user['full_name'], 
                    $user['date_of_birth'],
                    $user['age'], 
                    $userGender, 
                    $user['address'], 
                    $user['contact'],
                    $_SESSION['user']['id'], 
                    $userId
                ];
                
                // Add optional columns only if they exist in the user data
                if ($sitioExists && isset($user['sitio'])) {
                    $columns[] = "sitio";
                    $placeholders[] = "?";
                    $values[] = $user['sitio'];
                }
                
                if ($civilStatusExists && isset($user['civil_status'])) {
                    $columns[] = "civil_status";
                    $placeholders[] = "?";
                    $values[] = $user['civil_status'];
                }
                
                if ($occupationExists && isset($user['occupation'])) {
                    $columns[] = "occupation";
                    $placeholders[] = "?";
                    $values[] = $user['occupation'];
                }
                
                $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
                
                // Insert into main patients table with gender from user
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute($values);
                $patientId = $pdo->lastInsertId();
                
                // Insert medical info with gender from user
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type) 
                    VALUES (?, ?, 0, 0, '')");
                $stmt->execute([$patientId, $userGender]);
                
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

// Get list of patients matching search
$patients = [];
$searchedUsers = [];
if (!empty($searchTerm)) {
    try {
        if ($searchBy === 'unique_number') {
            // Search by unique number from sitio1_users table
            $query = "SELECT id, full_name, email, date_of_birth, age, gender, ";
            
            // Check if columns exist before including them
            $checkCols = $pdo->prepare("SHOW COLUMNS FROM sitio1_users LIKE 'civil_status'");
            $checkCols->execute();
            if ($checkCols->rowCount() > 0) {
                $query .= "civil_status, ";
            }
            
            $checkCols = $pdo->prepare("SHOW COLUMNS FROM sitio1_users LIKE 'occupation'");
            $checkCols->execute();
            if ($checkCols->rowCount() > 0) {
                $query .= "occupation, ";
            }
            
            $query .= "address, sitio, contact, unique_number, 'user' as type
                     FROM sitio1_users 
                     WHERE approved = 1 AND unique_number LIKE ? 
                     ORDER BY full_name LIMIT 10";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute(["%$searchTerm%"]);
            $searchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Search by name (default) - FIXED THE SQL SYNTAX ERROR
            $selectQuery = "SELECT p.id, p.full_name, p.date_of_birth, p.age, p.gender, e.blood_type, e.height, e.weight, e.temperature, e.blood_pressure
                     FROM sitio1_patients p 
                     LEFT JOIN existing_info_patients e ON p.id = e.patient_id 
                     WHERE p.added_by = ? AND p.full_name LIKE ? 
                     ORDER BY p.full_name LIMIT 10";
            
            $stmt = $pdo->prepare($selectQuery);
            $stmt->execute([$_SESSION['user']['id'], "%$searchTerm%"]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching patients: " . $e->getMessage();
    }
}

// Check if we're viewing all records
$viewAll = isset($_GET['view_all']) && $_GET['view_all'] == 'true';

// Pagination setup
$recordsPerPage = 5;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Get total count of patients
try {
    $countQuery = "SELECT COUNT(*) as total FROM sitio1_patients p 
                   WHERE p.added_by = ? AND p.deleted_at IS NULL";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute([$_SESSION['user']['id']]);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
} catch (PDOException $e) {
    $error = "Error counting patient records: " . $e->getMessage();
    $totalRecords = 0;
    $totalPages = 1;
}

// Get all patients with their medical info for the patient list with pagination or all records
try {
    // Build dynamic SELECT query - FIXED to properly handle registered user data
    $selectQuery = "SELECT 
                p.id,
                p.full_name,
                -- Use patient's date_of_birth if exists, otherwise use user's
                COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth,
                -- Use patient's age if exists, otherwise use user's
                COALESCE(p.age, u.age) as age,
                -- Use patient's gender if exists, otherwise use and convert user's gender
                CASE 
                    WHEN p.gender IS NOT NULL AND p.gender != '' THEN p.gender
                    WHEN u.gender = 'male' THEN 'Male'
                    WHEN u.gender = 'female' THEN 'Female'
                    WHEN u.gender = 'other' THEN 'Other'
                    ELSE p.gender
                END as gender,
                -- Use patient's address if exists, otherwise use user's
                COALESCE(p.address, u.address) as address,
                -- Use patient's contact if exists, otherwise use user's
                COALESCE(p.contact, u.contact) as contact,
                e.height, e.weight, e.temperature, e.blood_pressure, e.blood_type, 
                e.allergies, e.immunization_record, e.chronic_conditions,
                e.medical_history, e.current_medications, e.family_history,
                u.unique_number, u.email as user_email, u.sitio as user_sitio,
                u.id as user_id";
    
    // Add patient-specific columns if they exist
    if ($sitioExists) {
        $selectQuery .= ", p.sitio";
    }
    
    if ($civilStatusExists) {
        $selectQuery .= ", p.civil_status";
    }
    
    if ($occupationExists) {
        $selectQuery .= ", p.occupation";
    }
    
    // Get user-specific columns if patient is a registered user
    $selectQuery .= ", u.civil_status as user_civil_status,
                     u.occupation as user_occupation";
    
    $selectQuery .= " FROM sitio1_patients p
              LEFT JOIN existing_info_patients e ON p.id = e.patient_id
              LEFT JOIN sitio1_users u ON p.user_id = u.id
              WHERE p.added_by = ? AND p.deleted_at IS NULL
              ORDER BY p.created_at DESC";
    
    if (!$viewAll) {
        $selectQuery .= " LIMIT ? OFFSET ?";
    }
    
    $stmt = $pdo->prepare($selectQuery);
    
    if ($viewAll) {
        $stmt->execute([$_SESSION['user']['id']]);
    } else {
        // Bind parameters with explicit types for LIMIT and OFFSET
        $stmt->bindParam(1, $_SESSION['user']['id'], PDO::PARAM_INT);
        $stmt->bindParam(2, $recordsPerPage, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $allPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error fetching patient records: " . $e->getMessage();
}

// Get existing health info if patient is selected
if (!empty($selectedPatientId)) {
    try {
        // Get basic patient info - FIXED to include user data properly
        $stmt = $pdo->prepare("SELECT 
                p.*, 
                u.unique_number, 
                u.email as user_email, 
                u.sitio as user_sitio,
                u.date_of_birth as user_date_of_birth,
                u.age as user_age,
                CASE 
                    WHEN u.gender = 'male' THEN 'Male'
                    WHEN u.gender = 'female' THEN 'Female'
                    WHEN u.gender = 'other' THEN 'Other'
                    ELSE u.gender
                END as user_gender,
                u.civil_status as user_civil_status,
                u.occupation as user_occupation
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
    <title>Patient Health Records - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/asssets/css/normalize.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                        info: '#17a2b8',
                        warmRed: '#fef2f2',
                        warmBlue: '#f0f9ff'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

body,
.tab-btn,
.patient-card,
.modal,
.patient-table,
.btn-view,
.btn-archive,
.btn-add-patient,
.btn-primary,
.btn-success,
.btn-gray,
.btn-print,
.btn-edit,
.btn-save-medical,
.pagination-btn,
.btn-view-all,
.btn-back-to-pagination,
#modalContent input,
#modalContent select,
#modalContent textarea,
.search-input,
.search-select {
    font-family: 'Poppins', sans-serif !important;
}

.form-section-title-modal {
    font-family: 'Poppins', sans-serif !important;
    font-weight: 700 !important;
}

.tab-content { display: none; }
.tab-content.active { display: block; }
.patient-card { transition: all 0.3s ease; }
.patient-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
.modal { transition: opacity 0.3s ease; }
.required-field::after { content: " *"; color: #e74c3c; }
.patient-table { width: 100%; border-collapse: collapse; }
.patient-table th, .patient-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; }
.patient-table th { background-color: #f8fafc; font-weight: 600; color: #2c3e50; }
.patient-table tr:hover { background-color: #f1f5f9; }
.patient-id { font-weight: bold; color: #3498db; }
.user-badge { background-color: #e0e7ff; color: #3730a3; display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }

/* UPDATED BUTTON STYLES - 100% opacity normally, 60% opacity on hover */
.btn-view {
    background-color: #3498db;
    color: #ffffffff;
    border: 2px solid #3498db;
    opacity: 1;
    border-radius: 30px;
    padding: 10px 20px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-view:hover {
    background-color: #50a4dbff;
    border-color: #3498db;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
}

.btn-archive {
    background-color: #e74c3c;
    color: white;
    opacity: 1;
    border-radius: 30px;
    padding: 10px 20px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-archive:hover {
    background-color: #d86154ff;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
}

.btn-add-patient {
    background-color: #2ecc71;
    color: #ffffffff;
    border: 2px solid #2ecc71;
    opacity: 1;
    border-radius: 50px;
    padding: 17px 25px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-add-patient:hover {
    background-color: #42d37eff;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
}

.btn-primary {
    background-color: #3498db;
    color: #ffffffff;
    opacity: 1;
    border-radius: 30px;
    padding: 15px 30px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 55px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.btn-primary:hover {
    background-color: #55a3d8ff;
    color: #ffffffff;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
}

.btn-success {
    background-color: white;
    color: #2ecc71;
    border: 2px solid #2ecc71;
    opacity: 1;
    border-radius: 8px;
    padding: 12px 24px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 55px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.btn-success:hover {
    background-color: #f0fdf4;
    border-color: #2ecc71;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
}

.btn-gray {
    background-color: white;
    color: #36a9dfff;
    border: 2px solid #36a9dfff;
    opacity: 1;
    border-radius: 30px;
    padding: 12px 24px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 55px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.btn-gray:hover {
    background-color: #f9fafb;
    border-color: #36a9dfff;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.15);
}

/* NEW: Print Button - Bigger and Readable */
.btn-print {
    background-color: #3498db;
    color: #ffffffff;
    opacity: 1;
    border-radius: 30px;
    padding: 14px 30px;
    transition: all 0.3s ease;
    font-weight: 600;
    min-height: 60px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    width: auto;
    margin: 8px 0;
}
.btn-print:hover {
    background-color: #3c9dddff;
    border-color: #3498db;
    opacity: 0.8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
}

/* NEW: Edit Button */
.btn-edit {
    background-color: white;
    color: #f39c12;
    border: 2px solid #f39c12;
    opacity: 1;
    border-radius: 8px;
    padding: 14px 28px;
    transition: all 0.3s ease;
    font-weight: 600;
    min-height: 60px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    width: 355px;
    margin: 8px 0;
}
.btn-edit:hover {
    background-color: #fef3c7;
    border-color: #f39c12;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.15);
}

/* NEW: Save Medical Information Button - UPDATED with warmBlue border */
.btn-save-medical {
    background-color: #50a4dbff;
    color: #ffffffff;
    opacity: 1;
    border-radius: 30px;
    padding: 14px 28px;
    transition: all 0.3s ease;
    font-weight: 600;
    min-height: 60px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 2px;
    width: auto;
    margin: 8px 0;
}
.btn-save-medical:hover {
    background-color: #59ace4ff;
    opacity: 0.6;
    transform: translateY(-2px);

}

#viewModal { backdrop-filter: blur(5px); transition: opacity 0.3s ease; }
#viewModal > div { transform: scale(0.95); transition: transform 0.3s ease; }
#viewModal[style*="display: flex"] > div { transform: scale(1); }

#viewModal ::-webkit-scrollbar { width: 8px; }
#viewModal ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
#viewModal ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
#viewModal ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

#modalContent input:not([type="checkbox"]):not([type="radio"]),
#modalContent select,
#modalContent textarea { min-height: 48px; font-size: 16px; }

#modalContent .grid { gap: 1.5rem; }

@media (max-width: 1024px) { #viewModal > div { margin: 1rem; max-height: calc(100vh - 2rem); } }
@media (max-width: 768px) { #viewModal > div { margin: 0.5rem; max-height: calc(100vh - 1rem); } #viewModal .p-8 { padding: 1.5rem; } }

.custom-notification { animation: slideIn 0.3s ease-out; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

.visit-type-badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; }
.visit-type-checkup { background-color: #e0f2fe; color: #0369a1; }
.visit-type-consultation { background-color: #fef3c7; color: #92400e; }
.visit-type-emergency { background-color: #fee2e2; color: #b91c1c; }
.visit-type-followup { background-color: #d1fae5; color: #065f46; }
.readonly-field { background-color: #f9fafb; cursor: not-allowed; }

/* Enhanced Pagination Styles */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding: 1rem 0;
    border-top: 1px solid #e2e8f0;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.8rem;
    flex-grow: 1;
}

.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 30px;
    background-color: white;
    border: 1px solid #3498db;
    opacity: 1;
    color: #4b5563;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
}

.pagination-btn:hover {
    background-color: #f0f9ff;
    border-color: #3498db;
    opacity: 0.6;
    color: #374151;
}

.pagination-btn.active {
    background-color: white;
    color: #3498db;
    border: 2px solid #3498db;
    opacity: 1;
    font-weight: 600;
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-btn.disabled:hover {
    background-color: white;
    color: #4b5563;
    border-color: #3498db;
    opacity: 0.5;
}

.pagination-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btn-view-all {
    background-color: white;
    color: #3498db;
    border: 2px solid #3498db;
    opacity: 1;
    border-radius: 30px;
    padding: 12px 24px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    min-height: 55px;
    font-size: 16px;
}

.btn-view-all:hover {
    background-color: #ffffffff;
    border: 2px solid #479ed8ff;
    opacity: 0.8;
    transform: translateY(-2px);
    
}

.btn-back-to-pagination {
    background-color: white;
    color: #3498db;
    border: 2px solid #3498db;
    opacity: 1;
    border-radius: 8px;
    padding: 12px 24px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    min-height: 55px;
    font-size: 16px;
    margin-bottom: 1rem;
}

.btn-back-to-pagination:hover {
    background-color: #f0f9ff;
    border-color: #3498db;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
}

/* Scrollable table for all records */
.scrollable-table-container {
    max-height: 70vh;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.scrollable-table-container table {
    margin-bottom: 0;
}

/* Form validation styles */
.field-empty { background-color: #fef2f2 !important; border-color: #fecaca !important; }
.field-filled { background-color: #f0f9ff !important; border-color: #bae6fd !important; }
.btn-disabled {
    background-color: #f9fafb !important;
    color: #9ca3af !important;
    border-color: #e5e7eb !important;
    opacity: 1;
    cursor: not-allowed !important;
    transform: none !important;
    box-shadow: none !important;
}
.btn-disabled:hover {
    background-color: #f9fafb !important;
    color: #9ca3af !important;
    border-color: #e5e7eb !important;
    opacity: 1;
    transform: none !important;
    box-shadow: none !important;
}

/* Enhanced Form Input Styles for Add Patient Modal - WITH STROKE EFFECT */
.form-input-modal {
    border-radius: 8px !important;
    padding: 16px 20px !important;
    border: 2px solid #e2e8f0 !important;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 16px;
    min-height: 52px;
    background-color: white;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

.form-input-modal:focus {
    outline: none;
    border-color: #3498db !important;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
    background-color: white;
}

.form-select-modal {
    border-radius: 8px !important;
    padding: 16px 20px !important;
    border: 2px solid #e2e8f0 !important;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 16px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 20px center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 50px;
    min-height: 52px;
    background-color: white;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

.form-select-modal:focus {
    outline: none;
    border-color: #3498db !important;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
    background-color: white;
}

.form-textarea-modal {
    border-radius: 8px !important;
    padding: 16px 20px !important;
    border: 2px solid #e2e8f0 !important;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 16px;
    resize: vertical;
    min-height: 120px;
    background-color: white;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
}

.form-textarea-modal:focus {
    outline: none;
    border-color: #3498db !important;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1), inset 0 1px 2px rgba(0, 0, 0, 0.05);
    background-color: white;
}

.form-label-modal {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

/* UPDATED: Changed form section title icons */
.form-section-title-modal {
    font-size: 1.25rem;
    font-weight: 700;
    color: #3498db;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #f0f9ff;
    display: flex;
    align-items: center;
}

.form-section-title-modal i {
    margin-right: 10px;
    font-size: 1.1em;
    color: #3498db;
}

.form-checkbox-modal {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 2px solid #e2e8f0;
}

/* Add Patient Modal Specific Styles */
#addPatientModal { backdrop-filter: blur(5px); transition: opacity 0.3s ease; }
#addPatientModal > div { transform: scale(0.95); transition: transform 0.3s ease; }
#addPatientModal[style*="display: flex"] > div { transform: scale(1); }

.modal-header {
    background-color: white;
    border-bottom: 2px solid #f0f9ff;
}

.modal-grid-gap {
    gap: 1.25rem !important;
}

.modal-field-spacing {
    margin-bottom: 1.25rem;
}

/* Active Tab Styling */
.tab-btn {
    position: relative;
    transition: all 0.3s ease;
}

.tab-btn.active {
    color: #3498db;
    border-bottom-color: #3498db;
    background-color: #f0f9ff;
}

.tab-btn:hover:not(.active) {
    color: #3498db;
    background-color: #f8fafc;
}

/* UPDATED Search box styling - Consistent height and width with warmBlue border */
.search-input {
    border: 2px solid #55b2f0ff !important;
    background-color: white !important;
    transition: all 0.3s ease;
    border-radius: 10px !important;
    padding: 16px 20px 16px 55px !important;
    min-height: 55px !important;
    font-size: 16px;
    width: 355px !important;
    box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.05);
    position: relative;
}

.search-input:focus {
    border-color: #84c0e9ff !important;
    box-shadow: 0 0 0 3px #8acdfaff;
}

.search-select {
    border: 2px solid #55b2f0ff !important;
    background-color: white !important;
    transition: all 0.3s ease;
    border-radius: 10px !important;
    padding: 16px 20px !important;
    min-height: 55px !important;
    font-size: 16px;
    width: 355px !important;
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233498db' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
    background-position: right 20px center;
    background-repeat: no-repeat;
    background-size: 1.5em 1.5em;
    padding-right: 50px;
    
}

.search-select:focus {
    border-color: #84c0e9ff !important;
    box-shadow: 0 0 0 3px #8acdfaff;
}

/* Search icon position */
.search-icon-container {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    pointer-events: none;
}


/* Table styling */
.patient-table th {
    background-color: #f0f9ff;
    color: #2c3e50;
    border-bottom: 2px solid #e2e8f0;
}

.patient-table tr:hover {
    background-color: #f8fafc;
}

/* Main container styling */
.main-container {
    background-color: white;
    border: 1px solid #f0f9ff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

/* Section backgrounds */
.section-bg {
    background-color: white;
    border: 1px solid #f0f9ff;
    border-radius: 12px;
}

/* Success/Error message styling */
.alert-success {
    background-color: #f0fdf4;
    border: 2px solid #bbf7d0;
    color: #065f46;
}

.alert-error {
    background-color: #fef2f2;
    border: 2px solid #fecaca;
    color: #b91c1c;
}

/* Search form layout */
.search-form-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
}

.search-field-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

@media (min-width: 768px) {
    .search-form-container {
        flex-direction: row;
        align-items: flex-end;
        gap: 1.5rem;
    }

    .search-field-group {
        width: auto;
    }
}

/* Ensure opacity is visible */
* {
    --tw-border-opacity: 1 !important;
}

/* Fix for browsers that don't support opacity */
  .btn-success,
.btn-print, .btn-edit, .btn-view-all, .btn-back-to-pagination, 
.search-input, .search-select {
    border-style: solid !important;
}

/* Hover state opacity */
.btn-archive:hover, 
.btn-success:hover, .btn-edit:hover, .btn-back-to-pagination:hover,
.search-input:focus, .search-select:focus {
    border-color: inherit !important;
}

/* Add these styles to your existing CSS in existing_info_patients.php */

/* Success modal specific styles */
.success-modal-bg {
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}

/* Gradient backgrounds */
.bg-gradient-success {
    background: linear-gradient(135deg, #d4edda 0%, #f0fdf4 100%);
}

.bg-gradient-blue {
    background: linear-gradient(135deg, #dbeafe 0%, #f0f9ff 100%);
}

/* Animation keyframes */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes scaleIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}

.animate-scaleIn {
    animation: scaleIn 0.3s ease-out;
}

.pulse-animation {
    animation: pulse 2s ease-in-out;
}

.shake-animation {
    animation: shake 0.5s ease-in-out;
}

/* Success Modal Button Styles */
#successModal  {
    background-color: white;
    color: #3498db;
    border: 2px solid #3498db;
    opacity: 1;
    border-radius: 8px;
    padding: 10px 20px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

#successModal {
    background-color: #f0f9ff;
    border-color: #3498db;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
}

#successModal .btn-primary {
    background-color: white;
    color: #2ecc71;
    border: 2px solid #2ecc71;
    opacity: 1;
    border-radius: 30px;
    padding: 10px 24px;
    transition: all 0.3s ease;
    font-weight: 500;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

#successModal .btn-primary:hover {

    border-color: #2ecc71;
    opacity: 0.6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
}

/* Ensure z-index for modal */
#successModal {
    z-index: 9999;
}

/* Smooth transitions for all modals */
.modal-transition {
    transition: all 0.3s ease;
}

/* UPDATED: Added opacity support for borders */
.btn-view, .btn-archive,  .btn-primary, .btn-success, .btn-gray,
.btn-print, .btn-edit, .btn-save-medical, .btn-view-all, .btn-back-to-pagination, .pagination-btn {
    position: relative;
}

.btn-view::after, .btn-archive::after, .btn-primary::after,
.btn-success::after, .btn-gray::after, .btn-print::after, .btn-edit::after,
.btn-view-all::after, .btn-back-to-pagination::after, {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: 8px;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.btn-add-patient::after { border: 2px solid #2ecc71; opacity: 1; }
.btn-success::after { border: 2px solid #2ecc71; opacity: 1; }
.btn-edit::after { border: 2px solid #f39c12; opacity: 1; }
.btn-back-to-pagination::after { border: 2px solid #3498db; opacity: 1; }
.pagination-btn::after { border: 1px solid #3498db; opacity: 1; }

.btn-view:hover::after, .btn-archive:hover::after, .btn-add-patient:hover::after,
 .btn-success:hover::after,
 .btn-edit:hover::after,
.btn-view-all:hover::after, .btn-back-to-pagination:hover::after, .pagination-btn:hover::after {
    opacity: 0.6;
}
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-1">
        <h1 class="text-3xl font-semibold mb-6 text-secondary">Resident Patient Records</h1>
        
        <?php if ($message): ?>
            <div id="successMessage" class="alert-success px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error px-4 py-3 rounded mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <div class="main-container rounded-lg shadow-sm mb-8">
            <div class="flex border-b border-gray-200">
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
                    <a href="deleted_patients.php" class="btn-gray inline-flex items-center">
                        <i class="fas fa-archive mr-2"></i>View Archive
                    </a>
                </div>
                
                <!-- UPDATED Search Form with warmBlue border and separated icon -->
                <form method="get" action="" class="mb-6 section-bg p-6">
                    <input type="hidden" name="tab" value="patients-tab">
                    <?php if ($viewAll): ?>
                        <input type="hidden" name="view_all" value="true">
                    <?php endif; ?>
                    
                    <div class="search-form-container">
                        <!-- Search By Field -->
                        <div class="search-field-group">
                            <label for="search_by" class="block text-gray-700 mb-2 font-medium">Search By</label>
                            <div class="relative">
                                <select id="search_by" name="search_by" class="search-select">
                                    <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>>Name</option>
                                    <option value="unique_number" <?= $searchBy === 'unique_number' ? 'selected' : '' ?>>Unique Number</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Search Term Field with icon inside input -->
<div class="search-field-group flex-grow">
    <label for="search" class="block text-gray-700 mb-2 font-medium">
        Search Term
    </label>

    <div class="relative">
        <!-- Search Icon -->
        <i class="fa-solid fa-magnifying-glass
                  absolute left-7 top-1/2 -translate-y-1/2
                  text-gray-500 pointer-events-none z-10"></i>

        <!-- Input -->
        <input type="text"
               id="search"
               name="search"
               value="<?= htmlspecialchars($searchTerm) ?>"
               placeholder="<?= $searchBy === 'unique_number' ? 'Enter Patients Name...' : 'Search patients by name...' ?>"
               class="search-input w-full pl-11 pr-4 py-2 rounded-lg border
                      focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>


                        
                        <!-- Search Buttons -->
                        <div class="search-field-group flex flex-col sm:flex-row gap-2 mt-2 sm:mt-0">

    <?php if (empty($searchTerm)): ?>
        <!-- Show Search button ONLY when there is NO search term -->
        <button type="submit" class="btn-primary min-w-[120px]">
            <i class="fas fa-search mr-2"></i> Search
        </button>
    <?php else: ?>
        <!-- Show Clear button ONLY when search term EXISTS -->
        <a href="existing_info_patients.php" class="btn-gray min-w-[120px] text-center">
            <i class="fas fa-times mr-2"></i> Clear
        </a>
    <?php endif; ?>

</div>

                    </div>
                </form>
                
                <?php if (!empty($searchTerm)): ?>
                    <div class="section-bg overflow-hidden mb-6">
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
                                                    <th>Date of Birth</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <?php if ($sitioExists): ?>
                                                    <th>Sitio</th>
                                                    <?php endif; ?>
                                                    <?php if ($civilStatusExists): ?>
                                                    <th>Civil Status</th>
                                                    <?php endif; ?>
                                                    <?php if ($occupationExists): ?>
                                                    <th>Occupation</th>
                                                    <?php endif; ?>
                                                    <th>Blood Type</th>
                                                    <th>Type</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($patients as $index => $patient): ?>
                                                    <tr>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                        <td>
                                                            <?php 
                                                            // Display date of birth properly
                                                            if (!empty($patient['date_of_birth'])) {
                                                                echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                        <td>
                                                            <?php 
                                                            // Display gender properly
                                                            if (!empty($patient['gender'])) {
                                                                if ($patient['gender'] === 'male') echo 'Male';
                                                                elseif ($patient['gender'] === 'female') echo 'Female';
                                                                else echo htmlspecialchars($patient['gender']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <?php if ($sitioExists): ?>
                                                        <td><?= htmlspecialchars($patient['sitio'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <?php if ($civilStatusExists): ?>
                                                        <td><?= htmlspecialchars($patient['civil_status'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <?php if ($occupationExists): ?>
                                                        <td><?= htmlspecialchars($patient['occupation'] ?? 'N/A') ?></td>
                                                        <?php endif; ?>
                                                        <td class="font-semibold text-primary"><?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?></td>
                                                        <td>
                                                            <span class="text-gray-500">Regular Patient</span>
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
                                                    <th>Date of Birth</th>
                                                    <th>Age</th>
                                                    <th>Gender</th>
                                                    <th>Occupation</th>
                                                    <th>Sitio</th>
                                                    <th>Contact</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($searchedUsers as $index => $user): ?>
                                                    <tr>
                                                        <td class="patient-id"><?= $index + 1 ?></td>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td>
                                                            <?php 
                                                            // Display date of birth from user table
                                                            if (!empty($user['date_of_birth'])) {
                                                                echo date('M d, Y', strtotime($user['date_of_birth']));
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= $user['age'] ?? 'N/A' ?></td>
                                                        <td>
                                                            <?php 
                                                            // Display gender properly from user table
                                                            if (!empty($user['gender'])) {
                                                                if ($user['gender'] === 'male') echo 'Male';
                                                                elseif ($user['gender'] === 'female') echo 'Female';
                                                                else echo htmlspecialchars($user['gender']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($user['occupation'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['sitio'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['contact'] ?? 'N/A') ?></td>
                                                 
                                                        <td>
                                                            <a href="?convert_to_patient=<?= $user['id'] ?>" class="btn-add-patient inline-flex items-center" onclick="return confirm('Are you sure you want to add this user as a patient?')">
                                                            <i class="fa-solid fa-plus text-1xl mr-3"></i>Include Patient
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
                <div class="section-bg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-secondary">
                            <?= $viewAll ? 'All Patient Records' : 'Patient Records' ?>
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">
                            <?php if ($viewAll): ?>
                                Showing all <?= count($allPatients) ?> records
                            <?php else: ?>
                                Showing <?= count($allPatients) ?> of <?= $totalRecords ?> records
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (empty($allPatients)): ?>
                        <div class="text-center py-12 bg-gray-50 rounded-lg">
                            <i class="fa-solid fa-bed text-6xl mb-4 text-gray-300"></i>
                            <h3 class="text-lg font-medium text-gray-900">No patients found</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by adding a new patient.</p>
                            <div class="mt-6">
                                <button data-tab="add-tab" class="tab-trigger btn-primary inline-flex items-center">
                                    <i class="fas fa-plus-circle mr-2"></i>Add Patient
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($viewAll): ?>
                            <div class="p-4">
                                <a href="existing_info_patients.php?tab=patients-tab" class="btn-back-to-pagination inline-flex items-center">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Pagination View
                                </a>
                                <div class="scrollable-table-container">
                                    <table class="patient-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Date of Birth</th>
                                                <th>Age</th>
                                                <th>Gender</th>
                                                <?php if ($sitioExists): ?>
                                                <th>Sitio</th>
                                                <?php endif; ?>
                                                <?php if ($civilStatusExists): ?>
                                                <th>Civil Status</th>
                                                <?php endif; ?>
                                                <?php if ($occupationExists): ?>
                                                <th>Occupation</th>
                                                <?php endif; ?>
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
                                                    <td>
                                                        <?php 
                                                        // Display date of birth properly - for registered users, it comes from user table
                                                        if (!empty($patient['date_of_birth'])) {
                                                            echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                    <td>
                                                        <?php 
                                                        // Display gender properly
                                                        if (!empty($patient['gender'])) {
                                                            echo htmlspecialchars($patient['gender']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php if ($sitioExists): ?>
                                                    <td>
                                                        <?php 
                                                        // Show sitio from patient table if exists, otherwise from user table
                                                        if (!empty($patient['sitio'])) {
                                                            echo htmlspecialchars($patient['sitio']);
                                                        } elseif (!empty($patient['user_sitio'])) {
                                                            echo htmlspecialchars($patient['user_sitio']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php endif; ?>
                                                    <?php if ($civilStatusExists): ?>
                                                    <td>
                                                        <?php 
                                                        // Show civil status from patient table if exists, otherwise from user table
                                                        if (!empty($patient['civil_status'])) {
                                                            echo htmlspecialchars($patient['civil_status']);
                                                        } elseif (!empty($patient['user_civil_status'])) {
                                                            echo htmlspecialchars($patient['user_civil_status']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php endif; ?>
                                                    <?php if ($occupationExists): ?>
                                                    <td>
                                                        <?php 
                                                        // Show occupation from patient table if exists, otherwise from user table
                                                        if (!empty($patient['occupation'])) {
                                                            echo htmlspecialchars($patient['occupation']);
                                                        } elseif (!empty($patient['user_occupation'])) {
                                                            echo htmlspecialchars($patient['user_occupation']);
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php endif; ?>
                                                    <td class="font-semibold text-primary"><?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php if (!empty($patient['unique_number'])): ?>
                                                            <span class="user-badge">Registered User</span>
                                                            <?php if (!empty($patient['user_sitio'])): ?>
                                                                <br><small class="text-gray-500"><?= htmlspecialchars($patient['user_sitio']) ?></small>
                                                            <?php endif; ?>
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
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="patient-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Date of Birth</th>
                                            <th>Age</th>
                                            <th>Gender</th>
                                            <?php if ($sitioExists): ?>
                                            <th>Sitio</th>
                                            <?php endif; ?>
                                            <?php if ($civilStatusExists): ?>
                                            <th>Civil Status</th>
                                            <?php endif; ?>
                                            <?php if ($occupationExists): ?>
                                            <th>Occupation</th>
                                            <?php endif; ?>
                                            <th>Blood Type</th>
                                            <th>Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allPatients as $index => $patient): ?>
                                            <tr>
                                                <td class="patient-id"><?= $offset + $index + 1 ?></td>
                                                <td><?= htmlspecialchars($patient['full_name']) ?></td>
                                                <td>
                                                    <?php 
                                                    // Display date of birth properly - for registered users, it comes from user table
                                                    if (!empty($patient['date_of_birth'])) {
                                                        echo date('M d, Y', strtotime($patient['date_of_birth']));
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                <td>
                                                    <?php 
                                                    // Display gender properly
                                                    if (!empty($patient['gender'])) {
                                                        echo htmlspecialchars($patient['gender']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <?php if ($sitioExists): ?>
                                                <td>
                                                    <?php 
                                                    // Show sitio from patient table if exists, otherwise from user table
                                                    if (!empty($patient['sitio'])) {
                                                        echo htmlspecialchars($patient['sitio']);
                                                    } elseif (!empty($patient['user_sitio'])) {
                                                        echo htmlspecialchars($patient['user_sitio']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <?php endif; ?>
                                                <?php if ($civilStatusExists): ?>
                                                <td>
                                                    <?php 
                                                    // Show civil status from patient table if exists, otherwise from user table
                                                    if (!empty($patient['civil_status'])) {
                                                        echo htmlspecialchars($patient['civil_status']);
                                                    } elseif (!empty($patient['user_civil_status'])) {
                                                        echo htmlspecialchars($patient['user_civil_status']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <?php endif; ?>
                                                <?php if ($occupationExists): ?>
                                                <td>
                                                    <?php 
                                                    // Show occupation from patient table if exists, otherwise from user table
                                                    if (!empty($patient['occupation'])) {
                                                        echo htmlspecialchars($patient['occupation']);
                                                    } elseif (!empty($patient['user_occupation'])) {
                                                        echo htmlspecialchars($patient['user_occupation']);
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </td>
                                                <?php endif; ?>
                                                <td class="font-semibold text-primary"><?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if (!empty($patient['unique_number'])): ?>
                                                        <span class="user-badge">Registered User</span>
                                                        <?php if (!empty($patient['user_sitio'])): ?>
                                                            <br><small class="text-gray-500"><?= htmlspecialchars($patient['user_sitio']) ?></small>
                                                        <?php endif; ?>
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
                            
                            <!-- Enhanced Pagination Container -->
                            <div class="pagination-container">
                                <div class="pagination">
                                    <!-- Previous Button -->
                                    <a href="?tab=patients-tab&page=<?= $currentPage - 1 ?>" class="pagination-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    
                                    <!-- Page Numbers -->
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)): ?>
                                            <a href="?tab=patients-tab&page=<?= $i ?>" class="pagination-btn <?= $i == $currentPage ? 'active' : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php elseif ($i == $currentPage - 2 || $i == $currentPage + 2): ?>
                                            <span class="pagination-btn disabled">...</span>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <!-- Next Button -->
                                    <a href="?tab=patients-tab&page=<?= $currentPage + 1 ?>" class="pagination-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                                
                                <div class="pagination-actions">
                                    <a href="?tab=patients-tab&view_all=true" class="btn-view-all">
                                        <i class="fas fa-list mr-2"></i>View All Patients
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Patient Tab - UPDATED: Now only shows a button to open modal -->
            <div id="add-tab" class="tab-content p-6">
                <div class="text-center py-12">
                    <div class="max-w-md mx-auto">
                        <div class="section-bg p-8 mb-8">
                            <div class="w-20 h-20 bg-white flex items-center justify-center mx-auto mb-6">
                                <i class="fa-solid fa-fill text-8xl text-gray-300"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-secondary mb-4">Register New Patient</h2>
                            <p class="text-gray-600 mb-8">
                                Add a new patient record to the system. Fill out all required information including personal details and medical history.
                            </p>
                            <button onclick="openAddPatientModal()" class="btn-primary px-8 py-4 round-full text-lg font-semibold">
                                <i class="fas fa-plus-circle mr-3"></i>Add New Patient
                            </button>
                        </div>
                        <!-- <div class="text-sm text-gray-500 mt-6">
                            <p class="flex items-center justify-center mb-2">
                                <i class="fas fa-info-circle mr-2 text-primary"></i>
                                All patient records are kept confidential and secure
                            </p>
                            <p class="flex items-center justify-center">
                                <i class="fas fa-shield-alt mr-2 text-primary"></i>
                                Compliant with Data Privacy Act of 2012 (RA 10173)
                            </p>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Wider Modal for Viewing Patient Info -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col border-2 border-primary">
            <!-- Sticky Header -->
            <div class="p-8 border-b border-primary flex justify-between items-center bg-white rounded-t-2xl sticky top-0 z-10">
                <h3 class="text-2xl font-semibold flex items-center  px-4 py-3 rounded-lg">
    <i class="fa-solid fa-circle-info mr-4 text-5xl text-[#3498db]"></i>
    <span class="text-[#3498db]">Patient Health Information</span>
</h3>



                <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full w-10 h-10 flex items-center justify-center transition duration-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Scrollable Content -->
            <div class="p-8 bg-gray-50 flex-1 overflow-y-auto">
                <div id="modalContent" class="min-h-[500px]">
                    <!-- Content will be loaded via AJAX -->
                    <div class="flex justify-center items-center py-20">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin text-5xl text-primary mb-4"></i>
                            <p class="text-lg text-gray-600 font-medium">Loading patient data...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sticky Footer - UPDATED: Added Edit Button -->
            <div class="p-8 border-t border-gray-200 bg-white rounded-b-2xl sticky bottom-0">
                <div class="flex flex-wrap justify-center items-center gap-6">
                    <div class="flex flex-col items-center">
                        <button onclick="printPatientRecord()" class="btn-print">
                            <i class="fas fa-print mr-3"></i>Print Patient Record
                        </button>
                        
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="text-md text-gray-500 bg-gray-100 px-8 py-5 rounded-full">
                            <i class="fas fa-info-circle mr-3 text-1xl"></i>View and edit patient information
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NEW: Add Patient Modal (DESIGN ENHANCED – FUNCTIONALITY RETAINED) -->
<div id="addPatientModal"
     class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 modal"
     style="display:none;">

    <div class="bg-white rounded-lg shadow-2xl w-full max-w-7xl h-[92vh] overflow-hidden flex flex-col border border-blue-200">

        <!-- ================= HEADER ================= -->
        <div class="modal-header sticky top-0 z-20 bg-gradient-to-r from-blue-600 to-blue-500 px-10 py-6 flex justify-between items-center">
            <h3 class="text-2xl  flex items-center text-white">
                <i class="fa-solid fa-address-card text-4xl mr-4"></i>
                Register New Patient
            </h3>
            <button onclick="closeAddPatientModal()"
                    class="bg-white/20 hover:bg-white/30 rounded-full w-12 h-12 flex items-center justify-center transition">
                <i class="fas fa-times text-xl text-white"></i>
            </button>
        </div>

        <!-- ================= CONTENT ================= -->
        <div class="p-10 bg-blue-50 flex-1 overflow-y-auto">
            <form method="POST" action="" id="patientForm" enctype="multipart/form-data" class="space-y-10">

                <!-- ================= PERSONAL INFORMATION ================= -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-blue-100">
                    <h3 class="text-2xl font-bold text-blue-700 mb-8 flex items-center">
                        <i class="fa-solid fa-circle-info text-4xl mr-3 text-blue-500"></i>
                        Personal Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                        <div>
                            <label for="modal_full_name" class="block text-sm font-semibold text-blue-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="modal_full_name" name="full_name" required
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_date_of_birth" class="block text-sm font-semibold text-blue-700 mb-2">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="modal_date_of_birth" name="date_of_birth"
                                   required max="<?= date('Y-m-d') ?>"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_age" class="block text-sm font-semibold text-blue-700 mb-2">
                                Age (Auto-calculated)
                            </label>
                            <input type="number" id="modal_age" name="age" readonly
                                   class="w-full rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 cursor-not-allowed">
                        </div>

                        <div>
                            <label for="modal_gender" class="block text-sm font-semibold text-blue-700 mb-2">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <select id="modal_gender" name="gender" required
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <?php if ($civilStatusExists): ?>
                        <div>
                            <label for="modal_civil_status" class="block text-sm font-semibold text-blue-700 mb-2">
                                Civil Status
                            </label>
                            <select id="modal_civil_status" name="civil_status"
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                <option value="">Select Status</option>
                                <option>Single</option>
                                <option>Married</option>
                                <option>Widowed</option>
                                <option>Separated</option>
                                <option>Divorced</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($occupationExists): ?>
                        <div>
                            <label for="modal_occupation" class="block text-sm font-semibold text-blue-700 mb-2">
                                Occupation
                            </label>
                            <input type="text" id="modal_occupation" name="occupation"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>
                        <?php endif; ?>

                        <?php if ($sitioExists): ?>
                        <div>
                            <label for="modal_sitio" class="block text-sm font-semibold text-blue-700 mb-2">
                                Sitio
                            </label>
                            <select id="modal_sitio" name="sitio"
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                <option value="">Select Sitio</option>
                                <option value="Proper Luz">Proper Luz</option>
                                <option value="Lower Luz">Lower Luz</option>
                                <option value="Upper Luz">Upper Luz</option>
                                <option value="Luz Proper">Luz Proper</option>
                                <option value="Luz Heights">Luz Heights</option>
                                <option value="Panganiban">Panganiban</option>
                                <option value="Balagtas">Balagtas</option>
                                <option value="Carbon">Carbon</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="md:col-span-2">
                            <label for="modal_address" class="block text-sm font-semibold text-blue-700 mb-2">
                                Complete Address
                            </label>
                            <input type="text" id="modal_address" name="address"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_contact" class="block text-sm font-semibold text-blue-700 mb-2">
                                Contact Number
                            </label>
                            <input type="text" id="modal_contact" name="contact"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>
                    </div>
                </div>

                <!-- ================= MEDICAL INFORMATION ================= -->
                <div class="bg-white p-8 rounded-2xl shadow-sm border border-blue-100">
                    <h3 class="text-2xl font-bold text-blue-700 mb-8 flex items-center">
                        <i class="fas fa-stethoscope text-4xl mr-3 text-blue-500"></i>
                        Medical Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                        <div>
                            <label for="modal_height" class="block text-sm font-semibold text-blue-700 mb-2">
                                Height (cm) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="modal_height" name="height" required
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_weight" class="block text-sm font-semibold text-blue-700 mb-2">
                                Weight (kg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="modal_weight" name="weight" required
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_temperature" class="block text-sm font-semibold text-blue-700 mb-2">
                                Temperature (°C)
                            </label>
                            <input type="number" id="modal_temperature" name="temperature"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_blood_pressure" class="block text-sm font-semibold text-blue-700 mb-2">
                                Blood Pressure
                            </label>
                            <input type="text" id="modal_blood_pressure" name="blood_pressure"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>

                        <div>
                            <label for="modal_blood_type" class="block text-sm font-semibold text-blue-700 mb-2">
                                Blood Type <span class="text-red-500">*</span>
                            </label>
                            <select id="modal_blood_type" name="blood_type" required
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                <option value="">Select Blood Type</option>
                                <option>A+</option><option>A-</option>
                                <option>B+</option><option>B-</option>
                                <option>AB+</option><option>AB-</option>
                                <option>O+</option><option>O-</option>
                                <option>Unknown</option>
                            </select>
                        </div>

                        <div>
                            <label for="modal_last_checkup" class="block text-sm font-semibold text-blue-700 mb-2">
                                Last Check-up Date
                            </label>
                            <input type="date" id="modal_last_checkup" name="last_checkup"
                                   class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <textarea id="modal_allergies" name="allergies" rows="3"
                                  class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                  placeholder="Allergies"></textarea>

                        <textarea id="modal_current_medications" name="current_medications" rows="3"
                                  class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                  placeholder="Current Medications"></textarea>

                        <textarea id="modal_immunization_record" name="immunization_record" rows="3"
                                  class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                  placeholder="Immunization Record"></textarea>

                        <textarea id="modal_chronic_conditions" name="chronic_conditions" rows="3"
                                  class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3"
                                  placeholder="Chronic Conditions"></textarea>
                    </div>

                    <textarea id="modal_medical_history" name="medical_history" rows="4"
                              class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3 mt-6"
                              placeholder="Medical History"></textarea>

                    <textarea id="modal_family_history" name="family_history" rows="4"
                              class="form-textarea-modal w-full rounded-xl border-blue-200 px-4 py-3 mt-6"
                              placeholder="Family Medical History"></textarea>
                </div>

                <input type="hidden" name="add_patient" value="1">
                <input type="hidden" name="consent_given" value="1">
            </form>
        </div>

        <!-- ================= FOOTER ================= -->
        <div class="sticky bottom-0 bg-white border-t border-blue-100 px-10 py-6">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <span class="text-sm text-blue-600 bg-blue-50 px-4 py-2 rounded-full">
                    <i class="fa-solid fa-circle-info mr-2"></i>Fields marked with * are required
                </span>

                <div class="flex gap-3">
                    <button type="button" onclick="clearAddPatientForm()"
                            class="px-6 py-4 rounded-full bg-gray-200 hover:bg-gray-200 font-semibold">
                        Clear Form
                    </button>
                    <button type="submit" name="add_patient" form="patientForm"
                            class="px-8 py-4 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-bold shadow">
                        <i class="fas fa-save mr-2"></i>Register Patient
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>


    <script>
        // Form validation functionality for modal
        document.addEventListener('DOMContentLoaded', function() {
            const modalFormFields = document.querySelectorAll('.modal-form-field');
            const modalRegisterBtn = document.getElementById('modalRegisterPatientBtn');
            const modalDateOfBirth = document.getElementById('modal_date_of_birth');
            const modalAge = document.getElementById('modal_age');

            // Initialize modal field validation
            modalFormFields.forEach(field => {
                // Set initial state
                updateModalFieldState(field);
                
                // Add event listeners
                field.addEventListener('input', function() {
                    updateModalFieldState(this);
                    checkModalFormValidity();
                });
                
                field.addEventListener('change', function() {
                    updateModalFieldState(this);
                    checkModalFormValidity();
                });
                
                field.addEventListener('blur', function() {
                    updateModalFieldState(this);
                });
            });

            // Age calculation from date of birth in modal
            if (modalDateOfBirth && modalAge) {
                modalDateOfBirth.addEventListener('change', function() {
                    calculateModalAge(this.value);
                });
            }

            // Calculate age function for modal
            function calculateModalAge(dateOfBirth) {
                if (!dateOfBirth) return;
                
                const birthDate = new Date(dateOfBirth);
                const today = new Date();
                
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                modalAge.value = age;
                
                // Update form validation state
                updateModalFieldState(modalAge);
                checkModalFormValidity();
            }

            // Update modal field background color
            function updateModalFieldState(field) {
                if (!field) return;
                
                const value = field.type === 'checkbox' ? field.checked : field.value.trim();
                const isRequired = field.hasAttribute('required');
                
                if (isRequired && !value) {
                    field.classList.add('field-empty');
                    field.classList.remove('field-filled');
                } else if (value) {
                    field.classList.add('field-filled');
                    field.classList.remove('field-empty');
                } else {
                    field.classList.remove('field-filled', 'field-empty');
                }
            }

            // Check if all required modal fields are filled
            function checkModalFormValidity() {
                if (!modalRegisterBtn) return;
                
                let allRequiredFilled = true;
                
                // Check required form fields
                modalFormFields.forEach(field => {
                    if (field.hasAttribute('required')) {
                        const value = field.type === 'checkbox' ? field.checked : field.value.trim();
                        if (!value) {
                            allRequiredFilled = false;
                        }
                    }
                });

                // Enable/disable register button
                if (allRequiredFilled) {
                    modalRegisterBtn.disabled = false;
                    modalRegisterBtn.classList.remove('btn-disabled');
                } else {
                    modalRegisterBtn.disabled = true;
                    modalRegisterBtn.classList.add('btn-disabled');
                }
            }

            // Initialize modal form state
            checkModalFormValidity();
        });

        // Modal functions
        function openAddPatientModal() {
            const modal = document.getElementById('addPatientModal');
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
        }
        
        function closeAddPatientModal() {
            const modal = document.getElementById('addPatientModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        function clearAddPatientForm() {
            const form = document.getElementById('patientForm');
            if (form) {
                form.reset();
                
                // Reset all form field states
                const modalFormFields = document.querySelectorAll('.modal-form-field');
                modalFormFields.forEach(field => {
                    field.classList.remove('field-filled', 'field-empty');
                });
                
                // Re-check form validity
                setTimeout(() => {
                    checkModalFormValidity();
                }, 100);
            }
        }

        // Enhanced modal functions for viewing patient info
        function openViewModal(patientId) {
            // Show loading state
            document.getElementById('modalContent').innerHTML = `
                <div class="flex justify-center items-center py-20">
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin text-5xl text-primary mb-4"></i>
                        <p class="text-lg text-gray-600 font-medium">Loading patient data...</p>
                        <p class="text-sm text-gray-500 mt-2">Please wait while we retrieve the information</p>
                    </div>
                </div>
            `;
            
            // Show modal with smooth animation
            const modal = document.getElementById('viewModal');
            modal.style.display = 'flex';
            modal.style.opacity = '0';
            
            // Animate modal appearance
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease';
            }, 10);
            
            // Load patient data via AJAX
            fetch(`./get_patient_data.php?id=${patientId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                    
                    // Add custom styling for the loaded content
                    const modalContent = document.getElementById('modalContent');
                    const forms = modalContent.querySelectorAll('form');
                    forms.forEach(form => {
                        form.classList.add('w-full', 'max-w-full');
                        
                        // Make form containers wider
                        const containers = form.querySelectorAll('.grid, .flex');
                        containers.forEach(container => {
                            container.classList.add('w-full');
                        });
                        
                        // Make input fields larger and more visible
                        const inputs = form.querySelectorAll('input, select, textarea');
                        inputs.forEach(input => {
                            if (!input.classList.contains('readonly-field')) {
                                input.classList.add('text-lg', 'px-4', 'py-3');
                            }
                        });
                    });
                    
                    // Set up the Save Medical Information button
                    setupMedicalForm();
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-12 bg-red-50 rounded-xl border-2 border-red-200">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                            <h3 class="text-xl font-semibold text-red-700 mb-2">Error Loading Patient Data</h3>
                            <p class="text-red-600 mb-4">Unable to load patient information. Please try again.</p>
                            <button onclick="openViewModal(${patientId})" class="btn-primary px-6 py-3">
                                <i class="fas fa-redo mr-2"></i>Retry
                            </button>
                        </div>
                    `;
                });
        }
        
        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Function to set up medical form when modal content loads
        function setupMedicalForm() {
            const healthInfoForm = document.getElementById('healthInfoForm');
            
            if (healthInfoForm) {
                // Find the Save Medical Information button and update its class
                const saveBtn = healthInfoForm.querySelector('button[name="save_health_info"]');
                if (saveBtn) {
                    saveBtn.classList.remove('btn-primary', 'bg-primary', 'text-white');
                    saveBtn.classList.add('btn-save-medical');
                    saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Medical Information';
                }
                
                // Set up form submission
                healthInfoForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[name="save_health_info"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData(this);
                        const response = await fetch(this.action, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            // Show success message
                            showNotification('success', 'Medical information saved successfully!');
                            
                            // Reload the modal content to show updated data
                            const patientId = formData.get('patient_id');
                            if (patientId) {
                                setTimeout(() => {
                                    openViewModal(patientId);
                                }, 1500);
                            }
                        } else {
                            showNotification('error', 'Error saving medical information');
                        }
                        
                    } catch (error) {
                        showNotification('error', 'Network error: ' + error.message);
                        console.error('Form submission error:', error);
                    } finally {
                        // Restore button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                });
            }
        }
        
        // NEW: Print Patient Record Function
        function printPatientRecord() {
            const patientId = getPatientId();
            if (patientId) {
                // Open the print patient page in a new window
                const printWindow = window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank', 'width=1200,height=800');
                if (printWindow) {
                    printWindow.focus();
                    // Listen for the window to load and trigger print
                    printWindow.onload = function() {
                        // Give a small delay for everything to load
                        setTimeout(() => {
                            printWindow.print();
                        }, 1000);
                    };
                } else {
                    showNotification('error', 'Please allow pop-ups for this site to print');
                }
            } else {
                showNotification('error', 'No patient selected for printing');
            }
        }

        function getPatientId() {
            const selectors = [
                '#healthInfoForm input[name="patient_id"]',
                'input[name="patient_id"]',
                '[name="patient_id"]',
                '#patient_id',
                '.patient-id-input'
            ];
            
            for (const selector of selectors) {
                const element = document.querySelector(selector);
                if (element && element.value) {
                    return element.value;
                }
            }
            
            const modalContent = document.getElementById('modalContent');
            if (modalContent) {
                const hiddenInputs = modalContent.querySelectorAll('input[type="hidden"]');
                for (const input of hiddenInputs) {
                    if (input.name === 'patient_id' && input.value) {
                        return input.value;
                    }
                }
            }
            
            return null;
        }

        function showNotification(type, message) {
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-6 right-6 z-50 px-6 py-4 rounded-xl shadow-lg border-2 ${
                type === 'error' ? 'alert-error' :
                type === 'success' ? 'alert-success' :
                'bg-blue-100 text-blue-800 border-blue-200'
            }`;
            
            const icon = type === 'error' ? 'fa-exclamation-circle' :
                       type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3 text-xl"></i>
                    <span class="font-semibold">${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Enhanced modal close on outside click
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const addPatientModal = document.getElementById('addPatientModal');
            
            if (event.target === viewModal) {
                closeViewModal();
            }
            if (event.target === addPatientModal) {
                closeAddPatientModal();
            }
        };

        // Add keyboard support for modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeViewModal();
                closeAddPatientModal();
            }
        });

        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var successMessage = document.getElementById('successMessage');
                var errorMessage = document.querySelector('.alert-error');
                
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
                    tabButtons.forEach(btn => btn.classList.remove('border-primary', 'text-primary', 'active'));
                    button.classList.add('border-primary', 'text-primary', 'active');
                    
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
                            btn.classList.add('border-primary', 'text-primary', 'active');
                        } else {
                            btn.classList.remove('border-primary', 'text-primary', 'active');
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
        
        // Clear search on page refresh
        if (window.history.replaceState && !window.location.search.includes('search=')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
        
        // Ensure buttons have proper opacity styling
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn-view, .btn-archive, .btn-add-patient, .btn-primary, .btn-success, .btn-gray, .btn-print, .btn-edit, .btn-save-medical, .btn-view-all, .btn-back-to-pagination, .pagination-btn');
            buttons.forEach(button => {
                button.style.borderStyle = 'solid';
            });
        });
    </script>

    <script>
        // Enhanced form submission with success modal
        document.addEventListener('DOMContentLoaded', function() {
            const healthInfoForm = document.getElementById('healthInfoForm');
            const viewModal = document.getElementById('viewModal');
            
            if (healthInfoForm) {
                healthInfoForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    submitBtn.disabled = true;
                    
                    try {
                        const formData = new FormData(this);
                        const response = await fetch(this.action, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Close the view modal
                            if (viewModal) {
                                closeViewModal();
                            }
                            
                            // Show success modal with beautiful design
                            showSuccessModal(result.formatted_data || result.data);
                            
                            // Optional: Refresh the patient list after a delay
                            setTimeout(() => {
                                if (window.location.search.includes('tab=patients-tab')) {
                                    window.location.reload();
                                }
                            }, 3000);
                            
                        } else {
                            showNotification('error', result.message || 'Error saving patient information');
                        }
                        
                    } catch (error) {
                        showNotification('error', 'Network error: ' + error.message);
                        console.error('Form submission error:', error);
                    } finally {
                        // Restore button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;
                    }
                });
            }
        });

        // Function to show success modal
        function showSuccessModal(data) {
            // Remove existing success modal if any
            const existingModal = document.getElementById('successModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal HTML
            const modalHTML = `
                <div id="successModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center p-4 z-[9999] animate-fadeIn">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border-2 border-success animate-scaleIn">
                        <!-- Modal Header -->
                        <div class="p-8 bg-gradient-to-r from-success/10 to-success/5 border-b border-success/20">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 bg-success/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check-circle text-3xl text-success"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-secondary">Successfully Saved!</h3>
                                        <p class="text-gray-600">Patient information has been updated successfully.</p>
                                    </div>
                                </div>
                                <button onclick="closeSuccessModal()" class="text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full w-10 h-10 flex items-center justify-center transition duration-200">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Modal Body -->
                        <div class="p-8 max-h-[60vh] overflow-y-auto">
                            <!-- Patient Summary Card -->
                            <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h4 class="text-lg font-bold text-secondary mb-1">${data.patient_name || 'Patient'}</h4>
                                        <p class="text-sm text-gray-600">Patient ID: <span class="font-semibold text-primary">${data.patient_id || 'N/A'}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <div class="inline-block px-3 py-1 rounded-full text-sm font-medium ${data.patient_type && data.patient_type.includes('Registered') ? 'bg-success/20 text-success' : 'bg-info/20 text-info'}">
                                            ${data.patient_type ? data.patient_type.replace(/<[^>]*>/g, '') : 'Patient Type'}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="space-y-1">
                                        <p class="text-gray-500">Date of Birth</p>
                                        <p class="font-medium">${data.date_of_birth || 'Not specified'}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-gray-500">Age</p>
                                        <p class="font-medium">${data.age || 'Not specified'}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-gray-500">Gender</p>
                                        <p class="font-medium">${data.gender || 'Not specified'}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-gray-500">Last Check-up</p>
                                        <p class="font-medium">${data.last_checkup || 'Not scheduled'}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Medical Information Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <!-- Vital Signs -->
                                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                                    <h5 class="text-lg font-bold text-secondary mb-4 flex items-center">
                                        <i class="fas fa-heartbeat mr-2 text-danger"></i>Vital Signs
                                    </h5>
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Height</span>
                                            <span class="font-bold text-lg ${data.height !== 'Not provided' ? 'text-primary' : 'text-gray-400'}">
                                                ${data.height || 'Not provided'}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Weight</span>
                                            <span class="font-bold text-lg ${data.weight !== 'Not provided' ? 'text-primary' : 'text-gray-400'}">
                                                ${data.weight || 'Not provided'}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Blood Pressure</span>
                                            <span class="font-bold text-lg ${data.blood_pressure !== 'Not provided' ? 'text-warning' : 'text-gray-400'}">
                                                ${data.blood_pressure || 'Not provided'}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Temperature</span>
                                            <span class="font-bold text-lg ${data.temperature !== 'Not provided' ? 'text-danger' : 'text-gray-400'}">
                                                ${data.temperature || 'Not provided'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Medical Details -->
                                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                                    <h5 class="text-lg font-bold text-secondary mb-4 flex items-center">
                                        <i class="fas fa-stethoscope mr-2 text-primary"></i>Medical Details
                                    </h5>
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600">Blood Type</span>
                                            <span class="font-bold text-lg text-primary">
                                                ${data.blood_type ? data.blood_type.replace(/<[^>]*>/g, '') : 'Not provided'}
                                            </span>
                                        </div>
                                        <div class="text-center py-4">
                                            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full ${data.blood_type && data.blood_type !== 'Not provided' ? 'bg-red-50 border-2 border-red-200' : 'bg-gray-50 border-2 border-gray-200'}">
                                                <span class="text-2xl font-bold ${data.blood_type && data.blood_type !== 'Not provided' ? 'text-danger' : 'text-gray-400'}">
                                                    ${data.blood_type && data.blood_type !== 'Not provided' ? data.blood_type.replace(/<[^>]*>/g, '') : 'N/A'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Success Message -->
                            <div class="bg-gradient-to-r from-success/5 to-success/10 rounded-xl p-6 border border-success/20">
                                <div class="flex items-start space-x-3">
                                    <i class="fas fa-info-circle text-xl text-success mt-1"></i>
                                    <div>
                                        <p class="text-sm text-gray-700 mb-2">
                                            <strong>✓ Record Updated:</strong> Patient information has been securely saved to the database.
                                        </p>
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>Saved by: <strong>${data.saved_by || 'Medical Staff'}</strong></span>
                                            <span>${data.timestamp || 'Just now'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-shield-alt mr-1 text-primary"></i>
                                Data is protected under RA 10173
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="printPatientRecordFromSuccess(${data.patient_id})" class="btn-print inline-flex items-center px-4 py-2">
                                    <i class="fas fa-print mr-2"></i>Print Record
                                </button>
                                <button onclick="closeSuccessModal()" class="btn-primary inline-flex items-center px-6 py-2">
                                    <i class="fas fa-check mr-2"></i>Done
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Auto-close modal after 8 seconds
            setTimeout(() => {
                closeSuccessModal();
            }, 8000);
        }

        // Function to close success modal
        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) {
                modal.style.opacity = '0';
                modal.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }

        // Function to print patient record from success modal
        function printPatientRecordFromSuccess(patientId) {
            if (patientId) {
                // Open the print patient page in a new window
                const printWindow = window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank', 'width=1200,height=800');
                if (printWindow) {
                    printWindow.focus();
                    // Listen for the window to load and trigger print
                    printWindow.onload = function() {
                        // Give a small delay for everything to load
                        setTimeout(() => {
                            printWindow.print();
                        }, 1000);
                    };
                } else {
                    showNotification('error', 'Please allow pop-ups for this site to print');
                }
            } else {
                showNotification('error', 'No patient selected for printing');
            }
        }

        // Add CSS animations for modal (run once)
        if (!document.getElementById('successModalStyles')) {
            const style = document.createElement('style');
            style.id = 'successModalStyles';
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes scaleIn {
                    from { transform: scale(0.95); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
                
                .animate-fadeIn {
                    animation: fadeIn 0.3s ease-out;
                }
                
                .animate-scaleIn {
                    animation: scaleIn 0.3s ease-out;
                }
                
                /* Success Modal Specific Styles */
                #successModal .btn-print {
                    background-color: white;
                    color: #3498db;
                    border: 2px solid #3498db;
                    opacity: 1;
                    border-radius: 8px;
                    padding: 10px 20px;
                    transition: all 0.3s ease;
                    font-weight: 500;
                    min-height: 44px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                }
                
                #successModal .btn-print:hover {
                    background-color: #f0f9ff;
                    border-color: #3498db;
                    opacity: 0.6;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
                }
                
                #successModal .btn-primary {
                    background-color: white;
                    color: #2ecc71;
                    border: 2px solid #2ecc71;
                    opacity: 1;
                    border-radius: 8px;
                    padding: 10px 24px;
                    transition: all 0.3s ease;
                    font-weight: 500;
                    min-height: 44px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 14px;
                }
                
                #successModal .btn-primary:hover {
                    background-color: #f0fdf4;
                    border-color: #2ecc71;
                    opacity: 0.6;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
                }
                
                /* Additional animation styles */
                .success-modal-bg {
                    background: rgba(0, 0, 0, 0.7);
                    backdrop-filter: blur(5px);
                }
                
                /* Gradient backgrounds */
                .bg-gradient-success {
                    background: linear-gradient(135deg, #d4edda 0%, #f0fdf4 100%);
                }
                
                .bg-gradient-blue {
                    background: linear-gradient(135deg, #dbeafe 0%, #f0f9ff 100%);
                }
                
                /* Pulse animation for important elements */
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                
                .pulse-animation {
                    animation: pulse 2s ease-in-out;
                }
                
                /* Shake animation for error */
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
                
                .shake-animation {
                    animation: shake 0.5s ease-in-out;
                }
            `;
            document.head.appendChild(style);
        }

        // Keyboard support for success modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeSuccessModal();
            }
        });

        // Click outside to close
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('successModal');
            if (modal && event.target === modal) {
                closeSuccessModal();
            }
        });
    </script>
</body>
</html>