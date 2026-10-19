<?php
/**
 * includes/footer.php
 */
?>
</main>

<footer class="py-5 mt-5" style="background:#0b1220;color:#94a3b8;">
  <div class="container">
    
    <!-- Links Row -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <h6 class="fw-bold text-white mb-3">
          <i class="bi bi-lightning-charge-fill text-warning"></i>
          <?= e(SITE_NAME) ?>
        </h6>
        <p class="small mb-0">
          B2B Ordering, Distribution &amp; Delivery Platform serving Pakistan &amp; Azad Jammu &amp; Kashmir.
        </p>
      </div>
      
      <div class="col-lg-2 col-6">
        <h6 class="fw-bold text-white mb-3">Company</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-2"><a href="<?= BASE_URL ?>about.php" style="color:#94a3b8; text-decoration:none;">About Us</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>contact.php" style="color:#94a3b8; text-decoration:none;">Contact</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>index.php" style="color:#94a3b8; text-decoration:none;">Home</a></li>
        </ul>
      </div>
      
      <div class="col-lg-2 col-6">
        <h6 class="fw-bold text-white mb-3">Legal</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-2"><a href="<?= BASE_URL ?>terms.php" style="color:#94a3b8; text-decoration:none;">Terms of Service</a></li>
          <li class="mb-2"><a href="<?= BASE_URL ?>privacy.php" style="color:#94a3b8; text-decoration:none;">Privacy Policy</a></li>
        </ul>
      </div>
      
      <div class="col-lg-4">
        <h6 class="fw-bold text-white mb-3">Get in Touch</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-2"><i class="bi bi-telephone"></i> <?= e(SITE_PHONE) ?></li>
          <li class="mb-2"><i class="bi bi-envelope"></i> <?= e(SITE_EMAIL) ?></li>
          <li class="mb-2"><i class="bi bi-geo-alt"></i> Pakistan &amp; Azad Jammu &amp; Kashmir</li>
        </ul>
      </div>
    </div>

    <!-- Copyright Row -->
    <hr style="border-color:#1e293b;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small">
      <div>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</div>
      <div class="mt-2 mt-md-0">
        <a href="<?= BASE_URL ?>terms.php" style="color:#94a3b8; text-decoration:none;" class="me-3">Terms</a>
        <a href="<?= BASE_URL ?>privacy.php" style="color:#94a3b8; text-decoration:none;" class="me-3">Privacy</a>
        <a href="<?= BASE_URL ?>contact.php" style="color:#94a3b8; text-decoration:none;">Contact</a>
      </div>
    </div>
    
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Eco Mode Button -->
<button id="ecoModeToggle" class="eco-toggle-btn" onclick="toggleEcoMode()">
    <i class="bi bi-leaf"></i> <span id="ecoBtnText">Eco Mode</span>
</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('ecoMode') === 'true') {
            document.body.classList.add('eco-mode');
            document.getElementById('ecoBtnText').innerText = 'Eco Mode ON';
        }
    });

    function toggleEcoMode() {
        const body = document.body;
        const btnText = document.getElementById('ecoBtnText');
        
        body.classList.toggle('eco-mode');
        
        if (body.classList.contains('eco-mode')) {
            localStorage.setItem('ecoMode', 'true');
            btnText.innerText = 'Eco Mode ON';
        } else {
            localStorage.setItem('ecoMode', 'false');
            btnText.innerText = 'Eco Mode';
        }
    }
</script>
</body>
</html>