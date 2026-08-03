<?php
/**
 * Security — QR Code Scanner
 */
$pageTitle  = 'QR Scanner';
$activePage = 'qr';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('security', 'admin');

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> QR Scanner</div>
    <div class="topbar-actions"></div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">QR Code Scanner</h1><p class="page-subtitle">Scan item QR codes using your device camera to look up item details.</p></div>
    </div>

    <div class="row g-4">
      <!-- Scanner -->
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="bi bi-qr-code-scan"></i> Camera Scanner</span></div>
          <div class="card-body text-center">
            <div id="scannerContainer" style="position:relative;background:#000;border-radius:var(--radius);overflow:hidden;max-width:400px;margin:0 auto;">
              <video id="scannerVideo" style="width:100%;border-radius:var(--radius);" playsinline></video>
              <canvas id="scannerCanvas" style="display:none;"></canvas>
              <!-- Scanning overlay -->
              <div id="scannerOverlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                <div style="width:200px;height:200px;border:3px solid var(--primary-light);border-radius:12px;position:relative;">
                  <div style="position:absolute;top:-3px;left:-3px;width:30px;height:30px;border-top:4px solid #fff;border-left:4px solid #fff;border-radius:8px 0 0 0;"></div>
                  <div style="position:absolute;top:-3px;right:-3px;width:30px;height:30px;border-top:4px solid #fff;border-right:4px solid #fff;border-radius:0 8px 0 0;"></div>
                  <div style="position:absolute;bottom:-3px;left:-3px;width:30px;height:30px;border-bottom:4px solid #fff;border-left:4px solid #fff;border-radius:0 0 0 8px;"></div>
                  <div style="position:absolute;bottom:-3px;right:-3px;width:30px;height:30px;border-bottom:4px solid #fff;border-right:4px solid #fff;border-radius:0 0 8px 0;"></div>
                  <div id="scanLine" style="position:absolute;top:0;left:0;right:0;height:2px;background:var(--primary-light);animation:scanMove 2s linear infinite;"></div>
                </div>
              </div>
            </div>

            <div class="mt-3 d-flex gap-2 justify-content-center">
              <button class="btn btn-primary" id="startBtn" onclick="startScanner()">
                <i class="bi bi-camera me-2"></i> Start Camera
              </button>
              <button class="btn btn-outline-secondary" id="stopBtn" onclick="stopScanner()" style="display:none;">
                <i class="bi bi-stop me-2"></i> Stop
              </button>
            </div>
            <p class="text-muted-sm mt-2">Grant camera permission when prompted.</p>
          </div>
        </div>

        <!-- Manual entry -->
        <div class="card mt-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-keyboard"></i> Manual Lookup</span></div>
          <div class="card-body">
            <div class="d-flex gap-2">
              <input type="text" id="manualInput" class="form-control" placeholder="Enter Report # or Item ID…">
              <button class="btn btn-primary" onclick="lookupManual()"><i class="bi bi-search me-1"></i>Lookup</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Results -->
      <div class="col-lg-6">
        <div class="card" id="resultCard" style="display:none;">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-box-seam"></i> Item Details</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearResult()">Clear</button>
          </div>
          <div class="card-body" id="resultBody">
            <!-- Populated by JS -->
          </div>
        </div>

        <div class="card" id="emptyCard">
          <div class="card-body">
            <div class="empty-state py-4">
              <i class="bi bi-qr-code-scan" style="font-size:3rem;"></i>
              <h5>Ready to Scan</h5>
              <p>Start the camera and point it at an item's QR code, or enter a report number manually above.</p>
            </div>
          </div>
        </div>

        <!-- Scan history -->
        <div class="card mt-3" id="historyCard" style="display:none;">
          <div class="card-header"><span class="card-title"><i class="bi bi-clock-history"></i> Scan History</span></div>
          <div id="scanHistory"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes scanMove {
    0%   { top: 0; }
    50%  { top: calc(100% - 2px); }
    100% { top: 0; }
}
</style>

<!-- jsQR via CDN -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
let videoStream = null;
let scanInterval = null;
const history = [];

async function startScanner() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 720 } }
        });
        videoStream = stream;
        const video = document.getElementById('scannerVideo');
        video.srcObject = stream;
        await video.play();
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('stopBtn').style.display  = 'inline-flex';
        scanInterval = setInterval(scanFrame, 300);
    } catch (err) {
        showToast('error', 'Camera Error', err.message || 'Could not access camera.');
    }
}

