<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
            header('Location: index.php?page=dashboard');
            exit();
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SignMe - เข้าสู่ระบบเซ็นเอกสารออนไลน์</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('assets/login_bg.png') no-repeat center center/cover;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.4);
            z-index: 1;
        }

        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 500px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .login-info {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(14, 165, 233, 0.9));
            padding: 3rem;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form-side {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--primary);
        }

        .social-login {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .social-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 0.75rem;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .social-btn:hover {
            background: var(--background);
            border-color: var(--primary);
            color: var(--primary);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border);
        }

        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        @media (max-width: 768px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 450px;
            }
            .login-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="login-container animate-fade-in">
        <div class="login-info">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Sign Documents Anywhere.</h1>
            <p style="font-size: 1.1rem; opacity: 0.9;">เซ็นเอกสารออนไลน์ ง่าย รวดเร็ว และปลอดภัย ด้วยมาตรฐานสากล</p>
            <div style="margin-top: 3rem;">
                <div class="flex items-center gap-4" style="margin-bottom: 1.5rem;">
                    <div style="background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 10px;">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 600;">ความปลอดภัยสูง</h4>
                        <p style="font-size: 0.875rem; opacity: 0.8;">เข้ารหัสข้อมูลทุกขั้นตอน</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div style="background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 10px;">
                        <i data-lucide="zap"></i>
                    </div>
                    <div>
                        <h4 style="font-weight: 600;">รวดเร็วทันใจ</h4>
                        <p style="font-size: 0.875rem; opacity: 0.8;">เซ็นเสร็จภายในไม่กี่นาที</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="login-form-side">
            <div class="brand-logo">
                <i data-lucide="pen-tool" style="width: 32px; height: 32px;"></i>
                <span>SignMe</span>
            </div>
            <h2 style="margin-bottom: 0.5rem;">ยินดีต้อนรับกลับมา</h2>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">เข้าสู่ระบบเพื่อจัดการเอกสารของคุณ</p>

            <?php if ($error): ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 0.75rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.875rem; border: 1px solid #fecaca;">
                    <i data-lucide="alert-circle" style="width: 16px; display: inline; vertical-align: middle; margin-right: 0.5rem;"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>


            <form action="index.php?page=login" method="POST">
                <div class="form-group">
                    <label class="form-label">ชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control" placeholder="admin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="flex items-center gap-2" style="margin-bottom: 1.5rem; font-size: 0.875rem;">
                    <label class="flex items-center gap-2" style="cursor: pointer;">
                        <input type="checkbox" style="width: 16px; height: 16px;">
                        จดจำฉัน
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
