<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Initialize variables with simple defaults
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'patients';
$sitioFilter = $_GET['sitio'] ?? 'all';
$serviceTypeFilter = $_GET['service_type'] ?? 'all';

// Get sitios for filter
$sitios = [];
$stmt = $pdo->query("SELECT DISTINCT sitio FROM sitio1_patients WHERE sitio IS NOT NULL AND sitio != '' ORDER BY sitio");
$sitios = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container mx-auto px-4 py-8 max-w-6xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Export Reports</h1>
        <p class="text-gray-600">Generate and download reports in Excel or PDF format</p>
    </div>

    <!-- Simple Report Selection -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form method="GET" action="" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Report Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="report_type" value="patients" 
                                   <?= $reportType === 'patients' ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Patient Records</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="report_type" value="appointments"
                                   <?= $reportType === 'appointments' ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-gray-700">Appointments</span>
                        </label>
                    </div>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <input type="date" name="start_date" value="<?= $startDate ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <input type="date" name="end_date" value="<?= $endDate ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div id="patient-filters" style="<?= $reportType !== 'patients' ? 'display: none;' : '' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Sitio</label>
                <select name="sitio" class="w-full md:w-1/3 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Sitios</option>
                    <?php foreach ($sitios as $sitio): ?>
                        <option value="<?= htmlspecialchars($sitio) ?>" <?= $sitioFilter === $sitio ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sitio) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="appointment-filters" style="<?= $reportType !== 'appointments' ? 'display: none;' : '' ?>">
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Service Type</label>
                <select name="service_type" class="w-full md:w-1/3 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Services</option>
                    <option value="General Checkup" <?= $serviceTypeFilter === 'General Checkup' ? 'selected' : '' ?>>General Checkup</option>
                    <option value="Vaccination" <?= $serviceTypeFilter === 'Vaccination' ? 'selected' : '' ?>>Vaccination</option>
                    <option value="Dental" <?= $serviceTypeFilter === 'Dental' ? 'selected' : '' ?>>Dental</option>
                    <option value="Blood Test" <?= $serviceTypeFilter === 'Blood Test' ? 'selected' : '' ?>>Blood Test</option>
                </select>
            </div>

            <!-- Generate Button -->
            <div class="pt-4">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md shadow-sm transition duration-150 ease-in-out">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Export Options -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Export Options</h2>
        <p class="text-gray-600 mb-6">Click on any format below to download the report</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Excel Export -->
            <div class="border border-gray-200 rounded-lg p-5 hover:border-blue-300 hover:shadow-md transition duration-150">
                <div class="flex items-center mb-3">
                    <div class="p-2 rounded-full bg-green-100 text-green-600 mr-3">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">Excel Spreadsheet</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Download data in Excel format for analysis and sharing</p>
                <?php if ($reportType === 'patients'): ?>
                    <a href="/community-health-tracker/api/export_patients_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&sitio=<?= urlencode($sitioFilter) ?>"
                       class="inline-flex items-center justify-center w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Download Patient Excel
                    </a>
                <?php else: ?>
                    <a href="/community-health-tracker/api/export_appointments_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&service_type=<?= urlencode($serviceTypeFilter) ?>"
                       class="inline-flex items-center justify-center w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                        Download Appointments Excel
                    </a>
                <?php endif; ?>
            </div>

            <!-- PDF Export -->
            <div class="border border-gray-200 rounded-lg p-5 hover:border-red-300 hover:shadow-md transition duration-150">
                <div class="flex items-center mb-3">
                    <div class="p-2 rounded-full bg-red-100 text-red-600 mr-3">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">PDF Document</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Generate professional PDF report for printing and archiving</p>
                <?php if ($reportType === 'patients'): ?>
                    <a href="/community-health-tracker/api/export_patients_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&sitio=<?= urlencode($sitioFilter) ?>"
                       class="inline-flex items-center justify-center w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        Download Patient PDF
                    </a>
                <?php else: ?>
                    <a href="/community-health-tracker/api/export_appointments_pdf.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&service_type=<?= urlencode($serviceTypeFilter) ?>"
                       class="inline-flex items-center justify-center w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md transition duration-150">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        Download Appointments PDF
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <h3 class="text-sm font-medium text-gray-700 mb-3">Report Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-600">Report Type</p>
                    <p class="text-lg font-semibold text-gray-800"><?= $reportType === 'patients' ? 'Patient Records' : 'Appointments' ?></p>
                </div>
                <div class="bg-green-50 border border-green-100 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-600">Date Range</p>
                    <p class="text-lg font-semibold text-gray-800">
                        <?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>
                    </p>
                </div>
                <div class="bg-purple-50 border border-purple-100 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-600">Filter Applied</p>
                    <p class="text-lg font-semibold text-gray-800">
                        <?php 
                        if ($reportType === 'patients') {
                            echo $sitioFilter === 'all' ? 'All Sitios' : $sitioFilter;
                        } else {
                            echo $serviceTypeFilter === 'all' ? 'All Services' : $serviceTypeFilter;
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide filters based on report type
    const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');
    
    function toggleFilters() {
        const selectedReport = document.querySelector('input[name="report_type"]:checked').value;
        document.getElementById('patient-filters').style.display = selectedReport === 'patients' ? 'block' : 'none';
        document.getElementById('appointment-filters').style.display = selectedReport === 'appointments' ? 'block' : 'none';
    }
    
    reportTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleFilters);
    });
    
    // Set default end date to today
    const endDateInput = document.querySelector('input[name="end_date"]');
    if (endDateInput && !endDateInput.value) {
        const today = new Date().toISOString().split('T')[0];
        endDateInput.value = today;
    }
});
</script>