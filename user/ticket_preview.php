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
    <title>Appointment Ticket • Priority #<?= htmlspecialchars($appointment['priority_number']) ?></title>
    <!-- Poppins Font -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">


    <script src="https://cdn.tailwindcss.com"></script>

    <style>
    body {
        font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    @media print {
        .no-print { display: none !important; }
        body { background: #fff; padding: 0; }
        .ticket-card {
            box-shadow: none !important;
            border: 3px solid #2563eb !important;
        }
    }
</style>

</head>

<body class="font-['Poppins'] bg-slate-100 min-h-screen p-6 flex items-center justify-center">


<div class="w-full max-w-xl">

    <!-- Actions -->
    <div class="no-print flex justify-center gap-4 mb-6">
        <button onclick="window.print()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-7 py-3 rounded-xl text-base font-semibold shadow-lg transition">
            🖨 Print / Save Ticket
        </button>
        <button onclick="window.close()"
            class="bg-slate-600 hover:bg-slate-700 text-white px-7 py-3 rounded-xl text-base font-semibold shadow-lg transition">
            ✖ Close
        </button>
    </div>

    <!-- Ticket -->
    <div class="ticket-card bg-white rounded-2xl shadow-2xl border border-blue-300 overflow-hidden">

        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white px-8 py-6 text-center">
            <h1 class="text-2xl font-bold tracking-wide">
                Barangay Luz Health Appointment
            </h1>
            <p class="text-sm opacity-95 mt-2">
                Official Appointment Ticket
            </p>
        </div>

        <!-- Priority Number -->
        <div class="px-8 py-8 text-center border-b">
            <p class="text-sm uppercase tracking-widest text-blue-700 font-bold mb-3">
                Priority Number
            </p>
            <div class="text-7xl font-extrabold text-red-500 leading-none">
                <?= htmlspecialchars($appointment['priority_number']) ?>
            </div>
        </div>

        <!-- Details -->
        <div class="px-8 py-7 space-y-5 text-base">

            <div class="grid grid-cols-2 gap-y-4 gap-x-4">
                <div class="font-semibold text-slate-600">Patient Name</div>
                <div class="text-slate-900"><?= htmlspecialchars($appointment['patient_name']) ?></div>

                <div class="font-semibold text-slate-600">Health Worker</div>
                <div class="text-slate-900"><?= htmlspecialchars($appointment['staff_name']) ?></div>

                <?php if (!empty($appointment['specialization'])): ?>
                <div class="font-semibold text-slate-600">Specialization</div>
                <div class="text-slate-900"><?= htmlspecialchars($appointment['specialization']) ?></div>
                <?php endif; ?>

                <div class="font-semibold text-slate-600">Appointment Date</div>
                <div class="text-slate-900">
                    <?= date('F j, Y', strtotime($appointment['date'])) ?>
                </div>

                <div class="font-semibold text-slate-600">Time Schedule</div>
                <div class="text-slate-900">
                    <?= date('h:i A', strtotime($appointment['start_time'])) ?>
                    –
                    <?= date('h:i A', strtotime($appointment['end_time'])) ?>
                </div>
            </div>

            <?php if (!empty($appointment['health_concerns'])): ?>
            <div class="pt-5 border-t">
                <p class="font-semibold text-slate-600 mb-2">
                    Health Concerns
                </p>
                <p class="text-slate-800 leading-relaxed">
                    <?= htmlspecialchars($appointment['health_concerns']) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="bg-slate-50 px-8 py-5 text-center text-sm text-slate-600 border-t">
            <p>Generated on <?= date('F j, Y g:i A') ?></p>
            <p class="mt-2 font-bold text-blue-700 text-base">
                Please arrive at least 15 minutes early
            </p>
            <p class="mt-1">Bring a valid ID and this ticket</p>
        </div>
    </div>

    <!-- Tip -->
    <div class="no-print text-center text-sm text-slate-600 mt-5">
        💡 You may print, save as PDF, or take a screenshot
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