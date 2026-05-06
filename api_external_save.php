<?php
require_once 'config.php';
header('Content-Type: application/json');

ini_set('post_max_size', '20M');
ini_set('upload_max_filesize', '20M');

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
$signatures = $data['signatures'] ?? [];

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token missing']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM signers WHERE access_token = ?");
    $stmt->execute([$token]);
    $signer = $stmt->fetch();

    if (!$signer) throw new Exception('ไม่พบรหัสผู้ลงนามที่ถูกต้อง');

    // 1. บันทึกลายเซ็นลงในแต่ละฟิลด์
    foreach ($signatures as $sig) {
        $stmt = $pdo->prepare("UPDATE signature_fields SET signature_data = ?, status = 'filled' WHERE id = ? AND signer_id = ?");
        $stmt->execute([$sig['data'], $sig['id'], $signer['id']]);
    }

    // 2. อัปเดตสถานะผู้ลงนามคนนี้
    $stmt = $pdo->prepare("UPDATE signers SET status = 'signed', signed_at = NOW() WHERE id = ?");
    $stmt->execute([$signer['id']]);

    logDocumentActivity($pdo, $signer['document_id'], 'signed', $signer['name']);

    // 3. ตรวจสอบสถานะรวมของเอกสาร
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM signers WHERE document_id = ? AND status = 'pending'");
    $stmt->execute([$signer['document_id']]);
    $remaining = $stmt->fetchColumn();

    if ($remaining == 0) {
        $stmt = $pdo->prepare("UPDATE documents SET status = 'signed', signed_at = NOW() WHERE id = ?");
        $stmt->execute([$signer['document_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE documents SET status = 'pending' WHERE id = ?");
        $stmt->execute([$signer['document_id']]);
    }

    // 4. บันทึกไฟล์ PDF ฉบับอัปเดต (ทำทุกครั้งที่มีคนเซ็น เพื่อให้ไฟล์ล่าสุดมีลายเซ็นสะสมเสมอ)
    if (!empty($data['pdf_data'])) {
        $base64 = preg_replace('#^data:application/pdf;base64,#i', '', $data['pdf_data']);
        $pdf_decoded = base64_decode($base64);
        
        $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$signer['document_id']]);
        $docInfo = $stmt->fetch();
        
        if ($docInfo) {
            $new_path = $docInfo['file_path'];
            // ตรวจสอบว่ามี _completed.pdf หรือยัง ถ้ายังให้สร้างชื่อใหม่
            if (strpos($new_path, '_completed.pdf') === false) {
                $new_path = str_replace('.pdf', '_completed.pdf', $docInfo['file_path']);
            }
            
            if (file_put_contents($new_path, $pdf_decoded) === false) {
                throw new Exception('ไม่สามารถบันทึกไฟล์ PDF ได้ กรุณาตรวจสอบสิทธิ์โฟลเดอร์');
            }
            
            // อัปเดต Path ของเอกสารให้เป็นตัวที่รวมลายเซ็นแล้ว
            $stmt = $pdo->prepare("UPDATE documents SET file_path = ? WHERE id = ?");
            $stmt->execute([$new_path, $signer['document_id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
