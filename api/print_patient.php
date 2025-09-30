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
    // Get patient basic information
    $stmt = $pdo->prepare("SELECT p.*, u.unique_number, u.email as user_email 
                          FROM sitio1_patients p 
                          LEFT JOIN sitio1_users u ON p.user_id = u.id
                          WHERE p.id = ? AND p.added_by = ?");
    $stmt->execute([$patientId, $_SESSION['user']['id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        header('HTTP/1.0 404 Not Found');
        exit();
    }
    
    // Get health information
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $healthInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get visit history
    $stmt = $pdo->prepare("SELECT * FROM patient_visits WHERE patient_id = ? ORDER BY visit_date DESC");
    $stmt->execute([$patientId]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('HTTP/1.0 500 Internal Server Error');
    exit();
}

// Generate HTML for printing
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record - <?= htmlspecialchars($patient['full_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-gray: #f8f9fa;
            --border-color: #dee2e6;
            --text-color: #333;
            --header-bg: #f1f8ff;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0;
            padding: 20px;
            color: var(--text-color);
            background-color: #f5f5f5;
            line-height: 1.5;
        }
        
        .document-container {
            max-width: 8.5in; /* Bond paper width */
            min-height: 11in; /* Bond paper height */
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 0.5in;
            position: relative;
        }
        
        .barangay-header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }
        
        .barangay-header h2 {
            color: var(--secondary-color);
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .barangay-header p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #666;
        }
        
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            padding-bottom: 15px; 
        }
        
        .header h1 {
            color: var(--secondary-color);
            margin-bottom: 5px;
            font-size: 24px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        .section { 
            margin-bottom: 25px; 
            page-break-inside: avoid;
        }
        
        .section-title { 
            background-color: var(--header-bg); 
            padding: 8px 15px; 
            font-weight: 600;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
            color: var(--secondary-color);
            font-size: 16px;
        }
        
        .info-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 15px; 
            font-size: 14px;
        }
        
        .info-table th, .info-table td { 
            border: 1px solid var(--border-color); 
            padding: 10px; 
            text-align: left; 
            vertical-align: top;
        }
        
        .info-table th { 
            background-color: var(--light-gray); 
            font-weight: 600;
            width: 20%;
        }
        
        .visits-table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 13px;
        }
        
        .visits-table th, .visits-table td { 
            border: 1px solid var(--border-color); 
            padding: 8px; 
            text-align: left; 
        }
        
        .visits-table th { 
            background-color: var(--light-gray); 
            font-weight: 600;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .signature-box {
            width: 45%;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 40px 0 10px;
            padding-bottom: 5px;
        }
        
        .signature-label {
            font-weight: 600;
            margin-top: 5px;
        }
        
        .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 12px; 
            color: #666;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
        }
        
        /* Zoom Controls */
        .zoom-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }
        
        .zoom-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 3px;
            width: 36px;
            height: 36px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background 0.2s;
        }
        
        .zoom-btn:hover {
            background: #2980b9;
        }
        
        .zoom-level {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            min-width: 50px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .print-btn:hover {
            background-color: #2980b9;
        }
        
        .close-btn {
            background-color: #6c757d;
            color: white;
        }
        
        .close-btn:hover {
            background-color: #5a6268;
        }
        
        @media print {
            .no-print { display: none; }
            body { 
                margin: 0; 
                background: white;
                padding: 0;
            }
            .document-container {
                box-shadow: none;
                padding: 0.5in;
                max-width: 100%;
                min-height: auto;
            }
            .section-title {
                background-color: #f1f1f1 !important;
                -webkit-print-color-adjust: exact;
            }
            .info-table th {
                background-color: #f1f1f1 !important;
                -webkit-print-color-adjust: exact;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 900px) {
            body {
                padding: 10px;
            }
            .document-container {
                padding: 20px;
            }
            .signature-section {
                flex-direction: column;
                gap: 30px;
            }
            .signature-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="zoom-controls no-print">
        <button class="zoom-btn" id="zoomOut" title="Zoom Out">
            <i class="fas fa-search-minus"></i>
        </button>
        <div class="zoom-level" id="zoomLevel">100%</div>
        <button class="zoom-btn" id="zoomIn" title="Zoom In">
            <i class="fas fa-search-plus"></i>
        </button>
    </div>
    
    <div class="document-container" id="documentContent">
        <!-- Barangay Header -->
        <div class="barangay-header">
            <h2>Barangay Toong, Cebu City</h2>
            <p>OFFICE OF THE HEALTHCARE SERVICES</p>
        </div>
        
        <div class="header">
            <h1>Patient Health Record</h1>
            <p>Generated on: <?= date('F j, Y, g:i a') ?></p>
            <p>Patient ID: <?= $patientId ?></p>
        </div>
        
        <div class="section">
            <div class="section-title">Personal Information</div>
            <table class="info-table">
                <tr>
                    <th>Full Name</th>
                    <td><?= htmlspecialchars($patient['full_name']) ?></td>
                    <th>Age</th>
                    <td><?= $patient['age'] ?? 'N/A' ?></td>
                </tr>
                <tr>
                    <th>Gender</th>
                    <td><?= isset($patient['gender']) && $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></td>
                    <th>Contact</th>
                    <td><?= htmlspecialchars($patient['contact'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Address</th>
                    <td colspan="3"><?= htmlspecialchars($patient['address'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Last Check-up</th>
                    <td><?= $patient['last_checkup'] ? date('F j, Y', strtotime($patient['last_checkup'])) : 'N/A' ?></td>
                    <th>User Type</th>
                    <td><?= !empty($patient['unique_number']) ? 'Registered User' : 'Regular Patient' ?></td>
                </tr>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">Health Information</div>
            <table class="info-table">
                <tr>
                    <th>Height</th>
                    <td><?= $healthInfo['height'] ?? 'N/A' ?> cm</td>
                    <th>Weight</th>
                    <td><?= $healthInfo['weight'] ?? 'N/A' ?> kg</td>
                </tr>
                <tr>
                    <th>Blood Type</th>
                    <td><?= htmlspecialchars($healthInfo['blood_type'] ?? 'N/A') ?></td>
                    <th>Allergies</th>
                    <td><?= htmlspecialchars($healthInfo['allergies'] ?? 'None') ?></td>
                </tr>
                <tr>
                    <th>Medical History</th>
                    <td colspan="3"><?= htmlspecialchars($healthInfo['medical_history'] ?? 'None') ?></td>
                </tr>
                <tr>
                    <th>Current Medications</th>
                    <td colspan="3"><?= htmlspecialchars($healthInfo['current_medications'] ?? 'None') ?></td>
                </tr>
                <tr>
                    <th>Family History</th>
                    <td colspan="3"><?= htmlspecialchars($healthInfo['family_history'] ?? 'None') ?></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($visits)): ?>
        <div class="section">
            <div class="section-title">Visit History</div>
            <table class="visits-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($visit['visit_date'])) ?></td>
                        <td><?= htmlspecialchars($visit['reason'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($visit['diagnosis'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($visit['treatment'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($visit['notes'] ?? 'N/A') ?></td>
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
                <div class="signature-label">Attending Physician</div>
                <div>Dr. <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'Medical Doctor') ?></div>
            </div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Barangay Health Worker</div>
                <div>Name of Health Worker</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Generated by: <?= htmlspecialchars($_SESSION['user']['full_name'] ?? 'System') ?></p>
            <p>This document is confidential and intended for authorized personnel only.</p>
        </div>
    </div>
    
    <div class="no-print action-buttons">
        <button class="action-btn print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Document
        </button>
        <button class="action-btn close-btn" onclick="window.close()">
            <i class="fas fa-times"></i> Close Window
        </button>
    </div>

    <script>
        // Zoom functionality
        document.addEventListener('DOMContentLoaded', function() {
            const documentContent = document.getElementById('documentContent');
            const zoomLevel = document.getElementById('zoomLevel');
            const zoomInBtn = document.getElementById('zoomIn');
            const zoomOutBtn = document.getElementById('zoomOut');
            
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
        });
    </script>
</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
?>