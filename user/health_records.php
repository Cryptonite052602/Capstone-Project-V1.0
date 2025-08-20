<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

$userId = $_SESSION['user']['id'];
$error = '';
$success = '';

// Handle record viewing/download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_record'])) {
    $recordId = $_POST['record_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT v.*, s.full_name as doctor_name, p.full_name as patient_name
            FROM patient_visits v
            JOIN sitio1_staff s ON v.staff_id = s.id
            JOIN sitio1_patients p ON v.patient_id = p.id
            WHERE v.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$recordId, $userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Generate PDF or allow download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="health_record_' . $recordId . '.pdf"');
            // Simple text output (replace with PDF generation in production)
            echo "HEALTH RECORD\n";
            echo "========================\n\n";
            echo "Patient: " . $record['patient_name'] . "\n";
            echo "Doctor: " . $record['doctor_name'] . "\n";
            echo "Visit Date: " . $record['visit_date'] . "\n";
            echo "Visit Type: " . $record['visit_type'] . "\n\n";
            echo "Diagnosis:\n" . $record['diagnosis'] . "\n\n";
            echo "Treatment:\n" . $record['treatment'] . "\n\n";
            echo "Prescription:\n" . $record['prescription'] . "\n\n";
            echo "Notes:\n" . $record['notes'] . "\n";
            exit();
        } else {
            $error = 'Record not found or access denied';
        }
    } catch (PDOException $e) {
        $error = 'Error fetching record: ' . $e->getMessage();
    }
}

// Get user's health records
$healthRecords = [];

try {
    $stmt = $pdo->prepare("
        SELECT v.*, s.full_name as doctor_name
        FROM patient_visits v
        JOIN sitio1_staff s ON v.staff_id = s.id
        JOIN sitio1_patients p ON v.patient_id = p.id
        WHERE p.user_id = ?
        ORDER BY v.visit_date DESC
    ");
    $stmt->execute([$userId]);
    $healthRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching health records: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">My Health Records</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-lg shadow">
        <?php if (empty($healthRecords)): ?>
            <p class="text-gray-600">No health records available.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Doctor</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Visit Type</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($healthRecords as $record): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y', strtotime($record['visit_date'])) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($record['doctor_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= ucfirst($record['visit_type']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <button onclick="showRecordDetails(<?= htmlspecialchars(json_encode($record)) ?>)" 
                                            class="text-blue-600 hover:underline mr-2">
                                        View
                                    </button>
                                    <form method="POST" action="" class="inline">
                                        <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                                        <button type="submit" name="download_record" class="text-green-600 hover:underline">
                                            Download
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
</div>

<!-- Record Details Modal -->
<div id="recordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Health Record Details</h3>
            <button onclick="document.getElementById('recordModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                &times;
            </button>
        </div>
        <div id="recordDetails" class="space-y-4">
            <!-- Dynamic content will be inserted here -->
        </div>
    </div>
</div>

<script>
function showRecordDetails(record) {
    const modal = document.getElementById('recordModal');
    const detailsDiv = document.getElementById('recordDetails');
    
    detailsDiv.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="font-medium text-gray-700">Patient</h4>
                <p>${record.patient_name || '<?= htmlspecialchars($_SESSION['user']['full_name']) ?>'}</p>
            </div>
            <div>
                <h4 class="font-medium text-gray-700">Doctor</h4>
                <p>${record.doctor_name}</p>
            </div>
            <div>
                <h4 class="font-medium text-gray-700">Date</h4>
                <p>${new Date(record.visit_date).toLocaleString()}</p>
            </div>
            <div>
                <h4 class="font-medium text-gray-700">Visit Type</h4>
                <p>${record.visit_type}</p>
            </div>
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-700">Diagnosis</h4>
                <p class="whitespace-pre-line">${record.diagnosis || 'No diagnosis recorded'}</p>
            </div>
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-700">Treatment</h4>
                <p class="whitespace-pre-line">${record.treatment || 'No treatment recorded'}</p>
            </div>
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-700">Prescription</h4>
                <p class="whitespace-pre-line">${record.prescription || 'No prescription'}</p>
            </div>
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-700">Notes</h4>
                <p class="whitespace-pre-line">${record.notes || 'No additional notes'}</p>
            </div>
            ${record.next_visit_date ? `
            <div class="md:col-span-2">
                <h4 class="font-medium text-gray-700">Next Visit</h4>
                <p>${new Date(record.next_visit_date).toLocaleDateString()}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    modal.classList.remove('hidden');
}
</script>

