<?php
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เตรียมเอกสาร - SignMe</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
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
        body { font-family: 'Noto Sans Thai', Sarabun, sans-serif; }
        .prep-container { display: grid; grid-template-columns: 280px 1fr 300px; height: calc(100vh - 70px); background: #f1f5f9; overflow: hidden; }
        
        @media (max-width: 1024px) {
            .prep-container { grid-template-columns: 1fr; height: auto; overflow: visible; }
            .tool-panel { order: 2; border-right: none; border-bottom: 1px solid var(--border); padding: 1rem; position: sticky; top: 70px; z-index: 1000; }
            .viewer-panel { order: 3; padding: 1rem; min-height: 500px; }
            .config-panel { order: 1; border-left: none; border-bottom: 1px solid var(--border); max-height: none; }
            .draggable-tool { padding: 0.75rem; font-size: 0.85rem; }
        }
        .tool-panel { background: white; border-right: 1px solid var(--border); padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }
        .viewer-panel { flex: 1; overflow-y: auto; padding: 3rem; display: flex; flex-direction: column; align-items: center; position: relative; scroll-behavior: smooth; }
        .config-panel { background: white; border-left: 1px solid var(--border); padding: 1.5rem; overflow-y: auto; }
        
        .pdf-page-wrapper { position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-bottom: 3rem; background: white; }
        
        .draggable-tool { padding: 1rem; border: 2px dashed var(--border); border-radius: 12px; cursor: grab; background: #f8fafc; transition: all 0.2s; display: flex; align-items: center; gap: 0.75rem; color: var(--text-dark); font-weight: 500; }
        .draggable-tool:hover { border-color: var(--primary); color: var(--primary); background: rgba(37, 99, 235, 0.05); }

        .signer-card { background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem; position: relative; cursor: pointer; transition: all 0.2s; }
        .signer-card.active { border-color: var(--primary); background: #eff6ff; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1); }
        .signer-card:hover { border-color: var(--primary); }
        
        .signature-field-ui { position: absolute; border: 2px solid var(--primary); background: rgba(37, 99, 235, 0.1); border-radius: 4px; display: flex; align-items: center; justify-content: center; cursor: move; z-index: 100; min-width: 120px; min-height: 40px; }
        .field-label { font-size: 10px; background: var(--primary); color: white; position: absolute; top: -18px; left: -2px; padding: 1px 6px; border-radius: 4px 4px 0 0; white-space: nowrap; }
        .field-delete { position: absolute; top: -10px; right: -10px; background: var(--danger); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; font-size: 10px; }
        .field-resize { position: absolute; bottom: -5px; right: -5px; width: 12px; height: 12px; background: var(--primary); border-radius: 50%; border: 2px solid white; cursor: nwse-resize; z-index: 110; }

        .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2001; backdrop-filter: blur(4px); }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 20px; width: 450px; box-shadow: var(--shadow-lg); }

        #upload-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: white; z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Upload Overlay -->
    <div id="upload-overlay">
        <div class="brand-logo" style="font-size: 2rem; margin-bottom: 2rem;">SignMe</div>
        <h2 style="margin-bottom: 1rem;">เริ่มเตรียมเอกสารของคุณ</h2>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">อัปโหลดไฟล์ PDF เพื่อกำหนดตำแหน่งลงนาม</p>
        <input type="file" id="pdf-upload" accept=".pdf" style="display: none;">
        <button class="btn btn-primary" onclick="document.getElementById('pdf-upload').click()" style="padding: 1rem 2rem; font-size: 1.1rem;">
            <i data-lucide="upload-cloud"></i> เลือกไฟล์ PDF
        </button>
    </div>

    <!-- Signer Modal -->
    <div class="modal" id="signer-modal">
        <div class="modal-content animate-fade-in">
            <h3 id="signer-modal-title" style="margin-bottom: 1.5rem; font-weight: 700;">เพิ่มผู้ลงนาม</h3>
            <div class="form-group">
                <label class="form-label">ชื่อ-นามสกุล</label>
                <input type="text" id="modal-signer-name" class="form-control" placeholder="เช่น นายสมชาย รักชาติ">
            </div>
            <div class="form-group">
                <label class="form-label">อีเมล</label>
                <input type="email" id="modal-signer-email" class="form-control" placeholder="example@email.com">
            </div>
            <div class="form-group">
                <label class="form-label">รหัสเข้าถึงเอกสาร (Access Code - ตัวเลือก)</label>
                <input type="text" id="modal-signer-code" class="form-control" placeholder="เช่น 123456">
                <small style="color: var(--text-muted); font-size: 0.7rem;">หากตั้งรหัสไว้ ผู้ลงนามต้องกรอกรหัสนี้ก่อนเข้าถึงเอกสาร</small>
            </div>
            <div class="flex justify-end gap-2" style="margin-top: 2rem;">
                <button class="btn" style="background: #f1f5f9;" onclick="closeSignerModal()">ยกเลิก</button>
                <button class="btn btn-primary" onclick="saveSignerFromModal()">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>

    <main class="main-content" style="padding: 0; display: flex; flex-direction: column;">
        <header class="header" style="background: white; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 1005; width: 100%; box-sizing: border-box;">
            <!-- แถบบน: ปุ่มควบคุม -->
            <div style="height: 60px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 0 1.5rem;">
                <div class="flex items-center">
                    <a href="index.php?page=dashboard" style="color: var(--text-muted); display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; transition: var(--transition);" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'"><i data-lucide="arrow-left"></i></a>
                </div>
                <div class="flex justify-center">
                    <button class="btn btn-primary" id="save-workflow-btn" disabled style="box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); border: 2px solid transparent; padding: 0.6rem 1.8rem; border-radius: 99px; font-size: 0.9rem;">
                        <i data-lucide="send"></i> ส่งเพื่อลงนาม
                    </button>
                </div>
                <div class="flex justify-end"></div>
            </div>
            <!-- แถบล่าง: ชื่อเอกสาร -->
            <div style="background: #f8fafc; padding: 0.6rem 1.5rem; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.6rem; overflow: hidden;">
                <i data-lucide="file-text" style="width: 16px; color: var(--primary);"></i>
                <span id="file-name-display" style="font-weight: 600; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">ไม่ได้เลือกไฟล์</span>
            </div>
        </header>

        <div class="prep-container" style="height: calc(100vh - 105px);">
            <div class="tool-panel">
                <p class="nav-group-label" style="margin: 0 0 1rem 0;">เครื่องมือลงนาม</p>
                <div class="draggable-tool" draggable="true" ondragstart="onDragStart(event, 'signature')">
                    <i data-lucide="pen-tool"></i>
                    <span>ช่องลงลายมือชื่อ</span>
                </div>
                <div class="draggable-tool" draggable="true" ondragstart="onDragStart(event, 'text')">
                    <i data-lucide="type"></i>
                    <span>ช่องกรอกข้อความ</span>
                </div>
                <div class="draggable-tool" draggable="true" ondragstart="onDragStart(event, 'date')">
                    <i data-lucide="calendar"></i>
                    <span>วันที่ลงนาม</span>
                </div>
            </div>

            <div class="viewer-panel" id="pdf-viewer-container" ondragover="event.preventDefault()" ondrop="onDrop(event)">
                <div id="pdf-render-area"></div>
            </div>

            <div class="config-panel">
                <div class="flex justify-between items-center" style="margin-bottom: 1.5rem;">
                    <p class="nav-group-label" style="margin: 0;">ผู้ลงนาม (<span id="signer-count">0</span>)</p>
                    <button class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;" onclick="openSignerModal()">
                        <i data-lucide="user-plus" style="width: 14px;"></i> เพิ่ม
                    </button>
                </div>
                <div id="signers-list"></div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let signers = [];
        let fields = [];
        let activeSignerIndex = -1;
        let editingSignerIndex = -1;

        document.getElementById('pdf-upload').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            document.getElementById('file-name-display').innerText = file.name;
            const reader = new FileReader();
            reader.onload = async function() {
                const data = new Uint8Array(this.result);
                pdfDoc = await pdfjsLib.getDocument({data}).promise;
                await renderPDF();
                document.getElementById('upload-overlay').style.display = 'none';
                openSignerModal(); // บังคับให้เพิ่มคนแรกก่อน
            };
            reader.readAsArrayBuffer(file);
        });

        async function renderPDF() {
            const container = document.getElementById('pdf-render-area');
            container.innerHTML = '';
            const scale = 2.5; 
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                const page = await pdfDoc.getPage(i);
                const displayViewport = page.getViewport({scale: 1.0});
                const renderViewport = page.getViewport({scale: scale});
                
                const wrapper = document.createElement('div');
                wrapper.className = 'pdf-page-wrapper';
                wrapper.dataset.page = i;
                wrapper.style.width = displayViewport.width + 'px';
                wrapper.style.height = displayViewport.height + 'px';
                
                const canvas = document.createElement('canvas');
                canvas.width = renderViewport.width;
                canvas.height = renderViewport.height;
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                await page.render({canvasContext: canvas.getContext('2d'), viewport: renderViewport}).promise;
                wrapper.appendChild(canvas);
                container.appendChild(wrapper);
            }
        }

        function openSignerModal(index = -1) {
            editingSignerIndex = index;
            const title = document.getElementById('signer-modal-title');
            const nameInput = document.getElementById('modal-signer-name');
            const emailInput = document.getElementById('modal-signer-email');
            const codeInput = document.getElementById('modal-signer-code');
            
            if (index > -1) {
                title.innerText = "แก้ไขผู้ลงนาม";
                nameInput.value = signers[index].name;
                emailInput.value = signers[index].email;
                codeInput.value = signers[index].access_code || "";
            } else {
                title.innerText = "เพิ่มผู้ลงนามใหม่";
                nameInput.value = "";
                emailInput.value = "";
                codeInput.value = "";
            }
            document.getElementById('signer-modal').classList.add('active');
        }

        function closeSignerModal() {
            document.getElementById('signer-modal').classList.remove('active');
        }

        function saveSignerFromModal() {
            const name = document.getElementById('modal-signer-name').value;
            const email = document.getElementById('modal-signer-email').value;
            const code = document.getElementById('modal-signer-code').value;
            if (!name || !email) return alert('กรุณากรอกข้อมูลให้ครบถ้วน');

            if (editingSignerIndex > -1) {
                signers[editingSignerIndex] = { name, email, access_code: code };
            } else {
                signers.push({ name, email, access_code: code });
                if (activeSignerIndex === -1) activeSignerIndex = 0;
            }
            
            renderSigners();
            renderFields();
            closeSignerModal();
        }

        function renderSigners() {
            const list = document.getElementById('signers-list');
            list.innerHTML = '';
            document.getElementById('signer-count').innerText = signers.length;
            
            signers.forEach((s, i) => {
                const card = document.createElement('div');
                card.className = `signer-card ${i === activeSignerIndex ? 'active' : ''}`;
                card.onclick = () => { activeSignerIndex = i; renderSigners(); };
                card.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <div style="font-weight: 700; color: var(--text-dark);">${s.name}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">${s.email}</div>
                            ${s.access_code ? `<div style="font-size: 0.7rem; color: var(--primary); margin-top: 4px;"><i data-lucide="lock" style="width:10px;"></i> รหัส: ${s.access_code}</div>` : ''}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="event.stopPropagation(); openSignerModal(${i})" style="background:none; border:none; color:var(--primary); cursor:pointer;"><i data-lucide="edit-2" style="width:14px;"></i></button>
                            <button onclick="event.stopPropagation(); deleteSigner(${i})" style="background:none; border:none; color:var(--danger); cursor:pointer;"><i data-lucide="trash-2" style="width:14px;"></i></button>
                        </div>
                    </div>
                `;
                list.appendChild(card);
            });
            lucide.createIcons();
            checkReady();
        }

        function deleteSigner(i) {
            if (confirm('ลบผู้ลงนามนี้และช่องลงนามที่เกี่ยวข้อง?')) {
                fields = fields.filter(f => f.signer_index !== i);
                fields.forEach(f => { if (f.signer_index > i) f.signer_index--; });
                signers.splice(i, 1);
                activeSignerIndex = signers.length > 0 ? 0 : -1;
                renderSigners();
                renderFields();
            }
        }

        function onDragStart(e, type) { if (activeSignerIndex === -1) return alert('กรุณาเพิ่มผู้ลงนามก่อน'); e.dataTransfer.setData('type', type); }

        function onDrop(e) {
            e.preventDefault();
            const type = e.dataTransfer.getData('type');
            const wrapper = e.target.closest('.pdf-page-wrapper');
            if (!wrapper || activeSignerIndex === -1) return;
            const rect = wrapper.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width;
            const y = (e.clientY - rect.top) / rect.height;
            fields.push({ id: Date.now(), type, page: parseInt(wrapper.dataset.page), x, y, w: 0.25, h: 0.08, signer_index: activeSignerIndex });
            renderFields();
        }

        function renderFields() {
            document.querySelectorAll('.signature-field-ui').forEach(el => el.remove());
            fields.forEach((f, idx) => {
                const wrapper = document.querySelector(`.pdf-page-wrapper[data-page="${f.page}"]`);
                const fieldUi = document.createElement('div');
                fieldUi.className = 'signature-field-ui';
                fieldUi.style.left = (f.x * 100) + '%';
                fieldUi.style.top = (f.y * 100) + '%';
                fieldUi.style.width = (f.w * 100) + '%';
                fieldUi.style.height = (f.h * 100) + '%';
                let icon = 'pen-tool';
                if (f.type === 'text') icon = 'type';
                if (f.type === 'date') icon = 'calendar';

                fieldUi.innerHTML = `
                    <div class="field-label">${signers[f.signer_index].name}</div>
                    <div class="field-delete" onclick="event.stopPropagation(); deleteField(${idx})">×</div>
                    <i data-lucide="${icon}" style="width: 16px; opacity: 0.5;"></i>
                    <div class="field-resize"></div>
                `;
                
                const startDrag = (e) => {
                    if (e.target.className === 'field-delete' || e.target.className === 'field-resize') return;
                    e.preventDefault();
                    const isTouch = e.type === 'touchstart';
                    const moveEv = isTouch ? 'touchmove' : 'mousemove';
                    const upEv = isTouch ? 'touchend' : 'mouseup';
                    const startX = isTouch ? e.touches[0].clientX : e.clientX;
                    const startY = isTouch ? e.touches[0].clientY : e.clientY;
                    const rect = wrapper.getBoundingClientRect();
                    
                    const onMove = (me) => {
                        const curX = isTouch ? me.touches[0].clientX : me.clientX;
                        const curY = isTouch ? me.touches[0].clientY : me.clientY;
                        let nx = (f.x * rect.width) + (curX - startX);
                        let ny = (f.y * rect.height) + (curY - startY);
                        nx = Math.max(0, Math.min(nx, rect.width - (f.w * rect.width)));
                        ny = Math.max(0, Math.min(ny, rect.height - (f.h * rect.height)));
                        fieldUi.style.left = nx + 'px'; fieldUi.style.top = ny + 'px';
                    };
                    const onUp = () => {
                        const fr = fieldUi.getBoundingClientRect(), pr = wrapper.getBoundingClientRect();
                        f.x = (fr.left - pr.left) / pr.width; f.y = (fr.top - pr.top) / pr.height;
                        document.removeEventListener(moveEv, onMove);
                        document.removeEventListener(upEv, onUp);
                        renderFields();
                    };
                    document.addEventListener(moveEv, onMove, { passive: false });
                    document.addEventListener(upEv, onUp);
                };
                fieldUi.onmousedown = startDrag;
                fieldUi.ontouchstart = startDrag;

                // Resize Logic
                const resizer = fieldUi.querySelector('.field-resize');
                const startResize = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    const isTouch = e.type === 'touchstart';
                    const moveEv = isTouch ? 'touchmove' : 'mousemove';
                    const upEv = isTouch ? 'touchend' : 'mouseup';
                    const startX = isTouch ? e.touches[0].clientX : e.clientX;
                    const startY = isTouch ? e.touches[0].clientY : e.clientY;
                    let startW = f.w, startH = f.h;
                    const rect = wrapper.getBoundingClientRect();
                    
                    const onMove = (me) => {
                        const curX = isTouch ? me.touches[0].clientX : me.clientX;
                        const curY = isTouch ? me.touches[0].clientY : me.clientY;
                        let dw = (curX - startX) / rect.width;
                        let dh = (curY - startY) / rect.height;
                        f.w = Math.max(0.05, startW + dw);
                        f.h = Math.max(0.02, startH + dh);
                        fieldUi.style.width = (f.w * 100) + '%';
                        fieldUi.style.height = (f.h * 100) + '%';
                    };
                    const onUp = () => {
                        document.removeEventListener(moveEv, onMove);
                        document.removeEventListener(upEv, onUp);
                        renderFields();
                    };
                    document.addEventListener(moveEv, onMove, { passive: false });
                    document.addEventListener(upEv, onUp);
                };
                resizer.onmousedown = startResize;
                resizer.ontouchstart = startResize;

                wrapper.appendChild(fieldUi);
            });
            lucide.createIcons();
            checkReady();
        }

        function deleteField(idx) { fields.splice(idx, 1); renderFields(); }
        function checkReady() { document.getElementById('save-workflow-btn').disabled = !(fields.length > 0 && signers.length > 0); }

        document.getElementById('save-workflow-btn').onclick = async () => {
            const btn = document.getElementById('save-workflow-btn');
            btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="animate-spin"></i> กำลังส่ง...'; lucide.createIcons();
            const formData = new FormData();
            formData.append('pdf', document.getElementById('pdf-upload').files[0]);
            formData.append('file_name', document.getElementById('pdf-upload').files[0].name);
            formData.append('signers', JSON.stringify(signers));
            formData.append('fields', JSON.stringify(fields));
            try {
                const res = await fetch('api_workflow.php', { method: 'POST', body: formData });
                const result = await res.json();
                if (result.success) {
                    if (result.mail_warnings && result.mail_warnings.length > 0) {
                        alert('สร้างเอกสารเรียบร้อยแล้ว! แต่พบปัญหาการส่งเมล:\n- ' + result.mail_warnings.join('\n- '));
                    } else {
                        alert('ส่งเอกสารเรียบร้อยแล้ว!');
                    }
                    location.href = 'index.php?page=dashboard';
                } else throw new Error(result.message);
            } catch (e) { alert('ข้อผิดพลาด: ' + e.message); btn.disabled = false; btn.innerHTML = '<i data-lucide="send"></i> ส่งเพื่อลงนาม'; lucide.createIcons(); }
        };
    </script>
</body>
</html>
