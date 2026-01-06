<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if authorized (admin or inventory manager)
if (!isAdmin() && !hasPermission('manage_inventory')) {
    header('Location: /community-health-tracker/auth/login.php');
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'] ?? 1;

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
            $stmt->execute([$name, $category, $quantity, $unit, $expiryDate, $supplier, $reorderLevel, $userId]);
            
            $_SESSION['message'] = "Item added successfully!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['update_item'])) {
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        $reorderLevel = (int)$_POST['reorder_level'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE barangay_inventory 
                SET quantity = ?, reorder_level = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $reorderLevel, $itemId]);
            
            $_SESSION['message'] = "Item updated!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
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
            $checkStmt = $pdo->prepare("SELECT quantity, name FROM barangay_inventory WHERE id = ?");
            $checkStmt->execute([$itemId]);
            $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item['quantity'] < $quantityUsed) {
                $_SESSION['message'] = "Insufficient stock! Only " . $item['quantity'] . " available.";
                $_SESSION['message_type'] = 'error';
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE barangay_inventory 
                    SET quantity = quantity - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$quantityUsed, $itemId]);
                
                $_SESSION['message'] = "Item usage recorded!";
                $_SESSION['message_type'] = 'success';
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
        
    } elseif (isset($_POST['restock_item'])) {
        $itemId = (int)$_POST['item_id'];
        $quantityAdded = (int)$_POST['quantity_added'];
        $supplier = trim($_POST['supplier']);
        
        try {
            $updateStmt = $pdo->prepare("
                UPDATE barangay_inventory 
                SET quantity = quantity + ?, supplier = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantityAdded, $supplier, $itemId]);
            
            $_SESSION['message'] = "Item restocked!";
            $_SESSION['message_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
        
        header("Location: inventory_medicine.php");
        exit;
    }
}

