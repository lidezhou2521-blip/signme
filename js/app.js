// Initialize PDF.js
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let pdfDoc = null;
let currentPdfBytes = null;
let signers = [];
let selectedSignerId = null;

// DOM Elements
const fileInput = document.getElementById('file-input');
const uploadZone = document.getElementById('upload-zone');
const pdfViewer = document.getElementById('pdf-viewer');
const signerModal = document.getElementById('signer-modal');
const signersList = document.getElementById('signers-list');
const addSignerBtn = document.getElementById('add-signer-btn');
const confirmAddSigner = document.getElementById('confirm-add-signer');
const sendWorkflowBtn = document.getElementById('send-workflow');

// Color palette for different signers
const signerColors = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#db2777'];

// File Upload Handling
fileInput.addEventListener('change', handleFileSelect);

async function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file && file.type === 'application/pdf') {
        window.currentFileName = file.name;
        uploadZone.style.display = 'none';
        const reader = new FileReader();
        reader.onload = async function() {
            currentPdfBytes = new Uint8Array(this.result.slice(0));
            renderPDF(new Uint8Array(this.result));
            updateDocInfo(file);
        };
        reader.readAsArrayBuffer(file);
    }
}

function updateDocInfo(file) {
    const info = document.getElementById('doc-info');
    info.innerHTML = `
        <div class="card" style="padding: 1rem; margin-top: 1rem;">
            <p style="font-size: 0.875rem;"><strong>ไฟล์:</strong> ${file.name}</p>
            <p style="font-size: 0.75rem; color: var(--text-muted);">ขนาด: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
        </div>
    `;
}

async function renderPDF(data) {
    pdfDoc = await pdfjsLib.getDocument({data}).promise;
    pdfViewer.innerHTML = '';
    
    for (let i = 1; i <= pdfDoc.numPages; i++) {
        const page = await pdfDoc.getPage(i);
        const viewport = page.getViewport({scale: 1.5});
        
        const pageContainer = document.createElement('div');
        pageContainer.className = 'pdf-page-container';
        pageContainer.style.position = 'relative';
        pageContainer.style.marginBottom = '2rem';

        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-page';
        canvas.id = `page-${i}`;
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        await page.render({canvasContext: context, viewport: viewport}).promise;
        pageContainer.appendChild(canvas);

        // Sign layer
        const signLayer = document.createElement('div');
        signLayer.className = 'sign-layer';
        signLayer.id = `layer-${i}`;
        signLayer.style.position = 'absolute';
        signLayer.style.top = '0';
        signLayer.style.left = '0';
        signLayer.style.width = '100%';
        signLayer.style.height = '100%';
        
        // Handle Drop
        signLayer.addEventListener('dragover', (e) => e.preventDefault());
        signLayer.addEventListener('drop', (e) => handleDropField(e, i, signLayer));

        pageContainer.appendChild(signLayer);
        pdfViewer.appendChild(pageContainer);
    }
}

// Signer Management Tabs
document.getElementById('tab-single').addEventListener('click', () => {
    document.getElementById('tab-single').classList.add('active');
    document.getElementById('tab-bulk').classList.remove('active');
    document.getElementById('single-add-form').style.display = 'block';
    document.getElementById('bulk-add-form').style.display = 'none';
});

document.getElementById('tab-bulk').addEventListener('click', () => {
    document.getElementById('tab-bulk').classList.add('active');
    document.getElementById('tab-single').classList.remove('active');
    document.getElementById('bulk-add-form').style.display = 'block';
    document.getElementById('single-add-form').style.display = 'none';
});

addSignerBtn.addEventListener('click', () => {
    signerModal.classList.add('active');
});

function closeSignerModal() {
    signerModal.classList.remove('active');
}

confirmAddSigner.addEventListener('click', () => {
    const isBulk = document.getElementById('tab-bulk').classList.contains('active');
    
    if (isBulk) {
        const input = document.getElementById('bulk-signer-input').value;
        const lines = input.split('\n');
        lines.forEach(line => {
            const [name, email] = line.split(',').map(s => s.trim());
            if (name && email) {
                const newId = addSignerToList(name, email);
                selectedSignerId = newId; // เลือกคนล่าสุด
            }
        });
        document.getElementById('bulk-signer-input').value = '';
    } else {
        const name = document.getElementById('new-signer-name').value;
        const email = document.getElementById('new-signer-email').value;
        if (name && email) {
            const newId = addSignerToList(name, email);
            selectedSignerId = newId; // เลือกคนล่าสุด
            document.getElementById('new-signer-name').value = '';
            document.getElementById('new-signer-email').value = '';
        }
    }
    
    renderSigners();
    closeSignerModal();
});

function addSignerToList(name, email) {
    const id = Math.floor(Date.now() + Math.random() * 1000);
    const signer = {
        id: id,
        name,
        email,
        color: signerColors[signers.length % signerColors.length]
    };
    signers.push(signer);
    return id;
}

function renderSigners() {
    signersList.innerHTML = '';
    signers.forEach(s => {
        const div = document.createElement('div');
        div.className = `nav-item ${selectedSignerId === s.id ? 'active' : ''}`;
        div.style.cursor = 'pointer';
        div.style.borderLeft = `4px solid ${s.color}`;
        div.innerHTML = `
            <div style="flex: 1;">
                <p style="font-size: 0.875rem; font-weight: 600;">${s.name}</p>
                <p style="font-size: 0.75rem; opacity: 0.7;">${s.email}</p>
            </div>
            <button onclick="removeSigner(${s.id})" style="background:none; border:none; color:var(--danger);"><i data-lucide="trash-2" style="width:14px;"></i></button>
        `;
        div.onclick = () => {
            selectedSignerId = s.id;
            renderSigners();
        };
        signersList.appendChild(div);
    });
    lucide.createIcons();
    if (!selectedSignerId && signers.length > 0) selectedSignerId = signers[0].id;
}

