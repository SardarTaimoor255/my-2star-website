<?php ?>
    </main>
    <footer class="text-center text-muted small py-3 border-top bg-white px-2">
    <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></div>
    <div style="font-size: .7rem;">Vendor Portal</div>
</footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Mobile Sidebar Overlay
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('vendorSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        document.querySelectorAll('button[onclick*="vendorSidebar"]').forEach(function(btn) {
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
async function vendorAction(action, id, extra = {}) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');
    for (const k in extra) fd.append(k, extra[k]);
    const res = await fetch('<?= BASE_URL ?>api/vendor-action.php', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}

function doVendorAction(btn, action, id, extra = {}, confirmMsg = 'Are you sure?') {
    if (confirmMsg && !confirm(confirmMsg)) return;
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    vendorAction(action, id, extra).then(r => {
        if (r.success) {
            location.reload();
        } else {
            alert(r.message || 'Action failed');
            btn.disabled = false; btn.innerHTML = orig;
        }
    }).catch(() => {
        alert('Network error'); btn.disabled = false; btn.innerHTML = orig;
    });
}
</script>
</body>
</html>