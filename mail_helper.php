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

function sendEmail($to, $subject, $body) {
    global $pdo;

    // ดึงการตั้งค่าจากฐานข้อมูล
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    if (empty($settings['smtp_host']) || !class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // หากยังไม่ได้ตั้งค่า SMTP หรือไม่มี Library ให้ใช้ mail() ของ PHP เป็น fallback
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: SignMe <noreply@' . $_SERVER['HTTP_HOST'] . '>' . "\r\n";
        return @mail($to, $subject, $body, $headers); // ใช้ @ เพื่อปิด Warning หากส่งไม่ได้
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_user'];
        $mail->Password   = $settings['smtp_pass'];
        $mail->SMTPSecure = ($settings['smtp_port'] == '465') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['smtp_port'];

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
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
