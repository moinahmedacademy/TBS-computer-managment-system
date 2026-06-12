// Sidebar Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('d-block');
    } else {
        const wrapper = document.getElementById('mainWrapper');
        if (sidebar.style.transform === 'translateX(-100%)') {
            sidebar.style.transform = '';
            wrapper.style.marginLeft = 'var(--sidebar-w)';
        } else {
            sidebar.style.transform = 'translateX(-100%)';
            wrapper.style.marginLeft = '0';
        }
    }
}

// Create overlay on load
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.createElement('div');
    overlay.className = 'overlay';
    overlay.id = 'overlay';
    overlay.onclick = () => {
        document.getElementById('sidebar').classList.remove('show');
        overlay.classList.remove('d-block');
    };
    document.body.appendChild(overlay);

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert.alert-dismissible').forEach(a => {
            const bsAlert = new bootstrap.Alert(a);
            bsAlert.close();
        });
    }, 5000);
});

// Confirm delete
function confirmDelete(form) {
    if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
        form.submit();
    }
}

// AJAX search table
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}

// Format file size
function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
}

// WhatsApp message opener
function openWhatsApp(phone, message) {
    const clean = phone.replace(/[^0-9]/g, '');
    const num = clean.startsWith('0') ? '92' + clean.slice(1) : clean;
    const url = 'https://wa.me/' + num + '?text=' + encodeURIComponent(message);
    window.open(url, '_blank');
}

// Copy to clipboard
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    });
}

// Toast notification
function showToast(msg, type = 'info') {
    const wrapper = document.getElementById('toast-wrapper') || (() => {
        const d = document.createElement('div');
        d.id = 'toast-wrapper';
        d.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem';
        document.body.appendChild(d);
        return d;
    })();

    const colors = { success:'#10b981', danger:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const t = document.createElement('div');
    t.style.cssText = `background:#16161f;border:1px solid #2a2a3a;border-left:3px solid ${colors[type]||colors.info};
        color:#f0f0f5;padding:.7rem 1rem;border-radius:8px;font-size:.875rem;
        box-shadow:0 4px 16px rgba(0,0,0,.4);animation:slideIn .2s ease;min-width:220px;`;
    t.textContent = msg;
    wrapper.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

// Animate stat counters
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count);
        let current = 0;
        const step = Math.ceil(target / 40);
        const timer = setInterval(() => {
            current = Math.min(current + step, target);
            el.textContent = current.toLocaleString();
            if (current >= target) clearInterval(timer);
        }, 25);
    });
});

const style = document.createElement('style');
style.textContent = `@keyframes slideIn { from { transform: translateX(20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }`;
document.head.appendChild(style);
