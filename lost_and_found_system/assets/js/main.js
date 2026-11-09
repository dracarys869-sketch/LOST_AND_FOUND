/** Shared interactions — Campus Lost & Found. No client-side routing. */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// Theme tokens are shared with canvas charts, which cannot resolve CSS variables.
function chartPalette() {
    const css = getComputedStyle(document.documentElement);
    const token = name => css.getPropertyValue(name).trim();
    return {
        accent: token('--primary'), neutral: token('--text-muted'),
        text: token('--text'), border: token('--border'), surface: token('--surface'),
        font: token('--font-sans'),
    };
}

function applyChartTheme() {
    if (!window.Chart) return;
    const palette = chartPalette();
    Chart.defaults.color = palette.neutral;
    Chart.defaults.borderColor = palette.border;
    Chart.defaults.font.family = palette.font;
    Chart.defaults.font.size = 12;
    Chart.defaults.animation.duration = reducedMotion.matches ? 0 : 200;
    Chart.defaults.animation.easing = 'easeOutQuad';
    Object.values(Chart.instances).forEach(chart => {
        chart.options.animation = reducedMotion.matches ? false : { duration: 200, easing: 'easeOutQuad' };
        Object.values(chart.options.scales || {}).forEach(scale => {
            if (scale.ticks) scale.ticks.color = palette.neutral;
            if (scale.grid) scale.grid.color = palette.border;
        });
        if (chart.options.plugins?.legend?.labels) chart.options.plugins.legend.labels.color = palette.neutral;
        chart.data.datasets.forEach(dataset => {
            if (dataset.lafTone) dataset.backgroundColor = palette[dataset.lafTone];
        });
        chart.update('none');
    });
}
applyChartTheme();
reducedMotion.addEventListener('change', applyChartTheme);

const darkToggle = document.getElementById('darkToggle');
if (darkToggle) {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    darkToggle.classList.toggle('active', isDark);
    darkToggle.setAttribute('aria-checked', String(isDark));
    darkToggle.addEventListener('click', () => {
        const active = document.documentElement.getAttribute('data-theme') !== 'dark';
        document.documentElement.setAttribute('data-theme', active ? 'dark' : 'light');
        document.cookie = `dark_mode=${active ? '1' : '0'};path=/;max-age=31536000;SameSite=Lax`;
        darkToggle.classList.toggle('active', active);
        darkToggle.setAttribute('aria-checked', String(active));
        applyChartTheme();
    });
}

// Mobile navigation: focus, Escape, scroll locking, and hidden-link protection.
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const mobileLayout = window.matchMedia('(max-width: 991.98px)');
let previousSidebarFocus = null;
let previousBodyOverflow = '';

function syncSidebar() {
    if (!sidebar) return;
    const open = sidebar.classList.contains('open');
    sidebar.inert = mobileLayout.matches && !open;
    sidebarToggle?.setAttribute('aria-expanded', String(open));
    document.querySelectorAll('.main-content, .mobile-bottom-nav').forEach(el => {
        el.inert = mobileLayout.matches && open;
    });
}

function openSidebar() {
    if (!sidebar || !mobileLayout.matches) return;
    previousSidebarFocus = document.activeElement;
    previousBodyOverflow = document.body.style.overflow;
    sidebar.classList.add('open');
    overlay?.classList.add('show');
    document.body.style.overflow = 'hidden';
    syncSidebar();
    requestAnimationFrame(() => {
        if (sidebar.classList.contains('open')) sidebarClose?.focus({ preventScroll: true });
    });
}

function closeSidebar(restoreFocus = true) {
    if (!sidebar) return;
    const wasOpen = sidebar.classList.contains('open');
    sidebar.classList.remove('open');
    overlay?.classList.remove('show');
    if (wasOpen) document.body.style.overflow = previousBodyOverflow;
    syncSidebar();
    if (wasOpen && restoreFocus) previousSidebarFocus?.focus();
}