function stopScanner() {
    clearInterval(scanInterval);
    if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
    document.getElementById('startBtn').style.display = 'inline-flex';
    document.getElementById('stopBtn').style.display  = 'none';
}

function scanFrame() {
    const video  = document.getElementById('scannerVideo');
    const canvas = document.getElementById('scannerCanvas');
    if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(img.data, img.width, img.height);
    if (code) {
        stopScanner();
        processQRData(code.data);
    }
}

async function processQRData(data) {
    // Extract ID from URL
    let id = null;
    const match = data.match(/id=(\d+)/);
    if (match) id = match[1];
    else if (/^\d+$/.test(data.trim())) id = data.trim();

    if (!id) { showToast('warning','Invalid QR','Could not extract item ID from QR code.'); return; }

    await lookupItem(id);
}

function lookupManual() {
    const val = document.getElementById('manualInput').value.trim();
    if (!val) return;
    lookupItem(val);
}

async function lookupItem(idOrRn) {
    try {
        const res = await fetch(`${BASE_URL}/api/qr_lookup.php?id=${encodeURIComponent(idOrRn)}`);
        const data = await res.json();

        if (!data.success) {
            showToast('error','Not Found', data.message || 'Item not found.');
            return;
        }

        const item = data.item;
        addToHistory(item);
        displayResult(item);
        showToast('success','Item Found!', item.item_name);
    } catch (e) {
        showToast('error','Error', e.message);
    }
}

function displayResult(item) {
    document.getElementById('emptyCard').style.display = 'none';
    document.getElementById('resultCard').style.display = 'block';
    const badge = `<span class="badge-status badge-${item.status}" style="display:inline-flex;align-items:center;gap:.3rem;padding:.35em .75em;border-radius:20px;">${item.status}</span>`;
    document.getElementById('resultBody').innerHTML = `
        <div class="row g-3">
          <div class="col-12 d-flex align-items-center gap-3">
            <div class="stat-icon green" style="width:52px;height:52px;font-size:1.4rem;"><i class="bi bi-box-seam"></i></div>
            <div>
              <div style="font-size:1.1rem;font-weight:800;">${escHtml(item.item_name)}</div>
              <code style="font-size:.75rem;">${escHtml(item.report_number)}</code>
            </div>
          </div>
          ${row('Category', item.category)}
          ${row('Location Found', item.location)}
          ${row('Storage', item.storage_location || '—')}
          ${row('Brand', item.brand || '—')}
          ${row('Color', item.color || '—')}
          ${row('Date Found', item.date_found)}
          ${row('Status', badge)}
          ${item.description ? `<div class="col-12"><div class="form-label">Description</div><p style="font-size:.85rem;color:var(--text-muted);">${escHtml(item.description)}</p></div>` : ''}
        </div>
        ${item.storage_location ? `<a href="${BASE_URL}/security/claims.php" class="btn btn-primary mt-3 w-100"><i class="bi bi-file-earmark-check me-2"></i>Process Claims for This Item</a>` : ''}
    `;
}

function row(label, value) {
    return `<div class="col-6"><small class="text-muted-sm">${label}</small><div style="font-size:.85rem;font-weight:500;">${value}</div></div>`;
}

function clearResult() {
    document.getElementById('emptyCard').style.display = 'block';
    document.getElementById('resultCard').style.display = 'none';
}

function addToHistory(item) {
    history.unshift(item);
    if (history.length > 5) history.pop();
    const hc = document.getElementById('historyCard');
    const hs = document.getElementById('scanHistory');
    hc.style.display = 'block';
    hs.innerHTML = history.map(i =>
        `<div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="cursor:pointer;" onclick="displayResult(${JSON.stringify(i).replace(/'/g,"\\'")})">"
           <div class="stat-icon green" style="width:28px;height:28px;font-size:.75rem;flex-shrink:0;"><i class="bi bi-box-seam"></i></div>
           <div style="flex:1;min-width:0;">
             <div class="fw-600 truncate" style="font-size:.82rem;">${escHtml(i.item_name)}</div>
             <code style="font-size:.7rem;">${escHtml(i.report_number)}</code>
           </div>
         </div>`
    ).join('');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
