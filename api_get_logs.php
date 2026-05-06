<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$docId = $_GET['id'] ?? null;

if (!$docId) {
    echo json_encode(['success' => false, 'message' => 'Document ID is required']);
    exit;
}

try {
    // Check permission (admin can see all, user only their own)
    $isAdmin = ($_SESSION['role'] ?? 'user') === 'admin';
    $userId = $_SESSION['user_id'];

    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT id FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$docId, $userId]);
    }

    if (!$stmt->fetch()) {
        throw new Exception('Document not found or access denied');
    }

    $stmt = $pdo->prepare("SELECT * FROM document_logs WHERE document_id = ? ORDER BY created_at ASC");
    $stmt->execute([$docId]);
    $logs = $stmt->fetchAll();

    echo json_encode(['success' => true, 'logs' => $logs]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
