<?php
error_reporting(0); // ปิดการแสดง Error แบบ HTML ไม่ให้ไปกวน JSON
require_once 'config.php';
require_once 'mail_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['user_id'])) throw new Exception('กรุณาเข้าสู่ระบบก่อน');
        
        $user_id = $_SESSION['user_id'];
        $file_name = $_POST['file_name'] ?? 'document.pdf';
        $signersData = json_decode($_POST['signers'] ?? '[]', true);
        $fieldsData = json_decode($_POST['fields'] ?? '[]', true);

        if (!isset($_FILES['pdf'])) throw new Exception('ไม่พบไฟล์ PDF');

        // 1. Save original PDF
        $upload_dir = 'pdf_sign/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $new_filename = time() . '_orig_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
        $target_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $target_path)) {
            throw new Exception('ไม่สามารถบันทึกไฟล์ PDF ได้');
        }

        $pdo->beginTransaction();

        // 2. Create Document record
        $stmt = $pdo->prepare("INSERT INTO documents (user_id, file_name, file_path, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $file_name, $target_path]);
        $docId = $pdo->lastInsertId();

        $actorName = $_SESSION['username'] ?? 'Unknown User';
        logDocumentActivity($pdo, $docId, 'created', $actorName);

        // 3. Save Signers & Fields
        $signerIdsByIndex = [];
        foreach ($signersData as $index => $s) {
            $token = bin2hex(random_bytes(16));
            $code = $s['access_code'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO signers (document_id, name, email, access_token, access_code) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$docId, $s['name'], $s['email'], $token, $code]);
            $signerId = $pdo->lastInsertId();
            $signerIdsByIndex[$index] = $signerId;

            // --- ส่งอีเมลหาผู้ลงนาม ---
            $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']);
            $signLink = "$baseUrl/external_sign.php?token=$token";
            
            $codeMsg = $code ? "<p style='color: #dc2626; font-weight: bold;'>* เอกสารนี้มีการตั้งรหัสผ่านในการเข้าถึง: $code</p>" : "";

            $subject = "เชิญลงนามเอกสาร: $file_name";
            $body = "
                <div style='font-family: sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #2563eb;'>สวัสดีคุณ {$s['name']}</h2>
                    <p>คุณได้รับคำเชิญให้ลงนามในเอกสาร: <strong>$file_name</strong></p>
                    $codeMsg
                    <p style='margin-top: 30px; text-align: center;'>
                        <a href='$signLink' style='background: #2563eb; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>คลิกที่นี่เพื่อเริ่มลงนาม</a>
                    </p>
                    <p style='margin-top: 30px; font-size: 0.85rem; color: #666; border-top: 1px solid #eee; padding-top: 15px;'>หากปุ่มใช้งานไม่ได้ คุณสามารถคัดลอกลิงก์นี้ไปวางในเบราว์เซอร์ได้:<br><span style='color: #2563eb;'>$signLink</span></p>
                </div>
            ";
            @sendEmail($s['email'], $subject, $body); // ส่งเมลแบบไม่ให้ Error มาขัดจังหวะ
            // ------------------------
            logDocumentActivity($pdo, $docId, 'sent', "Sent to " . $s['name']);
        }

        foreach ($fieldsData as $f) {
            $type = $f['type'] ?? 'signature';
            $sIdx = $f['signer_index'] ?? 0;
            $signerId = $signerIdsByIndex[$sIdx] ?? 0;
            
            $stmt = $pdo->prepare("INSERT INTO signature_fields (document_id, signer_id, field_type, page_number, x, y, width, height) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$docId, $signerId, $type, $f['page'], $f['x'], $f['y'], $f['w'], $f['h']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
