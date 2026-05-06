<?php
$current_page = $_GET['page'] ?? 'dashboard';
$current_status = $_GET['status'] ?? '';
?>
<style>
    :root { 
        --sidebar-width: 260px; 
        --sidebar-collapsed-width: 80px;
        --primary: #2563eb; 
        --text-muted: #64748b; 
        --border: #e2e8f0; 
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    body { margin: 0; display: flex; background-color: #f8fafc; font-family: 'Inter', 'Sarabun', sans-serif; }
    
    .sidebar { 
        width: var(--sidebar-width); 
        background: white; 
        border-right: 1px solid var(--border); 
        display: flex; 
        flex-direction: column; 
        padding: 1.5rem 1rem; 
        position: fixed; 
        height: 100vh; 
        z-index: 1000; 
        box-sizing: border-box; 
        overflow-y: auto; 
        scrollbar-width: none;
        transition: var(--transition);
    }
    .sidebar.collapsed { width: var(--sidebar-collapsed-width); padding: 1.5rem 0.75rem; }
    .sidebar::-webkit-scrollbar { display: none; }

    .brand-logo { 
        display: flex; 
        align-items: center; 
        gap: 0.75rem; 
        font-size: 1.25rem; 
        font-weight: 800; 
        color: var(--primary); 
        margin-bottom: 2rem; 
        text-decoration: none;
        overflow: hidden;
        padding-left: 0.5rem;
    }
    .sidebar.collapsed .brand-logo span { display: none; }

    .nav-group-label { 
        font-size: 0.7rem; 
        text-transform: uppercase; 
        letter-spacing: 0.05em; 
        color: var(--text-muted); 
        margin: 1.5rem 0 0.5rem 1rem; 
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
    }
    .sidebar.collapsed .nav-group-label { opacity: 0; margin-left: 0; text-align: center; }

    .nav-item { 
        display: flex !important; 
        align-items: center; 
        gap: 0.875rem; 
        padding: 0.75rem 1rem; 
        border-radius: 12px; 
        color: var(--text-muted); 
        margin-bottom: 0.25rem; 
        font-weight: 500; 
        text-decoration: none; 
        transition: var(--transition); 
        white-space: nowrap;
        overflow: hidden;
    }
    .nav-item i { width: 20px; height: 20px; flex-shrink: 0; }
    .nav-item:hover { background: #f1f5f9; color: var(--primary); }
    .nav-item.active { background: rgba(37, 99, 235, 0.1); color: var(--primary); font-weight: 600; }
    
    .sidebar.collapsed .nav-item { justify-content: center; padding: 0.75rem; gap: 0; }
    .sidebar.collapsed .nav-item span { display: none; }

    .main-content { 
        margin-left: var(--sidebar-width); 
        flex: 1; 
        padding: 2.5rem; 
        width: calc(100% - var(--sidebar-width)); 
        box-sizing: border-box; 
        transition: var(--transition);
    }
    .main-content.expanded { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }

    .toggle-sidebar {
        position: absolute;
        right: -12px;
        top: 25px;
        background: var(--primary);
        color: white;
        border: none;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        z-index: 1001;
        transition: var(--transition);
    }
    .sidebar.collapsed .toggle-sidebar { transform: rotate(180deg); }

    /* Hamburger & Mobile Responsive */
    .hamburger-btn {
        display: none;
        position: fixed;
        top: 1rem;
        left: 1.25rem;
        z-index: 1500;
        background: white;
        border: none;
        padding: 0.75rem;
        border-radius: 14px;
        cursor: pointer;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        color: var(--primary);
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }
    .hamburger-btn:hover { transform: scale(1.05); background: #f8fafc; }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.3);
        backdrop-filter: blur(8px);
        z-index: 999;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    @media (max-width: 1024px) {
        .sidebar {
            left: -100%;
            width: var(--sidebar-width) !important;
            box-shadow: 20px 0 25px -5px rgba(0, 0, 0, 0.1);
        }
        .sidebar.open { left: 0; }
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 1.5rem;
            padding-top: 5.5rem;
        }
        .hamburger-btn { display: flex; }
        .sidebar-overlay.active { display: block; }
        .toggle-sidebar { display: none; }
        .sidebar.collapsed { width: var(--sidebar-width); } /* Don't collapse on mobile */
    }
</style>

<button class="hamburger-btn" onclick="toggleMobileMenu()">
    <i data-lucide="menu"></i>
</button>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleMobileMenu()"></div>

<aside class="sidebar" id="sidebar">
    <button class="toggle-sidebar" onclick="toggleSidebar()">
        <i data-lucide="chevron-left" style="width: 16px;"></i>
    </button>
    
    <a href="index.php" class="brand-logo">
        <i data-lucide="pen-tool"></i>
        <span>SignMe</span>
    </a>

    <nav style="display: flex; flex-direction: column; flex: 1;">
        <p class="nav-group-label">เอกสาร</p>
        <a href="index.php?page=dashboard" class="nav-item <?php echo ($current_page == 'dashboard' && $current_status == '') ? 'active' : ''; ?>">
            <i data-lucide="layout-dashboard"></i> <span>แผงควบคุม</span>
        </a>
        <a href="index.php?page=dashboard&status=all" class="nav-item <?php echo $current_status == 'all' ? 'active' : ''; ?>">
            <i data-lucide="layers"></i> <span>เอกสารทั้งหมด</span>
        </a>
        <a href="index.php?page=dashboard&status=pending" class="nav-item <?php echo $current_status == 'pending' ? 'active' : ''; ?>">
            <i data-lucide="clock"></i> <span>รอลงนาม</span>
        </a>
        <a href="index.php?page=dashboard&status=sent" class="nav-item <?php echo $current_status == 'sent' ? 'active' : ''; ?>">
            <i data-lucide="send"></i> <span>ส่งแล้ว</span>
        </a>
        <a href="index.php?page=dashboard&status=signed" class="nav-item <?php echo $current_status == 'signed' ? 'active' : ''; ?>">
            <i data-lucide="check-circle"></i> <span>เสร็จสิ้น</span>
        </a>
        
        <?php if (($_SESSION['role'] ?? 'user') === 'admin'): ?>
        <p class="nav-group-label">การจัดการ</p>
        <a href="index.php?page=team" class="nav-item <?php echo $current_page == 'team' ? 'active' : ''; ?>">
            <i data-lucide="users"></i> <span>สมาชิกทีม</span>
        </a>
        <a href="index.php?page=settings" class="nav-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
            <i data-lucide="settings"></i> <span>ตั้งค่า</span>
        </a>
        <?php endif; ?>
    </nav>

    <div style="padding-top: 1rem; border-top: 1px solid var(--border); margin-top: auto;">
        <a href="index.php?page=logout" class="nav-item" style="color: #ef4444;">
            <i data-lucide="log-out"></i> <span>ออกจากระบบ</span>
        </a>
    </div>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('expanded');
        }
        
        // บันทึกสถานะไว้ใน LocalStorage เพื่อให้รีเฟรชหน้าแล้วยังเหมือนเดิม
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const btnIcon = document.querySelector('.hamburger-btn i');
        
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        
        if (sidebar.classList.contains('open')) {
            btnIcon.setAttribute('data-lucide', 'x');
        } else {
            btnIcon.setAttribute('data-lucide', 'menu');
        }
        lucide.createIcons();
    }

    // โหลดสถานะเดิมจาก LocalStorage
    window.addEventListener('DOMContentLoaded', () => {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        // เฉพาะหน้าจอใหญ่เท่านั้นที่โหลดสถานะ Collapsed
        if (isCollapsed && window.innerWidth > 1024) {
            document.getElementById('sidebar').classList.add('collapsed');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) mainContent.classList.add('expanded');
        }
        lucide.createIcons();
    });
</script>
