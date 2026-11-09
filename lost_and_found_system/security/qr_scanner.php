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

<main class="main-content" id="main-content" tabindex="-1">
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
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="bi bi-qr-code-scan"></i> Camera Scanner</span></div>
          <div class="card-body text-center">
            <div id="scannerContainer" class="scanner-container">
              <video id="scannerVideo" playsinline muted aria-label="Camera preview for scanning an item QR code"></video>
              <canvas id="scannerCanvas" hidden></canvas>
              <div id="cameraPlaceholder" class="scanner-overlay text-white">Camera is off</div>
              <div id="scannerOverlay" class="scanner-overlay" hidden aria-hidden="true">
                <div class="scan-frame"></div>
              </div>
            </div>

            <div class="mt-3 d-flex gap-2 justify-content-center">
              <button type="button" class="btn btn-primary btn-lg" id="startBtn" onclick="startScanner()">
                <i class="bi bi-camera me-2"></i> Start Camera
              </button>
              <button type="button" class="btn btn-outline-secondary" id="stopBtn" onclick="stopScanner()" hidden>
                <i class="bi bi-stop me-2"></i> Stop
              </button>
            </div>
            <p class="scanner-status" id="cameraStatus" role="status">Start the camera, then allow access when prompted.</p>
          </div>
        </div>

        <!-- Manual entry -->
        <form class="support-note" id="manualLookupForm" data-native-pending="off">
          <label for="manualInput" class="form-label">Or enter the report number or item ID</label>
          <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="manualInput" class="form-control flex-grow-1 w-auto" placeholder="e.g. FI-2026-0001" required>
            <button type="submit" class="btn btn-outline-secondary" id="lookupBtn"><i class="bi bi-search" aria-hidden="true"></i> Look up item</button>
          </div>
        </form>
      </div>

      <!-- Results -->
      <div class="col-lg-5">
        <p id="lookupStatus" class="visually-hidden" role="status" aria-atomic="true"></p>
        <div class="card" id="resultCard" style="display:none;">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-box-seam"></i> Item Details</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearBtn" onclick="clearResult()">Clear</button>
          </div>
          <div class="card-body" id="resultBody" tabindex="-1">
            <!-- Populated by JS -->
          </div>
        </div>

        <div class="empty-state" id="emptyCard">
          <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
          <h2 class="h5">Item details appear here</h2>
          <p>Scan a QR label or enter a report number to check an item's status and storage location.</p>
        </div>

        <!-- Scan history -->
        <div class="card mt-3" id="historyCard" style="display:none;">
          <div class="card-header"><span class="card-title"><i class="bi bi-clock-history"></i> Scan History</span></div>
          <div id="scanHistory"></div>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- jsQR via CDN -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
let videoStream = null;
let scanInterval = null;
let cameraStarting = false;
let cameraRevision = 0;
let lookupPending = false;
const scanHistory = [];

async function startScanner() {
    if (cameraStarting || videoStream || lookupPending) return;
    cameraStarting = true;
    const revision = ++cameraRevision;
    const start = document.getElementById('startBtn');
    setButtonPending(start, true, 'Opening camera…');
    document.getElementById('stopBtn').hidden = false;
    document.getElementById('cameraStatus').textContent = 'Waiting for camera access. You can stop to cancel.';
    try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera access is unavailable. Use manual lookup or open this page on HTTPS or localhost.');
        if (typeof jsQR !== 'function') throw new Error('The scanner could not load. Please use manual lookup or reload the page.');
        const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 720 } }
        });
        if (revision !== cameraRevision) { stream.getTracks().forEach(track => track.stop()); return; }
        videoStream = stream;
        const video = document.getElementById('scannerVideo');
        video.srcObject = stream;
        await video.play();
        if (revision !== cameraRevision) return;
        start.hidden = true;
        document.getElementById('cameraPlaceholder').hidden = true;
        document.getElementById('scannerOverlay').hidden = false;
        document.getElementById('cameraStatus').textContent = 'Camera is on. Hold the item QR code inside the frame.';
        scanInterval = setInterval(scanFrame, 300);
    } catch (err) {
        if (revision !== cameraRevision) return;
        stopScanner();
        document.getElementById('cameraStatus').textContent = 'Camera unavailable. Try again or use the report number below.';
        showToast('error', 'Camera unavailable', err.message || 'Could not access the camera.');
    } finally {
        if (revision === cameraRevision) {
            cameraStarting = false;
            setButtonPending(start, false);
        }
    }
}

