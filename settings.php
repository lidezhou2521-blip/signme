<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = "";

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// ดึงข้อมูลการตั้งค่า SMTP (สำหรับ Admin)
$isAdmin = ($_SESSION['role'] ?? 'user') === 'admin';
$smtpSettings = [];
if ($isAdmin) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $smtpSettings[$row['setting_key']] = $row['setting_value'];
    }
}

// อัปเดตข้อมูลโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = $_POST['full_name'];
    $password = $_POST['password'];
    
    if (!empty($password)) {
        $hp = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ?");
        $stmt->execute([$fullName, $hp, $userId]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$fullName, $userId]);
    }
    $_SESSION['full_name'] = $fullName;
    $message = "บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว";
    header("Refresh: 2");
}

// อัปเดตการตั้งค่า SMTP (สำหรับ Admin)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_smtp'])) {
    foreach ($_POST['smtp'] as $key => $value) {
        if ($key === 'smtp_pass') {
            if (empty(trim($value))) {
                // ปล่อยว่างหมายความว่าไม่ต้องการเปลี่ยนรหัสผ่าน
                continue;
            }
            // เข้ารหัส App Password ก่อนบันทึกลงฐานข้อมูล
            $value = encryptSmtpPass(trim($value));
        }
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $message = "บันทึกการตั้งค่าอีเมลเรียบร้อยแล้ว";
    header("Refresh: 2");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า - SignMe</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1 style="font-size: 1.5rem; font-weight: 700;">การตั้งค่า</h1>
        </header>

        <?php if ($message): ?>
            <div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr <?php echo $isAdmin ? '1fr' : ''; ?>; gap: 2rem;">
            <!-- ส่วนข้อมูลส่วนตัว -->
            <div class="card animate-fade-in">
                <h2 style="font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="user"></i> ข้อมูลส่วนตัว
                </h2>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background: #f8fafc;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">รหัสผ่านใหม่ (ปล่อยว่างหากไม่ต้องการเปลี่ยน)</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">บันทึกโปรไฟล์</button>
                </form>
            </div>

            <?php if ($isAdmin): ?>
            <!-- ส่วนตั้งค่า SMTP (เฉพาะ Admin) -->
            <div class="card animate-fade-in" style="animation-delay: 0.1s;">
                <h2 style="font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="mail"></i> ตั้งค่าระบบอีเมล (SMTP)
                </h2>
                <form method="POST">
                    <input type="hidden" name="update_smtp" value="1">
                    <div class="form-group">
                        <label class="form-label">SMTP Host (เช่น smtp.gmail.com)</label>
                        <input type="text" name="smtp[smtp_host]" class="form-control" value="<?php echo htmlspecialchars($smtpSettings['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Port (ทั่วไป: 587 หรือ 465)</label>
                        <input type="text" name="smtp[smtp_port]" class="form-control" value="<?php echo htmlspecialchars($smtpSettings['smtp_port'] ?? '587'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email / Username</label>
                        <input type="email" name="smtp[smtp_user]" class="form-control" value="<?php echo htmlspecialchars($smtpSettings['smtp_user'] ?? ''); ?>" placeholder="user@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">App Password / Password</label>
                        <input type="password" name="smtp[smtp_pass]" class="form-control" placeholder="<?php echo !empty($smtpSettings['smtp_pass']) ? 'ตั้งค่าไว้แล้ว (ปล่อยว่างเพื่อไม่เปลี่ยน)' : 'กรอก App Password'; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ส่ง (Display Name)</label>
                        <input type="text" name="smtp[smtp_from_name]" class="form-control" value="<?php echo htmlspecialchars($smtpSettings['smtp_from_name'] ?? 'SignMe System'); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; background: #6366f1; border-color: #6366f1;">บันทึกการตั้งค่าอีเมล</button>
                </form>
                <div style="margin-top: 1rem; font-size: 0.75rem; color: var(--text-muted); background: #fefce8; padding: 0.75rem; border-radius: 8px; border: 1px solid #fef08a;">
                    <i data-lucide="info" style="width: 14px; vertical-align: middle;"></i> หากใช้ Gmail แนะนำให้สร้าง <b>App Password</b> แทนรหัสผ่านปกติครับ
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
