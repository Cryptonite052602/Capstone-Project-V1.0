<?php
require_once __DIR__ . '/../includes/auth.php';


redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

if (isset($_GET['id'])) {
    $patientId = intval($_GET['id']);
    
    try {
        // Soft delete by setting deleted_at timestamp
        $stmt = $pdo->prepare("UPDATE sitio1_patients SET deleted_at = NOW() WHERE id = ? AND added_by = ?");
        $stmt->execute([$patientId, $_SESSION['user']['id']]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Patient record archived successfully.';
        } else {
            $_SESSION['error'] = 'Patient record not found or you don\'t have permission to delete it.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error archiving patient record: ' . $e->getMessage();
    }
}

header('Location: patient_records.php');
exit();
?>