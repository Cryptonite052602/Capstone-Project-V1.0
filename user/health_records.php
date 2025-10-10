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
        .health-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        .health-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .nav-pill {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .nav-pill.active {
            background-color: #eff6ff;
            color: #3b82f6;
            border-color: #3b82f6;
        }
        .nav-pill:hover:not(.active) {
            background-color: #f9fafb;
        }
        .info-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .info-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .info-value {
            color: #1f2937;
            font-size: 1rem;
            font-weight: 500;
        }
        .empty-value {
            color: #9ca3af;
            font-style: italic;
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        .section-title {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
        }
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
            <div class="flex items-center bg-white p-4 rounded-xl shadow-sm border border-gray-200">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                    <i class="fas fa-user text-blue-500 text-xl"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($userFullName) ?></p>
                    <p class="text-sm text-gray-500">Patient ID: <?= !empty($patientInfo['id']) ? htmlspecialchars($patientInfo['id']) : 'N/A' ?></p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                <i class="fas fa-check-circle mr-3"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-xl shadow-sm p-3 mb-8 flex flex-wrap border border-gray-200">
            <button class="nav-pill active flex items-center mr-3 mb-2" data-tab="records">
                <i class="fas fa-file-medical mr-2"></i> Health Records
                <span class="bg-blue-100 text-blue-800 text-xs font-medium ml-2 px-2.5 py-1 rounded-full">
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
                <div class="bg-white p-12 rounded-xl shadow-sm text-center border border-gray-200">
                    <i class="fas fa-file-medical-alt text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Health Records Found</h3>
                    <p class="text-gray-500 max-w-md mx-auto">Your medical visit records will appear here once you have appointments with our healthcare providers.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($healthRecords as $record): ?>
                        <div class="health-card bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?= ucfirst($record['visit_type'] ?? 'General') ?> Visit</h3>
                                    <p class="text-gray-500 text-sm flex items-center mt-1">
                                        <i class="far fa-calendar-alt mr-2"></i> 
                                        <?= date('M d, Y', strtotime($record['visit_date'] ?? 'now')) ?>
                                    </p>
                                </div>
                                <span class="status-badge bg-green-100 text-green-800">
                                    Completed
                                </span>
                            </div>
                            
                            <div class="flex items-center text-gray-700 mb-4">
                                <i class="fas fa-user-md text-blue-500 mr-2"></i>
                                <span class="font-medium">Dr. <?= htmlspecialchars($record['doctor_name'] ?? 'Unknown') ?></span>
                            </div>
                            
                            <?php if (!empty($record['diagnosis'])): ?>
                                <div class="mb-4">
                                    <p class="info-label">Diagnosis</p>
                                    <p class="info-value text-sm line-clamp-2"><?= htmlspecialchars($record['diagnosis']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="pt-4 border-t border-gray-100">
                                <button onclick="showRecordDetails(<?= htmlspecialchars(json_encode($record)) ?>)" 
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg flex items-center justify-center transition-colors font-medium">
                                    <i class="fas fa-eye mr-2"></i> View Full Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Personal Profile Tab -->
        <div id="profile" class="tab-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Personal Information -->
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-user text-blue-500 mr-3"></i> Personal Information
                    </h3>
                    
                    <div class="space-y-5">
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
                    
                    <div class="space-y-5">
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Vital Statistics -->
                <div class="info-card">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 section-title flex items-center">
                        <i class="fas fa-chart-line text-blue-500 mr-3"></i> Vital Statistics
                    </h3>
                    
                    <div class="space-y-5">
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
                    
                    <div class="space-y-5">
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

    <!-- Record Details Modal -->
    <div id="recordModal" class="fixed inset-0 modal-overlay overflow-y-auto h-full w-full hidden z-50 transition-opacity duration-300">
        <div class="relative top-10 mx-auto p-6 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-xl rounded-xl bg-white transform transition-transform duration-300">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                <h3 class="text-2xl font-bold text-gray-800">Health Record Details</h3>
                <button onclick="closeRecordModal()" class="text-gray-500 hover:text-gray-700 text-2xl bg-gray-100 hover:bg-gray-200 rounded-full w-8 h-8 flex items-center justify-center transition-colors">
                    &times;
                </button>
            </div>
            <div id="recordDetails" class="space-y-6 max-h-[70vh] overflow-y-auto pr-4 custom-scrollbar">
                <!-- Dynamic content will be inserted here -->
            </div>
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeRecordModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2.5 rounded-lg font-medium transition-colors">
                    Close
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

        function showRecordDetails(record) {
            const modal = document.getElementById('recordModal');
            const detailsDiv = document.getElementById('recordDetails');
            
            // Format the record data with proper fallbacks
            const patientName = record.patient_name || '<?= htmlspecialchars($userFullName) ?>';
            const doctorName = record.doctor_name || 'Unknown';
            const visitDate = record.visit_date ? new Date(record.visit_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                weekday: 'long'
            }) : 'Unknown date';
            const visitType = record.visit_type ? record.visit_type.charAt(0).toUpperCase() + record.visit_type.slice(1) : 'General';
            const diagnosis = record.diagnosis || 'No diagnosis recorded';
            const treatment = record.treatment || 'No treatment recorded';
            const prescription = record.prescription || 'No prescription';
            const notes = record.notes || 'No additional notes';
            const nextVisit = record.next_visit_date ? new Date(record.next_visit_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            }) : null;
            
            detailsDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-blue-800 mb-2">Patient Information</h4>
                        <p class="text-blue-700">${patientName}</p>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-green-800 mb-2">Attending Doctor</h4>
                        <p class="text-green-700">Dr. ${doctorName}</p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-2">Visit Date</h4>
                        <p class="text-gray-700">${visitDate}</p>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-purple-800 mb-2">Visit Type</h4>
                        <p class="text-purple-700">${visitType}</p>
                    </div>
                    
                    <div class="md:col-span-2 bg-white border border-gray-200 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-diagnosis text-red-500 mr-2"></i> Diagnosis
                        </h4>
                        <p class="text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded border">${diagnosis}</p>
                    </div>
                    
                    <div class="md:col-span-2 bg-white border border-gray-200 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-band-aid text-green-500 mr-2"></i> Treatment
                        </h4>
                        <p class="text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded border">${treatment}</p>
                    </div>
                    
                    <div class="md:col-span-2 bg-white border border-gray-200 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-pills text-yellow-500 mr-2"></i> Prescription
                        </h4>
                        <p class="text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded border">${prescription}</p>
                    </div>
                    
                    <div class="md:col-span-2 bg-white border border-gray-200 p-4 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fas fa-notes-medical text-blue-500 mr-2"></i> Additional Notes
                        </h4>
                        <p class="text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded border">${notes}</p>
                    </div>
                    
                    ${nextVisit ? `
                    <div class="md:col-span-2 bg-orange-50 border border-orange-200 p-4 rounded-lg">
                        <h4 class="font-semibold text-orange-800 mb-2 flex items-center">
                            <i class="fas fa-calendar-check text-orange-500 mr-2"></i> Next Appointment
                        </h4>
                        <p class="text-orange-700 font-medium">${nextVisit}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.style.opacity = '1';
            }, 10);
        }
        
        function closeRecordModal() {
            const modal = document.getElementById('recordModal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            if (event.target === modal) {
                closeRecordModal();
            }
        };

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeRecordModal();
            }
        });
    </script>
</body>
</html>