<?php
/**
 * includes/admin_footer.php
 */
?>
    </main>

    <footer class="text-center py-3" style="background: var(--bg-main); border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.8rem;">
        <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></div>
        <div style="font-size: .7rem;">Admin Panel</div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        document.querySelectorAll('button[onclick*="adminSidebar"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                setTimeout(function() {
                    if (sidebar.classList.contains('show')) {
                        overlay.classList.add('show');
                    } else {
                        overlay.classList.remove('show');
                    }
                }, 10);
            });
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
        
        sidebar.querySelectorAll('a.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        });
    }
});
</script>
<script>
async function adminAction(action, id, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    for (const k in extra) fd.append(k, extra[k]);

    const res = await fetch('<?= BASE_URL ?>api/admin-action.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}

function handleApprove(btn, action, id, extra = {}) {
    const confirmMsg = {
        approve_user: 'Approve this account?',
        reject_user:  'Reject this account?',
        toggle_user:  'Change status of this account?'
    }[action] || 'Are you sure?';

    if (!confirm(confirmMsg)) return;

    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    adminAction(action, id, extra).then(r => {
        if (r.success) {
            location.reload();
        } else {
            alert(r.message || 'Action failed');
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }).catch(() => {
        alert('Network error');
        btn.disabled = false;
        btn.innerHTML = original;
    });
}
</script>
</body>
</html>