function stopScanner() {
    cameraRevision++;
    cameraStarting = false;
    clearInterval(scanInterval);
    if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; }
    document.getElementById('scannerVideo').srcObject = null;
    const start = document.getElementById('startBtn');
    setButtonPending(start, false);
    start.hidden = false;
    document.getElementById('stopBtn').hidden = true;
    document.getElementById('cameraPlaceholder').hidden = false;
    document.getElementById('scannerOverlay').hidden = true;
    document.getElementById('cameraStatus').textContent = 'Camera is off.';
}
window.addEventListener('pagehide', stopScanner);
document.addEventListener('visibilitychange', () => { if (document.hidden) stopScanner(); });

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
document.getElementById('manualLookupForm').addEventListener('submit', event => {
    event.preventDefault();
    lookupManual();
});

async function lookupItem(idOrRn) {
    if (lookupPending) return;
    if (videoStream || cameraStarting) stopScanner();
    lookupPending = true;
    const button = document.getElementById('lookupBtn');
    const card = document.getElementById('resultCard');
    const body = document.getElementById('resultBody');
    const status = document.getElementById('lookupStatus');
    setButtonPending(button, true, 'Looking up…');
    document.getElementById('startBtn').disabled = true;
    document.getElementById('clearBtn').disabled = true;
    document.getElementById('emptyCard').style.display = 'none';
    card.style.display = 'block';
    card.setAttribute('aria-busy', 'true');
    status.textContent = 'Looking up item…';
    body.innerHTML = '<div aria-hidden="true"><div class="skeleton skeleton-title"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div><p class="lookup-feedback">Looking up item…</p>';
    try {
        const data = await apiGet(`${BASE_URL}/api/qr_lookup.php`, { id: idOrRn });
        if (!data.success) throw new Error(data.message || 'No item was found. Check the report number and try again.');
        const item = data.item;
        addToHistory(item);
        displayResult(item);
        status.textContent = 'Item found: ' + item.item_name;
    } catch (e) {
        body.innerHTML = `<div class="alert alert-danger" role="alert">${escHtml(e.message || 'The lookup failed. Please try again.')}</div><p class="small mb-0">Check the number, then select Look up item to retry.</p>`;
        status.textContent = 'Item lookup failed. Check the number and try again.';
    } finally {
        lookupPending = false;
        card.removeAttribute('aria-busy');
        setButtonPending(button, false);
        document.getElementById('startBtn').disabled = false;
        document.getElementById('clearBtn').disabled = false;
        body.focus({ preventScroll: true });
    }
}

function displayResult(item) {
    document.getElementById('emptyCard').style.display = 'none';
    document.getElementById('resultCard').style.display = 'block';
    document.getElementById('startBtn').classList.replace('btn-primary', 'btn-outline-secondary');
    document.getElementById('resultBody').innerHTML = `
        <div class="row g-3">
          <div class="col-12">
            <h3>${escHtml(item.item_name)}</h3>
            <code>${escHtml(item.report_number)}</code>
          </div>
          ${row('Category', item.category)}
          ${row('Location Found', item.location)}
          ${row('Storage', item.storage_location || '—')}
          ${row('Brand', item.brand || '—')}
          ${row('Color', item.color || '—')}
          ${row('Date Found', item.date_found)}
          ${row('Status', item.status)}
          ${item.description ? `<div class="col-12"><h4 class="form-label">Description</h4><p class="small">${escHtml(item.description)}</p></div>` : ''}
        </div>
        ${item.storage_location ? `<a href="${BASE_URL}/security/claims.php" class="btn btn-primary mt-3 w-100"><i class="bi bi-file-earmark-check me-2"></i>Process Claims for This Item</a>` : ''}
    `;
}

function row(label, value) {
    return `<div class="col-sm-6"><div class="text-muted-sm">${escHtml(label)}</div><div class="small numeric">${escHtml(value)}</div></div>`;
}

function clearResult() {
    if (lookupPending) return;
    document.getElementById('emptyCard').style.display = 'block';
    document.getElementById('resultCard').style.display = 'none';
    document.getElementById('startBtn').classList.replace('btn-outline-secondary', 'btn-primary');
    document.getElementById('manualInput').focus();
}

function addToHistory(item) {
    scanHistory.unshift(item);
    if (scanHistory.length > 5) scanHistory.pop();
    const hc = document.getElementById('historyCard');
    const hs = document.getElementById('scanHistory');
    hc.style.display = 'block';
    hs.replaceChildren();
    scanHistory.forEach(entry => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'scan-history-item';
        button.innerHTML = `<i class="bi bi-box-seam" aria-hidden="true"></i><span><strong>${escHtml(entry.item_name)}</strong><code>${escHtml(entry.report_number)}</code></span>`;
        button.addEventListener('click', () => {
            if (lookupPending) return;
            displayResult(entry);
            document.getElementById('lookupStatus').textContent = 'Showing previous lookup: ' + entry.item_name;
            document.getElementById('resultBody').focus({ preventScroll: true });
        });
        hs.appendChild(button);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
