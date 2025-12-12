<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if authorized (admin or inventory manager)
if (!isAdmin() && !hasPermission('manage_inventory')) {
    header('Location: /community-health-tracker/auth/login.php');
    exit;
}

// Get user ID from session or default to 1 if not set
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Check and create required tables if they don't exist
function setupInventoryTables($pdo) {
    try {
        // Check if barangay_inventory table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'barangay_inventory'");
        if ($tableCheck->rowCount() == 0) {
            // Create barangay_inventory table
            $pdo->exec("
                CREATE TABLE barangay_inventory (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    quantity INT NOT NULL DEFAULT 0,
                    unit VARCHAR(50) NOT NULL,
                    expiry_date DATE NULL,
                    supplier VARCHAR(255) NOT NULL,
                    reorder_level INT NOT NULL DEFAULT 10,
                    created_by INT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_category (category),
                    INDEX idx_quantity (quantity),
                    INDEX idx_expiry (expiry_date)
                )
            ");
            
            // Insert sample data
            $pdo->exec("
                INSERT INTO barangay_inventory 
                (name, category, quantity, unit, supplier, reorder_level, created_by) VALUES
                ('Paracetamol 500mg', 'medicine', 100, 'tablet', 'Mercury Drug', 20, 1),
                ('Amoxicillin 500mg', 'medicine', 50, 'capsule', 'Generics Pharmacy', 15, 1),
                ('BCG Vaccine', 'vaccine', 25, 'vial', 'DOH', 10, 1),
                ('Face Mask', 'supplies', 500, 'piece', 'Medical Supplies Inc', 100, 1),
                ('Cotton Balls', 'supplies', 10, 'pack', 'Medical Supplies Inc', 5, 1)
            ");
        }
        
        // Check if inventory_usage table exists
        $usageCheck = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
        if ($usageCheck->rowCount() == 0) {
            // Create inventory_usage table
            $pdo->exec("
                CREATE TABLE inventory_usage (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    item_id INT NOT NULL,
                    quantity INT NOT NULL,
                    patient_id INT NULL,
                    service_type VARCHAR(50) NOT NULL,
                    notes TEXT NULL,
                    created_by INT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Check if inventory_logs table exists
        $logsCheck = $pdo->query("SHOW TABLES LIKE 'inventory_logs'");
        if ($logsCheck->rowCount() == 0) {
            // Create inventory_logs table
            $pdo->exec("
                CREATE TABLE inventory_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    action VARCHAR(20) NOT NULL,
                    item_id INT NOT NULL,
                    quantity INT NOT NULL,
                    batch_number VARCHAR(100) NULL,
                    expiry_date DATE NULL,
                    created_by INT NOT NULL DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// Setup tables on page load
setupInventoryTables($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = trim($_POST['name']);
        $category = $_POST['category'];
        $quantity = (int)$_POST['quantity'];
        $unit = $_POST['unit'];
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $supplier = trim($_POST['supplier']);
        $reorderLevel = (int)$_POST['reorder_level'];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO barangay_inventory 
                (name, category, quantity, unit, expiry_date, supplier, reorder_level, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $category, $quantity, $unit, $expiryDate, $supplier, $reorderLevel, $userId
            ]);
            
            // Log the addition
            logInventoryAction($pdo, 'ADD', $pdo->lastInsertId(), $quantity, $userId);
            
            $_SESSION['message'] = "Successfully Added Item!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Database error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['update_item'])) {
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        $reorderLevel = (int)$_POST['reorder_level'];
        
        try {
            // Get current quantity for logging
            $currentStmt = $pdo->prepare("SELECT quantity FROM barangay_inventory WHERE id = ?");
            $currentStmt->execute([$itemId]);
            $currentItem = $currentStmt->fetch(PDO::FETCH_ASSOC);
            $oldQuantity = $currentItem['quantity'];
            
            $stmt = $pdo->prepare("
                UPDATE barangay_inventory 
                SET quantity = ?, reorder_level = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $reorderLevel, $itemId]);
            
            // Log the update if quantity changed
            if ($oldQuantity != $quantity) {
                $change = $quantity - $oldQuantity;
                logInventoryAction($pdo, 'UPDATE', $itemId, $change, $userId);
            }
            
            $_SESSION['message'] = "Item updated successfully!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Database error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['use_item'])) {
        $itemId = (int)$_POST['item_id'];
        $quantityUsed = (int)$_POST['quantity_used'];
        $patientId = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $serviceType = $_POST['service_type'];
        $notes = trim($_POST['notes']);
        
        try {
            // Check if enough stock
            $checkStmt = $pdo->prepare("SELECT quantity, name FROM barangay_inventory WHERE id = ?");
            $checkStmt->execute([$itemId]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item['quantity'] < $quantityUsed) {
                $_SESSION['message'] = "Insufficient stock! Only " . $item['quantity'] . " " . $item['name'] . " available.";
                $_SESSION['message_type'] = 'error';
            } else {
                // Deduct from inventory
                $updateStmt = $pdo->prepare("
                    UPDATE barangay_inventory 
                    SET quantity = quantity - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$quantityUsed, $itemId]);
                
                // Log the usage
                logInventoryUsage($pdo, $itemId, $quantityUsed, $patientId, $serviceType, $notes, $userId);
                
                $_SESSION['message'] = "Successfully Recorded Item Usage!";
                $_SESSION['message_type'] = 'success';
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Database error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['restock_item'])) {
        $itemId = (int)$_POST['item_id'];
        $quantityAdded = (int)$_POST['quantity_added'];
        $supplier = trim($_POST['supplier']);
        $batchNumber = trim($_POST['batch_number']);
        $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        try {
            // Add to inventory
            $updateStmt = $pdo->prepare("
                UPDATE barangay_inventory 
                SET quantity = quantity + ?, supplier = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantityAdded, $supplier, $itemId]);
            
            // Log the restock
            logInventoryAction($pdo, 'RESTOCK', $itemId, $quantityAdded, $userId, $batchNumber, $expiryDate);
            
            $_SESSION['message'] = "Successfully Restocked Item!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Database error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['generate_report'])) {
        // Generate inventory report
        $reportType = $_POST['report_type'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        // Generate report based on type
        switch($reportType) {
            case 'monthly':
                $message = "Monthly Inventory Report generated successfully for period: " . date('F j, Y', strtotime($startDate)) . " to " . date('F j, Y', strtotime($endDate));
                break;
            case 'quarterly':
                $message = "Quarterly Inventory Report generated successfully for period: " . date('F j, Y', strtotime($startDate)) . " to " . date('F j, Y', strtotime($endDate));
                break;
            case 'low_stock':
                $message = "Low Stock Report generated successfully. Items below reorder level have been identified.";
                break;
            case 'usage':
                $message = "Usage Report generated successfully showing all item usage from " . date('F j, Y', strtotime($startDate)) . " to " . date('F j, Y', strtotime($endDate));
                break;
            case 'expiry':
                $message = "Expiry Report generated successfully showing all items expiring within the selected period.";
                break;
            default:
                $message = "Report generated successfully for " . $reportType . " from " . $startDate . " to " . $endDate;
        }
        
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
        
        header("Location: inventory_medicine.php");
        exit;
    }
}

// Function to log inventory actions
function logInventoryAction($pdo, $action, $itemId, $quantity, $userId, $batchNumber = null, $expiryDate = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_logs 
            (action, item_id, quantity, batch_number, expiry_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$action, $itemId, $quantity, $batchNumber, $expiryDate, $userId]);
    } catch (PDOException $e) {
        // Silently fail if logging table doesn't exist
        error_log("Failed to log inventory action: " . $e->getMessage());
    }
}

// Function to log inventory usage
function logInventoryUsage($pdo, $itemId, $quantity, $patientId, $serviceType, $notes, $userId) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_usage 
            (item_id, quantity, patient_id, service_type, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$itemId, $quantity, $patientId, $serviceType, $notes, $userId]);
    } catch (PDOException $e) {
        // Silently fail if logging table doesn't exist
        error_log("Failed to log inventory usage: " . $e->getMessage());
    }
}

// Get inventory items
try {
    $inventoryQuery = $pdo->prepare("
        SELECT * FROM barangay_inventory 
        ORDER BY category, name
    ");
    $inventoryQuery->execute();
    $inventoryItems = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventoryItems = [];
    if (!isset($_SESSION['message'])) {
        $_SESSION['message'] = "Failed to load inventory: " . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Get low stock items (below reorder level)
try {
    $lowStockQuery = $pdo->prepare("
        SELECT * FROM barangay_inventory 
        WHERE quantity <= reorder_level
        ORDER BY quantity ASC
    ");
    $lowStockQuery->execute();
    $lowStockItems = $lowStockQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lowStockItems = [];
}

// Get recent usage logs
try {
    $usageQuery = $pdo->prepare("
        SELECT iu.*, bi.name as item_name
        FROM inventory_usage iu
        LEFT JOIN barangay_inventory bi ON iu.item_id = bi.id
        ORDER BY iu.created_at DESC
        LIMIT 10
    ");
    $usageQuery->execute();
    $recentUsage = $usageQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentUsage = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Barangay Luz Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ffffff;
            --gray: #95a5a6;
            --border: #e2e8f0;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow: 0 4px 6px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px 10px 0 0;
        }

        .card-body {
            padding: 1.5rem;
            background: white;
            border-radius: 0 0 10px 10px;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: white;
            color: #2c3e50;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: white;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 1.75rem;
            font-weight: 500;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: white;
            color: var(--primary);
            border: 2px solid rgba(52, 152, 219, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: #f0f9ff;
            border-color: rgba(52, 152, 219, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-success {
            background: white;
            color: var(--success);
            border: 2px solid rgba(46, 204, 113, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-success:hover {
            background: #f0fdf4;
            border-color: rgba(46, 204, 113, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-warning {
            background: white;
            color: var(--warning);
            border: 2px solid rgba(243, 156, 18, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-warning:hover {
            background: #fef3c7;
            border-color: rgba(243, 156, 18, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-danger {
            background: white;
            color: var(--danger);
            border: 2px solid rgba(231, 76, 60, 1);
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            background: #fef2f2;
            border-color: rgba(231, 76, 60, 0.8);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background: white;
            color: var(--secondary);
            border: 2px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: var(--gray);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-sm {
            padding: 0.625rem 1.25rem;
            font-size: 0.75rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
            background: white;
        }

        .badge-low-stock {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .badge-expired {
            background: #fef3c7;
            color: #d97706;
            border-color: #fde68a;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
        }

        .category-medicine {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .category-vaccine {
            background: #f0f9ff;
            color: #0c4a6e;
            border-color: #bae6fd;
        }

        .category-supplies {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .category-family-planning {
            background: #fdf2f8;
            color: #9d174d;
            border-color: #fbcfe8;
        }

        dialog::backdrop {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray);
            font-weight: 500;
        }

        .inventory-item {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .inventory-item:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
            background: #f8fafc;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .inventory-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .inventory-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .inventory-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }

        /* Message styling */
        .message-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #10b981;
            color: #065f46;
        }

        .message-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #ef4444;
            color: #7f1d1d;
        }

        /* Action buttons grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .action-add .action-icon {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            color: var(--primary);
            border: 2px solid #bae6fd;
        }

        .action-use .action-icon {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: var(--success);
            border: 2px solid #bbf7d0;
        }

        .action-restock .action-icon {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: var(--warning);
            border: 2px solid #fcd34d;
        }

        .action-report .action-icon {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--secondary);
            border: 2px solid #e2e8f0;
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .action-description {
            font-size: 0.875rem;
            color: var(--gray);
        }

        /* Low stock alert */
        .low-stock-alert {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 2px solid #fecaca;
            border-radius: 10px;
        }

        /* Inventory item header */
        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .inventory-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .item-avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .medicine-avatar {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .vaccine-avatar {
            background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);
        }

        .supplies-avatar {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        .family-planning-avatar {
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        }

        .inventory-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Status indicators */
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-normal {
            background: #d1fae5;
            color: #059669;
        }

        .status-low {
            background: #fef3c7;
            color: #d97706;
        }

        .status-critical {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Table styling for usage logs */
        .usage-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .usage-table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .usage-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--secondary);
            border-bottom: 2px solid #e2e8f0;
        }

        .usage-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .usage-table tr:hover {
            background: #f8fafc;
        }

        .usage-table tr:last-child td {
            border-bottom: none;
        }

        /* Custom modal styling */
        .custom-modal {
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow-lg);
            border: none;
            padding: 0;
            max-width: 800px;
            width: 90%;
        }

        .custom-modal::backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Form grid layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        /* Search and filter */
        .search-container {
            position: relative;
            flex: 1;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input {
            padding-left: 3rem;
            width: 100%;
        }

        .filter-select {
            min-width: 200px;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8 mt-16">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-secondary mb-2">Inventory Management</h1>
            <p class="text-gray-600">Manage medical supplies, medicines, and vaccines for the health center</p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'message-error' : 'message-success' ?>">
                <i class="fas <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-3 text-xl"></i>
                <span class="font-medium"><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid mb-8">
            <div class="stat-card">
                <div class="stat-value"><?= array_sum(array_column($inventoryItems, 'quantity')) ?></div>
                <div class="stat-label">Total Items in Stock</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($lowStockItems) ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?= count(array_filter($inventoryItems, function($item) {
                        return $item['category'] === 'medicine';
                    })) ?>
                </div>
                <div class="stat-label">Medicine Types</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?= count(array_filter($inventoryItems, function($item) {
                        return $item['category'] === 'vaccine';
                    })) ?>
                </div>
                <div class="stat-label">Vaccine Types</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-grid mb-8">
            <div class="action-card action-add" onclick="document.getElementById('add-item-modal').showModal()">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-title">Add New Item</div>
                <div class="action-description">Register new medicine, vaccine or supplies</div>
            </div>
            
            <div class="action-card action-use" onclick="document.getElementById('use-item-modal').showModal()">
                <div class="action-icon">
                    <i class="fas fa-syringe"></i>
                </div>
                <div class="action-title">Record Usage</div>
                <div class="action-description">Deduct items used for patient services</div>
            </div>
            
            <div class="action-card action-restock" onclick="document.getElementById('restock-modal').showModal()">
                <div class="action-icon">
                    <i class="fas fa-truck-loading"></i>
                </div>
                <div class="action-title">Restock Items</div>
                <div class="action-description">Add more stock to existing items</div>
            </div>
            
            <div class="action-card action-report" onclick="document.getElementById('report-modal').showModal()">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-title">Generate Report</div>
                <div class="action-description">Create inventory reports and analytics</div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockItems)): ?>
        <div class="card low-stock-alert mb-8">
            <div class="card-header">
                <div class="flex items-center">
                    <div class="p-3 rounded-lg bg-red-50 mr-4">
                        <i class="fas fa-exclamation-triangle text-xl text-red-500"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-secondary">Low Stock Alert</h2>
                        <p class="text-sm text-gray-600"><?= count($lowStockItems) ?> items need replenishment</p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($lowStockItems as $item): ?>
                        <div class="inventory-item">
                            <div class="inventory-header">
                                <div class="inventory-info">
                                    <div class="item-avatar <?= $item['category'] ?>-avatar">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-secondary"><?= htmlspecialchars($item['name']) ?></h3>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="category-badge category-<?= $item['category'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $item['category'])) ?>
                                            </span>
                                            <span class="status-critical">
                                                <i class="fas fa-exclamation-circle"></i> LOW STOCK
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button onclick="openRestockModal(<?= $item['id'] ?>)" 
                                    class="btn btn-danger btn-sm">
                                    <i class="fas fa-plus mr-2"></i>Restock
                                </button>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Current Stock</p>
                                    <p class="font-medium text-red-600"><?= $item['quantity'] ?> <?= $item['unit'] ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Reorder Level</p>
                                    <p class="font-medium text-gray-800"><?= $item['reorder_level'] ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Inventory -->
        <div class="card mb-8">
            <div class="card-header">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-blue-50 mr-4">
                            <i class="fas fa-boxes text-xl" style="color: var(--primary);"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-secondary">Current Inventory</h2>
                            <p class="text-sm text-gray-600">All medical items in stock</p>
                        </div>
                    </div>
                    <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full font-medium">
                        <?= count($inventoryItems) ?> items
                    </span>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="p-4 border-b border-gray-200">
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInventory" placeholder="Search items by name..." 
                            class="form-control search-input">
                    </div>
                    <div>
                        <select id="categoryFilter" class="form-control filter-select">
                            <option value="">All Categories</option>
                            <option value="medicine">Medicines</option>
                            <option value="vaccine">Vaccines</option>
                            <option value="supplies">Medical Supplies</option>
                            <option value="family_planning">Family Planning</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Inventory List -->
            <div class="p-4">
                <?php if (empty($inventoryItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open empty-icon"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Inventory Items Found</h3>
                        <p class="text-gray-600 mb-6">Start by adding your first inventory item</p>
                        <button onclick="document.getElementById('add-item-modal').showModal()" 
                            class="btn btn-primary px-8 py-3">
                            <i class="fas fa-plus mr-2"></i>Add Your First Item
                        </button>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($inventoryItems as $item): 
                            $isLowStock = $item['quantity'] <= $item['reorder_level'];
                            $isExpired = $item['expiry_date'] && strtotime($item['expiry_date']) < time();
                            $statusClass = $isLowStock ? 'status-low' : 'status-normal';
                            $statusIcon = $isLowStock ? 'fa-exclamation-circle' : 'fa-check-circle';
                            $statusText = $isLowStock ? 'LOW STOCK' : 'IN STOCK';
                            
                            if ($isExpired) {
                                $statusClass = 'status-critical';
                                $statusIcon = 'fa-exclamation-triangle';
                                $statusText = 'EXPIRED';
                            }
                        ?>
                            <div class="inventory-item inventory-row" 
                                 data-category="<?= $item['category'] ?>"
                                 data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                                <div class="inventory-header">
                                    <div class="inventory-info">
                                        <div class="item-avatar <?= $item['category'] ?>-avatar">
                                            <?php 
                                            $icon = 'fa-pills';
                                            if ($item['category'] === 'vaccine') $icon = 'fa-syringe';
                                            if ($item['category'] === 'supplies') $icon = 'fa-box-medical';
                                            if ($item['category'] === 'family_planning') $icon = 'fa-heart';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-lg text-secondary"><?= htmlspecialchars($item['name']) ?></h3>
                                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                                <span class="category-badge category-<?= $item['category'] ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $item['category'])) ?>
                                                </span>
                                                <span class="<?= $statusClass ?>">
                                                    <i class="fas <?= $statusIcon ?>"></i> <?= $statusText ?>
                                                </span>
                                                <?php if ($item['expiry_date']): ?>
                                                    <span class="text-sm text-gray-600">
                                                        <i class="fas fa-calendar-alt mr-1"></i> 
                                                        Expires: <?= date('M j, Y', strtotime($item['expiry_date'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="inventory-actions">
                                        <button onclick="openUseModal(<?= $item['id'] ?>)" 
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-syringe mr-2"></i>Use
                                        </button>
                                        <button onclick="openRestockModal(<?= $item['id'] ?>)" 
                                            class="btn btn-warning btn-sm">
                                            <i class="fas fa-plus mr-2"></i>Restock
                                        </button>
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)" 
                                            class="btn btn-secondary btn-sm">
                                            <i class="fas fa-edit mr-2"></i>Edit
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Quantity Available</p>
                                        <p class="font-medium text-lg text-secondary">
                                            <?= $item['quantity'] ?> <?= $item['unit'] ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Reorder Level</p>
                                        <p class="font-medium text-gray-800"><?= $item['reorder_level'] ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Supplier</p>
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($item['supplier']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-1">Last Updated</p>
                                        <p class="font-medium text-gray-800"><?= date('M j, Y g:i A', strtotime($item['updated_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Usage -->
        <div class="card">
            <div class="card-header">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="p-3 rounded-lg bg-green-50 mr-4">
                            <i class="fas fa-history text-xl" style="color: var(--success);"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-secondary">Recent Usage</h2>
                            <p class="text-sm text-gray-600">Last 10 usage records</p>
                        </div>
                    </div>
                    <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full font-medium">
                        <?= count($recentUsage) ?> records
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($recentUsage)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history empty-icon"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Recent Usage Recorded</h3>
                        <p class="text-gray-600">Usage records will appear here once you start using items</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="usage-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Service Type</th>
                                    <th>Patient</th>
                                    <th>Staff ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsage as $usage): ?>
                                    <tr>
                                        <td class="whitespace-nowrap">
                                            <?= date('M j, Y', strtotime($usage['created_at'])) ?>
                                            <br>
                                            <span class="text-sm text-gray-500">
                                                <?= date('g:i A', strtotime($usage['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="font-medium text-secondary"><?= htmlspecialchars($usage['item_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="font-medium text-secondary"><?= $usage['quantity'] ?></span>
                                        </td>
                                        <td>
                                            <span class="category-badge category-medicine">
                                                <?= ucfirst(str_replace('_', ' ', $usage['service_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($usage['patient_id']): ?>
                                                <span class="text-sm bg-blue-50 text-blue-700 px-2 py-1 rounded">
                                                    Patient #<?= $usage['patient_id'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-sm text-gray-500">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-600">Staff #<?= $usage['created_by'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <dialog id="add-item-modal" class="custom-modal">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold">Add New Inventory Item</h3>
                    <p class="text-blue-100 mt-1">Register new medicine, vaccine or supplies</p>
                </div>
                <button onclick="document.getElementById('add-item-modal').close()" 
                    class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form method="post" action="" class="p-6 space-y-6">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name" class="form-label">
                        <i class="fas fa-pills mr-2"></i>Item Name *
                    </label>
                    <input type="text" id="name" name="name" required 
                        class="form-control" placeholder="e.g., Paracetamol 500mg">
                </div>
                
                <div class="form-group">
                    <label for="category" class="form-label">
                        <i class="fas fa-tag mr-2"></i>Category *
                    </label>
                    <select id="category" name="category" required class="form-control">
                        <option value="">Select Category</option>
                        <option value="medicine">Medicine</option>
                        <option value="vaccine">Vaccine</option>
                        <option value="supplies">Medical Supplies</option>
                        <option value="family_planning">Family Planning</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity" class="form-label">
                        <i class="fas fa-boxes mr-2"></i>Initial Quantity *
                    </label>
                    <input type="number" id="quantity" name="quantity" min="0" required 
                        class="form-control" placeholder="e.g., 100">
                </div>
                
                <div class="form-group">
                    <label for="unit" class="form-label">
                        <i class="fas fa-balance-scale mr-2"></i>Unit *
                    </label>
                    <select id="unit" name="unit" required class="form-control">
                        <option value="">Select Unit</option>
                        <option value="tablet">Tablet</option>
                        <option value="capsule">Capsule</option>
                        <option value="bottle">Bottle</option>
                        <option value="vial">Vial</option>
                        <option value="syringe">Syringe</option>
                        <option value="box">Box</option>
                        <option value="pack">Pack</option>
                        <option value="piece">Piece</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reorder_level" class="form-label">
                        <i class="fas fa-exclamation-circle mr-2"></i>Reorder Level *
                    </label>
                    <input type="number" id="reorder_level" name="reorder_level" min="1" required 
                        class="form-control" placeholder="e.g., 20">
                </div>
                
                <div class="form-group">
                    <label for="supplier" class="form-label">
                        <i class="fas fa-truck mr-2"></i>Supplier *
                    </label>
                    <input type="text" id="supplier" name="supplier" required 
                        class="form-control" placeholder="e.g., Mercury Drug">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label for="expiry_date" class="form-label">
                        <i class="fas fa-calendar-alt mr-2"></i>Expiry Date (if applicable)
                    </label>
                    <input type="date" id="expiry_date" name="expiry_date" 
                        class="form-control">
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="document.getElementById('add-item-modal').close()" 
                    class="btn btn-secondary px-6 py-3">
                    Cancel
                </button>
                <button type="submit" name="add_item" 
                    class="btn btn-success px-6 py-3">
                    <i class="fas fa-plus mr-2"></i>Add Item
                </button>
            </div>
        </form>
    </dialog>

    <!-- Use Item Modal -->
    <dialog id="use-item-modal" class="custom-modal">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold">Record Item Usage</h3>
                    <p class="text-blue-100 mt-1">Deduct items from inventory for patient services</p>
                </div>
                <button onclick="document.getElementById('use-item-modal').close()" 
                    class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form method="post" action="" class="p-6 space-y-6">
            <div class="form-group">
                <label for="use_item_id" class="form-label">
                    <i class="fas fa-pills mr-2"></i>Select Item *
                </label>
                <select id="use_item_id" name="item_id" required class="form-control">
                    <option value="">Select Item</option>
                    <?php foreach ($inventoryItems as $item): ?>
                        <option value="<?= $item['id'] ?>" data-stock="<?= $item['quantity'] ?>" data-unit="<?= $item['unit'] ?>">
                            <?= htmlspecialchars($item['name']) ?> (Stock: <?= $item['quantity'] ?> <?= $item['unit'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="quantity_used" class="form-label">
                        <i class="fas fa-boxes mr-2"></i>Quantity Used *
                    </label>
                    <input type="number" id="quantity_used" name="quantity_used" min="1" required 
                        class="form-control" placeholder="e.g., 5">
                    <div id="stock-info" class="text-sm text-gray-500 mt-2"></div>
                </div>
                
                <div class="form-group">
                    <label for="service_type" class="form-label">
                        <i class="fas fa-stethoscope mr-2"></i>Service Type *
                    </label>
                    <select id="service_type" name="service_type" required class="form-control">
                        <option value="">Select Service</option>
                        <option value="consultation">Consultation</option>
                        <option value="immunization">Immunization</option>
                        <option value="prenatal">Prenatal Care</option>
                        <option value="family_planning">Family Planning</option>
                        <option value="treatment">Treatment</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="use_patient_id" class="form-label">
                        <i class="fas fa-user-injured mr-2"></i>Patient (Optional)
                    </label>
                    <input type="text" id="use_patient_id" name="patient_id" 
                        class="form-control" placeholder="Enter Patient ID">
                </div>
                
                <div class="form-group md:col-span-2">
                    <label for="notes" class="form-label">
                        <i class="fas fa-notes-medical mr-2"></i>Notes (Optional)
                    </label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Additional notes or observations"
                        class="form-control"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="document.getElementById('use-item-modal').close()" 
                    class="btn btn-secondary px-6 py-3">
                    Cancel
                </button>
                <button type="submit" name="use_item" 
                    class="btn btn-success px-6 py-3">
                    <i class="fas fa-syringe mr-2"></i>Record Usage
                </button>
            </div>
        </form>
    </dialog>

    <!-- Restock Modal -->
    <dialog id="restock-modal" class="custom-modal">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold">Restock Inventory Item</h3>
                    <p class="text-blue-100 mt-1">Add more stock to existing inventory items</p>
                </div>
                <button onclick="document.getElementById('restock-modal').close()" 
                    class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form method="post" action="" class="p-6 space-y-6">
            <div class="form-group">
                <label for="restock_item_id" class="form-label">
                    <i class="fas fa-pills mr-2"></i>Select Item *
                </label>
                <select id="restock_item_id" name="item_id" required class="form-control">
                    <option value="">Select Item</option>
                    <?php foreach ($inventoryItems as $item): ?>
                        <option value="<?= $item['id'] ?>">
                            <?= htmlspecialchars($item['name']) ?> (Current: <?= $item['quantity'] ?> <?= $item['unit'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="quantity_added" class="form-label">
                        <i class="fas fa-boxes mr-2"></i>Quantity Added *
                    </label>
                    <input type="number" id="quantity_added" name="quantity_added" min="1" required 
                        class="form-control" placeholder="e.g., 50">
                </div>
                
                <div class="form-group">
                    <label for="restock_supplier" class="form-label">
                        <i class="fas fa-truck mr-2"></i>Supplier *
                    </label>
                    <input type="text" id="restock_supplier" name="supplier" required 
                        class="form-control" placeholder="e.g., Medical Supplies Inc">
                </div>
                
                <div class="form-group">
                    <label for="batch_number" class="form-label">
                        <i class="fas fa-barcode mr-2"></i>Batch Number (Optional)
                    </label>
                    <input type="text" id="batch_number" name="batch_number" 
                        class="form-control" placeholder="Enter batch number">
                </div>
                
                <div class="form-group">
                    <label for="restock_expiry_date" class="form-label">
                        <i class="fas fa-calendar-alt mr-2"></i>Expiry Date (Optional)
                    </label>
                    <input type="date" id="restock_expiry_date" name="expiry_date" 
                        class="form-control">
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="document.getElementById('restock-modal').close()" 
                    class="btn btn-secondary px-6 py-3">
                    Cancel
                </button>
                <button type="submit" name="restock_item" 
                    class="btn btn-success px-6 py-3">
                    <i class="fas fa-plus mr-2"></i>Restock Item
                </button>
            </div>
        </form>
    </dialog>

    <!-- Edit Modal -->
    <dialog id="edit-modal" class="custom-modal">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold">Edit Inventory Item</h3>
                    <p class="text-blue-100 mt-1">Update item quantity and reorder level</p>
                </div>
                <button onclick="closeEditModal()" 
                    class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form method="post" action="" id="edit-form" class="p-6 space-y-6">
            <input type="hidden" name="item_id" id="edit-item-id">
            <input type="hidden" name="update_item" value="1">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="edit-quantity" class="form-label">
                        <i class="fas fa-boxes mr-2"></i>Current Stock *
                    </label>
                    <input type="number" id="edit-quantity" name="quantity" min="0" required 
                        class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="edit-reorder-level" class="form-label">
                        <i class="fas fa-exclamation-circle mr-2"></i>Reorder Level *
                    </label>
                    <input type="number" id="edit-reorder-level" name="reorder_level" min="1" required 
                        class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-pills mr-2"></i>Item Name
                    </label>
                    <input type="text" id="edit-name" class="form-control bg-gray-50" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag mr-2"></i>Category
                    </label>
                    <input type="text" id="edit-category" class="form-control bg-gray-50" readonly>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="closeEditModal()" 
                    class="btn btn-secondary px-6 py-3">
                    Cancel
                </button>
                <button type="submit" 
                    class="btn btn-success px-6 py-3">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </dialog>

    <!-- Report Modal -->
    <dialog id="report-modal" class="custom-modal">
        <div class="modal-header">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-semibold">Generate Inventory Report</h3>
                    <p class="text-blue-100 mt-1">Generate monthly, quarterly, or special reports</p>
                </div>
                <button onclick="document.getElementById('report-modal').close()" 
                    class="close-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <form method="post" action="generate_inventory_report.php" class="p-6 space-y-6" target="_blank">
            <div class="form-grid">
                <div class="form-group">
                    <label for="report_type" class="form-label">
                        <i class="fas fa-chart-bar mr-2"></i>Report Type *
                    </label>
                    <select id="report_type" name="report_type" required class="form-control">
                        <option value="">Select Report Type</option>
                        <option value="monthly">Monthly Inventory Report</option>
                        <option value="quarterly">Quarterly Inventory Report</option>
                        <option value="low_stock">Low Stock Report</option>
                        <option value="usage">Usage Report</option>
                        <option value="expiry">Expiry Report</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date" class="form-label">
                        <i class="fas fa-calendar-day mr-2"></i>Start Date *
                    </label>
                    <input type="date" id="start_date" name="start_date" required 
                        class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="end_date" class="form-label">
                        <i class="fas fa-calendar-day mr-2"></i>End Date *
                    </label>
                    <input type="date" id="end_date" name="end_date" required 
                        class="form-control">
                </div>
            </div>
            
            <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-medium text-blue-800 mb-2 flex items-center">
                    <i class="fas fa-info-circle mr-2"></i> Report Features
                </h4>
                <ul class="text-sm text-blue-700 list-disc pl-5 space-y-1">
                    <li><strong>Professional Format:</strong> Healthcare-appropriate design with color coding</li>
                    <li><strong>Automated Calculations:</strong> Stock levels, usage rates, expiry tracking</li>
                    <li><strong>Actionable Insights:</strong> Recommendations and alerts</li>
                    <li><strong>Multiple Views:</strong> Summary, detailed, and analytical reports</li>
                    <li><strong>Printable Format:</strong> Optimized for printing and record keeping</li>
                </ul>
            </div>
            
            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button type="button" onclick="document.getElementById('report-modal').close()" 
                    class="btn btn-secondary px-6 py-3">
                    Cancel
                </button>
                <button type="submit" name="generate_report" 
                    class="btn btn-success px-6 py-3">
                    <i class="fas fa-file-csv mr-2"></i>Generate CSV Report
                </button>
            </div>
        </form>
    </dialog>

    <script>
        // Filter and search functionality
        document.getElementById('categoryFilter').addEventListener('change', filterInventory);
        document.getElementById('searchInventory').addEventListener('input', filterInventory);
        
        function filterInventory() {
            const category = document.getElementById('categoryFilter').value;
            const search = document.getElementById('searchInventory').value.toLowerCase();
            const rows = document.querySelectorAll('.inventory-row');
            
            rows.forEach(row => {
                const itemCategory = row.dataset.category;
                const itemName = row.dataset.name;
                
                const categoryMatch = !category || itemCategory === category;
                const searchMatch = !search || itemName.includes(search);
                
                row.style.display = categoryMatch && searchMatch ? '' : 'none';
            });
        }
        
        // Use item modal functionality
        document.getElementById('use_item_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const stock = selectedOption.getAttribute('data-stock');
            const unit = selectedOption.getAttribute('data-unit');
            document.getElementById('stock-info').innerHTML = `
                <i class="fas fa-box mr-1"></i> Current stock: <span class="font-medium">${stock} ${unit}</span>
            `;
        });
        
        // Open modals with pre-filled data
        function openUseModal(itemId) {
            document.getElementById('use_item_id').value = itemId;
            document.getElementById('use_item_id').dispatchEvent(new Event('change'));
            document.getElementById('use-item-modal').showModal();
        }
        
        function openRestockModal(itemId) {
            document.getElementById('restock_item_id').value = itemId;
            document.getElementById('restock-modal').showModal();
        }
        
        function openEditModal(itemData) {
            document.getElementById('edit-item-id').value = itemData.id;
            document.getElementById('edit-quantity').value = itemData.quantity;
            document.getElementById('edit-reorder-level').value = itemData.reorder_level;
            document.getElementById('edit-name').value = itemData.name;
            document.getElementById('edit-category').value = itemData.category.replace('_', ' ');
            
            document.getElementById('edit-modal').showModal();
        }
        
        function closeEditModal() {
            document.getElementById('edit-modal').close();
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('dialog').forEach(dialog => {
            dialog.addEventListener('click', function(e) {
                if (e.target === dialog) {
                    dialog.close();
                }
            });
        });
        
        // Prevent form submission if quantity exceeds stock
        document.querySelector('#use-item-modal form').addEventListener('submit', function(e) {
            const quantityUsed = parseInt(document.getElementById('quantity_used').value);
            const selectedOption = document.getElementById('use_item_id').options[document.getElementById('use_item_id').selectedIndex];
            const stock = parseInt(selectedOption.getAttribute('data-stock'));
            
            if (quantityUsed > stock) {
                e.preventDefault();
                alert(`Error: Cannot use ${quantityUsed} items. Only ${stock} items available in stock.`);
            }
        });
        
        // Set default dates for reports and expiry
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            const nextYear = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
            
            // Set default dates for reports
            document.getElementById('start_date').value = formatDate(firstDay);
            document.getElementById('end_date').value = formatDate(lastDay);
            
            // Set default date for expiry fields (6 months from now)
            const sixMonthsLater = new Date();
            sixMonthsLater.setMonth(sixMonthsLater.getMonth() + 6);
            document.getElementById('expiry_date').value = formatDate(sixMonthsLater);
            document.getElementById('restock_expiry_date').value = formatDate(sixMonthsLater);
        });
        
        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }
        
        // Confirmation for important actions
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation for item deletion (if you add delete functionality later)
            const deleteButtons = document.querySelectorAll('button[name="delete_item"]');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>