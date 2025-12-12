<?php
// staff/get_analytics_data.php
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Set content type first
header('Content-Type: application/json');

// Simple error handling
try {
    // Check if user is logged in
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
        throw new Exception('Access denied. Please login as staff.');
    }

    // Include database connection
    require_once __DIR__ . '/../includes/db.php';
    
    // Get staff ID from session
    $staffId = $_SESSION['user']['id'];
    
    // 1. Get total patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient'");
    $totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 2. Get approved patients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sitio1_users WHERE role = 'patient' AND approved = TRUE");
    $approvedPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 3. Get regular patients
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as total 
        FROM sitio1_users u 
        JOIN user_appointments ua ON u.id = ua.user_id 
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE u.role = 'patient' AND a.staff_id = ?
    ");
    $stmt->execute([$staffId]);
    $regularPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 4. Get total appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ?
    ");
    $stmt->execute([$staffId]);
    $totalAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 5. Get appointment status distribution
    $stmt = $pdo->prepare("
        SELECT ua.status, COUNT(*) as count 
        FROM user_appointments ua
        JOIN sitio1_appointments a ON ua.appointment_id = a.id
        WHERE a.staff_id = ?
        GROUP BY ua.status
    ");
    $stmt->execute([$staffId]);
    $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointment status data
    $statusLabels = [];
    $statusCounts = [];
    $statusColors = [];
    
    foreach ($statusData as $item) {
        $statusLabels[] = ucfirst($item['status']);
        $statusCounts[] = $item['count'];
        
        // Assign colors
        switch ($item['status']) {
            case 'approved': $statusColors[] = '#3B82F6'; break;
            case 'completed': $statusColors[] = '#10B981'; break;
            case 'pending': $statusColors[] = '#F59E0B'; break;
            case 'rejected': $statusColors[] = '#EF4444'; break;
            case 'cancelled': $statusColors[] = '#8B5CF6'; break;
            default: $statusColors[] = '#6B7280';
        }
    }
    
    // Get last 6 months data for trends
    $currentMonth = date('Y-m');
    $months = [];
    $monthlyData = [];
    $patientRegData = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $months[] = date('M Y', strtotime($month . '-01'));
        
        // Get appointments for this month
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM user_appointments ua
            JOIN sitio1_appointments a ON ua.appointment_id = a.id
            WHERE a.staff_id = ? AND DATE_FORMAT(ua.created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$staffId, $month]);
        $monthlyData[] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Get patient registrations for this month
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM sitio1_users 
            WHERE role = 'patient' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'
        ");
        $patientRegData[] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    
    // Calculate completion rate
    $completed = 0;
    foreach ($statusData as $item) {
        if ($item['status'] === 'completed') {
            $completed = $item['count'];
            break;
        }
    }
    $completionRate = $totalAppointments > 0 ? round(($completed / $totalAppointments) * 100) : 0;
    
    // Build response
    $response = [
        'success' => true,
        'cards' => [
            [
                'label' => 'Total Patients',
                'value' => $totalPatients,
                'icon' => 'fas fa-users',
                'color' => 'text-blue-500'
            ],
            [
                'label' => 'Approved Patients',
                'value' => $approvedPatients,
                'icon' => 'fas fa-user-check',
                'color' => 'text-green-500'
            ],
            [
                'label' => 'Your Patients',
                'value' => $regularPatients,
                'icon' => 'fas fa-user-friends',
                'color' => 'text-purple-500'
            ],
            [
                'label' => 'Your Appointments',
                'value' => $totalAppointments,
                'icon' => 'fas fa-calendar-alt',
                'color' => 'text-orange-500'
            ]
        ],
        'charts' => [
            'appointmentStatus' => [
                'type' => 'pie',
                'data' => [
                    'labels' => $statusLabels,
                    'datasets' => [[
                        'data' => $statusCounts,
                        'backgroundColor' => $statusColors,
                        'borderWidth' => 2,
                        'borderColor' => '#fff'
                    ]]
                ]
            ],
            'monthlyTrend' => [
                'type' => 'line',
                'data' => [
                    'labels' => $months,
                    'datasets' => [[
                        'label' => 'Appointments',
                        'data' => $monthlyData,
                        'borderColor' => '#3B82F6',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    ]]
                ]
            ],
            'patientRegistration' => [
                'type' => 'line',
                'data' => [
                    'labels' => $months,
                    'datasets' => [[
                        'label' => 'New Patients',
                        'data' => $patientRegData,
                        'borderColor' => '#10B981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'borderWidth' => 2,
                        'fill' => true,
                        'tension' => 0.4
                    ]]
                ]
            ],
            'completionRate' => [
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['Completed', 'Remaining'],
                    'datasets' => [[
                        'data' => [$completionRate, 100 - $completionRate],
                        'backgroundColor' => ['#10B981', '#E5E7EB']
                    ]]
                ]
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Return error in JSON format
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}