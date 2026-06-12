/* ===================================================
   The Brighten Stars Academy – admin.js
   Fixes: black screen, hamburger, confirm modal,
          auto-logout, live refresh, go-back button
   =================================================== */

// ── Sidebar Toggle (works for admin / student / parent) ──────────────────
function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('overlay');
    const wrapper  = document.getElementById('mainWrapper');
    if (window.innerWidth <= 992) {
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    } else {
        const collapsed = sidebar.dataset.collapsed === '1';
        if (collapsed) {
            sidebar.dataset.collapsed = '0';
            sidebar.style.transform  = '';
            if (wrapper) wrapper.style.marginLeft = 'var(--sidebar-w)';
        } else {
            sidebar.dataset.collapsed = '1';
            sidebar.style.transform  = 'translateX(-100%)';
            if (wrapper) wrapper.style.marginLeft = '0';
        }
    }
}

// ── DOMContentLoaded ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

    // ① FIX BLACK SCREEN: Remove Bootstrap modal remnants on every page load
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.style.overflow   = '';
    document.body.style.paddingRight = '';

    // ② OVERLAY – create once, used by hamburger
    if (!document.getElementById('overlay')) {
        const ov = document.createElement('div');
        ov.id = 'overlay';
        ov.className = 'overlay';
        ov.addEventListener('click', () => {
            const sb = document.getElementById('sidebar');
            if (sb) sb.classList.remove('show');
            ov.classList.remove('show');
        });
        document.body.appendChild(ov);
    }

    // ③ GO-BACK BUTTON – inject into every section-header that has no back btn
    const header = document.querySelector('.section-header');
    if (header && !header.querySelector('.btn-go-back') && window.history.length > 1) {
        const btn = document.createElement('button');
        btn.className  = 'btn-go-back';
        btn.innerHTML  = '<i class="bi bi-arrow-left"></i> Back';
        btn.title      = 'Go back';
        btn.addEventListener('click', () => history.back());
        header.prepend(btn);
    }

    // ④ AUTO-DISMISS alerts after 5 s
    setTimeout(() => {
        document.querySelectorAll('.alert.alert-dismissible').forEach(a => {
            try { new bootstrap.Alert(a).close(); } catch(e) {}
        });
    }, 5000);

    // ⑤ COUNTER ANIMATION
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count) || 0;
        let cur = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        const t = setInterval(() => {
            cur = Math.min(cur + step, target);
            el.textContent = cur.toLocaleString();
            if (cur >= target) clearInterval(t);
        }, 25);
    });

    // ⑥ AUTO-LOGOUT – timeout driven by PHP SESSION_LIFETIME constant
    initAutoLogout(window.SESSION_TIMEOUT || 20);

    // ⑦ LIVE REFRESH – update stat cards every 60 s (admin only)
    if (document.querySelector('.stat-card[data-stat]')) {
        setInterval(refreshStats, 60000);
    }
});

// ── Custom Confirm Dialog (replaces browser confirm()) ───────────────────
function tbsConfirm(message, onYes, opts = {}) {
    const id   = 'tbsConfirmModal';
    let modal  = document.getElementById(id);
    if (modal) modal.remove();

    const type  = opts.type   || 'danger';       // danger | warning | info
    const icon  = opts.icon   || (type==='danger' ? 'bi-trash' : 'bi-question-circle');
    const yesLbl= opts.yesLabel || (type==='danger' ? 'Yes, Delete' : 'Confirm');
    const noLbl = opts.noLabel  || 'Cancel';

    const colors = { danger:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const color  = colors[type] || colors.danger;

    modal = document.createElement('div');
    modal.id = id;
    modal.innerHTML = `
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:99999;display:flex;align-items:center;justify-content:center;padding:1rem">
      <div style="background:#16161f;border:1px solid #2a2a3a;border-top:3px solid ${color};border-radius:16px;padding:2rem;max-width:420px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,.6);animation:tbsFadeIn .15s ease">
        <div style="text-align:center;margin-bottom:1.5rem">
          <div style="width:60px;height:60px;border-radius:50%;background:rgba(${type==='danger'?'239,68,68':type==='warning'?'245,158,11':'59,130,246'},.15);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem">
            <i class="bi ${icon}" style="font-size:1.6rem;color:${color}"></i>
          </div>
          <div style="font-size:1.05rem;font-weight:600;color:#f0f0f5;margin-bottom:.5rem">Are you sure?</div>
          <div style="font-size:.88rem;color:#8888aa;line-height:1.5">${message}</div>
        </div>
        <div style="display:flex;gap:.75rem">
          <button id="tbsNo"  style="flex:1;padding:.65rem;border:1px solid #2a2a3a;border-radius:10px;background:#1e1e2a;color:#f0f0f5;cursor:pointer;font-size:.9rem;font-weight:500">${noLbl}</button>
          <button id="tbsYes" style="flex:1;padding:.65rem;border:none;border-radius:10px;background:${color};color:#fff;cursor:pointer;font-size:.9rem;font-weight:600">${yesLbl}</button>
        </div>
      </div>
    </div>`;

    document.body.appendChild(modal);
    modal.querySelector('#tbsNo').addEventListener('click',  () => modal.remove());
    modal.querySelector('#tbsYes').addEventListener('click', () => { modal.remove(); onYes(); });

    // Close on backdrop click
    modal.addEventListener('click', e => { if (e.target === modal.firstElementChild) modal.remove(); });
}

// Intercept all forms with data-confirm attribute OR delete forms
function confirmDelete(form, msg) {
    tbsConfirm(msg || 'This record will be permanently deleted and cannot be recovered.', () => {
        // Close any open Bootstrap modal first
        document.querySelectorAll('.modal.show').forEach(m => {
            try { bootstrap.Modal.getInstance(m)?.hide(); } catch(e){}
        });
        setTimeout(() => form.submit(), 200);
    }, { type: 'danger', yesLabel: 'Yes, Delete' });
    return false;
}

function confirmSave(form, msg) {
    tbsConfirm(msg || 'Do you want to save these changes?', () => {
        document.querySelectorAll('.modal.show').forEach(m => {
            try { bootstrap.Modal.getInstance(m)?.hide(); } catch(e){}
        });
        setTimeout(() => form.submit(), 200);
    }, { type: 'info', icon: 'bi-check-circle', yesLabel: 'Save Changes' });
    return false;
}

// ── WhatsApp helper ───────────────────────────────────────────────────────
function openWhatsApp(phone, message) {
    const clean = String(phone).replace(/[^0-9]/g, '');
    const num   = clean.startsWith('0') ? '92' + clean.slice(1) : clean;
    window.open('https://wa.me/' + num + (message ? '?text=' + encodeURIComponent(message) : ''), '_blank');
}

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg, type = 'info') {
    let wrap = document.getElementById('toast-wrapper');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'toast-wrapper';
        wrap.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(wrap);
    }
    const colors = { success:'#10b981', danger:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const t = document.createElement('div');
    t.style.cssText = `background:#16161f;border:1px solid #2a2a3a;border-left:3px solid ${colors[type]||colors.info};
        color:#f0f0f5;padding:.7rem 1.1rem;border-radius:8px;font-size:.875rem;
        box-shadow:0 4px 16px rgba(0,0,0,.4);animation:tbsSlideIn .2s ease;min-width:220px;`;
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// ── Copy to clipboard ─────────────────────────────────────────────────────
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => showToast('Copied!', 'success'));
}

