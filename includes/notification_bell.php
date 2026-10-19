<?php
if (!defined('APP_INIT')) { http_response_code(403); exit('Direct access denied.'); }
?>
<!-- Notification Bell -->
<div class="dropdown notification-bell-wrap">
    <a href="#" class="position-relative text-dark text-decoration-none d-flex align-items-center" 
       data-bs-toggle="dropdown" data-bs-auto-close="outside">
        <i class="bi bi-bell fs-5"></i>
        <span class="badge rounded-pill bg-danger notification-badge" id="notifBadge" style="display:none; position:absolute; top:-6px; right:-8px; font-size:.62rem;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end p-0 notification-dropdown" style="width:360px; max-width:90vw;">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <strong style="font-size:.9rem;">Notifications</strong>
            <a href="#" class="small text-decoration-none" id="markAllRead">Mark all read</a>
        </div>
        <div id="notifList" style="max-height:400px; overflow-y:auto;">
            <div class="p-4 text-center text-muted small">
                <div class="spinner-border spinner-border-sm"></div>
                <div class="mt-2">Loading...</div>
            </div>
        </div>
        <div class="border-top p-2 text-center">
            <a href="<?= BASE_URL ?>notifications.php" class="small text-decoration-none">View All</a>
        </div>
    </div>
</div>

<style>
.notification-dropdown .notif-item {
    display:block; padding:12px 16px; border-bottom:1px solid #f1f5f9;
    text-decoration:none; color:#0f172a; transition:background .15s;
}
.notification-dropdown .notif-item:hover { background:#f8fafc; }
.notification-dropdown .notif-item.unread { background:#eff6ff; }
.notification-dropdown .notif-item.unread:hover { background:#dbeafe; }
.notification-dropdown .notif-icon {
    width:34px; height:34px; border-radius:50%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    background:#e0e7ff; color:#3730a3;
}
.notification-dropdown .notif-title { font-weight:600; font-size:.83rem; line-height:1.3; }
.notification-dropdown .notif-msg { font-size:.78rem; color:#64748b; margin-top:2px; line-height:1.3; }
.notification-dropdown .notif-time { font-size:.68rem; color:#94a3b8; margin-top:4px; }
</style>

<script>
(function() {
    const notifBadge = document.getElementById('notifBadge');
    const notifList  = document.getElementById('notifList');

    function iconFor(type) {
        if (!type) return 'bi-bell';
        if (type.includes('payment')) return 'bi-cash-coin';
        if (type.includes('order'))   return 'bi-cart-check';
        if (type.includes('chat'))    return 'bi-chat-dots';
        if (type.includes('deliver')) return 'bi-truck';
        if (type.includes('price'))   return 'bi-tag';
        if (type.includes('shipment'))return 'bi-box-seam';
        return 'bi-bell';
    }

    async function loadNotifications() {
        try {
            const res = await fetch('<?= BASE_URL ?>api/notifications-fetch.php', {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!data.success) return;

            // Update badge
            if (data.unread > 0) {
                notifBadge.textContent = data.unread > 99 ? '99+' : data.unread;
                notifBadge.style.display = '';
            } else {
                notifBadge.style.display = 'none';
            }

            // Render list
            if (data.items.length === 0) {
                notifList.innerHTML = '<div class="p-4 text-center text-muted small">'
                    + '<i class="bi bi-bell-slash fs-4 d-block mb-2 opacity-50"></i>'
                    + 'No notifications</div>';
                return;
            }

            let html = '';
            data.items.forEach(n => {
                const unread = n.is_read == 0 ? 'unread' : '';
                const link = n.link || '#';
                html += `<a href="${link}" class="notif-item ${unread}" data-id="${n.id}">
                    <div class="d-flex gap-2">
                        <div class="notif-icon"><i class="bi ${iconFor(n.type)}"></i></div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="notif-title">${escapeHtml(n.title)}</div>
                            <div class="notif-msg">${escapeHtml(n.message)}</div>
                            <div class="notif-time">${escapeHtml(n.time_ago)}</div>
                        </div>
                    </div>
                </a>`;
            });
            notifList.innerHTML = html;

            // Click handler for items
            notifList.querySelectorAll('.notif-item').forEach(el => {
                el.addEventListener('click', async () => {
                    const id = el.dataset.id;
                    const fd = new FormData();
                    fd.append('action', 'mark_one');
                    fd.append('id', id);
                    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
                    await fetch('<?= BASE_URL ?>api/notifications-mark-read.php', {
                        method: 'POST', body: fd, credentials: 'same-origin'
                    });
                });
            });
        } catch (e) {
            console.error('Notif load failed', e);
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => 
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    document.getElementById('markAllRead').addEventListener('click', async (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('action', 'mark_all');
        fd.append('csrf_token', '<?= e(csrf_token()) ?>');
        await fetch('<?= BASE_URL ?>api/notifications-mark-read.php', {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        loadNotifications();
    });

    // Initial load
    loadNotifications();

    // Polling every 15 sec
    setInterval(loadNotifications, 15000);
})();
</script>