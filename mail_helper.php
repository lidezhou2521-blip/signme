<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ตรวจสอบว่ามีไฟล์ PHPMailer หรือไม่
$phpmailerPath = __DIR__ . '/lib/PHPMailer/src/';
if (file_exists($phpmailerPath . 'PHPMailer.php')) {
    require $phpmailerPath . 'Exception.php';
    require $phpmailerPath . 'PHPMailer.php';
    require $phpmailerPath . 'SMTP.php';
}

function sendEmail($to, $subject, $body)
{
    global $pdo;

    // ดึงการตั้งค่าจากฐานข้อมูล
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    if (empty($settings['smtp_host']) || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // แทนที่จะเงียบ ให้ฟ้องออกมาเลยว่าทำไมถึงตกมาที่นี่
        $reason = "";
        if (empty($settings['smtp_host'])) $reason .= " (ตรวจพบ smtp_host เป็นค่าว่างใน DB)";
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) $reason .= " (หา Class PHPMailer ไม่เจอ)";
        
        throw new Exception("ระบบพยายามใช้ PHP mail() ดั้งเดิมแทน SMTP เนื่องจาก:" . $reason);
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = trim(decryptSmtpPass($settings['smtp_pass'])); // ถอดรหัสและตัดช่องว่างที่อาจหลุดมา
        $mail->SMTPSecure = ($settings['smtp_port'] == '465') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['smtp_port'];

        // สำหรับการใช้งานบนเครื่อง Local (XAMPP) ที่มักมีปัญหาเรื่อง SSL Certificate
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($settings['smtp_user'], $settings['smtp_from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // แทนที่จะคืนค่า false ให้โยน Exception พร้อมสาเหตุออกมาเลย
        throw new Exception("Mail Error: " . $mail->ErrorInfo);
    }
}