// ── Auto-logout on idle ───────────────────────────────────────────────────
function initAutoLogout(minutes) {
    let idleTimer;
    const warningTime = (minutes - 2) * 60 * 1000;
    const logoutTime  = minutes * 60 * 1000;
    let warningShown  = false;

    function reset() {
        clearTimeout(idleTimer);
        warningShown = false;
        const warn = document.getElementById('idleWarning');
        if (warn) warn.remove();
        idleTimer = setTimeout(warnUser, warningTime);
    }

    function warnUser() {
        if (warningShown) return;
        warningShown = true;
        const d = document.createElement('div');
        d.id = 'idleWarning';
        d.innerHTML = `
        <div style="position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:#16161f;border:1px solid #f59e0b;
             border-radius:12px;padding:1rem 1.5rem;z-index:99998;box-shadow:0 8px 32px rgba(0,0,0,.5);text-align:center;min-width:280px">
            <div style="color:#f59e0b;font-weight:600;margin-bottom:.4rem"><i class="bi bi-clock-history me-2"></i>Session expiring soon</div>
            <div style="color:#8888aa;font-size:.82rem">You will be logged out in 2 minutes due to inactivity.</div>
            <button onclick="document.getElementById('idleWarning').remove();initAutoLogout(20)"
                style="margin-top:.75rem;background:#f59e0b;border:none;border-radius:8px;padding:.4rem 1.2rem;color:#000;font-weight:600;cursor:pointer;font-size:.85rem">
                Stay Logged In
            </button>
        </div>`;
        document.body.appendChild(d);
        setTimeout(() => { window.location.href = (window.BASE_URL || '') + '/logout.php'; }, 2 * 60 * 1000);
    }

    ['mousemove','keydown','click','scroll','touchstart'].forEach(e =>
        document.addEventListener(e, reset, { passive: true }));
    reset();
}

// ── Live stats refresh ────────────────────────────────────────────────────
function refreshStats() {
    fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc    = parser.parseFromString(html, 'text/html');
            doc.querySelectorAll('.stat-card[data-stat]').forEach(newCard => {
                const key     = newCard.dataset.stat;
                const current = document.querySelector(`.stat-card[data-stat="${key}"] .stat-value`);
                const updated = newCard.querySelector('.stat-value');
                if (current && updated && current.textContent !== updated.textContent) {
                    current.textContent = updated.textContent;
                    current.style.animation = 'none';
                    current.offsetHeight;
                    current.style.animation = 'tbsPulse .4s ease';
                }
            });
        }).catch(() => {});
}

// ── AJAX table search ─────────────────────────────────────────────────────
function searchTable(inputId, tableId) {
    const filter = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}

// ── Format file size ──────────────────────────────────────────────────────
function formatSize(bytes) {
    if (bytes < 1024)    return bytes + ' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
}

// ── Inject global styles ─────────────────────────────────────────────────
(function injectStyles() {
    const s = document.createElement('style');
    s.textContent = `
    @keyframes tbsFadeIn   { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
    @keyframes tbsSlideIn  { from{transform:translateX(20px);opacity:0} to{transform:translateX(0);opacity:1} }
    @keyframes tbsPulse    { 0%,100%{transform:scale(1)} 50%{transform:scale(1.08)} }

    .overlay { position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;display:none; }
    .overlay.show { display:block; }

    .btn-go-back {
        display:inline-flex;align-items:center;gap:.4rem;
        background:var(--surface2,#1e1e2a);border:1px solid var(--border,#2a2a3a);
        color:var(--text-muted,#8888aa);font-size:.82rem;font-weight:500;
        padding:.38rem .85rem;border-radius:8px;cursor:pointer;
        transition:all .2s;white-space:nowrap;
    }
    .btn-go-back:hover { background:var(--surface3,#252535);color:var(--text,#f0f0f5); }

    .btn-icon-wa { background:rgba(37,211,102,.15) !important; color:#25d366 !important; }
    .btn-icon-wa:hover { background:rgba(37,211,102,.3) !important; }
    `;
    document.head.appendChild(s);
})();