window.removeSigner = (id) => {
    signers = signers.filter(s => s.id !== id);
    if (selectedSignerId === id) selectedSignerId = null;
    renderSigners();
    // Remove fields associated with this signer
    document.querySelectorAll(`.field-signer-${id}`).forEach(f => f.remove());
};

// Field Drag & Drop
function handleDropField(e, pageNum, layer) {
    if (!selectedSignerId) {
        alert('กรุณาเลือกหรือเพิ่มผู้ลงนามก่อนวางจุดลงนาม');
        return;
    }

    const rect = layer.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    createFieldAt(x, y, pageNum, layer);
}

function createFieldAt(x, y, pageNum, layer) {
    const signer = signers.find(s => s.id === selectedSignerId);
    
    const field = document.createElement('div');
    field.className = `draggable-signature field-signer-${signer.id}`;
    field.style.left = `${x}px`;
    field.style.top = `${y}px`;
    field.style.background = signer.color + '22';
    field.style.borderColor = signer.color;
    field.style.width = '150px';
    field.style.height = '60px';
    field.style.display = 'flex';
    field.style.alignItems = 'center';
    field.style.justifyContent = 'center';
    field.style.fontSize = '0.75rem';
    field.style.color = signer.color;
    field.style.fontWeight = '600';
    field.innerHTML = `<span>จุดลงนาม: ${signer.name}</span>`;
    
    // Add delete button
    const delBtn = document.createElement('button');
    delBtn.innerHTML = '×';
    delBtn.style.cssText = 'position:absolute; top:-10px; right:-10px; background:red; color:white; border-radius:50%; width:20px; height:20px; font-size:12px;';
    delBtn.onclick = (e) => { e.stopPropagation(); field.remove(); };
    field.appendChild(delBtn);

    makeDraggable(field, layer);
    layer.appendChild(field);
}

function makeDraggable(element, parent) {
    let pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    element.onmousedown = dragMouseDown;

    function dragMouseDown(e) {
        e = e || window.event;
        e.preventDefault();
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        document.onmousemove = elementDrag;
    }

    function elementDrag(e) {
        e = e || window.event;
        e.preventDefault();
        pos1 = pos3 - e.clientX;
        pos2 = pos4 - e.clientY;
        pos3 = e.clientX;
        pos4 = e.clientY;
        
        let newTop = element.offsetTop - pos2;
        let newLeft = element.offsetLeft - pos1;

        // Contain within parent
        if (newTop < 0) newTop = 0;
        if (newLeft < 0) newLeft = 0;
        if (newTop + element.offsetHeight > parent.offsetHeight) newTop = parent.offsetHeight - element.offsetHeight;
        if (newLeft + element.offsetWidth > parent.offsetWidth) newLeft = parent.offsetWidth - element.offsetWidth;

        element.style.top = newTop + "px";
        element.style.left = newLeft + "px";
    }

    function closeDragElement() {
        document.onmouseup = null;
        document.onmousemove = null;
    }
}

// Workflow Submission
sendWorkflowBtn.addEventListener('click', async () => {
    if (!currentPdfBytes) { alert('กรุณาอัปโหลดเอกสาร'); return; }
    if (signers.length === 0) { alert('กรุณาเพิ่มผู้ลงนามอย่างน้อย 1 ท่าน'); return; }
    
    const fields = [];
    signers.forEach(s => {
        const signerFields = document.querySelectorAll(`.field-signer-${s.id}`);
        signerFields.forEach(f => {
            const layer = f.parentElement;
            const pageNum = parseInt(layer.id.replace('layer-', ''));
            const rect = f.getBoundingClientRect();
            const parentRect = layer.getBoundingClientRect();
            
            fields.push({
                signer_email: s.email,
                signer_name: s.name,
                page: pageNum,
                x: (f.offsetLeft / layer.offsetWidth),
                y: (f.offsetTop / layer.offsetHeight),
                w: (f.offsetWidth / layer.offsetWidth),
                h: (f.offsetHeight / layer.offsetHeight)
            });
        });
    });

    if (fields.length === 0) { alert('กรุณาวางจุดลงนามบนเอกสาร'); return; }

    try {
        sendWorkflowBtn.innerHTML = '<i data-lucide="loader-2" class="animate-spin"></i> กำลังส่ง...';
        sendWorkflowBtn.disabled = true;
        lucide.createIcons();

        const formData = new FormData();
        const blob = new Blob([currentPdfBytes], { type: 'application/pdf' });
        formData.append('pdf', blob);
        formData.append('file_name', window.currentFileName);
        formData.append('signers', JSON.stringify(signers));
        formData.append('fields', JSON.stringify(fields));

        const response = await fetch('api_workflow.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            alert('ส่งเอกสารเพื่อลงนามเรียบร้อยแล้ว!');
            window.location.href = 'index.php?page=dashboard';
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        alert('เกิดข้อผิดพลาด: ' + error.message);
        sendWorkflowBtn.innerHTML = '<i data-lucide="send"></i> ส่งเพื่อลงนาม';
        sendWorkflowBtn.disabled = false;
        lucide.createIcons();
    }
});
