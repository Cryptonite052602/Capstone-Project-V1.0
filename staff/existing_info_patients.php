<?php
ob_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

// Add PhpSpreadsheet for Excel export
// require_once __DIR__ . '/../vendor/autoload.php';

// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                    $gender,
                    $height,
                    $weight,
                    $blood_type,
                    $temperature,
                    $blood_pressure,
                    $allergies,
                    $medical_history,
                    $current_medications,
                    $family_history,
                    $immunization_record,
                    $chronic_conditions,
                    $patient_id
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
                    $patient_id,
                    $gender,
                    $height,
                    $weight,
                    $blood_type,
                    $temperature,
                    $blood_pressure,
                    $allergies,
                    $medical_history,
                    $current_medications,
                    $family_history,
                    $immunization_record,
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
                $patientId,
                $gender,
                $height,
                $weight,
                $temperature,
                $blood_pressure,
                $bloodType,
                $allergies,
                $medicalHistory,
                $currentMedications,
                $familyHistory,
                $immunizationRecord,
                $chronicConditions
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

// NEW: Handle manual export POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_manual'])) {
    $selectedPatients = isset($_POST['selected_patients']) ? $_POST['selected_patients'] : [];

    if (empty($selectedPatients)) {
        $error = 'Please select at least one patient to export.';
    } else {
        try {
            // Prepare placeholders for IN clause
            $placeholders = implode(',', array_fill(0, count($selectedPatients), '?'));

            // COMPREHENSIVE QUERY: Get ALL fields from both tables
            $query = "SELECT 
                p.*,
                e.*,
                CASE 
                    WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                    ELSE 'Regular Patient'
                END as patient_type,
                u.email as user_email,
                u.unique_number
            FROM sitio1_patients p
            LEFT JOIN existing_info_patients e ON p.id = e.patient_id
            LEFT JOIN sitio1_users u ON p.user_id = u.id
            WHERE p.id IN ($placeholders) AND p.added_by = ? AND p.deleted_at IS NULL
            ORDER BY p.full_name ASC";

            $params = array_merge($selectedPatients, [$_SESSION['user']['id']]);
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Set filename
            $filename = 'detailed_patient_export_' . date('Y-m-d_His') . '.xls';

            // CLEAN OUTPUT - Start fresh output buffer
            ob_clean();

            // Output ONLY Excel content - NO HTML headers, NO website content
            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: attachment; filename=\"$filename\"");

            // Start table directly without HTML doctype or headers
            echo '<table border="1">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Date of Birth</th>
                        <th>Age</th>
                        <th>Address</th>
                        <th>Sitio</th>
                        <th>Disease</th>
                        <th>Contact</th>
                        <th>Last Checkup</th>
                        <th>Medical History</th>
                        <th>Added By</th>
                        <th>Created At</th>
                        <th>Gender</th>
                        <th>Updated At</th>
                        <th>Consultation Type</th>
                        <th>Civil Status</th>
                        <th>Occupation</th>
                        <th>Consent Given</th>
                        <th>Consent Date</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>Blood Type</th>
                        <th>Allergies</th>
                        <th>Medical History (detailed)</th>
                        <th>Current Medications</th>
                        <th>Family History</th>
                        <th>Health Updated At</th>
                        <th>Temperature (°C)</th>
                        <th>Blood Pressure</th>
                        <th>Immunization Record</th>
                        <th>Chronic Conditions</th>
                        <th>Patient Type</th>
                        <th>User Email</th>
                        <th>Unique Number</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($patients as $patient) {
                echo '<tr>
                    <td>' . ($patient['id'] ?? '') . '</td>
                    <td>' . ($patient['user_id'] ?? '') . '</td>
                    <td>' . ($patient['full_name'] ?? '') . '</td>
                    <td>' . (!empty($patient['date_of_birth']) ? date('Y-m-d', strtotime($patient['date_of_birth'])) : '') . '</td>
                    <td>' . ($patient['age'] ?? '') . '</td>
                    <td>' . ($patient['address'] ?? '') . '</td>
                    <td>' . ($patient['sitio'] ?? '') . '</td>
                    <td>' . ($patient['disease'] ?? '') . '</td>
                    <td>' . ($patient['contact'] ?? '') . '</td>
                    <td>' . (!empty($patient['last_checkup']) ? date('Y-m-d', strtotime($patient['last_checkup'])) : '') . '</td>
                    <td>' . ($patient['medical_history'] ?? '') . '</td>
                    <td>' . ($patient['added_by'] ?? '') . '</td>
                    <td>' . (!empty($patient['created_at']) ? date('Y-m-d H:i:s', strtotime($patient['created_at'])) : '') . '</td>
                    <td>' . ($patient['gender'] ?? '') . '</td>
                    <td>' . (!empty($patient['updated_at']) ? date('Y-m-d H:i:s', strtotime($patient['updated_at'])) : '') . '</td>
                    <td>' . ($patient['consultation_type'] ?? '') . '</td>
                    <td>' . ($patient['civil_status'] ?? '') . '</td>
                    <td>' . ($patient['occupation'] ?? '') . '</td>
                    <td>' . ($patient['consent_given'] ? 'Yes' : 'No') . '</td>
                    <td>' . (!empty($patient['consent_date']) ? date('Y-m-d H:i:s', strtotime($patient['consent_date'])) : '') . '</td>
                    <td>' . ($patient['height'] ?? '') . '</td>
                    <td>' . ($patient['weight'] ?? '') . '</td>
                    <td>' . ($patient['blood_type'] ?? '') . '</td>
                    <td>' . ($patient['allergies'] ?? '') . '</td>
                    <td>' . ($patient['medical_history'] ?? '') . '</td>
                    <td>' . ($patient['current_medications'] ?? '') . '</td>
                    <td>' . ($patient['family_history'] ?? '') . '</td>
                    <td>' . (!empty($patient['updated_at']) ? date('Y-m-d H:i:s', strtotime($patient['updated_at'])) : '') . '</td>
                    <td>' . ($patient['temperature'] ?? '') . '</td>
                    <td>' . ($patient['blood_pressure'] ?? '') . '</td>
                    <td>' . ($patient['immunization_record'] ?? '') . '</td>
                    <td>' . ($patient['chronic_conditions'] ?? '') . '</td>
                    <td>' . ($patient['patient_type'] ?? '') . '</td>
                    <td>' . ($patient['user_email'] ?? '') . '</td>
                    <td>' . ($patient['unique_number'] ?? '') . '</td>
                </tr>';
            }

            echo '</tbody></table>';

            // Exit immediately to prevent any other output
            exit();

        } catch (Exception $e) {
            $error = "Error exporting selected patients: " . $e->getMessage();
        }
    }
}

