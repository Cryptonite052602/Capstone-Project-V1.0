<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];

// Get user information including email and date of birth from the database
$userInfo = [];
try {
    $stmt = $pdo->prepare("SELECT email, full_name, date_of_birth, created_at FROM sitio1_users WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = 'Error fetching user information: ' . $e->getMessage();
}

// Use database values as primary source, fallback to session
$userEmail = $userInfo['email'] ?? $_SESSION['user']['email'] ?? 'Not provided';
$userFullName = $userInfo['full_name'] ?? $_SESSION['user']['full_name'] ?? 'Not provided';
$userDateOfBirth = $userInfo['date_of_birth'] ?? $_SESSION['user']['date_of_birth'] ?? null;
$userCreatedAt = $userInfo['created_at'] ?? $_SESSION['user']['created_at'] ?? date('Y-m-d');
$error = '';
$success = '';

// Get patient info
$patientInfo = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE user_id = ?");
    $stmt->execute([$userId]);
    $patientInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $error = 'Error fetching patient information: ' . $e->getMessage();
}

// Get medical info
$medicalInfo = [];
if (!empty($patientInfo['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
        $stmt->execute([$patientInfo['id']]);
        $medicalInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // Not critical if this fails
    }
}

// Get user's health records
$healthRecords = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.*, s.full_name as doctor_name
        FROM patient_visits v
        JOIN sitio1_staff s ON v.staff_id = s.id
        JOIN sitio1_patients p ON v.patient_id = p.id
        WHERE p.user_id = ?
        ORDER BY v.visit_date DESC
    ");
    $stmt->execute([$userId]);
    $healthRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching health records: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Health Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Consistent warm blue color scheme */
        :root {
            --warm-blue: #3b82f6;
            --warm-blue-light: #dbeafe;
            --warm-blue-dark: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --border-light: #e5e7eb;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .btn-primary {
            background-color: white;
            border: 2px solid var(--warm-blue);
            color: var(--warm-blue);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--warm-blue-light);
            transform: translateY(-2px);
        }

        .nav-pill {
            background-color: white;
            border: 2px solid var(--warm-blue);
            color: var(--warm-blue);
            transition: all 0.3s ease;
        }

        .nav-pill.active {
            background-color: var(--warm-blue);
            color: white;
        }

        .nav-pill:hover:not(.active) {
            background-color: var(--warm-blue-light);
        }

        .info-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
        }

        .health-record-item {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--warm-blue);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-light);
            cursor: pointer;
            z-index: 10;
        }

        .close-modal:hover {
            color: var(--text-dark);
        }

        .section-title {
            position: relative;
            padding-left: 1rem;
        }

        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background: var(--warm-blue);
            border-radius: 4px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
            <div class="mb-6 md:mb-0">
                <h1 class="text-3xl font-bold text-gray-800">My Health Profile</h1>
                <p class="text-gray-600 mt-2">View your medical history and personal health information</p>
            </div>
            <div class="flex items-center bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-500 text-2xl"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-sm text-gray-500">Email: <?= htmlspecialchars($userEmail) ?></p>
                    <?php if ($userDateOfBirth): ?>
                        <p class="text-sm text-gray-500">Date of Birth: <?= date('M d, Y', strtotime($userDateOfBirth)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-2xl mb-6 flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-8 flex flex-wrap border border-gray-200">
            <button class="nav-pill active flex items-center mr-3 mb-2 px-4 py-2 rounded-full" data-tab="records">
                <i class="fas fa-file-medical mr-2"></i> Health Records
                <span class="bg-blue-100 text-blue-800 text-xs font-medium ml-2 px-3 py-1 rounded-full">
                    <?= count($healthRecords) ?>
                </span>
            </button>
            <button class="nav-pill flex items-center mr-3 mb-2 px-4 py-2 rounded-full" data-tab="profile">
                <i class="fas fa-user-circle mr-2"></i> Personal Info
            </button>
            <button class="nav-pill flex items-center mr-3 mb-2 px-4 py-2 rounded-full" data-tab="medical">
                <i class="fas fa-heartbeat mr-2"></i> Medical Info
            </button>
        </div>

        <!-- Health Records Tab -->
        <div id="records" class="tab-content active">
            <?php if (empty($healthRecords)): ?>
                <div class="bg-white p-12 rounded-2xl shadow-sm text-center border border-gray-200">
                    <i class="fas fa-file-medical-alt text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Health Records Found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Your medical visit records will appear here once you have appointments with our healthcare providers.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($healthRecords as $index => $record): ?>
                        <div class="health-record-item p-6">
                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                <div class="flex items-center mb-4 md:mb-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center mr-4">
                                        <i class="fas fa-calendar-check text-blue-500"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-lg">Visit on <?= date('M d, Y', strtotime($record['visit_date'] ?? 'now')) ?></h3>
                                        <p class="text-gray-500">With Dr. <?= htmlspecialchars($record['doctor_name'] ?? 'Unknown') ?></p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="viewRecord(<?= htmlspecialchars(json_encode($record)) ?>)" 
                                            class="btn-primary flex items-center px-4 py-2 rounded-full">
                                        <i class="fas fa-eye mr-2"></i> View
                                    </button>
                                    <button onclick="printRecord(<?= htmlspecialchars(json_encode($record)) ?>)" 
                                            class="btn-primary flex items-center px-4 py-2 rounded-full">
                                        <i class="fas fa-print mr-2"></i> Print
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-blue-800 mb-2">Diagnosis</h4>
                                    <p class="text-gray-700"><?= htmlspecialchars($record['diagnosis'] ?? 'No diagnosis recorded') ?></p>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-green-800 mb-2">Treatment</h4>
                                    <p class="text-gray-700"><?= htmlspecialchars($record['treatment'] ?? 'No treatment recorded') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Section -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-2xl border border-blue-200">
                        <p class="text-blue-600 font-semibold text-sm">Total Visits</p>
                        <p class="text-3xl font-bold text-blue-800 mt-2"><?= count($healthRecords) ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-2xl border border-green-200">
                        <p class="text-green-600 font-semibold text-sm">Last Check-up</p>
                        <p class="text-lg font-bold text-green-800 mt-2">
                            <?php 
                                if (!empty($healthRecords[0])) {
                                    echo date('M d, Y', strtotime($healthRecords[0]['visit_date']));
                                } else {
                                    echo 'N/A';
                                }
                            ?>
                        </p>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-2xl border border-purple-200">
                        <p class="text-purple-600 font-semibold text-sm">Account Status</p>
                        <p class="text-lg font-bold text-purple-800 mt-2">Active</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Personal Profile Tab -->
        <div id="profile" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Personal Information -->
                <div class="info-card p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-user text-blue-500 mr-3"></i> Personal Information
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Full Name</p>
                                <p class="text-gray-800 font-medium"><?= htmlspecialchars($userFullName) ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Email</p>
                                <p class="text-gray-800 font-medium"><?= htmlspecialchars($userEmail) ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Date of Birth</p>
                                <p class="text-gray-800 font-medium">
                                    <?php if ($userDateOfBirth): ?>
                                        <?= date('M d, Y', strtotime($userDateOfBirth)) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not provided</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Age</p>
                                <p class="text-gray-800 font-medium">
                                    <?php if (!empty($patientInfo['age'])): ?>
                                        <?= htmlspecialchars($patientInfo['age']) ?> years
                                    <?php elseif ($userDateOfBirth): ?>
                                        <?php 
                                            $birthDate = new DateTime($userDateOfBirth);
                                            $today = new DateTime();
                                            $age = $birthDate->diff($today)->y;
                                            echo $age . ' years';
                                        ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not provided</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Gender</p>
                                <p class="text-gray-800 font-medium"><?= !empty($patientInfo['gender']) ? htmlspecialchars($patientInfo['gender']) : '<span class="text-gray-400">Not provided</span>' ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Civil Status</p>
                                <p class="text-gray-800 font-medium"><?= !empty($patientInfo['civil_status']) ? htmlspecialchars($patientInfo['civil_status']) : '<span class="text-gray-400">Not provided</span>' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="info-card p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-address-card text-blue-500 mr-3"></i> Contact Information
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Address</p>
                            <p class="text-gray-800 font-medium"><?= !empty($patientInfo['address']) ? htmlspecialchars($patientInfo['address']) : '<span class="text-gray-400">Not provided</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Contact Number</p>
                            <p class="text-gray-800 font-medium"><?= !empty($patientInfo['contact']) ? htmlspecialchars($patientInfo['contact']) : '<span class="text-gray-400">Not provided</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Sitio/Barangay</p>
                            <p class="text-gray-800 font-medium"><?= !empty($patientInfo['sitio']) ? htmlspecialchars($patientInfo['sitio']) : '<span class="text-gray-400">Not provided</span>' ?></p>
                        </div>
                        
                        <?php if (!empty($patientInfo['last_checkup'])): ?>
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Last Check-up</p>
                            <p class="text-gray-800 font-medium"><?= date('M d, Y', strtotime($patientInfo['last_checkup'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="info-card p-6 lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-3"></i> Account Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Account Status</p>
                            <p class="text-gray-800 font-medium">
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    Active
                                </span>
                            </p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Member Since</p>
                            <p class="text-gray-800 font-medium"><?= date('M d, Y', strtotime($userCreatedAt)) ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Total Health Visits</p>
                            <p class="text-gray-800 font-medium"><?= count($healthRecords) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Information Tab -->
        <div id="medical" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Vital Statistics -->
                <div class="info-card p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-chart-line text-blue-500 mr-3"></i> Vital Statistics
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Blood Type</p>
                            <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['blood_type']) ? htmlspecialchars($medicalInfo['blood_type']) : '<span class="text-gray-400">Not recorded</span>' ?></p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Height</p>
                                <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['height']) ? htmlspecialchars($medicalInfo['height']) . ' cm' : '<span class="text-gray-400">--</span>' ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-sm font-medium mb-1">Weight</p>
                                <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['weight']) ? htmlspecialchars($medicalInfo['weight']) . ' kg' : '<span class="text-gray-400">--</span>' ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($medicalInfo['height']) && !empty($medicalInfo['weight'])): ?>
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">BMI</p>
                            <p class="text-gray-800 font-medium">
                                <?php 
                                    $height = $medicalInfo['height'] / 100;
                                    $weight = $medicalInfo['weight'];
                                    $bmi = $weight / ($height * $height);
                                    echo number_format($bmi, 1);
                                ?>
                                <span class="text-sm text-gray-500 ml-2">
                                    (<?= $bmi < 18.5 ? 'Underweight' : ($bmi < 25 ? 'Normal' : ($bmi < 30 ? 'Overweight' : 'Obese')) ?>)
                                </span>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Medical Details -->
                <div class="info-card p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-stethoscope text-blue-500 mr-3"></i> Medical Details
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Allergies</p>
                            <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['allergies']) ? nl2br(htmlspecialchars($medicalInfo['allergies'])) : '<span class="text-gray-400">No allergies recorded</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Current Medications</p>
                            <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['current_medications']) ? nl2br(htmlspecialchars($medicalInfo['current_medications'])) : '<span class="text-gray-400">No medications recorded</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-500 text-sm font-medium mb-1">Chronic Conditions</p>
                            <p class="text-gray-800 font-medium"><?= !empty($patientInfo['disease']) ? htmlspecialchars($patientInfo['disease']) : '<span class="text-gray-400">No chronic conditions recorded</span>' ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Medical History -->
                <div class="info-card p-6 lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-history text-blue-500 mr-3"></i> Medical History
                    </h3>
                    
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Medical History</p>
                        <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['medical_history']) ? nl2br(htmlspecialchars($medicalInfo['medical_history'])) : '<span class="text-gray-400">No medical history recorded</span>' ?></p>
                    </div>
                </div>
                
                <!-- Family History -->
                <div class="info-card p-6 lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-users text-blue-500 mr-3"></i> Family Medical History
                    </h3>
                    
                    <div>
                        <p class="text-gray-500 text-sm font-medium mb-1">Family History</p>
                        <p class="text-gray-800 font-medium"><?= !empty($medicalInfo['family_history']) ? nl2br(htmlspecialchars($medicalInfo['family_history'])) : '<span class="text-gray-400">No family medical history recorded</span>' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for viewing records - Initially hidden -->
    <div id="recordModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <div id="modalContent"></div>
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="printCurrentRecord()" class="btn-primary flex items-center px-4 py-2 rounded-full">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
                <button onclick="closeModal()" class="bg-gray-200 text-gray-700 flex items-center px-4 py-2 rounded-full hover:bg-gray-300">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.nav-pill').forEach(button => {
            button.addEventListener('click', () => {
                // Update tabs
                document.querySelectorAll('.nav-pill').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                
                // Show selected tab content
                const tabId = button.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabId).classList.add('active');
            });
        });

        let currentRecord = null;

        // View Record Function
        function viewRecord(record) {
            currentRecord = record;
            const modal = document.getElementById('recordModal');
            const modalContent = document.getElementById('modalContent');
            
            const visitDate = new Date(record.visit_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            const content = `
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Health Record</h2>
                <p class="text-gray-600 mb-6">Visit on ${visitDate}</p>
                
                <div class="bg-blue-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">Healthcare Provider</h3>
                    <p class="text-gray-700">Dr. ${record.doctor_name || 'Unknown'}</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-red-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-red-800 mb-2">Diagnosis</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">${record.diagnosis || 'No diagnosis recorded'}</p>
                    </div>
                    
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-green-800 mb-2">Treatment</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">${record.treatment || 'No treatment recorded'}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-yellow-800 mb-2">Prescription</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">${record.prescription || 'No prescription'}</p>
                    </div>
                    
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-purple-800 mb-2">Notes</h3>
                        <p class="text-gray-700 whitespace-pre-wrap">${record.notes || 'No additional notes'}</p>
                    </div>
                </div>
                
                ${record.next_visit_date ? `
                <div class="bg-indigo-50 p-4 rounded-lg mb-4">
                    <h3 class="font-semibold text-indigo-800 mb-2">Next Appointment</h3>
                    <p class="text-gray-700">${new Date(record.next_visit_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                </div>
                ` : ''}
            `;
            
            modalContent.innerHTML = content;
            modal.classList.add('active');
            
            // Prevent body scrolling when modal is open
            document.body.style.overflow = 'hidden';
        }

        // Close Modal Function
        function closeModal() {
            const modal = document.getElementById('recordModal');
            modal.classList.remove('active');
            
            // Restore body scrolling
            document.body.style.overflow = 'auto';
        }

        // Print Current Record from Modal
        function printCurrentRecord() {
            if (currentRecord) {
                printRecord(currentRecord);
            }
        }

        // Print Record Function
        function printRecord(record) {
            const printWindow = window.open('', '_blank');
            const visitDate = new Date(record.visit_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            });
            
            const content = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Health Record - ${visitDate}</title>
                    <style>
                        * { margin: 0; padding: 0; }
                        body { font-family: Arial, sans-serif; color: #333; }
                        .container { max-width: 8.5in; margin: 0 auto; padding: 40px; }
                        header { border-bottom: 3px solid #3b82f6; margin-bottom: 30px; padding-bottom: 20px; }
                        h1 { color: #3b82f6; font-size: 28px; margin-bottom: 5px; }
                        .visit-date { color: #666; font-size: 14px; }
                        .doctor { background: #f0f9ff; padding: 15px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #3b82f6; }
                        .section { margin-bottom: 25px; }
                        .section-title { color: #1f2937; font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
                        .section-content { color: #4b5563; line-height: 1.6; white-space: pre-wrap; }
                        footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #999; font-size: 12px; }
                        @media print { 
                            body { margin: 0; padding: 0; }
                            .container { padding: 0; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <header>
                            <h1>${record.visit_type ? record.visit_type.charAt(0).toUpperCase() + record.visit_type.slice(1) : 'General'} Visit Record</h1>
                            <div class="visit-date">Date: ${visitDate}</div>
                        </header>
                        
                        <div class="doctor">
                            <strong>Healthcare Provider:</strong><br>
                            Dr. ${record.doctor_name || 'Unknown'}
                        </div>
                        
                        <div class="section">
                            <div class="section-title">📋 Diagnosis</div>
                            <div class="section-content">${record.diagnosis || 'No diagnosis recorded'}</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-title">🏥 Treatment</div>
                            <div class="section-content">${record.treatment || 'No treatment recorded'}</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-title">💊 Prescription</div>
                            <div class="section-content">${record.prescription || 'No prescription'}</div>
                        </div>
                        
                        <div class="section">
                            <div class="section-title">📝 Notes</div>
                            <div class="section-content">${record.notes || 'No additional notes'}</div>
                        </div>
                        
                        ${record.next_visit_date ? `
                        <div class="section">
                            <div class="section-title">📅 Next Appointment</div>
                            <div class="section-content">${new Date(record.next_visit_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</div>
                        </div>
                        ` : ''}
                        
                        <footer>
                            <p>This is an official health record from the Community Health Tracker System.</p>
                            <p>Printed on: ${new Date().toLocaleString()}</p>
                        </footer>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(content);
            printWindow.document.close();
            
            setTimeout(() => {
                printWindow.print();
            }, 250);
        }

        // Close modal when clicking outside
        document.getElementById('recordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>