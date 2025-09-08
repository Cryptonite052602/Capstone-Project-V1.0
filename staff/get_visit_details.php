<?php
// get_visit_details.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

redirectIfNotLoggedIn();
if (!isStaff()) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Access denied</div>';
    exit();
}

$visitId = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT pv.*, u.full_name as staff_name, p.full_name as patient_name
                          FROM patient_visits pv 
                          LEFT JOIN sitio1_users u ON pv.staff_id = u.id 
                          LEFT JOIN sitio1_patients p ON pv.patient_id = p.id
                          WHERE pv.id = ? AND p.added_by = ?");
    $stmt->execute([$visitId, $_SESSION['user']['id']]);
    $visit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$visit) {
        echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Visit not found</div>';
        exit();
    }
    
    $visitDate = date('M j, Y g:i A', strtotime($visit['visit_date']));
    $nextVisit = $visit['next_visit_date'] ? date('M j, Y', strtotime($visit['next_visit_date'])) : 'Not scheduled';
    
    echo '
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-xl font-semibold text-secondary">Visit Details</h3>
            <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Patient</h4>
                    <p class="text-lg">' . htmlspecialchars($visit['patient_name']) . '</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Date & Time</h4>
                    <p class="text-lg">' . $visitDate . '</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Visit Type</h4>
                    <p class="text-lg"><span class="visit-type-badge visit-type-' . $visit['visit_type'] . '">' . ucfirst($visit['visit_type']) . '</span></p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Staff Member</h4>
                    <p class="text-lg">' . htmlspecialchars($visit['staff_name']) . '</p>
                </div>
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Next Visit</h4>
                    <p class="text-lg">' . $nextVisit . '</p>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Diagnosis</h4>
                    <p class="text-gray-800 bg-gray-50 p-4 rounded-lg">' . nl2br(htmlspecialchars($visit['diagnosis'] ?: 'Not specified')) . '</p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Treatment Provided</h4>
                    <p class="text-gray-800 bg-gray-50 p-4 rounded-lg">' . nl2br(htmlspecialchars($visit['treatment'] ?: 'Not specified')) . '</p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Prescription</h4>
                    <p class="text-gray-800 bg-gray-50 p-4 rounded-lg">' . nl2br(htmlspecialchars($visit['prescription'] ?: 'Not specified')) . '</p>
                </div>
                
                <div>
                    <h4 class="text-sm font-medium text-gray-500">Additional Notes</h4>
                    <p class="text-gray-800 bg-gray-50 p-4 rounded-lg">' . nl2br(htmlspecialchars($visit['notes'] ?: 'Not specified')) . '</p>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="this.parentElement.parentElement.parentElement.remove()" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>
    </div>';
} catch (PDOException $e) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Error loading visit details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>