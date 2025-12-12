<?php
// staff/test_analytics.php
echo "<h1>Testing Analytics Connection</h1>";

// Test 1: Check if file exists
echo "<h2>Test 1: File Existence</h2>";
if (file_exists(__DIR__ . '/get_analytics_data.php')) {
    echo "✅ get_analytics_data.php exists<br>";
} else {
    echo "❌ get_analytics_data.php NOT found<br>";
}

// Test 2: Check if we can read the file
echo "<h2>Test 2: File Permissions</h2>";
if (is_readable(__DIR__ . '/get_analytics_data.php')) {
    echo "✅ File is readable<br>";
} else {
    echo "❌ File is NOT readable<br>";
}

// Test 3: Check PHP version
echo "<h2>Test 3: PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Test 4: Test database connection
echo "<h2>Test 4: Database Connection</h2>";
try {
    require_once __DIR__ . '/../includes/db.php';
    echo "✅ Database connection successful<br>";
    
    // Try a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Database query successful<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 5: Test AJAX endpoint directly
echo "<h2>Test 5: Direct AJAX Endpoint Test</h2>";
echo '<a href="get_analytics_data.php" target="_blank">Click to test get_analytics_data.php directly</a>';

// Test 6: Check session
echo "<h2>Test 6: Session Status</h2>";
echo "Session status: " . session_status() . "<br>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Session is active<br>";
} else {
    echo "❌ Session is NOT active<br>";
}
?>