// Handle Excel Export - UPDATED with comprehensive data
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    try {
        $patientType = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'all';
        $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
        $searchBy = isset($_GET['search_by']) ? trim($_GET['search_by']) : 'name';

        // COMPREHENSIVE QUERY for All Records export
        $query = "SELECT 
            p.*,
            e.*,
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                ELSE 'Regular Patient'
            END as patient_type,
            u.email as user_email,
            u.unique_number
        FROM sitio1_patients p
        LEFT JOIN existing_info_patients e ON p.id = e.patient_id
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.added_by = ? AND p.deleted_at IS NULL";

        // Add search filters if search term exists
        $params = [$_SESSION['user']['id']];
        if (!empty($searchTerm)) {
            if ($searchBy === 'unique_number') {
                $query .= " AND EXISTS (
                    SELECT 1 FROM sitio1_users u 
                    WHERE u.id = p.user_id AND u.unique_number LIKE ?
                )";
                $params[] = "%$searchTerm%";
            } else {
                $$query .= " AND p.full_name LIKE ?";
                $params[] = "%$searchTerm%";
            }
        }

        // Add patient type filter
        if ($patientType == 'registered') {
            $query .= " AND p.user_id IS NOT NULL";
        } elseif ($patientType == 'regular') {
            $query .= " AND p.user_id IS NULL";
        }

        $query .= " ORDER BY p.full_name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set filename
        $filename = 'patient_records_export_' . date('Y-m-d');
        if ($patientType == 'registered') {
            $filename = 'registered_patients_export_' . date('Y-m-d');
        } elseif ($patientType == 'regular') {
            $filename = 'regular_patients_export_' . date('Y-m-d');
        }
        if (!empty($searchTerm)) {
            $filename .= '_search_' . substr($searchTerm, 0, 20);
        }
        $filename .= '.xls';

        // CLEAN OUTPUT - Start fresh output buffer
        ob_clean();

        // Output ONLY Excel content - NO HTML headers, NO website content
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$filename\"");

        // Start table directly without HTML doctype or headers
        echo '<table border="1">
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Date of Birth</th>
                    <th>Age</th>
                    <th>Address</th>
                    <th>Sitio</th>
                    <th>Disease</th>
                    <th>Contact</th>
                    <th>Last Checkup</th>
                    <th>Medical History</th>
                    <th>Added By</th>
                    <th>Created At</th>
                    <th>Gender</th>
                    <th>Updated At</th>
                    <th>Consultation Type</th>
                    <th>Civil Status</th>
                    <th>Occupation</th>
                    <th>Consent Given</th>
                    <th>Consent Date</th>
                    <th>Height (cm)</th>
                    <th>Weight (kg)</th>
                    <th>Blood Type</th>
                    <th>Allergies</th>
                    <th>Medical History (detailed)</th>
                    <th>Current Medications</th>
                    <th>Family History</th>
                    <th>Health Updated At</th>
                    <th>Temperature (°C)</th>
                    <th>Blood Pressure</th>
                    <th>Immunization Record</th>
                    <th>Chronic Conditions</th>
                    <th>Patient Type</th>
                    <th>User Email</th>
                    <th>Unique Number</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($patients as $patient) {
            echo '<tr>
                <td>' . ($patient['id'] ?? '') . '</td>
                <td>' . ($patient['user_id'] ?? '') . '</td>
                <td>' . ($patient['full_name'] ?? '') . '</td>
                <td>' . (!empty($patient['date_of_birth']) ? date('Y-m-d', strtotime($patient['date_of_birth'])) : '') . '</td>
                <td>' . ($patient['age'] ?? '') . '</td>
                <td>' . ($patient['address'] ?? '') . '</td>
                <td>' . ($patient['sitio'] ?? '') . '</td>
                <td>' . ($patient['disease'] ?? '') . '</td>
                <td>' . ($patient['contact'] ?? '') . '</td>
                <td>' . (!empty($patient['last_checkup']) ? date('Y-m-d', strtotime($patient['last_checkup'])) : '') . '</td>
                <td>' . ($patient['medical_history'] ?? '') . '</td>
                <td>' . ($patient['added_by'] ?? '') . '</td>
                <td>' . (!empty($patient['created_at']) ? date('Y-m-d H:i:s', strtotime($patient['created_at'])) : '') . '</td>
                <td>' . ($patient['gender'] ?? '') . '</td>
                <td>' . (!empty($patient['updated_at']) ? date('Y-m-d H:i:s', strtotime($patient['updated_at'])) : '') . '</td>
                <td>' . ($patient['consultation_type'] ?? '') . '</td>
                <td>' . ($patient['civil_status'] ?? '') . '</td>
                <td>' . ($patient['occupation'] ?? '') . '</td>
                <td>' . ($patient['consent_given'] ? 'Yes' : 'No') . '</td>
                <td>' . (!empty($patient['consent_date']) ? date('Y-m-d H:i:s', strtotime($patient['consent_date'])) : '') . '</td>
                <td>' . ($patient['height'] ?? '') . '</td>
                <td>' . ($patient['weight'] ?? '') . '</td>
                <td>' . ($patient['blood_type'] ?? '') . '</td>
                <td>' . ($patient['allergies'] ?? '') . '</td>
                <td>' . ($patient['medical_history'] ?? '') . '</td>
                <td>' . ($patient['current_medications'] ?? '') . '</td>
                <td>' . ($patient['family_history'] ?? '') . '</td>
                <td>' . (!empty($patient['updated_at']) ? date('Y-m-d H:i:s', strtotime($patient['updated_at'])) : '') . '</td>
                <td>' . ($patient['temperature'] ?? '') . '</td>
                <td>' . ($patient['blood_pressure'] ?? '') . '</td>
                <td>' . ($patient['immunization_record'] ?? '') . '</td>
                <td>' . ($patient['chronic_conditions'] ?? '') . '</td>
                <td>' . ($patient['patient_type'] ?? '') . '</td>
                <td>' . ($patient['user_email'] ?? '') . '</td>
                <td>' . ($patient['unique_number'] ?? '') . '</td>
            </tr>';
        }

        // If no records found
        if (empty($patients)) {
            echo '<tr>
                <td colspan="35" style="text-align: center; padding: 20px; color: #666;">
                    No patient records found for the selected filter
                </td>
            </tr>';
        }

        echo '</tbody></table>';

        // Exit immediately to prevent any other output
        exit();

    } catch (Exception $e) {
        $error = "Error exporting to Excel: " . $e->getMessage();
        // Log error but don't output to user to prevent breaking the Excel file
        error_log("Excel Export Error: " . $e->getMessage());
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
                if (
                    in_array($column, $deletedTableColumns) &&
                    !in_array($column, ['id', 'deleted_at'])
                ) { // Exclude auto columns
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

// Handle patient restoration - UPDATED to properly restore with all original data
if (isset($_GET['restore_patient'])) {
    $patientId = $_GET['restore_patient'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get archived patient data including ALL medical info
        $stmt = $pdo->prepare("
            SELECT 
                dp.*,
                eip.gender as health_gender,
                eip.height,
                eip.weight,
                eip.temperature,
                eip.blood_pressure,
                eip.blood_type,
                eip.allergies,
                eip.medical_history,
                eip.current_medications,
                eip.family_history,
                eip.immunization_record,
                eip.chronic_conditions,
                eip.updated_at as health_updated
            FROM deleted_patients dp
            LEFT JOIN existing_info_patients eip ON dp.original_id = eip.patient_id
            WHERE dp.original_id = ? AND dp.deleted_by = ?
        ");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        $archivedPatient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($archivedPatient) {
            // Check if patient already exists in main table
            $stmt = $pdo->prepare("SELECT id FROM sitio1_patients WHERE id = ? AND added_by = ?");
            $stmt->execute([$patientId, $_SESSION['user']['id']]);
            $existingPatient = $stmt->fetch();

            if ($existingPatient) {
                $error = 'This patient already exists in the active records!';
            } else {
                // Get column information from sitio1_patients table
                $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients");
                $stmt->execute();
                $mainTableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Prepare data for restoration
                $columns = [];
                $placeholders = [];
                $values = [];

                // Map archived data to main table columns
                foreach ($archivedPatient as $column => $value) {
                    // Skip columns that don't exist in sitio1_patients
                    if (!in_array($column, $mainTableColumns)) {
                        continue;
                    }

                    // Skip metadata columns from deleted_patients
                    if (in_array($column, ['deleted_by', 'deleted_at', 'id', 'created_at'])) {
                        continue;
                    }

                    // Map original_id back to id
                    if ($column === 'original_id') {
                        $columns[] = 'id';
                        $placeholders[] = "?";
                        $values[] = $value;
                        continue;
                    }

                    $columns[] = $column;
                    $placeholders[] = "?";
                    $values[] = $value;
                }

                // Add restored timestamp
                $columns[] = 'restored_at';
                $placeholders[] = "NOW()";

                $insertQuery = "INSERT INTO sitio1_patients (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";

                // Debug: Uncomment to see the generated query
                // error_log("Restore Query: " . $insertQuery);
                // error_log("Values: " . implode(', ', $values));

                // Restore to main patients table with original ID
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute($values);

                // Restore medical info if it exists
                if (!empty($archivedPatient['health_gender'])) {
                    $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                        (patient_id, gender, height, weight, temperature, blood_pressure, 
                         blood_type, allergies, medical_history, current_medications, 
                         family_history, immunization_record, chronic_conditions, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                            gender = VALUES(gender),
                            height = VALUES(height),
                            weight = VALUES(weight),
                            temperature = VALUES(temperature),
                            blood_pressure = VALUES(blood_pressure),
                            blood_type = VALUES(blood_type),
                            allergies = VALUES(allergies),
                            medical_history = VALUES(medical_history),
                            current_medications = VALUES(current_medications),
                            family_history = VALUES(family_history),
                            immunization_record = VALUES(immunization_record),
                            chronic_conditions = VALUES(chronic_conditions),
                            updated_at = VALUES(updated_at)");

                    $stmt->execute([
                        $patientId,
                        $archivedPatient['health_gender'],
                        $archivedPatient['height'] ?? null,
                        $archivedPatient['weight'] ?? null,
                        $archivedPatient['temperature'] ?? null,
                        $archivedPatient['blood_pressure'] ?? null,
                        $archivedPatient['blood_type'] ?? null,
                        $archivedPatient['allergies'] ?? null,
                        $archivedPatient['medical_history'] ?? null,
                        $archivedPatient['current_medications'] ?? null,
                        $archivedPatient['family_history'] ?? null,
                        $archivedPatient['immunization_record'] ?? null,
                        $archivedPatient['chronic_conditions'] ?? null,
                        $archivedPatient['health_updated'] ?? date('Y-m-d H:i:s')
                    ]);
                }

                // Delete from archive
                $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE original_id = ?");
                $stmt->execute([$patientId]);

                $pdo->commit();

                $_SESSION['success_message'] = 'Patient record restored successfully! All data has been recovered.';
                header('Location: deleted_patients.php');
                exit();
            }
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
                    if ($userGender === 'male')
                        $userGender = 'Male';
                    if ($userGender === 'female')
                        $userGender = 'Female';
                    if ($userGender === 'other')
                        $userGender = 'Other';
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

// Get patient type filter
$patientTypeFilter = isset($_GET['patient_type']) ? $_GET['patient_type'] : 'all';

// Check if manual selection mode is active
$manualSelectMode = isset($_GET['manual_select']) && $_GET['manual_select'] == 'true';

// Get patient ID if selected
$selectedPatientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_POST['patient_id']) ? $_POST['patient_id'] : '');

// Get list of patients matching search
$patients = [];
$searchedUsers = [];
if (!empty($searchTerm)) {
    try {
        // Update the search query for unique_number (around line 536)
        if ($searchBy === 'unique_number') {
            // Search by unique number from sitio1_users table
            $query = "SELECT u.id, u.full_name, u.email, u.date_of_birth, u.age, u.gender,
                             u.civil_status, u.occupation, u.address, u.sitio, u.contact, 
                             u.unique_number, 'user' as type
                      FROM sitio1_users u 
                      WHERE u.approved = 1 AND u.unique_number LIKE ? 
                      ORDER BY u.full_name LIMIT 10";

            $stmt = $pdo->prepare($query);
            $stmt->execute(["%$searchTerm%"]);
            $searchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Search by name (default) - using your table structure
            $selectQuery = "SELECT p.id, p.full_name, p.date_of_birth, p.age, 
                                   p.gender, p.sitio, p.civil_status, p.occupation,
                                   e.blood_type, e.height, e.weight, e.temperature, e.blood_pressure
                            FROM sitio1_patients p 
                            LEFT JOIN existing_info_patients e ON p.id = e.patient_id 
                            WHERE p.added_by = ? AND p.deleted_at IS NULL AND p.full_name LIKE ? 
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

// Get total count of patients based on filter
try {
    $countQuery = "SELECT COUNT(*) as total FROM sitio1_patients p 
                   WHERE p.added_by = ? AND p.deleted_at IS NULL";

    // Add patient type filter
    if ($patientTypeFilter == 'registered') {
        $countQuery .= " AND p.user_id IS NOT NULL";
    } elseif ($patientTypeFilter == 'regular') {
        $countQuery .= " AND p.user_id IS NULL";
    }

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
    $selectQuery = "SELECT 
            p.id,
            p.full_name,
            -- Prioritize patient table date_of_birth, fallback to user table
            COALESCE(p.date_of_birth, u.date_of_birth) as date_of_birth,
            p.age,
            COALESCE(e.gender, p.gender) as gender,
            p.sitio,
            p.civil_status,
            p.occupation,
            p.user_id,
            e.blood_type,
            e.height, e.weight, e.temperature, e.blood_pressure,
            e.allergies, e.immunization_record, e.chronic_conditions,
            e.medical_history, e.current_medications, e.family_history,
            u.unique_number,
            u.email as user_email,
            u.sitio as user_sitio,
            u.civil_status as user_civil_status,
            u.occupation as user_occupation,
            u.gender as user_gender,
            u.date_of_birth as user_date_of_birth, -- Get this separately for debugging
            CASE 
                WHEN p.user_id IS NOT NULL THEN 'Registered Patient'
                ELSE 'Regular Patient'
            END as patient_type
        FROM sitio1_patients p
        LEFT JOIN existing_info_patients e ON p.id = e.patient_id
        LEFT JOIN sitio1_users u ON p.user_id = u.id
        WHERE p.added_by = ? AND p.deleted_at IS NULL";

    // Add patient type filter to main query
    if ($patientTypeFilter == 'registered') {
        $selectQuery .= " AND p.user_id IS NOT NULL";
    } elseif ($patientTypeFilter == 'regular') {
        $selectQuery .= " AND p.user_id IS NULL";
    }

    $selectQuery .= " ORDER BY p.created_at DESC";

    if (!$viewAll && !$manualSelectMode) {
        $selectQuery .= " LIMIT ? OFFSET ?";
    }

    $stmt = $pdo->prepare($selectQuery);

    if ($viewAll || $manualSelectMode) {
        $stmt->execute([$_SESSION['user']['id']]);
    } else {
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
        // Update patient details query (around line 643)
        $stmt = $pdo->prepare("SELECT 
            p.*, 
            u.unique_number, 
            u.email as user_email,
            CASE 
                WHEN u.gender = 'male' THEN 'Male'
                WHEN u.gender = 'female' THEN 'Female'
                WHEN u.gender = 'other' THEN 'Other'
                ELSE u.gender
            END as user_gender
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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

        .patient-table th,
        .patient-table td {
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

        .regular-badge {
            background-color: #f0fdf4;
            color: #065f46;
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Export Button */
        .btn-export {
            background-color: #10b981;
            color: #ffffffff;
            border: 2px solid #10b981;
            opacity: 1;
            border-radius: 30px;
            padding: 15px 25px;
            transition: all 0.3s ease;
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-export:hover {
            background-color: #34d399;
            border-color: #10b981;
            opacity: 0.6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        /* Manual Export Controls */
        .manual-export-controls {
            background-color: #f0f9ff;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .checkbox-column {
            width: 50px;
            text-align: center;
        }

        .select-all-checkbox {
            margin-right: 10px;
        }

        /* Patient Type Filter */
        .patient-type-filter {
            border: 2px solid #55b2f0ff;
            background-color: white;
            border-radius: 10px;
            padding: 12px 20px;
            min-height: 55px;
            font-size: 16px;
            width: 100%;
            max-width: 355px;
        }

        .patient-type-filter:focus {
            border-color: #84c0e9ff;
            box-shadow: 0 0 0 3px #8acdfaff;
            outline: none;
        }

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

        #viewModal {
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }

        #viewModal>div {
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        #viewModal[style*="display: flex"]>div {
            transform: scale(1);
        }

        #viewModal ::-webkit-scrollbar {
            width: 8px;
        }

        #viewModal ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        #viewModal ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        #viewModal ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        #modalContent input:not([type="checkbox"]):not([type="radio"]),
        #modalContent select,
        #modalContent textarea {
            min-height: 48px;
            font-size: 16px;
        }

        #modalContent .grid {
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            #viewModal>div {
                margin: 1rem;
                max-height: calc(100vh - 2rem);
            }
        }

        @media (max-width: 768px) {
            #viewModal>div {
                margin: 0.5rem;
                max-height: calc(100vh - 1rem);
            }

            #viewModal .p-8 {
                padding: 1.5rem;
            }
        }

        .custom-notification {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

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
        .field-empty {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
        }

        .field-filled {
            background-color: #f0f9ff !important;
            border-color: #bae6fd !important;
        }

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
            border: 1px solid #85ccfb !important;
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
            border: 1px solid #85ccfb !important;
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
            border: 1px solid #85ccfb !important;
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
        #addPatientModal {
            backdrop-filter: blur(5px);
            transition: opacity 0.3s ease;
        }

        #addPatientModal>div {
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        #addPatientModal[style*="display: flex"]>div {
            transform: scale(1);
        }

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
        .btn-print,
        .btn-edit,
        .btn-view-all,
        .btn-back-to-pagination,
        .search-input,
        .search-select {
            border-style: solid !important;
        }

        /* Hover state opacity */
        .btn-archive:hover,
        .btn-success:hover,
        .btn-edit:hover,
        .btn-back-to-pagination:hover,
        .search-input:focus,
        .search-select:focus {
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
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
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
        #successModal {
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
        .btn-view,
        .btn-archive,
        .btn-primary,
        .btn-success,
        .btn-gray,
        .btn-print,
        .btn-edit,
        .btn-save-medical,
        .btn-view-all,
        .btn-back-to-pagination,
        .pagination-btn {
            position: relative;
        }

        .btn-view::after,
        .btn-archive::after,
        .btn-primary::after,
        .btn-success::after,
        .btn-gray::after,
        .btn-print::after,
        .btn-edit::after,
        .btn-view-all::after,
        .btn-back-to-pagination::after,
        {
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

        .btn-add-patient::after {
            border: 2px solid #2ecc71;
            opacity: 1;
        }

        .btn-success::after {
            border: 2px solid #2ecc71;
            opacity: 1;
        }

        .btn-edit::after {
            border: 2px solid #f39c12;
            opacity: 1;
        }

        .btn-back-to-pagination::after {
            border: 2px solid #3498db;
            opacity: 1;
        }

        .pagination-btn::after {
            border: 1px solid #3498db;
            opacity: 1;
        }

        .btn-view:hover::after,
        .btn-archive:hover::after,
        .btn-add-patient:hover::after,
        .btn-success:hover::after,
        .btn-edit:hover::after,
        .btn-view-all:hover::after,
        .btn-back-to-pagination:hover::after,
        .pagination-btn:hover::after {
            opacity: 0.6;
        }

        /* Export button group */
        .export-options {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            min-width: 220px;
            z-index: 100;
            margin-top: 5px;
        }

        .export-options.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        .export-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            border: none;
            background: none;
            color: #374151;
            font-size: 14px;
            transition: all 0.2s ease;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }

        .export-option:last-child {
            border-bottom: none;
        }

        .export-option:hover {
            background-color: #f0f9ff;
            color: #3498db;
        }

        .export-option i {
            margin-right: 8px;
            width: 20px;
        }

        /* Export button wrapper */
        .export-btn-wrapper {
            position: relative;
            display: inline-block;
        }

        /* Checkbox styling */
        .patient-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .manual-selection-header {
            background-color: #f0f9ff;
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .manual-export-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
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
                <button
                    class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition"
                    data-tab="patients-tab">
                    <i class="fas fa-list mr-2"></i>Patient Records
                </button>
                <button
                    class="tab-btn py-4 px-6 font-medium text-gray-600 hover:text-primary border-b-2 border-transparent hover:border-primary transition"
                    data-tab="add-tab">
                    <i class="fas fa-plus-circle mr-2"></i>Add New Patient
                </button>
            </div>

            <!-- Patients Tab -->
            <div id="patients-tab" class="tab-content p-6 active">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-secondary">Patient Records</h2>
                    <div class="flex gap-4">
                        <!-- Export Button with Dropdown -->
                        <div class="export-btn-wrapper">
                            <button onclick="toggleExportOptions()" class="btn-export inline-flex items-center">
                                <i class="fas fa-download mr-2"></i>Export
                            </button>
                            <div id="exportOptions" class="export-options">
                                <button type="button" onclick="exportAllRecords()" class="export-option">
                                    <i class="fas fa-database mr-2"></i>All Records
                                </button>
                                <button type="button" onclick="enableManualSelection()" class="export-option">
                                    <i class="fas fa-user-check mr-2"></i>Manual by Patient
                                </button>
                            </div>
                        </div>
                        <a href="deleted_patients.php" class="btn-gray inline-flex items-center">
                            <i class="fas fa-archive mr-2"></i>View Archive
                        </a>
                    </div>
                </div>

                <!-- Manual Export Controls (Hidden by default) -->
                <?php if ($manualSelectMode): ?>
                    <div class="manual-export-controls mb-6">
                        <form method="POST" action="" id="manualExportForm" class="manual-export-form">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h4 class="text-lg font-semibold text-secondary mb-2">
                                        <i class="fas fa-user-check mr-2 text-primary"></i>
                                        Select Patients for Export
                                    </h4>
                                    <p class="text-sm text-gray-600">Check the patients you want to include in the export
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="selectAllPatients"
                                            class="patient-checkbox select-all-checkbox" onchange="toggleAllPatients(this)">
                                        <label for="selectAllPatients" class="ml-2 text-sm font-medium text-gray-700">Select
                                            All</label>
                                    </div>
                                    <button type="button" onclick="disableManualSelection()" class="btn-gray px-4 py-2">
                                        <i class="fas fa-times mr-2"></i>Cancel
                                    </button>
                                    <button type="submit" name="export_manual" class="btn-export px-4 py-2">
                                        <i class="fas fa-download mr-2"></i>Export Selected
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-sm text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Selected: <span id="selectedCount">0</span> patients
                                </p>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- UPDATED Search Form with warmBlue border and separated icon -->
                <form method="get" action="" class="mb-6 section-bg p-6">
                    <input type="hidden" name="tab" value="patients-tab">
                    <?php if ($viewAll): ?>
                        <input type="hidden" name="view_all" value="true">
                    <?php endif; ?>
                    <?php if ($manualSelectMode): ?>
                        <input type="hidden" name="manual_select" value="true">
                    <?php endif; ?>

                    <div class="search-form-container">
                        <!-- Search By Field -->
                        <div class="search-field-group">
                            <label for="search_by" class="block text-gray-700 mb-2 font-medium">Search By</label>
                            <div class="relative">
                                <select id="search_by" name="search_by" class="search-select">
                                    <option value="name" <?= $searchBy === 'name' ? 'selected' : '' ?>>Name</option>
                                    <option value="unique_number" <?= $searchBy === 'unique_number' ? 'selected' : '' ?>>
                                        Unique Number</option>
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
                                <input type="text" id="search" name="search"
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
                                <a href="existing_info_patients.php<?= $manualSelectMode ? '?manual_select=true&tab=patients-tab' : '?tab=patients-tab' ?>"
                                    class="btn-gray min-w-[120px] text-center">
                                    <i class="fas fa-times mr-2"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

                <?php if (!empty($searchTerm)): ?>
                    <div class="section-bg overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-secondary">Search Results for
                                "<?= htmlspecialchars($searchTerm) ?>"</h3>
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
                                                                if ($patient['gender'] === 'male')
                                                                    echo 'Male';
                                                                elseif ($patient['gender'] === 'female')
                                                                    echo 'Female';
                                                                else
                                                                    echo htmlspecialchars($patient['gender']);
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
                                                        <td class="font-semibold text-primary">
                                                            <?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?>
                                                        </td>
                                                        <td>
                                                            <span class="text-gray-500">Regular Patient</span>
                                                        </td>
                                                        <td>
                                                            <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                                class="btn-view inline-flex items-center mr-2">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                            <a href="?delete_patient=<?= $patient['id'] ?>"
                                                                class="btn-archive inline-flex items-center"
                                                                onclick="return confirm('Are you sure you want to archive this patient record?')">
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
                                                                if ($user['gender'] === 'male')
                                                                    echo 'Male';
                                                                elseif ($user['gender'] === 'female')
                                                                    echo 'Female';
                                                                else
                                                                    echo htmlspecialchars($user['gender']);
                                                            } else {
                                                                echo 'N/A';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($user['occupation'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['sitio'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($user['contact'] ?? 'N/A') ?></td>

                                                        <td>
                                                            <a href="?convert_to_patient=<?= $user['id'] ?>"
                                                                class="btn-add-patient inline-flex items-center"
                                                                onclick="return confirm('Are you sure you want to add this user as a patient?')">
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
                        <!-- UPDATED: Added Export Button and Filter -->
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-medium text-secondary">
                                    <?= $viewAll ? 'All Patient Records' : ($manualSelectMode ? 'Select Patients for Export' : 'Patient Records') ?>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php if ($viewAll): ?>
                                        Showing all <?= count($allPatients) ?> records
                                    <?php elseif ($manualSelectMode): ?>
                                        Select patients to include in export
                                    <?php else: ?>
                                        Showing <?= count($allPatients) ?> of <?= $totalRecords ?> records
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Patient Type Filter -->
                                <form method="get" action="" class="flex items-center gap-2">
                                    <input type="hidden" name="tab" value="patients-tab">
                                    <?php if ($viewAll): ?>
                                        <input type="hidden" name="view_all" value="true">
                                    <?php endif; ?>
                                    <?php if ($manualSelectMode): ?>
                                        <input type="hidden" name="manual_select" value="true">
                                    <?php endif; ?>
                                    <select name="patient_type" onchange="this.form.submit()" class="patient-type-filter">
                                        <option value="all" <?= $patientTypeFilter === 'all' ? 'selected' : '' ?>>All Patient
                                            Types</option>
                                        <option value="registered" <?= $patientTypeFilter === 'registered' ? 'selected' : '' ?>>Registered Patient</option>
                                        <option value="regular" <?= $patientTypeFilter === 'regular' ? 'selected' : '' ?>>
                                            Regular Patient</option>
                                    </select>
                                </form>
                            </div>
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
                            <?php if ($viewAll || $manualSelectMode): ?>
                                <div class="p-4">
                                    <?php if (!$manualSelectMode): ?>
                                        <a href="existing_info_patients.php?tab=patients-tab"
                                            class="btn-back-to-pagination inline-flex items-center">
                                            <i class="fas fa-arrow-left mr-2"></i>Back to Pagination View
                                        </a>
                                    <?php endif; ?>
                                    <div class="scrollable-table-container">
                                        <form method="POST" action="" id="patientSelectionForm">
                                            <table class="patient-table">
                                                <thead>
                                                    <tr>
                                                        <?php if ($manualSelectMode): ?>
                                                            <th class="checkbox-column">
                                                                <input type="checkbox" id="selectAll" class="patient-checkbox"
                                                                    onchange="toggleAllSelection(this)">
                                                            </th>
                                                        <?php endif; ?>
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
                                                            <?php if ($manualSelectMode): ?>
                                                                <td class="checkbox-column">
                                                                    <input type="checkbox" name="selected_patients[]"
                                                                        value="<?= $patient['id'] ?>"
                                                                        class="patient-checkbox patient-select"
                                                                        onchange="updateSelectedCount()">
                                                                </td>
                                                            <?php endif; ?>
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
                                                            <td class="font-semibold text-primary">
                                                                <?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($patient['patient_type'] === 'Registered Patient'): ?>
                                                                    <span class="user-badge">Registered Patient</span>

                                                                <?php else: ?>
                                                                    <span class="regular-badge">Regular Patient</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                                    class="btn-view inline-flex items-center mr-2">
                                                                    <i class="fas fa-eye mr-1"></i> View
                                                                </button>
                                                                <?php if (!$manualSelectMode): ?>
                                                                    <a href="?delete_patient=<?= $patient['id'] ?>"
                                                                        class="btn-archive inline-flex items-center"
                                                                        onclick="return confirm('Are you sure you want to archive this patient record?')">
                                                                        <i class="fas fa-trash-alt mr-1"></i> Archive
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </form>
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
                                                    <td class="font-semibold text-primary">
                                                        <?= htmlspecialchars($patient['blood_type'] ?? 'N/A') ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($patient['patient_type'] === 'Registered Patient'): ?>
                                                            <span class="user-badge">Registered Patient</span>

                                                        <?php else: ?>
                                                            <span class="regular-badge">Regular Patient</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button onclick="openViewModal(<?= $patient['id'] ?>)"
                                                            class="btn-view inline-flex items-center mr-2">
                                                            <i class="fas fa-eye mr-1"></i> View
                                                        </button>
                                                        <a href="?delete_patient=<?= $patient['id'] ?>"
                                                            class="btn-archive inline-flex items-center"
                                                            onclick="return confirm('Are you sure you want to archive this patient record?')">
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
                                        <a href="?tab=patients-tab&page=<?= $currentPage - 1 ?>&patient_type=<?= $patientTypeFilter ?>"
                                            class="pagination-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>

                                        <!-- Page Numbers -->
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)): ?>
                                                <a href="?tab=patients-tab&page=<?= $i ?>&patient_type=<?= $patientTypeFilter ?>"
                                                    class="pagination-btn <?= $i == $currentPage ? 'active' : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php elseif ($i == $currentPage - 2 || $i == $currentPage + 2): ?>
                                                <span class="pagination-btn disabled">...</span>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <!-- Next Button -->
                                        <a href="?tab=patients-tab&page=<?= $currentPage + 1 ?>&patient_type=<?= $patientTypeFilter ?>"
                                            class="pagination-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </div>

                                    <div class="pagination-actions">
                                        <a href="?tab=patients-tab&view_all=true&patient_type=<?= $patientTypeFilter ?>"
                                            class="btn-view-all">
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
                                Add a new patient record to the system. Fill out all required information including
                                personal details and medical history.
                            </p>
                            <button onclick="openAddPatientModal()"
                                class="btn-primary px-8 py-4 round-full text-lg font-semibold">
                                <i class="fas fa-plus-circle mr-3"></i>Add New Patient
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Wider Modal for Viewing Patient Info -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 modal"
        style="display: none;">
        <div
            class="bg-white rounded-2xl shadow-2xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col border-2 border-primary">
            <!-- Sticky Header -->
            <div
                class="p-8 border-b border-primary flex justify-between items-center bg-blue-500 rounded-t-2xl sticky top-0 z-10">
                <h3 class="text-2xl font-semibold flex items-center  px-4 py-3 rounded-lg">
                    <i class="fa-solid fa-circle-info mr-4 text-5xl text-white"></i>
                    <span class="text-white">Patient Health Information</span>
                </h3>



                <button onclick="closeViewModal()"
                    class="text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full w-10 h-10 flex items-center justify-center transition duration-200">
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
    <div id="addPatientModal" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 modal"
        style="display:none;">

        <div class="bg-white rounded-lg shadow-2xl w-full max-w-7xl h-[92vh] overflow-hidden flex flex-col">
            <!-- ================= HEADER ================= -->
            <div class="sticky top-0 z-20 bg-[#2563EB] px-10 py-6 flex items-center">
                <h3 class="text-xl font-medium flex justify-center text-center w-full items-center text-white">
                    Registration For New Patient
                </h3>
                <button onclick="closeAddPatientModal()"
                    class="border-2 border-white hover:bg-white/20 rounded-full w-8 h-8 flex items-center justify-center transition">
                    <i class="fas fa-times text-xl text-white"></i>
                </button>
            </div>

            <!-- ================= CONTENT ================= -->
            <div class="flex-1 overflow-y-auto">
                <form method="POST" action="" id="patientForm" enctype="multipart/form-data" class="space-y-10">

                    <!-- ================= PERSONAL INFORMATION ================= -->
                    <div class="bg-white p-8">
                        <h3
                            class="text-2xl font-normal border-b border-black-100 py-4 text-[#2563EB] mb-8 gap-4 flex items-center">
                            <svg width="33" height="34" viewBox="0 0 33 34" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M31.8891 6.72969L31.1609 6.30938C31.2464 5.85712 31.2464 5.39288 31.1609 4.94063L31.8891 4.52031C32.1763 4.35455 32.3858 4.0815 32.4717 3.76122C32.5575 3.44095 32.5126 3.09968 32.3469 2.8125C32.1811 2.52532 31.9081 2.31575 31.5878 2.22989C31.2675 2.14404 30.9263 2.18893 30.6391 2.35469L29.9094 2.77656C29.5602 2.47696 29.1587 2.24442 28.725 2.09063V1.25C28.725 0.918479 28.5933 0.600537 28.3589 0.366116C28.1245 0.131696 27.8065 0 27.475 0C27.1435 0 26.8256 0.131696 26.5911 0.366116C26.3567 0.600537 26.225 0.918479 26.225 1.25V2.09063C25.7914 2.24442 25.3898 2.47696 25.0406 2.77656L24.311 2.35469C24.1688 2.27261 24.0118 2.21935 23.849 2.19793C23.6862 2.17652 23.5208 2.18738 23.3622 2.22989C23.2036 2.27241 23.055 2.34574 22.9247 2.4457C22.7945 2.54566 22.6852 2.6703 22.6031 2.8125C22.5211 2.9547 22.4678 3.11167 22.4464 3.27445C22.425 3.43723 22.4358 3.60264 22.4783 3.76122C22.5209 3.91981 22.5942 4.06847 22.6942 4.19871C22.7941 4.32896 22.9188 4.43824 23.061 4.52031L23.7891 4.94063C23.7037 5.39288 23.7037 5.85712 23.7891 6.30938L23.061 6.72969C22.8225 6.86721 22.6361 7.07959 22.5307 7.33387C22.4253 7.58815 22.4068 7.87011 22.478 8.13599C22.5493 8.40187 22.7063 8.6368 22.9247 8.80433C23.1431 8.97186 23.4107 9.06261 23.686 9.0625C23.9054 9.06318 24.1211 9.00548 24.311 8.89531L25.0406 8.47344C25.3898 8.77304 25.7914 9.00558 26.225 9.15938V10C26.225 10.3315 26.3567 10.6495 26.5911 10.8839C26.8256 11.1183 27.1435 11.25 27.475 11.25C27.8065 11.25 28.1245 11.1183 28.3589 10.8839C28.5933 10.6495 28.725 10.3315 28.725 10V9.15938C29.1587 9.00558 29.5602 8.77304 29.9094 8.47344L30.6391 8.89531C30.8289 9.00548 31.0446 9.06318 31.2641 9.0625C31.5393 9.06261 31.8069 8.97186 32.0253 8.80433C32.2437 8.6368 32.4007 8.40187 32.472 8.13599C32.5432 7.87011 32.5247 7.58815 32.4193 7.33387C32.3139 7.07959 32.1275 6.86721 31.8891 6.72969ZM26.225 5.625C26.225 5.37777 26.2983 5.1361 26.4357 4.93054C26.573 4.72498 26.7683 4.56476 26.9967 4.47015C27.2251 4.37554 27.4764 4.35079 27.7189 4.39902C27.9614 4.44725 28.1841 4.5663 28.3589 4.74112C28.5337 4.91593 28.6528 5.13866 28.701 5.38114C28.7492 5.62361 28.7245 5.87495 28.6299 6.10335C28.5353 6.33176 28.375 6.52699 28.1695 6.66434C27.9639 6.80169 27.7222 6.875 27.475 6.875C27.1435 6.875 26.8256 6.7433 26.5911 6.50888C26.3567 6.27446 26.225 5.95652 26.225 5.625ZM30.811 13.1422C30.484 13.1969 30.1922 13.3793 29.9996 13.6491C29.8071 13.919 29.7297 14.2543 29.7844 14.5813C29.9113 15.3392 29.9751 16.1065 29.975 16.875C29.978 20.2409 28.7409 23.49 26.5 26.0016C25.1059 23.9814 23.1456 22.4185 20.8656 21.5094C22.0904 20.5448 22.984 19.2225 23.4224 17.7264C23.8608 16.2303 23.822 14.6348 23.3116 13.1618C22.8012 11.6888 21.8444 10.4114 20.5743 9.50733C19.3042 8.60327 17.784 8.11747 16.225 8.11747C14.666 8.11747 13.1458 8.60327 11.8757 9.50733C10.6057 10.4114 9.64888 11.6888 9.13843 13.1618C8.62799 14.6348 8.58926 16.2303 9.02763 17.7264C9.466 19.2225 10.3597 20.5448 11.5844 21.5094C9.30442 22.4185 7.34413 23.9814 5.95002 26.0016C4.19176 24.0203 3.04325 21.5732 2.64258 18.9548C2.24192 16.3363 2.60615 13.6578 3.69148 11.2414C4.77681 8.82494 6.53704 6.77346 8.76052 5.3336C10.984 3.89375 13.576 3.12681 16.225 3.125C16.9936 3.12488 17.7608 3.18864 18.5188 3.31563C18.8443 3.36698 19.1769 3.28774 19.4442 3.09514C19.7116 2.90254 19.8921 2.61216 19.9465 2.28716C20.0008 1.96216 19.9247 1.62884 19.7346 1.3597C19.5444 1.09057 19.2557 0.907383 18.9313 0.85C15.5366 0.27893 12.0484 0.80146 8.97007 2.34215C5.89178 3.88283 3.38277 6.36195 1.8053 9.42156C0.227837 12.4812 -0.336461 15.9629 0.193886 19.3642C0.724233 22.7654 2.32178 25.9101 4.75587 28.3441C7.18996 30.7782 10.3346 32.3758 13.7359 32.9061C17.1371 33.4365 20.6189 32.8722 23.6785 31.2947C26.7381 29.7172 29.2172 27.2082 30.7579 24.1299C32.2986 21.0517 32.8211 17.5634 32.25 14.1688C32.1953 13.8418 32.013 13.55 31.7431 13.3574C31.4732 13.1649 31.1379 13.0875 30.811 13.1422ZM11.225 15.625C11.225 14.6361 11.5183 13.6694 12.0677 12.8472C12.6171 12.0249 13.398 11.384 14.3116 11.0056C15.2252 10.6272 16.2306 10.5281 17.2005 10.7211C18.1704 10.914 19.0613 11.3902 19.7605 12.0895C20.4598 12.7887 20.936 13.6796 21.1289 14.6495C21.3219 15.6195 21.2229 16.6248 20.8444 17.5384C20.466 18.452 19.8251 19.2329 19.0029 19.7823C18.1806 20.3318 17.2139 20.625 16.225 20.625C14.8989 20.625 13.6272 20.0982 12.6895 19.1605C11.7518 18.2229 11.225 16.9511 11.225 15.625ZM7.80002 27.7344C8.70429 26.3201 9.95002 25.1563 11.4224 24.3501C12.8948 23.5439 14.5464 23.1213 16.225 23.1213C17.9036 23.1213 19.5553 23.5439 21.0276 24.3501C22.5 25.1563 23.7457 26.3201 24.65 27.7344C22.2412 29.6078 19.2766 30.6249 16.225 30.6249C13.1734 30.6249 10.2089 29.6078 7.80002 27.7344Z"
                                    fill="#2563EB" />
                            </svg>

                            Personal Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <label for="modal_full_name" class="block text-sm font-medium mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_full_name" name="full_name" placeholder="Enter Full Name"
                                    required class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_date_of_birth" class="block text-sm font-medium mb-2">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="modal_date_of_birth" name="date_of_birth" required
                                    max="<?= date('Y-m-d') ?>"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_age" class="block text-sm font-medium mb-2">
                                    Age (Auto-calculated)
                                </label>
                                <input type="number" id="modal_age" name="age" placeholder="0" readonly
                                    class="form-input-modal w-full rounded-xl bg-[#F0F0F0] border border-blue-200 px-4 py-3 cursor-not-allowed">
                            </div>

                            <div>
                                <label for="modal_gender" class="block text-sm font-medium mb-2">
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
                                    <label for="modal_civil_status" class="block text-sm font-medium mb-2">
                                        Civil Status <span class="text-red-500">*</span>
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
                                    <label for="modal_occupation" class="block text-sm font-medium mb-2">
                                        Occupation
                                    </label>
                                    <input type="text" id="modal_occupation" name="occupation"
                                        placeholder="Enter Occupation"
                                        class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                </div>
                            <?php endif; ?>
                            <?php if ($sitioExists): ?>
                                <div>
                                    <label for="modal_sitio" class="block text-sm font-medium mb-2">
                                        Sitio <span class="text-red-500">*</span>
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
                            <div>
                                <label for="modal_address" class="block text-sm font-medium mb-2">
                                    Complete Address <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_address" name="address"
                                    placeholder="Enter Complete Address"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>
                            <div>
                                <label for="modal_contact" class="block text-sm font-medium mb-2">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="modal_contact" name="contact" placeholder="Enter Contact Number"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>
                        </div>

                    </div>

                    <!-- ================= MEDICAL INFORMATION ================= -->
                    <div class="bg-white px-8">
                        <h3
                            class="text-2xl border-b border-black-100 py-4 font-normal text-blue-700 gap-4 mb-8 flex items-center">

                            <svg width="33" height="33" viewBox="0 0 33 33" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M18.125 24.375C18.125 24.7458 18.015 25.1084 17.809 25.4167C17.603 25.725 17.3101 25.9654 16.9675 26.1073C16.6249 26.2492 16.2479 26.2863 15.8842 26.214C15.5205 26.1416 15.1864 25.963 14.9242 25.7008C14.662 25.4386 14.4834 25.1045 14.411 24.7408C14.3387 24.3771 14.3758 24.0001 14.5177 23.6575C14.6596 23.3149 14.9 23.022 15.2083 22.816C15.5167 22.61 15.8792 22.5 16.25 22.5C16.7473 22.5 17.2242 22.6975 17.5758 23.0492C17.9275 23.4008 18.125 23.8777 18.125 24.375ZM16.25 7.5C12.8031 7.5 10 10.0234 10 13.125V13.75C10 14.0815 10.1317 14.3995 10.3661 14.6339C10.6005 14.8683 10.9185 15 11.25 15C11.5815 15 11.8995 14.8683 12.1339 14.6339C12.3683 14.3995 12.5 14.0815 12.5 13.75V13.125C12.5 11.4062 14.1828 10 16.25 10C18.3172 10 20 11.4062 20 13.125C20 14.8438 18.3172 16.25 16.25 16.25C15.9185 16.25 15.6005 16.3817 15.3661 16.6161C15.1317 16.8505 15 17.1685 15 17.5V18.75C15 19.0815 15.1317 19.3995 15.3661 19.6339C15.6005 19.8683 15.9185 20 16.25 20C16.5815 20 16.8995 19.8683 17.1339 19.6339C17.3683 19.3995 17.5 19.0815 17.5 18.75V18.6375C20.35 18.1141 22.5 15.8406 22.5 13.125C22.5 10.0234 19.6969 7.5 16.25 7.5ZM32.5 16.25C32.5 19.4639 31.547 22.6057 29.7614 25.278C27.9758 27.9503 25.4379 30.0331 22.4686 31.263C19.4993 32.493 16.232 32.8148 13.0798 32.1878C9.9276 31.5607 7.03213 30.0131 4.75952 27.7405C2.48692 25.4679 0.939256 22.5724 0.312247 19.4202C-0.314763 16.268 0.00704086 13.0007 1.23696 10.0314C2.46689 7.06209 4.54969 4.52419 7.22199 2.73862C9.89429 0.953046 13.0361 0 16.25 0C20.5584 0.00454972 24.689 1.71806 27.7355 4.76454C30.7819 7.81102 32.4955 11.9416 32.5 16.25ZM30 16.25C30 13.5305 29.1936 10.8721 27.6827 8.61091C26.1718 6.34973 24.0244 4.58736 21.5119 3.54666C18.9994 2.50595 16.2348 2.23366 13.5675 2.7642C10.9003 3.29475 8.45026 4.60431 6.52729 6.52728C4.60432 8.45025 3.29476 10.9003 2.76421 13.5675C2.23366 16.2347 2.50596 18.9994 3.54666 21.5119C4.58737 24.0244 6.34974 26.1718 8.61092 27.6827C10.8721 29.1936 13.5305 30 16.25 30C19.8955 29.9959 23.3904 28.5459 25.9682 25.9682C28.5459 23.3904 29.9959 19.8955 30 16.25Z"
                                    fill="#2563EB" />
                            </svg>

                            Medical Information
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                            <div>
                                <label for="modal_height" class="block text-sm font-semibold text-blue-700 mb-2">
                                    Height (cm) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="modal_height" name="height" placeholder="0.0" required
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_weight" class="block text-sm font-semibold text-blue-700 mb-2">
                                    Weight (kg) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="modal_weight" name="weight" placeholder="0.0" required
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_temperature" class="block text-sm font-semibold text-blue-700 mb-2">
                                    Temperature (°C)
                                </label>
                                <input type="number" id="modal_temperature" name="temperature" placeholder="0"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_blood_pressure"
                                    class="block text-sm font-semibold text-blue-700 mb-2">
                                    Blood Pressure
                                </label>
                                <input type="text" id="modal_blood_pressure" name="blood_pressure" placeholder="0"
                                    class="form-input-modal w-full rounded-xl border-blue-200 px-4 py-3">
                            </div>

                            <div>
                                <label for="modal_blood_type" class="block text-sm font-semibold text-blue-700 mb-2">
                                    Blood Type <span class="text-red-500">*</span>
                                </label>
                                <select id="modal_blood_type" name="blood_type" required
                                    class="form-select-modal w-full rounded-xl border-blue-200 px-4 py-3">
                                    <option value="">Select Blood Type</option>
                                    <option>A+</option>
                                    <option>A-</option>
                                    <option>B+</option>
                                    <option>B-</option>
                                    <option>AB+</option>
                                    <option>AB-</option>
                                    <option>O+</option>
                                    <option>O-</option>
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
                    <span class="flex text-center items-center gap-3 text-sm text-blue-600 px-4 py-2 rounded-full">
                        <svg width="33" height="33" viewBox="0 0 33 33" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M16.25 0C13.0361 0 9.89429 0.953046 7.22199 2.73862C4.54969 4.52419 2.46689 7.06209 1.23696 10.0314C0.00704087 13.0007 -0.314763 16.268 0.312247 19.4202C0.939256 22.5724 2.48692 25.4679 4.75952 27.7405C7.03213 30.0131 9.9276 31.5607 13.0798 32.1878C16.232 32.8148 19.4993 32.493 22.4686 31.263C25.4379 30.0331 27.9758 27.9503 29.7614 25.278C31.547 22.6057 32.5 19.4639 32.5 16.25C32.4955 11.9416 30.7819 7.81102 27.7355 4.76454C24.689 1.71806 20.5584 0.00454972 16.25 0ZM16.25 30C13.5305 30 10.8721 29.1936 8.61092 27.6827C6.34974 26.1718 4.58737 24.0244 3.54666 21.5119C2.50596 18.9994 2.23366 16.2347 2.76421 13.5675C3.29476 10.9003 4.60432 8.45025 6.52729 6.52728C8.45026 4.60431 10.9003 3.29475 13.5675 2.7642C16.2348 2.23366 18.9994 2.50595 21.5119 3.54666C24.0244 4.58736 26.1718 6.34973 27.6827 8.61091C29.1936 10.8721 30 13.5305 30 16.25C29.9959 19.8955 28.5459 23.3904 25.9682 25.9682C23.3904 28.5459 19.8955 29.9959 16.25 30ZM18.75 23.75C18.75 24.0815 18.6183 24.3995 18.3839 24.6339C18.1495 24.8683 17.8315 25 17.5 25C16.837 25 16.2011 24.7366 15.7322 24.2678C15.2634 23.7989 15 23.163 15 22.5V16.25C14.6685 16.25 14.3505 16.1183 14.1161 15.8839C13.8817 15.6495 13.75 15.3315 13.75 15C13.75 14.6685 13.8817 14.3505 14.1161 14.1161C14.3505 13.8817 14.6685 13.75 15 13.75C15.663 13.75 16.2989 14.0134 16.7678 14.4822C17.2366 14.9511 17.5 15.587 17.5 16.25V22.5C17.8315 22.5 18.1495 22.6317 18.3839 22.8661C18.6183 23.1005 18.75 23.4185 18.75 23.75ZM13.75 9.375C13.75 9.00416 13.86 8.64165 14.066 8.33331C14.272 8.02496 14.5649 7.78464 14.9075 7.64273C15.2501 7.50081 15.6271 7.46368 15.9908 7.53603C16.3545 7.60837 16.6886 7.78695 16.9508 8.04917C17.2131 8.3114 17.3916 8.64549 17.464 9.0092C17.5363 9.37292 17.4992 9.74992 17.3573 10.0925C17.2154 10.4351 16.975 10.728 16.6667 10.934C16.3584 11.14 15.9958 11.25 15.625 11.25C15.1277 11.25 14.6508 11.0525 14.2992 10.7008C13.9476 10.3492 13.75 9.87228 13.75 9.375Z"
                                fill="#2563EB" />
                        </svg>
                        Fields marked with * are required
                    </span>

                    <div class="flex gap-3">
                        <button type="button" onclick="clearAddPatientForm()"
                            class="flex px-6 py-4 text-center items-center gap-3 rounded-full border border-[#2563EB] text-[#2563EB] hover:bg-gray-200 font-medium">
                            <svg width="15" height="15" viewBox="0 0 15 15" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M14.781 13.7198C14.8507 13.7895 14.906 13.8722 14.9437 13.9632C14.9814 14.0543 15.0008 14.1519 15.0008 14.2504C15.0008 14.349 14.9814 14.4465 14.9437 14.5376C14.906 14.6286 14.8507 14.7114 14.781 14.781C14.7114 14.8507 14.6286 14.906 14.5376 14.9437C14.4465 14.9814 14.349 15.0008 14.2504 15.0008C14.1519 15.0008 14.0543 14.9814 13.9632 14.9437C13.8722 14.906 13.7895 14.8507 13.7198 14.781L7.50042 8.56073L1.28104 14.781C1.14031 14.9218 0.94944 15.0008 0.750417 15.0008C0.551394 15.0008 0.360523 14.9218 0.219792 14.781C0.0790615 14.6403 3.92322e-09 14.4494 0 14.2504C-3.92322e-09 14.0514 0.0790615 13.8605 0.219792 13.7198L6.4401 7.50042L0.219792 1.28104C0.0790615 1.14031 0 0.94944 0 0.750417C0 0.551394 0.0790615 0.360523 0.219792 0.219792C0.360523 0.0790615 0.551394 0 0.750417 0C0.94944 0 1.14031 0.0790615 1.28104 0.219792L7.50042 6.4401L13.7198 0.219792C13.8605 0.0790615 14.0514 -3.92322e-09 14.2504 0C14.4494 3.92322e-09 14.6403 0.0790615 14.781 0.219792C14.9218 0.360523 15.0008 0.551394 15.0008 0.750417C15.0008 0.94944 14.9218 1.14031 14.781 1.28104L8.56073 7.50042L14.781 13.7198Z"
                                    fill="#2563EB" />
                            </svg>

                            Clear Form
                        </button>
                        <button type="submit" name="add_patient" form="patientForm"
                            class="flex items-center text-center gap-3 px-8 py-4 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-medium shadow">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M21.3112 2.689C21.1225 2.5005 20.8871 2.36569 20.629 2.29846C20.371 2.23122 20.0997 2.234 19.843 2.3065H19.829L1.83461 7.7665C1.54248 7.85069 1.28283 8.02166 1.09007 8.25676C0.897302 8.49185 0.780525 8.77997 0.75521 9.08294C0.729895 9.3859 0.797238 9.6894 0.948314 9.95323C1.09939 10.2171 1.32707 10.4287 1.60117 10.5602L9.56242 14.4377L13.4343 22.3943C13.5547 22.6513 13.7462 22.8685 13.9861 23.0201C14.226 23.1718 14.5042 23.2517 14.788 23.2502C14.8312 23.2502 14.8743 23.2484 14.9174 23.2446C15.2201 23.2201 15.5081 23.1036 15.7427 22.9107C15.9773 22.7178 16.1473 22.4578 16.2299 22.1656L21.6862 4.17119C21.6862 4.1665 21.6862 4.16181 21.6862 4.15712C21.7596 3.90115 21.7636 3.63024 21.6977 3.37223C21.6318 3.11421 21.4984 2.8784 21.3112 2.689ZM14.7965 21.7362L14.7918 21.7493V21.7427L11.0362 14.0271L15.5362 9.52712C15.6709 9.38533 15.7449 9.19651 15.7424 9.00094C15.7399 8.80537 15.6611 8.61852 15.5228 8.48022C15.3845 8.34191 15.1976 8.26311 15.002 8.26061C14.8065 8.2581 14.6177 8.3321 14.4759 8.46681L9.97586 12.9668L2.25742 9.21119H2.25086H2.26399L20.2499 3.75025L14.7965 21.7362Z"
                                    fill="white" />
                            </svg>
                            Register Patient
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Enhanced Export functionality
        function toggleExportOptions() {
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.toggle('show');
        }

        // Export all records based on current filter
        function exportAllRecords() {
            // Close dropdown
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.remove('show');

            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Get current patient type filter
            const patientTypeSelect = document.querySelector('select[name="patient_type"]');
            const currentPatientType = patientTypeSelect ? patientTypeSelect.value : 'all';

            // Build export URL
            let url = `existing_info_patients.php?export=excel&patient_type=${currentPatientType}`;

            // Add current search parameters
            const tab = urlParams.get('tab');
            const search = urlParams.get('search');
            const searchBy = urlParams.get('search_by');

            if (tab) url += `&tab=${tab}`;
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (searchBy) url += `&search_by=${searchBy}`;

            // Show loading message
            const typeLabels = {
                'all': 'All Patients',
                'registered': 'Registered Patients',
                'regular': 'Regular Patients'
            };
            showNotification('info', `Exporting ${typeLabels[currentPatientType] || 'All Patients'}...`);

            // Open export URL in new tab
            const exportWindow = window.open(url, '_blank');

            // Check if popup was blocked
            if (!exportWindow || exportWindow.closed || typeof exportWindow.closed == 'undefined') {
                showNotification('error', 'Pop-up blocked! Please allow pop-ups for this site to export.');

                // Alternative: Use form submission
                const form = document.createElement('form');
                form.method = 'GET';
                form.action = url;
                form.target = '_blank';
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        // Enable manual selection mode
        function enableManualSelection() {
            // Close dropdown
            const exportOptions = document.getElementById('exportOptions');
            exportOptions.classList.remove('show');

            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Build URL for manual selection mode
            let url = 'existing_info_patients.php?tab=patients-tab&manual_select=true';

            // Add patient type filter if exists
            const patientType = urlParams.get('patient_type');
            if (patientType) {
                url += `&patient_type=${patientType}`;
            }

            // Redirect to manual selection mode
            window.location.href = url;
        }

        // Disable manual selection mode
        function disableManualSelection() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Build URL to return to normal view
            let url = 'existing_info_patients.php?tab=patients-tab';

            // Add patient type filter if exists
            const patientType = urlParams.get('patient_type');
            if (patientType) {
                url += `&patient_type=${patientType}`;
            }

            // Redirect to normal view
            window.location.href = url;
        }

        // Toggle all checkboxes in manual selection
        function toggleAllSelection(checkbox) {
            const checkboxes = document.querySelectorAll('.patient-select');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        // Toggle all patients for export
        function toggleAllPatients(checkbox) {
            const checkboxes = document.querySelectorAll('.patient-select');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }

        // Update selected count display
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.patient-select:checked');
            const countElement = document.getElementById('selectedCount');
            if (countElement) {
                countElement.textContent = checkboxes.length;
            }
        }

        // Close export dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const exportBtn = document.querySelector('.btn-export');
            const exportOptions = document.getElementById('exportOptions');

            if (exportBtn && !exportBtn.contains(event.target) &&
                exportOptions && !exportOptions.contains(event.target)) {
                exportOptions.classList.remove('show');
            }
        });

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', function () {
            updateSelectedCount();
        });

        // Rest of your existing JavaScript code remains the same...
        // Form validation functionality for modal
        document.addEventListener('DOMContentLoaded', function () {
            const modalFormFields = document.querySelectorAll('.modal-form-field');
            const modalRegisterBtn = document.getElementById('modalRegisterPatientBtn');
            const modalDateOfBirth = document.getElementById('modal_date_of_birth');
            const modalAge = document.getElementById('modal_age');

            // Initialize modal field validation
            modalFormFields.forEach(field => {
                // Set initial state
                updateModalFieldState(field);

                // Add event listeners
                field.addEventListener('input', function () {
                    updateModalFieldState(this);
                    checkModalFormValidity();
                });

                field.addEventListener('change', function () {
                    updateModalFieldState(this);
                    checkModalFormValidity();
                });

                field.addEventListener('blur', function () {
                    updateModalFieldState(this);
                });
            });

            // Age calculation from date of birth in modal
            if (modalDateOfBirth && modalAge) {
                modalDateOfBirth.addEventListener('change', function () {
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
                healthInfoForm.addEventListener('submit', async function (e) {
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

        // Print Patient Record Function
        function printPatientRecord() {
            const patientId = getPatientId();
            if (patientId) {
                // Open the print patient page in a new window
                const printWindow = window.open(`/community-health-tracker/api/print_patient.php?id=${patientId}`, '_blank', 'width=1200,height=800');
                if (printWindow) {
                    printWindow.focus();
                    // Listen for the window to load and trigger print
                    printWindow.onload = function () {
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
            notification.className = `custom-notification fixed top-6 right-6 z-50 px-6 py-4 rounded-xl shadow-lg border-2 ${type === 'error' ? 'alert-error' :
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
        window.onclick = function (event) {
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
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeViewModal();
                closeAddPatientModal();
            }
        });

        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
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
        document.addEventListener('DOMContentLoaded', function () {
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
        document.addEventListener('DOMContentLoaded', function () {
            const buttons = document.querySelectorAll('.btn-view, .btn-archive, .btn-add-patient, .btn-primary, .btn-success, .btn-gray, .btn-print, .btn-edit, .btn-save-medical, .btn-view-all, .btn-back-to-pagination, .pagination-btn');
            buttons.forEach(button => {
                button.style.borderStyle = 'solid';
            });
        });
    </script>

    <script>
        // Enhanced form submission with success modal
        document.addEventListener('DOMContentLoaded', function () {
            const healthInfoForm = document.getElementById('healthInfoForm');
            const viewModal = document.getElementById('viewModal');

            if (healthInfoForm) {
                healthInfoForm.addEventListener('submit', async function (e) {
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
                    printWindow.onload = function () {
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
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSuccessModal();
            }
        });

        // Click outside to close
        document.addEventListener('click', function (event) {
            const modal = document.getElementById('successModal');
            if (modal && event.target === modal) {
                closeSuccessModal();
            }
        });
    </script>
</body>

</html>