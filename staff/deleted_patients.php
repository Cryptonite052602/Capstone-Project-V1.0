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
    <title>Deleted Patients - Community Health Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Deleted Patients Archive</h1>
            <a href="existing_info_patients.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Patients
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($deletedPatients)): ?>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <i class="fas fa-archive text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700">No deleted patients found</h3>
                <p class="text-gray-500 mt-2">Patients you delete will appear here for restoration.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted On</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($deletedPatients as $patient): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($patient['full_name']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= $patient['original_id'] ?></div>
                                        <?php if (!empty($patient['user_email'])): ?>
                                            <div class="text-sm text-gray-500">Email: <?= htmlspecialchars($patient['user_email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $patient['age'] ?? 'N/A' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $patient['gender'] ?? 'N/A' ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (!empty($patient['user_id']) && $patient['is_registered_user']): ?>
                                            <span class="user-badge">Registered User</span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Regular Patient</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($patient['deleted_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?restore_patient=<?= $patient['id'] ?>" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('Restore this patient record?')">
                                            <i class="fas fa-undo mr-1"></i>Restore
                                        </a>
                                        <a href="?permanent_delete=<?= $patient['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Permanently delete this record? This cannot be undone.')">
                                            <i class="fas fa-trash mr-1"></i>Delete Permanently
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>