// Get inventory items
try {
    $inventoryQuery = $pdo->prepare("SELECT * FROM barangay_inventory ORDER BY category, name");
    $inventoryQuery->execute();
    $inventoryItems = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventoryItems = [];
    $_SESSION['message'] = "Failed to load inventory: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// Get low stock items
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Health Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-blue {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-green {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-purple {
            background: #f3e8ff;
            color: #7c3aed;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-6 mt-16">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Inventory Management</h1>
            <p class="text-gray-600 text-sm">Manage medicines, vaccines, and supplies</p>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 rounded-lg flex items-center <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
                <i class="fas <?= ($_SESSION['message_type'] ?? '') === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-3"></i>
                <span><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mb-6">
            <button onclick="showModal('add')" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Item
            </button>
            <button onclick="showModal('use')" class="btn btn-success">
                <i class="fas fa-syringe"></i> Record Usage
            </button>
            <button onclick="showModal('restock')" class="btn btn-warning">
                <i class="fas fa-truck-loading"></i> Restock
            </button>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStockItems)): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center mb-3">
                <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                <h3 class="font-semibold text-red-800">Low Stock Alert</h3>
                <span class="ml-auto badge badge-error"><?= count($lowStockItems) ?> items</span>
            </div>
            <div class="text-sm text-red-700">
                <?php foreach ($lowStockItems as $item): ?>
                    <div class="flex justify-between items-center mb-2">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span class="font-medium"><?= $item['quantity'] ?> <?= $item['unit'] ?> (Reorder: <?= $item['reorder_level'] ?>)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inventory Items -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Current Inventory</h3>
                    <span class="text-sm text-gray-600"><?= count($inventoryItems) ?> items</span>
                </div>
                <div class="mt-3 flex gap-4">
                    <input type="text" id="search" placeholder="Search items..." class="flex-1 p-2 border border-gray-300 rounded">
                    <select id="filter" class="p-2 border border-gray-300 rounded">
                        <option value="">All Categories</option>
                        <option value="medicine">Medicine</option>
                        <option value="vaccine">Vaccine</option>
                        <option value="supplies">Supplies</option>
                    </select>
                </div>
            </div>
            
            <div class="p-4">
                <?php if (empty($inventoryItems)): ?>
                    <p class="text-center text-gray-500 py-8">No inventory items found.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($inventoryItems as $item): 
                            $isLowStock = $item['quantity'] <= $item['reorder_level'];
                            $badgeClass = 'badge-success';
                            if ($isLowStock) $badgeClass = 'badge-warning';
                            if ($item['expiry_date'] && strtotime($item['expiry_date']) < time()) $badgeClass = 'badge-error';
                        ?>
                            <div class="border border-gray-200 rounded p-4 item-row" 
                                 data-category="<?= $item['category'] ?>"
                                 data-name="<?= strtolower($item['name']) ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-sm text-gray-600">
                                                <?php 
                                                $categoryBadge = 'badge-blue';
                                                if ($item['category'] === 'vaccine') $categoryBadge = 'badge-green';
                                                if ($item['category'] === 'supplies') $categoryBadge = 'badge-purple';
                                                ?>
                                                <span class="badge <?= $categoryBadge ?>">
                                                    <?= ucfirst($item['category']) ?>
                                                </span>
                                            </span>
                                            <span class="badge <?= $badgeClass ?>">
                                                <?= $item['quantity'] ?> <?= $item['unit'] ?>
                                            </span>
                                            <?php if ($item['expiry_date']): ?>
                                                <span class="text-sm text-gray-600">
                                                    Exp: <?= date('M Y', strtotime($item['expiry_date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="useItem(<?= $item['id'] ?>)" class="text-sm bg-green-100 text-green-800 px-3 py-1 rounded">
                                            <i class="fas fa-syringe mr-1"></i> Use
                                        </button>
                                        <button onclick="restockItem(<?= $item['id'] ?>)" class="text-sm bg-yellow-100 text-yellow-800 px-3 py-1 rounded">
                                            <i class="fas fa-plus mr-1"></i> Restock
                                        </button>
                                        <button onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)" class="text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </button>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-600 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <span class="text-gray-500">Reorder:</span>
                                        <span class="font-medium ml-1"><?= $item['reorder_level'] ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Supplier:</span>
                                        <span class="font-medium ml-1"><?= htmlspecialchars($item['supplier']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Last Updated:</span>
                                        <span class="font-medium ml-1"><?= date('M j, Y', strtotime($item['updated_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Add New Item</h3>
                    <button onclick="hideModal('add')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form method="post" action="" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Name *</label>
                    <input type="text" name="name" required class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category" required class="w-full p-2 border border-gray-300 rounded">
                            <option value="">Select</option>
                            <option value="medicine">Medicine</option>
                            <option value="vaccine">Vaccine</option>
                            <option value="supplies">Supplies</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <input type="number" name="quantity" required min="0" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                        <select name="unit" required class="w-full p-2 border border-gray-300 rounded">
                            <option value="">Select</option>
                            <option value="tablet">Tablet</option>
                            <option value="capsule">Capsule</option>
                            <option value="bottle">Bottle</option>
                            <option value="vial">Vial</option>
                            <option value="piece">Piece</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reorder Level *</label>
                        <input type="number" name="reorder_level" required min="1" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                    <input type="text" name="supplier" required class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                    <input type="date" name="expiry_date" class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="hideModal('add')" class="px-4 py-2 border border-gray-300 rounded">Cancel</button>
                    <button type="submit" name="add_item" class="px-4 py-2 bg-blue-600 text-white rounded">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Use Item Modal -->
    <div id="useModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Record Item Usage</h3>
                    <button onclick="hideModal('use')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form method="post" action="" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Item *</label>
                    <select id="useItemSelect" name="item_id" required class="w-full p-2 border border-gray-300 rounded">
                        <option value="">Select Item</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= $item['id'] ?>" data-stock="<?= $item['quantity'] ?>">
                                <?= htmlspecialchars($item['name']) ?> (<?= $item['quantity'] ?> <?= $item['unit'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Used *</label>
                    <input type="number" id="quantityUsed" name="quantity_used" required min="1" class="w-full p-2 border border-gray-300 rounded">
                    <div id="stockInfo" class="text-sm text-gray-500 mt-1"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Type *</label>
                    <select name="service_type" required class="w-full p-2 border border-gray-300 rounded">
                        <option value="">Select</option>
                        <option value="consultation">Consultation</option>
                        <option value="immunization">Immunization</option>
                        <option value="treatment">Treatment</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient ID (Optional)</label>
                    <input type="text" name="patient_id" class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full p-2 border border-gray-300 rounded"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="hideModal('use')" class="px-4 py-2 border border-gray-300 rounded">Cancel</button>
                    <button type="submit" name="use_item" class="px-4 py-2 bg-green-600 text-white rounded">Record Usage</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restock Modal -->
    <div id="restockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Restock Item</h3>
                    <button onclick="hideModal('restock')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form method="post" action="" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Item *</label>
                    <select id="restockItemSelect" name="item_id" required class="w-full p-2 border border-gray-300 rounded">
                        <option value="">Select Item</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= $item['id'] ?>">
                                <?= htmlspecialchars($item['name']) ?> (<?= $item['quantity'] ?> <?= $item['unit'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity to Add *</label>
                    <input type="number" name="quantity_added" required min="1" class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier *</label>
                    <input type="text" name="supplier" required class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="hideModal('restock')" class="px-4 py-2 border border-gray-300 rounded">Cancel</button>
                    <button type="submit" name="restock_item" class="px-4 py-2 bg-yellow-600 text-white rounded">Restock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg w-full max-w-md">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Edit Item</h3>
                    <button onclick="hideModal('edit')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <form method="post" action="" class="p-4 space-y-4">
                <input type="hidden" name="item_id" id="editItemId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                    <input type="text" id="editItemName" class="w-full p-2 border border-gray-300 rounded bg-gray-50" readonly>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Quantity *</label>
                        <input type="number" name="quantity" id="editQuantity" required min="0" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reorder Level *</label>
                        <input type="number" name="reorder_level" id="editReorderLevel" required min="1" class="w-full p-2 border border-gray-300 rounded">
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="hideModal('edit')" class="px-4 py-2 border border-gray-300 rounded">Cancel</button>
                    <button type="submit" name="update_item" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showModal(type) {
            document.getElementById(type + 'Modal').classList.remove('hidden');
            document.getElementById(type + 'Modal').classList.add('flex');
        }
        
        function hideModal(type) {
            document.getElementById(type + 'Modal').classList.add('hidden');
            document.getElementById(type + 'Modal').classList.remove('flex');
        }
        
        // Use item functions
        function useItem(itemId) {
            document.getElementById('useItemSelect').value = itemId;
            updateStockInfo();
            showModal('use');
        }
        
        function restockItem(itemId) {
            document.getElementById('restockItemSelect').value = itemId;
            showModal('restock');
        }
        
        function editItem(item) {
            document.getElementById('editItemId').value = item.id;
            document.getElementById('editItemName').value = item.name;
            document.getElementById('editQuantity').value = item.quantity;
            document.getElementById('editReorderLevel').value = item.reorder_level;
            showModal('edit');
        }
        
        // Update stock info when item is selected
        document.getElementById('useItemSelect').addEventListener('change', updateStockInfo);
        
        function updateStockInfo() {
            const select = document.getElementById('useItemSelect');
            const option = select.options[select.selectedIndex];
            const stock = option.getAttribute('data-stock');
            document.getElementById('stockInfo').textContent = stock ? `Available stock: ${stock}` : '';
        }
        
        // Search and filter
        document.getElementById('search').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const items = document.querySelectorAll('.item-row');
            
            items.forEach(item => {
                const name = item.dataset.name;
                item.style.display = name.includes(search) ? '' : 'none';
            });
        });
        
        document.getElementById('filter').addEventListener('change', function() {
            const category = this.value;
            const items = document.querySelectorAll('.item-row');
            
            items.forEach(item => {
                const itemCategory = item.dataset.category;
                item.style.display = !category || itemCategory === category ? '' : 'none';
            });
        });
        
        // Validate usage quantity
        document.querySelector('#useModal form').addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('quantityUsed').value);
            const select = document.getElementById('useItemSelect');
            const stock = parseInt(select.options[select.selectedIndex].getAttribute('data-stock'));
            
            if (quantity > stock) {
                e.preventDefault();
                alert(`Cannot use ${quantity} items. Only ${stock} available.`);
            }
        });
        
        // Close modals on outside click
        document.querySelectorAll('[class*="Modal"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        });
    </script>
</body>
</html>