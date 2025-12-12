<?php
require_once __DIR__ . '/../includes/auth.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patientId <= 0) {
    header('HTTP/1.0 400 Bad Request');
    exit();
}

try {
    // Get patient basic information with user details
    $stmt = $pdo->prepare("SELECT 
        p.*, 
        u.id as user_id,
        u.full_name as user_full_name,
        u.email as user_email, 
        u.age as user_age,
        u.gender as user_gender,
        u.civil_status as user_civil_status,
        u.occupation as user_occupation,
        u.address as user_address,
        u.sitio as user_sitio,
        u.contact as user_contact,
        u.unique_number,
        u.date_of_birth as user_date_of_birth,
        COALESCE(p.full_name, u.full_name) as display_full_name,
        COALESCE(p.age, u.age) as display_age,
        COALESCE(p.date_of_birth, u.date_of_birth) as display_date_of_birth,
        COALESCE(p.gender, u.gender) as display_gender,
        COALESCE(p.civil_status, u.civil_status) as display_civil_status,
        COALESCE(p.occupation, u.occupation) as display_occupation,
        COALESCE(p.address, u.address) as display_address,
        COALESCE(p.sitio, u.sitio) as display_sitio,
        COALESCE(p.contact, u.contact) as display_contact
    FROM sitio1_patients p 
    LEFT JOIN sitio1_users u ON p.user_id = u.id
    WHERE p.id = ? AND p.added_by = ?");
    
    $stmt->execute([$patientId, $_SESSION['user']['id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('HTTP/1.0 404 Not Found');
        exit();
    }
    
    // Get health information - FIXED with proper error handling
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initialize as empty array if no health info exists
    if (!$healthInfo) {
        $healthInfo = [];
    }
    
    // Get visit history
    $stmt = $pdo->prepare("SELECT * FROM patient_visits WHERE patient_id = ? ORDER BY visit_date DESC");
    $stmt->execute([$patientId]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit();
}

// Format date of birth for display
$dateOfBirthDisplay = '';
if (!empty($patient['display_date_of_birth'])) {
    $dateOfBirthDisplay = date('F j, Y', strtotime($patient['display_date_of_birth']));
}

// Generate HTML for printing
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Health Record - <?= htmlspecialchars($patient['display_full_name']) ?> - Barangay Luz Health Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* Bond Paper Style */
        @page {
            size: letter;
            margin: 0.5in;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body { 
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0.5in;
            color: #000;
            background: #ffffff;
            line-height: 1.3;
            font-size: 12pt;
        }
        
        .document-container {
            max-width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            background: white;
            position: relative;
        }
        
        /* Official Government Header */
        .government-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000;
        }
        
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .logo-left, .logo-right {
            width: 120px;
            text-align: center;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            border: 2px solid #000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            overflow: hidden;
            background: white;
            position: relative;
        }
        
        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }
        
        .logo-circle.fallback::before {
            content: attr(data-fallback);
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            padding: 5px;
            line-height: 1.2;
        }
        
        .header-center {
            flex: 1;
            text-align: center;
            padding: 0 15px;
        }
        
        .header-center h1 {
            font-size: 16pt;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
        }
        
        .header-center h2 {
            font-size: 14pt;
            font-weight: 600;
            margin: 2px 0;
        }
        
        .header-center p {
            font-size: 10pt;
            margin: 1px 0;
            font-weight: 400;
        }
        
        .document-title {
            text-align: center;
            margin: 25px 0;
            padding: 10px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .document-title h1 {
            font-size: 18pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Information Sections */
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background: #f0f0f0;
            padding: 8px 12px;
            font-weight: 600;
            border: 1px solid #000;
            margin-bottom: 10px;
            font-size: 13pt;
        }
        
        .info-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15px; 
            font-size: 11pt;
        }
        
        .info-table th, .info-table td { 
            border: 1px solid #000; 
            padding: 8px 10px; 
            text-align: left; 
            vertical-align: top;
        }
        
        .info-table th { 
            background: #f8f8f8;
            font-weight: 600;
            width: 20%;
        }
        
        .info-table td {
            width: 30%;
        }
        
        .visits-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 10pt;
        }
        
        .visits-table th, .visits-table td { 
            border: 1px solid #000; 
            padding: 6px 8px; 
            text-align: left; 
        }
        
        .visits-table th { 
            background: #f8f8f8;
            font-weight: 600;
        }
        
        /* Signature Section */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 40px 0 8px;
            padding-bottom: 4px;
        }
        
        .signature-label {
            font-weight: 600;
            margin-top: 5px;
            font-size: 11pt;
        }
        
        .signature-details {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }
        
        /* Footer */
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            font-size: 9pt; 
            color: #666;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        
        /* Control Buttons */
        .control-buttons {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        
        .control-btn {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }
        
        .control-btn:hover {
            background: #34495e;
        }
        
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }
        
        .zoom-btn {
            background: #3498db;
            color: white;
            border: none;
            border-radius: 3px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .zoom-level {
            font-size: 11px;
            font-weight: 600;
            min-width: 40px;
            text-align: center;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            font-size: 16px;
            flex-direction: column;
            gap: 15px;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { 
                margin: 0; 
                padding: 0.5in;
                background: white !important;
            }
            .control-buttons { display: none !important; }
            .document-container {
                box-shadow: none;
                max-width: 100%;
                min-height: auto;
            }
            .logo-circle img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        /* Data styling */
        .data-value {
            font-weight: 400;
        }
        
        .empty-data {
            color: #999;
            font-style: italic;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .header-row {
                flex-direction: column;
                gap: 15px;
            }
            .logo-left, .logo-right {
                width: 100%;
            }
            .control-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay no-print" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div id="loadingText">Generating Document...</div>
    </div>
    
    <!-- Control Buttons -->
    <div class="control-buttons no-print">
        <button class="control-btn" onclick="handlePrint()">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="control-btn" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Excel
        </button>
        <button class="control-btn" onclick="exportToPDF()">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button class="control-btn" onclick="window.close()">
            <i class="fas fa-times"></i> Close
        </button>
        
        <div class="zoom-controls">
            <button class="zoom-btn" id="zoomOut" title="Zoom Out">
                <i class="fas fa-search-minus"></i>
            </button>
            <div class="zoom-level" id="zoomLevel">100%</div>
            <button class="zoom-btn" id="zoomIn" title="Zoom In">
                <i class="fas fa-search-plus"></i>
            </button>
        </div>
    </div>
    
    <div class="document-container" id="documentContent">
        <!-- Official Government Header -->
        <div class="government-header">
            <div class="header-row">
                <div class="logo-left">
                    <div class="logo-circle" id="dohLogo" data-fallback="DOH SEAL">
                        <img src="../asssets/images/DOH.webp" alt="Department of Health Seal" 
                             onerror="this.style.display='none'; document.getElementById('dohLogo').classList.add('fallback')">
                    </div>
                </div>
                
                <div class="header-center">
                    <h1>BARANGAY LUZ</h1>
                    <h2>CEBU CITY</h2>
                    <p>OFFICE OF THE BARANGAY CAPTAIN</p>
                    <p>BARANGAY HEALTH CENTER</p>
                    <p>Telephone: (032) 123-4567 • Email: healthcenter@barangayluzcebu.gov.ph</p>
                </div>
                
                <div class="logo-right">
                    <div class="logo-circle" id="barangayLogo" data-fallback="BARANGAY SEAL">
                        <img src="../asssets/images/Luz.jpg" alt="Barangay Luz Seal" 
                             onerror="this.style.display='none'; document.getElementById('barangayLogo').classList.add('fallback')">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Document Title -->
        <div class="document-title">
            <h1>PATIENT HEALTH RECORD</h1>
        </div>
        
        <!-- Document Information -->
        <div style="text-align: center; margin-bottom: 20px; font-size: 11pt;">
            <p><strong>Patient ID:</strong> <?= $patientId ?> | <strong>Date Generated:</strong> <?= date('F j, Y') ?> | <strong>Time:</strong> <?= date('g:i A') ?></p>
            <?php if (!empty($patient['unique_number'])): ?>
            <p><strong>Unique Number:</strong> <?= htmlspecialchars($patient['unique_number']) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Personal Information Section -->
        <div class="section">
            <div class="section-title">I. PERSONAL INFORMATION</div>
            <table class="info-table">
                <tr>
                    <th>Full Name</th>
                    <td class="data-value"><?= htmlspecialchars($patient['display_full_name']) ?></td>
                    <th>Date of Birth</th>
                    <td class="data-value"><?= !empty($dateOfBirthDisplay) ? $dateOfBirthDisplay : '<span class="empty-data">Not specified</span>' ?></td>
                </tr>
                <tr>
                    <th>Age</th>
                    <td class="data-value"><?= $patient['display_age'] ?? '<span class="empty-data">Not specified</span>' ?></td>
                    <th>Gender</th>
                    <td class="data-value"><?= $patient['display_gender'] ? htmlspecialchars($patient['display_gender']) : '<span class="empty-data">Not specified</span>' ?></td>
                </tr>
                <tr>
                    <th>Civil Status</th>
                    <td class="data-value"><?= $patient['display_civil_status'] ? htmlspecialchars($patient['display_civil_status']) : '<span class="empty-data">Not specified</span>' ?></td>
                    <th>Occupation</th>
                    <td class="data-value"><?= $patient['display_occupation'] ? htmlspecialchars($patient['display_occupation']) : '<span class="empty-data">Not specified</span>' ?></td>
                </tr>
                <tr>
                    <th>Contact Number</th>
                    <td class="data-value"><?= $patient['display_contact'] ? htmlspecialchars($patient['display_contact']) : '<span class="empty-data">Not specified</span>' ?></td>
                    <th>Sitio/Purok</th>
                    <td class="data-value">
                        <?php
                        if (!empty($patient['display_sitio'])) {
                            echo htmlspecialchars($patient['display_sitio']);
                        } else {
                            echo '<span class="empty-data">Not specified</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Complete Address</th>
                    <td colspan="3" class="data-value"><?= $patient['display_address'] ? htmlspecialchars($patient['display_address']) : '<span class="empty-data">Not specified</span>' ?></td>
                </tr>
                <tr>
                    <th>Last Check-up</th>
                    <td class="data-value"><?= $patient['last_checkup'] ? date('F j, Y', strtotime($patient['last_checkup'])) : '<span class="empty-data">No record</span>' ?></td>
                    <th>Patient Type</th>
                    <td class="data-value">
                        <?php if (!empty($patient['unique_number'])): ?>
                        <strong>REGISTERED BARANGAY RESIDENT</strong>
                        <?php else: ?>
                        WALK-IN PATIENT
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!empty($patient['user_email'])): ?>
                <tr>
                    <th>Email Address</th>
                    <td colspan="3" class="data-value"><?= htmlspecialchars($patient['user_email']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Health Information Section -->
        <div class="section">
            <div class="section-title">II. HEALTH INFORMATION</div>
            <table class="info-table">
                <tr>
                    <th>Height</th>
                    <td class="data-value"><?= isset($healthInfo['height']) && $healthInfo['height'] !== '' ? $healthInfo['height'] . ' cm' : '<span class="empty-data">Not recorded</span>' ?></td>
                    <th>Weight</th>
                    <td class="data-value"><?= isset($healthInfo['weight']) && $healthInfo['weight'] !== '' ? $healthInfo['weight'] . ' kg' : '<span class="empty-data">Not recorded</span>' ?></td>
                </tr>
                <tr>
                    <th>Blood Type</th>
                    <td class="data-value"><?= isset($healthInfo['blood_type']) && !empty($healthInfo['blood_type']) ? htmlspecialchars($healthInfo['blood_type']) : '<span class="empty-data">Not recorded</span>' ?></td>
                    <th>Temperature</th>
                    <td class="data-value"><?= isset($healthInfo['temperature']) && $healthInfo['temperature'] !== '' ? $healthInfo['temperature'] . ' °C' : '<span class="empty-data">Not recorded</span>' ?></td>
                </tr>
                <tr>
                    <th>Blood Pressure</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['blood_pressure']) && !empty($healthInfo['blood_pressure']) ? htmlspecialchars($healthInfo['blood_pressure']) : '<span class="empty-data">Not recorded</span>' ?></td>
                </tr>
                <tr>
                    <th>Allergies</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['allergies']) && !empty($healthInfo['allergies']) ? htmlspecialchars($healthInfo['allergies']) : '<span class="empty-data">None reported</span>' ?></td>
                </tr>
                <tr>
                    <th>Current Medications</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['current_medications']) && !empty($healthInfo['current_medications']) ? htmlspecialchars($healthInfo['current_medications']) : '<span class="empty-data">None reported</span>' ?></td>
                </tr>
                <tr>
                    <th>Medical History</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['medical_history']) && !empty($healthInfo['medical_history']) ? htmlspecialchars($healthInfo['medical_history']) : '<span class="empty-data">None reported</span>' ?></td>
                </tr>
                <tr>
                    <th>Family Medical History</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['family_history']) && !empty($healthInfo['family_history']) ? htmlspecialchars($healthInfo['family_history']) : '<span class="empty-data">None reported</span>' ?></td>
                </tr>
                <tr>
                    <th>Immunization Record</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['immunization_record']) && !empty($healthInfo['immunization_record']) ? htmlspecialchars($healthInfo['immunization_record']) : '<span class="empty-data">Not available</span>' ?></td>
                </tr>
                <tr>
                    <th>Chronic Conditions</th>
                    <td colspan="3" class="data-value"><?= isset($healthInfo['chronic_conditions']) && !empty($healthInfo['chronic_conditions']) ? htmlspecialchars($healthInfo['chronic_conditions']) : '<span class="empty-data">None reported</span>' ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Visit History Section -->
        <?php if (!empty($visits)): ?>
        <div class="section">
            <div class="section-title">III. MEDICAL VISIT HISTORY (<?= count($visits) ?> Records)</div>
            <table class="visits-table">
                <thead>
                    <tr>
                        <th style="width: 15%">Date</th>
                        <th style="width: 12%">Time</th>
                        <th style="width: 15%">Visit Type</th>
                        <th style="width: 20%">Symptoms/Complaints</th>
                        <th style="width: 18%">Diagnosis</th>
                        <th style="width: 20%">Treatment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($visit['visit_date'])) ?></td>
                        <td><?= date('g:i A', strtotime($visit['visit_date'])) ?></td>
                        <td><strong><?= strtoupper($visit['visit_type']) ?></strong></td>
                        <td><?= $visit['symptoms'] ? htmlspecialchars($visit['symptoms']) : '<span class="empty-data">None</span>' ?></td>
                        <td><?= $visit['diagnosis'] ? htmlspecialchars($visit['diagnosis']) : '<span class="empty-data">None</span>' ?></td>
                        <td><?= $visit['treatment'] ? htmlspecialchars($visit['treatment']) : '<span class="empty-data">None</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">ATTENDING PHYSICIAN/MEDICAL STAFF</div>
                <div class="signature-details">
                    Dr. <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Medical Officer') ?><br>
                    License No: PRC-<?= str_pad($_SESSION['user']['id'] ?? '0000', 6, '0', STR_PAD_LEFT) ?>
                </div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">BARANGAY HEALTH WORKER</div>
                <div class="signature-details">
                    Barangay Luz Health Center<br>
                    BHW ID: BHW-LUZ-<?= date('Y') ?>-<?= str_pad($patientId, 4, '0', STR_PAD_LEFT) ?>
                </div>
            </div>
        </div>
        
        <!-- Official Footer -->
        <div class="footer">
            <p><strong>BARANGAY LUZ HEALTH CENTER</strong> | Barangay Luz, Cebu City, Philippines 6000</p>
            <p>This document is generated by the Barangay Health Information System and is considered an official medical record.</p>
            <p><em>Confidentiality Notice: This document contains privileged and confidential information. Unauthorized disclosure is prohibited under R.A. 10173.</em></p>
            <p style="margin-top: 8px;">Document generated by: <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'System') ?> | Page 1 of 1</p>
        </div>
    </div>

    <script>
        // Zoom functionality
        document.addEventListener('DOMContentLoaded', function() {
            const documentContent = document.getElementById('documentContent');
            const zoomLevel = document.getElementById('zoomLevel');
            const zoomInBtn = document.getElementById('zoomIn');
            const zoomOutBtn = document.getElementById('zoomOut');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const loadingText = document.getElementById('loadingText');
            
            let currentZoom = 100;
            
            function updateZoom() {
                documentContent.style.transform = `scale(${currentZoom / 100})`;
                documentContent.style.transformOrigin = 'top center';
                zoomLevel.textContent = `${currentZoom}%`;
                
                // Disable buttons at limits
                zoomInBtn.disabled = currentZoom >= 150;
                zoomOutBtn.disabled = currentZoom <= 50;
            }
            
            zoomInBtn.addEventListener('click', function() {
                if (currentZoom < 150) {
                    currentZoom += 10;
                    updateZoom();
                }
            });
            
            zoomOutBtn.addEventListener('click', function() {
                if (currentZoom > 50) {
                    currentZoom -= 10;
                    updateZoom();
                }
            });
            
            // Initialize
            updateZoom();
            
            // Global functions for export
            window.handlePrint = function() {
                window.print();
            };
            
            window.exportToExcel = function() {
                showLoading('Generating Excel file...');
                
                try {
                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    
                    // Patient Information Sheet
                    const patientData = [
                        ['BARANGAY LUZ HEALTH CENTER - PATIENT HEALTH RECORD'],
                        ['Official Document - Generated on: <?= date('F j, Y, g:i a') ?>'],
                        [''],
                        ['PERSONAL INFORMATION'],
                        ['Full Name:', '<?= addslashes($patient['display_full_name']) ?>'],
                        ['Date of Birth:', '<?= !empty($dateOfBirthDisplay) ? $dateOfBirthDisplay : 'Not specified' ?>'],
                        ['Age:', '<?= $patient['display_age'] ?? 'Not specified' ?>'],
                        ['Gender:', '<?= addslashes($patient['display_gender'] ?? 'Not specified') ?>'],
                        ['Civil Status:', '<?= addslashes($patient['display_civil_status'] ?? 'Not specified') ?>'],
                        ['Occupation:', '<?= addslashes($patient['display_occupation'] ?? 'Not specified') ?>'],
                        ['Contact Number:', '<?= addslashes($patient['display_contact'] ?? 'Not specified') ?>'],
                        ['Address:', '<?= addslashes($patient['display_address'] ?? 'Not specified') ?>'],
                        ['Sitio/Purok:', '<?= addslashes($patient['display_sitio'] ?? 'Not specified') ?>'],
                        ['Last Check-up:', '<?= $patient['last_checkup'] ? date('F j, Y', strtotime($patient['last_checkup'])) : 'No record' ?>'],
                        ['Patient Type:', '<?= !empty($patient['unique_number']) ? 'Registered Barangay Resident' : 'Walk-in Patient' ?>'],
                        [''],
                        ['HEALTH INFORMATION'],
                        ['Height:', '<?= isset($healthInfo['height']) ? $healthInfo['height'] . ' cm' : 'Not recorded' ?>'],
                        ['Weight:', '<?= isset($healthInfo['weight']) ? $healthInfo['weight'] . ' kg' : 'Not recorded' ?>'],
                        ['Blood Type:', '<?= isset($healthInfo['blood_type']) ? addslashes($healthInfo['blood_type']) : 'Not recorded' ?>'],
                        ['Blood Pressure:', '<?= isset($healthInfo['blood_pressure']) ? addslashes($healthInfo['blood_pressure']) : 'Not recorded' ?>'],
                        ['Temperature:', '<?= isset($healthInfo['temperature']) ? $healthInfo['temperature'] . ' °C' : 'Not recorded' ?>'],
                        ['Allergies:', '<?= isset($healthInfo['allergies']) ? addslashes($healthInfo['allergies']) : 'None reported' ?>'],
                        ['Current Medications:', '<?= isset($healthInfo['current_medications']) ? addslashes($healthInfo['current_medications']) : 'None reported' ?>'],
                        ['Medical History:', '<?= isset($healthInfo['medical_history']) ? addslashes($healthInfo['medical_history']) : 'None reported' ?>'],
                        ['Family History:', '<?= isset($healthInfo['family_history']) ? addslashes($healthInfo['family_history']) : 'None reported' ?>'],
                        ['Immunization Record:', '<?= isset($healthInfo['immunization_record']) ? addslashes($healthInfo['immunization_record']) : 'Not available' ?>'],
                        ['Chronic Conditions:', '<?= isset($healthInfo['chronic_conditions']) ? addslashes($healthInfo['chronic_conditions']) : 'None reported' ?>'],
                        [''],
                        ['DOCUMENT INFORMATION'],
                        ['Generated by:', '<?= addslashes($_SESSION['user']['full_name'] ?? 'System') ?>'],
                        ['Patient ID:', '<?= $patientId ?>'],
                        ['Barangay Luz Health Center', 'Cebu City, Philippines']
                    ];
                    
                    const patientWs = XLSX.utils.aoa_to_sheet(patientData);
                    XLSX.utils.book_append_sheet(wb, patientWs, 'Patient Record');
                    
                    // Visit History Sheet
                    if (<?= !empty($visits) ? 'true' : 'false' ?>) {
                        const visitsData = [
                            ['VISIT HISTORY - BARANGAY LUZ HEALTH CENTER'],
                            ['Patient: <?= addslashes($patient['display_full_name']) ?>'],
                            [''],
                            ['Visit Date', 'Visit Time', 'Visit Type', 'Symptoms', 'Diagnosis', 'Treatment', 'Prescription', 'Notes']
                        ];
                        
                        <?php foreach ($visits as $visit): ?>
                        visitsData.push([
                            '<?= date('Y-m-d', strtotime($visit['visit_date'])) ?>',
                            '<?= date('H:i', strtotime($visit['visit_date'])) ?>',
                            '<?= addslashes(ucfirst($visit['visit_type'])) ?>',
                            '<?= addslashes($visit['symptoms'] ?? '') ?>',
                            '<?= addslashes($visit['diagnosis'] ?? '') ?>',
                            '<?= addslashes($visit['treatment'] ?? '') ?>',
                            '<?= addslashes($visit['prescription'] ?? '') ?>',
                            '<?= addslashes($visit['notes'] ?? '') ?>'
                        ]);
                        <?php endforeach; ?>
                        
                        const visitsWs = XLSX.utils.aoa_to_sheet(visitsData);
                        XLSX.utils.book_append_sheet(wb, visitsWs, 'Visit History');
                    }
                    
                    // Generate and download
                    const fileName = `Barangay_Luz_Health_Record_<?= addslashes($patient['display_full_name']) ?>_<?= date('Y-m-d') ?>.xlsx`;
                    XLSX.writeFile(wb, fileName);
                    
                } catch (error) {
                    alert('Error generating Excel file: ' + error.message);
                    console.error('Excel export error:', error);
                } finally {
                    hideLoading();
                }
            };
            
            window.exportToPDF = function() {
                showLoading('Generating PDF document...');
                
                // Use html2canvas to capture the document
                html2canvas(document.getElementById('documentContent'), {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                }).then(canvas => {
                    const imgData = canvas.toDataURL('image/png');
                    const { jsPDF } = window.jspdf;
                    const pdf = new jsPDF('p', 'mm', 'letter');
                    
                    const imgWidth = 216; // Letter width in mm
                    const pageHeight = 279; // Letter height in mm
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    let heightLeft = imgHeight;
                    let position = 0;
                    
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                    
                    while (heightLeft >= 0) {
                        position = heightLeft - imgHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }
                    
                    pdf.save(`Barangay_Luz_Health_Record_<?= addslashes($patient['display_full_name']) ?>_<?= date('Y-m-d') ?>.pdf`);
                    hideLoading();
                }).catch(error => {
                    alert('Error generating PDF: ' + error.message);
                    console.error('PDF export error:', error);
                    hideLoading();
                });
            };
            
            function showLoading(text) {
                if (loadingText) loadingText.textContent = text;
                if (loadingOverlay) loadingOverlay.style.display = 'flex';
            }
            
            function hideLoading() {
                if (loadingOverlay) loadingOverlay.style.display = 'none';
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'p':
                            e.preventDefault();
                            handlePrint();
                            break;
                        case 'e':
                            e.preventDefault();
                            exportToExcel();
                            break;
                        case 'd':
                            e.preventDefault();
                            exportToPDF();
                            break;
                    }
                }
            });

            // Check if images loaded successfully
            window.addEventListener('load', function() {
                const dohImg = document.querySelector('.logo-left img');
                const barangayImg = document.querySelector('.logo-right img');
                
                // Check if images failed to load
                if (dohImg && !dohImg.complete) {
                    dohImg.onerror = function() {
                        this.style.display = 'none';
                        document.getElementById('dohLogo').classList.add('fallback');
                    };
                }
                
                if (barangayImg && !barangayImg.complete) {
                    barangayImg.onerror = function() {
                        this.style.display = 'none';
                        document.getElementById('barangayLogo').classList.add('fallback');
                    };
                }
            });
        });
    </script>
</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
?>