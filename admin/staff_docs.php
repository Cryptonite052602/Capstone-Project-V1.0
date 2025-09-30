<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';

redirectIfNotLoggedIn();
if (!isAdmin()) {
    header('Location: /community-health-tracker/');
    exit();
}

global $pdo;

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    if (!empty($title) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../assets/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['document']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO staff_documents (title, description, file_name, uploaded_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $fileName, $_SESSION['user']['id']]);
                
                $_SESSION['success'] = 'Document uploaded successfully!';
                header('Location: staff_docs.php');
                exit();
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error saving document info: ' . $e->getMessage();
                unlink($targetPath);
            }
        } else {
            $_SESSION['error'] = 'Error uploading document.';
        }
    } else {
        $_SESSION['error'] = 'Please provide a title and select a valid document.';
    }
}

// Get all documents
$documents = [];

try {
    $stmt = $pdo->query("
        SELECT d.*, a.full_name as uploaded_by_name 
        FROM staff_documents d
        LEFT JOIN admin a ON d.uploaded_by = a.id
        ORDER BY d.created_at DESC
    ");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error fetching documents: ' . $e->getMessage();
}
?>

<div class="container mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Staff Documentation</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="bg-white p-6 rounded-lg shadow mb-8">
        <h2 class="text-xl font-semibold mb-4">Upload New Document</h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="title" class="block text-gray-700 mb-2">Title *</label>
                <input type="text" id="title" name="title" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <div class="mb-4">
                <label for="description" class="block text-gray-700 mb-2">Description</label>
                <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="mb-4">
                <label for="document" class="block text-gray-700 mb-2">Document *</label>
                <input type="file" id="document" name="document" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            
            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">Upload Document</button>
        </form>
    </div>

    <!-- Documents List -->
    <div class="bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-4">Available Documents</h2>
        
        <?php if (empty($documents)): ?>
            <p class="text-gray-600">No documents available.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Title</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Description</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Uploaded By</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($doc['title']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($doc['description'] ?: 'N/A') ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($doc['uploaded_by_name']) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200"><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                                <td class="py-2 px-4 border-b border-gray-200">
                                    <a href="/community-health-tracker/assets/documents/<?= htmlspecialchars($doc['file_name']) ?>" 
                                       class="text-blue-600 hover:underline" download>Download</a>
                                    <a href="delete_document.php?id=<?= $doc['id'] ?>" 
                                       class="text-red-600 hover:underline ml-2" 
                                       onclick="return confirm('Are you sure you want to delete this document?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

