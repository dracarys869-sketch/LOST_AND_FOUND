/**
 * Main JavaScript — School Lost & Found System
 */

// ─── Dark Mode ──────────────────────────────────────────────
const darkToggle = document.getElementById('darkToggle');
if (darkToggle) {
    const isDark = document.cookie.split(';').some(c => c.trim().startsWith('dark_mode=1'));
    if (isDark) {
        document.documentElement.setAttribute('data-theme', 'dark');
        darkToggle.classList.add('active');
    }
    darkToggle.addEventListener('click', () => {
        const active = darkToggle.classList.toggle('active');
        document.documentElement.setAttribute('data-theme', active ? 'dark' : '');
        document.cookie = `dark_mode=${active ? '1' : '0'};path=/;max-age=31536000`;
    });
}

// ─── Page Loader ─────────────────────────────────────────────
window.addEventListener('load', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => loader.remove(), 300);
    }
});

// ─── Sidebar Toggle (Mobile) ─────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
const sidebarToggle = document.getElementById('sidebarToggle');

function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
    // Lock body scroll on mobile when sidebar is open
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
    document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', openSidebar);
overlay?.addEventListener('click', closeSidebar);

// Auto-close sidebar on nav link tap (mobile)
sidebar?.querySelectorAll('.nav-link-sidebar').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) {
            closeSidebar();
        }
    });
});

// ─── Swipe-to-close Sidebar ──────────────────────────────────
(function() {
    let touchStartX = 0;
    let touchStartY = 0;

    document.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    document.addEventListener('touchend', (e) => {
        if (!sidebar?.classList.contains('open')) return;
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = Math.abs(e.changedTouches[0].clientY - touchStartY);
        // Swipe left ≥ 60px, not predominantly vertical
        if (dx < -60 && dy < 80) {
            closeSidebar();
        }
    }, { passive: true });
})();

// ─── Toast Notifications ─────────────────────────────────────
const TOAST_ICONS = {
    success: 'bi-check-circle-fill',
    error:   'bi-x-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info:    'bi-info-circle-fill',
};

function showToast(type, title, message = '', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icon = TOAST_ICONS[type] || TOAST_ICONS.info;
    const id   = 'toast_' + Date.now();

    const toast = document.createElement('div');
    toast.className = `laf-toast ${type}`;
    toast.id = id;
    toast.innerHTML = `
        <i class="bi ${icon} laf-toast-icon"></i>
        <div class="laf-toast-body">
            <div class="laf-toast-title">${escHtml(title)}</div>
            ${message ? `<div class="laf-toast-msg">${escHtml(message)}</div>` : ''}
        </div>
        <button class="laf-toast-close" onclick="removeToast('${id}')"><i class="bi bi-x"></i></button>
    `;
    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => removeToast(id), duration);
    }
}

function removeToast(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.animation = 'slideInRight 0.2s ease reverse';
        setTimeout(() => el.remove(), 200);
    }
}

// ─── HTML Escape ─────────────────────────────────────────────
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str ?? ''));
    return d.innerHTML;
}

// ─── AJAX Helper ─────────────────────────────────────────────
async function apiPost(url, data = {}) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
        return await res.json();
    } catch (err) {
        return { success: false, message: 'Network error: ' + err.message };
    }
}

async function apiGet(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(qs ? `${url}?${qs}` : url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    return res.json();
}

// ─── Notification Badge Polling ───────────────────────────────
function pollNotifications() {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;

    setInterval(async () => {
        try {
            const data = await apiGet(BASE_URL + '/api/notifications.php', { action: 'count' });
            if (data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        } catch (e) {}
    }, 30000); // every 30s
}

// ─── Image Preview ────────────────────────────────────────────
function initImagePreviews(inputId, gridId) {
    const input = document.getElementById(inputId);
    const grid  = document.getElementById(gridId);
    if (!input || !grid) return;

    input.addEventListener('change', () => {
        grid.innerHTML = '';
        Array.from(input.files).forEach((file, i) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'photo-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-btn" onclick="removePreview(this, ${i})">
                        <i class="bi bi-x"></i>
                    </button>`;
                grid.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    });
}

// ─── Drop Zone ────────────────────────────────────────────────
function initDropZone(zoneId, inputId) {
    const zone  = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        }
    });
}

// ─── Form Validation ─────────────────────────────────────────
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    form.classList.add('was-validated');
    return form.checkValidity();
}

// ─── Confirm Delete ───────────────────────────────────────────
function confirmDelete(msg = 'Are you sure you want to delete this item? This cannot be undone.') {
    return window.confirm(msg);
}

// ─── Search with debounce ─────────────────────────────────────
function debounce(fn, delay = 300) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// ─── Match Score Ring (SVG) ───────────────────────────────────
function renderScoreRing(containerId, score) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const radius = 34;
    const circ   = 2 * Math.PI * radius;
    const offset = circ - (score / 100) * circ;
    const color  = score >= 90 ? '#10b981' : score >= 75 ? '#065f46' : '#f59e0b';

    el.innerHTML = `
        <svg width="80" height="80" viewBox="0 0 80 80">
          <circle cx="40" cy="40" r="${radius}" fill="none" stroke="#e9ecef" stroke-width="6"/>
          <circle cx="40" cy="40" r="${radius}" fill="none" stroke="${color}" stroke-width="6"
                  stroke-dasharray="${circ}" stroke-dashoffset="${offset}"
                  stroke-linecap="round" style="transition:stroke-dashoffset 1s ease"/>
        </svg>
        <span class="score-val" style="color:${color}">${score}%</span>`;
}

// ─── Table Filter ─────────────────────────────────────────────
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', debounce(() => {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }, 250));
}

// ─── BASE_URL injection (set by PHP in each page) ───────────
// Each page should include: <script>const BASE_URL = '<?= BASE_URL ?>';</script>
// before loading main.js, or it defaults to empty string.
if (typeof BASE_URL === 'undefined') { window.BASE_URL = ''; }

// ─── Init on DOM ready ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    pollNotifications();

    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
        setTimeout(() => el.classList.remove('show'), 5000);
    });
});
