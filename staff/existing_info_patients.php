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

// Handle form submission for editing health info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_health_info'])) {
    // Validate required fields
    $required = ['patient_id', 'height', 'weight', 'blood_type', 'gender'];
    $missing = array();
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        $error = "Please fill in all required fields: " . implode(', ', str_replace('_', ' ', $missing));
    } else {
        try {
            $patient_id = $_POST['patient_id'];
            $gender = $_POST['gender'];
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
                $message = "Patient health information updated successfully!";
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                    (patient_id, gender, height, weight, blood_type, allergies, 
                    medical_history, current_medications, family_history, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $patient_id, $gender, $height, $weight, $blood_type, $allergies,
                    $medical_history, $current_medications, $family_history
                ]);
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
            
            // Insert into main patients table
            $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                (full_name, age, gender, address, contact, last_checkup, added_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $age, $gender, $address, $contact, $lastCheckup, $_SESSION['user']['id']]);
            $patientId = $pdo->lastInsertId();
            
            // Insert into medical info table
            $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                (patient_id, height, weight, blood_type, allergies, medical_history, current_medications, family_history) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $patientId, $height, $weight, $bloodType, 
                $allergies, $medicalHistory, $currentMedications, $familyHistory
            ]);
            
            $pdo->commit();
            
            $message = 'Patient record added successfully!';
            header('Location: existing_info_patients.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error adding patient record: ' . $e->getMessage();
        }
    } else {
        $error = 'Full name is required.';
    }
}

// Get search term if exists
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get patient ID if selected
$selectedPatientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : (isset($_POST['patient_id']) ? $_POST['patient_id'] : '');

// Check if view printed information is requested
$viewPrinted = isset($_GET['view_printed']) && $_GET['view_printed'] == 'true';

// Get list of patients matching search
$patients = [];
if (!empty($searchTerm)) {
    try {
        // First check what columns exist in the table
        $stmt = $pdo->prepare("SHOW COLUMNS FROM sitio1_patients LIKE 'gender'");
        $stmt->execute();
        $genderColumnExists = $stmt->fetch();
        
        // Build query based on available columns
        $query = "SELECT id, full_name, age" . ($genderColumnExists ? ", gender" : "") . 
                 " FROM sitio1_patients WHERE added_by = ? AND full_name LIKE ? ORDER BY full_name LIMIT 10";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['user']['id'], "%$searchTerm%"]);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching patients: " . $e->getMessage();
    }
}

