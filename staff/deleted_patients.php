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

// Handle patient restoration - MODIFIED TO PRESERVE USER_ID
if (isset($_GET['restore_patient'])) {
    $deletedPatientId = $_GET['restore_patient'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get deleted patient data including user_id
        $stmt = $pdo->prepare("SELECT * FROM deleted_patients WHERE id = ? AND deleted_by = ?");
        $stmt->execute([$deletedPatientId, $_SESSION['user']['id']]);
        $deletedPatient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($deletedPatient) {
            // Insert back into main patients table - preserve the user_id
            $stmt = $pdo->prepare("INSERT INTO sitio1_patients 
                (id, full_name, age, gender, address, contact, last_checkup, added_by, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $deletedPatient['original_id'],
                $deletedPatient['full_name'],
                $deletedPatient['age'],
                $deletedPatient['gender'],
                $deletedPatient['address'],
                $deletedPatient['contact'],
                $deletedPatient['last_checkup'],
                $deletedPatient['added_by'],
                $deletedPatient['user_id'] // This preserves the user linkage for restoration
            ]);
            
            // Also restore health info if it exists in archive
            $stmt = $pdo->prepare("INSERT IGNORE INTO existing_info_patients (patient_id) VALUES (?)");
            $stmt->execute([$deletedPatient['original_id']]);
            
            // Remove from deleted patients table
            $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE id = ?");
            $stmt->execute([$deletedPatientId]);
            
            $pdo->commit();
            
            $message = 'Patient record restored successfully!';
            header('Location: deleted_patients.php');
            exit();
        } else {
            $error = 'Deleted patient not found or access denied!';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Error restoring patient record: ' . $e->getMessage();
    }
}

// Handle permanent deletion
if (isset($_GET['permanent_delete'])) {
    $deletedPatientId = $_GET['permanent_delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM deleted_patients WHERE id = ? AND deleted_by = ?");
        $stmt->execute([$deletedPatientId, $_SESSION['user']['id']]);
        
        if ($stmt->rowCount() > 0) {
            $message = 'Patient record permanently deleted!';
        } else {
            $error = 'Record not found or access denied!';
        }
        
        header('Location: deleted_patients.php');
        exit();
    } catch (PDOException $e) {
        $error = 'Error permanently deleting record: ' . $e->getMessage();
    }
}

// Get all deleted patients with user information
try {
    $stmt = $pdo->prepare("SELECT d.*, 
                          u.unique_number, u.email as user_email,
                          CASE WHEN d.user_id IS NOT NULL THEN 1 ELSE 0 END as is_registered_user
                          FROM deleted_patients d
                          LEFT JOIN sitio1_users u ON d.user_id = u.id
                          WHERE d.deleted_by = ? 
                          ORDER BY d.deleted_at DESC");
    $stmt->execute([$_SESSION['user']['id']]);
    $deletedPatients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching deleted patients: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Patients Archive - Barangay Luz Health Center</title>
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
        .user-badge {
            background-color: #e0e7ff;
            color: #3730a3;
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Main container styling */
        .main-container {
            background-color: white;
            border: 1px solid #f0f9ff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
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
            border-radius: 8px;
        }
        
        .alert-error {
            background-color: #fef2f2;
            border: 2px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
        }
        
        /* Button Styles - UPDATED: Consistent borders always visible */
        .btn-primary { 
            background-color: white; 
            color: #3498db; 
            border: 2px solid #bae6fd; 
            border-radius: 8px; 
            padding: 12px 24px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            min-height: 55px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            text-decoration: none;
        }
        .btn-primary:hover { 
            background-color: #f0f9ff; 
            border-color: #3498db;
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.15);
        }
        
        .btn-restore { 
            background-color: white; 
            color: #2ecc71; 
            border: 2px solid #bbf7d0; 
            border-radius: 8px; 
            padding: 10px 20px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .btn-restore:hover { 
            background-color: #f0fdf4; 
            border-color: #2ecc71;
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15);
        }
        
        .btn-delete { 
            background-color: white; 
            color: #e74c3c; 
            border: 2px solid #fecaca; 
            border-radius: 8px; 
            padding: 10px 20px; 
            transition: all 0.3s ease; 
            font-weight: 500;
            min-height: 45px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .btn-delete:hover { 
            background-color: #fef2f2; 
            border-color: #e74c3c;
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.15);
        }
        
        /* Table styling */
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
            background-color: #f0f9ff; 
            color: #2c3e50; 
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 14px;
        }
        
        .patient-table tr:hover { 
            background-color: #f8fafc; 
        }
        
        /* Patient ID styling */
        .patient-id { 
            font-weight: bold; 
            color: #3498db; 
        }
        
        /* Custom notification animation */
        .custom-notification { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { 
            from { transform: translateX(100%); opacity: 0; } 
            to { transform: translateX(0); opacity: 1; } 
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 text-secondary">Deleted Patients Archive</h1>
        
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
        
        <div class="main-container overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-semibold text-secondary">Archived Patient Records</h2>
                        <p class="text-sm text-gray-500 mt-1">Patient records that have been moved to archive</p>
                    </div>
                    <a href="existing_info_patients.php" class="btn-primary">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Patients
                    </a>
                </div>
            </div>
            
            <?php if (empty($deletedPatients)): ?>
                <div class="text-center py-12 bg-gray-50 rounded-lg">
                    <div class="w-20 h-20 bg-white border-2 border-warmBlue rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-archive text-primary text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Archive is Empty</h3>
                    <p class="mt-1 text-sm text-gray-500">No deleted patient records found in archive.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="patient-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Deleted On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deletedPatients as $patient): ?>
                                <tr>
                                    <td>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($patient['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= $patient['original_id'] ?></div>
                                        <?php if (!empty($patient['user_email'])): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($patient['user_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $patient['age'] ?? 'N/A' ?></td>
                                    <td><?= htmlspecialchars($patient['gender'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($patient['user_id']) && $patient['is_registered_user']): ?>
                                            <span class="user-badge">Registered User</span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Regular Patient</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($patient['contact'] ?? 'N/A') ?></td>
                                    <td>
                                        <?= date('M j, Y', strtotime($patient['deleted_at'])) ?>
                                        <div class="text-sm text-gray-500">
                                            <?= date('g:i A', strtotime($patient['deleted_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="?restore_patient=<?= $patient['id'] ?>" 
                                               class="btn-restore" 
                                               onclick="return confirm('Are you sure you want to restore this patient record?')">
                                                <i class="fas fa-undo mr-1"></i>Restore
                                            </a>
                                            <a href="?permanent_delete=<?= $patient['id'] ?>" 
                                               class="btn-delete" 
                                               onclick="return confirm('Are you sure you want to permanently delete this record? This action cannot be undone.')">
                                                <i class="fas fa-trash-alt mr-1"></i>Delete
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
    </div>

    <script>
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
        
        // Show notification function
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
    </script>
</body>
</html>