sidebarToggle?.setAttribute('aria-controls', 'sidebar');
sidebarToggle?.setAttribute('aria-label', 'Open navigation');
sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', () => closeSidebar());
overlay?.addEventListener('click', () => closeSidebar());
mobileLayout.addEventListener('change', () => closeSidebar(false));
syncSidebar();
document.addEventListener('keydown', event => {
    if (!sidebar?.classList.contains('open')) return;
    if (event.key === 'Escape') closeSidebar();
    if (event.key === 'Tab') {
        const focusable = [...sidebar.querySelectorAll('a[href], button:not(:disabled), [tabindex="0"]')]
            .filter(el => el.getClientRects().length);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});
sidebar?.querySelectorAll('.nav-link-sidebar').forEach(link => {
    link.addEventListener('click', () => { if (mobileLayout.matches) closeSidebar(false); });
});
let touchStart = null;
sidebar?.addEventListener('touchstart', event => {
    touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
}, { passive: true });
sidebar?.addEventListener('touchend', event => {
    if (!touchStart || !sidebar.classList.contains('open')) return;
    const touch = event.changedTouches[0];
    if (touch.clientX - touchStart.x < -60 && Math.abs(touch.clientY - touchStart.y) < 80) closeSidebar();
    touchStart = null;
}, { passive: true });

// Pending controls are reversible and preserve the original accessible label.
const pendingButtons = new WeakMap();
function setButtonPending(button, pending, label = 'Saving…') {
    if (!button) return;
    if (pending) {
        if (pendingButtons.has(button)) return;
        pendingButtons.set(button, { html: button.innerHTML, disabled: button.disabled });
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = `<span class="button-spinner" aria-hidden="true"></span><span>${escHtml(label)}</span>`;
    } else {
        const original = pendingButtons.get(button);
        if (!original) return;
        button.innerHTML = original.html;
        button.disabled = original.disabled;
        button.removeAttribute('aria-busy');
        pendingButtons.delete(button);
    }
}

const pendingForms = new Map();
function restorePendingForms() {
    pendingForms.forEach((state, form) => {
        state.buttons.forEach(({ button, disabled }) => {
            setButtonPending(button, false);
            button.disabled = disabled;
        });
        state.hidden?.remove();
        state.feedback.remove();
        form.removeAttribute('aria-busy');
        delete form.dataset.submitting;
    });
    pendingForms.clear();
}

// Inline feedback for enhanced forms. Server validation remains authoritative.
const fieldErrors = new WeakMap();
let fieldErrorSequence = 0;
function syncPasswordConfirmation(form) {
    form.querySelectorAll('[data-match]').forEach(field => {
        const original = document.getElementById(field.dataset.match);
        field.setCustomValidity(field.value && original && field.value !== original.value ? 'Passwords do not match.' : '');
    });
}
function showFieldValidity(field) {
    if (!field.willValidate) return;
    const valid = field.validity.valid;
    let feedback = fieldErrors.get(field);
    field.classList.toggle('is-invalid', !valid);
    if (valid) {
        field.removeAttribute('aria-invalid');
        if (feedback) {
            const descriptions = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== feedback.id);
            if (descriptions.length) field.setAttribute('aria-describedby', descriptions.join(' '));
            else field.removeAttribute('aria-describedby');
            feedback.remove();
            fieldErrors.delete(field);
        }
        return;
    }
    field.setAttribute('aria-invalid', 'true');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'field-error';
        feedback.id = `field-error-${++fieldErrorSequence}`;
        const anchor = field.closest('.input-group') || field;
        anchor.insertAdjacentElement('afterend', feedback);
        field.setAttribute('aria-describedby', `${field.getAttribute('aria-describedby') || ''} ${feedback.id}`.trim());
        fieldErrors.set(field, feedback);
    }
    feedback.textContent = field.validationMessage;
}
function validateFields(form) {
    syncPasswordConfirmation(form);
    const fields = [...form.elements].filter(field => field.willValidate);
    fields.forEach(showFieldValidity);
    const invalid = fields.filter(field => !field.validity.valid);
    let summary = form.querySelector('[data-validation-summary]');
    if (!invalid.length) { summary?.remove(); return true; }
    if (!summary) {
        summary = document.createElement('div');
        summary.className = 'alert alert-danger validation-summary';
        summary.dataset.validationSummary = '';
        summary.setAttribute('role', 'alert');
        form.prepend(summary);
    }
    summary.textContent = `Please check ${invalid.length === 1 ? 'the highlighted field' : `the ${invalid.length} highlighted fields`} before continuing.`;
    form.dataset.validationAttempted = 'true';
    const first = invalid[0];
    const focusTarget = first.getClientRects().length ? first : document.querySelector(`[aria-controls="${first.id}"]`);
    focusTarget?.focus();
    return false;
}