// Get all patients with their medical info for the patient list
try {
    $stmt = $pdo->prepare("SELECT p.*, m.height, m.weight, m.blood_type, m.allergies, 
                          m.medical_history, m.current_medications, m.family_history
                          FROM sitio1_patients p
                          LEFT JOIN existing_info_patients m ON p.id = m.patient_id
                          WHERE p.added_by = ? AND p.deleted_at IS NULL
                          ORDER BY p.created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user']['id']]);
    $allPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching patient records: " . $e->getMessage();
}

// Get existing health info if patient is selected
$health_info = [];
$patient_details = [];
if (!empty($selectedPatientId)) {
    try {
        // Get basic patient info
        $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ?");
        $stmt->execute([$selectedPatientId]);
        $patient_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient_details) {
            $error = "Patient not found!";
        } else {
            // Get health info
            $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
            $stmt->execute([$selectedPatientId]);
            $health_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching health information: " . $e->getMessage();
    }
}

// If in view printed information mode, show simplified layout
if ($viewPrinted && !empty($selectedPatientId) && !empty($patient_details)): ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Health Record - <?= htmlspecialchars($patient_details['full_name']) ?></title>
        <style>
            /* Base Styles */
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background: #f9f9f9;
            }
            
            /* Document Container */
            .health-record {
                max-width: 800px;
                margin: 0 auto;
                padding: 30px;
                background: white;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            
            /* Header with Logo */
            .record-header {
                display: flex;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #3498db;
            }
            
            .clinic-logo {
                width: 80px;
                height: 80px;
                margin-right: 20px;
                background-color: #3498db;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 14px;
                text-align: center;
            }
            
            .clinic-info {
                flex: 1;
            }
            
            .clinic-name {
                font-size: 24px;
                font-weight: bold;
                color: #2c3e50;
                margin: 0 0 5px 0;
            }
            
            .clinic-address {
                font-size: 14px;
                color: #7f8c8d;
                margin: 0;
            }
            
            .document-title {
                font-size: 20px;
                color: #3498db;
                text-align: center;
                margin: 20px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            /* Patient Information */
            .patient-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 5px;
                margin-bottom: 30px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .info-item {
                margin-bottom: 10px;
            }
            
            .info-label {
                font-weight: bold;
                color: #2c3e50;
                display: inline-block;
                min-width: 120px;
            }
            
            /* Sections */
            .section {
                margin-bottom: 25px;
                page-break-inside: avoid;
            }
            
            .section-title {
                font-size: 16px;
                color: #3498db;
                border-bottom: 1px solid #ecf0f1;
                padding-bottom: 5px;
                margin-bottom: 15px;
                text-transform: uppercase;
            }
            
            /* Vital Stats */
            .vital-stats {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            
            .stat-item {
                background: #ecf0f1;
                padding: 10px;
                border-radius: 5px;
            }
            
            .stat-label {
                font-weight: bold;
                font-size: 14px;
                color: #7f8c8d;
            }
            
            .stat-value {
                font-size: 18px;
                color: #2c3e50;
            }
            
            /* BMI Indicator */
            .bmi-value {
                font-weight: bold;
            }
            
            .underweight { color: #3498db; }
            .normal { color: #2ecc71; }
            .overweight { color: #f39c12; }
            .obese { color: #e74c3c; }
            
            /* Content Areas */
            .content-area {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            
            /* QR Code */
            .qr-container {
                text-align: center;
                margin: 30px 0;
            }
            
            .qr-code {
                display: inline-block;
                padding: 10px;
                background: white;
                border: 1px solid #ecf0f1;
                border-radius: 5px;
            }
            
            .qr-label {
                font-size: 12px;
                color: #7f8c8d;
                margin-top: 5px;
            }
            
            /* Signature Area */
            .signature-area {
                display: flex;
                justify-content: space-between;
                margin-top: 50px;
            }
            
            .signature-box {
                width: 250px;
                text-align: center;
            }
            
            .signature-line {
                border-top: 1px solid #333;
                margin: 40px auto 5px;
                width: 80%;
            }
            
            /* Print Styles */
            @media print {
                body {
                    background: none;
                    padding: 0;
                    font-size: 12pt;
                }
                
                .health-record {
                    box-shadow: none;
                    padding: 0;
                    max-width: 100%;
                }
                
                .no-print {
                    display: none !important;
                }
                
                @page {
                    size: A4;
                    margin: 15mm;
                }
                
                .page-break {
                    page-break-after: always;
                }
            }
            
            /* Action Buttons */
            .action-buttons {
                text-align: center;
                margin: 30px 0;
                padding: 15px;
                background: #ecf0f1;
                border-radius: 5px;
            }
            
            .action-btn {
                padding: 10px 20px;
                margin: 0 10px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.3s;
            }
            
            .print-btn {
                background-color: #3498db;
                color: white;
            }
            
            .print-btn:hover {
                background-color: #2980b9;
            }
            
            .close-btn {
                background-color: #95a5a6;
                color: white;
            }
            
            .close-btn:hover {
                background-color: #7f8c8d;
            }
            
            .export-btn {
                background-color: #2ecc71;
                color: white;
            }
            
            .export-btn:hover {
                background-color: #27ae60;
            }
        </style>
    </head>
    <body>
        <div class="health-record">
            <!-- Document Header with Logo -->
            <div class="record-header">
                <div class="clinic-logo">
                    CHC<br>Gawi
                </div>
                <div class="clinic-info">
                    <h1 class="clinic-name">Community Health Center</h1>
                    <p class="clinic-address">Barangay Health Center, Gawi, Oslob, Cebu, Philippines</p>
                </div>
            </div>
            
            <h2 class="document-title">Patient Health Record</h2>
            
            <!-- Patient Information -->
            <div class="patient-info">
                <div class="info-grid">
                    <div>
                        <div class="info-item">
                            <span class="info-label">Patient Name:</span>
                            <?= htmlspecialchars($patient_details['full_name']) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Age:</span>
                            <?= htmlspecialchars($patient_details['age'] ?? 'N/A') ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Gender:</span>
                            <?= htmlspecialchars($health_info['gender'] ?? ($patient_details['gender'] ?? 'N/A')) ?>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <span class="info-label">Record Date:</span>
                            <?= date('F j, Y', strtotime($health_info['created_at'] ?? 'now')) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Updated:</span>
                            <?= date('F j, Y', strtotime($health_info['updated_at'] ?? $health_info['created_at'] ?? 'now')) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Record ID:</span>
                            <?= htmlspecialchars($selectedPatientId) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vital Statistics -->
            <div class="section">
                <h3 class="section-title">Vital Statistics</h3>
                <div class="vital-stats">
                    <div class="stat-item">
                        <div class="stat-label">Height</div>
                        <div class="stat-value"><?= htmlspecialchars($health_info['height'] ?? 'N/A') ?> cm</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Weight</div>
                        <div class="stat-value"><?= htmlspecialchars($health_info['weight'] ?? 'N/A') ?> kg</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Blood Type</div>
                        <div class="stat-value"><?= htmlspecialchars($health_info['blood_type'] ?? 'N/A') ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">BMI</div>
                        <div class="stat-value">
                            <?php 
                                if (!empty($health_info['height']) && !empty($health_info['weight'])) {
                                    $bmi = $health_info['weight'] / (($health_info['height']/100) * ($health_info['height']/100));
                                    echo '<span class="bmi-value">' . number_format($bmi, 1) . '</span>';
                                    
                                    // Add BMI category
                                    if ($bmi < 18.5) {
                                        echo '<span class="underweight"> (Underweight)</span>';
                                    } elseif ($bmi >= 18.5 && $bmi < 25) {
                                        echo '<span class="normal"> (Normal)</span>';
                                    } elseif ($bmi >= 25 && $bmi < 30) {
                                        echo '<span class="overweight"> (Overweight)</span>';
                                    } else {
                                        echo '<span class="obese"> (Obese)</span>';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Medical Information Sections -->
            <?php if (!empty($health_info['allergies'])): ?>
            <div class="section">
                <h3 class="section-title">Allergies</h3>
                <div class="content-area">
                    <?= nl2br(htmlspecialchars($health_info['allergies'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($health_info['medical_history'])): ?>
            <div class="section">
                <h3 class="section-title">Medical History</h3>
                <div class="content-area">
                    <?= nl2br(htmlspecialchars($health_info['medical_history'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($health_info['current_medications'])): ?>
            <div class="section">
                <h3 class="section-title">Current Medications</h3>
                <div class="content-area">
                    <?= nl2br(htmlspecialchars($health_info['current_medications'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($health_info['family_history'])): ?>
            <div class="section">
                <h3 class="section-title">Family History</h3>
                <div class="content-area">
                    <?= nl2br(htmlspecialchars($health_info['family_history'])) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- QR Code for Verification -->
            <div class="qr-container">
                <div class="qr-code">
                    <div id="qrCode"></div>
                    <div class="qr-label">Scan to verify this record</div>
                </div>
            </div>
            
            <!-- Signature Area -->
            <div class="signature-area">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <p>Patient's Signature</p>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <p>Healthcare Provider</p>
                    <p><strong><?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?></strong></p>
                </div>
            </div>
            
            <!-- Action Buttons (Not Printed) -->
            <div class="action-buttons no-print">
                <button onclick="window.print()" class="action-btn print-btn">Print Record</button>
                <button onclick="window.close()" class="action-btn close-btn">Close Window</button>
                <button onclick="exportToPDF()" class="action-btn export-btn">Export as PDF</button>
            </div>
        </div>
    
        <!-- QR Code Generation -->
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
        <!-- PDF Generation -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        
        <script>
            // Generate QR Code
            QRCode.toCanvas(document.getElementById('qrCode'), 
                `Patient Health Record\n` +
                `Name: <?= htmlspecialchars($patient_details['full_name']) ?>\n` +
                `ID: <?= htmlspecialchars($selectedPatientId) ?>\n` +
                `Date: <?= date('M j, Y', strtotime($health_info['updated_at'] ?? $health_info['created_at'] ?? 'now')) ?>`, 
                { width: 150, margin: 0 }, 
                function(error) {
                    if (error) console.error(error);
                }
            );
            
            // Export to PDF function
            function exportToPDF() {
                // Load jsPDF
                const { jsPDF } = window.jspdf;
                
                // Get the health record element
                const element = document.querySelector('.health-record');
                
                // Use html2canvas to capture the element
                html2canvas(element, {
                    scale: 2,
                    logging: false,
                    useCORS: true,
                    allowTaint: true
                }).then(canvas => {
                    // Create PDF
                    const imgData = canvas.toDataURL('image/png');
                    const pdf = new jsPDF('p', 'mm', 'a4');
                    const imgWidth = 210; // A4 width in mm
                    const pageHeight = 295; // A4 height in mm
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;
                    
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                    
                    // Add additional pages if needed
                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }
                    
                    // Save the PDF
                    pdf.save(`Health_Record_<?= htmlspecialchars($patient_details['full_name']) ?>.pdf`);
                });
            }
        </script>
    </body>
    </html>
    <?php
    exit();
    endif;
    ?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Patient Health Records</h1>
    
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <!-- Search Bar -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <form method="get" action="" class="mb-6">
            <div class="flex items-center">
                <div class="relative flex-grow">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" 
                        placeholder="Search patients by name...">
                </div>
                <button type="submit" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Search
                </button>
                <?php if (!empty($searchTerm)): ?>
                    <a href="?" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Patient List -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Patient Records</h2>
            <div class="flex space-x-2">
                <a href="deleted_patients.php" class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-1 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 4a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1V5a1 1 0 00-1-1H5zM12 4a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1V5a1 1 0 00-1-1h-1zM5 12a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1v-1a1 1 0 00-1-1H5z" clip-rule="evenodd" />
                    </svg>
                    Archive
                </a>
                <button id="showFormBtn" class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    Add Patient
                </button>
            </div>
        </div>
        
        <?php if (empty($allPatients)): ?>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No patients found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by adding a new patient.</p>
                <div class="mt-6">
                    <button id="showFormBtnEmpty" type="button" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        Add Patient
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age/Gender</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Blood Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Check-up</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($allPatients as $patient): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <svg class="h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($patient['full_name']) ?></div>
                                            <div class="text-sm text-gray-500">ID: <?= htmlspecialchars($patient['id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= $patient['age'] ?? 'N/A' ?></div>
                                    <div class="text-sm text-gray-500"><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $patient['blood_type'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= htmlspecialchars($patient['blood_type'] ?: 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $patient['last_checkup'] ? date('M d, Y', strtotime($patient['last_checkup'])) : 'N/A' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="?patient_id=<?= $patient['id'] ?>" class="text-blue-600 hover:text-blue-900" title="View Health Info">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <a href="?patient_id=<?= $patient['id'] ?>&view_printed=true" class="text-green-600 hover:text-green-900" title="Print Record">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                        <a href="soft_delete_patient.php?id=<?= $patient['id'] ?>" class="text-yellow-600 hover:text-yellow-900" title="Archive" onclick="return confirm('Move this patient record to archive?')">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M5 4a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1V5a1 1 0 00-1-1H5zM12 4a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1V5a1 1 0 00-1-1h-1zM5 12a1 1 0 00-1 1v1a1 1 0 001 1h1a1 1 0 001-1v-1a1 1 0 00-1-1H5z" />
                                                <path d="M3 5a2 2 0 012-2h1a2 2 0 012 2v1a2 2 0 01-2 2H5a2 2 0 01-2-2V5zM12 5a2 2 0 012-2h1a2 2 0 012 2v1a2 2 0 01-2 2h-1a2 2 0 01-2-2V5z" />
                                            </svg>
                                        </a>
                                        <a href="deleted_patients.php?id=<?= $patient['id'] ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Are you sure you want to permanently delete this patient record?')">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add New Patient Form (Initially Hidden) -->
    <div id="patientFormContainer" class="bg-white p-6 rounded-lg shadow mb-8 hidden">
        <h2 class="text-xl font-semibold mb-4">Add New Patient</h2>
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Information -->
                <div>
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="age" class="block text-gray-700 mb-2">Age</label>
                    <input type="number" id="age" name="age" min="0" max="120" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="gender" class="block text-gray-700 mb-2">Gender</label>
                    <select id="gender" name="gender" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="address" class="block text-gray-700 mb-2">Address</label>
                    <input type="text" id="address" name="address" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="contact" class="block text-gray-700 mb-2">Contact Number</label>
                    <input type="text" id="contact" name="contact" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="last_checkup" class="block text-gray-700 mb-2">Last Check-up Date</label>
                    <input type="date" id="last_checkup" name="last_checkup" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Medical Information -->
                <div>
                    <label for="height" class="block text-gray-700 mb-2">Height (cm)</label>
                    <input type="number" id="height" name="height" step="0.01" min="0" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="weight" class="block text-gray-700 mb-2">Weight (kg)</label>
                    <input type="number" id="weight" name="weight" step="0.01" min="0" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="blood_type" class="block text-gray-700 mb-2">Blood Type</label>
                    <select id="blood_type" name="blood_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                    <label for="allergies" class="block text-gray-700 mb-2">Allergies</label>
                    <textarea id="allergies" name="allergies" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div>
                    <label for="current_medications" class="block text-gray-700 mb-2">Current Medications</label>
                    <textarea id="current_medications" name="current_medications" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>
            
            <div class="mt-6">
                <label for="medical_history" class="block text-gray-700 mb-2">Medical History</label>
                <textarea id="medical_history" name="medical_history" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="mt-6">
                <label for="family_history" class="block text-gray-700 mb-2">Family History</label>
                <textarea id="family_history" name="family_history" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="mt-6 flex justify-between">
                <button type="button" id="cancelFormBtn" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600 transition">Cancel</button>
                <button type="submit" name="add_patient" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">Add Patient</button>
            </div>
        </form>
    </div>
    
    <!-- Patient Health Info Form (Shown when patient is selected) -->
    <?php if (!empty($selectedPatientId) && !empty($patient_details)): ?>
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Edit Health Information</h2>
                <div>
                    <a href="?patient_id=<?= htmlspecialchars($selectedPatientId) ?>&view_printed=true" 
                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                        <svg class="-ml-1 mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
                        </svg>
                        Print Record
                    </a>
                </div>
            </div>
            
            <form method="post" action="" id="healthInfoForm">
                <input type="hidden" name="patient_id" value="<?= htmlspecialchars($selectedPatientId) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Add gender field -->
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-1 required-field">Gender</label>
                        <select id="gender" name="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- Select gender --</option>
                            <option value="Male" <?= isset($health_info['gender']) && $health_info['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= isset($health_info['gender']) && $health_info['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= isset($health_info['gender']) && $health_info['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="height" class="block text-sm font-medium text-gray-700 mb-1 required-field">Height (cm)</label>
                        <input type="number" step="0.1" id="height" name="height" value="<?= htmlspecialchars($health_info['height'] ?? '') ?>" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter height in centimeters" required>
                    </div>
                    <div>
                        <label for="weight" class="block text-sm font-medium text-gray-700 mb-1 required-field">Weight (kg)</label>
                        <input type="number" step="0.1" id="weight" name="weight" value="<?= htmlspecialchars($health_info['weight'] ?? '') ?>" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter weight in kilograms" required>
                    </div>
                    <div>
                        <label for="blood_type" class="block text-sm font-medium text-gray-700 mb-1 required-field">Blood Type</label>
                        <select id="blood_type" name="blood_type" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="">-- Select blood type --</option>
                            <option value="A+" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'A+' ? 'selected' : '' ?>>A+</option>
                            <option value="A-" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'A-' ? 'selected' : '' ?>>A-</option>
                            <option value="B+" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'B+' ? 'selected' : '' ?>>B+</option>
                            <option value="B-" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'B-' ? 'selected' : '' ?>>B-</option>
                            <option value="AB+" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'AB+' ? 'selected' : '' ?>>AB+</option>
                            <option value="AB-" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'AB-' ? 'selected' : '' ?>>AB-</option>
                            <option value="O+" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'O+' ? 'selected' : '' ?>>O+</option>
                            <option value="O-" <?= isset($health_info['blood_type']) && $health_info['blood_type'] == 'O-' ? 'selected' : '' ?>>O-</option>
                        </select>
                    </div>
                    <div>
                        <label for="bmi" class="block text-sm font-medium text-gray-700 mb-1">BMI</label>
                        <input type="text" id="bmi" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100" 
                            value="<?php 
                                if (!empty($health_info['height']) && !empty($health_info['weight'])) {
                                    $bmi = $health_info['weight'] / (($health_info['height']/100) * ($health_info['height']/100));
                                    echo number_format($bmi, 1);
                                    
                                    // Add BMI category
                                    if ($bmi < 18.5) {
                                        echo ' (Underweight)';
                                    } elseif ($bmi >= 18.5 && $bmi < 25) {
                                        echo ' (Normal weight)';
                                    } elseif ($bmi >= 25 && $bmi < 30) {
                                        echo ' (Overweight)';
                                    } else {
                                        echo ' (Obese)';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                            ?>" 
                            readonly>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="allergies" class="block text-sm font-medium text-gray-700 mb-1">Allergies</label>
                    <textarea id="allergies" name="allergies" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="List any known allergies"><?= htmlspecialchars($health_info['allergies'] ?? '') ?></textarea>
                </div>
                
                <div class="mt-6">
                    <label for="medical_history" class="block text-sm font-medium text-gray-700 mb-1">Medical History</label>
                    <textarea id="medical_history" name="medical_history" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Previous illnesses, surgeries, chronic conditions"><?= htmlspecialchars($health_info['medical_history'] ?? '') ?></textarea>
                </div>
                
                <div class="mt-6">
                    <label for="current_medications" class="block text-sm font-medium text-gray-700 mb-1">Current Medications</label>
                    <textarea id="current_medications" name="current_medications" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="List current medications with dosage"><?= htmlspecialchars($health_info['current_medications'] ?? '') ?></textarea>
                </div>
                
                <div class="mt-6">
                    <label for="family_history" class="block text-sm font-medium text-gray-700 mb-1">Family History</label>
                    <textarea id="family_history" name="family_history" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Family history of diseases"><?= htmlspecialchars($health_info['family_history'] ?? '') ?></textarea>
                </div>
                
                <div class="mt-6 flex justify-between">
                    <a href="?" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        Back to List
                    </a>
                    <button type="submit" name="save_health_info" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Save Health Information
                    </button>
                </div>
            </form>

            <div class="mt-6 qr-code">
                <div id="qrCodeContainerView"></div>
                <p class="text-sm text-gray-500 mt-2">Scan this QR code to verify this record</p>
            </div>

            <script>
            document.getElementById('healthInfoForm').addEventListener('submit', function(e) {
                const requiredFields = [
                    'height', 'weight', 'blood_type', 'gender'
                ];
                
                let isValid = true;
                
                requiredFields.forEach(field => {
                    const input = document.querySelector(`[name="${field}"]`);
                    if (!input.value.trim()) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields (marked with *)');
                }
            });

            // Recalculate BMI when height or weight changes
            const heightInput = document.getElementById('height');
            const weightInput = document.getElementById('weight');
            const bmiInput = document.getElementById('bmi');

            function calculateBMI() {
                const height = parseFloat(heightInput.value);
                const weight = parseFloat(weightInput.value);
                
                if (height && weight) {
                    const bmi = weight / Math.pow(height/100, 2);
                    let bmiText = bmi.toFixed(1);
                    
                    // Add BMI category
                    if (bmi < 18.5) {
                        bmiText += ' (Underweight)';
                    } else if (bmi >= 18.5 && bmi < 25) {
                        bmiText += ' (Normal weight)';
                    } else if (bmi >= 25 && bmi < 30) {
                        bmiText += ' (Overweight)';
                    } else {
                        bmiText += ' (Obese)';
                    }
                    
                    bmiInput.value = bmiText;
                } else {
                    bmiInput.value = 'N/A';
                }
            }

            heightInput.addEventListener('input', calculateBMI);
            weightInput.addEventListener('input', calculateBMI);

            // Generate QR code in view mode
            QRCode.toCanvas(document.getElementById('qrCodeContainerView'), 
                `Patient: <?= htmlspecialchars($patient_details['full_name']) ?>\n` +
                `Record ID: <?= htmlspecialchars($selectedPatientId) ?>\n` +
                `Date: <?= date('Y-m-d') ?>`, 
                { width: 150 }, 
                function(error) {
                    if (error) console.error(error);
                }
            );
            </script>

            <style>
            .required-field::after {
                content: " *";
                color: red;
            }
            .border-red-500 {
                border-color: #ef4444;
            }
            </style>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const showFormBtn = document.getElementById('showFormBtn');
        const showFormBtnEmpty = document.getElementById('showFormBtnEmpty');
        const cancelFormBtn = document.getElementById('cancelFormBtn');
        const patientFormContainer = document.getElementById('patientFormContainer');
        
        if (showFormBtn) {
            showFormBtn.addEventListener('click', function() {
                patientFormContainer.classList.remove('hidden');
                window.scrollTo({
                    top: patientFormContainer.offsetTop,
                    behavior: 'smooth'
                });
            });
        }
        
        if (showFormBtnEmpty) {
            showFormBtnEmpty.addEventListener('click', function() {
                patientFormContainer.classList.remove('hidden');
                window.scrollTo({
                    top: patientFormContainer.offsetTop,
                    behavior: 'smooth'
                });
            });
        }
        
        if (cancelFormBtn) {
            cancelFormBtn.addEventListener('click', function() {
                patientFormContainer.classList.add('hidden');
            });
        }
    });
</script>

