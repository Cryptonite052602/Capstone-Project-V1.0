<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get soft-deleted patients
$patients = [];

try {
    $stmt = $pdo->prepare("SELECT p.*, m.blood_type 
                          FROM sitio1_patients p
                          LEFT JOIN existing_info_patients m ON p.id = m.patient_id
                          WHERE p.added_by = ? AND p.deleted_at IS NOT NULL
                          ORDER BY p.deleted_at DESC");
    $stmt->execute([$_SESSION['user']['id']]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching archived patient records: ' . $e->getMessage();
}

// Handle restore action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore_patient'])) {
        $id = intval($_POST['id']);
        
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET deleted_at = NULL WHERE id = ? AND added_by = ?");
                $stmt->execute([$id, $_SESSION['user']['id']]);
                
                $_SESSION['success'] = 'Patient record restored successfully!';
                header('Location: deleted_patients.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error restoring patient record: ' . $e->getMessage();
            }
        }
    }
    
    // Handle permanent deletion
    if (isset($_POST['delete_permanently'])) {
        $id = intval($_POST['id']);
        
        if ($id > 0) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Delete from medical info table first
                $stmt = $pdo->prepare("DELETE FROM existing_info_patients WHERE patient_id = ?");
                $stmt->execute([$id]);
                
                // Then delete from main patients table
                $stmt = $pdo->prepare("DELETE FROM sitio1_patients WHERE id = ? AND added_by = ?");
                $stmt->execute([$id, $_SESSION['user']['id']]);
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Patient record permanently deleted successfully!';
                header('Location: deleted_patients.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Error permanently deleting patient record: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Archived Patient Records</h1>
        <a href="patient_records.php" class="text-blue-600 hover:underline">Back to Active Records</a>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($patients)): ?>
        <p class="text-gray-600">No archived patient records found.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Age/Gender</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Blood Type</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Archived On</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($patient['full_name']) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200">
                                <?= $patient['age'] ?: 'N/A' ?>
                                <?= $patient['gender'] ? '/'.htmlspecialchars($patient['gender']) : '' ?>
                            </td>
                            <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($patient['blood_type'] ?: 'N/A') ?></td>
                            <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y H:i', strtotime($patient['deleted_at'])) ?></td>
                            <td class="py-2 px-4 border-b border-gray-200">
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="id" value="<?= $patient['id'] ?>">
                                    <button type="submit" name="restore_patient" class="text-green-600 hover:underline mr-2">
                                        Restore
                                    </button>
                                    <button type="submit" name="delete_permanently" class="text-red-600 hover:underline" 
                                        onclick="return confirm('WARNING: This will permanently delete this patient record and all associated data. Are you sure?')">
                                        Delete Permanently
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