window.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !form.noValidate || event.submitter?.formNoValidate) return;
    if (!validateFields(form)) event.preventDefault();
});

// Window-level bubbling runs after existing form confirmation/validation handlers.
window.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.dataset.nativePending === 'off' || form.method === 'dialog') return;
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    const submitter = event.submitter;
    const buttons = [...form.querySelectorAll('button, input[type="submit"]')]
        .filter(button => button.type === 'submit')
        .map(button => ({ button, disabled: button.disabled }));
    const label = submitter?.dataset.pendingLabel || form.dataset.pendingLabel || (form.method.toLowerCase() === 'get' ? 'Searching…' : 'Saving…');

    // Disabled submitters are omitted by the browser. Keep named decisions intact.
    let hidden = null;
    if (submitter?.name) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value;
        form.appendChild(hidden);
    }
    const feedback = document.createElement('div');
    feedback.className = 'form-feedback';
    feedback.setAttribute('role', 'status');
    feedback.textContent = label;
    form.appendChild(feedback);
    form.setAttribute('aria-busy', 'true');
    form.dataset.submitting = 'true';
    const liveStatus = document.getElementById('interactionStatus');
    if (liveStatus) liveStatus.textContent = label;
    pendingForms.set(form, { buttons, hidden, feedback });
    if (submitter?.tagName === 'BUTTON') setButtonPending(submitter, true, label);
    buttons.forEach(({ button }) => { button.disabled = true; });
});
window.addEventListener('pageshow', restorePendingForms);

