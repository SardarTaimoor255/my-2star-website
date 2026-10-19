<?php
/**
 * includes/shop_footer.php
 */
?>
    </main>
    <footer class="text-center text-muted small py-3 border-top bg-white px-2">
    <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></div>
    <div style="font-size: .7rem;">Shop Portal</div>
</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof GLightbox !== 'undefined') {
        GLightbox({
            selector: '.glightbox',
            touchNavigation: true,
            loop: false,
            zoomable: true,
            draggable: true,
        });
    }
});
</script>
<script>
// Mobile Sidebar Overlay
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('shopSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        // Hamburger button find karein
        document.querySelectorAll('button[onclick*="shopSidebar"]').forEach(function(btn) {
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
        
        // Overlay click par sidebar band
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
        
        // Navigation link click par bhi band
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
async function cartAction(action, productId, qty = 1) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('product_id', productId);
    fd.append('qty', qty);
    fd.append('csrf_token', '<?= e(csrf_token()) ?>');

    const res = await fetch('<?= BASE_URL ?>api/cart-action.php', {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    return res.json();
}

function addToCart(btn, productId, qty = 1) {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    cartAction('add', productId, qty).then(r => {
        if (r.success) {
            location.reload();
        } else {
            alert(r.message || 'Failed');
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });
}
</script>
</body>
</html>