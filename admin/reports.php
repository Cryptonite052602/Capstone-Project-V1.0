<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Initialize variables
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$reportType = $_GET['report_type'] ?? 'patients';
$searchQuery = $_GET['search'] ?? '';
$showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';

// Get report data
$reportData = [];
$chartData = [];
$patientsData = [];
$totalPatients = 0;

try {
    if ($reportType === 'patients') {
        // Get patient statistics for the chart
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM sitio1_patients 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate . ' 23:59:59']);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total number of patients for the period
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sitio1_patients WHERE created_at BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate . ' 23:59:59']);
        $totalPatients = $stmt->fetchColumn();
        
        // Prepare chart data
        foreach ($reportData as $row) {
            $chartData['labels'][] = date('M d', strtotime($row['date']));
            $chartData['data'][] = $row['count'];
        }
        
        // Get detailed patient data
        $patientQuery = "SELECT 
                            id, 
                            full_name, 
                            age, 
                            address, 
                            disease, 
                            contact as contact_number, 
                            last_checkup, 
                            medical_history, 
                            created_at 
                         FROM sitio1_patients
                         WHERE created_at BETWEEN ? AND ?";
        $params = [$startDate, $endDate . ' 23:59:59'];
        
        if (!empty($searchQuery)) {
            $patientQuery .= " AND (full_name LIKE ? OR address LIKE ? OR contact LIKE ? OR disease LIKE ?)";
            $searchParam = "%$searchQuery%";
            $params = array_merge($params, array_fill(0, 4, $searchParam));
        }
        
        $patientQuery .= " ORDER BY created_at DESC";
        
        if (!$showAll) {
            $patientQuery .= " LIMIT 5";
        }
        
        $stmt = $pdo->prepare($patientQuery);
        $stmt->execute($params);
        $patientsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($reportType === 'consultations') {
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM sitio1_consultations 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate . ' 23:59:59']);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reportData as $row) {
            $chartData['labels'][] = date('M d', strtotime($row['date']));
            $chartData['data'][] = $row['count'];
        }
    } elseif ($reportType === 'appointments') {
        $stmt = $pdo->prepare("
            SELECT DATE(ua.created_at) as date, 
                   COUNT(*) as total,
                   SUM(CASE WHEN ua.status = 'pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN ua.status = 'approved' THEN 1 ELSE 0 END) as approved,
                   SUM(CASE WHEN ua.status = 'completed' THEN 1 ELSE 0 END) as completed,
                   SUM(CASE WHEN ua.status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM user_appointments ua
            WHERE ua.created_at BETWEEN ? AND ?
            GROUP BY DATE(ua.created_at)
            ORDER BY date
        ");
        $stmt->execute([$startDate, $endDate . ' 23:59:59']);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reportData as $row) {
            $chartData['labels'][] = date('M d', strtotime($row['date']));
            $chartData['pending'][] = $row['pending'];
            $chartData['approved'][] = $row['approved'];
            $chartData['completed'][] = $row['completed'];
            $chartData['rejected'][] = $row['rejected'];
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error generating report: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Health Reports Dashboard</h1>
            <p class="text-gray-600">View and analyze community health data</p>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500"><?= date('F j, Y') ?></span>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <span><?= $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Simplified Report Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Generate Report</h2>
            <p class="text-sm text-gray-600">Select your report criteria below</p>
        </div>
        
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="report_type" class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                    <select id="report_type" name="report_type" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="patients" <?= $reportType === 'patients' ? 'selected' : '' ?>>Patient Registrations</option>
                        <option value="consultations" <?= $reportType === 'consultations' ? 'selected' : '' ?>>Health Consultations</option>
                        <option value="appointments" <?= $reportType === 'appointments' ? 'selected' : '' ?>>Appointment Status</option>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" id="start_date" name="start_date" value="<?= $startDate ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" id="end_date" name="end_date" value="<?= $endDate ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md shadow-sm transition duration-150 ease-in-out flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                        </svg>
                        Generate
                    </button>
                </div>
            </div>
            
            <?php if ($reportType === 'patients'): ?>
                <div class="relative max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                           placeholder="Search patients by name, address, or condition" 
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Report Summary -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    <?= ucfirst($reportType) ?> Report Summary
                </h2>
                <p class="text-sm text-gray-600">
                    <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?>
                    <?php if ($reportType === 'patients'): ?>
                        • <?= number_format($totalPatients) ?> total patients
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Export Options -->
            <div class="mt-2 md:mt-0">
                <div class="inline-flex rounded-md shadow-sm">
                    <button type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium rounded-l-md text-gray-700 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export
                    </button>
                    <div x-data="{ open: false }" class="relative -ml-px">
                        <button @click="open = !open" type="button" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <span class="sr-only">Open options</span>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 -mr-1 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10">
                            <div class="py-1">
                                <?php if ($reportType === 'patients'): ?>
                                    <a href="/community-health-tracker/api/export_patients.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= urlencode($searchQuery) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">CSV Format</a>
                                    <a href="/community-health-tracker/api/export_patients_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= urlencode($searchQuery) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">PDF Document</a>
                                    <a href="/community-health-tracker/api/export_patients_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&search=<?= urlencode($searchQuery) ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">Excel Spreadsheet</a>
                                <?php elseif ($reportType === 'consultations'): ?>
                                    <a href="/community-health-tracker/api/export_consultations_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">PDF Document</a>
                                    <a href="/community-health-tracker/api/export_consultations_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">Excel Spreadsheet</a>
                                <?php elseif ($reportType === 'appointments'): ?>
                                    <a href="/community-health-tracker/api/export_appointments_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">PDF Document</a>
                                    <a href="/community-health-tracker/api/export_appointments_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">Excel Spreadsheet</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <?php if ($reportType === 'patients'): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-blue-100 text-blue-600 mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Patients</p>
                        <p class="text-2xl font-semibold text-gray-800"><?= number_format($totalPatients) ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Reporting Period</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?= date('M j', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-purple-50 border border-purple-100 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="p-2 rounded-full bg-purple-100 text-purple-600 mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Days Covered</p>
                        <p class="text-2xl font-semibold text-gray-800"><?= round((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) + 1 ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Chart Visualization -->
        <?php if (!empty($chartData)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Trend Visualization</h3>
            <div class="h-80">
                <canvas id="reportChart" height="320"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data Display -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($reportData) && $reportType !== 'patients'): ?>
                <div class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No data available</h3>
                    <p class="mt-1 text-gray-500">Try adjusting your filters or select a different date range.</p>
                </div>
            <?php else: ?>
                <?php if ($reportType === 'patients'): ?>
                    <!-- Patient Data Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health Info</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($patientsData as $patient): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-blue-600">
                                                    <a href="/community-health-tracker/patients/view.php?id=<?= $patient['id'] ?>" class="hover:underline">
                                                        <?= htmlspecialchars($patient['full_name']) ?>
                                                    </a>
                                                </div>
                                                <div class="text-sm text-gray-500">ID: <?= $patient['id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= $patient['age'] ?> years</div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($patient['address']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($patient['disease']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= $patient['last_checkup'] ? 'Last checkup: ' . date('M j, Y', strtotime($patient['last_checkup'])) : 'No checkup recorded' ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($patient['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!$showAll && empty($searchQuery)): ?>
                        <div class="px-6 py-4 bg-gray-50 text-center">
                            <a href="?report_type=patients&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&show_all=1" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Load All Patients
                            </a>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($reportType === 'consultations' || $reportType === 'appointments'): ?>
                    <!-- Consultations/Appointments Table -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <?php if ($reportType === 'appointments'): ?>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($reportData as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= date('M j, Y', strtotime($row['date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $row['count'] ?? $row['total'] ?>
                                    </td>
                                    <?php if ($reportType === 'appointments'): ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                <?= $row['pending'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?= $row['approved'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= $row['completed'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                <?= $row['rejected'] ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reportChart')?.getContext('2d');
    const reportType = '<?= $reportType ?>';
    
    if (!ctx) return;
    
    if (reportType === 'appointments') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartData['labels'] ?? []) ?>,
                datasets: [
                    {
                        label: 'Pending',
                        data: <?= json_encode($chartData['pending'] ?? []) ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Approved',
                        data: <?= json_encode($chartData['approved'] ?? []) ?>,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Completed',
                        data: <?= json_encode($chartData['completed'] ?? []) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Rejected',
                        data: <?= json_encode($chartData['rejected'] ?? []) ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    } else if (reportType === 'patients' || reportType === 'consultations') {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartData['labels'] ?? []) ?>,
                datasets: [{
                    label: reportType === 'patients' ? 'New Patients' : 'Consultations',
                    data: <?= json_encode($chartData['data'] ?? []) ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true,
                    pointBackgroundColor: 'rgba(13, 110, 253, 1)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return (reportType === 'patients' ? 'Patients' : 'Consultations') + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

