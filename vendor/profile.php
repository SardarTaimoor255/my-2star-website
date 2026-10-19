<?php
require_once dirname(__DIR__) . '/includes/init.php';
require_role($pdo, ROLE_VENDOR);

$user = current_user($pdo);
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$vendor = $stmt->fetch();

// ==========================================
// 1. HANDLE PROFILE PICTURE UPLOAD
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    csrf_require($_POST['csrf_token'] ?? null);
    
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        $uploadDir = UPLOADS_PATH . '/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = 'user_' . $user['id'] . '_' . time() . '.' . $ext;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename)) {
            // Delete old avatar if exists
            if (!empty($user['avatar']) && file_exists($uploadDir . $user['avatar'])) {
                unlink($uploadDir . $user['avatar']);
            }
            
            // Update DB
            $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$filename, $user['id']]);
            flash_success('Profile picture updated successfully!');
            redirect(BASE_URL . 'vendor/profile.php'); 
        }
    } else {
        flash_error('Invalid image format. Use JPG, PNG, or WebP.');
    }
}

// ==========================================
// 2. HANDLE TEXT DETAILS UPDATE
// ==========================================
// ==========================================
// 2. HANDLE TEXT DETAILS UPDATE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    csrf_require($_POST['csrf_token'] ?? null);

    $fullName = clean($_POST['full_name'] ?? '');
    $email    = clean($_POST['email'] ?? '');
    $phone    = clean($_POST['phone'] ?? '');
    $business = clean($_POST['business_name'] ?? '');
    $address  = clean($_POST['address'] ?? '');
    $commType = $_POST['commission_type'] ?? 'fixed';
    $commVal  = (float)($_POST['commission_value'] ?? 500);

    if ($fullName === '' || $business === '' || $email === '') {
        flash_error('Name, Email, and Business name are required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_error('Please enter a valid email address.');
    } else {
        // Check karein ke naya email kisi aur user ke paas toh nahi hai
        $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->execute([$email, $user['id']]);
        
        if ($checkEmail->fetch()) {
            flash_error('This email is already registered by another user.');
        } else {
            // Sab kuch theek hai, ab update karein
            $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?")
                ->execute([$fullName, $email, $phone, $user['id']]);
                
            $pdo->prepare("UPDATE vendors SET business_name = ?, address = ?, default_commission_type = ?, default_commission_value = ? WHERE id = ?")
                ->execute([$business, $address, $commType, $commVal, $vendor['id']]);
                
            flash_success('Profile updated successfully.');
            redirect(BASE_URL . 'vendor/profile.php');
        }
    }
}

$pageTitle = 'Profile';
require_once dirname(__DIR__) . '/includes/vendor_header.php';
?>

<style>
    /* Fix Dark Theme Inputs */
    .form-control, .form-select {
        background-color: #0f111a !important;
        border: 1px solid #272a37 !important;
        color: #ffffff !important;
    }
    .form-control:focus, .form-select:focus {
        background-color: #141622 !important;
        border-color: #a78bfa !important;
        box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.2) !important;
        color: #ffffff !important;
    }
    .form-control:disabled {
        background-color: #1a1c29 !important;
        color: #64748b !important;
    }
    /* Avatar Image Style */
    .profile-avatar {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid #a78bfa;
        padding: 3px;
        background: #0f111a;
        margin-bottom: 15px;
    }
</style>

<h2 class="fw-bold mb-1" style="color:#fff;">Profile</h2>
<p class="text-muted small mb-4">Manage your business information and profile picture</p>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-soft p-4">
            <form method="post">
                <?= csrf_field() ?>

                <h6 class="fw-bold mb-3" style="color:#fff;">Account</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
    <label class="form-label small text-muted">Email Address</label>
    <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
</div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                    </div>
                </div>

                <h6 class="fw-bold mb-3" style="color:#fff;">Business</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-12">
                        <label class="form-label small text-muted">Business Name</label>
                        <input type="text" name="business_name" class="form-control" value="<?= e($vendor['business_name']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= e($vendor['address'] ?? '') ?>">
                    </div>
                </div>

                <h6 class="fw-bold mb-3" style="color:#fff;">Default Commission</h6>
                <p class="small text-muted">This is the default commission added to every product unless overridden.</p>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Type</label>
                        <select name="commission_type" class="form-select">
                            <option value="fixed" <?= $vendor['default_commission_type']==='fixed'?'selected':'' ?>>Fixed (PKR per unit)</option>
                            <option value="percentage" <?= $vendor['default_commission_type']==='percentage'?'selected':'' ?>>Percentage (%)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Value</label>
                        <input type="number" step="0.01" name="commission_value" class="form-control" value="<?= e($vendor['default_commission_value']) ?>">
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-soft p-4 text-center">
            <!-- Profile Picture Display -->
            <?php 
            $avatarUrl = !empty($user['avatar']) ? UPLOADS_URL . 'avatars/' . $user['avatar'] : null; 
            ?>
            <?php if ($avatarUrl && file_exists(UPLOADS_PATH . '/avatars/' . $user['avatar'])): ?>
                <img src="<?= $avatarUrl ?>" alt="Profile Picture" class="profile-avatar">
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center mb-3" style="width: 120px; height: 120px; border-radius: 50%; background: #0f111a; border: 3px solid #272a37; margin: 0 auto;">
                    <i class="bi bi-person fs-1 text-muted"></i>
                </div>
            <?php endif; ?>

            <h6 class="fw-bold mb-1" style="color:#fff;"><?= e($vendor['business_name']) ?></h6>
            <div class="small text-muted mb-3"><?= e($user['email']) ?></div>
            
            <!-- Upload Form -->
            <form method="post" enctype="multipart/form-data" class="mt-4 pt-3 border-top border-secondary">
                <?= csrf_field() ?>
                <label class="form-label small text-muted">Update Profile Picture</label>
                <input type="file" name="avatar" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp" required>
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" style="border-color:#a78bfa; color:#a78bfa;">
                    <i class="bi bi-cloud-arrow-up"></i> Upload Image
                </button>
            </form>

            <hr class="border-secondary mt-4">
            
            <div class="small text-start">
                <div class="mb-1 text-muted"><strong>Default Commission:</strong> <?= e($vendor['default_commission_type']) ?> <?= e($vendor['default_commission_value']) ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/vendor_footer.php'; ?>