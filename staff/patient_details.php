<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$patient = null;
$medicalInfo = null;
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch patient details
try {
    // Get main patient info
    $stmt = $pdo->prepare("SELECT * FROM sitio1_patients WHERE id = ? AND added_by = ?");
    $stmt->execute([$id, $_SESSION['user']['id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['error'] = 'Patient record not found or access denied.';
        header('Location: patient_records.php');
        exit();
    }
    
    // Get medical info
    $stmt = $pdo->prepare("SELECT * FROM existing_info_patients WHERE patient_id = ?");
    $stmt->execute([$id]);
    $medicalInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching patient record: ' . $e->getMessage();
    header('Location: patient_records.php');
    exit();
}
?>

<div class="container mx-auto px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Patient Details</h1>
        <a href="patient_records.php" class="text-blue-600 hover:underline">Back to Records</a>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h2 class="text-xl font-semibold mb-4">Personal Information</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-500 text-sm">Full Name</p>
                        <p class="text-gray-800"><?= htmlspecialchars($patient['full_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Age</p>
                        <p class="text-gray-800"><?= $patient['age'] ?: 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Gender</p>
                        <p class="text-gray-800"><?= $patient['gender'] ? htmlspecialchars($patient['gender']) : 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Address</p>
                        <p class="text-gray-800"><?= $patient['address'] ? htmlspecialchars($patient['address']) : 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Contact Number</p>
                        <p class="text-gray-800"><?= $patient['contact'] ? htmlspecialchars($patient['contact']) : 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Last Check-up</p>
                        <p class="text-gray-800"><?= $patient['last_checkup'] ? date('M d, Y', strtotime($patient['last_checkup'])) : 'N/A' ?></p>
                    </div>
                </div>
            </div>
            
            <div>
                <h2 class="text-xl font-semibold mb-4">Medical Information</h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-500 text-sm">Height</p>
                        <p class="text-gray-800"><?= $medicalInfo['height'] ?? 'N/A' ?> cm</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Weight</p>
                        <p class="text-gray-800"><?= $medicalInfo['weight'] ?? 'N/A' ?> kg</p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Blood Type</p>
                        <p class="text-gray-800"><?= $medicalInfo['blood_type'] ?? 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Allergies</p>
                        <p class="text-gray-800"><?= $medicalInfo['allergies'] ? nl2br(htmlspecialchars($medicalInfo['allergies'])) : 'N/A' ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Current Medications</p>
                        <p class="text-gray-800"><?= $medicalInfo['current_medications'] ? nl2br(htmlspecialchars($medicalInfo['current_medications'])) : 'N/A' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-8">
            <h2 class="text-xl font-semibold mb-4">Medical History</h2>
            <div class="bg-gray-50 p-4 rounded-lg">
                <?= $medicalInfo['medical_history'] ? nl2br(htmlspecialchars($medicalInfo['medical_history'])) : 'No medical history recorded.' ?>
            </div>
        </div>
        
        <div class="mt-8">
            <h2 class="text-xl font-semibold mb-4">Family History</h2>
            <div class="bg-gray-50 p-4 rounded-lg">
                <?= $medicalInfo['family_history'] ? nl2br(htmlspecialchars($medicalInfo['family_history'])) : 'No family history recorded.' ?>
            </div>
        </div>
        
        <div class="mt-8 flex justify-between">
            <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">Edit Record</a>
            <div class="space-x-2">
                <a href="soft_delete_patient.php?id=<?= $patient['id'] ?>" class="bg-yellow-500 text-white py-2 px-6 rounded-lg hover:bg-yellow-600 transition" onclick="return confirm('Move this patient record to archive?')">Archive</a>
                <a href="delete_patient.php?id=<?= $patient['id'] ?>" class="bg-red-600 text-white py-2 px-6 rounded-lg hover:bg-red-700 transition" onclick="return confirm('Are you sure you want to permanently delete this patient record?')">Delete</a>
            </div>
        </div>
    </div>
</div>