// Toasts announce real feedback; errors remain until explicitly dismissed.
const TOAST_ICONS = {
    success: 'bi-check-circle', error: 'bi-x-circle',
    warning: 'bi-exclamation-triangle', info: 'bi-info-circle',
};
let toastSequence = 0;
function showToast(type, title, message = '', duration = type === 'error' ? 0 : 6000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const kind = Object.hasOwn(TOAST_ICONS, type) ? type : 'info';
    const toast = document.createElement('div');
    toast.className = `laf-toast ${kind}`;
    toast.id = `toast_${++toastSequence}`;
    toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
        <i class="bi ${TOAST_ICONS[kind]} laf-toast-icon" aria-hidden="true"></i>
        <div class="laf-toast-body">
            <div class="laf-toast-title">${escHtml(title)}</div>
            ${message ? `<div class="laf-toast-msg">${escHtml(message)}</div>` : ''}
        </div>
        <button type="button" class="laf-toast-close" aria-label="Dismiss notification"><i class="bi bi-x-lg" aria-hidden="true"></i></button>`;
    toast.querySelector('button').addEventListener('click', () => removeToast(toast.id));
    let timer;
    const schedule = () => { if (duration > 0) timer = setTimeout(() => removeToast(toast.id), duration); };
    toast.addEventListener('pointerenter', () => clearTimeout(timer));
    toast.addEventListener('pointerleave', schedule);
    toast.addEventListener('focusin', () => clearTimeout(timer));
    toast.addEventListener('focusout', schedule);
    container.appendChild(toast);
    schedule();
    return toast;
}

function removeToast(id) {
    const toast = document.getElementById(id);
    if (!toast) return;
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), reducedMotion.matches ? 0 : 150);
}

function escHtml(value) {
    const element = document.createElement('div');
    element.textContent = String(value ?? '');
    return element.innerHTML;
}

async function fetchJson(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
        const response = await fetch(url, { ...options, signal: controller.signal });
        if (!response.ok) throw new Error(`Request failed (${response.status}). Please try again.`);
        return await response.json();
    } catch (error) {
        if (error.name === 'AbortError') throw new Error('The request timed out. Please try again.');
        throw error;
    } finally { clearTimeout(timer); }
}

async function apiPost(url, data = {}) {
    try {
        return await fetchJson(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(data),
        });
    } catch (error) { return { success: false, message: error.message }; }
}

async function apiGet(url, params = {}) {
    const query = new URLSearchParams(params).toString();
    return fetchJson(query ? `${url}${url.includes('?') ? '&' : '?'}${query}` : url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
}

function pollNotifications() {
    const badge = document.getElementById('notifBadge');
    if (!badge && !document.querySelector('[data-notification-count]')) return;
    setInterval(async () => {
        if (document.hidden) return;
        try {
            const data = await apiGet(BASE_URL + '/api/notifications.php', { action: 'count' });
            const count = Math.max(0, Number(data.count) || 0);
            if (badge) {
                badge.textContent = count;
                badge.style.display = count ? 'inline-block' : 'none';
            }
            document.querySelectorAll('[data-notification-count]').forEach(counter => {
                counter.textContent = counter.classList.contains('mbn-badge') && count > 9 ? '9+' : count;
                counter.hidden = count === 0;
            });
            document.getElementById('notifBtnTop')?.setAttribute('aria-label', `Notifications${count ? `, ${count} unread` : ''}`);
        } catch { /* The next background poll retries; no interruption to the current task. */ }
    }, 30000);
}

// Image previews keep file order and ignore stale reads after a file is removed.
function initImagePreviews(inputId, gridId) {
    const input = document.getElementById(inputId);
    const grid = document.getElementById(gridId);
    if (!input || !grid) return;
    let revision = 0;
    const render = () => {
        const current = ++revision;
        grid.replaceChildren();
        const invalidFile = [...input.files].find(file => !['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024);
        input.setCustomValidity(invalidFile ? `${invalidFile.name}: choose a JPG, PNG, GIF, or WebP image up to 5 MB.` : '');
        showFieldValidity(input);
        Array.from(input.files).forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;
            const item = document.createElement('div');
            item.className = 'photo-preview-item';
            const image = document.createElement('img');
            image.alt = 'Preview of ' + file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-btn';
            remove.dataset.index = String(index);
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            item.append(image, remove);
            grid.appendChild(item);
            const reader = new FileReader();
            reader.onload = event => { if (current === revision) image.src = event.target.result; };
            reader.onerror = () => { if (current === revision) image.alt = 'Preview unavailable for ' + file.name; };
            reader.readAsDataURL(file);
        });
    };
    input.addEventListener('change', render);
    grid.addEventListener('click', event => {
        const button = event.target.closest('.remove-btn');
        if (!button) return;
        event.preventDefault();
        const index = Number(button.dataset.index);
        if (Number.isNaN(index)) return;
        const transfer = new DataTransfer();
        Array.from(input.files).forEach((file, fileIndex) => { if (fileIndex !== index) transfer.items.add(file); });
        try { input.files = transfer.files; } catch { input.value = ''; }
        render();
        const next = grid.querySelectorAll('.remove-btn')[Math.max(0, index - 1)];
        (next || document.querySelector(`[aria-controls="${inputId}"]`))?.focus();
    });
}

function initDropZone(zoneId, inputId) {
    const zone = document.getElementById(zoneId);
    const input = document.getElementById(inputId);
    if (!zone || !input) return;
    zone.setAttribute('role', 'button');
    zone.setAttribute('tabindex', '0');
    zone.setAttribute('aria-label', 'Choose photos or drop image files here');
    zone.setAttribute('aria-controls', inputId);
    zone.addEventListener('click', () => input.click());
    zone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); }
    });
    zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.classList.remove('dragover');
        if (!event.dataTransfer?.files?.length) return;
        const transfer = new DataTransfer();
        Array.from(event.dataTransfer.files).forEach(file => transfer.items.add(file));
        if (!transfer.files.length) return;
        try { input.files = transfer.files; input.dispatchEvent(new Event('change')); }
        catch { showToast('warning', 'Use the file picker', 'Please select photos using the Choose photos control.'); }
    });
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    return validateFields(form);
}
function confirmDelete(message = 'Are you sure you want to delete this item? This cannot be undone.') { return window.confirm(message); }
function debounce(fn, delay = 300) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}
function renderScoreRing(containerId, score) {
    const element = document.getElementById(containerId);
    if (!element) return;
    const value = Math.min(100, Math.max(0, Number(score) || 0));
    const radius = 34;
    const circumference = 2 * Math.PI * radius;
    element.setAttribute('role', 'img');
    element.setAttribute('aria-label', `${value}% item similarity`);
    element.innerHTML = `<svg width="80" height="80" viewBox="0 0 80 80" aria-hidden="true">
        <circle class="score-track" cx="40" cy="40" r="${radius}" fill="none" stroke-width="5"/>
        <circle class="score-stroke" cx="40" cy="40" r="${radius}" fill="none" stroke-width="5"
            stroke-dasharray="${circumference}" stroke-dashoffset="${circumference * (1 - value / 100)}" stroke-linecap="round"/>
        </svg><span class="score-val" aria-hidden="true">${value}%</span>`;
}
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('input', debounce(() => {
        const query = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => { row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none'; });
    }, 250));
}

if (typeof BASE_URL === 'undefined') window.BASE_URL = '';
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[novalidate]').forEach(form => {
        form.addEventListener('input', event => {
            syncPasswordConfirmation(form);
            if (fieldErrors.has(event.target)) showFieldValidity(event.target);
            form.querySelectorAll('[data-match]').forEach(field => { if (fieldErrors.has(field)) showFieldValidity(field); });
            if (form.dataset.validationAttempted && [...form.elements].every(field => !field.willValidate || field.validity.valid)) {
                form.querySelector('[data-validation-summary]')?.remove();
            }
        });
        form.addEventListener('change', event => { if (fieldErrors.has(event.target)) showFieldValidity(event.target); });
    });
    document.querySelectorAll('[data-item-image]').forEach(image => {
        const fallback = () => {
            image.hidden = true;
            image.style.display = 'none';
            const placeholder = image.parentElement.querySelector('.board-item-placeholder');
            if (placeholder) {
                placeholder.hidden = false;
                placeholder.querySelector('span').textContent = 'Photo unavailable';
            }
        };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    });
    // Add keyboard scrolling only where a table actually overflows. Observing
    // both elements also covers opened details and Bootstrap tab changes.
    const updateTableRegion = region => {
        const overflowing = region.scrollWidth > region.clientWidth + 1;
        if (overflowing) {
            const heading = region.closest('.card')?.querySelector('.card-title') || document.querySelector('h1');
            region.setAttribute('tabindex', '0');
            region.setAttribute('role', 'region');
            region.setAttribute('aria-label', `${heading?.textContent.trim() || 'Data'} — scrollable table`);
        } else {
            region.removeAttribute('tabindex');
            region.removeAttribute('role');
            region.removeAttribute('aria-label');
        }
    };
    const tableObserver = new ResizeObserver(entries => {
        entries.forEach(entry => updateTableRegion(entry.target.closest('.table-responsive')));
    });
    document.querySelectorAll('.table-responsive').forEach(region => {
        updateTableRegion(region);
        tableObserver.observe(region);
        const table = region.querySelector('table');
        if (table) tableObserver.observe(table);
    });
    if (!document.getElementById('interactionStatus')) {
        const liveStatus = document.createElement('div');
        liveStatus.id = 'interactionStatus';
        liveStatus.className = 'visually-hidden';
        liveStatus.setAttribute('role', 'status');
        liveStatus.setAttribute('aria-atomic', 'true');
        document.body.appendChild(liveStatus);
    }
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            const icon = button.querySelector('.bi');
            if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
    document.querySelectorAll('[data-strength-meter]').forEach(input => {
        input.addEventListener('input', () => {
            const output = document.getElementById(input.dataset.strengthMeter);
            if (!output) return;
            const value = input.value;
            const score = Number(value.length >= 8) + Number(/[A-Z]/.test(value)) + Number(/[0-9]/.test(value)) + Number(/[^A-Za-z0-9]/.test(value));
            const label = ['Weak', 'Weak', 'Fair', 'Good', 'Strong'][score];
            output.innerHTML = value ? `<progress class="password-meter" value="${score}" max="4" aria-label="Password strength"></progress><small>${label} password</small>` : '';
        });
    });
    pollNotifications();
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => {
        if (window.bootstrap) bootstrap.Tooltip.getOrCreateInstance(element, { trigger: 'hover focus' });
    });
    document.querySelectorAll('.nav-link-sidebar.active, .mbn-item.active, a.nav-link.active').forEach(link => link.setAttribute('aria-current', 'page'));
    document.querySelectorAll('.bi:not([aria-label])').forEach(icon => icon.setAttribute('aria-hidden', 'true'));
});
