<?php
$host    = 'localhost'; // Shared Hosting ส่วนใหญ่ใช้ Socket ผ่าน localhost
$db      = 'signme';   // ← ปรับชื่อ DB ให้ตรงกับที่สร้างใน cPanel
$user    = 'root';     // ← ปรับเป็น DB User จาก cPanel
$pass    = '';         // ← ปรับเป็นรหัสผ่าน DB
$charset = 'utf8mb4';

// รวม dbname ไว้ใน DSN — ใช้ได้ทั้ง XAMPP และ Hosting จริง
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // พยายามสร้าง DB หากยังไม่มี (ใช้ได้บน XAMPP, บน Hosting จริงมักจะ fail เงียบๆ เพราะไม่มีสิทธิ์)
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (\PDOException $e) {
    }
    $pdo->exec("USE `$db`");

    // 1. ตาราง users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        role VARCHAR(20) DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) AFTER password");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user' AFTER full_name");

    // 2. ตาราง documents
    $pdo->exec("CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        status ENUM('pending', 'sent', 'signed', 'archive') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        signed_at DATETIME NULL,
        signer_name VARCHAR(100) NULL
    )");

    // 3. ตาราง signers
    $pdo->exec("CREATE TABLE IF NOT EXISTS signers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        access_token VARCHAR(100) UNIQUE NOT NULL,
        access_code VARCHAR(50) NULL,
        status ENUM('pending', 'signed') DEFAULT 'pending',
        signed_at DATETIME NULL
    )");
    // มั่นใจว่ามีคอลัมน์ใหม่ๆ
    $pdo->exec("ALTER TABLE signers ADD COLUMN IF NOT EXISTS document_id INT NOT NULL AFTER id");
    $pdo->exec("ALTER TABLE signers ADD COLUMN IF NOT EXISTS access_code VARCHAR(50) NULL AFTER access_token");
    $pdo->exec("ALTER TABLE signers ADD COLUMN IF NOT EXISTS signed_at DATETIME NULL AFTER status");

    // 4. ตาราง signature_fields
    $pdo->exec("CREATE TABLE IF NOT EXISTS signature_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        signer_id INT NOT NULL,
        page_number INT NOT NULL,
        x FLOAT NOT NULL,
        y FLOAT NOT NULL,
        width FLOAT NOT NULL,
        height FLOAT NOT NULL,
        status ENUM('empty', 'filled') DEFAULT 'empty',
        signature_data LONGTEXT NULL,
        signed_at DATETIME NULL
    )");
    // มั่นใจว่ามี document_id
    $pdo->exec("ALTER TABLE signature_fields ADD COLUMN IF NOT EXISTS document_id INT NOT NULL AFTER id");
    $pdo->exec("ALTER TABLE signature_fields ADD COLUMN IF NOT EXISTS field_type VARCHAR(20) DEFAULT 'signature' AFTER signer_id");

    // 5. ตาราง settings (สำหรับเก็บค่า SMTP และอื่นๆ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // ใส่ค่าเริ่มต้นสำหรับ SMTP
    $defaultSettings = [
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from_name' => 'SignMe System'
    ];
    foreach ($defaultSettings as $key => $val) {
        $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
    }

    // Seed Admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hp = password_hash('admin', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES ('admin', ?, 'Administrator', 'admin')")->execute([$hp]);
    }

    // 6. ตาราง document_logs (Audit Trail)
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        actor_name VARCHAR(100) NOT NULL,
        actor_ip VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (\PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// Helper function สำหรับบันทึกประวัติเอกสาร
function logDocumentActivity($pdo, $docId, $action, $actorName)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare("INSERT INTO document_logs (document_id, action, actor_name, actor_ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$docId, $action, $actorName, $ip]);
    } catch (Exception $e) {
        // เงียบไว้เพื่อไม่ให้กระทบการทำงานหลัก
    }
}


// ==================== SMTP Encryption Helpers ====================
// Key นี้ใช้สำหรับเข้ารหัส/ถอดรหัส App Password ก่อนบันทึกลงฐานข้อมูล
// เก็บไว้ใน config.php ซึ่งถูก .gitignore ไว้แล้ว — ห้ามแชร์ Key นี้
define('SMTP_ENCRYPT_KEY', 'c2367f39767343846e97cfbb286eb0c90a4ae53dccf876fddf240e0d142d5365');

function encryptSmtpPass(string $plaintext): string
{
    if (empty($plaintext)) return '';
    $key = hex2bin(SMTP_ENCRYPT_KEY); // แปลง hex → 32 bytes (AES-256)
    $iv  = openssl_random_pseudo_bytes(16); // IV แบบสุ่มใหม่ทุกครั้ง
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    // เก็บ IV ไว้ที่หน้าข้อมูล เพื่อใช้ถอดรหัส (IV ไม่จำเป็นต้องเป็นความลับ)
    return base64_encode($iv . $enc);
}

function decryptSmtpPass(string $ciphertext): string
{
    if (empty($ciphertext)) return '';
    $key     = hex2bin(SMTP_ENCRYPT_KEY);
    $decoded = base64_decode($ciphertext, true);
    if ($decoded === false || strlen($decoded) < 17) {
        // ข้อมูลเก่าที่ยังไม่ได้เข้ารหัส — คืนค่าเดิมเพื่อ backward compat.
        return $ciphertext;
    }
    $iv  = substr($decoded, 0, 16);
    $enc = substr($decoded, 16);
    $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    // ถ้า decrypt ล้มเหลว คืน plain-text เดิม (backward compat.)
    return ($dec === false) ? $ciphertext : $dec;
}
// ==================================================================

if (session_status() === PHP_SESSION_NONE) session_start();
