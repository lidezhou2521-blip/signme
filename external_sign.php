<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
if (!$token) die('Invalid Access Token');

$stmt = $pdo->prepare("SELECT s.*, d.file_name, d.file_path FROM signers s JOIN documents d ON s.document_id = d.id WHERE s.access_token = ?");
$stmt->execute([$token]);
$signer = $stmt->fetch();

if (!$signer) die('Access Token not found');
if ($signer['status'] === 'signed') {
    $downloadPath = $signer['file_path'];
    // ตรวจสอบว่ามีไฟล์ที่ประมวลผลแล้วหรือไม่
    if (strpos($downloadPath, '_completed.pdf') === false) {
        $checkPath = str_replace('.pdf', '_completed.pdf', $signer['file_path']);
        if (file_exists($checkPath)) $downloadPath = $checkPath;
    }
    die("
    <div style='font-family:\"Noto Sans Thai\", sans-serif; text-align:center; padding: 5rem 2rem; background:#f8fafc; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;'>
        <div style='background:white; padding:3rem; border-radius:32px; box-shadow:0 20px 50px rgba(0,0,0,0.05); max-width:450px; width:100%;'>
            <div style='background:#ecfdf5; width:80px; height:80px; border-radius:100%; display:flex; align-items:center; justify-content:center; margin:0 auto 2rem;'>
                <div style='color:#10b981; font-size: 3rem;'>✓</div>
            </div>
            <h1 style='color:#0f172a; margin-bottom:1rem; font-weight:800;'>ลงนามเรียบร้อยแล้ว!</h1>
            <p style='color:#64748b; margin-bottom:2rem; line-height:1.6;'>ขอบคุณที่คุณร่วมลงนามในเอกสาร<br><b>" . htmlspecialchars($signer['file_name']) . "</b><br>คุณสามารถดาวน์โหลดเอกสารเก็บไว้เป็นหลักฐานได้ด้านล่างนี้</p>
            <a href='{$downloadPath}' download style='display:block; background:#2563eb; color:white; padding: 1.25rem; border-radius: 16px; text-decoration:none; font-weight:700; box-shadow:0 10px 20px rgba(37,99,235,0.2); transition:0.3s;'>ดาวน์โหลดเอกสาร</a>
            <p style='font-size:0.85rem; color:#94a3b8; margin-top:2rem;'>คุณสามารถปิดหน้านี้ได้ทันที</p>
        </div>
    </div>
    ");
}

// บันทึกประวัติการเปิดอ่าน (เช็ค Session เพื่อไม่ให้บันทึกซ้ำซ้อนเวลา Refresh)
if (!isset($_SESSION['viewed_' . $token])) {
    logDocumentActivity($pdo, $signer['document_id'], 'viewed', $signer['name']);
    $_SESSION['viewed_' . $token] = true;
}

// ตรวจสอบการกรอกรหัสผ่าน (ถ้ามีตั้งไว้)
$showAuth = !empty($signer['access_code']);
$isAuthenticated = false;

if ($showAuth) {
    if (isset($_POST['access_code']) && $_POST['access_code'] === $signer['access_code']) {
        $isAuthenticated = true;
    }
} else {
    $isAuthenticated = true;
}

$stmt = $pdo->prepare("SELECT * FROM signature_fields WHERE signer_id = ?");
$stmt->execute([$signer['id']]);
$fields = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงนามเอกสาร - <?php echo htmlspecialchars($signer['file_name']); ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <style>
        @font-face {
            font-family: 'Noto Sans Thai';
            src: url('fonts/NotoSansThai-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Thai';
            src: url('fonts/NotoSansThai-Bold.ttf') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        body { margin: 0; padding-top: 80px; background: #f1f5f9; font-family: 'Noto Sans Thai', Sarabun, sans-serif; }
        .signer-header { position: fixed; top: 0; left: 0; right: 0; height: 80px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 2rem; z-index: 2000; box-shadow: 0 2px 10px rgba(0,0,0,0.05); box-sizing: border-box; }
        .header-left { flex: 1; display: flex; align-items: center; gap: 1.5rem; overflow: hidden; }
        .header-right { width: 160px; display: flex; justify-content: flex-end; }
        
        .viewer-container { max-width: 1000px; margin: 0 auto; padding: 2rem; position: relative; }
        #pdf-viewer { display: flex; flex-direction: column; align-items: center; gap: 2rem; }

        /* Floating Nav */
        .signing-nav { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); z-index: 2500; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
        .nav-btn { background: var(--primary); color: white; border: none; padding: 1rem 2rem; border-radius: 99px; font-weight: 700; box-shadow: 0 15px 30px rgba(37, 99, 235, 0.3); cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: var(--transition); border: 2px solid rgba(255,255,255,0.2); }
        .nav-btn:hover { transform: scale(1.05); background: var(--primary-hover); }
        .nav-btn:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; }
        
        .pulse-indicator { position: absolute; width: 100%; height: 100%; border: 4px solid var(--primary); border-radius: 8px; animation: pulse 2s infinite; pointer-events: none; display: none; }
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.8; } 70% { transform: scale(1.2); opacity: 0; } 100% { transform: scale(1); opacity: 0; } }
        
        .pdf-page-container { position: relative; box-shadow: var(--shadow-lg); margin-bottom: 2rem; background: white; max-width: 95vw; }
        
        .clickable-field { 
            position: absolute; border: 2px dashed var(--primary); background: rgba(37, 99, 235, 0.1); 
            cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; 
            z-index: 100; border-radius: 8px; animation: pulse 2s infinite; 
        }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); } 100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); } }
        .clickable-field.filled { border: none; background: transparent; animation: none; }
        .clickable-field.filled img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .btn-style.active { background: var(--primary) !important; color: white !important; }

        #signature-canvas { border: 2px solid var(--border); width: 100%; height: 250px; background: #fafafa; border-radius: 16px; touch-action: none; }
        .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 3000; backdrop-filter: blur(8px); }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2.5rem; border-radius: 28px; width: 500px; max-width: 90vw; }

        /* Auth Screen Styling */
        .auth-container { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: #f1f5f9; z-index: 5000; display: flex; align-items: center; justify-content: center; }
        .auth-card { background: white; padding: 3rem; border-radius: 32px; box-shadow: var(--shadow-xl); width: 400px; max-width: 90vw; text-align: center; }

        @media (max-width: 768px) {
            .signer-header { padding: 0 1rem; height: 70px; }
            .header-left h2 { font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
            .header-right { width: 120px; }
            .viewer-container { padding: 1rem; }
            .modal-content { width: 95vw; padding: 1.5rem; }
            .auth-card { padding: 2rem; }
            body { padding-top: 70px; }
        }
    </style>
</head>
<body>

    <?php if (!$isAuthenticated): ?>
        <!-- หน้าจอตรวจสอบรหัสผ่าน -->
        <div class="auth-container">
            <div class="auth-card animate-fade-in">
                <div style="background: #eff6ff; width: 64px; height: 64px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: #2563eb;">
                    <i data-lucide="lock" style="width: 32px; height: 32px;"></i>
                </div>
                <h2 style="font-weight: 800; font-size: 1.5rem; margin-bottom: 0.5rem;">ยืนยันตัวตน</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">เอกสารนี้มีการตั้งรหัสผ่านในการเข้าถึง<br>กรุณากรอกรหัสผ่านที่ได้รับจากอีเมล</p>
                
                <form method="POST" action="external_sign.php?token=<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group" style="text-align: left;">
                        <label class="form-label">รหัสผ่านเข้าถึงเอกสาร</label>
                        <input type="password" name="access_code" class="form-control" placeholder="••••••" required autofocus style="text-align: center; letter-spacing: 0.5rem; font-size: 1.25rem;">
                    </div>
                    <?php if (isset($_POST['access_code'])): ?>
                        <p style="color: #dc2626; font-size: 0.85rem; margin-bottom: 1rem;">รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง</p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        ยืนยันและเปิดเอกสาร
                    </button>
                </form>
            </div>
        </div>
        <script>lucide.createIcons();</script>
    <?php else: ?>
        <div class="signing-nav" id="signing-nav">
            <button class="nav-btn" id="next-field-btn">
                <i data-lucide="chevron-down"></i>
                <span>เริ่มลงนาม</span>
            </button>
            <div style="font-size: 0.75rem; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 10px; backdrop-filter: blur(4px);" id="signing-progress">
                เหลืออีก <span id="pending-count">0</span> จุด
            </div>
        </div>
        <!-- หน้าจอลงนามปกติ -->
        <header class="signer-header">
            <div class="header-left">
                <div class="brand-logo" style="margin-bottom: 0; font-size: 1.5rem;">SignMe</div>
                <div style="border-left: 1px solid var(--border); padding-left: 1.5rem; overflow: hidden;">
                    <p style="font-weight: 800; font-size: 1.1rem; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($signer['file_name']); ?></p>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">ผู้ลงนาม: <span style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($signer['name']); ?></span></p>
                </div>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" id="finish-signing" disabled style="padding: 0.75rem 1.25rem;">
                    <i data-lucide="check-circle"></i> เสร็จสิ้น
                </button>
            </div>
        </header>

        <main class="viewer-container">
            <div id="pdf-viewer"></div>
        </main>

        <div class="modal" id="sign-modal">
            <div class="modal-content animate-fade-in">
                <div class="flex justify-between items-center" style="margin-bottom: 1.5rem;">
                    <h3 style="font-weight: 700; margin: 0;">ลงลายมือชื่อ</h3>
                    <button onclick="closeModal()" style="background:none; border:none; cursor:pointer; color:var(--text-muted);"><i data-lucide="x"></i></button>
                </div>
                <div id="signature-pad-container">
                    <canvas id="signature-canvas"></canvas>
                    <div class="flex gap-3 items-center justify-center" style="margin-top: 1rem;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">สีปากกา:</span>
                        <button type="button" class="pen-color-btn active" data-color="#000000" style="width: 24px; height: 24px; border-radius: 50%; background: #000000; border: 2px solid white; outline: 1px solid #cbd5e1; cursor: pointer;"></button>
                        <button type="button" class="pen-color-btn" data-color="#0000bb" style="width: 24px; height: 24px; border-radius: 50%; background: #0000bb; border: 2px solid white; outline: 1px solid #cbd5e1; cursor: pointer;"></button>
                        <button type="button" class="pen-color-btn" data-color="#cc0000" style="width: 24px; height: 24px; border-radius: 50%; background: #cc0000; border: 2px solid white; outline: 1px solid #cbd5e1; cursor: pointer;"></button>
                    </div>
                </div>
                <div id="text-input-container" style="display: none;">
                    <input type="text" id="field-text-input" class="form-control" placeholder="กรอกข้อความที่นี่..." style="font-size: 1.25rem; padding: 1rem; margin-bottom: 1rem;">
                    <div class="flex gap-2 items-center" style="margin-bottom: 1rem; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 120px;">
                            <label style="font-size: 0.75rem; margin-bottom: 0.25rem; display: block;">แบบอักษร</label>
                            <select id="font-family" class="form-control" style="padding: 0.5rem; font-size: 0.9rem;">
                                <option value="'Noto Sans Thai', sans-serif">Noto Sans Thai</option>
                                <option value="Sarabun, sans-serif">Sarabun (ไทย)</option>
                                <option value="'Courier New', Courier, monospace">Courier (พิมพ์ดีด)</option>
                                <option value="'Times New Roman', Times, serif">Times (ทางการ)</option>
                                <option value="cursive">Handwriting (ลายมือ)</option>
                            </select>
                        </div>
                        <div class="form-group" style="width: 80px;">
                            <label style="font-size: 0.75rem; margin-bottom: 0.25rem; display: block;">ขนาด</label>
                            <input type="number" id="font-size" class="form-control" value="18" min="10" max="200" style="padding: 0.5rem; font-size: 0.9rem;">
                        </div>
                        <div class="form-group" style="width: 60px;">
                            <label style="font-size: 0.75rem; margin-bottom: 0.25rem; display: block;">สี</label>
                            <input type="color" id="font-color" value="#000000" style="width: 100%; height: 38px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; padding: 2px;">
                        </div>
                    </div>
                    <div class="flex gap-2 items-center" style="margin-bottom: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label style="font-size: 0.75rem; margin-bottom: 0.25rem; display: block;">ความหนา</label>
                            <select id="font-weight" class="form-control" style="padding: 0.5rem; font-size: 0.9rem;">
                                <option value="300">บาง (Thin)</option>
                                <option value="400" selected>ปกติ (Normal)</option>
                                <option value="700">หนา (Bold)</option>
                                <option value="900">หนาพิเศษ (Black)</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; gap: 0.5rem; align-items: flex-end; height: 58px;">
                            <button type="button" class="btn btn-style" id="btn-bold" title="ตัวหนา" style="padding: 0.5rem 0.75rem; background: #f1f5f9;"><i data-lucide="bold" style="width:16px;"></i></button>
                            <button type="button" class="btn btn-style" id="btn-italic" title="ตัวเอียง" style="padding: 0.5rem 0.75rem; background: #f1f5f9;"><i data-lucide="italic" style="width:16px;"></i></button>
                        </div>
                    </div>
                </div>
                <div class="flex justify-between items-center" style="margin-top: 1.5rem;">
                    <button class="btn" style="background: #f1f5f9;" id="clear-signature">ล้าง</button>
                    <button class="btn btn-primary" id="confirm-field">ยืนยัน</button>
                </div>
            </div>
        </div>

        <script>
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            const fields = <?php echo json_encode($fields); ?>;
            const signerToken = '<?php echo $token; ?>';
            const pdfUrl = '<?php echo $signer['file_path']; ?>';
            const signerName = '<?php echo htmlspecialchars($signer['name'], ENT_QUOTES); ?>';
            
            let signaturePad = null;
            let activeFieldId = null;
            const filledFields = new Set();
            const signaturesMap = new Map();

            function updateProgress() {
                const total = fields.length;
                const remaining = total - filledFields.size;
                document.getElementById('pending-count').innerText = remaining;
                
                const btn = document.getElementById('next-field-btn');
                if (remaining === 0) {
                    document.getElementById('finish-signing').disabled = false;
                    btn.innerHTML = '<i data-lucide="check"></i> <span>เสร็จสิ้นการลงนาม</span>';
                    btn.onclick = () => document.getElementById('finish-signing').click();
                } else if (filledFields.size === 0) {
                    btn.innerHTML = '<i data-lucide="chevron-down"></i> <span>เริ่มลงนาม</span>';
                    btn.onclick = goToNextField;
                } else {
                    btn.innerHTML = '<i data-lucide="arrow-down-right"></i> <span>ไปจุดถัดไป</span>';
                    btn.onclick = goToNextField;
                }
                lucide.createIcons();
            }

            function goToNextField() {
                const nextField = fields.find(f => !filledFields.has(f.id.toString()));
                if (nextField) {
                    const el = document.getElementById(`field-${nextField.id}`);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        // แสดงเอฟเฟกต์ Pulsing ชั่วคราว
                        const pulse = document.createElement('div');
                        pulse.className = 'pulse-indicator';
                        pulse.style.display = 'block';
                        el.appendChild(pulse);
                        setTimeout(() => { 
                            pulse.remove();
                            openModal(nextField.id); 
                        }, 800);
                    }
                }
            }

            let isBold = false;
            let isItalic = false;

            function updatePreview() {
                const input = document.getElementById('field-text-input');
                const fontFamily = document.getElementById('font-family').value;
                const fontColor = document.getElementById('font-color').value;
                const fontWeight = document.getElementById('font-weight').value;
                
                input.style.fontFamily = fontFamily;
                input.style.color = fontColor;
                input.style.fontWeight = isBold ? '900' : fontWeight;
                input.style.fontStyle = isItalic ? 'italic' : 'normal';
            }

            document.getElementById('font-family').addEventListener('change', updatePreview);
            document.getElementById('font-size').addEventListener('input', updatePreview);
            document.getElementById('font-color').addEventListener('input', updatePreview);
            document.getElementById('font-weight').addEventListener('change', updatePreview);

            document.getElementById('btn-bold').onclick = () => {
                isBold = !isBold;
                document.getElementById('btn-bold').classList.toggle('active', isBold);
                updatePreview();
            };
            document.getElementById('btn-italic').onclick = () => {
                isItalic = !isItalic;
                document.getElementById('btn-italic').classList.toggle('active', isItalic);
                updatePreview();
            };

            async function init() {
                try {
                    // รอให้ฟอนต์โหลดเสร็จก่อนเริ่มงาน เพื่อป้องกัน Canvas วาดฟอนต์ผิด
                    if (document.fonts) await document.fonts.ready;
                    
                    const data = await fetch(pdfUrl).then(res => res.arrayBuffer());
                    const pdfDoc = await pdfjsLib.getDocument({data: new Uint8Array(data)}).promise;
                    const viewer = document.getElementById('pdf-viewer');

                    console.log("Fields to sign:", fields);
                    if (fields.length === 0) {
                        alert("ไม่พบช่องที่คุณต้องลงนามในเอกสารนี้ หากเป็นข้อผิดพลาด กรุณาติดต่อผู้ส่งเอกสาร");
                        document.getElementById('finish-signing').disabled = false;
                    }

                    for (let i = 1; i <= pdfDoc.numPages; i++) {
                        const page = await pdfDoc.getPage(i);
                        const renderScale = 3.5;
                        const displayScale = 1.5;
                        const displayViewport = page.getViewport({scale: displayScale});
                        const renderViewport = page.getViewport({scale: renderScale});
                        
                        const container = document.createElement('div');
                        container.className = 'pdf-page-container';
                        container.style.width = displayViewport.width + 'px';
                        container.style.height = displayViewport.height + 'px';
                        
                        const canvas = document.createElement('canvas');
                        canvas.width = renderViewport.width;
                        canvas.height = renderViewport.height;
                        canvas.style.width = '100%';
                        canvas.style.height = '100%';
                        await page.render({canvasContext: canvas.getContext('2d'), viewport: renderViewport}).promise;
                        container.appendChild(canvas);

                        fields.filter(f => parseInt(f.page_number) === i).forEach(f => {
                            const fieldDiv = document.createElement('div');
                            fieldDiv.className = 'clickable-field';
                            fieldDiv.id = `field-${f.id}`;
                            fieldDiv.style.left = (f.x * 100) + '%';
                            fieldDiv.style.top = (f.y * 100) + '%';
                            fieldDiv.style.width = (f.width * 100) + '%';
                            fieldDiv.style.height = (f.height * 100) + '%';
                            
                            let label = 'เซ็นที่นี่';
                            let icon = 'edit-3';
                            if (f.field_type === 'text') { label = 'กรอกข้อความ'; icon = 'type'; }
                            if (f.field_type === 'date') { label = 'ระบุวันที่'; icon = 'calendar'; }

                            fieldDiv.innerHTML = `<i data-lucide="${icon}" style="width:16px;"></i><span style="font-size:10px; font-weight:600;">${label}</span>`;
                            fieldDiv.onclick = () => openModal(f.id);
                            container.appendChild(fieldDiv);
                        });
                        viewer.appendChild(container);
                    }
                    lucide.createIcons();
                    initSignaturePad();
                    updateProgress(); // อัปเดตสถานะเมื่อทุกอย่างพร้อม
                } catch (e) { 
                    console.error("Initialization Error:", e);
                    alert('Error: ' + e.message); 
                }
            }

            function initSignaturePad() {
                const canvas = document.getElementById('signature-canvas');
                if (!canvas) return;
                signaturePad = new SignaturePad(canvas, { 
                    backgroundColor: 'rgba(255, 255, 255, 0)',
                    penColor: '#000000'
                });

                // จัดการเรื่องการเปลี่ยนสีปากกา
                document.querySelectorAll('.pen-color-btn').forEach(btn => {
                    btn.onclick = () => {
                        const color = btn.getAttribute('data-color');
                        signaturePad.penColor = color;
                        
                        // อัปเดต UI ปุ่มที่เลือก
                        document.querySelectorAll('.pen-color-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        // ใส่เอฟเฟกต์ Outline ให้เห็นชัดว่าเลือกสีไหน
                        document.querySelectorAll('.pen-color-btn').forEach(b => b.style.outlineColor = '#cbd5e1');
                        btn.style.outlineColor = 'var(--primary)';
                    };
                });
            }

            function openModal(fieldId) {
                activeFieldId = fieldId;
                const field = fields.find(f => f.id == fieldId);
                if (!field) { console.error("Field not found:", fieldId); return; }

                const modal = document.getElementById('sign-modal');
                const sigContainer = document.getElementById('signature-pad-container');
                const textContainer = document.getElementById('text-input-container');
                const textInput = document.getElementById('field-text-input');
                const title = modal.querySelector('h3');

                modal.classList.add('active');
                updatePreview();
                
                if (field.field_type === 'signature' || !field.field_type) {
                    title.innerText = 'ลงลายมือชื่อ';
                    sigContainer.style.display = 'block';
                    textContainer.style.display = 'none';
                    document.getElementById('clear-signature').style.display = 'block';
                    setTimeout(() => {
                        const canvas = document.getElementById('signature-canvas');
                        const ratio = Math.max(window.devicePixelRatio || 1, 1);
                        const modalWidth = document.querySelector('.modal-content').offsetWidth - 80;
                        canvas.style.width = modalWidth + 'px';
                        canvas.style.height = (modalWidth * (field.height / field.width)) + 'px';
                        canvas.width = canvas.offsetWidth * ratio;
                        canvas.height = canvas.offsetHeight * ratio;
                        const ctx = canvas.getContext("2d");
                        ctx.scale(ratio, ratio);
                        signaturePad.clear();
                    }, 100);
                } else {
                    sigContainer.style.display = 'none';
                    textContainer.style.display = 'block';
                    document.getElementById('clear-signature').style.display = 'none';
                    if (field.field_type === 'date') {
                        title.innerText = 'ระบุวันที่';
                        textInput.type = 'date';
                        textInput.value = new Date().toISOString().split('T')[0];
                    } else {
                        title.innerText = 'กรอกข้อความ';
                        textInput.type = 'text';
                        textInput.value = signerName;
                        setTimeout(() => textInput.focus(), 100);
                    }
                }
            }

            function formatThaiDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                const day = date.getDate();
                const monthNames = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
                const month = monthNames[date.getMonth()];
                const year = (date.getFullYear() + 543).toString().slice(-2);
                
                let result = `${day} ${month} ${year}`;
                const thaiNumerals = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                return result.replace(/[0-9]/g, m => thaiNumerals[m]);
            }

            function closeModal() { document.getElementById('sign-modal').classList.remove('active'); }
            document.getElementById('clear-signature').onclick = () => signaturePad.clear();
            document.getElementById('confirm-field').onclick = () => {
                const field = fields.find(f => f.id == activeFieldId);
                let dataUrl = '';

                if (field.field_type === 'signature' || !field.field_type) {
                    if (signaturePad.isEmpty()) return alert('กรุณาลงลายมือชื่อ');
                    dataUrl = signaturePad.toDataURL('image/png');
                } else {
                    let val = document.getElementById('field-text-input').value;
                    if (!val) return alert('กรุณากรอกข้อมูล');
                    
                    // หากเป็นฟิลด์วันที่ ให้แปลงรูปแบบเป็นภาษาไทยและเลขไทย
                    if (field.field_type === 'date') {
                        val = formatThaiDate(val);
                    }
                    
                    const fontFamily = document.getElementById('font-family').value;
                    const fontSize = document.getElementById('font-size').value;
                    const fontColor = document.getElementById('font-color').value;

                    // ดึงขนาดของกล่องจริงบนหน้าจอ เพื่อสร้าง Canvas ที่มีสัดส่วน (Aspect Ratio) ตรงกัน
                    // ป้องกันปัญหาฟอนต์บิดเบี้ยว (ยืด/หด) เมื่อนำไปฝังใน PDF
                    const fd = document.getElementById(`field-${activeFieldId}`);
                    const rect = fd.getBoundingClientRect();
                    
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // ใช้ความละเอียด 4 เท่าของหน้าจอเพื่อให้ภาพคมชัดเวลาพิมพ์
                    const scale = 4;
                    canvas.width = rect.width * scale;
                    canvas.height = rect.height * scale;
                    
                    // ปรับขนาดฟอนต์ให้สัมพันธ์กับสเกลของ Canvas
                    const scaledFontSize = parseInt(fontSize) * (canvas.height / 100); // เทียบสัดส่วนความสูง
                    
                    const fontWeight = isBold ? '900' : document.getElementById('font-weight').value;
                    const fontStyle = isItalic ? 'italic' : '';
                    
                    ctx.font = `${fontStyle} ${fontWeight} ${scaledFontSize}px ${fontFamily}`;
                    ctx.fillStyle = fontColor;
                    
                    // จัดตำแหน่งข้อความให้อยู่ตรงกลางกล่องพอดี
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(val, canvas.width / 2, canvas.height / 2);
                    
                    dataUrl = canvas.toDataURL('image/png');
                }

                signaturesMap.set(activeFieldId, dataUrl);
                const fd = document.getElementById(`field-${activeFieldId}`);
                fd.innerHTML = `<img src="${dataUrl}">`;
                fd.classList.add('filled');
                filledFields.add(activeFieldId.toString());
                updateProgress();
                closeModal();
                
                // หลังจากปิด Modal ให้สแกนหาจุดถัดไปแล้วพาไปหาแบบเท่ๆ
                setTimeout(goToNextField, 400);
            };



            document.getElementById('finish-signing').onclick = async () => {
                const btn = document.getElementById('finish-signing');
                btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin"></i> กำลังบันทึกและประมวลผล PDF...'; lucide.createIcons();
                const signatures = [];
                signaturesMap.forEach((data, id) => signatures.push({ id, data }));
                try {
                    const allSigsRes = await fetch(`api_get_signatures.php?id=<?php echo $signer['document_id']; ?>`);
                    const previousFields = await allSigsRes.json();
                    
                    const allFieldsToFlatten = [...previousFields];
                    signatures.forEach(sig => {
                        const fieldDef = fields.find(f => f.id == sig.id);
                        if (fieldDef) {
                            allFieldsToFlatten.push({ ...fieldDef, signature_data: sig.data });
                        }
                    });

                    const pdfBytes = await fetch(pdfUrl).then(res => res.arrayBuffer());
                    const pdfDocLib = await PDFLib.PDFDocument.load(pdfBytes);
                    
                    for (const field of allFieldsToFlatten) {
                        if (!field.signature_data) continue;
                        const page = pdfDocLib.getPage(field.page_number - 1);
                        const { width, height } = page.getSize();
                        
                        const base64Data = field.signature_data.split(',')[1];
                        const binaryStr = atob(base64Data);
                        const bytes = new Uint8Array(binaryStr.length);
                        for (let i = 0; i < binaryStr.length; i++) bytes[i] = binaryStr.charCodeAt(i);
                        
                        const sigImage = await pdfDocLib.embedPng(bytes);
                        page.drawImage(sigImage, { 
                            x: field.x * width, 
                            y: height - (field.y * height) - (field.height * height), 
                            width: field.width * width, 
                            height: field.height * height 
                        });
                    }
                    
                    const flattenedPdfBase64 = await pdfDocLib.saveAsBase64();

                    const res = await fetch('api_external_save.php', { 
                        method: 'POST', 
                        body: JSON.stringify({ 
                            token: signerToken, 
                            signatures: signatures,
                            pdf_data: flattenedPdfBase64
                        }) 
                    });
                    const result = await res.json();
                    if (result.success) { alert('ลงนามและบันทึกเอกสารเรียบร้อย!'); location.reload(); } else throw new Error(result.message);
                } catch (e) { alert('ผิดพลาด: ' + e.message); btn.disabled = false; btn.innerHTML = 'เสร็จสิ้น'; lucide.createIcons(); }
            };
            init();
        </script>
    <?php endif; ?>
</body>
</html>
