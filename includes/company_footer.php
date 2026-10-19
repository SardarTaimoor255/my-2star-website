<?php ?>
    </main>
    
    <footer class="text-center py-3" style="background: var(--bg-main); border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.8rem;">
        <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></div>
        <div style="font-size: .7rem;">Company Portal</div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('companySidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        document.querySelectorAll('button[onclick*="companySidebar"]').forEach(function(btn) {
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
async function companyAction(action, id, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    for (const k in extra) fd.append(k, extra[k]);
    const res = await fetch('<?= BASE_URL ?>api/company-action.php', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}
function doCompanyAction(btn, action, id, extra = {}, msg = 'Are you sure?') {
    if (msg && !confirm(msg)) return;
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    companyAction(action, id, extra).then(r => {
        if (r.success) location.reload();
        else { alert(r.message || 'Failed'); btn.disabled = false; btn.innerHTML = orig; }
    }).catch(() => { alert('Network error'); btn.disabled = false; btn.innerHTML = orig; });
}
</script>
</body>
</html>