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
$userEmail = $_SESSION['user']['email'] ?? 'Not provided';
$userFullName = $_SESSION['user']['full_name'] ?? 'Not provided';
$userCreatedAt = $_SESSION['user']['created_at'] ?? date('Y-m-d');
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
        /* Formal, unique visual theme for My Health Profile */
        :root{
            --bg: #f7fafc;
            --card-bg: #ffffff;
            --muted: #6b7280;
            --accent: #0f172a; /* dark navy */
            --accent-soft: #e6eef8;
            --border: #e6e9ee;
            --success: #0f766e;
        }

        html,body{height:100%;}
        body{
            background: linear-gradient(180deg, #f8fafc 0%, #f3f4f6 100%);
            color: #111827;
            font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }

        /* Header & Profile summary */
        .profile-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}
        .profile-card{background:var(--card-bg);border:1px solid var(--border);padding:1rem 1.25rem;border-radius:20px;display:flex;align-items:center;gap:1rem}
        .avatar{width:64px;height:64px;border-radius:16px;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;font-size:1.25rem;color:var(--accent)}
        .profile-meta .name{font-weight:700;font-size:1.05rem;color:var(--accent)}
        .profile-meta .sub{color:var(--muted);font-size:0.9rem}

        /* Tabs */
        .nav-pill{background:transparent;border:1px solid transparent;padding:0.75rem 1.25rem;border-radius:50px;color:var(--muted);display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;transition:all 0.3s ease}
        .nav-pill.active{background:linear-gradient(90deg,var(--accent-soft),#f1f6fb);border-color:var(--border);color:var(--accent)}

        .tab-content{display:none}
        .tab-content.active{display:block}

        /* Info cards */
        .info-card{background:var(--card-bg);border:1px solid var(--border);padding:1.5rem;border-radius:20px;margin-bottom:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
        .info-label{color:var(--muted);font-size:0.78rem;font-weight:700;letter-spacing:0.6px;margin-bottom:0.35rem}
        .info-value{color:var(--accent);font-weight:600}
        .empty-value{color:#9ca3af;font-style:italic}

        /* Timeline-style health records for a formal look */
        .health-record-item{display:flex;gap:1rem;background:var(--card-bg);border:1px solid var(--border);border-left:6px solid #cfe3ff;padding:0;margin-bottom:1.5rem;border-radius:20px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
        .record-side{width:120px;background:#fbfdff;padding:1rem 0.75rem;display:flex;flex-direction:column;align-items:center;justify-content:center;border-right:1px solid var(--border);border-radius:20px 0 0 20px}
        .record-side .date{font-weight:700;color:var(--accent);font-size:0.95rem;text-align:center}
        .record-body{padding:1.5rem 1.5rem;flex:1}

        .record-section{background:transparent;border-radius:16px;padding:1rem;border:1px dashed transparent}
        .record-section h4{font-weight:700;color:var(--accent);margin-bottom:0.35rem;font-size:0.95rem}
        .record-section p{color:#374151;white-space:pre-wrap}

        .action-btn{padding:0.75rem 1.25rem;border-radius:50px;font-weight:600;font-size:0.9rem;transition:all 0.3s ease}
        .action-btn i{margin-right:0.45rem}

        /* Summary cards */
        .summary-tile{padding:1.5rem;border-radius:20px;background:linear-gradient(180deg,#fafbff,#f6f8fb);border:1px solid var(--border)}

        /* Print/Download/Share consistent colors */
        .btn-print{background:#e6f0ff;color:#0b4da0}
        .btn-download{background:#e9f7f2;color:#0a6b48}
        .btn-share{background:#f5edff;color:#6b21a8}

        /* Status badges */
        .status-badge{padding:0.5rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:600}

        /* Section titles */
        .section-title{position:relative;padding-left:1rem}
        .section-title:before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:4px;height:20px;background:#3b82f6;border-radius:4px}

        /* Small responsive tweaks */
        @media (max-width: 768px){
            .record-side{display:none}
            .health-record-item{border-left-width:4px;border-radius:16px}
        }

        /* Hover effects */
        .nav-pill:hover{background:var(--accent-soft);color:var(--accent)}
        .action-btn:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.1)}
        .info-card:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08)}
        .health-record-item:hover{box-shadow:0 4px 12px rgba(0,0,0,0.08)}
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-6 py-8 max-w-7xl">
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
                    <p class="text-sm text-gray-500">Patient ID: <?= !empty($patientInfo['id']) ? htmlspecialchars($patientInfo['id']) : 'N/A' ?></p>
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
            <button class="nav-pill active flex items-center mr-3 mb-2" data-tab="records">
                <i class="fas fa-file-medical mr-2"></i> Health Records
                <span class="bg-blue-100 text-blue-800 text-xs font-medium ml-2 px-3 py-1 rounded-full">
                    <?= count($healthRecords) ?>
                </span>
            </button>
            <button class="nav-pill flex items-center mr-3 mb-2" data-tab="profile">
                <i class="fas fa-user-circle mr-2"></i> Personal Info
            </button>
            <button class="nav-pill flex items-center mr-3 mb-2" data-tab="medical">
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
                        <div class="health-record-item bg-white rounded-2xl overflow-hidden shadow-sm transition-all duration-300">
                            <div class="record-side">
                                <div class="date"><?= date('M d', strtotime($record['visit_date'] ?? 'now')) ?></div>
                                <div class="text-xs mt-2" style="color:var(--muted)"><?= date('Y', strtotime($record['visit_date'] ?? 'now')) ?></div>
                                <div class="text-xs mt-3 px-3 py-1 bg-green-100 text-green-800 rounded-full" style="font-weight:700">Completed</div>
                            </div>

                            <!-- Record Content -->
                            <div class="record-body px-6 py-6 space-y-6">
                                <!-- Doctor Information -->
                                <div class="flex items-start gap-4 pb-4 border-b border-gray-100">
                                    <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-user-md text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="info-label">Attending Healthcare Provider</p>
                                        <p class="info-value">Dr. <?= htmlspecialchars($record['doctor_name'] ?? 'Unknown') ?></p>
                                    </div>
                                </div>

                                <!-- Diagnosis -->
                                <div class="bg-red-50 border border-red-100 rounded-2xl p-5">
                                    <h4 class="font-semibold text-red-800 mb-3 flex items-center">
                                        <i class="fas fa-stethoscope text-red-600 mr-3"></i> Diagnosis
                                    </h4>
                                    <p class="text-gray-700 whitespace-pre-wrap">
                                        <?= htmlspecialchars($record['diagnosis'] ?? 'No diagnosis recorded') ?>
                                    </p>
                                </div>

                                <!-- Treatment -->
                                <div class="bg-green-50 border border-green-100 rounded-2xl p-5">
                                    <h4 class="font-semibold text-green-800 mb-3 flex items-center">
                                        <i class="fas fa-bandage text-green-600 mr-3"></i> Treatment
                                    </h4>
                                    <p class="text-gray-700 whitespace-pre-wrap">
                                        <?= htmlspecialchars($record['treatment'] ?? 'No treatment recorded') ?>
                                    </p>
                                </div>

                                <!-- Prescription -->
                                <div class="bg-yellow-50 border border-yellow-100 rounded-2xl p-5">
                                    <h4 class="font-semibold text-yellow-800 mb-3 flex items-center">
                                        <i class="fas fa-pills text-yellow-600 mr-3"></i> Prescription
                                    </h4>
                                    <p class="text-gray-700 whitespace-pre-wrap">
                                        <?= htmlspecialchars($record['prescription'] ?? 'No prescription') ?>
                                    </p>
                                </div>

                                <!-- Notes -->
                                <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5">
                                    <h4 class="font-semibold text-blue-800 mb-3 flex items-center">
                                        <i class="fas fa-clipboard text-blue-600 mr-3"></i> Notes
                                    </h4>
                                    <p class="text-gray-700 whitespace-pre-wrap">
                                        <?= htmlspecialchars($record['notes'] ?? 'No additional notes') ?>
                                    </p>
                                </div>

                                <!-- Next Appointment -->
                                <?php if (!empty($record['next_visit_date'])): ?>
                                    <div class="bg-purple-50 border border-purple-100 rounded-2xl p-5">
                                        <h4 class="font-semibold text-purple-800 mb-3 flex items-center">
                                            <i class="fas fa-calendar-check text-purple-600 mr-3"></i> Next Appointment
                                        </h4>
                                        <p class="text-gray-700 font-medium">
                                            <?= date('l, F d, Y', strtotime($record['next_visit_date'])) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div class="pt-4 flex gap-3 flex-wrap">
                                    <button onclick="printRecord(<?= htmlspecialchars(json_encode($record)) ?>)" 
                                            class="action-btn btn-print flex items-center gap-2">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button onclick="downloadRecord(<?= $record['id'] ?>)" 
                                            class="action-btn btn-download flex items-center gap-2">
                                        <i class="fas fa-download"></i> Download PDF
                                    </button>
                                    <button onclick="shareRecord(<?= $record['id'] ?>)" 
                                            class="action-btn btn-share flex items-center gap-2">
                                        <i class="fas fa-share-alt"></i> Share
                                    </button>
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
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-user text-blue-500 mr-3"></i> Personal Information
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="info-label">Full Name</p>
                                <p class="info-value"><?= htmlspecialchars($userFullName) ?></p>
                            </div>
                            <div>
                                <p class="info-label">Email</p>
                                <p class="info-value"><?= htmlspecialchars($userEmail) ?></p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="info-label">Age</p>
                                <p class="info-value"><?= !empty($patientInfo['age']) ? htmlspecialchars($patientInfo['age']) : '<span class="empty-value">Not provided</span>' ?></p>
                            </div>
                            <div>
                                <p class="info-label">Gender</p>
                                <p class="info-value"><?= !empty($patientInfo['gender']) ? htmlspecialchars($patientInfo['gender']) : '<span class="empty-value">Not provided</span>' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-address-card text-blue-500 mr-3"></i> Contact Information
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="info-label">Address</p>
                            <p class="info-value"><?= !empty($patientInfo['address']) ? htmlspecialchars($patientInfo['address']) : '<span class="empty-value">Not provided</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="info-label">Contact Number</p>
                            <p class="info-value"><?= !empty($patientInfo['contact']) ? htmlspecialchars($patientInfo['contact']) : '<span class="empty-value">Not provided</span>' ?></p>
                        </div>
                        
                        <?php if (!empty($patientInfo['last_checkup'])): ?>
                        <div>
                            <p class="info-label">Last Check-up</p>
                            <p class="info-value"><?= date('M d, Y', strtotime($patientInfo['last_checkup'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="info-card lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-3"></i> Account Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <p class="info-label">User ID</p>
                            <p class="info-value font-mono"><?= htmlspecialchars($userId) ?></p>
                        </div>
                        
                        <div>
                            <p class="info-label">Patient ID</p>
                            <p class="info-value"><?= !empty($patientInfo['id']) ? htmlspecialchars($patientInfo['id']) : '<span class="empty-value">Not assigned</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="info-label">Account Status</p>
                            <p class="info-value">
                                <span class="status-badge bg-green-100 text-green-800">
                                    Active
                                </span>
                            </p>
                        </div>
                        
                        <div>
                            <p class="info-label">Member Since</p>
                            <p class="info-value"><?= date('M d, Y', strtotime($userCreatedAt)) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Medical Information Tab -->
        <div id="medical" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Vital Statistics -->
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-chart-line text-blue-500 mr-3"></i> Vital Statistics
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="info-label">Blood Type</p>
                            <p class="info-value"><?= !empty($medicalInfo['blood_type']) ? htmlspecialchars($medicalInfo['blood_type']) : '<span class="empty-value">Not recorded</span>' ?></p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="info-label">Height</p>
                                <p class="info-value"><?= !empty($medicalInfo['height']) ? htmlspecialchars($medicalInfo['height']) . ' cm' : '<span class="empty-value">--</span>' ?></p>
                            </div>
                            <div>
                                <p class="info-label">Weight</p>
                                <p class="info-value"><?= !empty($medicalInfo['weight']) ? htmlspecialchars($medicalInfo['weight']) . ' kg' : '<span class="empty-value">--</span>' ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($medicalInfo['height']) && !empty($medicalInfo['weight'])): ?>
                        <div>
                            <p class="info-label">BMI</p>
                            <p class="info-value">
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
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-stethoscope text-blue-500 mr-3"></i> Medical Details
                    </h3>
                    
                    <div class="space-y-6">
                        <div>
                            <p class="info-label">Allergies</p>
                            <p class="info-value"><?= !empty($medicalInfo['allergies']) ? nl2br(htmlspecialchars($medicalInfo['allergies'])) : '<span class="empty-value">No allergies recorded</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="info-label">Current Medications</p>
                            <p class="info-value"><?= !empty($medicalInfo['current_medications']) ? nl2br(htmlspecialchars($medicalInfo['current_medications'])) : '<span class="empty-value">No medications recorded</span>' ?></p>
                        </div>
                        
                        <div>
                            <p class="info-label">Chronic Conditions</p>
                            <p class="info-value"><?= !empty($patientInfo['disease']) ? htmlspecialchars($patientInfo['disease']) : '<span class="empty-value">No chronic conditions recorded</span>' ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Medical History -->
                <div class="info-card lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-history text-blue-500 mr-3"></i> Medical History
                    </h3>
                    
                    <div>
                        <p class="info-label">Medical History</p>
                        <p class="info-value"><?= !empty($medicalInfo['medical_history']) ? nl2br(htmlspecialchars($medicalInfo['medical_history'])) : '<span class="empty-value">No medical history recorded</span>' ?></p>
                    </div>
                </div>
                
                <!-- Family History -->
                <div class="info-card lg:col-span-2">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-users text-blue-500 mr-3"></i> Family Medical History
                    </h3>
                    
                    <div>
                        <p class="info-label">Family History</p>
                        <p class="info-value"><?= !empty($medicalInfo['family_history']) ? nl2br(htmlspecialchars($medicalInfo['family_history'])) : '<span class="empty-value">No family medical history recorded</span>' ?></p>
                    </div>
                </div>
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

        // Download PDF Function
        function downloadRecord(recordId) {
            // This would integrate with a backend API to generate PDF
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `/community-health-tracker/api/export_patient.php?record_id=${recordId}`, true);
            xhr.responseType = 'blob';
            xhr.onload = function() {
                const blob = new Blob([xhr.response], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `health-record-${recordId}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            };
            xhr.send();
        }

        // Share Record Function
        function shareRecord(recordId) {
            if (navigator.share) {
                navigator.share({
                    title: 'My Health Record',
                    text: 'I would like to share my health record with you.',
                    url: window.location.href
                }).catch(err => {
                    showShareModal(recordId);
                });
            } else {
                showShareModal(recordId);
            }
        }

        function showShareModal(recordId) {
            const email = prompt('Enter email address to share this record with:');
            if (email) {
                // This would integrate with a backend API to send record via email
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/community-health-tracker/api/share_record.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        alert('Record shared successfully!');
                    } else {
                        alert('Error sharing record. Please try again.');
                    }
                };
                xhr.send('record_id=' + recordId + '&email=' + encodeURIComponent(email));
            }
        }
    </script>
</body>
</html>