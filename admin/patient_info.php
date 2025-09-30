<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get all patients with staff info
$patients = [];
$diseaseStats = [];

try {
    $stmt = $pdo->query("
        SELECT p.*, s.full_name as staff_name 
        FROM sitio1_patients p
        LEFT JOIN sitio1_staff s ON p.added_by = s.id
        ORDER BY p.created_at DESC
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get disease statistics
    $stmt = $pdo->query("
        SELECT disease, COUNT(*) as count 
        FROM sitio1_patients 
        WHERE disease IS NOT NULL AND disease != ''
        GROUP BY disease
        ORDER BY count DESC
        LIMIT 5
    ");
    $diseaseStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching patient data: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Patient Information</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Disease Statistics -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4">Top Diseases/Conditions</h2>
        
        <?php if (empty($diseaseStats)): ?>
            <p class="text-gray-600">No disease data available.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <?php foreach ($diseaseStats as $disease): ?>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="font-semibold text-blue-800"><?= htmlspecialchars($disease['disease']) ?></h3>
                        <p class="text-2xl font-bold text-blue-600"><?= $disease['count'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Patient List -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4">All Patients</h2>
        
        <?php if (empty($patients)): ?>
            <p class="text-gray-600">No patient records found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Age</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Disease</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Last Check-up</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Added By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($patient['full_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= $patient['age'] ?: 'N/A' ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($patient['disease'] ?: 'N/A') ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= $patient['last_checkup'] ? date('M d, Y', strtotime($patient['last_checkup'])) : 'N/A' ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($patient['staff_name'] ?: 'System') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

