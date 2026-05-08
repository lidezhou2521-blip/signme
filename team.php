<?php
require_once 'config.php';

// Check login & Admin Role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: index.php?page=dashboard");
    exit;
}

$searchTerm = $_GET['search'] ?? '';
$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $full_name = $_POST['full_name'];
            $role = $_POST['role'];
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $password, $full_name, $role]);
                $message = "เพิ่มสมาชิกเรียบร้อยแล้ว";
            } catch (PDOException $e) { $message = "ข้อผิดพลาด: ชื่อผู้ใช้ซ้ำ"; }
        } elseif ($_POST['action'] === 'edit') {
            $id = $_POST['id'];
            $full_name = $_POST['full_name'];
            $role = $_POST['role'];
            $password_sql = "";
            $params = [$full_name, $role];
            
            if (!empty($_POST['password'])) {
                $password_sql = ", password = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ? $password_sql WHERE id = ?");
            $stmt->execute($params);
            $message = "แก้ไขข้อมูลสมาชิกเรียบร้อยแล้ว";
        } elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'];
            if ($id != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = "ลบสมาชิกเรียบร้อยแล้ว";
            }
        }
    }
}

// Fetch users
$query = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($searchTerm) {
    $query .= " AND (username LIKE ? OR full_name LIKE ?)";
    $params[] = "%$searchTerm%"; $params[] = "%$searchTerm%";
}
$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสมาชิก - SignMe</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .role-admin { background: #fee2e2; color: #991b1b; }
        .role-user { background: #e0f2fe; color: #075985; }
        .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2.5rem; border-radius: 24px; width: 480px; box-shadow: var(--shadow-lg); }
        .action-btn { cursor: pointer; border: none; padding: 8px; border-radius: 8px; background: #f1f5f9; transition: all 0.2s; display: inline-flex; }
        .action-btn:hover { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1 style="font-size: 1.5rem; font-weight: 700;">จัดการสมาชิกในทีม (<?php echo count($users); ?>)</h1>
            <div class="flex items-center gap-4">
                <form action="index.php" method="GET" class="search-box">
                    <input type="hidden" name="page" value="team">
                    <i data-lucide="search"></i>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อหรือ Username..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </form>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <span style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
            </div>
        </header>

        <div class="upload-card animate-fade-in" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <div>
                <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">กำลังขยายทีมอยู่ใช่ไหม?</h2>
                <p style="opacity: 0.9; font-size: 0.875rem;">คุณสามารถเพิ่มสมาชิกใหม่และกำหนดสิทธิ์การใช้งานได้ที่นี่</p>
            </div>
            <button class="btn" style="background: white; color: var(--primary);" onclick="openAddModal()">
                <i data-lucide="user-plus"></i> เพิ่มสมาชิกใหม่
            </button>
        </div>

        <?php if ($message): ?>
            <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.875rem;" class="animate-fade-in">
                <i data-lucide="check-circle" style="width:16px; display:inline; vertical-align:middle;"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <table class="doc-table animate-fade-in">
            <thead>
                <tr>
                    <th>ชื่อ-นามสกุล</th>
                    <th>Username</th>
                    <th>บทบาท</th>
                    <th>วันที่เข้าร่วม</th>
                    <th style="text-align: right;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                            </div>
                        </td>
                        <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem;">@<?php echo htmlspecialchars($user['username']); ?></code></td>
                        <td>
                            <?php $uRole = $user['role'] ?? 'user'; ?>
                            <span class="status-badge <?php echo $uRole === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                <?php echo ucfirst($uRole); ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        <td style="text-align: right;">
                            <div class="flex gap-2 justify-end">
                                <button type="button" class="action-btn" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, "UTF-8"); ?>)'>
                                    <i data-lucide="edit" style="width: 16px;"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('คุณต้องการลบสมาชิกคนนี้ใช่หรือไม่?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="action-btn" style="color: var(--danger);">
                                            <i data-lucide="trash-2" style="width: 16px;"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>

    <!-- Modal Form -->
    <div class="modal" id="user-modal">
        <div class="modal-content animate-fade-in">
            <h3 id="modal-title" style="margin-bottom: 1.5rem; font-weight: 700;">เพิ่มสมาชิกใหม่</h3>
            <form method="POST" id="user-form">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="id" id="user-id">
                
                <div class="form-group" id="username-group">
                    <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="เช่น somchai_s">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required placeholder="นายสมชาย รักดี">
                </div>

                <div class="form-group">
                    <label class="form-label">รหัสผ่าน <span id="pwd-note" style="font-size: 0.7rem; color: var(--text-muted); font-weight: 400;"></span></label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label class="form-label">บทบาทสิทธิ์การใช้งาน</label>
                    <select name="role" id="role" class="form-control">
                        <option value="user">User (จัดการเอกสารทั่วไป)</option>
                        <option value="admin">Admin (จัดการระบบ)</option>
                    </select>
                </div>
                
                <div class="flex justify-end gap-2" style="margin-top: 2rem;">
                    <button type="button" class="btn" style="background: #f1f5f9; color: var(--text-dark);" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูลสมาชิก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        const modal = document.getElementById('user-modal');
        const form = document.getElementById('user-form');
        const modalTitle = document.getElementById('modal-title');
        const formAction = document.getElementById('form-action');
        const usernameGroup = document.getElementById('username-group');
        const pwdNote = document.getElementById('pwd-note');

        function openAddModal() {
            modalTitle.innerText = "เพิ่มสมาชิกใหม่";
            formAction.value = "add";
            form.reset();
            usernameGroup.style.display = "block";
            pwdNote.innerText = "";
            document.getElementById('username').required = true;
            document.getElementById('password').required = true;
            modal.classList.add('active');
        }

        function openEditModal(user) {
            modalTitle.innerText = "แก้ไขข้อมูลสมาชิก";
            formAction.value = "edit";
            document.getElementById('user-id').value = user.id;
            document.getElementById('full_name').value = user.full_name || '';
            document.getElementById('role').value = user.role || 'user';
            
            usernameGroup.style.display = "none";
            document.getElementById('username').required = false;
            document.getElementById('password').required = false;
            pwdNote.innerText = "(เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน)";
            
            modal.classList.add('active');
        }

        function closeModal() { modal.classList.remove('active'); }
    </script>
</body>
</html>
