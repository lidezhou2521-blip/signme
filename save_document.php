<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pdf']) && isset($_POST['file_name'])) {
        $user_id = $_SESSION['user_id'];
        $file_name = $_POST['file_name'];
        $signer_name = $_POST['signer_name'] ?? 'Self Signed';
        
        $upload_dir = 'pdf_sign/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $new_filename = time() . '_' . $file_name;
        $target_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['pdf']['tmp_name'], $target_path)) {
            $stmt = $pdo->prepare("INSERT INTO documents (user_id, file_name, file_path, status, created_at, signed_at, signer_name) VALUES (?, ?, ?, 'signed', NOW(), NOW(), ?)");
            $stmt->execute([$user_id, $file_name, $target_path, $signer_name]);
            $docId = $pdo->lastInsertId();

            $actorName = $_SESSION['username'] ?? 'Unknown User';
            logDocumentActivity($pdo, $docId, 'created', $actorName);
            logDocumentActivity($pdo, $docId, 'signed', $actorName);
            
            echo json_encode(['success' => true, 'message' => 'บันทึกเอกสารเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
