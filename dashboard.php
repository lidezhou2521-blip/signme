<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$statusFilter = $_GET['status'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// การลบเอกสาร
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $userId = $_SESSION['user_id'];
    $isAdmin = ($_SESSION['role'] ?? 'user') === 'admin';

    try {
        $pdo->beginTransaction();
        
        // ตรวจสอบสิทธิ์ก่อนลบ
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE id = ?");
            $stmt->execute([$deleteId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE id = ? AND user_id = ?");
            $stmt->execute([$deleteId, $userId]);
        }
        
        $doc = $stmt->fetch();
        if ($doc) {
            // ลบไฟล์ PDF บนเซิร์ฟเวอร์
            if (!empty($doc['file_path'])) {
                // ลบไฟล์ปัจจุบันที่อ้างอิงใน DB
                if (file_exists($doc['file_path'])) {
                    unlink($doc['file_path']);
                }
                
                // ตรวจสอบและลบไฟล์คู่กัน (ต้นฉบับ <-> Completed)
                if (strpos($doc['file_path'], '_completed.pdf') !== false) {
                    // ถ้าไฟล์ใน DB เป็นแบบ completed ให้หาทางลบไฟล์ต้นฉบับด้วย
                    $originalPath = str_replace('_completed.pdf', '.pdf', $doc['file_path']);
                    if (file_exists($originalPath)) {
                        unlink($originalPath);
                    }
                } else {
                    // ถ้าไฟล์ใน DB เป็นแบบต้นฉบับ ให้หาทางลบไฟล์ completed ด้วย
                    $completedPath = str_replace('.pdf', '_completed.pdf', $doc['file_path']);
                    if (file_exists($completedPath)) {
                        unlink($completedPath);
                    }
                }
            }
            
            // ลบข้อมูลที่เกี่ยวข้องตามลำดับ (ป้องกัน Foreign Key Error)
            $pdo->prepare("DELETE FROM signature_fields WHERE document_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM signers WHERE document_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$deleteId]);
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    header("Location: index.php?page=dashboard");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SignMe</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1 style="font-size: 1.5rem; font-weight: 700;">ภาพรวมเอกสาร</h1>
            <div class="flex items-center gap-4">
                <form action="index.php" method="GET" class="search-box">
                    <input type="hidden" name="page" value="dashboard">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <i data-lucide="search"></i>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อเอกสารหรือผู้ลงนาม..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </form>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
                <span style="font-weight: 500; font-size: 0.875rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
            </div>
        </header>

        <div class="upload-card animate-fade-in">
            <div>
                <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">พร้อมเซ็นเอกสารใหม่หรือยัง?</h2>
                <p style="opacity: 0.7; font-size: 0.875rem;">อัปโหลดไฟล์ PDF เพื่อเริ่มกระบวนการลงนามได้ทันที</p>
            </div>
            <a href="index.php?page=sign" class="btn" style="background: white; color: var(--primary);">
                <i data-lucide="plus"></i> อัปโหลดไฟล์ใหม่
            </a>
        </div>

        <div class="filter-tabs">
            <?php
            // คำนวณจำนวนในแต่ละสถานะ
            $uid = $_SESSION['user_id'];
            $c_all = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
            $c_all->execute([$uid]);
            $count_all = $c_all->fetchColumn();

            $c_pending = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status = 'pending' AND id NOT IN (SELECT s.document_id FROM signers s JOIN signature_fields f ON s.id = f.signer_id WHERE f.status = 'filled')");
            $c_pending->execute([$uid]);
            $count_pending = $c_pending->fetchColumn();

            $c_sent = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status = 'pending' AND id IN (SELECT s.document_id FROM signers s JOIN signature_fields f ON s.id = f.signer_id WHERE f.status = 'filled')");
            $c_sent->execute([$uid]);
            $count_sent = $c_sent->fetchColumn();

            $c_signed = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND status = 'signed'");
            $c_signed->execute([$uid]);
            $count_signed = $c_signed->fetchColumn();
            ?>
            <a href="index.php?page=dashboard&status=all" class="filter-btn <?php echo $statusFilter == 'all' ? 'active' : ''; ?>">
                <i data-lucide="layers"></i> ทั้งหมด (<?php echo $count_all; ?>)
            </a>
            <a href="index.php?page=dashboard&status=pending" class="filter-btn <?php echo $statusFilter == 'pending' ? 'active' : ''; ?>">
                <i data-lucide="pen"></i> รอลงนาม (<?php echo $count_pending; ?>)
            </a>
            <a href="index.php?page=dashboard&status=sent" class="filter-btn <?php echo $statusFilter == 'sent' ? 'active' : ''; ?>">
                <i data-lucide="send"></i> ส่งแล้ว (<?php echo $count_sent; ?>)
            </a>
            <a href="index.php?page=dashboard&status=signed" class="filter-btn <?php echo $statusFilter == 'signed' ? 'active' : ''; ?>">
                <i data-lucide="check"></i> เสร็จสิ้น (<?php echo $count_signed; ?>)
            </a>
        </div>

        <table class="doc-table animate-fade-in">
            <thead>
                <tr>
                    <th style="width: 50px;">เลือก</th>
                    <th>สถานะ</th>
                    <th>ผู้สร้าง</th>
                    <th>สร้างเมื่อ</th>
                    <th>ลงนามเมื่อ</th>
                    <th>ผู้ลงนาม</th>
                    <th style="text-align: right;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // ดึงข้อมูลเอกสารพร้อมชื่อผู้สร้าง
                $query = "SELECT d.*, u.full_name as creator_name, u.username as creator_uname 
                          FROM documents d 
                          JOIN users u ON d.user_id = u.id 
                          WHERE 1=1";
                
                // หากไม่ใช่ Admin ให้ดูได้เฉพาะของตัวเอง (หรือตามที่คุณต้องการ)
                if (($_SESSION['role'] ?? 'user') !== 'admin') {
                    $query .= " AND d.user_id = " . intval($_SESSION['user_id']);
                }

                $params = [];
                if ($searchTerm) {
                    $query .= " AND (d.file_name LIKE ? OR d.id IN (SELECT s.document_id FROM signers s WHERE s.name LIKE ? OR s.email LIKE ?))";
                    $params[] = "%$searchTerm%"; $params[] = "%$searchTerm%"; $params[] = "%$searchTerm%";
                }

                if ($statusFilter == 'pending') {
                    $query .= " AND d.status = 'pending' AND d.id NOT IN (SELECT s.document_id FROM signers s JOIN signature_fields f ON s.id = f.signer_id WHERE f.status = 'filled')";
                } elseif ($statusFilter == 'sent') {
                    $query .= " AND d.status = 'pending' AND d.id IN (SELECT s.document_id FROM signers s JOIN signature_fields f ON s.id = f.signer_id WHERE f.status = 'filled')";
                } elseif ($statusFilter == 'signed') {
                    $query .= " AND d.status = 'signed'";
                }

                $query .= " ORDER BY d.created_at DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $docs = $stmt->fetchAll();

                if (empty($docs)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">ไม่พบเอกสารในขณะนี้</td></tr>
                <?php else:
                    foreach ($docs as $doc): 
                        $pStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='signed' THEN 1 ELSE 0 END) as signed FROM signers WHERE document_id = ?");
                        $pStmt->execute([$doc['id']]);
                        $progress = $pStmt->fetch();
                ?>
                    <tr class="name-row">
                        <td colspan="7">
                            <div class="flex items-center gap-2">
                                <input type="checkbox">
                                <i data-lucide="file-pdf" style="color: #ef4444; width: 20px;"></i>
                                <span class="file-name-title"><?php echo htmlspecialchars($doc['file_name']); ?></span>
                            </div>
                        </td>
                    </tr>
                    <tr class="data-row">
                        <td class="desktop-only"></td>
                        <td data-label="ชื่อเอกสาร" class="mobile-only">
                            <div class="flex items-center gap-2">
                                <i data-lucide="file-pdf" style="color: #ef4444; width: 18px;"></i>
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($doc['file_name']); ?></span>
                            </div>
                        </td>
                        <td data-label="สถานะ">
                            <?php if ($doc['status'] == 'signed'): ?>
                                <span class="status-badge" style="background: #dcfce7; color: #166534;">เสร็จสิ้น</span>
                            <?php elseif ($progress['signed'] > 0): ?>
                                <span class="status-badge" style="background: #e0f2fe; color: #075985;">ลงนามแล้ว <?php echo $progress['signed'] . '/' . $progress['total']; ?></span>
                            <?php else: ?>
                                <span class="status-badge" style="background: #fef9c3; color: #854d0e;">รอลงนาม</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="ผู้สร้าง">
                            <div class="flex items-center gap-2">
                                <div style="width: 24px; height: 24px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; color: var(--primary); font-weight: 700; border: 1px solid var(--border);">
                                    <?php echo strtoupper(substr(($doc['creator_name'] ?? $doc['creator_uname'] ?? 'U'), 0, 1)); ?>
                                </div>
                                <span style="font-size: 0.85rem; font-weight: 500; color: var(--text-dark);"><?php echo htmlspecialchars($doc['creator_name'] ?? $doc['creator_uname'] ?? 'Unknown'); ?></span>
                            </div>
                        </td>
                        <td data-label="สร้างเมื่อ"><span style="color: var(--text-muted); font-size: 0.8rem;"><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></span></td>
                        <td data-label="ลงนามเมื่อ"><span style="color: var(--text-muted); font-size: 0.8rem;"><?php echo $doc['signed_at'] ? date('d/m/Y H:i', strtotime($doc['signed_at'])) : '-'; ?></span></td>
                        <td data-label="ผู้ลงนาม">
                            <?php 
                            $sStmt = $pdo->prepare("SELECT * FROM signers WHERE document_id = ?");
                            $sStmt->execute([$doc['id']]);
                            $signersArr = $sStmt->fetchAll();
                            foreach ($signersArr as $s) {
                                $statusBadge = ($s['status'] == 'signed') 
                                    ? "<span style='background:#ecfdf5; color:#10b981; padding:2px 8px; border-radius:12px; font-weight:600; display:inline-flex; align-items:center; gap:4px;'><i data-lucide='check-circle' style='width: 10px;'></i> ลงนามแล้ว</span>" 
                                    : "<span style='background:#fff7ed; color:#f97316; padding:2px 8px; border-radius:12px; font-weight:600; display:inline-flex; align-items:center; gap:4px;'><i data-lucide='clock' style='width: 10px;'></i> รอลงนาม</span>";

                                echo "<div class='flex items-center justify-between gap-2' style='font-size: 0.75rem; margin-bottom: 6px; background:#f8fafc; padding:6px 10px; border-radius:10px; border:1px solid #e2e8f0;'>";
                                echo "  <div class='flex flex-col' style='overflow:hidden;'>";
                                echo "    <span style='font-weight:700; color:#1e293b; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;'>" . htmlspecialchars($s['name']) . "</span>";
                                echo "    <div style='margin-top:2px;'>$statusBadge</div>";
                                echo "  </div>";
                                if ($s['status'] == 'pending') {
                                    $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/external_sign.php?token=" . $s['access_token'];
                                    echo "<button onclick='copyToClipboard(\"$link\")' style='background:var(--primary); color:white; border:none; cursor:pointer; padding:6px 10px; border-radius:8px; display:flex; align-items:center; gap:4px; transition:0.2s; flex-shrink:0;' title='คัดลอกลิงก์เพื่อส่งให้ผู้ลงนามมาเซ็น'><i data-lucide='copy' style='width: 12px;'></i> คัดลอกลิงก์เซ็น</button>";
                                } else {
                                    $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/external_sign.php?token=" . $s['access_token'];
                                    echo "<button onclick='copyToClipboard(\"$link\")' style='background:#10b981; color:white; border:none; cursor:pointer; padding:6px 10px; border-radius:8px; display:flex; align-items:center; gap:4px; transition:0.2s; flex-shrink:0;' title='คัดลอกลิงก์ดาวน์โหลดเอกสารที่เซ็นแล้ว'><i data-lucide='share-2' style='width: 12px;'></i> ส่งลิงก์โหลด</button>";
                                }
                                echo "</div>";
                            }
                            ?>
                        </td>
                        <td data-label="จัดการ">
                            <div class="flex gap-2 justify-end">
                                <button onclick="viewAuditTrail(<?php echo $doc['id']; ?>)" class="action-btn" title="ประวัติเอกสาร (Audit Trail)"><i data-lucide="list" style="width: 16px;"></i></button>
                                <button onclick="finalizeAndDownload(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['file_name']); ?>', true)" class="action-btn" title="ดูตัวอย่าง"><i data-lucide="eye" style="width: 16px;"></i></button>
                                <?php 
                                $hasSignatures = ($progress['signed'] > 0);
                                if ($doc['status'] == 'signed'): 
                                    $completedPath = $doc['file_path'];
                                    if (strpos($completedPath, '_completed.pdf') === false) {
                                        $completedPath = str_replace('.pdf', '_completed.pdf', $doc['file_path']);
                                    }
                                    $isReady = file_exists($completedPath);
                                    if ($isReady): ?>
                                        <a href="<?php echo htmlspecialchars($completedPath); ?>" download class="action-btn" style="background: var(--success); color: white;" title="ดาวน์โหลดฉบับสมบูรณ์"><i data-lucide="file-check" style="width: 16px;"></i></a>
                                    <?php else: ?>
                                        <button onclick="finalizeAndDownload(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['file_name']); ?>')" class="action-btn" style="background: var(--success); color: white;" title="ดาวน์โหลดฉบับสมบูรณ์"><i data-lucide="file-check" style="width: 16px;"></i></button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($hasSignatures): ?>
                                        <button onclick="finalizeAndDownload(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['file_name']); ?>')" class="action-btn" style="background: var(--primary); color: white;" title="ดาวน์โหลดฉบับที่มีลายเซ็นบางส่วน"><i data-lucide="download" style="width: 16px;"></i></button>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download class="action-btn" title="ดาวน์โหลดต้นฉบับ"><i data-lucide="download" style="width: 16px;"></i></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (($_SESSION['role'] ?? 'user') === 'admin'): ?>
                                    <a href="index.php?page=dashboard&delete_id=<?php echo $doc['id']; ?>" onclick="return confirm('คุณต้องการลบเอกสารนี้ใช่หรือไม่?');" class="action-btn" style="background: #ef4444; color: white;" title="ลบเอกสาร"><i data-lucide="trash-2" style="width: 16px;"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </main>

    <!-- Audit Trail Modal -->
    <div class="modal" id="audit-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 3000;">
        <div class="modal-content animate-fade-in" style="background: white; padding: 2rem; border-radius: 16px; width: 600px; max-width: 90vw; max-height: 80vh; overflow-y: auto;">
            <div class="flex justify-between items-center" style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
                <h3 style="font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.5rem;"><i data-lucide="activity"></i> ประวัติเอกสาร</h3>
                <button onclick="document.getElementById('audit-modal').style.display='none'" style="background:none; border:none; cursor:pointer; color:var(--text-muted);"><i data-lucide="x"></i></button>
            </div>
            <div id="audit-timeline" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Timeline items will be injected here -->
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function copyToClipboard(text) {
            // ลองใช้ Modern API ก่อน
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('คัดลอกลิงก์เรียบร้อย!');
                }).catch(() => fallbackCopy(text));
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            // ทำให้ textarea มองไม่เห็น
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            textArea.style.top = "0";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                const successful = document.execCommand('copy');
                if (successful) alert('คัดลอกลิงก์เรียบร้อย!');
                else throw new Error();
            } catch (err) {
                // ถ้ายังไม่ได้จริงๆ ให้แสดง Prompt ให้ผู้ใช้คัดลอกเอง
                window.prompt("กรุณาคัดลอกลิงก์ด้านล่างนี้:", text);
            }
            document.body.removeChild(textArea);
        }
        async function finalizeAndDownload(docId, filePath, fileName, isPreview = false) {
            try {
                const btn = event.currentTarget; const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin" style="width:16px;"></i>'; btn.disabled = true; lucide.createIcons();

                // ถ้า filePath เป็น _completed.pdf แสดงว่ามีลายเซ็น bake ไว้แล้ว — เปิดตรงๆ ได้เลย
                if (filePath.includes('_completed.pdf')) {
                    const url = filePath;
                    if (isPreview) { window.open(url, '_blank'); } else {
                        const link = document.createElement('a'); link.href = url; link.download = 'COMPLETED_' + fileName; link.click();
                    }
                    btn.innerHTML = originalHtml; btn.disabled = false; lucide.createIcons();
                    return;
                }

                // กรณีไฟล์ต้นฉบับ (ยังไม่มีลายเซ็น bake) — ดึงลายเซ็นจาก DB มาวาดก่อน
                const response = await fetch(`api_get_signatures.php?id=${docId}`);
                const fields = await response.json();
                const pdfBytes = await fetch(filePath).then(res => res.arrayBuffer());
                const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
                for (const field of fields) {
                    if (!field.signature_data) continue;
                    const page = pdfDoc.getPage(field.page_number - 1);
                    const { width, height } = page.getSize();
                    const base64Data = field.signature_data.split(',')[1];
                    const binaryStr = atob(base64Data);
                    const bytes = new Uint8Array(binaryStr.length);
                    for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
                    const sigImage = await pdfDoc.embedPng(bytes);
                    const imgDims = sigImage.scale(1);
                    const fieldW = field.width * width, fieldH = field.height * height;
                    const fieldX = field.x * width, fieldY = height - (field.y * height) - fieldH;
                    const fitScale = Math.min(fieldW / imgDims.width, fieldH / imgDims.height);
                    page.drawImage(sigImage, {
                        x: fieldX + (fieldW - imgDims.width * fitScale) / 2,
                        y: fieldY + (fieldH - imgDims.height * fitScale) / 2,
                        width: imgDims.width * fitScale, height: imgDims.height * fitScale
                    });
                }
                const mergedPdfBytes = await pdfDoc.save();
                const blob = new Blob([mergedPdfBytes], { type: 'application/pdf' });
                const url = URL.createObjectURL(blob);
                if (isPreview) { window.open(url, '_blank'); } else {
                    const link = document.createElement('a'); link.href = url; link.download = 'COMPLETED_' + fileName; link.click();
                }
                btn.innerHTML = originalHtml; btn.disabled = false; lucide.createIcons();
            } catch (error) { alert('เกิดข้อผิดพลาด: ' + error.message); location.reload(); }
        }

        async function viewAuditTrail(docId) {
            const timeline = document.getElementById('audit-timeline');
            timeline.innerHTML = '<div style="text-align:center; padding: 2rem;"><i data-lucide="loader-2" class="animate-spin"></i> กำลังโหลดประวัติ...</div>';
            document.getElementById('audit-modal').style.display = 'flex';
            lucide.createIcons();

            try {
                const res = await fetch(`api_get_logs.php?id=${docId}`);
                const data = await res.json();
                
                if (!data.success) throw new Error(data.message);
                
                if (data.logs.length === 0) {
                    timeline.innerHTML = '<div style="text-align:center; color: var(--text-muted); padding: 1rem;">ไม่มีประวัติสำหรับเอกสารนี้</div>';
                    return;
                }

                timeline.innerHTML = '';
                data.logs.forEach(log => {
                    const date = new Date(log.created_at);
                    const formattedDate = date.toLocaleString('th-TH');
                    
                    let icon = 'activity';
                    let color = 'var(--text-muted)';
                    let actionText = log.action;
                    
                    if (log.action === 'created') { icon = 'file-plus'; color = 'var(--primary)'; actionText = 'สร้างเอกสาร'; }
                    else if (log.action === 'sent') { icon = 'send'; color = '#0ea5e9'; actionText = 'ส่งเอกสาร'; }
                    else if (log.action === 'viewed') { icon = 'eye'; color = '#f59e0b'; actionText = 'เปิดอ่านเอกสาร'; }
                    else if (log.action === 'signed') { icon = 'pen-tool'; color = 'var(--success)'; actionText = 'ลงนามเอกสาร'; }

                    const itemHtml = `
                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                            <div style="background: ${color}20; color: ${color}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i data-lucide="${icon}" style="width: 16px;"></i>
                            </div>
                            <div style="flex: 1; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
                                <div style="font-weight: 600; font-size: 0.95rem;">${actionText}</div>
                                <div style="font-size: 0.85rem; color: var(--text-dark); margin-top: 2px;">โดย: ${log.actor_name} <span style="color: var(--text-muted); font-size: 0.75rem;">(IP: ${log.actor_ip})</span></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">${formattedDate}</div>
                            </div>
                        </div>
                    `;
                    timeline.insertAdjacentHTML('beforeend', itemHtml);
                });
                lucide.createIcons();
            } catch (error) {
                timeline.innerHTML = `<div style="color: red; padding: 1rem;">เกิดข้อผิดพลาด: ${error.message}</div>`;
            }
        }
    </script>
</body>
</html>
