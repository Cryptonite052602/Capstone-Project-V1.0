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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_patient'])) {
        $fullName = trim($_POST['full_name']);
        $age = intval($_POST['age']);
        $gender = trim($_POST['gender']);
        $address = trim($_POST['address']);
        $contact = trim($_POST['contact']);
        $lastCheckup = trim($_POST['last_checkup']);
        
        // Medical information
        $height = !empty($_POST['height']) ? floatval($_POST['height']) : null;
        $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
        $bloodType = trim($_POST['blood_type']);
        $allergies = trim($_POST['allergies']);
        $medicalHistory = trim($_POST['medical_history']);
        $currentMedications = trim($_POST['current_medications']);
        $familyHistory = trim($_POST['family_history']);
        
        if (!empty($fullName)) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Update main patient info
                $stmt = $pdo->prepare("UPDATE sitio1_patients SET 
                    full_name = ?, age = ?, gender = ?, address = ?, 
                    contact = ?, last_checkup = ?, updated_at = NOW() 
                    WHERE id = ? AND added_by = ?");
                
                $stmt->execute([
                    $fullName, $age, $gender, $address, 
                    $contact, $lastCheckup, $id, $_SESSION['user']['id']
                ]);
                
                // Update or insert medical info
                if ($medicalInfo) {
                    $stmt = $pdo->prepare("UPDATE existing_info_patients SET 
                        height = ?, weight = ?, blood_type = ?, allergies = ?, 
                        medical_history = ?, current_medications = ?, family_history = ? 
                        WHERE patient_id = ?");
                    
                    $stmt->execute([
                        $height, $weight, $bloodType, $allergies, 
                        $medicalHistory, $currentMedications, $familyHistory, $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO existing_info_patients 
                        (patient_id, height, weight, blood_type, allergies, 
                        medical_history, current_medications, family_history) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $id, $height, $weight, $bloodType, $allergies, 
                        $medicalHistory, $currentMedications, $familyHistory
                    ]);
                }
                
                $pdo->commit();
                
                $_SESSION['success'] = 'Patient record updated successfully!';
                header('Location: patient_records.php');
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Error updating patient record: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Full name is required.';
        }
    }
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Edit Patient Record</h1>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white p-6 rounded-lg shadow">
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Information -->
                <div>
                    <label for="full_name" class="block text-gray-700 mb-2">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($patient['full_name']) ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                
                <div>
                    <label for="age" class="block text-gray-700 mb-2">Age</label>
                    <input type="number" id="age" name="age" value="<?= $patient['age'] ?>" min="0" max="120" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="gender" class="block text-gray-700 mb-2">Gender</label>
                    <select id="gender" name="gender" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Gender</option>
                        <option value="Male" <?= $patient['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $patient['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $patient['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="address" class="block text-gray-700 mb-2">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($patient['address']) ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="contact" class="block text-gray-700 mb-2">Contact Number</label>
                    <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($patient['contact']) ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="last_checkup" class="block text-gray-700 mb-2">Last Check-up Date</label>
                    <input type="date" id="last_checkup" name="last_checkup" value="<?= $patient['last_checkup'] ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Medical Information -->
                <div>
                    <label for="height" class="block text-gray-700 mb-2">Height (cm)</label>
                    <input type="number" id="height" name="height" step="0.01" min="0" value="<?= $medicalInfo['height'] ?? '' ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="weight" class="block text-gray-700 mb-2">Weight (kg)</label>
                    <input type="number" id="weight" name="weight" step="0.01" min="0" value="<?= $medicalInfo['weight'] ?? '' ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label for="blood_type" class="block text-gray-700 mb-2">Blood Type</label>
                    <select id="blood_type" name="blood_type" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Blood Type</option>
                        <option value="A+" <?= ($medicalInfo['blood_type'] ?? '') === 'A+' ? 'selected' : '' ?>>A+</option>
                        <option value="A-" <?= ($medicalInfo['blood_type'] ?? '') === 'A-' ? 'selected' : '' ?>>A-</option>
                        <option value="B+" <?= ($medicalInfo['blood_type'] ?? '') === 'B+' ? 'selected' : '' ?>>B+</option>
                        <option value="B-" <?= ($medicalInfo['blood_type'] ?? '') === 'B-' ? 'selected' : '' ?>>B-</option>
                        <option value="AB+" <?= ($medicalInfo['blood_type'] ?? '') === 'AB+' ? 'selected' : '' ?>>AB+</option>
                        <option value="AB-" <?= ($medicalInfo['blood_type'] ?? '') === 'AB-' ? 'selected' : '' ?>>AB-</option>
                        <option value="O+" <?= ($medicalInfo['blood_type'] ?? '') === 'O+' ? 'selected' : '' ?>>O+</option>
                        <option value="O-" <?= ($medicalInfo['blood_type'] ?? '') === 'O-' ? 'selected' : '' ?>>O-</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label for="allergies" class="block text-gray-700 mb-2">Allergies</label>
                    <textarea id="allergies" name="allergies" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($medicalInfo['allergies'] ?? '') ?></textarea>
                </div>
                
                <div>
                    <label for="current_medications" class="block text-gray-700 mb-2">Current Medications</label>
                    <textarea id="current_medications" name="current_medications" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($medicalInfo['current_medications'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="mt-6">
                <label for="medical_history" class="block text-gray-700 mb-2">Medical History</label>
                <textarea id="medical_history" name="medical_history" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($medicalInfo['medical_history'] ?? '') ?></textarea>
            </div>
            
            <div class="mt-6">
                <label for="family_history" class="block text-gray-700 mb-2">Family History</label>
                <textarea id="family_history" name="family_history" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($medicalInfo['family_history'] ?? '') ?></textarea>
            </div>
            
            <div class="mt-6 flex justify-between">
                <a href="patient_records.php" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600 transition">Cancel</a>
                <button type="submit" name="update_patient" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 transition">Update Patient</button>
            </div>
        </form>
    </div>
</div>

