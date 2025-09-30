<?php
// staff/get_visit_details.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
redirectIfNotLoggedIn();

if (!isStaff()) {
    header('Location: /community-health-tracker/');
    exit();
}

$visitId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($visitId <= 0) {
    echo '<div class="p-4 text-danger">Invalid visit ID</div>';
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT pv.*, u.full_name as staff_name, p.full_name as patient_name
                          FROM patient_visits pv 
                          LEFT JOIN sitio1_users u ON pv.staff_id = u.id 
                          LEFT JOIN sitio1_patients p ON pv.patient_id = p.id
                          WHERE pv.id = ?");
    $stmt->execute([$visitId]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        echo '<div class="p-4 text-danger">Visit not found</div>';
        exit();
    }
    
    $visitDate = date('M j, Y g:i A', strtotime($visit['visit_date']));
    $nextVisit = !empty($visit['next_visit_date']) ? date('M j, Y', strtotime($visit['next_visit_date'])) : 'Not scheduled';
    
    echo '
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Visit Details</h3>
                <button onclick="this.closest(\'.fixed\').remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Patient</label>
                    <p class="text-gray-800">' . htmlspecialchars($visit['patient_name']) . '</p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Staff</label>
                    <p class="text-gray-800">' . htmlspecialchars($visit['staff_name']) . '</p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Visit Date</label>
                    <p class="text-gray-800">' . $visitDate . '</p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Visit Type</label>
                    <p class="text-gray-800"><span class="visit-type-badge visit-type-' . $visit['visit_type'] . '">' . ucfirst($visit['visit_type']) . '</span></p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Next Visit</label>
                    <p class="text-gray-800">' . $nextVisit . '</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Diagnosis</label>
                    <p class="text-gray-800 bg-gray-50 p-3 rounded">' . (!empty($visit['diagnosis']) ? nl2br(htmlspecialchars($visit['diagnosis'])) : 'N/A') . '</p>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Treatment Provided</label>
                    <p class="text-gray-800 bg-gray-50 p-3 rounded">' . (!empty($visit['treatment']) ? nl2br(htmlspecialchars($visit['treatment'])) : 'N/A') . '</p>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Prescription</label>
                    <p class="text-gray-800 bg-gray-50 p-3 rounded">' . (!empty($visit['prescription']) ? nl2br(htmlspecialchars($visit['prescription'])) : 'N/A') . '</p>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Additional Notes</label>
                    <p class="text-gray-800 bg-gray-50 p-3 rounded">' . (!empty($visit['notes']) ? nl2br(htmlspecialchars($visit['notes'])) : 'N/A') . '</p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button onclick="this.closest(\'.fixed\').remove()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Close
                </button>
            </div>
        </div>
    </div>';
    
} catch (PDOException $e) {
    echo '<div class="p-4 text-danger">Error loading visit details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>