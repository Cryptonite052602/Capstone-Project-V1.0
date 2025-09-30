<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Get all appointments across all sitios
$allAppointments = [];
$stats = [
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'rejected' => 0
];

try {
    $stmt = $pdo->query("
        SELECT ua.*, u.full_name, u.contact, a.date, a.start_time, a.end_time, s.full_name as staff_name
        FROM user_appointments ua
        JOIN sitio1_users u ON ua.user_id = u.id
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        JOIN sitio1_staff s ON a.staff_id = s.id
        ORDER BY a.date DESC, a.start_time DESC
    ");
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stats
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM user_appointments GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statusCounts as $status) {
        $stats[$status['status']] = $status['count'];
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching appointments: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Appointments Overview</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Appointment Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-700">Pending</h3>
            <p class="text-3xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-700">Approved</h3>
            <p class="text-3xl font-bold text-green-600"><?= $stats['approved'] ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-700">Completed</h3>
            <p class="text-3xl font-bold text-blue-600"><?= $stats['completed'] ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold text-gray-700">Rejected</h3>
            <p class="text-3xl font-bold text-red-600"><?= $stats['rejected'] ?></p>
        </div>
    </div>

    <!-- All Appointments -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4">All Appointments</h2>
        
        <?php if (empty($allAppointments)): ?>
            <p class="text-gray-600">No appointments found.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Patient</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Staff</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date & Time</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAppointments as $appointment): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($appointment['full_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($appointment['staff_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <?= date('M d, Y', strtotime($appointment['date'])) ?><br>
                                    <?= date('h:i A', strtotime($appointment['start_time'])) ?> - <?= date('h:i A', strtotime($appointment['end_time'])) ?>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $appointment['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                           ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($appointment['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) ?>">
                                        <?= ucfirst($appointment['status']) ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($appointment['contact']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

