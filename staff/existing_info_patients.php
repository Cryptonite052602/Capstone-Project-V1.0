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
        // Create deleted_patients table with dynamic columns
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
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deleted_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        
        // Add optional columns if they exist in the main table
        if ($sitioExists) {
            $createTableQuery .= ", sitio VARCHAR(255)";
        }
        if ($civilStatusExists) {
            $createTableQuery .= ", civil_status VARCHAR(100)";
        }
        if ($occupationExists) {
            $createTableQuery .= ", occupation VARCHAR(255)";
        }
        
        $createTableQuery .= ")";
        
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
    $consent_given = isset($_POST['consent_given']) ? 1 : 0;
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
            // Build dynamic INSERT query for deleted_patients based on available columns
            $columns = ["original_id", "full_name", "date_of_birth", "age", "gender", "address", "contact", "last_checkup", "added_by", "user_id", "deleted_at", "deleted_by"];
            $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "NOW()", "?"];
            $values = [
                $patient['id'], 
                $patient['full_name'], 
                $patient['date_of_birth'],
                $patient['age'], 
                $patient['gender'], 
                $patient['address'], 
                $patient['contact'], 
                $patient['last_checkup'], 
                $patient['added_by'],
                $patient['user_id'],
                $_SESSION['user']['id']
            ];
            
            // Add optional columns only if they exist in the source data
            if ($sitioExists && isset($patient['sitio'])) {
                $columns[] = "sitio";
                $placeholders[] = "?";
                $values[] = $patient['sitio'];
            }
            
            if ($civilStatusExists && isset($patient['civil_status'])) {
                $columns[] = "civil_status";
                $placeholders[] = "?";
                $values[] = $patient['civil_status'];
            }
            
            if ($occupationExists && isset($patient['occupation'])) {
                $columns[] = "occupation";
                $placeholders[] = "?";
                $values[] = $patient['occupation'];
            }
            
            $insertQuery = "INSERT INTO deleted_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            
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
            // Build dynamic INSERT query for restoration based on available columns
            $columns = ["id", "full_name", "date_of_birth", "age", "gender", "address", "contact", "last_checkup", "added_by", "user_id", "created_at"];
            $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "NOW()"];
            $values = [
                $archivedPatient['original_id'], 
                $archivedPatient['full_name'], 
                $archivedPatient['date_of_birth'],
                $archivedPatient['age'], 
                $archivedPatient['gender'], 
                $archivedPatient['address'], 
                $archivedPatient['contact'], 
                $archivedPatient['last_checkup'], 
                $archivedPatient['added_by'],
                $archivedPatient['user_id']
            ];
            
            // Add optional columns only if they exist in the archived data
            if ($sitioExists && isset($archivedPatient['sitio'])) {
                $columns[] = "sitio";
                $placeholders[] = "?";
                $values[] = $archivedPatient['sitio'];
            }
            
            if ($civilStatusExists && isset($archivedPatient['civil_status'])) {
                $columns[] = "civil_status";
                $placeholders[] = "?";
                $values[] = $archivedPatient['civil_status'];
            }
            
            if ($occupationExists && isset($archivedPatient['occupation'])) {
                $columns[] = "occupation";
                $placeholders[] = "?";
                $values[] = $archivedPatient['occupation'];
            }
            
            $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            
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
                
                // Build dynamic INSERT query for conversion based on available columns
                $columns = ["full_name", "date_of_birth", "age", "gender", "address", "contact", "added_by", "user_id", "consent_given", "consent_date"];
                $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "1", "NOW()"];
                $values = [
                    $user['full_name'], 
                    $user['date_of_birth'],
                    $user['age'], 
                    $user['gender'], 
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
    // Build dynamic SELECT query based on available columns
    $selectQuery = "SELECT p.id, p.full_name, p.date_of_birth, p.age, p.gender, p.address, p.contact, 
              e.height, e.weight, e.temperature, e.blood_pressure, e.blood_type, 
              e.allergies, e.immunization_record, e.chronic_conditions,
              e.medical_history, e.current_medications, e.family_history,
              u.unique_number, u.email as user_email, u.sitio as user_sitio";
    
    if ($sitioExists) {
        $selectQuery .= ", p.sitio";
    }
    
    if ($civilStatusExists) {
        $selectQuery .= ", p.civil_status";
    }
    
    if ($occupationExists) {
        $selectQuery .= ", p.occupation";
    }
    
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
        // Get basic patient info
        $stmt = $pdo->prepare("SELECT p.*, u.unique_number, u.email as user_email, u.sitio as user_sitio
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
                        info: '#17a2b8',
                        warmRed: '#fef2f2',
                        warmBlue: '#f0f9ff'
                    }
                }
            }
        }
    </script>
    <style>
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
        .btn-view { background-color: #3498db; color: white; border-radius: 9999px; padding: 8px 16px; transition: all 0.3s ease; }
        .btn-view:hover { background-color: #2980b9; transform: translateY(-2px); }
        .btn-archive { background-color: #e74c3c; color: white; border-radius: 9999px; padding: 8px 16px; transition: all 0.3s ease; }
        .btn-archive:hover { background-color: #c0392b; transform: translateY(-2px); }
        .btn-add-patient { background-color: #2ecc71; color: white; border-radius: 9999px; padding: 8px 16px; transition: all 0.3s ease; }
        .btn-add-patient:hover { background-color: #27ae60; transform: translateY(-2px); }
        
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
            gap: 0.5rem;
            flex-grow: 1;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 9999px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #4b5563;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .pagination-btn:hover {
            background-color: #e2e8f0;
            color: #374151;
        }
        
        .pagination-btn.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-btn.disabled:hover {
            background-color: #f8fafc;
            color: #4b5563;
        }
        
        .pagination-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-view-all {
            background-color: #2ecc71;
            color: white;
            border-radius: 9999px;
            padding: 8px 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
        }
        
        .btn-view-all:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .btn-back-to-pagination {
            background-color: #3498db;
            color: white;
            border-radius: 9999px;
            padding: 8px 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .btn-back-to-pagination:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
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
        .btn-disabled { background-color: #9ca3af !important; cursor: not-allowed !important; transform: none !important; }
        .btn-disabled:hover { background-color: #9ca3af !important; transform: none !important; }
        .consent-not-checked { border-color: #fecaca !important; background-color: #fef2f2 !important; }
        .consent-checked { border-color: #bbf7d0 !important; background-color: #f0fdf4 !important; }
        
        /* Enhanced Form Input Styles for Add Patient Tab */
        .form-input-rounded {
            border-radius: 9999px !important;
            padding: 12px 20px !important;
            border: 1px solid #d1d5db !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
        }
        
        .form-select-rounded {
            border-radius: 9999px !important;
            padding: 12px 20px !important;
            border: 1px solid #d1d5db !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 16px center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 50px;
        }
        
        .form-textarea-rounded {
            border-radius: 20px !important;
            padding: 16px 20px !important;
            border: 1px solid #d1d5db !important;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            resize: vertical;
            min-height: 120px;
        }
        
        .form-input-rounded:focus,
        .form-select-rounded:focus,
        .form-textarea-rounded:focus {
            outline: none;
            border-color: #3498db !important;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
        }
        
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.1em;
        }
        
        .form-checkbox-rounded {
            border-radius: 50% !important;
            width: 20px;
            height: 20px;
        }
        
        .consent-container-rounded {
            border-radius: 20px !important;
            padding: 20px !important;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-secondary">Barangay Luz Health Center - Patient Records</h1>
        
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
                    <a href="deleted_patients.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-full hover:bg-gray-700 transition">
                        <i class="fas fa-archive mr-2"></i>View Archive
                    </a>
                </div>
                
                <!-- Search Form -->
                <form method="get" action="" class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <input type="hidden" name="tab" value="patients-tab">
                    <?php if ($viewAll): ?>
                        <input type="hidden" name="view_all" value="true">
                    <?php endif; ?>
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
                                <button type="submit" class="ml-3 inline-flex items-center px-4 py-2 bg-primary text-white rounded-full hover:bg-blue-700 transition">
                                    <i class="fas fa-search mr-2"></i> Search
                                </button>
                                <?php if (!empty($searchTerm)): ?>
                                    <a href="existing_info_patients.php" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-500 text-white rounded-full hover:bg-gray-600 transition">
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
                                                        <td><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></td>
                                                        <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                        <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
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
                                                        <td><?= $user['date_of_birth'] ? date('M d, Y', strtotime($user['date_of_birth'])) : 'N/A' ?></td>
                                                        <td><?= $user['age'] ?? 'N/A' ?></td>
                                                        <td><?= htmlspecialchars($user['gender'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['occupation'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['sitio'] ?? 'N/A') ?></td>
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
                            <i class="fas fa-user-injured text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900">No patients found</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by adding a new patient.</p>
                            <div class="mt-6">
                                <button data-tab="add-tab" class="tab-trigger inline-flex items-center px-4 py-2 bg-primary text-white rounded-full hover:bg-blue-700 transition">
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
                                                    <td><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></td>
                                                    <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                    <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
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
                                                <td><?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A' ?></td>
                                                <td><?= $patient['age'] ?? 'N/A' ?></td>
                                                <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
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
                                    <a href="?tab=patients-tab&view_all=true" 
   class="bg-blue-600 text-white px-8 py-3 rounded-full hover:bg-blue-700 transition flex items-center justify-center w-fit">
    <i class="fas fa-list mr-2"></i>View All Patients
</a>

                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Patient Tab -->
            <div id="add-tab" class="tab-content p-6">
                <h2 class="text-xl font-semibold mb-6 text-secondary">Register New Patient</h2>
                
                <form method="POST" action="" class="bg-gray-50 p-6 rounded-lg" id="patientForm" enctype="multipart/form-data">
                    <!-- Personal Information Section -->
                    <div class="mb-8">
                        <h3 class="form-section-title">
                            <i class="fas fa-user"></i>Personal Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="full_name" class="form-label required-field">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-input-rounded form-field" required>
                            </div>
                            
                            <!-- ADDED DATE OF BIRTH FIELD -->
                            <div>
                                <label for="date_of_birth" class="form-label required-field">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-input-rounded form-field" required max="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div>
                                <label for="age" class="form-label">Age (Auto-calculated)</label>
                                <input type="number" id="age" name="age" min="0" max="120" class="form-input-rounded form-field" readonly>
                            </div>
                            
                            <div>
                                <label for="gender" class="form-label required-field">Gender</label>
                                <select id="gender" name="gender" class="form-select-rounded form-field" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <?php if ($civilStatusExists): ?>
                            <div>
                                <label for="civil_status" class="form-label">Civil Status</label>
                                <select id="civil_status" name="civil_status" class="form-select-rounded form-field">
                                    <option value="">Select Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                    <option value="Divorced">Divorced</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($occupationExists): ?>
                            <div>
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" id="occupation" name="occupation" class="form-input-rounded form-field" placeholder="Current occupation">
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($sitioExists): ?>
                            <div>
                                <label for="sitio" class="form-label">Sitio</label>
                                <select id="sitio" name="sitio" class="form-select-rounded form-field">
                                    <option value="">Select Sitio</option>
                                    <option value="Proper Toong">Proper Toong</option>
                                    <option value="Lower Toong">Lower Toong</option>
                                    <option value="Buacao">Buacao</option>
                                    <option value="Angay-Angay">Angay-Angay</option>
                                    <option value="Badiang">Badiang</option>
                                    <option value="Candahat">Candahat</option>
                                    <option value="NapNapan">NapNapan</option>
                                    <option value="Buyo">Buyo</option>
                                    <option value="Kalumboyan">Kalumboyan</option>
                                    <option value="Bugna">Bugna</option>
                                    <option value="Kaangking">Kaangking</option>
                                    <option value="Caolong">Caolong</option>
                                    <option value="Acasia">Acasia</option>
                                    <option value="Buad">Buad</option>
                                    <option value="Pangpang">Pangpang</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="md:col-span-2">
                                <label for="address" class="form-label">Complete Address</label>
                                <input type="text" id="address" name="address" class="form-input-rounded form-field" placeholder="House #, Street, Barangay">
                            </div>
                            
                            <div>
                                <label for="contact" class="form-label">Contact Number</label>
                                <input type="text" id="contact" name="contact" class="form-input-rounded form-field" placeholder="09XXXXXXXXX">
                            </div>
                        </div>
                    </div>

                    <!-- Medical Information Section -->
                    <div class="mb-8">
                        <h3 class="form-section-title">
                            <i class="fas fa-heartbeat"></i>Medical Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label for="height" class="form-label required-field">Height (cm)</label>
                                <input type="number" id="height" name="height" step="0.1" min="0" class="form-input-rounded form-field" required>
                            </div>
                            
                            <div>
                                <label for="weight" class="form-label required-field">Weight (kg)</label>
                                <input type="number" id="weight" name="weight" step="0.1" min="0" class="form-input-rounded form-field" required>
                            </div>
                            
                            <div>
                                <label for="temperature" class="form-label">Temperature (°C)</label>
                                <input type="number" id="temperature" name="temperature" step="0.1" min="0" max="45" class="form-input-rounded form-field">
                            </div>
                            
                            <div>
                                <label for="blood_pressure" class="form-label">Blood Pressure</label>
                                <input type="text" id="blood_pressure" name="blood_pressure" class="form-input-rounded form-field" placeholder="120/80">
                            </div>
                            
                            <div>
                                <label for="blood_type" class="form-label required-field">Blood Type</label>
                                <select id="blood_type" name="blood_type" class="form-select-rounded form-field" required>
                                    <option value="">Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="Unknown">Unknown</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="last_checkup" class="form-label">Last Check-up Date</label>
                                <input type="date" id="last_checkup" name="last_checkup" class="form-input-rounded form-field">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label for="allergies" class="form-label">Allergies</label>
                                <textarea id="allergies" name="allergies" rows="3" class="form-textarea-rounded form-field" placeholder="Food, drug, environmental allergies..."></textarea>
                            </div>
                            
                            <div>
                                <label for="current_medications" class="form-label">Current Medications</label>
                                <textarea id="current_medications" name="current_medications" rows="3" class="form-textarea-rounded form-field" placeholder="Medications with dosage and frequency..."></textarea>
                            </div>
                            
                            <div>
                                <label for="immunization_record" class="form-label">Immunization Record</label>
                                <textarea id="immunization_record" name="immunization_record" rows="3" class="form-textarea-rounded form-field" placeholder="Vaccinations received with dates..."></textarea>
                            </div>
                            
                            <div>
                                <label for="chronic_conditions" class="form-label">Chronic Conditions</label>
                                <textarea id="chronic_conditions" name="chronic_conditions" rows="3" class="form-textarea-rounded form-field" placeholder="Hypertension, diabetes, asthma, etc..."></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="medical_history" class="form-label">Medical History</label>
                            <textarea id="medical_history" name="medical_history" rows="4" class="form-textarea-rounded form-field" placeholder="Past illnesses, surgeries, hospitalizations, chronic conditions..."></textarea>
                        </div>
                        
                        <div class="mt-4">
                            <label for="family_history" class="form-label">Family Medical History</label>
                            <textarea id="family_history" name="family_history" rows="4" class="form-textarea-rounded form-field" placeholder="Family history of diseases (parents, siblings)..."></textarea>
                        </div>
                    </div>

                    <!-- Data Privacy Consent -->
                    <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200 consent-container consent-container-rounded">
                        <div class="flex items-start">
                            <input type="checkbox" id="consent_given" name="consent_given" class="mt-1 mr-3 consent-checkbox form-checkbox-rounded" required>
                            <label for="consent_given" class="text-gray-700">
                                <span class="font-medium text-blue-800">Data Privacy Consent</span>
                                <p class="text-sm text-blue-600 mt-1">
                                    I hereby give my consent to the Barangay Luz Health Center to collect, process, 
                                    and store my personal and health information in accordance with the 
                                    <strong>Data Privacy Act of 2012 (RA 10173)</strong>. I understand that my 
                                    information will be kept confidential and used only for healthcare purposes.
                                </p>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-4">
                        <button type="reset" class="bg-gray-500 text-white py-3 px-8 rounded-full hover:bg-gray-600 transition" id="clearFormBtn">
                            <i class="fas fa-times mr-2"></i>Clear Form
                        </button>
                        <button type="submit" name="add_patient" class="bg-primary text-white py-3 px-8 rounded-full hover:bg-blue-700 transition" id="registerPatientBtn" disabled>
                            <i class="fas fa-save mr-2"></i>Register Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Wider Modal with Sticky Header -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal" style="display: none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col">
        <!-- Sticky Header -->
        <div class="p-8 border-b border-blue-500 flex justify-between items-center bg-gradient-to-r from-primary to-blue-600 text-white rounded-t-2xl sticky top-0 z-10">
            <h3 class="text-2xl font-bold flex items-center">
                <i class="fas fa-user-injured mr-3 text-white"></i>Patient Health Information
            </h3>
            <button onclick="closeViewModal()" class="text-white hover:text-gray-200 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-full w-10 h-10 flex items-center justify-center transition duration-200">
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
        
        <!-- Sticky Footer -->
        <div class="p-8 border-t border-gray-200 bg-white rounded-b-2xl sticky bottom-0">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                        <i class="fas fa-info-circle mr-1"></i>View and edit patient information
                    </span>
                </div>
                <div class="flex flex-wrap gap-3">
    
                    <button onclick="printPatientRecord()" class="px-6 py-3 bg-blue-100 text-primary rounded-full hover:bg-blue-200 transition font-semibold shadow-sm hover:shadow-md flex items-center">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
        // Form validation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('patientForm');
            const formFields = document.querySelectorAll('.form-field');
            const consentCheckbox = document.querySelector('.consent-checkbox');
            const consentContainer = document.querySelector('.consent-container');
            const registerBtn = document.getElementById('registerPatientBtn');
            const clearBtn = document.getElementById('clearFormBtn');

            // Initialize field validation
            formFields.forEach(field => {
                // Set initial state
                updateFieldState(field);
                
                // Add event listeners
                field.addEventListener('input', function() {
                    updateFieldState(this);
                    checkFormValidity();
                });
                
                field.addEventListener('change', function() {
                    updateFieldState(this);
                    checkFormValidity();
                });
                
                field.addEventListener('blur', function() {
                    updateFieldState(this);
                });
            });

            // Consent checkbox event listener
            consentCheckbox.addEventListener('change', function() {
                updateConsentState();
                checkFormValidity();
            });

            // Clear form button
            clearBtn.addEventListener('click', function() {
                setTimeout(() => {
                    formFields.forEach(field => updateFieldState(field));
                    updateConsentState();
                    checkFormValidity();
                }, 100);
            });

            // Update field background color based on content
            function updateFieldState(field) {
                const value = field.type === 'checkbox' ? field.checked : field.value.trim();
                
                if (field.hasAttribute('required') && !value) {
                    // Required field is empty - warm red
                    field.classList.add('field-empty');
                    field.classList.remove('field-filled');
                } else if (value) {
                    // Field has content - warm blue
                    field.classList.add('field-filled');
                    field.classList.remove('field-empty');
                } else {
                    // Optional field is empty - default
                    field.classList.remove('field-filled', 'field-empty');
                }
            }

            // Update consent container state
            function updateConsentState() {
                if (consentCheckbox.checked) {
                    consentContainer.classList.add('consent-checked');
                    consentContainer.classList.remove('consent-not-checked');
                } else {
                    consentContainer.classList.add('consent-not-checked');
                    consentContainer.classList.remove('consent-checked');
                }
            }

            // Check if all required fields are filled and consent is given
            function checkFormValidity() {
                let allRequiredFilled = true;
                
                // Check required form fields
                formFields.forEach(field => {
                    if (field.hasAttribute('required')) {
                        const value = field.type === 'checkbox' ? field.checked : field.value.trim();
                        if (!value) {
                            allRequiredFilled = false;
                        }
                    }
                });

                // Check consent
                const consentGiven = consentCheckbox.checked;
                
                // Enable/disable register button
                if (allRequiredFilled && consentGiven) {
                    registerBtn.disabled = false;
                    registerBtn.classList.remove('btn-disabled');
                } else {
                    registerBtn.disabled = true;
                    registerBtn.classList.add('btn-disabled');
                }
            }

            // Initialize form state
            updateConsentState();
            checkFormValidity();
        });

        // Enhanced modal functions
        function openViewModal(patientId) {
            // Show loading state with better styling
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
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-12 bg-red-50 rounded-xl border-2 border-red-200">
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4"></i>
                            <h3 class="text-xl font-semibold text-red-700 mb-2">Error Loading Patient Data</h3>
                            <p class="text-red-600 mb-4">Unable to load patient information. Please try again.</p>
                            <button onclick="openViewModal(${patientId})" class="px-6 py-3 bg-primary text-white rounded-full hover:bg-blue-700 transition font-semibold">
                                <i class="fas fa-redo mr-2"></i>Retry
                            </button>
                        </div>
                    `;
                });
        }
        
        // Enhanced close modal function
        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Enhanced export functions
        function printPatientRecord() {
            const patientId = getPatientId();
            if (patientId) {
                const printWindow = window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank', 'width=1200,height=800');
                if (printWindow) {
                    printWindow.focus();
                }
            } else {
                showNotification('error', 'No patient selected for printing');
            }
        }

        function exportToExcel() {
            const patientId = getPatientId();
            if (patientId) {
                showNotification('info', 'Preparing Excel export...');
                window.location.href = `/community-health-tracker/api/export_patient.php?id=${patientId}&format=excel`;
            } else {
                showNotification('error', 'No patient selected for export');
            }
        }

        function exportToPDF() {
            const patientId = getPatientId();
            if (patientId) {
                showNotification('info', 'Generating PDF document...');
                window.location.href = `/community-health-tracker/api/export_patient.php?id=${patientId}&format=pdf`;
            } else {
                showNotification('error', 'No patient selected for export');
            }
        }

        // Enhanced patient ID detection
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
            
            // Try to get from URL if modal is open
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

        // Notification function
        function showNotification(type, message) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification fixed top-6 right-6 z-50 px-6 py-4 rounded-xl shadow-lg border-2 ${
                type === 'error' ? 'bg-red-100 text-red-800 border-red-200' :
                type === 'success' ? 'bg-green-100 text-green-800 border-green-200' :
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
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }

        // Enhanced modal close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeViewModal();
            }
        };

        // Add keyboard support for modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeViewModal();
            }
        });

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
        
        // Clear search on page refresh
        if (window.history.replaceState && !window.location.search.includes('search=')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // ADDED: Age calculation from date of birth
        document.addEventListener('DOMContentLoaded', function() {
            const dateOfBirthInput = document.getElementById('date_of_birth');
            const ageInput = document.getElementById('age');
            
            if (dateOfBirthInput && ageInput) {
                dateOfBirthInput.addEventListener('change', function() {
                    calculateAge(this.value);
                });
                
                // Calculate age on page load if date of birth is already set
                if (dateOfBirthInput.value) {
                    calculateAge(dateOfBirthInput.value);
                }
            }
            
            function calculateAge(dateOfBirth) {
                if (!dateOfBirth) return;
                
                const birthDate = new Date(dateOfBirth);
                const today = new Date();
                
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                ageInput.value = age;
                
                // Update form validation state
                if (ageInput.classList.contains('form-field')) {
                    updateFieldState(ageInput);
                    checkFormValidity();
                }
            }
        });
    </script>
</body>
</html>