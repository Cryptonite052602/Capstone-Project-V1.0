<?php
require_once __DIR__ . '/../includes/auth.php';

redirectIfNotLoggedIn();
if (!isUser()) {
    header('Location: /community-health-tracker/');
    exit();
}

if (isset($_GET['appointment_id'])) {
    $appointmentId = intval($_GET['appointment_id']);
    $userId = $_SESSION['user']['id'];
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ua.*, 
                a.date, 
                a.start_time, 
                a.end_time, 
                s.full_name as staff_name, 
                s.specialization,
                u.full_name as patient_name,
                u.age,
                u.contact
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            JOIN sitio1_staff s ON a.staff_id = s.id
            JOIN sitio1_users u ON ua.user_id = u.id
            WHERE ua.id = ? AND ua.user_id = ?
        ");
        $stmt->execute([$appointmentId, $userId]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            die('Appointment not found');
        }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Ticket - Priority #<?= $appointment['priority_number'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .ticket-container {
                box-shadow: none !important;
                border: 2px solid #000 !important;
            }
        }
        
        .ticket-container {
            border: 2px solid #3b82f6;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }
        
        .priority-number {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-md mx-auto">
        <!-- Print Button -->
        <div class="no-print mb-4 text-center">
            <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                📄 Print or Take Screenshot
            </button>
            <button onclick="window.close()" class="ml-2 bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition">
                ❌ Close
            </button>
        </div>
        
        <!-- Ticket -->
        <div class="ticket-container rounded-lg p-6 shadow-lg">
            <!-- Header -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-blue-600 mb-2">HEALTH APPOINTMENT TICKET</h1>
                <div class="w-32 h-1 bg-blue-600 mx-auto"></div>
            </div>
            
            <!-- Priority Number -->
            <div class="text-center mb-6 py-4 bg-red-50 border-2 border-red-200 rounded-lg">
                <div class="text-sm text-red-600 font-semibold mb-1">PRIORITY NUMBER</div>
                <div class="priority-number text-5xl font-bold text-red-600">
                    <?= htmlspecialchars($appointment['priority_number']) ?>
                </div>
            </div>
            
            <!-- Appointment Details -->
            <div class="space-y-3 mb-6">
                <div class="grid grid-cols-2 gap-2">
                    <div class="font-semibold text-gray-700">Patient Name:</div>
                    <div><?= htmlspecialchars($appointment['patient_name']) ?></div>
                    
                    <div class="font-semibold text-gray-700">Health Worker:</div>
                    <div><?= htmlspecialchars($appointment['staff_name']) ?></div>
                    
                    <?php if ($appointment['specialization']): ?>
                    <div class="font-semibold text-gray-700">Specialization:</div>
                    <div><?= htmlspecialchars($appointment['specialization']) ?></div>
                    <?php endif; ?>
                    
                    <div class="font-semibold text-gray-700">Date:</div>
                    <div><?= date('F j, Y', strtotime($appointment['date'])) ?></div>
                    
                    <div class="font-semibold text-gray-700">Time:</div>
                    <div><?= date('h:i A', strtotime($appointment['start_time'])) ?> - <?= date('h:i A', strtotime($appointment['end_time'])) ?></div>
                </div>
                
                <?php if (!empty($appointment['health_concerns'])): ?>
                <div class="mt-3">
                    <div class="font-semibold text-gray-700">Health Concerns:</div>
                    <div class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($appointment['health_concerns']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="text-center text-xs text-gray-500 border-t pt-3">
                <p>Generated on: <?= date('F j, Y g:i A') ?></p>
                <p class="mt-1">Please arrive 15 minutes before your scheduled time</p>
                <p>Bring valid ID and this ticket</p>
            </div>
        </div>
        
        <!-- Screenshot Instructions -->
        <div class="no-print mt-4 text-center text-sm text-gray-600">
            <p>💡 <strong>Tip:</strong> Press Ctrl+P to print or use your browser's screenshot tool</p>
        </div>
    </div>
</body>
</html>
<?php
    } catch (PDOException $e) {
        die('Error loading ticket: ' . $e->getMessage());
    }
} else {
    die('Invalid request');
}
?>